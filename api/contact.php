<?php
// Contact endpoint — real email delivery, server-side only.
//
// TRANSPORTS (auto-selected; HTTPS API first because it runs on 443 and is not
// blocked by local antivirus mail filters, SMTP second):
//   1) RESEND_API_KEY set  -> Resend HTTPS API (https://api.resend.com, port 443)
//   2) otherwise           -> Gmail SMTP via PHPMailer (PHPMailer bundled in api/lib/)
//
// SECRETS: never in this file. Set env vars, or api/config.local.php (gitignored):
//   RESEND_API_KEY, MAIL_FROM (sender), MAIL_TO (inbox), GMAIL_USER, GMAIL_APP_PASSWORD
// GitHub Pages (static) cannot run this file — deploy to any PHP host to use it.

ini_set('display_errors', '0'); // never leak paths/stack traces to visitors
ini_set('log_errors', '1');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/lib/Exception.php';
require __DIR__ . '/lib/PHPMailer.php';
require __DIR__ . '/lib/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { fail('Method not allowed.', 405); }

// ---------- input ----------
$data = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($data)) $data = $_POST;

$name    = clean_line((string)($data['name'] ?? ''));
$email   = clean_line((string)($data['email'] ?? ''));
$subject = clean_line((string)($data['subject'] ?? ''));
$message = trim((string)($data['message'] ?? ''));
$honey   = trim((string)($data['_honey'] ?? ''));

// Honeypot filled => bot. Report success so bots don't learn, but send nothing.
if ($honey !== '') { echo json_encode(['ok' => true]); exit; }

// ---------- validation (server-side, authoritative) ----------
if ($name === '' || mb_strlen($name) > 100) fail('Please provide your name.', 422);
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) fail('Enter a valid email address.', 422);
if ($subject === '' || mb_strlen($subject) > 150) fail('Please add a subject.', 422);
if (mb_strlen($message) < 10 || mb_strlen($message) > 5000) fail('Message needs 10-5000 characters.', 422);

// ---------- abuse protection: 5 sends per hour per IP ----------
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!rate_limit_ok($ip)) fail('Too many messages sent. Please try again later.', 429);

// ---------- configuration ----------
$cfg = load_config();
$to = $cfg['MAIL_TO'] ?: ($cfg['GMAIL_USER'] ?: '');
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) { fail('Mail backend not configured on this server.', 501); }

$body = "New portfolio inquiry\n\n"
      . "Name:    {$name}\n"
      . "Email:   {$email}\n"
      . "Subject: {$subject}\n"
      . "IP:      {$ip}\n"
      . "Sent:    " . gmdate('Y-m-d H:i') . " UTC\n\n"
      . "Message:\n{$message}\n";

// ---------- send: HTTPS API first, SMTP second ----------
$sent = false; $stage = ''; $detail = '';

if ($cfg['RESEND_API_KEY'] !== '') {
  [$ok, $detail] = send_via_resend($cfg['RESEND_API_KEY'], $cfg['MAIL_FROM'], $to, $name, $email, $subject, $body);
  $sent = $ok; $stage = 'api';
}
if (!$sent && $cfg['GMAIL_USER'] !== '' && $cfg['GMAIL_APP_PASSWORD'] !== '') {
  [$ok, $detail] = send_via_smtp($cfg, $to, $name, $email, $subject, $body);
  $sent = $ok; $stage = 'smtp';
}

if (!$sent) {
  error_log("portfolio mail failed [{$stage}]: " . mb_substr($detail, 0, 250)); // diagnostic, server-side only
  fail(visitor_message($stage, $detail, $to), 502);
}

echo json_encode(['ok' => true, 'transport' => $stage]);
exit;

// ===================== helpers =====================

function fail(string $msg, int $code): void {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg]);
  exit;
}

function clean_line(string $s): string {
  return trim(preg_replace('/[\r\n\t]+/', ' ', $s));
}

/** env vars win; api/config.local.php fills the gaps (gitignored) */
function load_config(): array {
  $keys = ['RESEND_API_KEY', 'MAIL_FROM', 'MAIL_TO', 'GMAIL_USER', 'GMAIL_APP_PASSWORD'];
  $cfg = [];
  foreach ($keys as $k) $cfg[$k] = (string)(getenv($k) ?: '');
  $file = __DIR__ . '/config.local.php';
  if (is_file($file)) {
    $local = require $file;
    if (is_array($local)) foreach ($keys as $k) if ($cfg[$k] === '') $cfg[$k] = (string)($local[$k] ?? '');
  }
  return $cfg;
}

