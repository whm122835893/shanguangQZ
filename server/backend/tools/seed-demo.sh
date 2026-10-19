#!/usr/bin/env bash
# ============================================================
# seed-demo.sh —— 演示数据灌入（不抓原站，静态图零出网）
#
# 分工：
#   - banner / 公告：走管理端 HTTP 接口（对 P0 管理端的真实使用）
#   - 系列/发行方/藏品/挂单：P0 管理端无「新建藏品」接口，走 SQL
#   - 全部图片引用 public/static/oss/ 预下载资产，本地命中零死链
#
# 特性：幂等 —— 先按固定标记清理旧演示数据再灌，可重复执行。
# 用法：bash tools/seed-demo.sh
# 管理员：demo_admin / DemoAdmin#2026（create-admin.php 创建）
# ============================================================
set -u
cd "$(dirname "$0")/.."   # backend/（本脚本在 tools/ 下，上一级即 backend）

PORT=8002
BASE="http://127.0.0.1:${PORT}"
PASS=0; FAIL=0

ck() {
    if echo "$3" | grep -qF "$2"; then PASS=$((PASS+1)); echo "[OK]  $1";
    else FAIL=$((FAIL+1)); echo "[FAIL] $1 —— 实际: $(echo "$3" | head -c 200)"; fi
}

# ---------- 0. 清理旧演示数据（幂等） ----------
mysql gandart <<'SQL'
DELETE mg FROM market_goods mg
  JOIN collection c ON c.id = mg.collection_id
  WHERE c.name LIKE '演示%';
DELETE FROM collection WHERE name LIKE '演示%';
DELETE FROM series  WHERE name IN ('历史区','盲盒区','文创区');
DELETE FROM publisher WHERE name = '演示发行方';
DELETE FROM user WHERE username = 'demo_seller';
DELETE FROM admin_user WHERE username = 'demo_admin';
DELETE FROM banner  WHERE title LIKE '演示%';
DELETE FROM affiche WHERE title LIKE '演示%';
SQL
echo "── 旧演示数据已清理 ──"

# ---------- 1. 演示管理员 ----------
php tools/create-admin.php demo_admin 'DemoAdmin#2026' >/dev/null || { echo "[FAIL] 建管理员失败"; exit 1; }

php think run -p "$PORT" >/tmp/seed_demo_srv.log 2>&1 &
SRV=$!
trap 'kill $SRV 2>/dev/null' EXIT
for _ in $(seq 1 40); do curl -s -o /dev/null "$BASE/" && break; sleep 0.3; done

# ---------- 2. 登录（走管理端接口） ----------
R=$(curl -s -X POST "$BASE/admin/auth/login" -d "username=demo_admin&password=DemoAdmin#2026")
ck "管理端登录" '"code":1' "$R"
TOKEN=$(echo "$R" | php -r 'echo json_decode(stream_get_contents(STDIN),true)["data"]["token"] ?? "";')
[ -n "$TOKEN" ] || { echo "[FAIL] 未取到 token"; exit 1; }
A="-H X-Admin-Token:$TOKEN"

# ---------- 3. banner（管理端接口） ----------
post() { curl -s -X POST "$BASE$1" $A "$@"; }
i=0
for row in \
  "演示·新人上手指南|uploads/20261017/ba4460acb479dc61f616b1d96053a6eb.jpg|99" \
  "演示·本周发售日历|uploads/20261016/a8b738e3bb0833b5ac46c6fb8ae23240.jpg|98" \
  "演示·市场交易须知|uploads/20261014/2729c06448b4ebcc8192291e9d11a221.jpg|97"
do
  i=$((i+1))
  T="${row%%|*}"; rest="${row#*|}"; IMG="${rest%%|*}"; SORT="${rest##*|}"
  R=$(post /admin/banner/save --data-urlencode "title=$T" --data-urlencode "image=/$IMG" --data-urlencode "sort=$SORT")
  ck "banner $i 入库" '"code":1' "$R"
done

# ---------- 4. 公告（管理端接口） ----------
R=$(post /admin/affiche/save --data-urlencode "title=演示·平台服务上线公告" --data-urlencode "type=1" \
     --data-urlencode "image=/uploads/20261012/211146a4cfbec1ab3e6ca8abf620ff0d.png" \
     --data-urlencode "content=<p>钱包账本、实名认证、支付闭环与运营管理端已上线，本条为演示数据。</p>")
ck "公告 1（平台公告）" '"code":1' "$R"
R=$(post /admin/affiche/save --data-urlencode "title=演示·首发优惠活动开启" --data-urlencode "type=2" \
     --data-urlencode "image=/uploads/20261010/1430b69574b076ff200433e2694b3066.png" \
     --data-urlencode "content=<p>新用户完成实名认证即获首发购藏资格。本条为演示数据。</p>")
