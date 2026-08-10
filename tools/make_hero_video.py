# -*- coding: utf-8 -*-
"""메인 배경 영상을 직접 그려 만든다.

외부에서 받은 영상은 상업적 이용 범위와 초상권을 따져야 해서 그대로 올릴 수 없다.
그래서 저작권 문제가 없는 자체 제작본을 만들어 자리를 채운다.
드론 촬영본이나 생성 영상이 준비되면 같은 이름으로 덮어쓰면 된다.

    python tools/make_hero_video.py office
    python tools/make_hero_video.py consult
    python tools/make_hero_video.py all

만드는 장면
  office  — 법무법인 사무실. 세로 기둥 사이로 빛이 천천히 지나간다
  consult — 상담 장면. 마주 앉은 두 사람의 실루엣 위로 빛이 번진다

두 장면 모두 화면 왼쪽 1/3 은 비워 둔다. 그 자리에 문구가 올라간다.
"""
import math
import os
import shutil
import subprocess
import sys
import tempfile

from PIL import Image, ImageDraw, ImageFilter

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(BASE, 'assets', 'video')

W, H = 1920, 1080
FPS = 25
SECONDS = 12
FRAMES = FPS * SECONDS

NAVY = (10, 24, 66)
DEEP = (5, 12, 36)
GOLD = (176, 141, 79)


def lerp(a, b, t):
    return tuple(int(round(a[i] + (b[i] - a[i]) * t)) for i in range(3))


def backdrop(size, top, bottom):
    """위아래로 색이 바뀌는 바탕."""
    w, h = size
    img = Image.new('RGB', (1, h))
    px = img.load()
    for y in range(h):
        px[0, y] = lerp(top, bottom, y / (h - 1))
    return img.resize((w, h))


def frame_office(i):
    """세로 기둥과 창. 빛이 오른쪽으로 천천히 흐른다."""
    t = i / FRAMES                      # 0 → 1, 끝과 처음이 이어지도록 씀
    img = backdrop((W, H), DEEP, NAVY)
    d = ImageDraw.Draw(img, 'RGBA')

    cols = 13
    span = W / cols
    for c in range(cols):
        x = c * span
        depth = 0.35 + 0.65 * abs(math.sin(c * 1.7))     # 기둥마다 다른 밝기
        # 기둥 사이 창
        d.rectangle([x + span * .18, H * .10, x + span * .82, H * .93],
                    fill=(255, 255, 255, int(10 + 16 * depth)))
        # 창을 가로지르는 층 선
        for f in range(4):
            y = H * (.20 + f * .19)
            d.line([(x + span * .18, y), (x + span * .82, y)],
                   fill=(255, 255, 255, 16), width=2)
        # 기둥
        d.rectangle([x, 0, x + span * .18, H], fill=(4, 10, 30, 210))

        # 흐르는 빛 — 기둥 위치에 따라 시차를 준다
        phase = (t + c / cols) % 1.0
        glow = max(0.0, math.sin(phase * math.pi * 2)) ** 3
        if glow > 0.02:
            d.rectangle([x + span * .18, H * .10, x + span * .82, H * .93],
                        fill=GOLD + (int(46 * glow),))

    img = img.filter(ImageFilter.GaussianBlur(1.4))
    d = ImageDraw.Draw(img, 'RGBA')
    d.rectangle([0, 0, W, H * .16], fill=(4, 10, 30, 120))       # 위쪽을 눌러 헤더와 붙인다
    d.rectangle([0, H * .84, W, H], fill=(4, 10, 30, 120))
    return img


def frame_consult(i):
    """책상을 사이에 두고 마주 앉은 두 사람. 뒤에서 빛이 번진다."""
    t = i / FRAMES
    img = backdrop((W, H), (12, 28, 72), (4, 10, 30))
    d = ImageDraw.Draw(img, 'RGBA')

    # 뒤쪽 빛무리 — 좌우로 아주 천천히 움직인다
    cx = W * (.62 + .05 * math.sin(t * math.pi * 2))
    for r in range(520, 0, -26):
        a = int(30 * (1 - r / 520) ** 2)
        d.ellipse([cx - r, H * .42 - r, cx + r, H * .42 + r], fill=GOLD + (a,))

    # 창틀
    for c in range(5):
        x = W * .40 + c * W * .13
        d.line([(x, 0), (x, H * .70)], fill=(255, 255, 255, 20), width=3)

    def person(px, py, scale, flip):
        """머리와 어깨만 있는 실루엣."""
        hr = 62 * scale
        d.ellipse([px - hr, py - hr * 2.5, px + hr, py - hr * .5], fill=(3, 8, 24, 245))
        w = 175 * scale
        d.pieslice([px - w, py - hr * .7, px + w, py + hr * 3.2], 180, 360,
                   fill=(3, 8, 24, 245))
        # 팔 — 책상 쪽으로
        ax = px + (w * .72 if flip else -w * .72)
        d.ellipse([ax - 30 * scale, py + hr * .6, ax + 30 * scale, py + hr * 2.4],
                  fill=(3, 8, 24, 245))

    breathe = math.sin(t * math.pi * 2) * 4          # 아주 미세한 움직임
    person(W * .52, H * .70 + breathe, 1.0, False)   # 의뢰인
    person(W * .80, H * .68 - breathe, 1.08, True)   # 변호사

    d.rectangle([W * .38, H * .78, W, H], fill=(3, 8, 24, 250))   # 책상
    d.line([(W * .38, H * .78), (W, H * .78)], fill=GOLD + (60,), width=3)

    img = img.filter(ImageFilter.GaussianBlur(2.2))
    d = ImageDraw.Draw(img, 'RGBA')
    d.rectangle([0, 0, W, H * .14], fill=(4, 10, 30, 130))
    return img


SCENES = {'office': frame_office, 'consult': frame_consult}


def build(name):
    draw = SCENES[name]
    tmp = tempfile.mkdtemp(prefix='jlhero-')
    try:
        for i in range(FRAMES):
            draw(i).save(os.path.join(tmp, '%04d.png' % i))
            if i % 50 == 0:
                print('  %d/%d' % (i, FRAMES))
        dst = os.path.join(OUT, 'hero-%s.mp4' % name)
        subprocess.check_call([
            'ffmpeg', '-y', '-loglevel', 'error',
            '-framerate', str(FPS), '-i', os.path.join(tmp, '%04d.png'),
            '-c:v', 'libx264', '-preset', 'slow', '-crf', '30',
            '-pix_fmt', 'yuv420p', '-movflags', '+faststart', '-an', dst,
        ])
        print('%s — %.1fMB' % (dst, os.path.getsize(dst) / 1048576))
    finally:
        shutil.rmtree(tmp, ignore_errors=True)


def main():
    what = sys.argv[1] if len(sys.argv) > 1 else 'all'
    names = list(SCENES) if what == 'all' else [what]
    for n in names:
        if n not in SCENES:
            print('없는 장면:', n)
            return 1
        print('만드는 중 —', n)
        build(n)
    return 0


if __name__ == '__main__':
    sys.exit(main())
