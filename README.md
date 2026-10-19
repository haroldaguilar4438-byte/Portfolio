# Aguilar Harold R — Industry-Ready Portfolio

Premium single-page portfolio for a BSIT / CS student preparing for internship and junior roles.
Vanilla HTML + CSS + JS. No dependencies. Fast, responsive, accessible.

## Project showcase
- Project images live in `assets/projects/` (e.g. `eduportal.png`). The `<img class="pf-shot">` in each card is plain HTML — no JS needed — and falls back to the wireframe if the file is missing.
- Refresh a screenshot any time (headless Chrome):
```powershell
& "C:\Program Files\Google\Chrome\Application\chrome.exe" --headless=new --disable-gpu --hide-scrollbars --window-size=1440,1000 --virtual-time-budget=12000 --screenshot="assets\projects\eduportal.png" "https://your-site-url"
```
- `▸ Live preview` opens the running site in a popup window. Use it instead of an iframe when the target sends `X-Frame-Options: SAMEORIGIN` (or a `frame-ancestors` CSP), which blocks embedding.
- Add/edit project entries in `js/content.js` → `projects`. Reserved entries stay hidden while `published: false`.

## Mock interview video
- Files: `assets/video/mock-interview.mp4` + `mock-interview-poster.jpg`
- The MP4 is **compressed to ~2.2 Mbps H.264** so the page stays fast. If you replace it with a new recording, compress it first — a raw phone recording can be 50 MB+ and will make the page slow:
  - HandBrake (free) → preset "Fast 720p30", or
  - Clipchamp (built into Windows) → Export → 1080p / Medium quality
- Keep the same filenames and no code changes are needed. Keep it under ~10 MB if you can.

## Contact form
- Posts to `api/contact.php` (PHP required — GitHub Pages is static, so the live site on Pages cannot run it).
- Two transports, auto-selected: HTTPS mail API (`RESEND_API_KEY`, port 443) first, then Gmail SMTP via bundled PHPMailer.
- Copy `api/config.example.php` → `api/config.local.php` and fill it in. `config.local.php` is gitignored: **never upload it.**

## Run locally
Just open `index.html`, or serve for correct font/CORS behavior:
```powershell
python -m http.server 8000
# then visit http://localhost:8000
```

## Customize (5 minutes)
1. **Personal info:** edit `js/content.js` → `PORTFOLIO.personal` (name, title, email, GitHub, LinkedIn, location).
2. **Search-replace placeholders** in `index.html`:
   - `[YOUR NAME]`, `[YOUR PROFESSIONAL TITLE]`, `[YOUR EMAIL]`
   - `[YOUR GITHUB URL]`, `[YOUR LINKEDIN URL]`, `[YOUR LOCATION]`
   - `[PROJECT 1 TITLE]`, `[PROJECT DESCRIPTION]`, `[GITHUB REPO URL]`, `[LIVE DEMO URL]`
3. **Skills:** edit the Skills section in `index.html` (or `js/content.js` categories). Use only real tech. Levels: Familiar / Beginner / Intermediate / Advanced.
4. **Photo:** replace the `.avatar` div in the hero with `<img src="assets/profile.jpg" alt="Professional headshot of [YOUR NAME]">`.
5. **Resume:** export your resume as PDF → save to `assets/resume.pdf` (overwrite the placeholder). Keep filename professional when sending: `Firstname-Lastname-Resume.pdf`.
6. **Projects:** for each project replace Problem/Solution/Stack/Architecture/Challenges + add real screenshots under `assets/` and link them.
7. **Problem-solving (5 cases), Security case, AI Usage Log table:** replace with real cases. Never invent metrics.

## Structure
```
index.html      — all sections (Home → Contact)
css/style.css   — design system (colors, spacing, responsive)
js/content.js   — single config for personal info + skills
js/main.js      — nav, mobile menu, reveal, accordion, form validation
assets/         — profile photo + resume.pdf go here
```

## Before you present
- [ ] No `[BRACKETS]` left except intentional TBD slots
- [ ] All GitHub / demo links open and are real
- [ ] Resume PDF is yours and prints to one page
- [ ] Test on mobile (375px), tablet, desktop — no horizontal scroll
- [ ] Tab through with keyboard; form shows errors/success
- [ ] No console errors (F12 → Console)
