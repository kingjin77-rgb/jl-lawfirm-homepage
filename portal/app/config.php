<?php
/**
 * 설정 로더 — 설정 파일은 반드시 웹루트 밖에 둔다.
 * 탐색 순서:
 *   1) JL_PORTAL_CONFIG 상수 또는 환경변수 (배포·테스트에서 강제 지정용)
 *   2) 가비아: 도큐먼트루트 옆 portal-config.php (repo 밖)
 *   3) 로컬 개발: D:\DDownloads\jl-portal-dev\config.local.php
 */

function portal_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $candidates = [];
    if (defined('JL_PORTAL_CONFIG')) {
        $candidates[] = JL_PORTAL_CONFIG;
    }
    $env = getenv('JL_PORTAL_CONFIG');
    if ($env !== false && $env !== '') {
        $candidates[] = $env;
    }
    // 가비아 공유호스팅: /web(도큐먼트루트) 옆에 portal-config.php 를 두는 구조.
    $candidates[] = __DIR__ . '/../../../portal-config.php';
    // 로컬 개발 폴백 — 저장소 밖 고정 경로.
    $candidates[] = 'D:\\DDownloads\\jl-portal-dev\\config.local.php';

    foreach ($candidates as $path) {
        if (is_file($path)) {
            $loaded = require $path;
            if (is_array($loaded) && isset($loaded['db'])) {
                $config = $loaded;
                return $config;
            }
        }
    }

    http_response_code(500);
    exit('설정 파일을 찾을 수 없습니다. portal/config.sample.php 를 참고해 웹루트 밖에 만들어 주십시오.');
}
