# -*- coding: utf-8 -*-
"""검색·공유용 메타태그와 구조화 데이터를 각 페이지에 주입한다.

실행: python tools/seo.py
      python tools/seo.py --site https://다른도메인.co.kr

각 페이지의 <title> 과 description 은 HTML 파일에 있는 것을 그대로 읽어 쓴다.
이 스크립트가 만드는 것은 그 두 가지에서 파생되는 항목들이다.

  - canonical (대표 주소 — 같은 내용이 여러 주소로 잡히는 것을 막는다)
  - Open Graph / 트위터 카드 (카카오톡·페이스북 공유 미리보기)
  - JSON-LD 구조화 데이터 (검색결과에 사무소 정보·이동경로 노출)
  - sitemap.xml / robots.txt

주입 결과는 아래 표시 사이에만 들어가므로 몇 번을 실행해도 중복되지 않는다.

    <!-- SEO:AUTO --> … <!-- /SEO:AUTO -->

HTML 을 직접 고친 뒤 다시 실행하면 바뀐 title·description 이 반영된다.
"""
import argparse
import glob
import json
import os
import re
import sys
from datetime import datetime, timedelta, timezone

# Windows 콘솔(cp949)에서도 한글 로그가 깨지지 않도록
for _s in (sys.stdout, sys.stderr):
    try:
        _s.reconfigure(encoding="utf-8")
    except Exception:
        pass

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
KST = timezone(timedelta(hours=9))

# 배포 도메인. 바뀌면 여기만 고치고 다시 실행한다.
SITE = "https://www.jllawfirm.co.kr"

OG_IMAGE = "assets/img/og-default.jpg"
SITE_NAME = "법무법인 제이엘"

BEGIN = "<!-- SEO:AUTO -->"
END = "<!-- /SEO:AUTO -->"

# 검색에 걸릴 필요가 없는 경로 (admin/ 하위는 전부)
NOINDEX = {"admin/index.html", "admin/registry.html", "404.html"}

# sitemap 우선순위 — 적을수록 후순위. 없으면 0.6
PRIORITY = {
    "index.html": "1.0",
    "practice.html": "0.9",
    "registry.html": "0.9",
    "dongtan.html": "0.9",
    "lawyers.html": "0.9",
    "contact.html": "0.8",
    "about.html": "0.8",
    "corporate.html": "0.8",
    "magazine.html": "0.7",
    "law.html": "0.7",
}

# 이동경로(빵부스러기) — 검색결과에 "홈 > 업무분야" 형태로 노출된다
CRUMBS = {
    "about.html": ["법인소개"],
    "lawyers.html": ["구성원"],
    "practice.html": ["업무분야"],
    "corporate.html": ["업무분야", "기업법무"],
    "registry.html": ["단체등기"],
    "dongtan.html": ["단체등기", "단체등기센터"],
    "law.html": ["법률정보"],
    "magazine.html": ["제이엘 매거진"],
    "contact.html": ["오시는 길"],
    "privacy.html": ["개인정보처리방침"],
}
CRUMB_PARENT = {
    "corporate.html": "practice.html",
    "dongtan.html": "registry.html",
}

OFFICES = [
    {
        "name": "법무법인 제이엘 서울 본사",
        "street": "서초대로53길 15 정원빌딩 4층",
        "locality": "서초구",
        "region": "서울특별시",
        "postal": "06643",
        "tel": "+82-2-537-0123",
        "fax": "+82-2-537-1331",
        "lat": 37.4955361954462,
        "lng": 127.014646495883,
    },
    {
        "name": "법무법인 제이엘 동탄 분사무소",
        "street": "동탄순환대로 127-5 우성센트럴타워 9층 914호",
        "locality": "화성시",
        "region": "경기도",
        "postal": "18471",
        "tel": "+82-1899-4252",
        "fax": "+82-505-300-6300",
        "lat": 37.166027084515264,
        "lng": 127.10730396739461,
    },
]

