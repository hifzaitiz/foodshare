// Small touches that make the dashboard feel alive: live countdowns and count-up numbers.
(function () {
    'use strict';
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

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
            var text = el.querySelector('.tl') || el;
            text.textContent = label(secs, el.getAttribute('data-mode') === 'claimed');
            el.classList.remove('t-ok', 't-soon', 't-urgent');
            el.classList.add('t-' + tone(secs));
        });
    }
    tick();
    setInterval(tick, 30000);

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