ck "公告 2（活动福利）" '"code":1' "$R"
R=$(post /admin/affiche/save --data-urlencode "title=演示·首批藏品发售预告" --data-urlencode "type=3" \
     --data-urlencode "image=/uploads/20261010/9731ccc93865123c3ecd6d1d5325d34b.png" \
     --data-urlencode "content=<p>四个系列藏品即将开售，详见发售日历。本条为演示数据。</p>")
ck "公告 3（发售上新）" '"code":1' "$R"

# ---------- 5. 系列/发行方/藏品/挂单（SQL：P0 管理端无新建藏品接口） ----------
mysql gandart <<'SQL'
INSERT INTO publisher (name, company_logo, createtime)
VALUES ('演示发行方', '/uploads/20261012/845c7363da83712b4453bac532bc1fdc.jpg', UNIX_TIMESTAMP());
SET @pub = LAST_INSERT_ID();

INSERT INTO series (name, type, sort, is_show, createtime) VALUES
  ('历史区', 10, 99, 1, UNIX_TIMESTAMP()),
  ('盲盒区', 20, 98, 1, UNIX_TIMESTAMP()),
  ('文创区', 30, 97, 1, UNIX_TIMESTAMP());
SET @s_hist = (SELECT id FROM series WHERE name='历史区');
SET @s_box  = (SELECT id FROM series WHERE name='盲盒区');
SET @s_wen  = (SELECT id FROM series WHERE name='文创区');

INSERT INTO user (username, nickname, email, mobile, avatar, level, gender, bio, money, agent_money, score, successions, maxsuccessions, loginip, createtime, jointime)
VALUES ('demo_seller','演示卖家','','13900001234','','1','1','演示数据挂单人',0,0,0,0,0,'',UNIX_TIMESTAMP(),UNIX_TIMESTAMP());
SET @seller = LAST_INSERT_ID();

INSERT INTO collection (name, main_image, listimg, series_id, issuer_id, total_num_show, total_num, diysort, is_show, createtime, updatetime) VALUES
  ('演示·山河社稷图 壹',  '/uploads/20261012/0cf33a77d86d6298bbe578c17517a84f.jpg','/uploads/20261012/0cf33a77d86d6298bbe578c17517a84f.jpg',@s_hist,@pub,1000,1000,99,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  ('演示·敦煌飞天',       '/uploads/20261012/8ef8cc0b715485deb38becbe47624679.jpg','/uploads/20261012/8ef8cc0b715485deb38becbe47624679.jpg',@s_hist,@pub,500,500,98,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  ('演示·星际旅行者 盲盒','/uploads/20261012/94c03900501606b442e476b7bdb015c6.jpg','/uploads/20261012/94c03900501606b442e476b7bdb015c6.jpg',@s_box, @pub,10000,10000,97,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  ('演示·青花瓷韵 限量',  '/uploads/20261012/0621dd0c5ef1f8c8f40b1f1a222a401a.jpg','/uploads/20261012/0621dd0c5ef1f8c8f40b1f1a222a401a.jpg',@s_wen, @pub,200,200,96,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

INSERT INTO market_goods (user_id, collection_id, collection_code, price, num, sold_num, status, createtime, updatetime)
SELECT @seller, c.id, CONCAT('DEMO-', LPAD(c.id, 6, '0')),
       CASE c.name
         WHEN '演示·山河社稷图 壹' THEN '19.90'
         WHEN '演示·敦煌飞天' THEN '39.90'
         WHEN '演示·星际旅行者 盲盒' THEN '68.00'
         ELSE '99.00' END,
       1, 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM collection c WHERE c.name LIKE '演示%';
SQL
echo "── 系列/发行方/藏品/挂单已入库 ──"

# ---------- 6. C 端验证 ----------
echo "── C 端验证 ──"
ck "C 端 banner 列表非空" '"total":3' "$(curl -s -X POST "$BASE/api/banner/show/getBanner")"
ck "C 端公告列表非空"    '"total":3' "$(curl -s -X POST "$BASE/api/index/afficheList" -d "page=1&list_rows=10")"
ck "C 端系列列表非空"    '"name":"历史区"' "$(curl -s -X POST "$BASE/api/index/getSeries")"
ck "C 端藏品列表非空"    '"total":4' "$(curl -s -X POST "$BASE/api/box/collection/collection_list" -d "page=1&size=10")"
ck "C 端市场挂单非空"    '"min_price":"19.90"' "$(curl -s -X POST "$BASE/api/market/market/getMarketList" -d "page=1&size=10")"

echo "========================================"
if [ "$FAIL" -eq 0 ]; then echo "演示数据灌入完成（$PASS 项验证通过）";
else echo "有 $FAIL 项失败 / $PASS 项通过"; fi
exit $([ "$FAIL" -eq 0 ] && echo 0 || echo 1)
