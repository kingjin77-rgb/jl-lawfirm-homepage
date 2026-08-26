# -*- coding: utf-8 -*-
"""찍어 둔 화면을 파트별로 묶어 한 장짜리 회의용 안내서로 만든다.

tools/shoot_pages.py 를 먼저 돌려 tools/shots/ 를 채워 둔다.
PNG 원본은 15MB가 넘으므로 폭 520으로 줄이고 JPEG로 바꿔 본문에 박아 넣는다.
너무 긴 페이지는 위에서 잘라낸다 — 메뉴북은 첫인상을 보는 물건이다.
"""
import base64
import io
import os

from PIL import Image

HERE = os.path.dirname(os.path.abspath(__file__))
SHOTS = os.path.join(HERE, 'shots')
OUT = os.path.join(HERE, 'menubook.html')

THUMB_W = 520          # 본문에 박히는 가로폭
THUMB_MAX = int(THUMB_W * 4.2)   # 이보다 길면 위에서 자른다
QUALITY = 76


def thumbs():
    """캡처를 줄여 data URI 로 바꾼다."""
    if not os.path.isdir(SHOTS):
        raise SystemExit('tools/shots 가 없다. tools/shoot_pages.py 를 먼저 돌려라.')
    out = {}
    for name in sorted(os.listdir(SHOTS)):
        if not name.endswith('.png'):
            continue
        im = Image.open(os.path.join(SHOTS, name)).convert('RGB')
        im = im.resize((THUMB_W, int(im.height * THUMB_W / im.width)), Image.LANCZOS)
        if im.height > THUMB_MAX:
            im = im.crop((0, 0, THUMB_W, THUMB_MAX))
        buf = io.BytesIO()
        im.save(buf, 'JPEG', quality=QUALITY, optimize=True)
        out[name[:-4]] = 'data:image/jpeg;base64,' + base64.b64encode(buf.getvalue()).decode()
    if not out:
        raise SystemExit('tools/shots 에 PNG 가 없다.')
    return out


imgs = thumbs()

PARTS = [
 ("01", "진입", "검색으로 들어온 사람이 자기 사건이 여기 있는지 확인하고 넘어가는 자리.", [
   ("index", "메인", "index.html",
    "히어로에서 시작해 법인소개, 구성원, 주요업무, 재건축·재개발, 사건을 대하는 방식, 단체등기, 아파트친구, 등기센터, 매거진 순으로 내려간다.",
    ["섹션 11", "단체컷 배치", "이번 주 순서 변경"]),
 ]),
 ("02", "법인", "누가 하는가. 단체컷을 붙인 두 장.", [
   ("about", "법인소개", "about.html",
    "설립 배경과 세 가지 원칙, 법인 개요, 지명원 PDF 내려받기.",
    ["섹션 7", "단체컷 배치", "지명원 다운로드"]),
   ("lawyers", "구성원", "lawyers.html",
    "단체컷 밴드 아래 슬라이드쇼. 여섯 명 전원의 약력과 담당 분야.",
    ["변호사 6인", "단체컷 배치", "개별 상세"]),
 ]),
 ("03", "업무분야", "무엇을 하는가. 재건축·재개발을 이번 주에 메인 앞자리로 올렸다.", [
   ("practice", "업무분야", "practice.html",
    "여섯 분야 카드에서 각 상세로 갈라진다. 민사·형사, 하자소송, 가사·상속·이혼은 이 페이지 안에 있다.",
    ["섹션 8", "분야 6종"]),
   ("corporate", "기업법무", "corporate.html",
    "중소기업 상시 자문과 법인등기. 주요 자문 고객사를 싣는다.",
    ["섹션 5", "자문 고객사"]),
   ("redevelopment", "재건축·재개발", "redevelopment.html",
    "조합 측과 조합원 측을 갈라 보여준다. 6단계 타임라인 탭에서 단계별 쟁점을 연다.",
    ["섹션 9", "법정 기한 4종", "이번 주 신설"]),
   ("redevelopment-desk", "정비사업 동향", "redevelopment-desk.html",
    "월 1회 갱신하는 동향 리포트와 도시정비법 조문 지도.",
    ["섹션 7", "월간 갱신", "조문 지도"]),
 ]),
 ("04", "단체등기", "주력 분야. 등기 계열은 이번 주에 메인 후순위로 내렸다.", [
   ("registry", "단체등기", "registry.html",
    "입주 아파트와 분양전환, 대지권. 진행 절차와 수행 단지 실적.",
    ["섹션 8", "수행 단지 실적"]),
   ("registry-detail", "상세안내", "registry-detail.html",
    "유형별 상세. LH 분양전환 납부방식, NHF리츠 취급실적, 아파트친구 연결.",
    ["섹션 5", "45개 단지 실적", "아파트친구 연결"]),
   ("dongtan", "등기센터", "dongtan.html",
    "개별등기 온라인 접수, 진행 조회, 비용 계산기, 준비서류 체크리스트. 채팅 상담은 이 페이지에만 붙였다.",
    ["섹션 9", "채팅 상담", "비용 계산기"]),
 ]),
 ("05", "정보", "검색으로 사람을 데려오는 자리. 두 곳은 자동으로 갱신된다.", [
   ("law", "법률정보", "law.html",
    "법제처 API로 법령 개정과 판례를 주 1회 자동 수집한다. 제이엘 자체 분석을 덧붙인다.",
    ["주 1회 자동", "법제처 API"]),
   ("magazine", "제이엘 매거진", "magazine.html",
    "THE ASSET 리걸 매거진과 분양공고 검토보고서.",
    ["관리자에서 발행"]),
   ("qna", "질의응답", "qna.html",
    "받은 질문과 답변. 관리자 페이지에서 등록한다.",
    ["질문 24건", "관리자 등록"]),
 ]),
 ("06", "연락", "", [
   ("contact", "오시는 길", "contact.html",
    "본사와 동탄 분사무소, 지도, 상담 문의.",
    ["사무소 2곳", "지도 임베드"]),
 ]),
]

