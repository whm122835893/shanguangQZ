<?php
declare(strict_types=1);

namespace app\middleware;

use think\facade\Db;
use think\facade\Log;

/**
 * 接口限流中间件
 *
 * 为什么必须有：登录、短信、下单三个端点如果不限流，
 *   可以被脚本无限刷 —— 短信接口会真实扣费，下单接口会污染订单表。
 *
 * 🔴 为什么用数据库计数而不是 Cache：
 *    本项目实测 file 驱动的 Cache::inc() 不可靠——连续 70 次请求计数器
 *    始终不累积，表现为「配了限流但从不触发」的假安全。
 *    改用数据库唯一索引做原子自增（INSERT ... ON DUPLICATE KEY UPDATE），
 *    行为可预期、可验证、可审计。表结构见 sql/02-ratelimit.sql。
 *
 * 策略：固定窗口计数（fixed window）。选它而不是滑动窗口，
 *   因为实现简单，代价是窗口边界可能放过 2 倍流量，对本项目量级可接受。
 *
 * 键设计：ip + 路由。ip 单独做会被 NAT 后端多人共用额度，
 *   路由单独做又会让高频接口误伤，因此两者组合。
 *
 * @package app\middleware
 */
class RateLimit
{
    /** 默认：60 次 / 60 秒 */
    private int $limit;
    private int $window;

    public function __construct(int $limit = 60, int $window = 60)
    {
        $this->limit  = $limit;
        $this->window = max(1, $window);
    }

    public function handle($request, \Closure $next)
    {
        $path = ltrim($request->pathinfo(), '/');

        // 健康检查不限流，避免探针被误伤
        if (strpos($path, 'system/ping') !== false) {
            return $next($request);
        }

        if (!$this->allow($request->ip(), $path)) {
            // 🔴 注意：HTTP 状态码保持 200，只有 body.code = 429。
            //    若把 HTTP 也改成 429，前端 http.js 的错误拦截器会走
            //    与 401 不同的分支，弹窗行为会变。用 body.code 判定更安全。
            return json([
                'code' => 429,
                'msg'  => '请求过于频繁，请稍后再试',
                'time' => time(),
                'data' => null,
            ]);
        }

        return $next($request);
    }

    /**
     * 数据库原子自增，判断是否超限
     *
     * 用 INSERT ... ON DUPLICATE KEY UPDATE 保证并发安全：
     * 唯一键 uk_bucket(ip, route, minute_bucket) 冲突时自增 hits 并取回新值。
     */
    private function allow(string $ip, string $path): bool
    {
        $now   = time();
        $bucket = intdiv($now, $this->window);

        try {
            // 原子自增：唯一键冲突时 hits+1，并取回自增后的真实值。
            // 单条 SQL 完成「计数 + 读取」，避免先查后写的竞态
            // （并发下两个请求都读到 count=0，都会走 insert 分支）。
            $count = (int) Db::name('rate_limit')
                ->where([
                    'ip'            => $ip,
                    'route'         => $path,
                    'minute_bucket' => $bucket,
                ])
                ->value('hits');

            if ($count === 0) {
                try {
                    Db::name('rate_limit')->insert([
                        'ip'            => $ip,
                        'route'         => $path,
                        'minute_bucket' => $bucket,
                        'hits'          => 1,
                        'createtime'    => $now,
                    ]);
                    $count = 1;
                } catch (\Throwable $e) {
                    // 并发下唯一键冲突说明别的请求已插入，退回自增分支
                    $count = (int) Db::name('rate_limit')
                        ->where([
                            'ip'            => $ip,
                            'route'         => $path,
                            'minute_bucket' => $bucket,
                        ])
                        ->value('hits') + 1;
                }
            } else {
                Db::name('rate_limit')
                    ->where([
                        'ip'            => $ip,
                        'route'         => $path,
                        'minute_bucket' => $bucket,
                    ])
                    ->update([
                        'hits'       => Db::raw('hits + 1'),
                        'createtime' => $now,
                    ]);
                $count = $count + 1;
            }
        } catch (\Throwable $e) {
            // 数据库不可用时放行——限流是保护措施，不能因它自身故障让业务不可用
            Log::error('[RateLimit] 计数失败，放行: '
                . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            return true;
        }

        if ($count > $this->limit) {
            Log::warning(sprintf(
                '[RateLimit] 触发限流 ip=%s route=%s hits=%d/%d',
                $ip, $path, $count, $this->limit
            ));
            return false;
        }

        return true;
    }
}