# -*- coding: utf-8 -*-
"""사진이 준비되기 전까지 쓸 아트워크를 생성한다.

출력:
  assets/img/acc/acc-01.jpg … acc-05.jpg   주요업무 아코디언 배경 (1600×900)
  assets/img/mag/report-01.jpg … -09.jpg   매거진 검토보고서 카드 썸네일 (800×500)

실행: python tools/make_artwork.py

실사가 아니라 브랜드 톤(네이비·골드)의 절차 생성 그래픽이다.
실제 촬영본이 준비되면 같은 파일명으로 덮어쓰면 된다.
시드가 고정돼 있어 몇 번을 실행해도 같은 그림이 나온다.
"""
import math
import os
import random
import sys

from PIL import Image, ImageDraw, ImageFilter

for _s in (sys.stdout, sys.stderr):
    try:
        _s.reconfigure(encoding="utf-8")
    except Exception:
        pass

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

NAVY_DEEP = (8, 15, 40)
GOLD = (176, 141, 79)


def gradient(w, h, top, bottom, diag=False):
    img = Image.new("RGB", (w, h), top)
    d = ImageDraw.Draw(img)
    for y in range(h):
        t = y / h
        c = tuple(int(top[i] + (bottom[i] - top[i]) * t) for i in range(3))
        d.line([(0, y), (w, y)], fill=c)
    if diag:
        img = img.rotate(6, resample=Image.BICUBIC, expand=False)
    return img


