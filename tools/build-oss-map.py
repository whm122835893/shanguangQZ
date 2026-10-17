#!/usr/bin/env python3
"""扫描 public/static/oss，把无透明通道的 PNG 转为 JPEG（保持原尺寸，质量 88），
并生成 src/api/oss-map.json 供前端 oss() 查表（保证路径正确）。"""
import json, os
from PIL import Image

ROOT = 'public/static/oss'
MAP = {}

for dirpath, _, files in os.walk(ROOT):
    for fn in files:
        full = os.path.join(dirpath, fn)
        rel = '/' + os.path.relpath(full, ROOT).replace(os.sep, '/')
        orig_key = rel          # 原站原始路径（接口返回的 .png/.jpg）
        im = Image.open(full)
        is_png = rel.lower().endswith('.png')
        has_alpha = is_png and (
            im.mode in ('RGBA', 'LA') or (im.mode == 'P' and 'transparency' in im.info)
        )
        if has_alpha:
            im.convert('RGBA').save(full, 'PNG', optimize=True)
            print(f'  保留PNG(含透明) {os.path.basename(rel)} {os.path.getsize(full)} bytes')
        else:
            jpg = full.rsplit('.', 1)[0] + '.jpg'
            im.convert('RGB').save(jpg, 'JPEG', quality=88, optimize=True, progressive=True)
            if jpg != full:
                os.remove(full)
            rel = rel.rsplit('.', 1)[0] + '.jpg'
            print(f'  ->JPEG {os.path.basename(rel)} {os.path.getsize(jpg)} bytes')
        # key = 原站原始路径，value = 本项目实际静态文件名
        MAP[orig_key] = rel

os.makedirs('src/api', exist_ok=True)
with open('src/api/oss-map.json', 'w', encoding='utf-8') as f:
    json.dump(MAP, f, ensure_ascii=False, indent=2)
print('oss-map.json 已生成', len(MAP), '条')
