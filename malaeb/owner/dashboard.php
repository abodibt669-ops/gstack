<?php
// owner/dashboard.php — a court owner's home: their courts, upcoming bookings
// and what they've earned. Every query is scoped to owner_id, so an owner can
// only ever see their own numbers.
require_once __DIR__ . '/../bootstrap.php';
requireOwner('../');

$isAdmin = isAdmin();
$ownerId = (int)$_SESSION['user_id'];

// --- headline numbers -------------------------------------------------
// Revenue counts confirmed bookings only: a pending hold is unpaid, and
// counting it would show an owner money that may never arrive.
$scope  = $isAdmin ? '' : ' AND c.owner_id = ?';
$sql = "SELECT
          (SELECT COUNT(*) FROM courts c WHERE 1=1" . ($isAdmin ? '' : ' AND c.owner_id = ?') . ") AS courts,
          (SELECT COUNT(*) FROM bookings b JOIN courts c ON c.court_id = b.court_id
            WHERE b.status = 'confirmed' AND b.booking_date >= CURDATE()" . $scope . ") AS upcoming,
          (SELECT COALESCE(SUM(b.total_price),0) FROM bookings b JOIN courts c ON c.court_id = b.court_id
            WHERE b.status = 'confirmed'" . $scope . ") AS revenue";
$stmt = $conn->prepare($sql);
if (!$isAdmin) {
    $stmt->bind_param("iii", $ownerId, $ownerId, $ownerId);
}
$stmt->execute();
$s = $stmt->get_result()->fetch_assoc();

// --- their courts -----------------------------------------------------
$sql = "SELECT court_id, name, sport_type, location, price_per_hour, status, is_published
        FROM courts" . ($isAdmin ? '' : ' WHERE owner_id = ?') . " ORDER BY name";
$stmt = $conn->prepare($sql);
if (!$isAdmin) { $stmt->bind_param("i", $ownerId); }
$stmt->execute();
$courts = $stmt->get_result();

$rows = '';
while ($c = $courts->fetch_assoc()) {
    // An unlisted court still belongs to the owner and is still editable here;
    // it has just been taken off the public listing, so say so plainly rather
    // than leaving them wondering why nobody is booking it.
    $hiddenNote = (int)$c['is_published'] === 1
        ? ''
        : '<span class="badge-hidden">Not visible to players</span>';

    $rows .= view('owner/court_row.html', [
        'name'     => $c['name'],
        'sport'    => $c['sport_type'],
        'location' => $c['location'],
        'price'    => number_format((float)$c['price_per_hour'], 0),
        'status'   => COURT_STATUSES[$c['status']] ?? $c['status'],
        'status_class' => $c['status'],
        'court_id' => (int)$c['court_id'],
        'hidden_note'  => raw($hiddenNote),
    ])->html;
}
if ($rows === '') {
    $rows = '<tr><td colspan="5" class="muted">No courts yet — add your first one to start taking bookings.</td></tr>';
}

// --- upcoming bookings on their courts --------------------------------
$sql = "SELECT b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
               c.name AS court_name, u.full_name AS player
        FROM bookings b
        JOIN courts c ON c.court_id = b.court_id
        JOIN users  u ON u.user_id  = b.user_id
        WHERE b.booking_date >= CURDATE() AND b.status <> 'cancelled'"
        . ($isAdmin ? '' : ' AND c.owner_id = ?') .
      " ORDER BY b.booking_date, b.start_time LIMIT 25";
$stmt = $conn->prepare($sql);
if (!$isAdmin) { $stmt->bind_param("i", $ownerId); }
$stmt->execute();
$bk = $stmt->get_result();

$bookingRows = '';
while ($b = $bk->fetch_assoc()) {
    $bookingRows .= view('owner/booking_row.html', [
        'court'  => $b['court_name'],
        'player' => $b['player'],
        'date'   => $b['booking_date'],
        'time'   => substr($b['start_time'], 0, 5) . '–' . substr($b['end_time'], 0, 5),
        'total'  => number_format((float)$b['total_price'], 2),
        'status' => ucfirst($b['status']),
        'status_class' => $b['status'],
    ])->html;
}
if ($bookingRows === '') {
    $bookingRows = '<tr><td colspan="6" class="muted">No upcoming bookings yet.</td></tr>';
}

$content = view('owner/dashboard.html', [
    'alerts'        => take_flash(),
    'stat_courts'   => (int)$s['courts'],
    'stat_upcoming' => (int)$s['upcoming'],
    'stat_revenue'  => number_format((float)$s['revenue'], 0),
    'court_rows'    => raw($rows),
    'booking_rows'  => raw($bookingRows),
]);

render_page('My courts', $content, '../');
