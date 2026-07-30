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

$registerBtn = isLoggedIn()
    ? ''
    : '<a class="btn btn-outline" href="register.php">Create account</a>';

$content = view('index.html', [
    'register_btn' => raw($registerBtn),
    'cards'        => raw($cards),
]);

render_page('Home', $content);
