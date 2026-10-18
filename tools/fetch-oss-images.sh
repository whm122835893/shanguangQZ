#!/bin/bash
# 从原站 img.gandart.shop 下载 OSS 图片，填充到 public/static/oss/
# 规则：图片一律来自原站下载；为兼容旧版 iOS Safari，不使用 WebP，
#       无透明通道的图统一转 JPEG(q88，保持原尺寸) 以大幅降体积。
set -e
cd "$(dirname "$0")/.."
DEST=public/static/oss
mkdir -p "$DEST"

IMGS=(
"/uploads/20261010/2bac5172345cf2ca30c08a6713a65af7.jpg"
"/uploads/20261010/d86edf5a1795d65dc822e139a88e4c2a.jpg"
"/uploads/20261012/982cfb57d2d7bd4f66da6be37899e220.png"
)

# ---- 追加：数据库里实际引用的图片路径 ----
# 🔴 为什么不能再只靠上面那 3 行写死清单：2026-10-19 实测库内有 25 条
#    图片路径，写死清单只覆盖 3 条，其余 22 张从未被预下载——首页、
#    公告、藏品卡的图因此一直走运行时回源（或干脆裂）。
#    「写死会变的事实」是本项目反复踩的坑族（见 PROJECT_MEMORY §4.9），
#    清单必须随库内数据走，单一出处。
#    密码可用 GANDART_DB_PASS 覆盖；mysql 不可用时静默跳过，不影响写死清单。
if command -v mysql >/dev/null 2>&1; then
  while IFS= read -r line; do
    [ -n "$line" ] && IMGS+=("$line")
  done < <(mysql -ugandart -p"${GANDART_DB_PASS:-Gandart#2026Dev}" -h127.0.0.1 gandart -N -B -e "
      SELECT listimg FROM blind_box WHERE listimg<>'' UNION
      SELECT main_image FROM blind_box WHERE main_image<>'' UNION
      SELECT image FROM affiche WHERE image<>'' UNION
      SELECT listimg FROM collection WHERE listimg<>''" 2>/dev/null || true)
fi
echo "待处理路径：${#IMGS[@]} 条"

for p in "${IMGS[@]}"; do
  out="$DEST$p"
  mkdir -p "$(dirname "$out")"
  if [ ! -s "$out" ]; then
    curl -s -o "$out" -H "Origin: https://h5.gandart.shop" -H "Referer: https://h5.gandart.shop/" "https://img.gandart.shop$p"

    # 🔴 必须校验魔数：原站 OSS 对不存在的 key 返回 **HTTP 404 + XML 错误体**
    #    （<Error><Code>NoSuchKey，约 422 字节），而 curl -s 不看状态码、
    #    照样把它写成 .jpg。2026-10-19 就在资产目录里发现过这么一个文件：
    #    它让 build-oss-map.py 在 Image.open() 处崩溃，整轮转码中断。
    #    非图片一律删掉，宁可缺图也不要留一个污染目录的假文件。
    if ! head -c 8 "$out" | grep -qE $'^\xff\xd8\xff|^\x89PNG|^GIF8|^RIFF|^<svg'; then
      echo "  [丢弃] 非图片响应（原站 404?）: $p ($(stat -c%s "$out")B)"
      rm -f "$out"
      continue
    fi
  fi
  echo "downloaded $(stat -c%s "$out") $p"
done

# 转码 + 生成映射表
python3 tools/build-oss-map.py
