<?php
declare(strict_types=1);

namespace app\controller\admin\v1;

use app\service\admin\AdminException;
use app\service\admin\Guard;
use app\service\admin\Resp;
use think\facade\Db;

/**
 * CmsController —— CMS 管理（契约 §8.13，18 个接口 / 5 组）
 *
 * 数据载体：
 *   Banner   banner 表（link↔url / sort_order↔sort / status↔is_show）+ show_time_start/end 时间窗
 *   公告     cms_announcement（type 1=系统 2=活动 3=维护；status 0=草稿 1=已发布 2=已下线）
 *   协议     cms_agreement（每类型一份，uk_type）
 *   文物展览 cms_artifact
 *   装修     site_config(cms.decoration) 部分更新（未传键保留原值）
 *
 * @package app\controller\admin\v1
 */
final class CmsController
{
    use V1;

    // ========================================================
    // 8.13.1 Banner（134~138）
    // ========================================================

    /** GET /cms/banners（134） */
    public function bannerList()
    {
        self::guard('cms:banner:list');
        [$page, $size] = self::paging();
        $q = Db::name('banner');
        if (self::str('status') !== '') {
            $q->where('is_show', self::int('status') === 1 ? 1 : 0);
        }
        $q->order('sort', 'desc')->order('id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(fn ($r) => $this->bannerBrief($r), $rows);
        return Resp::page($list, $total, $page, $size);
    }

    /** POST /cms/banners（135） */
    public function bannerCreate()
    {
        $admin = self::guard('cms:banner:create');
        self::requireFields(['image', 'link']);
        $now = time();
        $id = (int) Db::name('banner')->insertGetId([
            'title' => mb_substr(self::str('title'), 0, 128),
            'image' => self::str('image'),
            'url' => self::str('link'),
            'url_type' => self::int('link_type') === 2 ? 2 : 1,
            'sort' => self::int('sort_order'),
            'is_show' => self::int('status', 1) === 1 ? 1 : 0,
            'show_time_start' => $this->timeParam('show_time_start'),
            'show_time_end' => $this->timeParam('show_time_end'),
            'createtime' => $now,
            'updatetime' => $now,
        ]);
        Guard::audit('cms_banner_create', (int) $admin['id'], $id, ['image' => self::str('image')]);
        return Resp::ok(['id' => $id]);
    }

    /** GET /cms/banners/:id（136） */
    public function bannerDetail()
    {
        self::guard('cms:banner:detail');
        $r = Db::name('banner')->where('id', self::int('id'))->find();
        if ($r === null) {
            throw new AdminException('Banner 不存在', Resp::NOT_FOUND);
        }
        return Resp::ok($this->bannerBrief($r) + ['title' => (string) $r['title']]);
    }

    /** PUT /cms/banners/:id（137） */
    public function bannerEdit()
    {
        $admin = self::guard('cms:banner:edit');
        $id = self::int('id');
        $r = Db::name('banner')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('Banner 不存在', Resp::NOT_FOUND);
        }
        $upd = [];
        if (self::str('image') !== '') {
            $upd['image'] = self::str('image');
        }
        if (self::str('link') !== '') {
            $upd['url'] = self::str('link');
        }
        if (self::str('sort_order') !== '') {
            $upd['sort'] = self::int('sort_order');
        }
        if (self::str('status') !== '') {
            $upd['is_show'] = self::int('status') === 1 ? 1 : 0;
        }
        if (self::str('show_time_start') !== '') {
            $upd['show_time_start'] = $this->timeParam('show_time_start');
        }
        if (self::str('show_time_end') !== '') {
            $upd['show_time_end'] = $this->timeParam('show_time_end');
        }
        if ($upd !== []) {
            $upd['updatetime'] = time();
            Db::name('banner')->where('id', $id)->update($upd);
        }
        Guard::audit('cms_banner_edit', (int) $admin['id'], $id, $upd);
        return Resp::ok(['success' => true]);
    }

