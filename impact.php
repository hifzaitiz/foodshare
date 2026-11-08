<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

// Community impact: everything here is calculated from existing tables
// (food_posts, claims, users, ngos, post_status_logs); nothing extra is stored.
$pdo = db();

$totals = $pdo->query(
    "SELECT COUNT(*) AS posts,
            COALESCE(SUM(status = 'collected'), 0) AS collected,
            COALESCE(SUM(status = 'claimed'), 0) AS claimed,
            COALESCE(SUM(status = 'available' AND pickup_until > NOW()), 0) AS open_now,
            COALESCE(SUM(status = 'available' AND pickup_until <= NOW()), 0) AS expired,
            COALESCE(SUM(status = 'withdrawn'), 0) AS withdrawn,
            COUNT(DISTINCT donor_id) AS donors
     FROM food_posts"
)->fetch();

$people = $pdo->query(
    "SELECT (SELECT COUNT(*) FROM ngos WHERE is_verified = 1) AS ngos,
            (SELECT COUNT(*) FROM users WHERE role = 'volunteer') AS volunteers"
)->fetch();

// Amount of food collected, per unit (plates, kg, packs...)
$saved = $pdo->query(
    "SELECT unit, SUM(quantity) AS qty FROM food_posts WHERE status = 'collected'
     GROUP BY unit ORDER BY qty DESC"
)->fetchAll();
$plates = 0;
foreach ($saved as $s) {
    if ($s['unit'] === 'plates') {
        $plates = (int) $s['qty'];
    }
}

// Average minutes from posting to the first claim, using the status history.
$avgMinutes = $pdo->query(
    "SELECT AVG(TIMESTAMPDIFF(MINUTE, posted.at, claimed.at))
     FROM (SELECT post_id, MIN(changed_at) AS at FROM post_status_logs WHERE old_status IS NULL GROUP BY post_id) posted
     JOIN (SELECT post_id, MIN(changed_at) AS at FROM post_status_logs WHERE new_status = 'claimed' GROUP BY post_id) claimed
       ON claimed.post_id = posted.post_id"
)->fetchColumn();
$avgLabel = $avgMinutes === null ? '–'
    : ($avgMinutes < 60 ? round($avgMinutes) . ' min' : round($avgMinutes / 60, 1) . ' h');

// Success rate: of the posts that are finished, how many were collected (withdrawn ones don't count).
$finished    = (int) $totals['collected'] + (int) $totals['expired'];
$successRate = $finished > 0 ? round(100 * (int) $totals['collected'] / $finished) : null;

// Chart 1: activity over the last 14 days (posted vs collected), from post_status_logs.
$rows = $pdo->query(
    "SELECT DATE(changed_at) AS d,
            SUM(old_status IS NULL) AS posted,
            SUM(new_status = 'collected') AS collected
     FROM post_status_logs
     WHERE changed_at >= CURDATE() - INTERVAL 13 DAY
     GROUP BY DATE(changed_at)"
)->fetchAll();
$byDay = [];
foreach ($rows as $r) {
    $byDay[$r['d']] = $r;
}
$days = $postedSeries = $collectedSeries = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $days[]            = date('d M', strtotime($d));
    $postedSeries[]    = (int) ($byDay[$d]['posted'] ?? 0);
    $collectedSeries[] = (int) ($byDay[$d]['collected'] ?? 0);
}

// Chart 2: posts per food category.
$categories = $pdo->query(
    "SELECT COALESCE(c.name, 'Other') AS name, COUNT(*) AS n
     FROM food_posts p LEFT JOIN food_categories c ON c.id = p.category_id
     GROUP BY c.name ORDER BY n DESC"
)->fetchAll();

// Leaderboards: top donors and top collectors.
$topDonors = $pdo->query(
    "SELECT COALESCE(u.organization_name, u.full_name) AS name, COUNT(*) AS posts,
            SUM(p.status = 'collected') AS collected
     FROM food_posts p JOIN users u ON u.id = p.donor_id
     GROUP BY u.id ORDER BY collected DESC, posts DESC LIMIT 5"
)->fetchAll();
$topCollectors = $pdo->query(
    "SELECT name, COUNT(*) AS pickups
     FROM (SELECT COALESCE(n.name, u.full_name) AS name
           FROM claims c JOIN users u ON u.id = c.claimed_by LEFT JOIN ngos n ON n.id = c.ngo_id
           WHERE c.collected_at IS NOT NULL) AS done
     GROUP BY name ORDER BY pickups DESC LIMIT 5"
)->fetchAll();

