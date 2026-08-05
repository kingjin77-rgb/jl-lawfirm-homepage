# -*- coding: utf-8 -*-
"""CSS/JS 참조에 버전 쿼리를 붙여 배포 후 브라우저 캐시가 남지 않게 한다.

사용법:
    python tools/bump_assets.py 4

스타일이나 스크립트를 고쳤는데 화면에 반영되지 않으면 이 스크립트로 숫자를 올리고
다시 배포하세요.
"""
import glob
import io
import os
import re
import sys

# Windows 콘솔(cp949)에서도 한글 로그가 깨지지 않도록
for _s in (sys.stdout, sys.stderr):
    try:
        _s.reconfigure(encoding="utf-8")
    except Exception:
        pass

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def main():
    ver = sys.argv[1] if len(sys.argv) > 1 else None
    if not ver:
        print("사용법: python tools/bump_assets.py <버전>  (예: 4)")
        return 1

    os.chdir(BASE)
    changed = 0
    for p in sorted(glob.glob('*.html')):
        s = io.open(p, encoding='utf-8').read()
        orig = s
        s = re.sub(r'(href="assets/css/style\.css)(\?v=[^"]*)?"', r'\1?v=%s"' % ver, s)
        s = re.sub(r'(src="assets/js/([a-z-]+)\.js)(\?v=[^"]*)?"', r'\1?v=%s"' % ver, s)
        if s != orig:
            io.open(p, 'w', encoding='utf-8').write(s)
            print('갱신', p)
            changed += 1

    print('완료 — %d개 파일을 v=%s 로 올렸습니다.' % (changed, ver))
    return 0


if __name__ == '__main__':
    sys.exit(main())
