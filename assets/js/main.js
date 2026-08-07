document.addEventListener("DOMContentLoaded", () => {
  const header = document.querySelector(".site-header");
  const menu = document.querySelector(".mobile-menu");
  if (header) {
    let lastScrollY = window.scrollY;
    window.addEventListener("scroll", () => {
      const currentScrollY = window.scrollY;
      const scrollingDown = currentScrollY > lastScrollY;
      const menuOpen = menu && menu.classList.contains("open");
      if (!menuOpen && scrollingDown && currentScrollY > header.offsetHeight) {
        header.classList.add("header-hidden");
      } else if (!scrollingDown) {
        header.classList.remove("header-hidden");
      }
      lastScrollY = currentScrollY;
    }, { passive: true });
  }

  const toggle = document.querySelector(".nav-toggle");
  if (toggle && menu) {
    toggle.addEventListener("click", () => {
      menu.classList.toggle("open");
      toggle.setAttribute("aria-expanded", menu.classList.contains("open"));
    });
    menu.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", () => menu.classList.remove("open"));
    });
  }

  const form = document.querySelector(".contact-form");
  if (form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    const status = form.querySelector(".form-status");
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (submitBtn) submitBtn.disabled = true;
      if (status) {
        status.textContent = "Anfrage wird gesendet …";
        status.classList.remove("is-success", "is-error");
      }
      try {
        const response = await fetch(form.action, {
          method: "POST",
          body: new FormData(form),
          headers: { Accept: "application/json" },
        });
        const result = await response.json();
        if (status) {
          status.textContent = result.message || (result.success ? "Danke für Ihre Anfrage!" : "Etwas ist schiefgelaufen.");
          status.classList.add(result.success ? "is-success" : "is-error");
        }
        if (result.success) form.reset();
      } catch (err) {
        if (status) {
          status.textContent = "Die Anfrage konnte nicht gesendet werden. Bitte rufen Sie uns an oder versuchen Sie es später erneut.";
          status.classList.add("is-error");
        }
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    });
  }
});
