# -*- coding: utf-8 -*-
"""구성원 단체컷 — 실물 사진만 사용. 얼굴을 새로 만들지 않는다.

핵심 두 가지
 1) 원본이 380px 밖에 안 되므로 크게 확대하지 않는다. 확대하면 바로 뭉갠다.
 2) 사진마다 인물이 프레임에서 차지하는 비율이 달라, 화면 높이로 맞추면
    얼굴 크기가 제각각이 된다. 피부톤으로 머리 폭을 재서 그걸 기준으로 맞춘다.
"""
import os
import numpy as np
from PIL import Image, ImageFilter, ImageDraw
from scipy.ndimage import label

SRC = r'D:\DDownloads\jl-lawfirm-homepage\assets\img\lawyers'
OUT = r'D:\DDownloads\jl_group'
ORDER = ['lee-ji-hun', 'ha-hye-yong', 'park-jong-il', 'lim-jun-kyu', 'jang-woo-jin']
CENTER = 2          # 앞으로 조금 나오게 할 사람(대표) 위치

W, H = 1400, 470
HEAD_W = 92          # 모든 인물의 머리 폭을 이 값으로 맞춘다
LEAD_K = 1.30        # 대표변호사만 이만큼 더 크게 — 가운데에서 가장 크게 선다
NAVY_TOP, NAVY_BOT = (17, 36, 82), (8, 17, 45)
BG = 'navy'          # 'navy' | 'white'


def cutout(path):
    im = Image.open(path).convert('RGB')
    a = np.asarray(im).astype(np.float32)
    lum = a.mean(axis=2)
    alpha = np.clip((251.0 - lum) / 16.0, 0.0, 1.0)

    bg = lum > 244
    lab, _ = label(bg)
    ids = set(lab[0, :]) | set(lab[-1, :]) | set(lab[:, 0]) | set(lab[:, -1])
    ids.discard(0)
    outside = np.isin(lab, list(ids)) if ids else np.zeros_like(bg)
    alpha = np.where(outside, alpha, 1.0)

    rgb = a[..., :3]
    # 배경이 흰색이라 반투명 픽셀에는 흰빛이 섞여 있다. 그만큼 빼 준다.
    al = alpha[..., None]
    rgb = np.where((al > 0.02) & (al < 0.98),
                   np.clip((rgb - 255.0 * (1 - al)) / np.maximum(al, 0.15), 0, 255),
                   rgb)

    out = Image.fromarray(np.dstack([rgb.astype(np.uint8),
                                     (alpha * 255).astype(np.uint8)]), 'RGBA')
    out.putalpha(out.getchannel('A').filter(ImageFilter.GaussianBlur(0.6)))
    return out


def head_width(im):
    """피부톤 픽셀로 머리 폭을 잰다 — 얼굴 크기를 맞추기 위한 기준."""
    a = np.asarray(im).astype(np.float32)
    r, g, b, al = a[..., 0], a[..., 1], a[..., 2], a[..., 3]
    skin = ((r > 95) & (g > 55) & (b > 40) & (r > g) & (g > b) &
            ((r - b) > 15) & (al > 200))
    top = skin[: int(skin.shape[0] * 0.55)]        # 상반부(얼굴)만
    if top.sum() < 80:
        return im.width * 0.42
    cols = np.where(top.any(axis=0))[0]
    return max(8.0, float(cols[-1] - cols[0]))


