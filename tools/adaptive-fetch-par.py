#!/usr/bin/env python3
"""
并发版自适应抓取器 —— 加速版

原版单线程每端点最多 24 次请求，174 个端点要 1 小时以上。
本版：4 线程并发 + 早停策略（找到 1 个有效参数即停）+ 剪枝
"""
import json, os, re, sys, time, threading, queue
import urllib.request, urllib.error

BASE = 'https://api.gandart.shop/api'
OUT = '/workspace/recon/raw2'
TOKEN = open('/workspace/recon/.token').read().strip()
RP = '/workspace/recon/schema-map/endpoint-params.json'
LOCK = threading.Lock()

CANDIDATES = ['id', 'ids', 'status', 'sort', 'type', 'goods_id', 'collection_id',
              'order_id', 'order_sn', 'activity_id', 'theme_id', 'union_id',
              'address_id', 'record_id', 'cate_id', 'goods_type', 'type_id']
ENUMS = ['1', '2', 'desc', 'asc', 'all', 'new']

def call(path, body=None, timeout=20):
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(
        BASE + path, data=data,
        method='POST' if body is not None else 'GET',
        headers={'Content-Type': 'application/json',
                 'Origin': 'https://h5.gandart.shop',
                 'Referer': 'https://h5.gandart.shop/',
                 'User-Agent': 'Mozilla/5.0 (iPhone) Mobile/15E148',
                 'token': TOKEN})
    try:
        with urllib.request.urlopen(req, timeout=timeout) as r:
            return json.loads(r.read())
    except urllib.error.HTTPError as e:
        try:
            return json.loads(e.read())
        except Exception:
            return {'code': e.code, 'msg': 'HTTPError', 'data': None}
    except Exception as e:
        return {'code': -1, 'msg': f'{type(e).__name__}', 'data': None}

def slug(p):
    return re.sub(r'[^a-zA-Z0-9]+', '_', p.strip('/')).strip('_').lower()

def probe(path):
    """返回 (resp, params_found)"""
    # 1) 裸 POST / GET
    for body in ({}, None):
        r = call(path, body)
        if r.get('code') == 1:
            return r, []
        if r.get('code') == 401:
            return r, ['<need_auth>']
    base = {'page': 1, 'list_rows': 20}

    # 2) 单参数试探（先 id 系列，最常见）
    for cand in CANDIDATES:
        for val in (1, '1'):
            r = call(path, {**base, cand: val})
            if r.get('code') == 1:
                return r, [cand]
            if r.get('code') == 401:
                return r, ['<need_auth>']
            # 报错文案变化说明该参数被识别
    # 3) 枚举值试探（针对"状态错误""排序方式错误"）
    for cand in ('status', 'sort', 'type'):
        for val in ENUMS:
            r = call(path, {**base, cand: val})
            if r.get('code') == 1:
                return r, [cand]
    return {'code': r.get('code'), 'msg': r.get('msg'), 'data': None}, []

def worker(q, stats, out):
    while True:
        try:
            path, desc = q.get_nowait()
        except queue.Empty:
            return
        s = slug(path)
        try:
            resp, params = probe(path)
        except Exception as e:
            resp, params = {'code': -1, 'msg': str(e)[:60], 'data': None}, []
        with open(f'{OUT}/{s}.json', 'w') as f:
            json.dump(resp, f, ensure_ascii=False, indent=1)
        code = resp.get('code')
        with LOCK:
            out[s] = {'path': path, 'desc': desc, 'code': code,
                      'msg': resp.get('msg'), 'params': params}
            if code == 1:
                stats['ok'] += 1
            elif code == 401:
                stats['auth'] += 1
            else:
                stats['fail'] += 1
            n = stats['ok'] + stats['auth'] + stats['fail']
            if n % 20 == 0:
                print(f"  [{n}] 成功{stats['ok']} 401:{stats['auth']} 失败{stats['fail']}", flush=True)
                json.dump(out, open(RP, 'w'), ensure_ascii=False, indent=1)
        q.task_done()

def main():
    todo = json.load(open('/tmp/todo.json'))
    out = json.load(open(RP)) if os.path.exists(RP) else {}
    # 排除已成功的（保留失败重试机会）
    q = queue.Queue()
    n = 0
    for path, desc in todo:
        s = slug(path)
        if out.get(s, {}).get('code') == 1:
            continue
        q.put((path, desc))
        n += 1
    print(f'待抓 {n} 个（已成功的跳过），4 线程并发')
    stats = {'ok': 0, 'auth': 0, 'fail': 0}
    threads = [threading.Thread(target=worker, args=(q, stats, out), daemon=True)
               for _ in range(4)]
    for t in threads:
        t.start()
    for t in threads:
        t.join()
    json.dump(out, open(RP, 'w'), ensure_ascii=False, indent=1)
    print(f'完成：成功{stats["ok"]} | 401:{stats["auth"]} | 失败{stats["fail"]}')

if __name__ == '__main__':
    main()