$chartData = [
    'days'      => $days,
    'posted'    => $postedSeries,
    'collected' => $collectedSeries,
    'catLabels' => array_column($categories, 'name'),
    'catCounts' => array_map('intval', array_column($categories, 'n')),
    'outcome'   => [
        'labels' => ['Collected', 'Claimed', 'Open now', 'Expired', 'Withdrawn'],
        'values' => [(int) $totals['collected'], (int) $totals['claimed'], (int) $totals['open_now'],
                     (int) $totals['expired'], (int) $totals['withdrawn']],
    ],
];

$kpis = [
    ['Plates saved', number_format($plates), 'utensils', 'amber', 'plates of cooked food collected'],
    ['Pickups completed', (int) $totals['collected'], 'check-circle', 'green', 'posts marked collected'],
    ['Success rate', $successRate === null ? '–' : $successRate . '%', 'leaf', 'blue', 'of finished posts were collected'],
    ['Avg. time to claim', $avgLabel, 'clock', 'slate', 'from posting to first claim'],
];

$pageTitle = 'Impact';
require __DIR__ . '/includes/header.php';
?>
<main class="content">
    <header class="page-head">
        <div>
            <p class="eyebrow"><?= icon('leaf') ?> Community impact</p>
            <h1>Every plate counts.</h1>
            <p class="muted">Live numbers from everyone on Save &amp; Serve: <?= plural((int) $totals['donors'], 'donor') ?>, <?= plural((int) $people['ngos'], 'verified NGO') ?> and <?= plural((int) $people['volunteers'], 'volunteer') ?>.</p>
        </div>
    </header>

    <section class="stats">
        <?php foreach ($kpis as [$label, $value, $ico, $tone, $hint]): ?>
        <div class="stat" title="<?= e($hint) ?>">
            <span class="stat-icon tone-<?= $tone ?>"><?= icon($ico) ?></span>
            <div>
                <span class="stat-label"><?= e($label) ?></span>
                <strong class="stat-value"><?= e((string) $value) ?></strong>
            </div>
        </div>
        <?php endforeach; ?>
    </section>

    <div class="impact-grid">
        <section class="card chart-card chart-wide">
            <div class="card-head">
                <div>
                    <h2>Last 14 days</h2>
                    <p class="muted small">Food posted vs food collected each day.</p>
                </div>
            </div>
            <div class="chart-box"><canvas id="activityChart" aria-label="Posted and collected per day"></canvas></div>
        </section>

        <section class="card chart-card">
            <h2>What gets shared</h2>
            <p class="muted small">Posts by food category.</p>
            <div class="chart-box chart-box-round"><canvas id="categoryChart" aria-label="Posts by category"></canvas></div>
        </section>

        <section class="card chart-card">
            <h2>What happens to posts</h2>
            <p class="muted small">Current outcome of every post.</p>
            <div class="chart-box"><canvas id="outcomeChart" aria-label="Post outcomes"></canvas></div>
        </section>

        <section class="card">
            <h2>Top donors</h2>
            <p class="muted small">Ranked by food collected, then posts.</p>
            <ol class="leaderboard">
                <?php foreach ($topDonors as $i => $d): ?>
                <li>
                    <span class="rank rank-<?= $i + 1 ?>"><?= $i + 1 ?></span>
                    <span class="lb-name"><?= e($d['name']) ?></span>
                    <span class="lb-score"><strong><?= (int) $d['collected'] ?></strong> collected · <?= (int) $d['posts'] ?> posts</span>
                </li>
                <?php endforeach; ?>
                <?php if (!$topDonors): ?><li class="muted">No posts yet.</li><?php endif; ?>
            </ol>
        </section>

        <section class="card">
            <h2>Top collectors</h2>
            <p class="muted small">NGOs and volunteers with the most completed pickups.</p>
            <ol class="leaderboard">
                <?php foreach ($topCollectors as $i => $c): ?>
                <li>
                    <span class="rank rank-<?= $i + 1 ?>"><?= $i + 1 ?></span>
                    <span class="lb-name"><?= e($c['name']) ?></span>
                    <span class="lb-score"><strong><?= (int) $c['pickups'] ?></strong> pickup<?= (int) $c['pickups'] === 1 ? '' : 's' ?></span>
                </li>
                <?php endforeach; ?>
                <?php if (!$topCollectors): ?><li class="muted">No completed pickups yet.</li><?php endif; ?>
            </ol>
        </section>
    </div>
</main>

<!-- Chart data as JSON (escaped so names can never break out of the script tag) -->
<script type="application/json" id="impact-data"><?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="assets/js/vendor/chart.umd.min.js"></script>
<script src="assets/js/impact.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
