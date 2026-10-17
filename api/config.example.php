<?php
// Copy to config.local.php (gitignored — NEVER commit or upload this file) and fill your values.
// On a real host you can instead set these as environment variables and skip this file entirely.

return [
  // ---- Option A: HTTPS mail API (recommended — works on port 443, not blocked by antivirus) ----
  // Create a free account at resend.com, copy the API key, and verify your sending domain.
  'RESEND_API_KEY' => '',                 // e.g. re_xxxxxxxxxxxx
  'MAIL_FROM' => '',                       // e.g. "Harold Portfolio <portfolio@yourdomain.com>"

  // ---- Option B: Gmail SMTP (needs 2-Step Verification + an App password) ----
  'GMAIL_USER' => '',                      // you@gmail.com
  'GMAIL_APP_PASSWORD' => '',              // 16-char app password — never share or commit

  // ---- Where messages land ----
  'MAIL_TO' => 'aguilarhrld@gmail.com',    // your inbox
];