    /** DELETE /cms/banners/:id（138） */
    public function bannerDelete()
    {
        $admin = self::guard('cms:banner:delete');
        $id = self::int('id');
        $r = Db::name('banner')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('Banner 不存在', Resp::NOT_FOUND);
        }
        Db::name('banner')->where('id', $id)->delete();
        Guard::audit('cms_banner_delete', (int) $admin['id'], $id, ['image' => (string) $r['image']]);
        return Resp::ok(['success' => true]);
    }

    // ========================================================
    // 8.13.2 公告（139~143）
    // ========================================================

    /** GET /cms/announcements（139） */
    public function announcementList()
    {
        self::guard('cms:announcement:list');
        [$page, $size] = self::paging();
        $q = Db::name('cms_announcement');
        if (self::str('type') !== '') {
            $q->where('type', self::int('type'));
        }
        if (self::str('status') !== '') {
            $q->where('status', self::int('status'));
        }
        $q->order('is_top', 'desc')->order('id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(fn ($r) => $this->announcementBrief($r), $rows);
        return Resp::page($list, $total, $page, $size);
    }

    /** POST /cms/announcements（140） */
    public function announcementCreate()
    {
        $admin = self::guard('cms:announcement:create');
        self::requireFields(['title', 'content', 'type']);
        $type = self::int('type');
        if ($type < 1 || $type > 3) {
            throw new AdminException('type 不合法（1=系统 2=活动 3=维护）');
        }
        $now = time();
        $id = (int) Db::name('cms_announcement')->insertGetId([
            'title' => mb_substr(self::str('title'), 0, 128),
            'content' => self::str('content'),
            'type' => $type,
            'is_top' => self::int('is_top') === 1 ? 1 : 0,
            'status' => in_array(self::int('status'), [0, 1, 2], true) ? self::int('status') : 0,
            'publish_time' => $this->timeParam('publish_time'),
            'createtime' => $now,
            'updatetime' => $now,
        ]);
        Guard::audit('cms_announcement_create', (int) $admin['id'], $id, ['title' => self::str('title')]);
        return Resp::ok(['id' => $id]);
    }

    /** GET /cms/announcements/:id（141） */
    public function announcementDetail()
    {
        self::guard('cms:announcement:detail');
        $r = Db::name('cms_announcement')->where('id', self::int('id'))->find();
        if ($r === null) {
            throw new AdminException('公告不存在', Resp::NOT_FOUND);
        }
        return Resp::ok($this->announcementBrief($r));
    }

    /** PUT /cms/announcements/:id（142） */
    public function announcementEdit()
    {
        $admin = self::guard('cms:announcement:edit');
        $id = self::int('id');
        $r = Db::name('cms_announcement')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('公告不存在', Resp::NOT_FOUND);
        }
        $upd = [];
        if (self::str('title') !== '') {
            $upd['title'] = mb_substr(self::str('title'), 0, 128);
        }
        if (self::str('content') !== '') {
            $upd['content'] = self::str('content');
        }
        if (self::str('type') !== '') {
            $t = self::int('type');
            if ($t < 1 || $t > 3) {
                throw new AdminException('type 不合法（1=系统 2=活动 3=维护）');
            }
            $upd['type'] = $t;
        }
        if (self::str('is_top') !== '') {
            $upd['is_top'] = self::int('is_top') === 1 ? 1 : 0;
        }
        if (self::str('status') !== '') {
            $upd['status'] = in_array(self::int('status'), [0, 1, 2], true) ? self::int('status') : (int) $r['status'];
        }
        if (self::str('publish_time') !== '') {
            $upd['publish_time'] = $this->timeParam('publish_time');
        }
        if ($upd !== []) {
            $upd['updatetime'] = time();
            Db::name('cms_announcement')->where('id', $id)->update($upd);
        }
        Guard::audit('cms_announcement_edit', (int) $admin['id'], $id, $upd);
        return Resp::ok(['success' => true]);
    }

    /** DELETE /cms/announcements/:id（143） */
    public function announcementDelete()
    {
        $admin = self::guard('cms:announcement:delete');
        $id = self::int('id');
        $r = Db::name('cms_announcement')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('公告不存在', Resp::NOT_FOUND);
        }
        Db::name('cms_announcement')->where('id', $id)->delete();
        Guard::audit('cms_announcement_delete', (int) $admin['id'], $id, ['title' => (string) $r['title']]);
        return Resp::ok(['success' => true]);
    }

    // ========================================================
    // 8.13.3 协议（144~145）
    // ========================================================

    /** GET /cms/agreements（144） */
    public function agreementList()
    {
        self::guard('cms:agreement:list');
        $q = Db::name('cms_agreement')->where('status', 1);
        if (self::str('type') !== '') {
            $q->where('type', self::str('type'));
        }
        $rows = $q->order('id')->select()->toArray();
        $list = array_map(static fn ($r) => [
            'id'          => (int) $r['id'],
            'type'        => (string) $r['type'],
            'title'       => (string) $r['title'],
            'content'     => (string) ($r['content'] ?? ''),
            'version'     => (string) $r['version'],
            'status'      => (int) $r['status'],
            'update_time' => self::iso((int) $r['updatetime']),
        ], $rows);
        return Resp::ok(['list' => $list]);
    }

    /** PUT /cms/agreements/:id（145） */
    public function agreementEdit()
    {
        $admin = self::guard('cms:agreement:edit');
        $id = self::int('id');
        $r = Db::name('cms_agreement')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('协议不存在', Resp::NOT_FOUND);
        }
        $upd = [];
        if (self::str('title') !== '') {
            $upd['title'] = mb_substr(self::str('title'), 0, 128);
        }
        if (self::str('content') !== '') {
            $upd['content'] = self::str('content');
        }
        if (self::str('version') !== '') {
            $upd['version'] = mb_substr(self::str('version'), 0, 32);
        }
        if ($upd !== []) {
            $upd['updatetime'] = time();
            Db::name('cms_agreement')->where('id', $id)->update($upd);
        }
        Guard::audit('cms_agreement_edit', (int) $admin['id'], $id, ['version' => (string) ($upd['version'] ?? $r['version'])]);
        return Resp::ok(['success' => true]);
    }

    // ========================================================
    // 8.13.4 文物展览（146~149）
    // ========================================================

    /** GET /cms/artifacts（146） */
    public function artifactList()
    {
        self::guard('cms:artifact:list');
        [$page, $size] = self::paging();
        $q = Db::name('cms_artifact');
        $q->order('sort', 'desc')->order('id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(static fn ($r) => [
            'id'           => (int) $r['id'],
            'name'         => (string) $r['name'],
            'dynasty'      => (string) $r['dynasty'],
            'material'     => (string) $r['material'],
            'image'        => (string) $r['image'],
            'description'  => (string) ($r['description'] ?? ''),
            'status'       => (int) $r['status'],
            'create_time'  => self::iso((int) $r['createtime']),
        ], $rows);
        return Resp::page($list, $total, $page, $size);
    }

    /** POST /cms/artifacts（147） */
    public function artifactCreate()
    {
        $admin = self::guard('cms:artifact:create');
        self::requireFields(['name', 'image']);
        $now = time();
        $id = (int) Db::name('cms_artifact')->insertGetId([
            'name' => mb_substr(self::str('name'), 0, 128),
            'dynasty' => mb_substr(self::str('dynasty'), 0, 64),
            'material' => mb_substr(self::str('material'), 0, 64),
            'image' => self::str('image'),
            'description' => self::str('description'),
            'sort' => self::int('sort_order'),
            'status' => self::int('status', 1) === 1 ? 1 : 0,
            'createtime' => $now,
            'updatetime' => $now,
        ]);
        Guard::audit('cms_artifact_create', (int) $admin['id'], $id, ['name' => self::str('name')]);
        return Resp::ok(['id' => $id]);
    }

    /** PUT /cms/artifacts/:id（148） */
    public function artifactEdit()
    {
        $admin = self::guard('cms:artifact:edit');
        $id = self::int('id');
        $r = Db::name('cms_artifact')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('文物不存在', Resp::NOT_FOUND);
        }
        $upd = [];
        foreach (['name', 'dynasty', 'material', 'image', 'description'] as $k) {
            if (self::str($k) !== '') {
                $upd[$k] = self::str($k);
            }
        }
        if (self::str('sort_order') !== '') {
            $upd['sort'] = self::int('sort_order');
        }
        if (self::str('status') !== '') {
            $upd['status'] = self::int('status') === 1 ? 1 : 0;
        }
        if ($upd !== []) {
            $upd['updatetime'] = time();
            Db::name('cms_artifact')->where('id', $id)->update($upd);
        }
        Guard::audit('cms_artifact_edit', (int) $admin['id'], $id, $upd);
        return Resp::ok(['success' => true]);
    }

    /** DELETE /cms/artifacts/:id（149） */
    public function artifactDelete()
    {
        $admin = self::guard('cms:artifact:delete');
        $id = self::int('id');
        $r = Db::name('cms_artifact')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('文物不存在', Resp::NOT_FOUND);
        }
        Db::name('cms_artifact')->where('id', $id)->delete();
        Guard::audit('cms_artifact_delete', (int) $admin['id'], $id, ['name' => (string) $r['name']]);
        return Resp::ok(['success' => true]);
    }

    // ========================================================
    // 8.13.5 网页装修（150~151）
    // ========================================================

    /** GET /cms/decoration（150） */
    public function decorationView()
    {
        self::guard('cms:decoration:view');
        return Resp::ok($this->readConfig('cms.decoration', [
            'theme_color' => '', 'button_color' => '', 'background_gradient' => '',
            'site_name' => '', 'site_logo' => '', 'sections' => [],
        ]));
    }

    /** PUT /cms/decoration（151） */
    public function decorationEdit()
    {
        $admin = self::guard('cms:decoration:edit');
        $keys = ['theme_color', 'button_color', 'background_gradient', 'site_name', 'site_logo', 'sections'];
        $current = $this->readConfig('cms.decoration', []);
        $upd = [];
        foreach ($keys as $k) {
            $v = request()->post($k);
            if ($v !== null) {
                $upd[$k] = is_array($v) ? $v : (string) $v;
            }
        }
        if ($upd === []) {
            throw new AdminException('无可更新字段');
        }
        $this->writeConfig('cms.decoration', 'cms', array_merge($current, $upd));
        Guard::audit('cms_decoration_edit', (int) $admin['id'], 0, array_keys($upd));
        return Resp::ok(['success' => true]);
    }

    // --------------------------------------------------------

    /** Banner 行 → 输出（url→link / sort→sort_order / is_show→status） */
    private function bannerBrief(array $r): array
    {
        return [
            'id'               => (int) $r['id'],
            'image'            => (string) $r['image'],
            'link'             => (string) $r['url'],
            'sort_order'       => (int) $r['sort'],
            'status'           => (int) $r['is_show'],
            'show_time_start'  => self::iso((int) ($r['show_time_start'] ?? 0)),
            'show_time_end'    => self::iso((int) ($r['show_time_end'] ?? 0)),
            'create_time'      => self::iso((int) $r['createtime']),
        ];
    }

    /** 公告行 → 输出 */
    private function announcementBrief(array $r): array
    {
        return [
            'id'           => (int) $r['id'],
            'title'        => (string) $r['title'],
            'content'      => (string) ($r['content'] ?? ''),
            'type'         => (int) $r['type'],
            'is_top'       => (int) $r['is_top'],
            'status'       => (int) $r['status'],
            'publish_time' => self::iso((int) $r['publish_time']),
            'create_time'  => self::iso((int) $r['createtime']),
        ];
    }

    /** site_config 读（JSON 解析，缺省兜底） */
    private function readConfig(string $key, array $defaults): array
    {
        $raw = Db::name('site_config')->where('key', $key)->value('value');
        $cfg = json_decode((string) $raw, true);
        if (!is_array($cfg)) {
            $cfg = [];
        }
        return $cfg + $defaults;
    }

    /** site_config 写（KV upsert） */
    private function writeConfig(string $key, string $group, array $value): void
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        $now = time();
        $exists = Db::name('site_config')->where('key', $key)->find();
        if ($exists === null) {
            Db::name('site_config')->insert([
                'key' => $key, 'value' => $json, 'group_name' => $group,
                'remark' => '', 'updatetime' => $now,
            ]);
        } else {
            Db::name('site_config')->where('key', $key)->update(['value' => $json, 'updatetime' => $now]);
        }
    }

    /** 时间参数：unixtime 数字或日期字符串 → unixtime（空 → 0） */
    private function timeParam(string $key): int
    {
        $v = self::str($key);
        if ($v === '') {
            return 0;
        }
        return is_numeric($v) ? (int) $v : (int) strtotime($v);
    }
}
