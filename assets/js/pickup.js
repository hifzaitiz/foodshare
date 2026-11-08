// Draws the pickup QR code on post.php (uses the bundled qrcode-generator library).
(function () {
    'use strict';
    var box = document.querySelector('.qr-box[data-qr]');
    if (!box || typeof qrcode === 'undefined') {
        return;
    }
    var qr = qrcode(0, 'M');           // 0 = pick the smallest size that fits, M = medium error correction
    qr.addData(box.getAttribute('data-qr'));
    qr.make();
    box.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 2, scalable: true });
})();
