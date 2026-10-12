<?php
// True-SMTP contact endpoint — Gmail via STARTTLS, no dependencies.
// Secrets NEVER live here. Set env vars or api/config.local.php (gitignored).
//   GMAIL_USER, GMAIL_APP_PASSWORD, MAIL_TO (optional, defaults to GMAIL_USER)
// Local XAMPP: use api/config.local.php (see config.example.php).
// Production: set real env vars on your host (Render/Railway/VPS). GitHub Pages CANNOT run this file.

header('Content-Type: application/json; charset=utf-8');

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

// ---- build message ----
$safeBody = str_replace('.', '..', $message); // dot-stuffing guard handled again at send
$headers = [
  'From: "' . addcslashes(mb_substr($name, 0, 60), '"') . '" <' . $user . '>',
  'Reply-To: ' . $email,
  'To: ' . $to,
  'Subject: [Portfolio] ' . mb_substr($subject, 0, 140),
  'MIME-Version: 1.0',
  'Content-Type: text/plain; charset=UTF-8',
  'X-Mailer: portfolio-php-smtp',
];
$body = "Name: {$name}\nEmail: {$email}\nSubject: {$subject}\nIP: {$ip}\n\n{$message}\n";

// ---- send via Gmail SMTP ----
$err = smtp_send('smtp.gmail.com', 587, $user, $pass, $user, $to, implode("\r\n", $headers), $body);
if ($err !== true) {
  error_log('portfolio smtp: ' . $err); // server log only, no secrets
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

function smtp_read($fp): array {
  $out = '';
  while (($line = fgets($fp, 1024)) !== false) {
    $out .= $line;
    if (preg_match('/^\d{3} /', $line)) break;
  }
  return [(int)substr($out, 0, 3), $out];
}

function smtp_cmd($fp, string $cmd, array $want): array {
  if ($cmd !== '') fwrite($fp, $cmd . "\r\n");
  [$code, $text] = smtp_read($fp);
  if (!in_array($code, $want, true)) return [false, "Unexpected reply {$code}: " . mb_substr(trim($text), 0, 120)];
  return [true, $text];
}

/** @return true|string true on success, error string (sanitized) on failure */
function smtp_send(string $host, int $port, string $user, string $pass, string $from, string $to, string $headers, string $body) {
  $fp = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 15);
  if (!$fp) return 'Connection failed.';
  stream_set_timeout($fp, 15);

  [$code] = smtp_read($fp);
  if ($code !== 220) { fclose($fp); return 'SMTP greeting failed.'; }

  [$ok, $e] = smtp_cmd($fp, 'EHLO portfolio', [250]);
  if (!$ok) { fclose($fp); return $e; }
  [$ok, $e] = smtp_cmd($fp, 'STARTTLS', [220]);
  if (!$ok) { fclose($fp); return $e; }
  if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
    fclose($fp); return 'TLS negotiation failed.';
  }
  [$ok, $e] = smtp_cmd($fp, 'EHLO portfolio', [250]);
  if (!$ok) { fclose($fp); return $e; }
  [$ok, $e] = smtp_cmd($fp, 'AUTH LOGIN', [334]);
  if (!$ok) { fclose($fp); return $e; }
  [$ok, $e] = smtp_cmd($fp, base64_encode($user), [334]);
  if (!$ok) { fclose($fp); return 'Authentication rejected (check user/app password).'; }
  [$ok, $e] = smtp_cmd($fp, base64_encode($pass), [235]);
  if (!$ok) { fclose($fp); return 'Authentication rejected (check user/app password).'; }
  [$ok, $e] = smtp_cmd($fp, "MAIL FROM:<{$from}>", [250]);
  if (!$ok) { fclose($fp); return $e; }
  [$ok, $e] = smtp_cmd($fp, "RCPT TO:<{$to}>", [250, 251]);
  if (!$ok) { fclose($fp); return $e; }
  [$ok, $e] = smtp_cmd($fp, 'DATA', [354]);
  if (!$ok) { fclose($fp); return $e; }

  $stuffed = preg_replace('/^\./m', '..', $body);
  fwrite($fp, $headers . "\r\n\r\n" . $stuffed . "\r\n.\r\n");
  [$code2, $text2] = smtp_read($fp);
  fwrite($fp, "QUIT\r\n");
  fclose($fp);
  if ($code2 !== 250) return 'Message refused by mail server.';
  return true;
}
