<?php
require_once __DIR__ . '/db.php';

/* ── Session ── */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['samesite' => 'Lax', 'httponly' => true]);
    session_start();
}

/* ── Auth helpers ── */
function isLoggedIn(): bool { return isset($_SESSION['user_id']); }

function requireLogin(string $role = ''): void {
    if (!isLoggedIn()) { header('Location: /dmc/index.php'); exit; }
    if ($role && $_SESSION['role'] !== $role && $_SESSION['role'] !== 'admin') {
        header('Location: /dmc/index.php?error=access_denied'); exit;
    }
}

function requireRoles(array $roles): void {
    if (!isLoggedIn() || !in_array($_SESSION['role'], $roles)) {
        header('Location: /dmc/index.php?error=access_denied'); exit;
    }
}

function currentUser(): array { return $_SESSION['user'] ?? []; }
function currentRole(): string { return $_SESSION['role'] ?? ''; }
function currentUserId(): int  { return (int)($_SESSION['user_id'] ?? 0); }

function dashboardUrl(): string {
    $map = [
        'admin'          => '/dmc/admin/index.php',
        'doctor'         => '/dmc/doctor/index.php',
        'nurse'          => '/dmc/nurse/index.php',
        'receptionist'   => '/dmc/receptionist/index.php',
        'pharmacist'     => '/dmc/pharmacist/index.php',
        'accountant'     => '/dmc/accountant/index.php',
        'lab_technician' => '/dmc/lab/index.php',
        'patient'        => '/dmc/patient/index.php',
    ];
    return $map[$_SESSION['role'] ?? ''] ?? '/dmc/index.php';
}

/* ── DB helpers ── */
function row(string $sql, array $params = []): array {
    $st = db()->prepare($sql); $st->execute($params); return $st->fetch() ?: [];
}
function rows(string $sql, array $params = []): array {
    $st = db()->prepare($sql); $st->execute($params); return $st->fetchAll();
}
function scalar(string $sql, array $params = []) {
    $st = db()->prepare($sql); $st->execute($params); return $st->fetchColumn();
}
function execute(string $sql, array $params = []): int {
    $st = db()->prepare($sql); $st->execute($params); return (int)db()->lastInsertId() ?: $st->rowCount();
}

/* ── String/format helpers ── */
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function money(float $n): string { return 'RWF ' . number_format($n, 0, '.', ','); }
function dateF(string $d): string { return $d ? date('d M Y', strtotime($d)) : '—'; }
function timeF(string $t): string { return $t ? date('h:i A', strtotime($t)) : '—'; }
function dtF(string $dt): string  { return $dt ? date('d M Y, h:i A', strtotime($dt)) : '—'; }
function age(string $dob): int    { return (int)date_diff(date_create($dob), date_create('today'))->y; }

function generateNo(string $prefix, string $table, string $column): string {
    $year  = date('Y');
    $count = (int)scalar("SELECT COUNT(*) FROM $table WHERE $column LIKE '$prefix-$year-%'") + 1;
    return "$prefix-$year-" . str_pad($count, 4, '0', STR_PAD_LEFT);
}

function avatarUrl(string $file): string {
    return '/dmc/assets/img/avatars/' . $file;
}

/* ── Setting helper ── */
function setting(string $key, string $default = ''): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        $cache[$key] = scalar("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]) ?: $default;
    }
    return $cache[$key];
}

/* ── Notification helper ── */
function notify(int $userId, string $title, string $message, string $type = 'info', string $link = ''): void {
    execute("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?,?,?,?,?)",
            [$userId, $title, $message, $type, $link]);
}

function unreadCount(int $userId): int {
    return (int)scalar("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$userId]);
}

/* ── Audit log ── */
function audit(string $action, string $table = '', int $recordId = 0, string $details = ''): void {
    execute("INSERT INTO audit_logs (user_id, action, table_name, record_id, details, ip_address) VALUES (?,?,?,?,?,?)",
            [currentUserId(), $action, $table, $recordId, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
}

/* ── SMS sender via Mista API ── */
function sendSMS(string $phone, string $message): string {
    $token     = setting('mista_api_key') ?: '691|noH******************************wuaZx';
    $senderId  = setting('sms_sender_id') ?: 'E-Notifier';
    $clean     = preg_replace('/[^0-9]/', '', preg_replace('/^\+?250/', '', $phone));
    $contact   = '+250' . $clean;
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => 'https://api.mista.io/sms',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'recipient' => $contact,
            'sender_id' => $senderId,
            'type'      => 'plain',
            'message'   => $message,
        ]),
    ]);
    $response  = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $status = ($http_code >= 200 && $http_code < 300) ? 'sent' : 'failed';
    if ($status === 'failed') {
        $body = json_decode($response ?: '{}', true);
        if (!empty($body['status']) && $body['status'] == 200) $status = 'sent';
    }
    execute("INSERT INTO sms_logs (recipient_phone, message, channel, status) VALUES (?,?,'sms',?)",
            [$phone, $message, $status]);
    return $status;
}

function sendAppointmentSMS(array $patient, array $appt): void {
    $msg = "DMC Hospital\nDear {$patient['first_name']}, your appointment is confirmed.\nDate: " .
           dateF($appt['appointment_date']) . " at " . timeF($appt['appointment_time']) .
           "\nDoctor: Dr. {$appt['doctor_name']}\nRef: {$appt['appointment_no']}\nCall: 0782 749 660";
    sendSMS($patient['phone'], $msg);
}

function sendPaymentSMS(array $patient, array $payment): void {
    $msg = "DMC Hospital\nPayment received!\nAmount: " . money($payment['amount']) .
           "\nInvoice: {$payment['invoice_no']}\nRef: {$payment['payment_no']}\nThank you, {$patient['first_name']}!";
    sendSMS($patient['phone'], $msg);
}

/* ── Flash messages ── */
function flash(string $key, string $msg, string $type = 'success'): void {
    $_SESSION['flash'][$key] = ['msg' => $msg, 'type' => $type];
}
function getFlash(string $key): array {
    $f = $_SESSION['flash'][$key] ?? [];
    unset($_SESSION['flash'][$key]);
    return $f;
}
function showFlash(string $key): string {
    $f = getFlash($key);
    if (!$f) return '';
    $icons = ['success'=>'check-circle','danger'=>'x-circle','warning'=>'exclamation-triangle','info'=>'info-circle'];
    $icon = $icons[$f['type']] ?? 'info-circle';
    return "<div class='alert alert-{$f['type']} alert-dismissible fade show' role='alert'>
        <i class='bi bi-{$icon} me-2'></i>{$f['msg']}
        <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
}
