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
      status.className = "form-status sending";
      status.textContent = "Sending your message…";
      try {
        // Real delivery via own backend. Success is shown ONLY when the backend
        // confirms the mail service accepted the message.
        let sent = false, notConfigured = false, serverMsg = "";
        try {
          const r = await fetch("api/contact.php", {
            method: "POST",
            headers: { "Content-Type": "application/json", Accept: "application/json" },
            body: JSON.stringify({ name, email, subject, message, _honey: form._honey ? form._honey.value : "" })
          });
          const j = await r.json().catch(() => ({}));
          if (r.ok && j && j.ok) {
            sent = true;
          } else if (r.status === 501) {
            notConfigured = true;
          } else if (r.status === 422) {
            status.className = "form-status err";
            status.textContent = (j && j.error) || "Please check the highlighted fields.";
            return;
          } else if (r.status === 429) {
            status.className = "form-status err";
            status.textContent = "Too many messages from this device. Please try again later.";
            return;
          } else {
            serverMsg = (j && j.error) || "";
            throw new Error(serverMsg || "mail service error");
          }
        } catch (bErr) {
          const msg = String((bErr && bErr.message) || "");
          if (msg.includes("Failed to fetch") || msg.includes("NetworkError") || msg.includes("Load failed")) {
            notConfigured = true; // static host with no PHP backend
          } else {
            throw bErr;
          }
        }
        if (!sent) {
          if (notConfigured) {
            status.className = "form-status err";
            status.textContent = "The mail service isn't available on this host yet. Please email aguilarhrld@gmail.com directly — I reply to every message.";
            return;
          }
          throw new Error(serverMsg || "send failed");
        }
        status.className = "form-status ok";
        status.textContent = "Message sent — it was accepted for delivery. I'll reply from aguilarhrld@gmail.com.";
        form.reset();
      } catch (err) {
        const detail = String((err && err.message) || "");
        status.className = "form-status err";
        if (detail && !detail.includes("Failed to fetch")) {
          status.textContent = detail + " You can also email aguilarhrld@gmail.com directly.";
        } else {
          status.textContent = "Network problem — the message was not sent. Please try again, or email aguilarhrld@gmail.com directly.";
        }
        } finally {
        if (sendBtn) { sendBtn.disabled = false; sendBtn.textContent = "Send Message →"; }
      }
    });
    // Live clear
    form.querySelectorAll("input,textarea").forEach((el) =>
      el.addEventListener("input", () => el.closest(".field")?.classList.remove("invalid"))
    );
  }

  // Optional project screenshots — only injected when a real file is configured
  // in js/content.js, so no 404s and no fabricated imagery.
  const proj = (window.PORTFOLIO && window.PORTFOLIO.projects) || [];
  const featured = proj.find((p) => p && p.published && p.screenshot);
  const media = document.querySelector("#projects .project-featured .pf-media");
  if (featured && media) {
    const img = document.createElement("img");
    img.className = "pf-shot";
    img.alt = featured.title + " — live interface screenshot";
    img.loading = "lazy";
    img.decoding = "async";
    img.width = 1440;
    img.height = 1000;
    img.addEventListener("load", () => media.classList.add("has-shot")); // hide wireframe fallback
    img.addEventListener("error", () => img.remove()); // no image = keep the neutral wireframe
    img.src = featured.screenshot;
    media.insertBefore(img, media.firstChild);
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
