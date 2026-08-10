# -*- coding: utf-8 -*-
"""한 문단에 여러 문장이 붙어 있으면 문장마다 줄을 나눈다.

한국어는 문장이 이어 붙으면 눈이 어디서 끊어 읽을지 잡지 못한다.
문단은 그대로 두고, 문장 사이에만 줄을 넣는다.

  <p>가나다.  라마바.</p>
  →
  <p>
    <span class="s">가나다.</span>
    <span class="s">라마바.</span>
  </p>

span 은 CSS 에서 display:block 이라 줄이 나뉘고, 검색엔진에는 한 문단으로 읽힌다.
문장 끝(다. 요. ? !)에서만 자르므로 "1. 항목" 같은 번호는 건드리지 않는다.

    python tools/split_sentences.py          점검만
    python tools/split_sentences.py --write  실제 적용
"""
import glob
import io
import re
import sys

# 문장 끝: 종결어미 + 마침표, 또는 물음표·느낌표. 뒤에 공백이 와야 한다.
END = re.compile(r'(?<=[다요음함])\.(?=\s)|(?<=[?!])(?=\s)')
# 이미 나눠 둔 문단은 다시 손대지 않는다
DONE = re.compile(r'class="s"')
# 안내문·이동경로처럼 한 줄로 두는 편이 나은 곳은 제외한다
KEEP = re.compile(r'class="[^"]*\b(?:cf__hint|breadcrumb)\b')


def sentences(inner):
    """문단 안 텍스트를 문장 목록으로 나눈다. 나눌 게 없으면 None."""
    if DONE.search(inner):
        return None
    parts, last = [], 0
    for m in END.finditer(inner):
        parts.append(inner[last:m.end()])
        last = m.end()
    parts.append(inner[last:])
    # 줄바꿈과 연속 공백을 한 칸으로 눌러 span 안이 들쭉날쭉해지지 않게 한다
    parts = [re.sub(r'\s+', ' ', p).strip() for p in parts]
    parts = [p for p in parts if p]
    return parts if len(parts) > 1 else None


def handle(path, write):
    src = io.open(path, encoding='utf-8').read()
    hits = [0]

    def repl(m):
        open_tag, inner, close = m.group(1), m.group(2), m.group(3)
        if KEEP.search(open_tag):
            return m.group(0)
        parts = sentences(inner)
        if parts is None:
            return m.group(0)
        hits[0] += 1
        # 여는 태그가 있던 자리만큼 들여쓰기를 맞춘다
        pad = ' ' * (m.start(1) - src.rfind('\n', 0, m.start(1)) - 1)
        body = '\n'.join('%s  <span class="s">%s</span>' % (pad, p) for p in parts)
        return '%s\n%s\n%s%s' % (open_tag, body, pad, close)

    new = re.sub(r'(<p[^>]*>)(.*?)(</p>)', repl, src, flags=re.S)
    if hits[0] and write:
        io.open(path, 'w', encoding='utf-8').write(new)
    return hits[0]


def main():
    write = '--write' in sys.argv
    files = sorted(glob.glob('*.html')) + sorted(glob.glob('lawyers/*.html'))
    total = 0
    for f in files:
        n = handle(f, write)
        if n:
            print('%s — 문단 %d' % (f, n))
            total += n
    print('%s — 문단 %d개' % ('적용' if write else '점검(적용 안 함)', total))


if __name__ == '__main__':
    main()
