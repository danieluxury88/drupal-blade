// Plain vanilla for now — no Drupal.behaviors/once wrapper, since this theme
// isn't wired into Drupal yet and the standalone preview.html has no
// Drupal/once globals to attach to. The Drupal integration step should wrap
// this in Drupal.behaviors (see zaunprofi's assets/js/accordion.js) so it
// re-attaches after AJAX-loaded content.
(function () {
    "use strict";

    document
        .querySelectorAll(".paragraph-accordion__item-trigger")
        .forEach((trigger) => {
            const panel = document.getElementById(
                trigger.getAttribute("aria-controls"),
            );
            if (!panel) return;

            trigger.addEventListener("click", () => {
                const isOpen = trigger.getAttribute("aria-expanded") === "true";
                trigger.setAttribute("aria-expanded", String(!isOpen));
                panel.classList.toggle("is-open", !isOpen);
            });
        });
})();
