// Small touches that make the dashboard feel alive: live countdowns and count-up numbers.
(function () {
    'use strict';
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ---- Light / dark mode toggle (remembered per browser) ----
    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var root = document.documentElement;
            var current = root.getAttribute('data-theme') ||
                (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            var next = current === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            try { localStorage.setItem('theme', next); } catch (e) {}
            if (document.getElementById('impact-data')) { location.reload(); }   // charts read colours once
        });
    });

    // ---- Live countdowns: <span class="time-chip" data-until="ISO" [data-mode="claimed"]><span class="tl"> ----
    function label(secs, claimed) {
        if (secs <= 0) { return claimed ? 'Pickup overdue' : 'Pickup closed'; }
        var h = Math.floor(secs / 3600), m = Math.floor((secs % 3600) / 60);
        if (h >= 24) { return Math.floor(h / 24) + 'd ' + (h % 24) + 'h left'; }
        return h > 0 ? h + 'h ' + m + 'm left' : Math.max(m, 1) + 'm left';
    }
    function tone(secs) {
        return secs <= 0 ? 'urgent' : secs < 3600 ? 'urgent' : secs < 3 * 3600 ? 'soon' : 'ok';
    }
    function tick() {
        document.querySelectorAll('[data-until]').forEach(function (el) {
            var until = Date.parse(el.getAttribute('data-until'));
            if (isNaN(until)) { return; }
            var secs = Math.round((until - Date.now()) / 1000);
            // How much of the pickup window is left (0..1), drawn as a draining ring by the CSS.
            var from = Date.parse(el.getAttribute('data-from') || '');
            var total = !isNaN(from) && until > from ? (until - from) / 1000 : 4 * 3600;
            el.style.setProperty('--pct', Math.max(0, Math.min(1, secs / total)).toFixed(3));
            var text = el.querySelector('.tl') || el;
            text.textContent = label(secs, el.getAttribute('data-mode') === 'claimed');
            // An unclaimed post whose window has closed can no longer be collected: fade its card.
            if (secs <= 0 && el.getAttribute('data-mode') !== 'claimed') {
                var card = el.closest('.live-card, .food-card');
                if (card) { card.classList.add('is-closed'); }
            }
            el.classList.remove('t-ok', 't-soon', 't-urgent');
            el.classList.add('t-' + tone(secs));
        });
    }
    tick();
    setInterval(tick, 30000);

    // Clock on the homepage live board
    var clock = document.querySelector('[data-clock]');
    if (clock) {
        var showTime = function () { var d = new Date(); clock.textContent = ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); };
        showTime(); setInterval(showTime, 15000);
    }

    // ---- Homepage navbar: mobile menu and shadow after scrolling ----
    var nav = document.querySelector('[data-nav]');
    if (nav) {
        var toggle = nav.querySelector('[data-nav-toggle]');
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('nav-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        nav.querySelectorAll('.site-links a').forEach(function (a) {
            a.addEventListener('click', function () { nav.classList.remove('nav-open'); toggle.setAttribute('aria-expanded', 'false'); });
        });
        var onScroll = function () { nav.classList.toggle('scrolled', window.scrollY > 10); };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    // ---- Highlight the navbar link of the section being read ----
    var spyLinks = document.querySelectorAll('.site-links > a[href^="#"]');
    if (spyLinks.length && 'IntersectionObserver' in window) {
        var byId = {};
        spyLinks.forEach(function (a) { byId[a.getAttribute('href').slice(1)] = a; });
        var spy = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting && byId[en.target.id]) {
                    spyLinks.forEach(function (a) { a.classList.remove('is-current'); });
                    byId[en.target.id].classList.add('is-current');
                }
            });
        }, { rootMargin: '-45% 0px -50% 0px' });
        Object.keys(byId).forEach(function (id) { var el = document.getElementById(id); if (el) { spy.observe(el); } });
    }

    // ---- Fade sections in as they scroll into view (staggered within a row) ----
    var reveals = document.querySelectorAll('.scroll-reveal');
    if (reveals.length) {
        if (!('IntersectionObserver' in window) || reduceMotion) {
            reveals.forEach(function (el) { el.classList.add('is-visible'); });
        } else {
            var ro = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (!en.isIntersecting) { return; }
                    var siblings = Array.prototype.indexOf.call(en.target.parentNode.children, en.target);
                    en.target.style.transitionDelay = (Math.max(siblings, 0) % 4) * 90 + 'ms';
                    en.target.classList.add('is-visible');
                    ro.unobserve(en.target);
                });
            }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
            reveals.forEach(function (el) { ro.observe(el); });
        }
    }

    // ---- Claim confirmation sheet: check the details before committing to a pickup ----
    var claimForms = document.querySelectorAll('form.claim-form');
    if (claimForms.length && typeof HTMLDialogElement === 'function') {
        var sheet = document.createElement('dialog');
        sheet.className = 'sheet';
        sheet.setAttribute('aria-labelledby', 'sheet-title');
        sheet.innerHTML =
            '<p class="sheet-tag">Confirm your pickup</p>' +
            '<h2 id="sheet-title"></h2>' +
            '<dl class="sheet-facts"><div><dt>Quantity</dt><dd data-f="qty"></dd></div>' +
            '<div><dt>Pick up from</dt><dd data-f="place"></dd></div>' +
            '<div><dt>Collect before</dt><dd data-f="until"></dd></div></dl>' +
            '<p class="sheet-ask"></p>' +
            '<div class="sheet-actions"><button type="button" class="btn btn-outline" data-act="cancel">Not now</button>' +
            '<button type="button" class="btn btn-primary" data-act="ok">Yes, claim it</button></div>';
        document.body.appendChild(sheet);
        var pending = null;
        var fill = function (sel, text) { sheet.querySelector(sel).textContent = text; };
        claimForms.forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                pending = form;
                fill('#sheet-title', form.dataset.title);
                fill('[data-f="qty"]', form.dataset.qty);
                fill('[data-f="place"]', form.dataset.place);
                fill('[data-f="until"]', form.dataset.until);
                fill('.sheet-ask', 'Can you get there and collect it by ' + form.dataset.until + '? Only one person can claim it, so please only confirm if you can.');
                sheet.showModal();
                sheet.querySelector('[data-act="ok"]').focus();
            });
        });
        sheet.querySelector('[data-act="cancel"]').addEventListener('click', function () { sheet.close(); });
        sheet.addEventListener('click', function (e) { if (e.target === sheet) { sheet.close(); } });
        sheet.querySelector('[data-act="ok"]').addEventListener('click', function () {
            if (pending) { this.disabled = true; this.textContent = 'Claiming…'; pending.submit(); }   // form.submit() skips the submit event
        });
    }

    // ---- Post food: quick pickup-window chips fill both datetime fields ----
    var fromIn = document.getElementById('pickup_from'), untilIn = document.getElementById('pickup_until');
    if (fromIn && untilIn) {
        var local = function (d) {   // "2026-11-08T17:30" in the user's time zone
            var p = function (n) { return ('0' + n).slice(-2); };
            return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes());
        };
        document.querySelectorAll('[data-window]').forEach(function (chip) {
            chip.addEventListener('click', function () {
                var from = new Date(), until;
                from.setSeconds(0, 0);
                from.setMinutes(Math.ceil(from.getMinutes() / 5) * 5);   // round up to 5 minutes
                var w = chip.getAttribute('data-window');
                if (w === 'midnight') {
                    until = new Date(from); until.setHours(23, 59, 0, 0);
                } else if (w === 'tomorrow') {
                    from = new Date(); from.setDate(from.getDate() + 1); from.setHours(12, 0, 0, 0);
                    until = new Date(from); until.setHours(15, 0, 0, 0);
                } else {
                    until = new Date(from.getTime() + parseInt(w, 10) * 3600 * 1000);
                }
                fromIn.value = local(from);
                untilIn.value = local(until);
                document.querySelectorAll('[data-window]').forEach(function (c) { c.classList.toggle('is-on', c === chip); });
            });
        });
    }

    // ---- Count-up numbers: <strong data-count="753">753</strong> ----
    function countUp(el) {
        var target = parseInt(el.getAttribute('data-count'), 10);
        if (!target || reduceMotion) { return; }
        var start = null, duration = 900;
        function step(ts) {
            if (start === null) { start = ts; }
            var p = Math.min((ts - start) / duration, 1);
            var eased = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(target * eased).toLocaleString();
            if (p < 1) { requestAnimationFrame(step); }
        }
        el.textContent = '0';
        requestAnimationFrame(step);
    }
    var counters = document.querySelectorAll('[data-count]');
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) { countUp(en.target); io.unobserve(en.target); }
            });
        }, { threshold: 0.4 });
        counters.forEach(function (el) { io.observe(el); });
    } else {
        counters.forEach(countUp);
    }
})();
