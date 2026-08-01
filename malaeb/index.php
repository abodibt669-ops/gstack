<?php
// index.php — HOME (logic). Builds data, fills templates/index.html, prints page.
require_once __DIR__ . '/bootstrap.php';

$featured = $conn->query(
    "SELECT court_id, name, sport_type, location, price_per_hour
     FROM courts WHERE status = 'available' AND is_published = 1
     ORDER BY price_per_hour ASC LIMIT 3"
);

// build the featured cards by filling the court_card.html partial once per court
$cards = '';
while ($c = $featured->fetch_assoc()) {
    // view() escapes every value on its own now, so the page cannot break (or
    // be attacked) because someone named a court with a stray < in it.
    $cards .= view('partials/court_card.html', [
        'sport'    => $c['sport_type'],
        'name'     => $c['name'],
        'location' => $c['location'],
        'price'    => number_format((float)$c['price_per_hour'], 0),
        'action'   => raw('<a class="btn btn-dark btn-sm" href="booking.php?court_id='
                          . (int)$c['court_id'] . '">Book</a>'),
    ])->html;
}

// hero search: same sport list courts.php validates against, so the form can
// only ever submit a value the listing accepts.
$sportOptions = '';
foreach (SPORTS as $s) {
    $sportOptions .= '<option value="' . e($s) . '">' . e($s) . '</option>';
}

// Real numbers, counted from the catalogue on every load — never hard-coded.
// Sports and districts describe what a visitor can actually browse, so unlisted
// courts are left out; the starting price is a promise about what can be booked
// right now, so it counts published AND available only.
$stats = $conn->query(
    "SELECT
        (SELECT COUNT(DISTINCT sport_type) FROM courts WHERE is_published = 1)             AS sports,
        (SELECT COUNT(DISTINCT location)   FROM courts WHERE is_published = 1)             AS districts,
        (SELECT MIN(price_per_hour) FROM courts
          WHERE status = 'available' AND is_published = 1)                                 AS from_price"
)->fetch_assoc();

$content = view('index.html', [
    'sport_options' => raw($sportOptions),
    'stat_sports'   => (int)$stats['sports'],
    'stat_districts'=> (int)$stats['districts'],
    'stat_price'    => number_format((float)$stats['from_price'], 0),
    'cards'         => raw($cards),
]);

render_page('Home', $content);
