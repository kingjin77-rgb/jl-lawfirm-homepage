# -*- coding: utf-8 -*-
"""변호사 개인 페이지를 data/lawyers.json 에서 생성한다.

출력: lawyers/<slug>.html (변호사 1인당 1페이지)
실행: python tools/make_lawyer_pages.py
      이후 python tools/seo.py 를 실행하면 메타태그·sitemap 에 반영된다.

프로필·경력·전문분야·컬럼 전부 lawyers.json 이 원본이다.
JSON 을 고치고 이 스크립트를 다시 실행하면 페이지가 갱신된다.
컬럼이 없는 변호사는 컬럼 섹션 자체가 생성되지 않는다.
"""
import html
import json
import os
import re
import sys

# Windows 콘솔(cp949)에서도 한글 로그가 깨지지 않도록
for _s in (sys.stdout, sys.stderr):
    try:
        _s.reconfigure(encoding="utf-8")
    except Exception:
        pass

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT_DIR = os.path.join(BASE, "lawyers")


def esc(s):
    return html.escape(str(s or ""), quote=True)


def career_html(s):
    """경력 항목 — <strong> 만 허용한다 (lawyers.js 와 동일 규칙)."""
    return esc(s).replace("&lt;strong&gt;", "<strong>").replace("&lt;/strong&gt;", "</strong>")


def strip_tags(s):
    return re.sub(r"<[^>]+>", "", s or "")


def focus_html(focus):
    cards = "".join(
        '\n        <article class="lwp-focus__card reveal">'
        '\n          <h3>%s</h3>'
        '\n          <p>%s</p>'
        '\n        </article>' % (esc(f["title"]), esc(f["desc"]))
        for f in focus
    )
    return '''
  <section class="section section--alt">
    <div class="container">
      <div class="sec-head reveal">
        <h2 class="sec-title"><span class="en">PRACTICE FOCUS</span>주력 분야</h2>
      </div>
      <div class="lwp-focus">%s
      </div>
    </div>
  </section>''' % cards


def columns_html(columns, name):
    if not columns:
        return ""
    items = "".join(
        '\n        <a class="lwp-col reveal" href="%s">'
        '\n          <span class="lwp-col__cate">%s</span>'
        '\n          <strong class="lwp-col__title">%s</strong>'
        '\n          <span class="lwp-col__desc">%s</span>'
        '\n          <span class="lwp-col__src">%s</span>'
        '\n        </a>' % (
            esc(c.get("link") or "#"),
            esc(c.get("cate", "컬럼")),
            esc(c["title"]),
            esc(c.get("desc", "")),
            esc(c.get("source", "")),
        )
        for c in columns
    )
    return '''
  <section class="section">
    <div class="container">
      <div class="sec-head reveal">
        <h2 class="sec-title"><span class="en">COLUMNS</span>%s 변호사의 <strong>글</strong></h2>
        <p class="sec-desc">직접 쓴 컬럼과 연재입니다. 실무에서 부딪히는 쟁점을 다룹니다.</p>
      </div>
      <div class="lwp-cols">%s
      </div>
    </div>
  </section>''' % (esc(name), items)