def add_glow(img, cx, cy, r, color, alpha=70):
    """부드러운 원형 광원."""
    glow = Image.new("RGBA", img.size, (0, 0, 0, 0))
    d = ImageDraw.Draw(glow)
    d.ellipse([cx - r, cy - r, cx + r, cy + r], fill=color + (alpha,))
    glow = glow.filter(ImageFilter.GaussianBlur(r // 3))
    img.paste(Image.alpha_composite(img.convert("RGBA"), glow).convert("RGB"), (0, 0))


def overlay(img):
    return ImageDraw.Draw(img, "RGBA")


# ---------- 아코디언 배경 5장 ----------

def art_civil(img):
    """01 민사·형사 — 법원 기둥의 수직 리듬."""
    w, h = img.size
    d = overlay(img)
    rnd = random.Random(11)
    add_glow(img, int(w * .78), int(h * .2), 300, (64, 96, 180), 60)
    n = 9
    cw = w // n
    for i in range(n):
        x = i * cw + cw // 4
        top = int(h * .18) + rnd.randint(-30, 30)
        light = 24 + int(20 * math.sin(i * 1.1))
        d.rectangle([x, top, x + cw // 2, h], fill=(light, light + 8, light + 34, 210))
        d.rectangle([x - 8, top - 26, x + cw // 2 + 8, top], fill=(light + 10, light + 18, light + 46, 230))
    d.line([(0, int(h * .82)), (w, int(h * .82))], fill=GOLD + (120,), width=2)


def art_corporate(img):
    """02 기업법무 — 커튼월 창의 격자."""
    w, h = img.size
    d = overlay(img)
    rnd = random.Random(22)
    add_glow(img, int(w * .2), int(h * .3), 340, (60, 92, 176), 55)
    for gx in range(0, w, 64):
        for gy in range(0, h, 42):
            if rnd.random() < .62:
                lum = rnd.randint(16, 46)
                on = rnd.random() < .12
                c = (200, 176, 120, 150) if on else (lum, lum + 8, lum + 30, 170)
                d.rectangle([gx + 6, gy + 5, gx + 58, gy + 37], fill=c)
    d.line([(int(w * .12), 0), (int(w * .32), h)], fill=GOLD + (70,), width=3)


def art_registry(img):
    """03 단체등기 — 아파트 단지 실루엣과 세대 창."""
    w, h = img.size
    d = overlay(img)
    rnd = random.Random(33)
    add_glow(img, int(w * .5), int(h * .12), 320, (72, 104, 190), 55)
    x = -30
    while x < w + 30:
        bw = rnd.randint(120, 210)
        bh = rnd.randint(int(h * .35), int(h * .78))
        top = h - bh
        d.rectangle([x, top, x + bw, h], fill=(14, 22, 52, 235))
        for wy in range(top + 24, h - 20, 34):
            for wx in range(x + 16, x + bw - 16, 30):
                if rnd.random() < .5:
                    on = rnd.random() < .2
                    c = (204, 178, 126, 190) if on else (52, 74, 132, 190)
                    d.rectangle([wx, wy, wx + 12, wy + 16], fill=c)
        x += bw + rnd.randint(26, 60)


def art_defect(img):
    """04 하자소송 — 도면 격자와 측정선."""
    w, h = img.size
    d = overlay(img)
    rnd = random.Random(44)
    add_glow(img, int(w * .82), int(h * .75), 300, (58, 88, 168), 50)
    for gx in range(0, w, 90):
        d.line([(gx, 0), (gx, h)], fill=(70, 96, 160, 40), width=1)
    for gy in range(0, h, 90):
        d.line([(0, gy), (w, gy)], fill=(70, 96, 160, 40), width=1)
    pts = [(int(w * .1), int(h * .8)), (int(w * .3), int(h * .45)),
           (int(w * .52), int(h * .62)), (int(w * .72), int(h * .3)),
           (int(w * .9), int(h * .5))]
    d.line(pts, fill=GOLD + (170,), width=3)
    for p in pts:
        d.ellipse([p[0] - 7, p[1] - 7, p[0] + 7, p[1] + 7], outline=GOLD + (220,), width=3)
    for i in range(14):
        x1 = rnd.randint(0, w); y1 = rnd.randint(0, h)
        d.line([(x1, y1), (x1 + rnd.randint(40, 130), y1)], fill=(120, 140, 190, 60), width=2)


def art_redevelop(img):
    """05 재건축·재개발 — 상승하는 블록과 크레인 붐."""
    w, h = img.size
    d = overlay(img)
    rnd = random.Random(55)
    add_glow(img, int(w * .25), int(h * .18), 320, (66, 98, 184), 55)
    bx = int(w * .58)
    for i in range(6):
        bw = 150 - i * 14
        bh = 66
        y2 = h - i * (bh + 12)
        lum = 22 + i * 7
        d.rectangle([bx, y2 - bh, bx + bw, y2], fill=(lum, lum + 9, lum + 36, 225))
    # 크레인
    mast_x = int(w * .3)
    d.rectangle([mast_x, int(h * .3), mast_x + 12, h], fill=(30, 40, 76, 235))
    d.line([(mast_x - 260, int(h * .3)), (mast_x + 420, int(h * .24))], fill=(38, 50, 92, 255), width=8)
    d.line([(mast_x + 416, int(h * .24)), (mast_x + 416, int(h * .52))], fill=GOLD + (200,), width=3)
    d.rectangle([mast_x + 398, int(h * .52), mast_x + 434, int(h * .56)], fill=GOLD + (220,))


ACC = [
    ("acc-01.jpg", (27, 43, 87), (18, 28, 60), art_civil),
    ("acc-02.jpg", (19, 29, 60), (12, 19, 42), art_corporate),
    ("acc-03.jpg", (29, 42, 77), (15, 23, 50), art_registry),
    ("acc-04.jpg", (22, 32, 63), (13, 20, 44), art_defect),
    ("acc-05.jpg", (26, 37, 71), (16, 24, 52), art_redevelop),
]


# ---------- 매거진 카드 썸네일 9장 ----------

def report_thumb(idx, top, bottom):
    w, h = 800, 500
    img = gradient(w, h, top, bottom)
    d = overlay(img)
    rnd = random.Random(100 + idx)
    add_glow(img, rnd.randint(int(w * .2), int(w * .8)), rnd.randint(80, 240),
             240, (70, 100, 185), 60)
    motif = idx % 3
    if motif == 0:      # 등고선 원호
        cx, cy = rnd.randint(100, 700), rnd.randint(320, 520)
        for r in range(60, 480, 46):
            d.ellipse([cx - r, cy - r, cx + r, cy + r], outline=(96, 122, 188, 60), width=2)
        d.ellipse([cx - 152, cy - 152, cx + 152, cy + 152], outline=GOLD + (150,), width=2)
    elif motif == 1:    # 사선 결
        for i in range(-6, 22):
            x = i * 60
            a = 34 if i % 3 else 90
            d.line([(x, h), (x + 260, 0)], fill=(92, 118, 184, a), width=2)
        d.line([(rnd.randint(100, 380), h), (rnd.randint(360, 660), 0)], fill=GOLD + (140,), width=2)
    else:               # 블록 리듬
        for i in range(10):
            bw = rnd.randint(40, 130); bh = rnd.randint(60, 220)
            x = rnd.randint(0, w - bw); y2 = h - rnd.randint(0, 60)
            d.rectangle([x, y2 - bh, x + bw, y2], fill=(24, 34, 68, 120))
        d.line([(0, h - 70), (w, h - 70)], fill=GOLD + (110,), width=2)
    # 네 귀 프레임
    m = 26; L = 46
    for cx, cy, dx, dy in [(m, m, 1, 1), (w - m, m, -1, 1), (m, h - m, 1, -1), (w - m, h - m, -1, -1)]:
        d.line([(cx, cy), (cx + dx * L, cy)], fill=(210, 190, 150, 150), width=2)
        d.line([(cx, cy), (cx, cy + dy * L)], fill=(210, 190, 150, 150), width=2)
    return img


REPORT_TONES = [
    ((29, 42, 77), (66, 99, 159)),
    ((27, 43, 87), (75, 107, 171)),
    ((22, 32, 63), (60, 84, 136)),
    ((19, 29, 60), (49, 69, 111)),
    ((26, 37, 71), (72, 95, 146)),
    ((24, 34, 64), (58, 85, 144)),
    ((20, 29, 58), (51, 73, 122)),
    ((27, 37, 71), (70, 96, 155)),
    ((42, 47, 77), (90, 106, 156)),
]


def main():
    acc_dir = os.path.join(BASE, "assets", "img", "acc")
    mag_dir = os.path.join(BASE, "assets", "img", "mag")
    os.makedirs(acc_dir, exist_ok=True)
    os.makedirs(mag_dir, exist_ok=True)

    for name, top, bottom, fn in ACC:
        img = gradient(1600, 900, top, bottom)
        fn(img)
        img = img.filter(ImageFilter.GaussianBlur(0.4))
        path = os.path.join(acc_dir, name)
        img.save(path, "JPEG", quality=86, optimize=True)
        print("생성 —", os.path.relpath(path, BASE), "(%d KB)" % (os.path.getsize(path) // 1024))

    for i, (top, bottom) in enumerate(REPORT_TONES, 1):
        img = report_thumb(i, top, bottom)
        path = os.path.join(mag_dir, "report-%02d.jpg" % i)
        img.save(path, "JPEG", quality=85, optimize=True)
        print("생성 —", os.path.relpath(path, BASE), "(%d KB)" % (os.path.getsize(path) // 1024))

    print("완료. index.html 아코디언과 data/magazine.json 의 thumb 경로가 이 파일들을 참조합니다.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
