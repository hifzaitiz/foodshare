// Draws the charts on impact.php from the JSON embedded in the page.
(function () {
    'use strict';

    var dataEl = document.getElementById('impact-data');
    if (!dataEl || typeof Chart === 'undefined') {
        return;
    }
    var data = JSON.parse(dataEl.textContent);

    // Match the site's warm editorial style: flat fills, no outlines.
    var ink = '#1f1a16';
    var palette = ['#e8613c', '#2f6b4f', '#f2b45a', '#7fa7c9', '#c8a2d4', '#e9a08a', '#9cc5a8'];
    Chart.defaults.font.family = '"DM Sans", system-ui, sans-serif';
    Chart.defaults.font.size = 13;
    Chart.defaults.color = '#7d7166';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.tooltip.backgroundColor = ink;
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        Chart.defaults.animation = false;
    }

    var grid = { color: 'rgba(31, 26, 22, .06)' };
    var whole = { precision: 0 };   // counts are whole numbers

    new Chart(document.getElementById('activityChart'), {
        type: 'bar',
        data: {
            labels: data.days,
            datasets: [
                { label: 'Posted', data: data.posted, backgroundColor: '#f2b45a', borderRadius: 8, maxBarThickness: 22 },
                { label: 'Collected', data: data.collected, backgroundColor: '#2f6b4f', borderRadius: 8, maxBarThickness: 22 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: whole, grid: grid }, x: { grid: { display: false } } },
            plugins: { legend: { position: 'top' } }
        }
    });

    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: data.catLabels,
            datasets: [{ data: data.catCounts, backgroundColor: palette, borderColor: '#ffffff', borderWidth: 3, hoverOffset: 8 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '55%', plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('outcomeChart'), {
        type: 'bar',
        data: {
            labels: data.outcome.labels,
            datasets: [{
                data: data.outcome.values,
                backgroundColor: ['#2f6b4f', '#f2b45a', '#7fa7c9', '#d8cfc4', '#e9a08a'],
                borderRadius: 8, maxBarThickness: 26
            }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            scales: { x: { beginAtZero: true, ticks: whole, grid: grid }, y: { grid: { display: false } } },
            plugins: { legend: { display: false } }
        }
    });
})();
