"""링크·파일·구조화 데이터를 점검한다. 문제가 있으면 0이 아닌 값으로 끝난다."""
from __future__ import annotations
import io, json, re, sys
from pathlib import Path

for _s in ("stdout", "stderr"):
    _st = getattr(sys, _s)
    if hasattr(_st, "buffer"):
        setattr(sys, _s, io.TextIOWrapper(_st.buffer, encoding="utf-8", errors="replace"))

ROOT = Path(__file__).resolve().parent.parent
problems: list[str] = []

pages = sorted(q for q in ROOT.rglob("*.html") if ".git" not in q.parts)
ids = {p.name: set(re.findall(r'id="([^"]+)"', p.read_text(encoding="utf-8"))) for p in pages}

for p in pages:
    html = p.read_text(encoding="utf-8")

    # 내부 링크와 파일 참조
    for m in re.finditer(r'(?:href|src)="(?!https?:|mailto:|tel:|data:|//|#)([^"]+)"', html):
        raw = m.group(1)
        path, _, frag = raw.partition("#")
        path = path.split("?")[0]
        # "/" 로 시작하면 사이트 뿌리 기준이다
        base = ROOT if path.startswith("/") else p.parent
        if path and not (base / path.lstrip("/")).resolve().exists():
            problems.append(f"{p.name}: 파일 없음 — {path}")
        if frag:
            target = (path.split("/")[-1] or p.name) if path else p.name
            if target in ids and frag not in ids[target]:
                problems.append(f"{p.name}: 앵커 없음 — {target}#{frag}")

    # 구조화 데이터
    for m in re.finditer(r'<script type="application/ld\+json">(.*?)</script>', html, re.S):
        try:
            json.loads(m.group(1))
        except json.JSONDecodeError as e:
            problems.append(f"{p.name}: 구조화 데이터 문법 오류 — {e}")

    # 동작하지 않는 버튼을 두지 않는다
    if re.search(r'href="#"(?![^>]*data-)', html):
        problems.append(f"{p.name}: 빈 링크(href=\"#\")가 있습니다")

for line in problems:
    print("문제:", line)
print(f"점검한 페이지 {len(pages)}개 · 문제 {len(problems)}건")
sys.exit(1 if problems else 0)