SERVICES = [
    "민사소송", "부동산소송", "형사변호", "기업법무", "법인등기",
    "공동주택 단체등기", "하자소송", "재건축·재개발", "상속", "조세",
]


def read(path):
    with open(path, encoding="utf-8") as f:
        return f.read()


def write(path, text):
    with open(path, "w", encoding="utf-8", newline="") as f:
        f.write(text)


def get_title(html):
    m = re.search(r"<title>(.*?)</title>", html, re.S)
    return re.sub(r"\s+", " ", m.group(1)).strip() if m else SITE_NAME


def get_desc(html):
    m = re.search(r'<meta\s+name="description"\s+content="(.*?)"', html, re.S)
    return re.sub(r"\s+", " ", m.group(1)).strip() if m else ""


def strip_managed(html):
    """이전 실행분과, 손으로 넣어둔 중복 태그를 걷어낸다."""
    html = re.sub(re.escape(BEGIN) + r".*?" + re.escape(END) + r"\s*", "", html, flags=re.S)
    html = re.sub(
        r'[ \t]*<meta\s+(?:property|name)="(?:og:[\w:]+|twitter:[\w:]+)"[^>]*>\r?\n?',
        "", html,
    )
    html = re.sub(r'[ \t]*<link\s+rel="canonical"[^>]*>\r?\n?', "", html)
    html = re.sub(r'[ \t]*<meta\s+name="robots"[^>]*>\r?\n?', "", html)
    html = re.sub(r'[ \t]*<link\s+rel="icon"[^>]*>\r?\n?', "", html)
    return html


def indent(obj):
    return json.dumps(obj, ensure_ascii=False, indent=2)


def org_node():
    """법인 자체 — 모든 페이지가 이 노드를 참조한다."""
    return {
        "@type": "LegalService",
        "@id": SITE + "/#organization",
        "name": SITE_NAME,
        "alternateName": "JL Law Firm",
        "url": SITE + "/",
        "logo": SITE + "/" + OG_IMAGE,
        "image": SITE + "/" + OG_IMAGE,
        "email": "jllaw2020@naver.com",
        "telephone": OFFICES[0]["tel"],
        "foundingDate": "2020",
        "priceRange": "상담 후 협의",
        "areaServed": [
            {"@type": "AdministrativeArea", "name": "대한민국"},
        ],
        "knowsLanguage": ["ko"],
        "address": {
            "@type": "PostalAddress",
            "streetAddress": OFFICES[0]["street"],
            "addressLocality": OFFICES[0]["locality"],
            "addressRegion": OFFICES[0]["region"],
            "postalCode": OFFICES[0]["postal"],
            "addressCountry": "KR",
        },
        "openingHoursSpecification": [{
            "@type": "OpeningHoursSpecification",
            "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
            "opens": "09:00",
            "closes": "18:00",
        }],
        "hasOfferCatalog": {
            "@type": "OfferCatalog",
            "name": "업무분야",
            "itemListElement": [
                {"@type": "Offer", "itemOffered": {"@type": "Service", "name": s}}
                for s in SERVICES
            ],
        },
    }


def branch_nodes():
    out = []
    for o in OFFICES:
        out.append({
            "@type": "LegalService",
            "@id": SITE + "/#office-" + ("seoul" if o["locality"] == "서초구" else "dongtan"),
            "name": o["name"],
            "parentOrganization": {"@id": SITE + "/#organization"},
            "telephone": o["tel"],
            "faxNumber": o["fax"],
            "url": SITE + "/contact.html",
            "address": {
                "@type": "PostalAddress",
                "streetAddress": o["street"],
                "addressLocality": o["locality"],
                "addressRegion": o["region"],
                "postalCode": o["postal"],
                "addressCountry": "KR",
            },
            "geo": {"@type": "GeoCoordinates", "latitude": o["lat"], "longitude": o["lng"]},
            "openingHoursSpecification": [{
                "@type": "OpeningHoursSpecification",
                "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
                "opens": "09:00",
                "closes": "18:00",
            }],
        })
    return out


