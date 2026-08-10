"""제1종 국민주택채권 즉시매도 할인율을 받아와 data/registry.json 을 갱신한다.

등기비용 계산기가 쓰는 값 가운데 채권 할인율만 매일 바뀐다.
나머지(세율·수수료)는 법이 바뀔 때만 움직이므로 수기로 둔다.

출처는 주택도시기금이 채권 시세 화면에 걸어 둔 우리은행 조회 서비스다.
주택도시기금 사이트 자체는 이 화면을 iframe 으로 불러오기만 하므로,
그 안쪽 주소를 직접 조회한다.

  기준일        매도단가   수익률   할인율
  2026.08.10    8,541     4.186   14.79755

받아온 값이 이상하면(범위 밖 · 전일 대비 급변) 갱신하지 않고 종료한다.
잘못된 값이 계산기에 들어가면 고객에게 틀린 금액을 안내하게 된다.
"""

from __future__ import annotations

import io
import json
import re
import sys
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timedelta, timezone
from pathlib import Path

# 윈도우 콘솔은 기본이 cp949 라 한글 로그에서 깨진다
for _s in ("stdout", "stderr"):
    _stream = getattr(sys, _s)
    if hasattr(_stream, "buffer"):
        setattr(sys, _s, io.TextIOWrapper(_stream.buffer, encoding="utf-8", errors="replace"))

ROOT = Path(__file__).resolve().parent.parent
TARGET = ROOT / "data" / "registry.json"

KST = timezone(timedelta(hours=9))
UA = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/120.0 Safari/537.36"
)
ENDPOINT = "https://svc.wooribank.com/svc/Dream?withyou=HBNHB0087"

# 할인율이 이 범위를 벗어나면 받아온 값을 의심한다.
MIN_RATE, MAX_RATE = 1.0, 40.0
# 하루 사이 이만큼 넘게 움직이면 사람이 확인해야 한다.
MAX_JUMP = 3.0

# "2026.08.10 8,541 4.186 14.79755" 한 줄을 뽑는다
ROW = re.compile(
    r"(\d{4})\.(\d{2})\.(\d{2})\s+"      # 기준일
    r"([\d,]+)\s+"                        # 매도단가
    r"(\d+\.\d+)\s+"                      # 수익률
    r"(\d+\.\d+)"                         # 할인율
)


def fetch_month(year: int, month: int) -> str:
    body = urllib.parse.urlencode(
        {
            "MODE": "1",
            "BSDT_YM": f"{year}{month:02d}",
            "STD_YEAR": str(year),
            "STD_MONTH": f"{month:02d}",
        }
    ).encode()
    req = urllib.request.Request(
        ENDPOINT,
        data=body,
        headers={
            "User-Agent": UA,
            "Content-Type": "application/x-www-form-urlencoded",
            "Referer": ENDPOINT,
        },
    )
    with urllib.request.urlopen(req, timeout=25) as res:
        raw = res.read()
    for enc in ("utf-8", "euc-kr", "cp949"):
        try:
            return raw.decode(enc)
        except UnicodeDecodeError:
            continue
    return raw.decode("utf-8", "replace")


def latest_row(html: str) -> tuple[str, float, float, float] | None:
    """가장 마지막(최신) 영업일 행을 돌려준다."""
    text = re.sub(r"<script.*?</script>", " ", html, flags=re.S)
    text = re.sub(r"<[^>]+>", " ", text)
    text = re.sub(r"\s+", " ", text)

    rows = []
    for m in ROW.finditer(text):
        y, mo, d, price, yield_, discount = m.groups()
        rows.append(
            (
                f"{y}-{mo}-{d}",
                float(price.replace(",", "")),
                float(yield_),
                float(discount),
            )
        )
    if not rows:
        return None
    rows.sort(key=lambda r: r[0])
    return rows[-1]


def collect() -> tuple[str, float, float, float] | None:
    """이번 달을 먼저 보고, 아직 게시가 없으면 지난달을 본다."""
    now = datetime.now(KST)
    tries = [(now.year, now.month)]
    prev = now.replace(day=1) - timedelta(days=1)
    tries.append((prev.year, prev.month))

    for year, month in tries:
        try:
            html = fetch_month(year, month)
        except (urllib.error.URLError, OSError, TimeoutError) as e:
            print(f"  조회 실패 — {year}-{month:02d}: {e}", file=sys.stderr)
            continue
        row = latest_row(html)
        if row:
            print(f"  찾음 — 기준일 {row[0]} · 매도단가 {row[1]:,.0f} · 할인율 {row[3]}%")
            return row
        print(f"  자료 없음 — {year}-{month:02d}", file=sys.stderr)
    return None


def main() -> int:
    data = json.loads(TARGET.read_text(encoding="utf-8"))
    bond = data.get("bond")
    if bond is None:
        print("registry.json 에 bond 항목이 없습니다.", file=sys.stderr)
        return 1

    print("채권 할인율 수집 시작")
    row = collect()
    if row is None:
        print("값을 얻지 못했습니다. 기존 값을 유지합니다.", file=sys.stderr)
        return 0

    basis_date, price, yield_, rate = row
    prev = float(bond.get("rate", 0) or 0)

    if not (MIN_RATE <= rate <= MAX_RATE):
        print(f"범위 밖 값({rate}%). 갱신하지 않습니다.", file=sys.stderr)
        return 0
    if prev and abs(rate - prev) > MAX_JUMP:
        print(
            f"이전 {prev}% 에서 {rate}% 로 {abs(rate - prev):.2f}%p 움직였습니다.\n"
            "사람이 확인해야 하므로 갱신하지 않습니다. "
            "값이 맞다면 registry.json 의 rate 를 손으로 한 번 맞춰 주세요.",
            file=sys.stderr,
        )
        return 0

    if abs(rate - prev) < 0.0001 and bond.get("rateDate") == basis_date:
        print("변경 없음")
        return 0

    bond["rate"] = round(rate, 3)
    bond["rateDate"] = basis_date
    bond["rateSellPrice"] = round(price)
    bond["rateYield"] = yield_
    bond["rateSource"] = "주택도시기금 · 우리은행 제1종 국민주택채권 즉시매도 기준 (자동 수집)"
    data["updatedAt"] = datetime.now(KST).strftime("%Y-%m-%d")

    TARGET.write_text(
        json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    print(f"갱신 — {prev}% → {rate}% (기준일 {basis_date})")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
