// Plain vanilla for now — see the note in accordion.js. Ported from
// zaunprofi's assets/js/swiper.js "hero-slider" behavior.
import Swiper from "swiper";
import { A11y, Autoplay, EffectFade, Navigation } from "swiper/modules";

const prefersReducedMotion = window.matchMedia(
    "(prefers-reduced-motion: reduce)",
).matches;

document.querySelectorAll(".js-hero-slider").forEach((el) => {
    const slides = el.querySelectorAll(".swiper-slide");
    if (slides.length <= 1) return;

    new Swiper(el, {
        modules: [A11y, Autoplay, EffectFade, Navigation],
        loop: true,
        effect: "fade",
        fadeEffect: { crossFade: true },
        autoplay: prefersReducedMotion
            ? false
            : { delay: 5000, disableOnInteraction: true },
        pagination: false,
    });
});