def crumbs_node(rel, title=""):
    if rel.startswith("lawyers/"):
        leaf = title.split("|")[0].strip() or rel
        return {"@type": "BreadcrumbList", "itemListElement": [
            {"@type": "ListItem", "position": 1, "name": "홈", "item": SITE + "/"},
            {"@type": "ListItem", "position": 2, "name": "구성원",
             "item": SITE + "/lawyers.html"},
            {"@type": "ListItem", "position": 3, "name": leaf, "item": SITE + "/" + rel},
        ]}
    names = CRUMBS.get(rel)
    if not names:
        return None
    items = [{"@type": "ListItem", "position": 1, "name": "홈", "item": SITE + "/"}]
    pos = 2
    parent = CRUMB_PARENT.get(rel)
    if parent and len(names) > 1:
        items.append({
            "@type": "ListItem", "position": pos,
            "name": names[0], "item": SITE + "/" + parent,
        })
        pos += 1
        leaf = names[-1]
    else:
        leaf = names[0]
    items.append({"@type": "ListItem", "position": pos, "name": leaf,
                  "item": SITE + "/" + rel})
    return {"@type": "BreadcrumbList", "itemListElement": items}


def _lawyers_data():
    path = os.path.join(BASE, "data", "lawyers.json")
    if not os.path.exists(path):
        return []
    with open(path, encoding="utf-8") as f:
        return json.load(f).get("lawyers", [])


def _person(L):
    slug = L.get("slug") or L["en"].replace(" ", "-").lower()
    node = {
        "@type": "Person",
        "@id": SITE + "/#person-" + slug,
        "name": L["name"],
        "alternateName": L["en"],
        "jobTitle": L["role"],
        "description": re.sub(r"<[^>]+>", "", L.get("tagline", "")),
        "worksFor": {"@id": SITE + "/#organization"},
        "knowsAbout": [f["title"] for f in L.get("focus", [])] or SERVICES,
    }
    if L.get("slug"):
        node["url"] = SITE + "/lawyers/" + L["slug"] + ".html"
    return node


def person_nodes():
    """구성원 변호사 — 검색결과에 사람 정보로 잡히게 한다."""
    return [_person(L) for L in _lawyers_data()]


def person_node_for(rel):
    """개인 페이지 — 해당 변호사 1인의 노드만 붙인다."""
    slug = os.path.splitext(os.path.basename(rel))[0]
    for L in _lawyers_data():
        if L.get("slug") == slug:
            return _person(L)
    return None


def graph_for(rel, title, desc):
    page_id = SITE + "/" + ("" if rel == "index.html" else rel)
    page = {
        "@type": "WebPage",
        "@id": page_id + "#webpage",
        "url": page_id,
        "name": title,
        "isPartOf": {"@id": SITE + "/#website"},
        "about": {"@id": SITE + "/#organization"},
        "inLanguage": "ko-KR",
    }
    if desc:
        page["description"] = desc

    graph = [
        {
            "@type": "WebSite",
            "@id": SITE + "/#website",
            "url": SITE + "/",
            "name": SITE_NAME,
            "publisher": {"@id": SITE + "/#organization"},
            "inLanguage": "ko-KR",
        },
        page,
    ]
    if rel == "index.html":
        graph.append(org_node())
        graph.extend(branch_nodes())
    elif rel == "contact.html":
        graph.extend(branch_nodes())
    elif rel == "lawyers.html":
        graph.extend(person_nodes())
    elif rel.startswith("lawyers/"):
        p = person_node_for(rel)
        if p:
            graph.append(p)

    c = crumbs_node(rel, title)
    if c:
        graph.append(c)
    return {"@context": "https://schema.org", "@graph": graph}


