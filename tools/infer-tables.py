#!/usr/bin/env python3
"""从全部真实响应反推原站表结构（最终版）"""
import json, glob, os, re
from collections import defaultdict

ENVELOPE = {'total', 'per_page', 'current_page', 'last_page', 'list', 'count',
            'ltnum', 'data', 'current_count', 'current_consignment'}

def strip(o):
    if isinstance(o, dict):
        if isinstance(o.get('data'), list) and o['data']:
            return strip(o['data'][0])
        if isinstance(o.get('list'), list) and o['list']:
            return strip(o['list'][0])
    return o

def capture(node, prefix, depth, acc):
    """把 dict 的标量字段收进 acc，嵌套交给上层处理"""
    if depth > 2 or not isinstance(node, dict):
        return
    for k, v in node.items():
        p = f'{prefix}.{k}' if prefix else k
        if isinstance(v, dict):
            continue                      # 嵌套由 rec 处理
        if isinstance(v, list):
            acc.append((p, 'list'))
        else:
            acc.append((p, type(v).__name__))

def entities(o):
    """返回 {实体名: {字段路径: 类型}}"""
    tables = defaultdict(dict)

    def rec(node, prefix, depth):
        if depth > 3 or not isinstance(node, dict):
            return
        own = []
        for k, v in node.items():
            if k in ENVELOPE and not isinstance(v, (dict, list)):
                continue
            p = f'{prefix}.{k}' if prefix else k
            name = k.replace('[]', '')
            if isinstance(v, dict):
                acc = []
                capture(v, name, 0, acc)
                for path, t in acc:
                    tables[name].setdefault(path, t)
                rec(v, name, depth + 1)
            elif isinstance(v, list):
                if v and isinstance(v[0], (dict, list)):
                    acc = []
                    capture(v[0], name, 0, acc)
                    for path, t in acc:
                        tables[name].setdefault(path, t)
                else:
                    tables.setdefault(name, {}).setdefault(p, 'list')
            else:
                own.append((p, type(v).__name__))
        # 本层标量归属：优先用 prefix（嵌套实体的字段），否则用第一个键名占位
        if own:
            key = prefix or '<root>'
            for path, t in own:
                tables[key].setdefault(path, t)

    rec(o, '', 0)
    return {k: v for k, v in tables.items() if v}

def main():
    result = defaultdict(lambda: {'f': {}, 'ev': set()})
    for d in ['raw', 'raw/auth', 'raw2']:
        for fp in glob.glob(f'/workspace/recon/{d}/*.json'):
            try:
                j = json.load(open(fp))
            except Exception:
                continue
            if not isinstance(j, dict) or j.get('code') != 1 or j.get('data') is None:
                continue
            s = strip(j['data'])
            if not isinstance(s, dict) or not s:
                continue
            slug = os.path.basename(fp)[:-5]
            for name, fields in entities(s).items():
                t = result[name]
                t['ev'].add(slug)
                for path, ty in fields.items():
                    t['f'].setdefault(path, ty)

    DOM = [
        ('用户/账户', r'^user|userinfo|statistics|verification|^my$'),
        ('藏品/盲盒', r'collection|batch|publisher|blind'),
        ('订单/交易', r'order|artel|uc_ids|consign|entrust|^list$|market'),
        ('合成/抽奖', r'^play|theme|lottery|consecutive'),
        ('公会', r'union'),
        ('内容/配置', r'affiche|banner|version|custom_series|platform_series|agreement|terms'),
    ]
    def dom(n):
        for d, p in DOM:
            if re.search(p, n, re.I):
                return d
        return '其他'

    g = defaultdict(list)
    for r in result:
        g[dom(r)].append(r)

    print(f'反推实体数: {len(result)}\n')
    for d in sorted(g, key=lambda x: -len(g[x])):
        print(f'■ {d}（{len(g[d])} 实体）')
        for r in sorted(g[d], key=lambda x: -len(result[x]['f'])):
            t = result[r]
            print(f'   {r:<24}{len(t["f"]):>3} 字段   {", ".join(sorted(t["ev"])[:2])}')
        print()

    json.dump({r: {'fields': result[r]['f'], 'evidence': sorted(result[r]['ev'])}
               for r in result},
              open('/workspace/recon/schema-map/inferred-tables.json', 'w'),
              ensure_ascii=False, indent=1)
    print('已写出 schema-map/inferred-tables.json')

if __name__ == '__main__':
    main()
