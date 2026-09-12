// Plain vanilla for now — see the note in accordion.js. Opens/closes the
// full-screen mobile nav declared in templates/layout/header.html.twig.
(function () {
    "use strict";

    const toggle = document.getElementById("main-nav-toggle");
    const nav = document.getElementById("main-nav-wrapper");
    if (!toggle || !nav) return;

    toggle.addEventListener("click", () => {
        const isOpen = toggle.getAttribute("aria-expanded") === "true";
        toggle.setAttribute("aria-expanded", String(!isOpen));
        nav.setAttribute("aria-expanded", String(!isOpen));
        document.body.classList.toggle("no-scroll", !isOpen);
    });
})();
