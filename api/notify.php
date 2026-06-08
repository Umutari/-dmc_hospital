<?php
/**
 * /dmc/api/notify.php
 * Shared with accountant/payments.php for OTP + receipt delivery.
 * OTP codes are stored server-side in sessions — never returned to client.
 */
require_once __DIR__ . '/../config/functions.php';
requireLogin();

header('Content-Type: application/json');

define('OTP_TTL',       600);
define('OTP_MAX_TRIES', 5);

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($input['action'] ?? '');
$phone  = trim($input['phone']  ?? '');
$name   = trim($input['name']   ?? 'Patient');
$channel= trim($input['channel']?? 'sms');

function dmcSendSMS(string $phone, string $message): string {
    $token   = setting('mista_api_key') ?: '691|noH******************************wuaZx';
    $sender  = setting('sms_sender_id') ?: 'E-Notifier';
    $clean   = preg_replace('/[^0-9]/', '', preg_replace('/^\+?250/', '', $phone));
    $contact = '+250' . $clean;
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
            'sender_id' => $sender,
            'type'      => 'plain',
            'message'   => $message,
        ]),
    ]);
    $response  = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $ok = ($http_code >= 200 && $http_code < 300);
    if (!$ok) {
        $body = json_decode($response ?: '{}', true);
        if (!empty($body['status']) && $body['status'] == 200) $ok = true;
    }
    $status = $ok ? 'sent' : 'failed';
    execute("INSERT INTO sms_logs (recipient_phone, message, channel, status) VALUES (?,?,'otp',?)",
            [$phone, substr($message, 0, 50).'...', $status]);
    return $status === 'sent' ? 'success' : 'error';
}

function dmcSendWhatsApp(string $phone, string $message): string {
    $token    = setting('wa_token');
    $phone_id = setting('wa_phone_id');
    if (!$token || !$phone_id) return 'error';
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if (strpos($digits, '250') === 0) $digits = substr($digits, 3);
    if (strpos($digits, '0')   === 0) $digits = substr($digits, 1);
    $to   = '250' . $digits;
    $url  = 'https://graph.facebook.com/v22.0/' . $phone_id . '/messages';
    $data = json_encode(['messaging_product'=>'whatsapp','to'=>$to,'type'=>'text','text'=>['body'=>$message]]);
    $opts = ['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\nAuthorization: Bearer ".$token."\r\n",'content'=>$data,'ignore_errors'=>true]];
    $result = file_get_contents($url, false, stream_context_create($opts));
    $json   = json_decode($result ?: '', true);
    return isset($json['messages']) ? 'success' : 'error';
}

function makeOTP(): string { return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT); }

/* ── send_otp ── */
if ($action === 'send_otp') {
    if (!$phone) { echo json_encode(['ok'=>false,'error'=>'Phone required']); exit; }
    $otp_sms = makeOTP(); $otp_wa = makeOTP();
    $exp = time() + OTP_TTL;
    $_SESSION['dmc_otp_sms'] = $otp_sms; $_SESSION['dmc_otp_sms_exp'] = $exp;
    $_SESSION['dmc_otp_wa']  = $otp_wa;  $_SESSION['dmc_otp_wa_exp']  = $exp;
    $_SESSION['dmc_otp_tries'] = 0;

    $sms_msg = "DMC Hospital\nPayment OTP: $otp_sms\nValid 10 min. Do NOT share. KK 541 St, Kigali.";
    $wa_msg  = "*DMC Hospital*\n\nPayment OTP: *$otp_wa*\nValid 10 minutes.\n\n_Do not share this code._";

    $sms_st = $wa_st = 'skipped';
    if ($channel==='sms'||$channel==='both')      $sms_st = dmcSendSMS($phone, $sms_msg);
    if ($channel==='whatsapp'||$channel==='both') $wa_st  = dmcSendWhatsApp($phone, $wa_msg);

    $ok = ($sms_st==='success'||$wa_st==='success');
    echo json_encode(['ok'=>$ok,'sms_status'=>$sms_st,'wa_status'=>$wa_st,'error'=>$ok?null:'Could not deliver OTP. Please try again.']);
    exit;
}

/* ── verify_otp ── */
if ($action === 'verify_otp') {
    $entered = trim($input['code'] ?? '');
    $tab     = trim($input['tab']  ?? 'sms');
    $tries   = (int)($_SESSION['dmc_otp_tries'] ?? 0);
    if ($tries >= OTP_MAX_TRIES) { echo json_encode(['ok'=>false,'error'=>'Too many attempts. Request a new code.']); exit; }
    $key = $tab === 'whatsapp' ? 'dmc_otp_wa' : 'dmc_otp_sms';
    $exp = $key . '_exp';
    if (empty($_SESSION[$key])) { echo json_encode(['ok'=>false,'error'=>'No OTP found. Request a new code.']); exit; }
    if (time() > (int)($_SESSION[$exp] ?? 0)) { unset($_SESSION[$key],$_SESSION[$exp]); echo json_encode(['ok'=>false,'error'=>'Code expired. Request a new one.']); exit; }
    $ok = hash_equals($_SESSION[$key], $entered);
    if ($ok) {
        unset($_SESSION['dmc_otp_sms'],$_SESSION['dmc_otp_sms_exp'],$_SESSION['dmc_otp_wa'],$_SESSION['dmc_otp_wa_exp'],$_SESSION['dmc_otp_tries']);
        echo json_encode(['ok'=>true]);
    } else {
        $_SESSION['dmc_otp_tries'] = $tries + 1;
        $left = OTP_MAX_TRIES - $_SESSION['dmc_otp_tries'];
        echo json_encode(['ok'=>false,'error'=>"Incorrect code. ".($left>0?"{$left} attempt(s) left.":'No attempts left — request new code.')]);
    }
    exit;
}

echo json_encode(['ok'=>false,'error'=>'Unknown action']);
