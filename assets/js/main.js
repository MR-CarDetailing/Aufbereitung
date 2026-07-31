document.addEventListener("DOMContentLoaded", () => {
  const toggle = document.querySelector(".nav-toggle");
  const menu = document.querySelector(".mobile-menu");
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
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const data = new FormData(form);
      const subject = encodeURIComponent(`Anfrage über die Website – ${data.get("service") || "Aufbereitung"}`);
      const bodyLines = [
        `Name: ${data.get("name") || ""}`,
        `Telefon: ${data.get("phone") || ""}`,
        `Fahrzeug: ${data.get("vehicle") || ""}`,
        `Gewünschte Leistung: ${data.get("service") || ""}`,
        "",
        data.get("message") || "",
      ];
      const body = encodeURIComponent(bodyLines.join("\n"));
      window.location.href = `mailto:${form.dataset.mailto}?subject=${subject}&body=${body}`;
    });
  }
});
