# -*- coding: utf-8 -*-
"""페이지 전체 화면을 찍는다 — 메뉴북 재료.

로컬 서버를 먼저 띄워 두고 돌린다. reveal 모션이 걸린 요소는
스크롤을 끝까지 내렸다 올려 전부 드러낸 뒤 찍는다.
"""
import os
from playwright.sync_api import sync_playwright

OUT = os.path.join(os.path.dirname(__file__), 'shots')
BASE = 'http://localhost:5180/'
PAGES = ['index.html', 'about.html', 'lawyers.html', 'practice.html', 'corporate.html',
         'redevelopment.html', 'redevelopment-desk.html', 'registry.html',
         'registry-detail.html', 'dongtan.html', 'law.html', 'magazine.html',
         'contact.html', 'qna.html']

RAISE = """document.querySelectorAll('.reveal').forEach(e=>e.classList.add('is-in'))"""
COUNT = """document.querySelectorAll('.statcard .num').forEach(el=>{
  const t=el.__target,u=el.__unit; if(!isFinite(t))return;
  el.textContent=Math.round(t).toLocaleString('ko-KR'); if(u)el.appendChild(u);})"""


def main():
    os.makedirs(OUT, exist_ok=True)
    with sync_playwright() as p:
        b = p.chromium.launch()
        pg = b.new_page(viewport={'width': 1440, 'height': 900})
        for f in PAGES:
            pg.goto(BASE + f, wait_until='networkidle', timeout=30000)
            pg.evaluate(RAISE)
            pg.evaluate(COUNT)
            pg.evaluate("window.scrollTo(0,document.body.scrollHeight)")
            pg.wait_for_timeout(700)
            pg.evaluate("window.scrollTo(0,0)")
            pg.wait_for_timeout(500)
            pg.screenshot(path=os.path.join(OUT, f.replace('.html', '') + '.png'),
                          full_page=True)
            print('찍음', f)
        b.close()


if __name__ == '__main__':
    main()