def block_for(rel, title, desc, depth):
    up = "../" * depth
    url = SITE + "/" + ("" if rel == "index.html" else rel)
    lines = [BEGIN, '<link rel="canonical" href="%s">' % url]

    if rel in NOINDEX:
        lines.append('<meta name="robots" content="noindex, nofollow">')
    else:
        lines.append('<meta name="robots" content="index, follow, max-image-preview:large">')

    lines += [
        '<meta property="og:type" content="%s">' % ("website" if rel == "index.html" else "article"),
        '<meta property="og:site_name" content="%s">' % SITE_NAME,
        '<meta property="og:locale" content="ko_KR">',
        '<meta property="og:url" content="%s">' % url,
        '<meta property="og:title" content="%s">' % title,
    ]
    if desc:
        lines.append('<meta property="og:description" content="%s">' % desc)
    lines += [
        '<meta property="og:image" content="%s/%s">' % (SITE, OG_IMAGE),
        '<meta property="og:image:width" content="1200">',
        '<meta property="og:image:height" content="630">',
        '<meta property="og:image:alt" content="%s">' % SITE_NAME,
        '<meta name="twitter:card" content="summary_large_image">',
        '<meta name="twitter:title" content="%s">' % title,
    ]
    if desc:
        lines.append('<meta name="twitter:description" content="%s">' % desc)
    lines += [
        '<meta name="twitter:image" content="%s/%s">' % (SITE, OG_IMAGE),
        '<meta name="author" content="%s">' % SITE_NAME,
        '<link rel="icon" href="%sassets/img/favicon.svg" type="image/svg+xml">' % up,
        '<script type="application/ld+json">',
        indent(graph_for(rel, title, desc)),
        '</script>',
        END,
    ]
    return "\n".join(lines) + "\n"


def pages():
    out = []
    for p in sorted(glob.glob(os.path.join(BASE, "*.html"))):
        out.append(p)
    for p in sorted(glob.glob(os.path.join(BASE, "lawyers", "*.html"))):
        out.append(p)
    for p in sorted(glob.glob(os.path.join(BASE, "admin", "*.html"))):
        out.append(p)
    return out


def build_sitemap(rels):
    today = datetime.now(KST).strftime("%Y-%m-%d")
    rows = []
    for rel in rels:
        if rel in NOINDEX:
            continue
        loc = SITE + "/" + ("" if rel == "index.html" else rel)
        rows.append(
            "  <url>\n"
            "    <loc>%s</loc>\n"
            "    <lastmod>%s</lastmod>\n"
            "    <changefreq>%s</changefreq>\n"
            "    <priority>%s</priority>\n"
            "  </url>" % (
                loc, today,
                "daily" if rel == "law.html" else "monthly",
                PRIORITY.get(rel, "0.8" if rel.startswith("lawyers/") else "0.6"),
            )
        )
    xml = (
        '<?xml version="1.0" encoding="UTF-8"?>\n'
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
        + "\n".join(rows) + "\n</urlset>\n"
    )
    write(os.path.join(BASE, "sitemap.xml"), xml)
    return len(rows)


def build_robots():
    txt = (
        "User-agent: *\n"
        "Allow: /\n"
        "Disallow: /admin/\n"
        "\n"
        "Sitemap: %s/sitemap.xml\n" % SITE
    )
    write(os.path.join(BASE, "robots.txt"), txt)


def main():
    global SITE
    ap = argparse.ArgumentParser()
    ap.add_argument("--site", help="배포 도메인 (예: https://www.jllawfirm.co.kr)")
    a = ap.parse_args()
    if a.site:
        SITE = a.site.rstrip("/")

    rels = []
    for path in pages():
        rel = os.path.relpath(path, BASE).replace("\\", "/")
        depth = rel.count("/")
        html = read(path)

        title = get_title(html)
        desc = get_desc(html)
        html = strip_managed(html)

        block = block_for(rel, title, desc, depth)
        if "</head>" not in html:
            print("건너뜀 (head 없음) —", rel)
            continue
        html = html.replace("</head>", block + "</head>", 1)
        write(path, html)
        rels.append(rel)
        print("주입 —", rel)

    n = build_sitemap(rels)
    build_robots()
    print("sitemap.xml %d건 · robots.txt 생성 · 기준 도메인 %s" % (n, SITE))
    return 0


if __name__ == "__main__":
    sys.exit(main())