def page_html(L, all_lawyers):
    name = L["name"]
    slug = L["slug"]
    focus = L.get("focus", [])
    columns = L.get("columns", [])
    tagline = L.get("tagline", "")
    desc_meta = strip_tags(tagline)

    photo = (
        '<img src="../%s" alt="%s 변호사">' % (esc(L["photo"]), esc(name))
        if L.get("photo")
        else '<span class="initial">%s</span>' % esc(L.get("initial") or name[0])
    )

    career = "".join(
        "\n            <li>%s</li>" % career_html(c) for c in L.get("career", [])
    )

    others = "".join(
        '\n        <a class="lwp-other%s" href="%s.html">'
        '\n          <strong>%s</strong><span>%s</span>'
        '\n        </a>' % (
            " is-cur" if o["slug"] == slug else "",
            esc(o["slug"]),
            esc(o["name"]),
            esc(o.get("short") or o.get("role", "")),
        )
        for o in all_lawyers
    )

    focus_kw = " · ".join(f["title"] for f in focus) if focus else ""

    return '''<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>%(name)s %(rolePlain)s | 법무법인 제이엘</title>
<meta name="description" content="%(metaDesc)s">
<script>document.documentElement.className+=' js';</script>
<link rel="stylesheet" href="../assets/css/style.css?v=6">
</head>
<body>

<header class="header is-sub">
  <div class="container header__inner">
    <a class="logo" href="../index.html">
      <img class="logo__img logo__img--white" src="../assets/img/logo-white.png" alt="법무법인 제이엘"><img class="logo__img logo__img--color" src="../assets/img/logo.png" alt="">
    </a>
    <nav class="gnb" aria-label="주메뉴">
      <a href="../about.html">법인소개</a>
      <a href="../lawyers.html">구성원</a>
      <a href="../practice.html">업무분야</a>
      <a href="../registry.html">단체등기</a>
      <a href="../law.html">법률정보</a>
      <a href="../magazine.html">제이엘 매거진</a>
      <a href="../contact.html">오시는 길</a>
    </nav>
    <div class="header__util">
      <a class="btn-track" href="https://www.jllawfirm.kr/member/login.php" target="_blank" rel="noopener">등기진행조회</a>
      <a class="header__tel" href="tel:025370123">
        <span class="lbl">LEGAL CONSULTATION</span>
        <span class="num">02-537-0123</span>
      </a>
    </div>
    <button class="nav-toggle" type="button" aria-label="메뉴 열기" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>

<main>
  <section class="subhero">
    <div class="container subhero__inner">
      <h1>%(name)s<span class="en">%(en)s</span></h1>
      <p class="breadcrumb"><span>HOME</span><span><a href="../lawyers.html">구성원</a></span><span>%(name)s</span></p>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="lwp-profile reveal">
        <div class="lwp-profile__photo">%(photo)s</div>
        <div class="lwp-profile__body">
          <p class="lwp-profile__role"><span class="lwtier lwtier--%(tierMod)s">%(tierLabel)s</span>%(roleQual)s</p>
          <h2 class="lwp-profile__name">%(name)s<span class="en">%(en)s</span></h2>
          <p class="lwp-profile__tagline">%(tagline)s</p>
          <div class="lwp-profile__acts">
            <a class="btn btn--fill" href="../contact.html?lawyer=%(slug)s#consult">%(name)s 변호사에게 상담 문의 <span class="arrow">→</span></a>
            <a class="btn" href="tel:025370123">02-537-0123</a>
          </div>
        </div>
      </div>
    </div>
  </section>
%(focusSec)s
  <section class="section">
    <div class="container">
      <div class="sec-head reveal">
        <h2 class="sec-title"><span class="en">CAREER</span>주요 경력</h2>
      </div>
      <div class="lwp-career reveal">
        <ul class="lwslide__career lwp-career__list">%(career)s
        </ul>
      </div>
    </div>
  </section>
%(colsSec)s
  <section class="section section--alt">
    <div class="container">
      <div class="sec-head reveal">
        <h2 class="sec-title"><span class="en">MEMBERS</span>다른 구성원</h2>
      </div>
      <div class="lwp-others reveal">%(others)s
      </div>
    </div>
  </section>

  <section class="section cta">
    <div class="container cta__inner">
      <div class="reveal">
        <h2>사건은 초기 대응에서 갈립니다.</h2>
        <p>지금 상황을 알려주시면 %(name)s 변호사가 직접 검토해 드립니다.</p>
      </div>
      <div class="cta__tels reveal" data-delay="120">
        <div><span class="lbl">변호사 상담</span><a class="num" href="tel:025370123">02-537-0123</a></div>
      </div>
    </div>
  </section>
</main>

<footer class="footer">
  <div class="container">
    <div class="footer__body">
      <div>
        <p class="footer__logo"><img class="footer__logoimg" src="../assets/img/logo-white.png" alt="법무법인 제이엘"></p>
      </div>
      <div class="footer__offices">
        <address>
          <h4>서울 본사</h4>
          서울 서초구 서초대로53길 15 정원빌딩 4층<br>
          TEL 02-537-0123 &nbsp;|&nbsp; FAX 02-537-1331
        </address>
        <address>
          <h4>동탄 분사무소</h4>
          경기도 화성시 동탄순환대로 127-5 우성센트럴타워 9층 914호<br>
          TEL 1899-4252 &nbsp;|&nbsp; FAX 0505-300-6300
        </address>
      </div>
    </div>
    <div class="footer__copy">
      <span>EMAIL jllaw2020@naver.com</span>
      <a class="is-strong" href="../privacy.html">개인정보처리방침</a>
      <span class="footer__adm">직원용 — <a href="../admin/index.html">홈페이지 관리자</a> · <a href="../admin/registry.html">등기센터 관리자</a> · <a href="../admin/qna.html">법률상식 관리자</a></span>
      <span>© JL LAW FIRM. All rights reserved.</span>
    </div>
  </div>
</footer>

<div class="quick">
  <a href="tel:025370123">변호사 상담 02-537-0123</a>
</div>

<script src="../assets/js/main.js?v=6"></script>
</body>
</html>
''' % {
        "name": esc(name),
        "en": esc(L.get("en", "")),
        "role": esc(L.get("role", "변호사")),
        "rolePlain": esc(L.get("short") or L.get("role", "변호사")),
        # 법무법인은 구성원변호사(파트너)와 소속변호사를 구분해 표기한다
        "tierMod": "partner" if L.get("tier") == "구성원" else "associate",
        "tierLabel": esc(L.get("short") or ("구성원변호사" if L.get("tier") == "구성원" else "소속변호사")),
        "roleQual": ('<span class="lwp-profile__qual">%s</span>' % esc(L["role"])) if L.get("role") else "",
        "metaDesc": esc(
            "법무법인 제이엘 %s %s — %s" % (
                name, L.get("short") or "변호사",
                (focus_kw + ". " if focus_kw else "") + desc_meta,
            )
        )[:160],
        "photo": photo,
        "slug": esc(slug),
        "tagline": esc(tagline),
        "career": career,
        "focusSec": focus_html(focus) if focus else "",
        "colsSec": columns_html(columns, name),
        "others": others,
    }


def main():
    with open(os.path.join(BASE, "data", "lawyers.json"), encoding="utf-8") as f:
        data = json.load(f)
    lawyers = data.get("lawyers", [])

    missing = [L["name"] for L in lawyers if not L.get("slug")]
    if missing:
        print("slug 없는 변호사:", ", ".join(missing), "— data/lawyers.json 에 slug 를 넣어주세요.")
        return 1

    os.makedirs(OUT_DIR, exist_ok=True)
    for L in lawyers:
        path = os.path.join(OUT_DIR, L["slug"] + ".html")
        with open(path, "w", encoding="utf-8", newline="") as f:
            f.write(page_html(L, lawyers))
        n_col = len(L.get("columns", []))
        print("생성 — lawyers/%s.html (컬럼 %d건)" % (L["slug"], n_col))
    print("완료. 이어서 python tools/seo.py 를 실행하세요.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
