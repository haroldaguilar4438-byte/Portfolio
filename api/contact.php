<?php
// True-SMTP contact endpoint — Gmail via PHPMailer (STARTTLS), no Composer needed.
// Library lives in api/lib/ (PHPMailer v6.9.3 src, MIT license).
// Secrets NEVER live here. Set env vars or api/config.local.php (gitignored).
//   GMAIL_USER, GMAIL_APP_PASSWORD, MAIL_TO (optional, defaults to GMAIL_USER)
// Local XAMPP: use api/config.local.php (see config.example.php).
// Production: set real env vars on your host. GitHub Pages CANNOT run this file.

// Never render PHP errors to visitors (stack traces can contain arguments).
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

require __DIR__ . '/lib/Exception.php';
require __DIR__ . '/lib/PHPMailer.php';
require __DIR__ . '/lib/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
  exit;
}

// ---- input (JSON or form) ----
$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
if (!is_array($data)) $data = $_POST;

$name    = trim((string)($data['name'] ?? ''));
$email   = trim((string)($data['email'] ?? ''));
$subject = trim((string)($data['subject'] ?? ''));
$message = trim((string)($data['message'] ?? ''));
$honey   = trim((string)($data['_honey'] ?? ''));

// spam trap: pretend success
if ($honey !== '') { echo json_encode(['ok' => true]); exit; }

// ---- validation ----
$clean = fn($s) => preg_replace("/[\r\n]+/", ' ', $s);
$name = $clean($name); $subject = $clean($subject); $email = $clean($email);

if ($name === '' || mb_strlen($name) > 100) fail('Please provide your name.', 422);
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) fail('Enter a valid email address.', 422);
if ($subject === '' || mb_strlen($subject) > 150) fail('Please add a subject.', 422);
if (mb_strlen($message) < 10 || mb_strlen($message) > 5000) fail('Message needs 10–5000 characters.', 422);

// ---- rate limit: 5/hour per IP ----
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rlFile = sys_get_temp_dir() . '/portfolio_rl_' . md5($ip) . '.json';
$now = time(); $hits = [];
if (is_file($rlFile)) { $hits = json_decode((string)file_get_contents($rlFile), true) ?: []; }
$hits = array_values(array_filter((array)$hits, fn($t) => ($now - (int)$t) < 3600));
if (count($hits) >= 5) fail('Too many messages. Please try again later.', 429);
$hits[] = $now;
@file_put_contents($rlFile, json_encode($hits), LOCK_EX);

// ---- credentials (env wins, local file fallback) ----
$user = getenv('GMAIL_USER') ?: '';
$pass = getenv('GMAIL_APP_PASSWORD') ?: '';
$to   = getenv('MAIL_TO') ?: '';
$localFile = __DIR__ . '/config.local.php';
if (($user === '' || $pass === '') && is_file($localFile)) {
  $local = require $localFile;
  if (is_array($local)) {
    $user = $user ?: (string)($local['GMAIL_USER'] ?? '');
    $pass = $pass ?: (string)($local['GMAIL_APP_PASSWORD'] ?? '');
    $to   = $to   ?: (string)($local['MAIL_TO'] ?? '');
  }
}
if ($to === '') $to = $user;
if ($user === '' || $pass === '' || $to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
  http_response_code(501);
  echo json_encode(['ok' => false, 'error' => 'Mail backend not configured on this server.']);
  exit;
}

// ---- CA bundle for TLS verification (XAMPP php.ini often leaves this unset) ----
$ca = null;
foreach (['C:\\xampp\\apache\\bin\\curl-ca-bundle.crt', 'C:\\xampp\\php\\extras\\ssl\\cacert.pem', ini_get('openssl.cafile')] as $c) {
  if (is_string($c) && $c !== '' && is_file($c)) { $ca = $c; break; }
}

// ---- send via PHPMailer + Gmail SMTP ----
try {
  $mail = new PHPMailer(true);
  $mail->isSMTP();
  $mail->Host = 'smtp.gmail.com';
  $mail->Port = 587;
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->SMTPAuth = true;
  $mail->Username = $user;
  $mail->Password = $pass;
  $mail->Timeout = 15;
  $mail->CharSet = 'UTF-8';
  if ($ca !== null) {
    $mail->SMTPOptions = ['ssl' => [
      'verify_peer' => true, 'verify_peer_name' => true,
      'peer_name' => 'smtp.gmail.com', 'cafile' => $ca,
    ]];
  }
  $mail->setFrom($user, mb_substr($name, 0, 60));
  $mail->addAddress($to);
  $mail->addReplyTo($email, mb_substr($name, 0, 60));
  $mail->Subject = '[Portfolio] ' . mb_substr($subject, 0, 140);
  $mail->Body = "Name: {$name}\nEmail: {$email}\nSubject: {$subject}\nIP: {$ip}\n\n{$message}\n";
  $mail->send();
} catch (MailerException $e) {
  error_log('portfolio smtp: ' . mb_substr($e->getMessage(), 0, 200));
  fail(mapMailerError($e->getMessage(), $to), 502);
} catch (Throwable $t) {
  error_log('portfolio smtp: exception ' . get_class($t));
  fail('Mail server rejected the message. Please email ' . $to . ' directly.', 502);
}

echo json_encode(['ok' => true]);
exit;

// ================= helpers =================
function fail(string $msg, int $code): void {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg]);
  exit;
}

/** Map PHPMailer errors to safe, stage-specific visitor messages (no secrets). */
function mapMailerError(string $detail, string $to): string {
  $d = strtolower($detail);
  if (str_contains($d, 'connect') || str_contains($d, 'timed out') || str_contains($d, 'network')) {
    return 'Could not reach the mail server. Please try again later.';
  }
  if (str_contains($d, 'tls') || str_contains($d, 'starttls') || str_contains($d, 'crypto') || str_contains($d, 'certificate') || str_contains($d, 'ssl')) {
    return 'Secure connection to the mail server failed. Please try again later.';
  }
  if (str_contains($d, 'auth') || str_contains($d, 'password') || str_contains($d, 'username') || str_contains($d, 'credentials') || str_contains($d, '535') || str_contains($d, '534')) {
    return 'Mail login was rejected. The site owner needs to check the mail user/app password.';
  }
  return 'Mail server rejected the message. Please email ' . $to . ' directly.';
}
