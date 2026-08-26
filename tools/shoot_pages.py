# -*- coding: utf-8 -*-
"""페이지 전체 화면을 찍는다 — 메뉴북 재료.

서버를 직접 띄우므로 다른 준비가 필요 없다. 리포 어디서 돌려도 된다.
reveal 모션이 걸린 요소는 스크롤 전에 투명하다. 끝까지 내렸다 올려 전부 드러낸 뒤 찍는다.
카운트업 숫자도 그냥 찍으면 0 으로 굳으므로 최종값을 써 넣는다.
"""
import contextlib
import functools
import http.server
import os
import socket
import socketserver
import threading

from playwright.sync_api import sync_playwright

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, 'tools', 'shots')

PAGES = ['index.html', 'about.html', 'lawyers.html', 'practice.html', 'corporate.html',
         'redevelopment.html', 'redevelopment-desk.html', 'registry.html',
         'registry-detail.html', 'dongtan.html', 'law.html', 'magazine.html',
         'contact.html', 'qna.html']

RAISE = "document.querySelectorAll('.reveal').forEach(e=>e.classList.add('is-in'))"
COUNT = """document.querySelectorAll('.statcard .num').forEach(el=>{
  const t=el.__target,u=el.__unit; if(!isFinite(t))return;
  el.textContent=Math.round(t).toLocaleString('ko-KR'); if(u)el.appendChild(u);})"""


class Quiet(http.server.SimpleHTTPRequestHandler):
    def log_message(self, *a):
        pass


@contextlib.contextmanager
def serve():
    """빈 포트를 골라 리포를 그대로 서빙한다."""
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        port = s.getsockname()[1]
    handler = functools.partial(Quiet, directory=ROOT)
    httpd = socketserver.TCPServer(('127.0.0.1', port), handler)
    t = threading.Thread(target=httpd.serve_forever, daemon=True)
    t.start()
    try:
        yield 'http://127.0.0.1:%d/' % port
    finally:
        httpd.shutdown()
        httpd.server_close()


def main():
    os.makedirs(OUT, exist_ok=True)
    with serve() as base, sync_playwright() as p:
        b = p.chromium.launch()
        pg = b.new_page(viewport={'width': 1440, 'height': 900})
        for f in PAGES:
            pg.goto(base + f, wait_until='networkidle', timeout=30000)
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
    print('저장', OUT)


if __name__ == '__main__':
    main()
