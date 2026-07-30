<?php
// my_bookings.php — MY BOOKINGS (logic). Fills templates/my_bookings.html.
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/template.php';
requireLogin();

$msg = '';
if (isset($_GET['cancel'])) {
    $bid = (int)$_GET['cancel'];
    $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE booking_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $bid, $_SESSION['user_id']);
    $stmt->execute();
    $msg = $stmt->affected_rows ? "Booking cancelled." : "";
}

$stmt = $conn->prepare(
    "SELECT b.*, c.name AS court_name, c.sport_type
     FROM bookings b JOIN courts c ON c.court_id = b.court_id
     WHERE b.user_id = ? ORDER BY b.booking_date DESC, b.start_time"
);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$bookings = $stmt->get_result();

if ($bookings->num_rows === 0) {
    $body = view('my_bookings_empty.html');
} else {
    $rows = '';
    while ($b = $bookings->fetch_assoc()) {
        if ($b['status'] === 'confirmed') {
            $actions = '<div class="row-actions">'
                . '<a class="btn btn-dark btn-sm" href="edit_booking.php?id=' . $b['booking_id'] . '">Edit</a>'
                . '<a class="btn btn-danger btn-sm" href="my_bookings.php?cancel=' . $b['booking_id'] . '" data-confirm="Cancel this booking?">Cancel</a>'
                . '</div>';
        } else {
            $actions = '<span class="muted">-</span>';
        }
        $rows .= view('partials/booking_row.html', [
            'court_name'   => htmlspecialchars($b['court_name']),
            'sport'        => htmlspecialchars($b['sport_type']),
            'date'         => htmlspecialchars($b['booking_date']),
            'time'         => substr($b['start_time'],0,5) . ' - ' . substr($b['end_time'],0,5),
            'total'        => number_format($b['total_price'], 2),
            'status_class' => $b['status'],
            'status'       => ucfirst($b['status']),
            'actions'      => $actions,
        ]);
    }
    $body = view('my_bookings_table.html', ['rows' => $rows]);
}

$content = view('my_bookings.html', [
    'alerts' => $msg ? alert_success($msg) : '',
    'body'   => $body,
]);

render_page('My Bookings', $content);
