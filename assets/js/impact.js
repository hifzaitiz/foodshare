// Draws the charts on impact.php from the JSON embedded in the page.
(function () {
    'use strict';

    var dataEl = document.getElementById('impact-data');
    if (!dataEl || typeof Chart === 'undefined') {
        return;
    }
    var data = JSON.parse(dataEl.textContent);

    // Match the site's bold style: ink outlines, flat colour fills.
    var ink = '#1d1a17';
    var palette = ['#e8613c', '#b9efd2', '#ffe27a', '#c4e4ff', '#dccfff', '#ffcbc2', '#ffd8c4'];
    Chart.defaults.font.family = '"DM Sans", system-ui, sans-serif';
    Chart.defaults.font.size = 13;
    Chart.defaults.color = ink;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.tooltip.backgroundColor = ink;
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        Chart.defaults.animation = false;
    }

    var grid = { color: 'rgba(29, 26, 23, .08)' };
    var whole = { precision: 0 };   // counts are whole numbers

    new Chart(document.getElementById('activityChart'), {
        type: 'bar',
        data: {
            labels: data.days,
            datasets: [
                { label: 'Posted', data: data.posted, backgroundColor: palette[2], borderColor: ink, borderWidth: 2, borderRadius: 6 },
                { label: 'Collected', data: data.collected, backgroundColor: palette[0], borderColor: ink, borderWidth: 2, borderRadius: 6 }
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
            datasets: [{ data: data.catCounts, backgroundColor: palette, borderColor: ink, borderWidth: 2, hoverOffset: 10 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '55%', plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('outcomeChart'), {
        type: 'bar',
        data: {
            labels: data.outcome.labels,
            datasets: [{
                data: data.outcome.values,
                backgroundColor: ['#b9efd2', '#ffe27a', '#c4e4ff', '#ece6dd', '#ffcbc2'],
                borderColor: ink, borderWidth: 2, borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            scales: { x: { beginAtZero: true, ticks: whole, grid: grid }, y: { grid: { display: false } } },
            plugins: { legend: { display: false } }
        }
    });
})();
