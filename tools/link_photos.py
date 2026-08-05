# -*- coding: utf-8 -*-
"""변호사 사진 파일을 lawyers.json 에 자동 연결한다.

사용법:
  1. 사진을 assets/img/lawyers/ 에 slug 이름으로 저장한다.
       park-jong-il.jpg   박종일
       lee-ji-hun.jpg     이지훈
       jang-woo-jin.jpg   장우진
       lim-jun-kyu.jpg    임준규
       ha-hye-yong.jpg    하혜용
     (jpg / png / webp 모두 인식)
  2. python tools/link_photos.py
  3. python tools/make_lawyer_pages.py   ← 개인 페이지 갱신

권장 사양: 세로형(3:4), 800×1060 이상, 배경 정리된 상반신.
"""
import json
import io
import os
import sys

for _s in (sys.stdout, sys.stderr):
    try:
        _s.reconfigure(encoding="utf-8")
    except Exception:
        pass

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PHOTO_DIR = os.path.join(BASE, "assets", "img", "lawyers")
EXTS = (".jpg", ".jpeg", ".png", ".webp")


def main():
    path = os.path.join(BASE, "data", "lawyers.json")
    data = json.load(open(path, encoding="utf-8"))

    os.makedirs(PHOTO_DIR, exist_ok=True)
    linked = kept = missing = 0
    for L in data.get("lawyers", []):
        slug = L.get("slug")
        if not slug:
            continue
        found = ""
        for ext in EXTS:
            p = os.path.join(PHOTO_DIR, slug + ext)
            if os.path.exists(p):
                found = "assets/img/lawyers/" + slug + ext
                break
        if found:
            if L.get("photo") == found:
                kept += 1
            else:
                L["photo"] = found
                linked += 1
                print("연결 —", L["name"], "→", found)
        else:
            missing += 1
            print("사진 없음 —", L["name"], "(%s.jpg 대기)" % slug)

    if linked:
        io.open(path, "w", encoding="utf-8", newline="").write(
            json.dumps(data, ensure_ascii=False, indent=2) + "\n")
        print("lawyers.json 갱신 완료. make_lawyer_pages.py 를 다시 실행하세요.")
    else:
        print("갱신할 항목 없음 (연결됨 %d · 대기 %d)" % (kept, missing))
    return 0


if __name__ == "__main__":
    sys.exit(main())
