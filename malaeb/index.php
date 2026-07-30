<?php
// index.php — HOME (logic). Builds data, fills templates/index.html, prints page.
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/template.php';

$featured = $conn->query(
    "SELECT * FROM courts WHERE status = 'available' ORDER BY price_per_hour ASC LIMIT 3"
);

// build the featured cards by filling the court_card.html partial once per court
$cards = '';
while ($c = $featured->fetch_assoc()) {
    $cards .= view('partials/court_card.html', [
        'sport'    => htmlspecialchars($c['sport_type']),
        'name'     => htmlspecialchars($c['name']),
        'location' => htmlspecialchars($c['location']),
        'price'    => number_format($c['price_per_hour'], 0),
        'action'   => '<a class="btn btn-dark btn-sm" href="booking.php?court_id=' . $c['court_id'] . '">Book</a>',
    ]);
}

$registerBtn = isLoggedIn() ? '' : '<a class="btn btn-outline" href="register.php">Create account</a>';

$content = view('index.html', [
    'register_btn' => $registerBtn,
    'cards'        => $cards,
]);

render_page('Home', $content);
