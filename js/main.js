/* Portfolio interactions — vanilla JS, no dependencies */
(function () {
  "use strict";
  const prefersReduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  // Footer year
  const year = document.getElementById("year");
  if (year) year.textContent = String(new Date().getFullYear());

  // Mobile menu
  const menuBtn = document.getElementById("menuBtn");
  const mobileMenu = document.getElementById("mobileMenu");
  if (menuBtn && mobileMenu) {
    menuBtn.addEventListener("click", () => {
      const open = mobileMenu.classList.toggle("open");
      menuBtn.setAttribute("aria-expanded", open ? "true" : "false");
    });
    mobileMenu.querySelectorAll("a").forEach((a) =>
      a.addEventListener("click", () => mobileMenu.classList.remove("open"))
    );
  }

  // Active section highlight
  const links = Array.from(document.querySelectorAll("[data-nav]"));
  const sections = links
    .map((a) => document.querySelector(a.getAttribute("href")))
    .filter(Boolean);
  if ("IntersectionObserver" in window && sections.length) {
    const obs = new IntersectionObserver(
      (entries) => {
        entries.forEach((e) => {
          if (e.isIntersecting) {
            const id = "#" + e.target.id;
            links.forEach((a) => a.classList.toggle("active", a.getAttribute("href") === id));
          }
        });
      },
      { rootMargin: "-40% 0px -55% 0px" }
    );
    sections.forEach((s) => obs.observe(s));
  }

  // Scroll reveal
  const revealEls = document.querySelectorAll(".reveal");
  if (prefersReduced || !("IntersectionObserver" in window)) {
    revealEls.forEach((el) => el.classList.add("visible"));
  } else {
    const robs = new IntersectionObserver(
      (entries) => entries.forEach((e) => {
        if (e.isIntersecting) { e.target.classList.add("visible"); robs.unobserve(e.target); }
      }),
      { threshold: 0.12 }
    );
    revealEls.forEach((el) => robs.observe(el));
  }

  // Accordion (problem-solving)
  document.querySelectorAll(".acc-item").forEach((item) => {
    const btn = item.querySelector(".acc-btn");
    if (!btn) return;
    btn.addEventListener("click", () => {
      const isOpen = item.classList.contains("open");
      document.querySelectorAll(".acc-item.open").forEach((o) => {
        o.classList.remove("open");
        const b = o.querySelector(".acc-btn");
        if (b) b.setAttribute("aria-expanded", "false");
      });
      if (!isOpen) {
        item.classList.add("open");
        btn.setAttribute("aria-expanded", "true");
      }
    });
  });

  // Terminal typing effect (subtle, skips if reduced motion)
  const termLines = document.getElementById("termLines");
  if (termLines && !prefersReduced) {
    const full = termLines.innerHTML;
    // Only animate caret line; keep it simple and fast to avoid annoyance
    const caretLine = document.getElementById("termTyping");
    if (caretLine) {
      const text = "$ npm run build — portfolio ready for review";
      caretLine.textContent = "";
      let i = 0;
      const tick = () => {
        if (i <= text.length) {
          caretLine.textContent = text.slice(0, i);
          i += 1;
          setTimeout(tick, 34);
        } else {
          const c = document.createElement("span");
          c.className = "caret";
          caretLine.appendChild(c);
        }
      };
      // Start when hero visible
      setTimeout(tick, 600);
    }
    void full;
  }

  // Contact form — true SMTP backend first (api/contact.php), FormSubmit fallback for static hosts
  const form = document.getElementById("contactForm");
  if (form) {
    const status = document.getElementById("formStatus");
    const sendBtn = document.getElementById("sendBtn");
    const setInvalid = (name, invalid) => {
      const field = form.querySelector(`[data-field="${name}"]`);
      if (field) field.classList.toggle("invalid", invalid);
    };
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const name = form.name.value.trim();
      const email = form.email.value.trim();
      const subject = form.subject.value.trim();
      const message = form.message.value.trim();
      let ok = true;
      setInvalid("name", !name); if (!name) ok = false;
      const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
      setInvalid("email", !emailOk); if (!emailOk) ok = false;
      setInvalid("subject", !subject); if (!subject) ok = false;
      setInvalid("message", !(message && message.length >= 10)); if (!(message && message.length >= 10)) ok = false;
      if (!ok) {
        status.className = "form-status err";
        status.textContent = "Please fix the highlighted fields (message needs at least 10 characters).";
        return;
      }
      if (sendBtn) { sendBtn.disabled = true; sendBtn.textContent = "Sending…"; }
      status.className = "form-status ok";
      status.textContent = "Sending…";
      try {
        // 1) True SMTP backend (same origin — keeps Gmail secrets server-side)
        let sent = false, backendMissing = false;
        try {
          const r = await fetch("api/contact.php", {
            method: "POST",
            headers: { "Content-Type": "application/json", Accept: "application/json" },
            body: JSON.stringify({ name, email, subject, message, _honey: form._honey ? form._honey.value : "" })
          });
          if (r.ok) {
            const j = await r.json().catch(() => ({}));
            if (j && j.ok) sent = true;
            else if (r.status === 501) backendMissing = true;
            else throw new Error((j && j.error) || "backend rejected");
          } else if (r.status === 501) backendMissing = true;
          else {
            const j = await r.json().catch(() => ({}));
            throw new Error((j && j.error) || "backend error");
          }
        } catch (bErr) {
          // Backend unreachable on static hosts (GitHub Pages) — fall through to FormSubmit
          if (String((bErr && bErr.message) || "").includes("Failed to fetch")) backendMissing = true;
          else if (!backendMissing) throw bErr;
        }
        // 2) Fallback for static hosting (no secrets in code)
        if (!sent) {
          const res = await fetch("https://formsubmit.co/ajax/aguilarhrld@gmail.com", {
            method: "POST",
            headers: { "Content-Type": "application/json", Accept: "application/json" },
            body: JSON.stringify({ name, email, subject, message, _subject: "Portfolio inquiry — Harold R. Aguilar", _template: "table" })
          });
          if (!res.ok) throw new Error("send failed");
        }
        status.className = "form-status ok";
        status.textContent = "Message sent. Thank you — I reply from aguilarhrld@gmail.com.";
        form.reset();
      } catch (err) {
        // Fallback: native POST (works even if fetch is blocked)
        status.className = "form-status err";
        status.textContent = "Direct send was blocked — submitting the classic way…";
        form.submit();
      } finally {
        if (sendBtn) { sendBtn.disabled = false; sendBtn.textContent = "Send Message →"; }
      }
    });
    // Live clear
    form.querySelectorAll("input,textarea").forEach((el) =>
      el.addEventListener("input", () => el.closest(".field")?.classList.remove("invalid"))
    );
  }

  // Back to top (only new global feature — everything else already existed)
  const toTop = document.getElementById("toTop");
  if (toTop) {
    const onScroll = () => toTop.classList.toggle("show", window.scrollY > 600);
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
    toTop.addEventListener("click", () => {
      window.scrollTo({ top: 0, behavior: prefersReduced ? "auto" : "smooth" });
    });
  }

  // Resume print (prints only resume paper)
  const printBtns = document.querySelectorAll("[data-print-resume]");
  printBtns.forEach((b) =>
    b.addEventListener("click", () => {
      alert("Tip: replace assets/resume.pdf with your real resume. This button opens the print view of the on-page resume preview.");
      window.print();
    })
  );
})();