ADMIN = [
 ("관리자 홈", "admin/index.html", "매거진과 검토보고서 발행"),
 ("등기센터 관리", "admin/registry.html", "채권 할인율, 수행 단지"),
 ("질의응답 관리", "admin/qna.html", "질문 등록과 답변"),
]

CHANGES = [
 ("메인 섹션 순서", "재건축·재개발을 업무분야 바로 뒤로 올리고, 단체등기와 아파트친구, 등기센터를 뒤로 내렸다.", "배포됨"),
 ("재건축·재개발 신설", "메인에 재개발 섹션이 아예 없었다. 법정 기한 네 가지와 조합·조합원 카드로 새로 만들었다.", "배포됨"),
 ("정비사업 동향", "손으로 쓰는 글이라 주간 갱신을 못 따라갔다. 월 1회로 낮추고 갱신 절차를 파일에 적어 뒀다.", "배포됨"),
 ("단체컷", "메인과 법인소개, 구성원 세 곳에 배치했다. 실물 사진만 합성했고 얼굴은 만들지 않았다.", "확인 필요"),
 ("위임장 문구", "전자접수가 실제로 동작한 적이 없다. 관리 프로그램으로 바꾸고 거짓 문구를 지웠다.", "배포됨"),
]

AGENDA = [
 ("메인 순서", "재건축·재개발이 앞으로, 등기 계열이 뒤로. 이대로 갈지."),
 ("단체컷", "6인 화이트를 세 페이지에 넣었다. 5인 컷도 있다. 재촬영 여부."),
 ("데이터 서버", "가비아 PHP 베이직 무제한 월 10,450원에 설치비 11,000원. 계정 개설이 필요하다."),
 ("게이트 비밀번호", "현재 1234. 바꿔야 한다."),
]


def card(slug, name, path, desc, tags):
    img = imgs.get(slug, '')
    tg = ''.join('<li>%s</li>' % t for t in tags)
    return ('<article class="pg">\n'
            '  <div class="pg__shot"><img src="%s" alt="%s 페이지 화면" loading="lazy"></div>\n'
            '  <div class="pg__text">\n'
            '    <h3 class="pg__name">%s</h3>\n'
            '    <p class="pg__path">%s</p>\n'
            '    <p class="pg__desc">%s</p>\n'
            '    <ul class="pg__tags">%s</ul>\n'
            '  </div>\n'
            '</article>') % (img, name, name, path, desc, tg)


