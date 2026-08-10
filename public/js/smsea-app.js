/* SMSEA Office — app shell interactions (vanilla, no framework) */
(function () {
    'use strict';

    var body = document.body;
    var DESKTOP = '(min-width: 992px)';
    var isDesktop = function () { return window.matchMedia(DESKTOP).matches; };

    // Restore collapsed state (desktop only) before paint is handled inline in the layout head.
    var toggle = document.querySelector('[data-sidebar-toggle]');
    if (toggle) {
        toggle.addEventListener('click', function () {
            if (isDesktop()) {
                var collapsed = body.classList.toggle('sidebar-collapsed');
                try { localStorage.setItem('smsea.sidebar', collapsed ? 'collapsed' : 'expanded'); } catch (e) {}
            } else {
                body.classList.toggle('sidebar-open');
            }
        });
    }

    var backdrop = document.querySelector('.sidebar-backdrop');
    if (backdrop) {
        backdrop.addEventListener('click', function () { body.classList.remove('sidebar-open'); });
    }
    // Close mobile drawer when a nav link is tapped.
    document.querySelectorAll('.app-sidebar .nav-item-link').forEach(function (a) {
        a.addEventListener('click', function () { if (!isDesktop()) body.classList.remove('sidebar-open'); });
    });
    // Reset drawer state on resize to desktop.
    window.addEventListener('resize', function () { if (isDesktop()) body.classList.remove('sidebar-open'); });

    // Auto-dismiss toasts.
    document.querySelectorAll('.app-toast[data-autohide]').forEach(function (t) {
        var close = function () { t.style.transition = 'opacity .2s'; t.style.opacity = '0'; setTimeout(function () { t.remove(); }, 220); };
        var btn = t.querySelector('.t-close');
        if (btn) btn.addEventListener('click', close);
        setTimeout(close, 5200);
    });

    // Loading state on submit — prevents duplicate submissions for slow actions
    // (Smart Paste, PDF, email). Buttons opting out use data-no-loading.
    document.querySelectorAll('form[data-loading]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('button[type="submit"]:not([data-no-loading]), button:not([type]):not([data-no-loading])');
            if (btn && !btn.disabled) {
                btn.classList.add('is-loading');
                // Re-enable if the browser restores the page from bfcache.
                setTimeout(function () {}, 0);
            }
        });
    });
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            document.querySelectorAll('.btn.is-loading').forEach(function (b) { b.classList.remove('is-loading'); });
        }
    });
})();
