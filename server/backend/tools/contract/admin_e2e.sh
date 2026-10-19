#!/usr/bin/env bash
# ============================================================
# 管理端 E2E 契约测试 —— 真实 HTTP 全链路
#
# 覆盖：登录 → 换 token → 未带 token 401 → 带 token 放行 →
#       banner 新建/上下架 → 公告新建/上下架 → 藏品上下架 →
#       登出吊销 → 登出后 401
#
# 用法：bash tools/contract/admin_e2e.sh
#   （脚本自己起 php think run，跑完自动回收，端口 8000）
# 退出码：0 = 全通过；非 0 = 有失败（禁止提交）
# ============================================================
set -u
cd "$(dirname "$0")/../.."   # backend/

PORT=8000
BASE="http://127.0.0.1:${PORT}"
PASS=0; FAIL=0

say()  { echo "$@"; }
ck() {  # ck <标签> <期望片段> <实际响应体>
    if echo "$3" | grep -qF "$2"; then
        PASS=$((PASS+1)); say "[OK]  $1"
    else
        FAIL=$((FAIL+1)); say "[FAIL] $1"
        say "       期望含: $2"
        say "       实际: $(echo "$3" | head -c 300)"
    fi
}

php_post() { curl -s -X POST "$BASE$1" -d "$2" ${3:-}; }
php_get()  { curl -s "$BASE$1" ${2:-}; }

# ---------- 起服务 ----------
php think run -p "$PORT" >/tmp/admin_e2e_server.log 2>&1 &
SRV=$!
trap 'kill $SRV 2>/dev/null' EXIT
for _ in $(seq 1 40); do
    curl -s -o /dev/null "$BASE/" && break
    sleep 0.3
done

# ---------- 造测试管理员（独立于 admin_test.php 的账号） ----------
U="e2e_admin_$$"
P="E2e-Passw0rd-9"
php tools/create-admin.php "$U" "$P" >/dev/null || { say "[FAIL] create-admin 失败"; exit 1; }

say "── 会话 ──"
R=$(php_post /admin/auth/login "username=$U&password=WRONG")
ck "错误密码拒绝" '"code":0' "$R"
R=$(php_post /admin/auth/login "username=$U&password=$P")
ck "登录成功 code=1" '"code":1' "$R"
ck "返回 token" 'token' "$R"
TOKEN=$(echo "$R" | php -r 'echo json_decode(stream_get_contents(STDIN),true)["data"]["token"] ?? "";')
[ -n "$TOKEN" ] || { say "[FAIL] 未取到 token"; exit 1; }

R=$(php_post /admin/banner/list "page=1")
ck "无 token 访问 401" '"code":401' "$R"
AUTH=" -H X-Admin-Token:$TOKEN"
R=$(curl -s -X POST "$BASE/admin/auth/profile" -H "X-Admin-Token: $TOKEN")
ck "带 token profile 放行" "\"username\":\"$U\"" "$R"

say "── banner ──"
R=$(curl -s -X POST "$BASE/admin/banner/save" -H "X-Admin-Token: $TOKEN" -d "title=E2E标题&image=static/e2e.jpg&sort=99")
ck "banner 新建" '"code":1' "$R"
BID=$(echo "$R" | php -r 'echo json_decode(stream_get_contents(STDIN),true)["data"]["id"] ?? 0;')
R=$(curl -s -X POST "$BASE/admin/banner/toggle" -H "X-Admin-Token: $TOKEN" -d "id=$BID&is_show=0")
ck "banner 下架 is_show=0" '"is_show":0' "$R"

say "── 公告 ──"
R=$(curl -s -X POST "$BASE/admin/affiche/save" -H "X-Admin-Token: $TOKEN" -d "title=E2E公告&type=1&content=正文")
ck "公告新建" '"code":1' "$R"
AID=$(echo "$R" | php -r 'echo json_decode(stream_get_contents(STDIN),true)["data"]["id"] ?? 0;')
R=$(curl -s -X POST "$BASE/admin/affiche/toggle" -H "X-Admin-Token: $TOKEN" -d "id=$AID&is_show=0")
ck "公告下架" '"is_show":0' "$R"

say "── 藏品上下架 ──"
# collection 可能为空表：插入夹具，测完删除，绝不污染业务数据
mysql gandart -e "INSERT INTO collection (name, main_image, listimg, createtime, updatetime, is_show)
                  VALUES ('__e2e_fixture__', 'x', 'x', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 1);
                  SET @cid = LAST_INSERT_ID();"
CID=$(mysql gandart -N -e "SELECT id FROM collection WHERE name='__e2e_fixture__' LIMIT 1" 2>/dev/null)
if [ -n "$CID" ]; then
    R=$(curl -s -X POST "$BASE/admin/collection/toggle" -H "X-Admin-Token: $TOKEN" -d "id=$CID&is_show=0")
    ck "藏品下架" '"is_show":0' "$R"
    R=$(curl -s -X POST "$BASE/admin/collection/toggle" -H "X-Admin-Token: $TOKEN" -d "id=$CID&is_show=1")
    ck "藏品恢复上架" '"is_show":1' "$R"
    mysql gandart -e "DELETE FROM collection WHERE id=$CID"
else
    say "[SKIP] collection 夹具创建失败，跳过上下架断言"
fi

say "── 登出吊销 ──"
R=$(curl -s -X POST "$BASE/admin/auth/logout" -H "X-Admin-Token: $TOKEN")
ck "登出成功" '"code":1' "$R"
R=$(curl -s -X POST "$BASE/admin/auth/profile" -H "X-Admin-Token: $TOKEN")
ck "登出后 401" '"code":401' "$R"

# ---------- 清理测试数据 ----------
mysql gandart -e "DELETE FROM admin_user WHERE username='$U'; DELETE FROM banner WHERE id=$BID; DELETE FROM affiche WHERE id=$AID;" 2>/dev/null

say "========================================"
if [ "$FAIL" -eq 0 ]; then
    say "E2E 全部通过（$PASS 项）"
else
    say "E2E 失败 $FAIL 项 / 通过 $PASS 项"
fi
exit $([ "$FAIL" -eq 0 ] && echo 0 || echo 1)
