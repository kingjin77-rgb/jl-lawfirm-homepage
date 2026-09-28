<?php
/**
 * 민감정보 암호화 — 주소·계좌번호는 sodium secretbox(XSalsa20-Poly1305)로
 * 암호화해 base64 로 저장한다. 키는 설정 파일의 crypto_key(32바이트 base64).
 * 키가 설정에만 있고 DB·저장소에 없으므로, DB 가 통째로 유출돼도
 * 주소·계좌 원문은 나오지 않는다.
 */

require_once __DIR__ . '/config.php';

function crypto_key(): string
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }
    $b64 = portal_config()['crypto_key'] ?? '';
    $raw = base64_decode($b64, true);
    if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        http_response_code(500);
        exit('crypto_key 설정이 없거나 32바이트 base64 가 아닙니다. config.sample.php 참고.');
    }
    $key = $raw;
    return $key;
}

/** 평문 → base64(논스+암호문). 빈 문자열은 빈 문자열 그대로. */
function encrypt_field(string $plain): string
{
    if ($plain === '') {
        return '';
    }
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = sodium_crypto_secretbox($plain, $nonce, crypto_key());
    return base64_encode($nonce . $cipher);
}

/** base64(논스+암호문) → 평문. 손상됐거나 키가 다르면 null. */
function decrypt_field(string $stored): ?string
{
    if ($stored === '') {
        return '';
    }
    $raw = base64_decode($stored, true);
    if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        return null;
    }
    $nonce  = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open($cipher, $nonce, crypto_key());
    return $plain === false ? null : $plain;
}