parts_html = []
for no, title, lead, pages in PARTS:
    cards = '\n'.join(card(*p) for p in pages)
    lead_html = '<p class="part__lead">%s</p>' % lead if lead else ''
    parts_html.append(
        '<section class="part">\n'
        '  <header class="part__head">\n'
        '    <span class="part__no">%s</span>\n'
        '    <h2 class="part__title">%s</h2>\n'
        '    <span class="part__count">%d장</span>\n'
        '    %s\n'
        '  </header>\n'
        '  <div class="pgs">\n%s\n  </div>\n'
        '</section>' % (no, title, len(pages), lead_html, cards))

admin_html = '\n'.join(
    '<tr><td>%s</td><td class="mono">%s</td><td>%s</td></tr>' % a for a in ADMIN)
chg_html = '\n'.join(
    '<tr><td>%s</td><td>%s</td><td><span class="st st--%s">%s</span></td></tr>'
    % (a, b, 'ok' if c == '배포됨' else 'wait', c) for a, b, c in CHANGES)
ag_html = '\n'.join('<li><b>%s</b><span>%s</span></li>' % a for a in AGENDA)

CSS = """
:root{
  --ground:#f5f6fa; --surface:#ffffff; --sunk:#eceff7;
  --ink:#111a30; --ink-2:#3d475f; --ink-3:#6d7791;
  --navy:#0d2162; --gold:#8f7033; --line:#dde2ee;
  --ok:#1f6b4a; --wait:#9a5a12;
}
@media (prefers-color-scheme: dark){
  :root:not([data-theme="light"]){
    --ground:#0a0f1e; --surface:#131a2e; --sunk:#0f1526;
    --ink:#e9edf8; --ink-2:#b3bcd2; --ink-3:#8791a9;
    --navy:#93a9e8; --gold:#d3b071; --line:#242e48;
    --ok:#6fd3a4; --wait:#e2b062;
  }
}
:root[data-theme="dark"]{
  --ground:#0a0f1e; --surface:#131a2e; --sunk:#0f1526;
  --ink:#e9edf8; --ink-2:#b3bcd2; --ink-3:#8791a9;
  --navy:#93a9e8; --gold:#d3b071; --line:#242e48;
  --ok:#6fd3a4; --wait:#e2b062;
}
*{box-sizing:border-box}
body{
  margin:0; background:var(--ground); color:var(--ink);
  font-family:"Noto Sans KR",system-ui,-apple-system,"Malgun Gothic",sans-serif;
  font-size:15.5px; line-height:1.75; letter-spacing:-.01em;
  -webkit-font-smoothing:antialiased;
}
.wrap{max-width:1180px; margin:0 auto; padding:0 24px}
.mono{font-family:"IBM Plex Mono",ui-monospace,Menlo,monospace; font-variant-numeric:tabular-nums}
.cover{padding:76px 0 46px; border-bottom:1px solid var(--line)}
.cover__eyebrow{
  margin:0 0 14px; font-size:12px; letter-spacing:.22em;
  color:var(--gold); font-weight:700;
  font-family:"IBM Plex Mono",ui-monospace,monospace;
}
.cover h1{
  margin:0; font-family:"Gowun Batang",serif; font-weight:700;
  font-size:clamp(34px,5.4vw,54px); line-height:1.24; letter-spacing:-.02em;
  text-wrap:balance;
}
.cover__sub{margin:18px 0 0; max-width:62ch; color:var(--ink-2)}
.facts{display:flex; flex-wrap:wrap; gap:14px 34px; margin:32px 0 0; padding:0; list-style:none}
.facts li{display:flex; flex-direction:column; gap:1px}
.facts b{
  font-family:"IBM Plex Mono",ui-monospace,monospace; font-size:25px;
  font-weight:500; color:var(--navy); font-variant-numeric:tabular-nums; line-height:1.2;
}
.facts span{font-size:12.5px; color:var(--ink-3); letter-spacing:.02em}
.part{padding:56px 0 10px; border-bottom:1px solid var(--line)}
.part__head{
  display:grid; grid-template-columns:auto 1fr auto; align-items:baseline;
  gap:4px 16px; margin:0 0 26px;
}
.part__no{
  font-family:"IBM Plex Mono",ui-monospace,monospace; font-size:13px;
  font-weight:500; color:var(--gold); letter-spacing:.1em;
}
.part__title{
  margin:0; font-family:"Gowun Batang",serif; font-size:27px;
  font-weight:700; letter-spacing:-.02em;
}
.part__count{font-family:"IBM Plex Mono",ui-monospace,monospace; font-size:12.5px; color:var(--ink-3)}
.part__lead{
  grid-column:2 / -1; margin:2px 0 0; color:var(--ink-2);
  font-size:14.5px; max-width:70ch;
}
.pgs{display:grid; grid-template-columns:repeat(auto-fill,minmax(258px,1fr)); gap:26px}
.pg{
  display:flex; flex-direction:column; background:var(--surface);
  border:1px solid var(--line); border-radius:5px; overflow:hidden;
}
.pg__shot{height:214px; overflow:hidden; background:var(--sunk); border-bottom:1px solid var(--line)}
.pg__shot img{display:block; width:100%; height:auto}
.pg__text{padding:16px 17px 18px; display:flex; flex-direction:column; gap:7px}
.pg__name{margin:0; font-size:17px; font-weight:700; letter-spacing:-.02em}
.pg__path{
  margin:0; font-family:"IBM Plex Mono",ui-monospace,monospace;
  font-size:11.5px; color:var(--ink-3); word-break:break-all;
}
.pg__desc{margin:2px 0 0; font-size:14px; line-height:1.68; color:var(--ink-2)}
.pg__tags{display:flex; flex-wrap:wrap; gap:5px; margin:5px 0 0; padding:0; list-style:none}
.pg__tags li{
  font-size:11.5px; line-height:1.65; padding:1px 8px; border-radius:2px;
  background:var(--sunk); color:var(--ink-2); border:1px solid var(--line);
}
.block{padding:56px 0; border-bottom:1px solid var(--line)}
.block h2{
  margin:0 0 6px; font-family:"Gowun Batang",serif; font-size:27px;
  font-weight:700; letter-spacing:-.02em;
}
.block p.lead{margin:0 0 24px; color:var(--ink-2); max-width:70ch}
.tw{overflow-x:auto}
table{border-collapse:collapse; width:100%; min-width:560px; font-size:14.5px}
th,td{text-align:left; padding:12px 14px; border-bottom:1px solid var(--line); vertical-align:top}
thead th{
  font-size:11.5px; letter-spacing:.12em; color:var(--ink-3); font-weight:700;
  border-bottom:1px solid var(--ink-3);
  font-family:"IBM Plex Mono",ui-monospace,monospace;
}
tbody tr:last-child td{border-bottom:none}
td.mono{font-size:12.5px; color:var(--ink-3)}
.st{font-size:12px; font-weight:700; white-space:nowrap}
.st--ok{color:var(--ok)}
.st--wait{color:var(--wait)}
.agenda{list-style:none; margin:0; padding:0; counter-reset:a}
.agenda li{
  counter-increment:a; display:grid; grid-template-columns:auto 1fr;
  gap:3px 18px; padding:16px 0; border-bottom:1px solid var(--line);
}
.agenda li:last-child{border-bottom:none}
.agenda li::before{
  content:counter(a,decimal-leading-zero);
  font-family:"IBM Plex Mono",ui-monospace,monospace; font-size:13px;
  color:var(--gold); grid-row:1 / span 2; padding-top:4px;
}
.agenda b{font-size:16.5px; letter-spacing:-.02em}
.agenda span{color:var(--ink-2); font-size:14.5px}
footer{padding:34px 0 62px; color:var(--ink-3); font-size:12.5px}
.pg:hover{border-color:var(--navy)}
@media (prefers-reduced-motion:no-preference){.pg{transition:border-color .18s ease}}
@media (max-width:640px){
  .cover{padding:48px 0 34px}
  .part__head{grid-template-columns:auto 1fr}
  .part__count{display:none}
}

/* 인쇄 — 회의 배포용. 카드와 표가 페이지 경계에서 잘리지 않게 한다. */
@media print{
  :root{
    --ground:#ffffff; --surface:#ffffff; --sunk:#f2f4f9;
    --ink:#111a30; --ink-2:#3d475f; --ink-3:#6d7791;
    --navy:#0d2162; --gold:#8f7033; --line:#c9d0e0;
    --ok:#1f6b4a; --wait:#8a4f10;
  }
  body{font-size:10.5pt; line-height:1.6}
  .wrap{max-width:none; padding:0}
  .cover{padding:0 0 18pt; break-after:page}
  .cover h1{font-size:30pt; line-height:1.25}
  .cover__sub{font-size:10.5pt}
  .facts b{font-size:18pt}
  .part{padding:0 0 6pt; break-before:page; border-bottom:none}
  .part:first-of-type{break-before:auto}
  .part__title{font-size:18pt}
  .pgs{grid-template-columns:repeat(2,1fr); gap:14pt}
  .pg{break-inside:avoid; border-color:var(--line)}
  .pg__shot{height:150pt}
  .pg__name{font-size:12pt}
  .pg__desc{font-size:9.5pt; line-height:1.55}
  .pg__tags li{font-size:8pt}
  .pg:hover{border-color:var(--line)}
  .block{padding:0 0 14pt; break-before:page; border-bottom:none}
  .block h2{font-size:18pt}
  table{min-width:0; font-size:10pt}
  tr{break-inside:avoid}
  .agenda li{break-inside:avoid}
  footer{padding:14pt 0 0}
}
"""

