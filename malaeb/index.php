<?php
// index.php — HOME (logic). Builds data, fills templates/index.html, prints page.
require_once __DIR__ . '/bootstrap.php';

$featured = $conn->query(
    "SELECT court_id, name, sport_type, location, price_per_hour
     FROM courts WHERE status = 'available' ORDER BY price_per_hour ASC LIMIT 3"
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

// Numbers for the hero panel, counted at page load. Claims about how much
// choice we offer should come from the courts table, not from a designer's
// placeholder text, so they stay true as courts are added or removed.
//
// Sports and districts count every court we list, because that is the range we
// cover; a court being in maintenance this week does not mean we stopped
// covering tennis. The starting price counts only bookable courts, because that
// is a promise about what you can actually reserve right now.
$stats = $conn->query(
    "SELECT COUNT(DISTINCT sport_type) AS sports,
            COUNT(DISTINCT location)   AS areas,
            COALESCE(MIN(CASE WHEN status = 'available' THEN price_per_hour END), 0) AS from_price
     FROM courts"
)->fetch_assoc();

// The sport dropdown in the hero search. Same list, and the same query string,
// that courts.php already filters on.
$sportOptions = '';
foreach (SPORTS as $s) {
    $sportOptions .= '<option value="' . e($s) . '">' . e($s) . '</option>';
}

// One primary action in the hero (the search). Signing up is offered underneath
// as the quieter follow-on, and disappears once you are logged in.
$panelCta = isLoggedIn()
    ? '<p class="hero-panel-cta"><a href="my_bookings.php">Go to my bookings &rarr;</a></p>'
    : '<p class="hero-panel-cta">Booking takes a free account. '
      . '<a href="register.php">Create one in a minute &rarr;</a></p>';

$content = view('index.html', [
    'sport_options'   => raw($sportOptions),
    'stat_sports'     => $stats['sports'],
    'stat_areas'      => $stats['areas'],
    'stat_from_price' => number_format((float)$stats['from_price'], 0),
    'panel_cta'       => raw($panelCta),
    'cards'           => raw($cards),
]);

render_page('Home', $content);
