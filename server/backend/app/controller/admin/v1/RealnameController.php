<?php
declare(strict_types=1);

namespace app\controller\admin\v1;

use app\service\admin\AdminException;
use app\service\admin\Resp;
use app\service\RealnameService;
use think\facade\Db;

/**
 * RealnameController —— 实名认证管理（契约 §8.4，4 个接口）
 *
 * §3.7 实名信息安全红线：
 *   1. 列表/详情只出脱敏（手机号/姓名/证件号），明文绝不进响应体
 *   2. 完整查看必须二次密码验证，且每次查看写审计（谁/何时/查了谁）
 *   3. 只读模块：不提供修改/删除
 *
 * @package app\controller\admin\v1
 */
final class RealnameController
{
    use V1;

    /** GET /realnames */
    public function list()
    {
        self::guard('realname:list');
        [$page, $size] = self::paging();

        $q = Db::name('user')->where('real_name_authentication', 1);
        $name = self::str('name');
        if ($name !== '') {
            $q->whereLike('real_name', '%' . $name . '%');
        }
        $phone = self::str('phone');
        if ($phone !== '') {
            $q->whereLike('mobile', '%' . $phone . '%');
        }
        [$s, $e] = self::dateRange();
        if ($s > 0) {
            $q->where('real_name_time', '>=', $s);
        }
        if ($e > 0) {
            $q->where('real_name_time', '<', $e);
        }

        $total = (clone $q)->count();
        $rows = $q->order('real_name_time', 'desc')->page($page, $size)
            ->field('id,real_name,real_name_enc,id_card,id_card_enc,mobile,real_name_time,createtime')
            ->select()->toArray();

        // 密文列解密一次后统一脱敏，明文绝不出响应体
        $list = array_map(function ($r) {
            return [
                'id'           => (int) $r['id'],
                'user_id'      => (int) $r['id'],
                'name_masked'  => RealnameService::maskName(self::plainName($r)),
                'idcard_masked' => RealnameService::maskIdCard(self::plainIdCard($r)),
                'phone_masked' => self::maskPhone((string) $r['mobile']),
                'status'       => 1,
                'submit_time'  => self::iso((int) $r['real_name_time']),
            ];
        }, $rows);

        return Resp::page($list, $total, $page, $size);
    }

    /** GET /realnames/:id —— 脱敏详情 */
    public function detail()
    {
        self::guard('realname:view');
        $id = self::int('id');
        $u = Db::name('user')->where('id', $id)->where('real_name_authentication', 1)->find();
        if ($u === null) {
            throw new AdminException('实名记录不存在', Resp::NOT_FOUND);
        }
        return Resp::ok([
            'user_id'      => (int) $u['id'],
            'name_masked'  => RealnameService::maskName(self::plainName($u)),
            'idcard_masked' => RealnameService::maskIdCard(self::plainIdCard($u)),
            'phone_masked' => self::maskPhone((string) $u['mobile']),
            'status'       => 1,
            'submit_time'  => self::iso((int) $u['real_name_time']),
        ]);
    }

    /** POST /realnames/:id/full —— 完整查看（二次密码验证 + 审计） */
    public function full()
    {
        $admin = self::guard('realname:full');
        $id = self::int('id');
        self::verifyPassword($admin);

        $u = Db::name('user')->where('id', $id)->where('real_name_authentication', 1)->find();
        if ($u === null) {
            throw new AdminException('实名记录不存在', Resp::NOT_FOUND);
        }

        // 审计先行：任何完整查看必须留痕
        \app\service\admin\Guard::audit('realname_full_view', (int) $admin['id'], $id, [
            'target_user' => $id, 'time' => date('Y-m-d H:i:s'),
        ]);

        $revealed = RealnameService::reveal($id);
        return Resp::ok([
            'user_id'     => (int) $u['id'],
            'name'        => $revealed['real_name'] ?? self::plainName($u),
            'idcard'      => $revealed['id_card'] ?? self::plainIdCard($u),
            'phone'       => (string) $u['mobile'],
            'idcard_front' => (string) ($u['id_card_front'] ?? ''),
            'idcard_back' => (string) ($u['id_card_back'] ?? ''),
            'status'      => 1,
            'submit_time' => self::iso((int) $u['real_name_time']),
        ]);
    }

    /** GET /realnames/:id/audit-logs —— 实名查看审计日志 */
    public function auditLogs()
    {
        self::guard('realname:audit');
        [$page, $size] = self::paging();
        $id = self::int('id');

        $q = Db::name('audit_log')->where('action', 'realname_full_view');
        $q->where(function ($query) use ($id) {
            $query->where('target_id', (string) $id)
                  ->whereOr('params', 'like', '%"target_user":' . $id . '%');
        });
        $total = (clone $q)->count();
        $rows = $q->order('id', 'desc')->page($page, $size)->select()->toArray();

        return Resp::page(array_map(fn ($r) => [
            'id' => (int) $r['id'], 'admin' => $r['username'],
            'ip' => (string) $r['ip'], 'time' => self::iso((int) $r['createtime']),
            'detail' => json_decode((string) $r['params'], true) ?: [],
        ], $rows), $total, $page, $size);
    }

    // --------------------------------------------------------

    /** 加密列解密出姓名；解密失败回落到存储列（可能已是明文） */
    private static function plainName(array $u): string
    {
        if ((string) ($u['real_name_enc'] ?? '') !== '') {
            try {
                $revealed = RealnameService::reveal((int) $u['id']);
                if (($revealed['real_name'] ?? '') !== '') {
                    return (string) $revealed['real_name'];
                }
            } catch (\Throwable) {
                // 落到存储列
            }
        }
        return (string) ($u['real_name'] ?? '');
    }

    private static function plainIdCard(array $u): string
    {
        if ((string) ($u['id_card_enc'] ?? '') !== '') {
            try {
                $revealed = RealnameService::reveal((int) $u['id']);
                if (($revealed['id_card'] ?? '') !== '') {
                    return (string) $revealed['id_card'];
                }
            } catch (\Throwable) {
                // 落到存储列
            }
        }
        return (string) ($u['id_card'] ?? '');
    }
}
