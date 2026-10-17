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

for p in "${IMGS[@]}"; do
  out="$DEST$p"
  mkdir -p "$(dirname "$out")"
  if [ ! -s "$out" ]; then
    curl -s -o "$out" -H "Origin: https://h5.gandart.shop" -H "Referer: https://h5.gandart.shop/" "https://img.gandart.shop$p"
  fi
  echo "downloaded $(stat -c%s "$out") $p"
done

# 转码 + 生成映射表
python3 tools/build-oss-map.py
