// Pickup code page (post.php): draws the QR codes and runs the full-screen pickup pass.
(function () {
    'use strict';

    // Draw every QR box on the page (the card and the full-screen pass).
    if (typeof qrcode !== 'undefined') {
        document.querySelectorAll('.qr-box[data-qr]').forEach(function (box) {
            var qr = qrcode(0, 'M');   // 0 = smallest size that fits, M = medium error correction
            qr.addData(box.getAttribute('data-qr'));
            qr.make();
            box.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 2, scalable: true });
        });
    }

    // Full-screen pass: a <dialog>, and keep the screen awake while it is open (where supported).
    var pass = document.getElementById('pass');
    var opener = document.querySelector('[data-pass-open]');
    if (!pass || !opener || typeof pass.showModal !== 'function') {
        if (opener) { opener.hidden = true; }
        return;
    }
    var lock = null;
    opener.addEventListener('click', function () {
        pass.showModal();
        if (navigator.wakeLock) {
            navigator.wakeLock.request('screen').then(function (l) { lock = l; }).catch(function () {});
        }
    });
    pass.querySelector('[data-pass-close]').addEventListener('click', function () { pass.close(); });
    pass.addEventListener('close', function () { if (lock) { lock.release(); lock = null; } });
    pass.addEventListener('click', function (e) { if (e.target === pass) { pass.close(); } });   // click outside closes
})();
