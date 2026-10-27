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
 * 키 만들기 (32바이트를 base64 로) — crypto_key 와 backup_key 는 서로 다른 값으로:
 *   php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
 * cron_token 만들기 (48자):
 *   php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"
 *
 * crypto_key: 주소·계좌·OTP 비밀키 암호화 키. 잃어버리면 기존 암호화 데이터를 못 푼다.
 * backup_key: 백업 파일 암호화 키. 잃어버리면 백업을 못 푼다.
 * 두 키는 이 파일에만 두고 DB·저장소에는 절대 넣지 않는다. 사본은 별도 장소에 보관.
 *
 * env=prod 이면 부팅 때 점검한다 (app/security.php): 개발 키 재사용·sms_driver 'dev'·
 * 짧은 cron_token·backup_key=crypto_key 이면 서비스를 시작하지 않는다. https 가 아니면 https 로 넘긴다.
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
    'env' => 'prod',                    // 'dev' 면 에러를 화면에 표시 (운영 점검도 건너뜀)
    'crypto_key' => '여기에_32바이트_base64_키',

    // 문자 발송 — 'aligo' | 'solapi' (운영) / 'dev' (개발: 인증번호를 화면에 표시, 운영에서는 부팅 거부)
    'sms_driver' => 'aligo',
    'sms' => [
        'aligo'  => ['key' => '', 'user_id' => '', 'sender' => '1899-4252', 'live' => false],
        'solapi' => ['key' => '', 'secret' => '', 'sender' => '1899-4252', 'live' => false],
        // live=false 동안은 요청만 만들고 보내지 않는다 (업체 계약·발신번호 등록 후 true)
    ],

    // 운영 도구 (모두 웹루트 밖 경로)
    'backup_key'         => '여기에_다른_32바이트_base64_키',
    'backup_dir'         => '/home/계정/portal-backup',
    'integrity_baseline' => '/home/계정/portal-integrity.json',
    'tmp_dir'            => '/home/계정/portal-tmp',      // 엑셀 업로드 임시폴더
    'session_path'       => '/home/계정/portal-sessions', // 공유호스팅 세션 파일 전용 폴더 (선택)
    'cron_token'         => '',                          // 비우면 /cron/* 비활성 (32자 이상)

    // 'base_host' => 'jllawfirm.kr',   // https 리다이렉트 때 쓸 호스트 (Host 헤더 대신 고정)
    // 'trust_proxy_https' => false,     // 프록시가 X-Forwarded-Proto 를 붙여 줄 때만 true
];
