# -*- coding: utf-8 -*-
"""메뉴북을 인쇄용 PDF 로 뽑는다.

tools/make_menubook.py 를 먼저 돌려 menubook.html 을 만들어 둔다.
인쇄 CSS 는 그 안에 들어 있다 — 카드와 표가 페이지 경계에서 잘리지 않게 잡아 둔 것.
"""
import os
import pathlib
import sys

from playwright.sync_api import sync_playwright

HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(HERE, 'menubook.html')

FOOT = ('<div style="width:100%;font-size:8px;color:#6d7791;font-family:sans-serif;'
        'padding:0 13mm;text-align:right">제이엘 홈페이지 메뉴북 · '
        '<span class="pageNumber"></span> / <span class="totalPages"></span></div>')


def main(out):
    if not os.path.exists(SRC):
        raise SystemExit('menubook.html 이 없다. tools/make_menubook.py 를 먼저 돌려라.')
    url = pathlib.Path(SRC).as_uri()
    with sync_playwright() as p:
        b = p.chromium.launch()
        pg = b.new_page()
        pg.goto(url, wait_until='networkidle', timeout=60000)
        pg.emulate_media(media='print')
        pg.wait_for_timeout(1200)
        pg.pdf(path=out, format='A4', print_background=True,
               margin={'top': '14mm', 'bottom': '14mm', 'left': '13mm', 'right': '13mm'},
               display_header_footer=True,
               header_template='<div></div>', footer_template=FOOT)
        b.close()
    print('PDF %s (%.1fMB)' % (out, os.path.getsize(out) / 1e6))


if __name__ == '__main__':
    main(sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, 'menubook.pdf'))
