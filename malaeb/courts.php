<?php
// courts.php — COURTS LISTING (logic). Fills templates/courts.html.
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/template.php';

$sport    = $_GET['sport']     ?? '';
$maxPrice = $_GET['max_price'] ?? '';

// build the filtered query
$sql = "SELECT * FROM courts WHERE 1=1";
$types = ""; $params = [];
if ($sport !== '') { $sql .= " AND sport_type = ?"; $types .= "s"; $params[] = $sport; }
if ($maxPrice !== '' && is_numeric($maxPrice)) { $sql .= " AND price_per_hour <= ?"; $types .= "d"; $params[] = $maxPrice; }
$sql .= " ORDER BY sport_type, price_per_hour";

$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$courts = $stmt->get_result();

// sport dropdown options (mark the selected one)
$sportOptions = '';
foreach (['Football','Padel','Basketball','Volleyball','Tennis'] as $s) {
    $sel = $sport === $s ? ' selected' : '';
    $sportOptions .= '<option value="' . $s . '"' . $sel . '>' . $s . '</option>';
}

// court cards
$cards = '';
if ($courts->num_rows === 0) {
    $cards = '<p class="muted">No courts match your filter. Try widening the price or choosing another sport.</p>';
} else {
    while ($c = $courts->fetch_assoc()) {
        $action = $c['status'] === 'available'
            ? '<a class="btn btn-dark btn-sm" href="booking.php?court_id=' . $c['court_id'] . '">Book</a>'
            : '<span class="badge-maint">Under maintenance</span>';
        $cards .= view('partials/court_card.html', [
            'sport'    => htmlspecialchars($c['sport_type']),
            'name'     => htmlspecialchars($c['name']),
            'location' => htmlspecialchars($c['location']),
            'price'    => number_format($c['price_per_hour'], 0),
            'action'   => $action,
        ]);
    }
}

$content = view('courts.html', [
    'sport_options' => $sportOptions,
    'max_price'     => htmlspecialchars($maxPrice),
    'cards'         => $cards,
]);

render_page('Courts', $content);
