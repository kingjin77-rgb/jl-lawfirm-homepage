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
ORDER = ['jang-woo-jin', 'ha-hye-yong', 'park-jong-il', 'lim-jun-kyu', 'oh-hyun-jin']
CENTER = 2          # 앞으로 조금 나오게 할 사람(대표) 위치

W, H = 1400, 470
HEAD_W = 92          # 모든 인물의 머리 폭을 이 값으로 맞춘다

# 머리 꼭대기를 놓을 y — 가운데가 가장 높고 바깥으로 갈수록 내려가는 피라미드.
# 바닥에 맞추면 원본 크롭 여유가 그대로 머리 높이 차이로 튀어 지그재그가 된다.
HEAD_TOP = {0: 27, 1: 47, 2: 72, 3: 92}

# 피부톤 폭 측정이 사람마다 조금씩 빗나간다. 렌더 결과를 재서 되먹인 보정값.
FACE_FIX = {'jang-woo-jin': 1.17}

OV_FAR = 0.09        # 일반 인접쌍 겹침 — 어깨에 걸린다
OV_LEAD = 0.13       # 대표 좌우만 조금 더 — 대신 손·팔뚝은 피한다
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
        # 가슴에서 잘린 흉상이라 발밑 그림자는 넣지 않는다. 아래로 흘려보내 자른다.
        return Image.new('RGB', (w, h), (255, 255, 255))
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
        k = target / head_width(im) * FACE_FIX.get(slug, 1.0)
        k = min(k, 1.34)
        im = im.resize((max(1, int(im.width * k)), max(1, int(im.height * k))),
                       Image.LANCZOS)
        cuts.append(im)

    # 머리 꼭대기를 피라미드 선에 얹는다 (bbox 크롭이라 이미지 y=0 이 곧 머리 꼭대기)
    tops = [HEAD_TOP[min(abs(i - CENTER), max(HEAD_TOP))] for i in range(n)]

    # 캔버스 아래로 전원이 흘러넘치게 자른다.
    # 밑단을 맞추지 않으면 들쭉날쭉한 가슴 절단선도, 떠 있는 느낌도 함께 사라진다.
    h = min(tops[i] + cuts[i].height for i in range(n))

    # 겹침은 어깨에 떨어뜨린다. 손이 잘리면 바로 합성으로 읽힌다.
    ovs = []
    for i in range(n - 1):
        near = (i == CENTER - 1) or (i + 1 == CENTER)
        base = (cuts[i].width + cuts[i + 1].width) / 2.0
        ovs.append(int(base * (OV_LEAD if near else OV_FAR)))

    content = sum(c.width for c in cuts) - sum(ovs)

    # 대표의 가로 중심과 인물 덩어리의 가로 중심을 일치시킨다.
    # 둘이 어긋나면 대표를 정중앙에 놓는 순간 한쪽 여백만 넓어진다.
    # 왼쪽 간격을 e 만큼씩 좁혀 맞춘다 — 벌리면 빈틈이 생기므로 좁히는 쪽만 쓴다.
    lead_off0 = sum(cuts[i].width - ovs[i] for i in range(CENTER)) + cuts[CENTER].width / 2.0
    if CENTER:
        e = int(round((2 * lead_off0 - content) / CENTER))
        for i in range(CENTER):
            room = int((cuts[i].width + cuts[i + 1].width) / 2.0 * 0.20) - ovs[i]
            ovs[i] += max(-ovs[i], min(e, room))       # 겹침 상한 20%
        content = sum(c.width for c in cuts) - sum(ovs)

        # 왼쪽만으로 못 맞추면 오른쪽 간격을 벌려 나머지를 흡수한다 (겹침 하한 5%)
        right = list(range(CENTER, n - 1))
        if right:
            lead_off = sum(cuts[i].width - ovs[i] for i in range(CENTER)) + cuts[CENTER].width / 2.0
            d = int(round((2 * lead_off - content) / len(right)))
            if d > 0:
                for i in right:
                    floor = int((cuts[i].width + cuts[i + 1].width) / 2.0 * 0.05)
                    ovs[i] -= min(d, max(0, ovs[i] - floor))
                content = sum(c.width for c in cuts) - sum(ovs)

    side = 92

    # 대표의 가로 중심을 캔버스 정중앙에 맞춘다.
    # 짝수 인원이면 그냥 늘어놓았을 때 대표가 옆으로 밀린다.
    lead_off = sum(cuts[i].width - ovs[i] for i in range(CENTER)) + cuts[CENTER].width / 2.0
    pad_l = side + max(0.0, content / 2.0 - lead_off)
    pad_r = side + max(0.0, lead_off - content / 2.0)
    w = int(content + pad_l + pad_r)

    canvas = backdrop(w, h).convert('RGBA')

    pos, cx = [], int(pad_l)
    for i, c in enumerate(cuts):
        pos.append(cx)
        cx += c.width - (ovs[i] if i < n - 1 else 0)

    # 가운데에서 먼 사람부터 깔아 대표가 맨 위에 오게 한다
    for i in sorted(range(n), key=lambda i: abs(i - CENTER), reverse=True):
        canvas.alpha_composite(cuts[i], (pos[i], tops[i]))

    return canvas.convert('RGB')


if __name__ == '__main__':
    import sys
    argv = sys.argv[1:]
    if 'white' in argv:
        BG = 'white'
        argv.remove('white')
    suffix = '_white' if BG == 'white' else ''
    if argv and argv[0] == '6':
        ORDER = ['jang-woo-jin', 'ha-hye-yong', 'lee-ji-hun',
                 'park-jong-il', 'lim-jun-kyu', 'oh-hyun-jin']
        CENTER = 3
        name = 'group6%s.jpg' % suffix
    else:
        name = 'group5%s.jpg' % suffix
    img = build()
    img.save(os.path.join(OUT, name), quality=92)
    print('저장', name, img.size)
