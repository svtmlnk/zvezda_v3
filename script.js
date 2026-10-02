// --- Подсветка активной ссылки в меню по текущей секции ---
const navLinks = [...document.querySelectorAll(".nav__link")];
const sections = navLinks.map((link) =>
  document.querySelector(link.getAttribute("href")),
);

function updateActiveLink() {
  const line = window.innerHeight * 0.4;
  let activeIndex = 0;

  sections.forEach((section, index) => {
    if (section.getBoundingClientRect().top <= line) activeIndex = index;
  });

  // В самом низу страницы всегда подсвечиваем последний пункт
  const isPageBottom =
    window.innerHeight + window.scrollY >= document.body.scrollHeight - 4;
  if (isPageBottom) activeIndex = sections.length - 1;

  navLinks.forEach((link, index) => {
    link.classList.toggle("nav__link--active", index === activeIndex);
  });
}

window.addEventListener("scroll", updateActiveLink, { passive: true });
updateActiveLink();

// --- Отправка формы (заглушка: данные никуда не уходят) ---
const form = document.getElementById("contact-form");
const success = document.getElementById("contact-success");
const failure = document.getElementById("contact-failure");
const contact__subtitle = document.querySelector(".contact__subtitle");
const loader = document.querySelector(".loader");

form.addEventListener("submit", (event) => {
  event.preventDefault();

  const form = event.target;
  const formData = new FormData(form);

  // Преобразуем данные формы в обычный объект
  const object = {};
  formData.forEach((value, key) => (object[key] = value));
  const json = JSON.stringify(object);

  form.classList.add("form--hidden");
  contact__subtitle.style.display = "none";
  loader.style.display = "inline-block";

  fetch("https://submit-form.com/msRoBphP3", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: json,
  })
    .then((response) => {
      if (response.ok) {
        success.classList.add("contact__success--visible");
      } else {
        failure.classList.add("contact__failure--visible");
      }

      loader.style.display = "none";
    })
    .catch((error) => {
      console.error("Ошибка:", error);
    });
});

// --- Мобильное меню ---
const header = document.querySelector(".site-header");
const nav = document.getElementById("site-nav");
const burger = document.querySelector(".burger");

function setMenu(open) {
  nav.classList.toggle("nav--open", open);
  burger.classList.toggle("burger--open", open);
  burger.setAttribute("aria-expanded", String(open));
  burger.setAttribute("aria-label", open ? "Закрыть меню" : "Открыть меню");
}

burger.addEventListener("click", () =>
  setMenu(!nav.classList.contains("nav--open")),
);

// Закрываем после выбора пункта
nav.addEventListener("click", (e) => {
  if (e.target.closest("a")) setMenu(false);
});

// Закрываем по тапу вне шапки и по Esc
document.addEventListener("click", (e) => {
  if (!header.contains(e.target)) setMenu(false);
});
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") setMenu(false);
});

// Сбрасываем состояние при переходе на десктопную ширину
window
  .matchMedia("(min-width: 901px)")
  .addEventListener("change", (e) => e.matches && setMenu(false));

// --- Интро-анимация: показываем логотип, затем открываем сайт ---
const intro = document.getElementById("intro");
const prefersReducedMotion = window.matchMedia(
  "(prefers-reduced-motion: reduce)",
).matches;

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const pageLoaded = new Promise((resolve) => {
  if (document.readyState === "complete") resolve();
  else window.addEventListener("load", resolve, { once: true });
});

// Ждём и проигрыш анимации, и загрузку страницы (но не дольше 5 секунд)
Promise.all([
  wait(prefersReducedMotion ? 300 : 1500),
  Promise.race([pageLoaded, wait(5000)]),
]).then(() => {
  intro.classList.add("intro--hide");
  document.documentElement.classList.remove("is-intro");
  setTimeout(() => intro.remove(), 1000);
});

// --- Анимация счётчиков в блоке статистики ---
const counters = document.querySelectorAll("[data-count]");

function renderCounter(el, value) {
  // Знаки после запятой берём из data-decimals,
  // а если его нет, считаем по записи числа в data-count (0.47 → 2)
  const decimals = Number(
    el.dataset.decimals ?? (el.dataset.count.split(".")[1] || "").length,
  );
  const number = value.toLocaleString("ru-RU", {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  });
  el.textContent = number + (el.dataset.suffix || "");
}

function animateCounter(el, duration = 2000) {
  const target = Number(el.dataset.count);
  const startTime = performance.now();

  function frame(now) {
    const progress = Math.min((now - startTime) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3); // замедление к концу
    renderCounter(el, target * eased);
    if (progress < 1) requestAnimationFrame(frame);
  }
  requestAnimationFrame(frame);
}

// Без анимации (reduce motion) оставляем итоговые значения из HTML
if (!prefersReducedMotion && "IntersectionObserver" in window) {
  counters.forEach((el) => renderCounter(el, 0));

  const counterObserver = new IntersectionObserver(
    (entries, observer) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        animateCounter(entry.target);
        observer.unobserve(entry.target); // запускаем один раз
      });
    },
    { threshold: 0.6 },
  );

  counters.forEach((el) => counterObserver.observe(el));
}

const v = document.querySelector(".hero__video");

if (v) {
  v.play().catch(() => {
    const start = () => {
      v.play().catch(() => {});
    };
    document.addEventListener("touchstart", start, {
      once: true,
      passive: true,
    });
  });
}

AOS.init({
  once: true,
});
