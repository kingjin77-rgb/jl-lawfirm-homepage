<?php
/**
 * 설정 파일 견본 — 실제 설정 파일은 반드시 웹루트(도큐먼트루트) 밖에 둔다.
 *
 * 위치:
 *  - 가비아: 도큐먼트루트(html/) 옆에 portal-config.php 로 저장
 *    (app/config.php 가 __DIR__/../../../portal-config.php 를 찾는다)
 *  - 로컬 개발: D:\DDownloads\jl-portal-dev\config.local.php
 *  - 그 밖의 경로: 환경변수 JL_PORTAL_CONFIG 로 지정
 *
 * crypto_key 만들기 (32바이트를 base64 로):
 *   php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
 * 이 키가 주소·계좌 암호화 키다. 잃어버리면 기존 암호화 데이터를 못 푼다.
 * 키는 이 파일에만 두고 DB·저장소에는 절대 넣지 않는다.
 */
return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'DB이름',
        'user'    => 'DB사용자',
        'pass'    => 'DB비밀번호',
        'charset' => 'utf8mb4',
    ],
    'env' => 'prod',                    // 'dev' 면 에러를 화면에 표시
    'crypto_key' => '여기에_32바이트_base64_키',
];
