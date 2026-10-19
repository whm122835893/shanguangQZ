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
DELETE FROM lottery WHERE name LIKE '演示%';
DELETE FROM synthesis_plan WHERE title LIKE '演示%';
DELETE FROM synthesis_theme WHERE name LIKE '演示%';
DELETE uc FROM user_collection uc JOIN user u ON u.id = uc.user_id WHERE u.username = 'demo_user';
DELETE ua FROM user_address ua JOIN user u ON u.id = ua.user_id WHERE u.username = 'demo_user';
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

# ---------- 4b. 抽奖活动（管理端接口；奖池引用下方第 5 节入库的演示藏品，
#           故本节整体放在藏品入库之后执行） ----------

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

# ---------- 5b. 抽奖活动（管理端接口，奖池引用 5 节入库的演示藏品） ----------
NOWTS=$(date +%s)
POOL=$(php -r '
$collA = json_decode(shell_exec("mysql gandart -N -e \"SELECT JSON_OBJECT(\\\"collection_id\\\", id, \\\"name\\\", name, \\\"image\\\", listimg, \\\"lottery_image\\\", listimg, \\\"level\\\", \\\"演示大奖\\\", \\\"weight\\\", 30) FROM collection WHERE name LIKE \\\"演示·山河社稷图 壹\\\" LIMIT 1\""), true);
$collB = json_decode(shell_exec("mysql gandart -N -e \"SELECT JSON_OBJECT(\\\"collection_id\\\", id, \\\"name\\\", name, \\\"image\\\", listimg, \\\"lottery_image\\\", listimg, \\\"level\\\", \\\"演示二等奖\\\", \\\"weight\\\", 20) FROM collection WHERE name LIKE \\\"演示·敦煌飞天\\\" LIMIT 1\""), true);
$pool = [$collA, $collB, ["collection_id" => 0, "name" => "谢谢参与", "image" => "", "lottery_image" => "", "level" => "谢谢参与", "weight" => 50]];
echo json_encode($pool, JSON_UNESCAPED_UNICODE);
')
R=$(post /admin/lottery/save \
  --data-urlencode "name=演示·幸运抽奖" \
  --data-urlencode "content=<p>消耗黄水晶抽奖，奖品为演示藏品。本条为演示数据。</p>" \
  --data-urlencode "image1=/uploads/20261016/a8b738e3bb0833b5ac46c6fb8ae23240.jpg" \
  --data-urlencode "crystal=10" \
  --data-urlencode "rule=10" \
  --data-urlencode "collection_json=$POOL" \
  --data-urlencode "start_time=$((NOWTS - 60))" \
  --data-urlencode "end_time=$((NOWTS + 86400 * 30))" \
  --data-urlencode "switch=1")
ck "抽奖活动入库" '"code":1' "$R"

# 演示买家发黄水晶（抽奖消耗货币；demo_user 为既有演示账号，无则跳过）
mysql gandart -e "UPDATE user SET crystal_yellow = 100 WHERE username = 'demo_user'"

# ---------- 5c. 合成主题+方案（管理端接口，材料引用 5 节入库的演示藏品） ----------
COLL_FRAG=$(mysql gandart -N -e "SELECT id FROM collection WHERE name LIKE '演示·敦煌飞天' LIMIT 1")
R=$(post /admin/synthesis/theme/save \
  --data-urlencode "name=演示·藏品合成季" \
  --data-urlencode "image=/uploads/20261012/8ef8cc0b715485deb38becbe47624679.jpg" \
  --data-urlencode "rule_text=集齐 3 份演示材料可合成目标藏品。本条为演示数据。" \
  --data-urlencode "sort=99")
ck "合成主题入库" '"code":1' "$R"
THMID=$(echo "$R" | php -r 'echo json_decode(stream_get_contents(STDIN),true)["data"]["id"] ?? "";')
R=$(post /admin/synthesis/plan/save \
  --data-urlencode "theme_id=$THMID" \
  --data-urlencode "title=演示·合成山河社稷图" \
  --data-urlencode "image=/uploads/20261012/0cf33a77d86d6298bbe578c17517a84f.jpg" \
  --data-urlencode "start_time=$(( (NOWTS - 3600) * 1000 ))" \
  --data-urlencode "end_time=$(( (NOWTS + 86400 * 30) * 1000 ))" \
  --data-urlencode "real_start_time=$(( (NOWTS - 3600) * 1000 ))" \
  --data-urlencode "classify=regular" \
  --data-urlencode "type=10" \
  --data-urlencode "stock=100" \
  --data-urlencode "need_items=[{\"collection_id\":$COLL_FRAG,\"name\":\"演示·敦煌飞天\",\"image\":\"/uploads/20261012/8ef8cc0b715485deb38becbe47624679.jpg\",\"num\":3}]")
ck "合成方案入库" '"code":1' "$R"

# ---------- 5d. 排行/地址演示（让市值排行页与地址页有真实数据） ----------
mysql gandart <<'SQL'
-- 演示买家持有 1 件山河社稷图（有最低挂单 19.90 → 排行榜可见）
INSERT INTO user_collection (user_id, collection_id, num, sell_num, lock_num, is_collection, can_compound, utime, createtime)
SELECT u.id, c.id, 1, 0, 0, 1, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM user u JOIN collection c ON c.name LIKE '演示·山河社稷图 壹'
WHERE u.username = 'demo_user' LIMIT 1;

-- 演示买家默认收货地址
INSERT INTO user_address (user_id, name, mobile, province, city, district, address, is_default, createtime, updatetime)
SELECT u.id, '演示收件人', '13800001234', '浙江省', '杭州市', '西湖区', '文三路 100 号（演示数据）', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM user u WHERE u.username = 'demo_user' LIMIT 1;
SQL

# ---------- 6. C 端验证 ----------
echo "── C 端验证 ──"
ck "C 端 banner 列表非空" '"total":3' "$(curl -s -X POST "$BASE/api/banner/show/getBanner")"
ck "C 端公告列表非空"    '"total":3' "$(curl -s -X POST "$BASE/api/index/afficheList" -d "page=1&list_rows=10")"
ck "C 端系列列表非空"    '"name":"历史区"' "$(curl -s -X POST "$BASE/api/index/getSeries")"
ck "C 端藏品列表非空"    '"total":4' "$(curl -s -X POST "$BASE/api/box/collection/collection_list" -d "page=1&size=10")"
ck "C 端市场挂单非空"    '"min_price":"19.90"' "$(curl -s -X POST "$BASE/api/market/market/getMarketList" -d "page=1&size=10")"
# 抽奖：管理端活动列表可查到（C 端 /lottery/info 需登录态，此处用管理端验证）
ck "抽奖活动入库可查"   '"name":"演示·幸运抽奖"' "$(curl -s "$BASE/admin/lottery/list" $A)"

echo "========================================"
if [ "$FAIL" -eq 0 ]; then echo "演示数据灌入完成（$PASS 项验证通过）";
else echo "有 $FAIL 项失败 / $PASS 项通过"; fi
exit $([ "$FAIL" -eq 0 ] && echo 0 || echo 1)
