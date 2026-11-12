/*
 * Devesh Studio – the main website's header behaviour on the eAlbum pages (copied from the website's js/site.js):
 * the mobile menu, and the banner picture that drifts a little under the mouse.
 */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Mobile menu: the round button opens it; a link, Escape or the button again closes it.
    var menuBtn = document.querySelector('.menu-btn');
    var menu = document.getElementById('mobile-menu');
    if (menuBtn && menu) {
        var setMenu = function (open) {
            menuBtn.setAttribute('aria-expanded', String(open));
            menu.hidden = !open;
        };
        menuBtn.addEventListener('click', function () { setMenu(menu.hidden); });
        menu.addEventListener('click', function (event) { if (event.target.closest('a')) setMenu(false); });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !menu.hidden) { setMenu(false); menuBtn.focus(); }
        });
    }

    // Banner picture: the mouse's place over it (-1 to 1 across and down) sets --mx and --my, which move the photo
    // and the orange circle behind it a little in opposite directions; leaving puts them back. Not on touch screens.
    if (!reduceMotion && window.matchMedia && window.matchMedia('(hover: hover)').matches) {
        Array.prototype.forEach.call(document.querySelectorAll('[data-drift]'), function (art) {
            art.addEventListener('pointermove', function (event) {
                var r = art.getBoundingClientRect();
                art.style.setProperty('--mx', (((event.clientX - r.left) / r.width) * 2 - 1).toFixed(3));
                art.style.setProperty('--my', (((event.clientY - r.top) / r.height) * 2 - 1).toFixed(3));
            });
            art.addEventListener('pointerleave', function () {
                art.style.setProperty('--mx', '0');
                art.style.setProperty('--my', '0');
            });
        });
    }
})();
