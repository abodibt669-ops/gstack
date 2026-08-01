<?php
// courts.php — COURTS LISTING (logic). Fills templates/courts.html.
require_once __DIR__ . '/bootstrap.php';

$sport    = input($_GET, 'sport');
$maxPrice = input($_GET, 'max_price');

// An unknown sport used to be sent to the database and quietly matched nothing,
// which looks identical to "there are no Padel courts". Treat it as no filter.
if ($sport !== '' && !valid_choice($sport, SPORTS)) {
    $sport = '';
}

// build the filtered query
// is_published = 0 means the owner or an admin has taken the court off the
// public listing. It stays in the database with its bookings intact; it just
// stops being offered to players, here and in the search below.
$sql = "SELECT court_id, name, sport_type, location, price_per_hour, status
        FROM courts WHERE is_published = 1";
$types = ""; $params = [];
if ($sport !== '') {
    $sql .= " AND sport_type = ?"; $types .= "s"; $params[] = $sport;
}
if ($maxPrice !== '' && is_numeric($maxPrice) && (float)$maxPrice >= 0) {
    $sql .= " AND price_per_hour <= ?"; $types .= "d"; $params[] = (float)$maxPrice;
}
$sql .= " ORDER BY sport_type, price_per_hour";

$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$courts = $stmt->get_result();

// sport dropdown options (mark the selected one)
$sportOptions = '';
foreach (SPORTS as $s) {
    $sel = $sport === $s ? ' selected' : '';
    $sportOptions .= '<option value="' . e($s) . '"' . $sel . '>' . e($s) . '</option>';
}

// court cards
$cards = '';
if ($courts->num_rows === 0) {
    $cards = '<p class="muted">No courts match your filter. Try widening the price or choosing another sport.</p>';
} else {
    while ($c = $courts->fetch_assoc()) {
        $action = $c['status'] === 'available'
            ? '<a class="btn btn-dark btn-sm" href="booking.php?court_id=' . (int)$c['court_id'] . '">Book</a>'
            : '<span class="badge-maint">Under maintenance</span>';
        $cards .= view('partials/court_card.html', [
            'sport'    => $c['sport_type'],
            'name'     => $c['name'],
            'location' => $c['location'],
            'price'    => number_format((float)$c['price_per_hour'], 0),
            'action'   => raw($action),
        ])->html;
    }
}

$content = view('courts.html', [
    'sport_options' => raw($sportOptions),
    'max_price'     => $maxPrice,
    'cards'         => raw($cards),
]);

render_page('Courts', $content);