HTML = ('<title>제이엘 홈페이지 메뉴북</title>\n'
        '<link rel="preconnect" href="https://fonts.googleapis.com">\n'
        '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>\n'
        '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?'
        'family=Gowun+Batang:wght@400;700&family=Noto+Sans+KR:wght@400;500;700&'
        'family=IBM+Plex+Mono:wght@400;500&display=swap">\n'
        '<style>%s</style>\n\n'
        '<div class="wrap">\n\n'
        '<header class="cover">\n'
        '  <p class="cover__eyebrow">JL LAW FIRM SITE MENU BOOK</p>\n'
        '  <h1>제이엘 홈페이지<br>무엇이 어디에 있는가</h1>\n'
        '  <p class="cover__sub">회의용 구성 안내서입니다. 화면은 2026년 8월 26일 배포본을 그대로 찍었습니다. '
        '파트별로 어떤 페이지가 무슨 일을 하는지, 이번 주에 무엇이 바뀌었는지 정리했습니다.</p>\n'
        '  <ul class="facts">\n'
        '    <li><b>14</b><span>공개 페이지</span></li>\n'
        '    <li><b>6</b><span>파트</span></li>\n'
        '    <li><b>3</b><span>관리자 페이지</span></li>\n'
        '    <li><b>2</b><span>자동 갱신</span></li>\n'
        '    <li><b>5</b><span>이번 주 변경</span></li>\n'
        '  </ul>\n'
        '</header>\n\n'
        '%s\n\n'
        '<section class="block">\n'
        '  <h2>관리자 페이지</h2>\n'
        '  <p class="lead">주소를 알아야 들어갈 수 있고, 게이트 비밀번호로 한 번 더 막혀 있습니다. 메뉴에는 노출하지 않습니다.</p>\n'
        '  <div class="tw"><table>\n'
        '    <thead><tr><th>페이지</th><th>경로</th><th>하는 일</th></tr></thead>\n'
        '    <tbody>\n%s\n    </tbody>\n'
        '  </table></div>\n'
        '</section>\n\n'
        '<section class="block">\n'
        '  <h2>이번 주에 바뀐 것</h2>\n'
        '  <p class="lead">배포까지 끝난 항목과 회의에서 확인이 필요한 항목을 나눠 적었습니다.</p>\n'
        '  <div class="tw"><table>\n'
        '    <thead><tr><th>항목</th><th>내용</th><th>상태</th></tr></thead>\n'
        '    <tbody>\n%s\n    </tbody>\n'
        '  </table></div>\n'
        '</section>\n\n'
        '<section class="block">\n'
        '  <h2>오늘 정할 것</h2>\n'
        '  <ol class="agenda">\n%s\n  </ol>\n'
        '</section>\n\n'
        '<footer>법무법인 제이엘 · 2026년 8월 26일 기준 · 화면은 실제 배포본 캡처</footer>\n\n'
        '</div>\n') % (CSS, '\n\n'.join(parts_html), admin_html, chg_html, ag_html)

with io.open(OUT, 'w', encoding='utf-8') as fp:
    fp.write(HTML)
print('작성 완료 %s (%.2fMB)' % (OUT, len(HTML.encode('utf-8')) / 1e6))
