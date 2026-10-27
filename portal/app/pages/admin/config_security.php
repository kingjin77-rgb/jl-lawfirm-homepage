<?php
/**
 * 보안 설정 — admin 전용 + OTP 재확인.
 *  ① 관리자 IP 허용목록 (setting admin_ip_allowlist, 비면 미적용).
 *     지금 접속한 IP 가 새 목록에 없으면 저장을 거부한다(자기 잠금 방지).
 *  ② IP 차단 현황 (10분 내 다수 실패 → 1시간) — 수동 해제.
 */

$staff = staff_require_admin();
staff_require_reauth();

$notice = '';
$error = '';
$myIp = client_ip();
$text = (string)(db_row('SELECT value FROM setting WHERE name = ?', ['admin_ip_allowlist'])['value'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $act = (string)($_POST['act'] ?? '');
    if ($act === 'allowlist') {
        $new = str_replace("\r\n", "\n", (string)($_POST['allowlist'] ?? ''));
        $parsed = admin_allowlist_parse($new);
        if ($parsed['bad'] !== []) {
            $error = 'IP/CIDR 형식이 아닌 줄이 있습니다: ' . implode(', ', array_slice($parsed['bad'], 0, 5));
            $text = $new;
        } elseif ($parsed['entries'] !== [] && !admin_ip_allowed($myIp, $parsed['entries'])) {
            $error = "지금 접속한 IP({$myIp})가 새 목록에 없어 저장하지 않았습니다. 저장하면 이 화면에서 바로 잠깁니다.";
            $text = $new;
        } else {
            setting_set('admin_ip_allowlist', trim($new));
            $text = trim($new);
            staff_audit((int)$staff['id'], 'security.allowlist', 'setting:admin_ip_allowlist',
                $parsed['entries'] === [] ? '허용목록 비움(미적용)' : '허용 ' . count($parsed['entries']) . '건: ' . implode(', ', $parsed['entries']));
            $notice = $parsed['entries'] === [] ? '허용목록을 비웠습니다 — 모든 IP 에서 관리자 화면이 열립니다.'
                : '허용목록을 저장했습니다 (' . count($parsed['entries']) . '건).';
        }
    } elseif ($act === 'release') {
        $id = (int)($_POST['id'] ?? 0);
        $row = db_row('SELECT ip FROM ip_block WHERE id = ?', [$id]);
        if ($row !== null) {
            db_exec('UPDATE ip_block SET released_by = ? WHERE ip = ? AND released_by IS NULL', [(int)$staff['id'], $row['ip']]);
            // 해제 직후 같은 실패 기록으로 바로 다시 막히지 않게, 그 IP 의 최근 실패를 집계에서 뺀다.
            db_exec("UPDATE login_log SET ok = 2 WHERE ip = ? AND ok = 0 AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)",
                [$row['ip'], IPBLOCK_WINDOW_MIN]);
            staff_audit((int)$staff['id'], 'security.ipblock.release', 'ip:' . $row['ip'], 'IP 차단 수동 해제');
            $notice = 'IP 차단을 풀었습니다: ' . $row['ip'];
        }
    }
}

$blocks = db_all('SELECT id, ip, reason, blocked_until, created_at FROM ip_block
                   WHERE blocked_until > NOW() AND released_by IS NULL ORDER BY id DESC LIMIT 100');

render_admin('config_security', [
    'pageTitle' => '보안 설정',
    'staff'     => $staff,
    'text'      => $text,
    'myIp'      => $myIp,
    'blocks'    => $blocks,
    'notice'    => $notice,
    'error'     => $error,
]);