def backdrop(w, h):
    if BG == 'white':
        # 원본 배경이 흰색이라 흰 바탕이 가장 깨끗하게 떨어진다.
        # 인물이 허공에 뜨지 않도록 바닥에 옅은 그림자만 깐다.
        bg = Image.new('RGB', (w, h), (255, 255, 255))
        sh = Image.new('L', (w, h), 0)
        ImageDraw.Draw(sh).ellipse(
            [w * 0.06, h * 0.93, w * 0.94, h * 1.06], fill=38)
        sh = sh.filter(ImageFilter.GaussianBlur(h // 26))
        return Image.composite(Image.new('RGB', (w, h), (208, 212, 220)), bg, sh)
    col = Image.new('RGB', (1, h))
    d = ImageDraw.Draw(col)
    for y in range(h):
        t = y / max(1, h - 1)
        d.point((0, y), tuple(int(NAVY_TOP[i] + (NAVY_BOT[i] - NAVY_TOP[i]) * t)
                              for i in range(3)))
    bg = col.resize((w, h))
    glow = Image.new('L', (w, h), 0)
    ImageDraw.Draw(glow).ellipse([w * 0.26, -h * 0.5, w * 0.74, h * 0.8], fill=64)
    glow = glow.filter(ImageFilter.GaussianBlur(w // 10))
    return Image.composite(Image.new('RGB', (w, h), (31, 57, 112)), bg, glow)


def build():
    n = len(ORDER)
    cuts = []
    for i, slug in enumerate(ORDER):
        im = cutout(os.path.join(SRC, slug + '.jpg'))
        bb = im.getchannel('A').point(lambda v: 255 if v > 8 else 0).getbbox()
        im = im.crop(bb)
        # 원본 맨 아랫줄에 JPEG 압축 찌꺼기가 한 줄 남는다. 가슴 아래라 잘라도 안 보인다.
        im = im.crop((0, 0, im.width, im.height - max(3, int(im.height * 0.025))))
        mid = (i == CENTER)
        target = HEAD_W * (LEAD_K if mid else 1.0)
        k = target / head_width(im)
        k = min(k, 1.34)
        im = im.resize((max(1, int(im.width * k)), max(1, int(im.height * k))),
                       Image.LANCZOS)
        cuts.append(im)

    # 캔버스를 인물 크기에 맞춘다 — 위가 휑하지 않게
    tall = max(c.height for c in cuts)
    h = int(tall * 1.07)
    ov = int(sum(c.width for c in cuts) / n * 0.16)      # 서로 겹치는 폭
    content = sum(c.width for c in cuts) - ov * (n - 1)
    side = int(content * 0.05)

    # 대표의 가로 중심이 캔버스 정중앙에 오도록 좌우 여백을 따로 준다.
    # 인원이 짝수면 그냥 늘어놓았을 때 가운데가 비어 대표가 옆으로 밀린다.
    lead_off = sum(c.width - ov for c in cuts[:CENTER]) + cuts[CENTER].width / 2.0
    pad_l = side + max(0.0, content / 2.0 - lead_off)
    pad_r = side + max(0.0, lead_off - content / 2.0)
    w = int(content + pad_l + pad_r)

    canvas = backdrop(w, h).convert('RGBA')

    pos, cx = [], int(pad_l)
    for c in cuts:
        pos.append(cx)
        cx += c.width - ov

    order = sorted(range(n), key=lambda i: abs(i - CENTER), reverse=True)
    for i in order:
        c = cuts[i]
        # 대표는 한 뼘 앞으로 — 발치를 조금 내려 화면을 뚫고 나오게 한다
        dy = int(h * 0.012) if i == CENTER else 0
        canvas.alpha_composite(c, (pos[i], h - c.height + dy))

    return canvas.convert('RGB')


if __name__ == '__main__':
    import sys
    argv = sys.argv[1:]
    if 'white' in argv:
        BG = 'white'
        argv.remove('white')
    suffix = '_white' if BG == 'white' else ''
    if argv and argv[0] == '6':
        ORDER = ['jang-woo-jin', 'lee-ji-hun', 'ha-hye-yong',
                 'park-jong-il', 'lim-jun-kyu', 'oh-hyun-jin']
        CENTER = 3
        name = 'group6%s.jpg' % suffix
    else:
        name = 'group5%s.jpg' % suffix
    img = build()
    img.save(os.path.join(OUT, name), quality=92)
    print('저장', name, img.size)
