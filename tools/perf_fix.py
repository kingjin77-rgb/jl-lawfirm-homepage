# -*- coding: utf-8 -*-
"""화면이 덜컹거리는 것과 스크립트가 화면을 잡아 두는 것을 함께 잡는다.

1) 이미지에 원래 크기(width/height)를 적어 준다.
   브라우저가 자리를 미리 잡아 두므로, 그림이 늦게 와도 글이 아래로 밀리지 않는다.
2) 첫 화면에 보이지 않는 이미지에 loading="lazy" 를 붙인다.
   로고처럼 처음부터 보이는 것은 붙이지 않는다. 붙이면 오히려 늦게 뜬다.
3) 스크립트에 defer 를 붙인다. 내려받는 동안 화면 그리기가 멈추지 않는다.

    python tools/perf_fix.py          점검만
    python tools/perf_fix.py --write  실제 적용
"""
import glob
import io
import os
import re
import sys

from PIL import Image

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# 첫 화면에 바로 보이는 것들 — 지연로딩을 붙이면 안 된다
EAGER = ('logo.png', 'logo-white.png', 'hero-poster.jpg')

_size_cache = {}


def size_of(src, page):
    """이미지 파일의 원래 크기. 못 찾으면 None."""
    if src in _size_cache:
        return _size_cache[src]
    rel = src.split('?')[0]
    cand = os.path.normpath(os.path.join(BASE, os.path.dirname(page), rel))
    if not os.path.isfile(cand):
        cand = os.path.normpath(os.path.join(BASE, rel.lstrip('./')))
    out = None
    if os.path.isfile(cand):
        try:
            with Image.open(cand) as im:
                out = im.size
        except Exception:
            out = None
    _size_cache[src] = out
    return out


def fix_img(tag, page):
    src = re.search(r'src="([^"]+)"', tag)
    if not src:
        return tag, 0
    name = os.path.basename(src.group(1).split('?')[0])
    changed = 0

    if 'width=' not in tag and 'height=' not in tag:
        wh = size_of(src.group(1), page)
        if wh:
            tag = tag[:-1].rstrip() + ' width="%d" height="%d">' % wh
            changed = 1

    if 'loading=' not in tag and name not in EAGER:
        tag = tag[:-1].rstrip() + ' loading="lazy" decoding="async">'
        changed = 1

    return tag, changed


def handle(path, write):
    src = io.open(path, encoding='utf-8').read()
    orig = src
    n = [0]

    def img_repl(m):
        tag, c = fix_img(m.group(0), path)
        n[0] += c
        return tag

    src = re.sub(r'<img\b[^>]*>', img_repl, src)

    # 스크립트는 문서 끝에 있지만, defer 를 붙이면 내려받는 동안에도 화면을 그린다
    def js_repl(m):
        t = m.group(0)
        if 'defer' in t or 'async' in t:
            return t
        n[0] += 1
        return t.replace('>', ' defer>', 1)

    src = re.sub(r'<script src="[^"]+\.js[^"]*"\s*>', js_repl, src)

    if src != orig and write:
        io.open(path, 'w', encoding='utf-8').write(src)
    return n[0]


def main():
    os.chdir(BASE)
    write = '--write' in sys.argv
    total = 0
    for f in sorted(glob.glob('*.html')) + sorted(glob.glob('lawyers/*.html')):
        c = handle(f, write)
        if c:
            print('%s — %d곳' % (f, c))
            total += c
    print('%s — 모두 %d곳' % ('적용' if write else '점검(적용 안 함)', total))


if __name__ == '__main__':
    main()