function rate_limit_ok(string $ip): bool {
  $f = sys_get_temp_dir() . '/portfolio_rl_' . md5($ip) . '.json';
  $now = time(); $hits = [];
  if (is_file($f)) $hits = json_decode((string)@file_get_contents($f), true) ?: [];
  $hits = array_values(array_filter((array)$hits, fn($t) => ($now - (int)$t) < 3600));
  if (count($hits) >= 5) return false;
  $hits[] = $now;
  @file_put_contents($f, json_encode($hits), LOCK_EX);
  return true;
}

/** HTTPS mail API on port 443 — immune to local SMTP/IMAP interception. */
function send_via_resend(string $key, string $from, string $to, string $name, string $email, string $subject, string $body): array {
  $payload = json_encode([
    'from' => $from !== '' ? $from : 'Portfolio <onboarding@resend.dev>',
    'to' => [$to],
    'reply_to' => $email,          // reply straight to the visitor
    'subject' => '[Portfolio] ' . $subject,
    'text' => $body,
  ]);
  $ch = curl_init('https://api.resend.com/emails');
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
  ]);
  $res = curl_exec($ch);
  $err = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($res === false) return [false, 'network: ' . $err];
  $json = json_decode((string)$res, true);
  if ($code >= 200 && $code < 300) return [true, 'accepted id=' . ($json['id'] ?? '?')];
  return [false, "api http $code: " . mb_substr((string)$res, 0, 200)];
}

function send_via_smtp(array $cfg, string $to, string $name, string $email, string $subject, string $body): array {
  $ca = null;
  foreach (['C:\\xampp\\apache\\bin\\curl-ca-bundle.crt', 'C:\\xampp\\php\\extras\\ssl\\cacert.pem', ini_get('openssl.cafile')] as $c) {
    if (is_string($c) && $c !== '' && is_file($c)) { $ca = $c; break; }
  }
  try {
    $m = new PHPMailer(true);
    $m->isSMTP();
    $m->Host = 'smtp.gmail.com';
    $m->Port = 587;
    $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $m->SMTPAuth = true;
    $m->Username = $cfg['GMAIL_USER'];
    $m->Password = $cfg['GMAIL_APP_PASSWORD'];
    $m->Timeout = 20;
    $m->CharSet = 'UTF-8';
    if ($ca !== null) {
      $m->SMTPOptions = ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => 'smtp.gmail.com', 'cafile' => $ca]];
    }
    $m->setFrom($cfg['MAIL_FROM'] ?: $cfg['GMAIL_USER'], mb_substr($name, 0, 60));
    $m->addAddress($to);
    $m->addReplyTo($email, mb_substr($name, 0, 60)); // reply-to visitor
    $m->Subject = '[Portfolio] ' . $subject;
    $m->Body = $body;
    $m->send();
    return [true, 'smtp accepted'];
  } catch (MailerException $e) {
    return [false, 'smtp: ' . $e->getMessage()];
  } catch (Throwable $t) {
    return [false, 'smtp exception: ' . get_class($t)];
  }
}

/** Map failures to safe visitor text — never expose paths, keys, or stack traces. */
function visitor_message(string $stage, string $detail, string $to): string {
  $d = strtolower($detail);
  if (str_contains($d, 'curl') || str_contains($d, 'timed out') || str_contains($d, 'could not resolve') || str_contains($d, 'failed to connect')) {
    return 'Could not reach the mail service. Please try again, or email ' . $to . ' directly.';
  }
  if (str_contains($d, 'tls') || str_contains($d, 'ssl') || str_contains($d, 'certificate') || str_contains($d, 'crypto') || str_contains($d, 'wrong version')) {
    return 'Secure connection to the mail server failed. Please try again, or email ' . $to . ' directly.';
  }
  if (str_contains($d, 'auth') || str_contains($d, 'credential') || str_contains($d, 'unauthorized') || str_contains($d, '401') || str_contains($d, '403') || str_contains($d, 'api key')) {
    return 'The mail service rejected the request. Please email ' . $to . ' directly.';
  }
  if (str_contains($d, 'verified') || str_contains($d, 'domain')) {
    return 'The mail service needs sender verification before it can deliver. Please email ' . $to . ' directly.';
  }
  return 'The message could not be sent right now. Please try again, or email ' . $to . ' directly.';
}