# -*- coding: utf-8 -*-
"""공유용 대표 이미지(OG 이미지)를 만든다.

출력: assets/img/og-default.jpg (1200×630)
실행: python tools/make_og.py

카카오톡·페이스북 등은 SVG를 읽지 못하므로 래스터 이미지가 필요하다.
브랜드 색과 서체 크기만 아래 상수에서 바꾸면 된다.
"""
import os
import sys

from PIL import Image, ImageDraw, ImageFont

# Windows 콘솔(cp949)에서도 한글 로그가 깨지지 않도록
for _s in (sys.stdout, sys.stderr):
    try:
        _s.reconfigure(encoding="utf-8")
    except Exception:
        pass

W, H = 1200, 630
NAVY = (13, 33, 98)
NAVY_DEEP = (8, 21, 65)
GOLD = (176, 141, 79)
WHITE = (255, 255, 255)

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(BASE, "assets", "img", "og-default.jpg")

# 한글이 나오는 서체를 순서대로 찾는다.
FONT_CANDIDATES = [
    r"C:\Windows\Fonts\malgunbd.ttf",
    r"C:\Windows\Fonts\malgun.ttf",
    "/usr/share/fonts/truetype/nanum/NanumGothicBold.ttf",
    "/usr/share/fonts/opentype/noto/NotoSansCJK-Bold.ttc",
]
FONT_LIGHT_CANDIDATES = [
    r"C:\Windows\Fonts\malgun.ttf",
    "/usr/share/fonts/truetype/nanum/NanumGothic.ttf",
    "/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc",
]


def pick(paths):
    for p in paths:
        if os.path.exists(p):
            return p
    return None


def font(paths, size):
    p = pick(paths)
    if not p:
        return ImageFont.load_default()
    return ImageFont.truetype(p, size)


def main():
    bold = pick(FONT_CANDIDATES)
    if not bold:
        print("한글 서체를 찾지 못했습니다. FONT_CANDIDATES 에 경로를 추가하세요.")
        return 1

    img = Image.new("RGB", (W, H), NAVY_DEEP)
    d = ImageDraw.Draw(img)

    # 배경 — 위에서 아래로 네이비 그라디언트
    for y in range(H):
        t = y / H
        d.line(
            [(0, y), (W, y)],
            fill=(
                int(NAVY_DEEP[0] + (NAVY[0] - NAVY_DEEP[0]) * t),
                int(NAVY_DEEP[1] + (NAVY[1] - NAVY_DEEP[1]) * t),
                int(NAVY_DEEP[2] + (NAVY[2] - NAVY_DEEP[2]) * t),
            ),
        )

    # 하단 스카이라인 — 사이트 히어로와 같은 인상을 준다
    import random

    random.seed(20200101)  # 실행할 때마다 같은 그림이 나오도록 고정
    x = -40
    while x < W + 40:
        bw = random.randint(46, 104)
        bh = random.randint(70, 240)
        top = H - bh
        d.rectangle([x, top, x + bw, H], fill=(6, 16, 52))
        for wy in range(top + 16, H - 14, 26):
            for wx in range(x + 12, x + bw - 12, 22):
                if random.random() < 0.34:
                    d.rectangle([wx, wy, wx + 7, wy + 11], fill=(38, 64, 140))
        x += bw + random.randint(6, 18)

    # 골드 러그
    d.rectangle([90, 128, 90 + 78, 128 + 5], fill=GOLD)

    f_en = font(FONT_LIGHT_CANDIDATES, 27)
    f_ko = font(FONT_CANDIDATES, 82)
    f_lead = font(FONT_LIGHT_CANDIDATES, 33)
    f_tel = font(FONT_LIGHT_CANDIDATES, 26)

    d.text((90, 168), "JL LAW FIRM SINCE 2020", font=f_en, fill=GOLD)
    d.text((90, 224), "법무법인 제이엘", font=f_ko, fill=WHITE)
    d.text((90, 344), "늘 고객의 입장에서, 신뢰와 진리의 길을.", font=f_lead, fill=(206, 214, 236))
    d.text(
        (90, 408),
        "민사·부동산  |  형사  |  기업법무  |  하자소송  |  단체등기",
        font=f_tel,
        fill=(150, 165, 205),
    )

    os.makedirs(os.path.dirname(OUT), exist_ok=True)
    img.save(OUT, "JPEG", quality=88, optimize=True)
    print("생성 완료 —", OUT, "(%d bytes)" % os.path.getsize(OUT))
    return 0


if __name__ == "__main__":
    sys.exit(main())
