# -*- coding: utf-8 -*-
"""법제처 국가법령정보 Open API로 법률정보 피드를 생성한다.

출력: jl-homepage/data/law-feed.json
실행: python tools/fetch_law_feed.py
환경변수: LAW_GO_KR_OC (없으면 --oc 인자)

제이엘 업무분야(민사·형사 / 기업법무 / 단체등기 / 하자소송 / 재건축·재개발)에
직접 걸리는 법령의 시행·개정 상태와 관련 대법원 판례를 모은다.
"""
import argparse
import json
import os
import sys
import urllib.parse
import urllib.request
from datetime import datetime, timezone, timedelta

BASE = "https://www.law.go.kr/DRF"
KST = timezone(timedelta(hours=9))

# Windows 콘솔(cp949)에서도 한글 로그가 깨지지 않도록
for _s in (sys.stdout, sys.stderr):
    try:
        _s.reconfigure(encoding="utf-8")
    except Exception:
        pass

# 추적 대상 법령 — 정확한 법령명으로 조회한다(부분일치 시 엉뚱한 시행규칙이 잡힘).
LAWS = [
    ("집합건물의 소유 및 관리에 관한 법률", "하자소송"),
    ("공동주택관리법", "하자소송"),
    ("주택법", "단체등기"),
    ("부동산등기법", "단체등기"),
    ("민간임대주택에 관한 특별법", "단체등기"),
    ("도시 및 주거환경정비법", "재건축·재개발"),
    ("빈집 및 소규모주택 정비에 관한 특례법", "재건축·재개발"),
    ("공익사업을 위한 토지 등의 취득 및 보상에 관한 법률", "재건축·재개발"),
    ("주택임대차보호법", "민사·형사"),
    ("상가건물 임대차보호법", "민사·형사"),
    ("민사집행법", "민사·형사"),
    ("상법", "기업법무"),
]

# 판례 검색어 — 분야별 핵심 쟁점
PREC_QUERIES = [
    ("하자보수보증금", "하자소송"),
    ("공동주택 하자담보책임", "하자소송"),
    ("관리처분계획 무효", "재건축·재개발"),
    ("현금청산금", "재건축·재개발"),
    ("사해행위취소", "민사·형사"),
    ("소유권이전등기 말소", "단체등기"),
]


def fetch(path, params):
    url = "%s/%s?%s" % (BASE, path, urllib.parse.urlencode(params))
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
    with urllib.request.urlopen(req, timeout=30) as r:
        return json.loads(r.read().decode("utf-8", "replace"))


def as_list(v):
    if v is None:
        return []
    return v if isinstance(v, list) else [v]


def fmt_date(s):
    """20260701 -> 2026.07.01"""
    s = str(s or "")
    return "%s.%s.%s" % (s[0:4], s[4:6], s[6:8]) if len(s) == 8 else s


def collect_laws(oc):
    out = []
    for name, cat in LAWS:
        try:
            d = fetch("lawSearch.do", {"OC": oc, "target": "law", "type": "JSON",
                                       "query": name, "display": 20})
            items = as_list(d.get("LawSearch", {}).get("law"))
            # 법령명이 정확히 일치하는 것만 채택 (시행령·시행규칙 배제)
            exact = [i for i in items if (i.get("법령명한글") or "").strip() == name]
            if not exact:
                print("  [skip] 정확 일치 없음: %s" % name, file=sys.stderr)
                continue
            it = exact[0]
            law_id = it.get("법령ID")
            out.append({
                "type": "law",
                "category": cat,
                "title": it.get("법령명한글"),
                "summary": "소관 %s · 시행 %s · 최종 공포 %s" % (
                    it.get("소관부처명") or "-",
                    fmt_date(it.get("시행일자")),
                    fmt_date(it.get("공포일자"))),
                "date": fmt_date(it.get("시행일자")),
                "sortKey": str(it.get("시행일자") or ""),
                "meta": {"lawId": law_id, "ministry": it.get("소관부처명")},
                "link": "https://www.law.go.kr/법령/%s" % urllib.parse.quote(name),
            })
        except Exception as e:
            print("  [err ] %s: %s" % (name, e), file=sys.stderr)
    return out


def collect_precedents(oc, per_query=4):
    out = []
    seen = set()
    for query, cat in PREC_QUERIES:
        try:
            d = fetch("lawSearch.do", {"OC": oc, "target": "prec", "type": "JSON",
                                       "query": query, "display": per_query})
            for it in as_list(d.get("PrecSearch", {}).get("prec")):
                case_no = (it.get("사건번호") or "").strip()
                if not case_no or case_no in seen:
                    continue
                seen.add(case_no)
                court = (it.get("법원명") or "").strip()
                out.append({
                    "type": "prec",
                    "category": cat,
                    "title": (it.get("사건명") or "").strip() or case_no,
                    "summary": " · ".join(x for x in [court, case_no, it.get("판결유형")] if x),
                    "date": fmt_date(str(it.get("선고일자") or "").replace(".", "")),
                    "sortKey": str(it.get("선고일자") or "").replace(".", ""),
                    "meta": {"caseNo": case_no, "court": court, "keyword": query},
                    "link": "https://www.law.go.kr/판례/(%s)" % urllib.parse.quote(case_no),
                })
        except Exception as e:
            print("  [err ] 판례 %s: %s" % (query, e), file=sys.stderr)
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--oc", default=os.getenv("LAW_GO_KR_OC"))
    ap.add_argument("--out", default=os.path.join(
        os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "data", "law-feed.json"))
    args = ap.parse_args()

    if not args.oc:
        print("LAW_GO_KR_OC 미설정 — open.law.go.kr 에서 발급받은 OC 코드가 필요합니다.", file=sys.stderr)
        return 1

    print("법령 조회…")
    laws = collect_laws(args.oc)
    print("판례 조회…")
    precs = collect_precedents(args.oc)

    items = laws + precs
    items.sort(key=lambda x: x.get("sortKey") or "", reverse=True)
    for i, it in enumerate(items):
        it["no"] = len(items) - i
        it.pop("sortKey", None)

    payload = {
        "generatedAt": datetime.now(KST).strftime("%Y-%m-%d %H:%M"),
        "source": "법제처 국가법령정보 공동활용 (law.go.kr)",
        "counts": {"law": len(laws), "prec": len(precs), "total": len(items)},
        "items": items,
    }

    os.makedirs(os.path.dirname(args.out), exist_ok=True)
    with open(args.out, "w", encoding="utf-8") as f:
        json.dump(payload, f, ensure_ascii=False, indent=2)

    print("완료 — 법령 %d건, 판례 %d건 → %s" % (len(laws), len(precs), args.out))
    return 0


if __name__ == "__main__":
    sys.exit(main())
