#!/usr/bin/env python3
"""把原站下载的 OSS 图片转为 webp（保持原尺寸，质量 90，体积降 90%+）。
仅供本地预览服务加速用，不改变图片来源（图片仍从原站 img.gandart.shop 下载）。"""
import sys
from PIL import Image

src, dst = sys.argv[1], sys.argv[2]
im = Image.open(src)
if im.mode in ('RGBA', 'LA', 'P'):
    im = im.convert('RGBA')
else:
    im = im.convert('RGB')
# 保持原始尺寸：显示宽 334 时缩放比与原站完全一致，避免重采样造成的像素差异
im.save(dst, 'WEBP', quality=90, method=6)
