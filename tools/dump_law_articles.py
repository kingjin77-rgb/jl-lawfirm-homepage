# -*- coding: utf-8 -*-
"""본문 인용 검증용 — 법령 원문(현행 조문)을 법제처 Open API로 받아 저장한다.

홈페이지 본문에 직접 쓴 "도시정비법 제38조" 같은 인용이
실제 조문 제목·내용과 맞는지 대조하는 데 쓴다. 사이트에는 싣지 않는다.

출력: law_articles.json  {법령명: {"id":…, "시행":…, "articles": {"38": {"title":…, "text":…}, "86-2": …}}}
실행: LAW_GO_KR_OC=… python tools/dump_law_articles.py [법령명 …]
"""
import json
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from fetch_law_feed import fetch, find_exact_law, as_list  # noqa: E402

LAWS = [
    "도시 및 주거환경정비법",
    "행정소송법",
    "개인정보 보호법",
    "변호사법",
    "부동산등기법",
    "지방세법",
    "지방세특례제한법",
    "인지세법",
    "농어촌특별세법",
    "주택도시기금법",
    "주택도시기금법 시행령",
    "민법",
    "공동주택관리법",
    "공동주택관리법 시행령",
    "집합건물의 소유 및 관리에 관한 법률",
    "주택법",
    "민간임대주택에 관한 특별법",
    "공공주택 특별법",
]


def flat(v):
    """조문내용·항·호가 문자열/리스트/딕트로 섞여 온다. 전부 이어 붙인다."""
    if v is None:
        return ""
    if isinstance(v, str):
        return v.strip()
    if isinstance(v, list):
        return "\n".join(x for x in (flat(i) for i in v) if x)
    if isinstance(v, dict):
        parts = []
        for k in ("조문내용", "항내용", "호내용", "목내용", "항", "호", "목"):
            if k in v:
                parts.append(flat(v[k]))
        return "\n".join(p for p in parts if p)
    return str(v)


def dump(oc, name):
    it = find_exact_law(oc, name)
    if not it:
        return {"error": "정확 일치 없음"}
    d = fetch("lawService.do", {"OC": oc, "target": "law", "type": "JSON", "ID": it["법령ID"]})
    law = d.get("법령", {})
    arts = {}
    for u in as_list((law.get("조문") or {}).get("조문단위")):
        if u.get("조문여부") != "조문":
            continue
        no = str(u.get("조문번호") or "").strip()
        br = str(u.get("조문가지번호") or "").strip()
        key = no + ("-" + br if br and br != "0" else "")
        arts[key] = {"title": (u.get("조문제목") or "").strip(), "text": flat(u)}
    info = law.get("기본정보", {})
    return {"id": it["법령ID"], "시행": info.get("시행일자"), "공포": info.get("공포일자"),
            "articles": arts}


def main():
    oc = os.getenv("LAW_GO_KR_OC")
    if not oc:
        print("LAW_GO_KR_OC 미설정", file=sys.stderr)
        return 1
    names = sys.argv[1:] or LAWS
    out = {}
    for n in names:
        try:
            out[n] = dump(oc, n)
            print("%-30s 조문 %d" % (n, len(out[n].get("articles", {}))))
        except Exception as e:
            out[n] = {"error": str(e)}
            print("%-30s 오류 %s" % (n, e), file=sys.stderr)
    with open("law_articles.json", "w", encoding="utf-8") as f:
        json.dump(out, f, ensure_ascii=False, indent=1)
    return 0


if __name__ == "__main__":
    sys.exit(main())
