<?php
// Small presentation helpers shared by all pages: icons, initials, time-left labels, category styling.

// Inline SVG icons (stroke style, 24x24 grid). Usage in templates: echo icon('clock');
function icon(string $name, string $class = 'icon'): string
{
    static $paths = [
        'home'     => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'box'      => '<path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
        'pin'      => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/>',
        'check'    => '<path d="M5 12.5 10 17 19 7"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8 12.5 3 3 5-6"/>',
        'x'        => '<path d="M6 6l12 12M18 6 6 18"/>',
        'logout'   => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 17l-5-5 5-5M5 12h11"/>',
        'shield'   => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'filter'   => '<path d="M3 5h18l-7 8.5V20l-4-2v-4.5L3 5Z"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'utensils' => '<path d="M7 3v8a2 2 0 0 0 2 2v8M11 3v8a2 2 0 0 1-2 2M7 3v5M11 3v5"/><path d="M17 21V3c-2 1-3 4-3 7s1 4 3 4"/>',
        'heart'    => '<path d="M12 20s-8-4.7-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 9c0 6.3-8 11-8 11Z"/>',
        'building' => '<rect x="4" y="3" width="16" height="18" rx="1.5"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2M10 21v-3h4v3"/>',
        'inbox'    => '<path d="M3 13h5l1.5 3h5L16 13h5"/><path d="M5.5 5h13L21 13v6H3v-6L5.5 5Z"/>',
        'alert'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5v.01"/>',
        'truck'    => '<path d="M3 6h11v10H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'hand'     => '<path d="M7 11V6.5a1.5 1.5 0 0 1 3 0V11M10 10V4.5a1.5 1.5 0 0 1 3 0V10M13 10V5.5a1.5 1.5 0 0 1 3 0V12"/><path d="M16 9.5a1.5 1.5 0 0 1 3 0V14a7 7 0 0 1-7 7h-.5A6.5 6.5 0 0 1 6 18l-2.6-4.4a1.5 1.5 0 0 1 2.5-1.6L7 13.5"/>',
        'leaf'     => '<path d="M5 19c0-8 5-14 15-15-1 10-7 15-15 15Z"/><path d="M5 19 13 11"/>',
        'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'phone'    => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'lock'     => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'id'       => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2.5"/><path d="M5.5 17a3.5 3.5 0 0 1 7 0M15 10h3M15 14h3"/>',
    ];
    $d = $paths[$name] ?? $paths['box'];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
         . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

// "Spice Garden" -> "SG"
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out ?: '?';
}

// Human-friendly remaining time until a pickup window closes.
function time_left(?string $until): array
{
    if (!$until) {
        return ['-', 'muted'];
    }
    $secs = strtotime($until) - time();
    if ($secs <= 0) {
        return ['Pickup closed', 'over'];
    }
    $h = intdiv($secs, 3600);
    $m = intdiv($secs % 3600, 60);
    $label = $h >= 24 ? intdiv($h, 24) . 'd ' . ($h % 24) . 'h left' : ($h > 0 ? "{$h}h {$m}m left" : "{$m}m left");
    return [$label, $secs < 3600 ? 'urgent' : ($secs < 3 * 3600 ? 'soon' : 'ok')];
}

// Emoji + colour theme for each food category (falls back to a neutral style).
function category_style(?string $name): array
{
    $map = [
        'Cooked meals'              => ['🍛', 'cat-cooked'],
        'Bakery'                    => ['🥐', 'cat-bakery'],
        'Fruits & vegetables'       => ['🥕', 'cat-fresh'],
        'Packaged food'             => ['📦', 'cat-packaged'],
        'Wedding / event leftovers' => ['🎉', 'cat-event'],
    ];
    return $map[$name ?? ''] ?? ['🍽️', 'cat-default'];
}

function greeting(): string
{
    $h = (int) date('G');
    return $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');
}
