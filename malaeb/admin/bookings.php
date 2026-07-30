<?php
// admin/bookings.php — ALL BOOKINGS (logic). Fills templates/admin/bookings.html.
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/template.php';
requireAdmin();

$msg = '';
if (isset($_GET['cancel'])) {
    $bid = (int)$_GET['cancel'];
    $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE booking_id = ?");
    $stmt->bind_param("i", $bid); $stmt->execute();
    $msg = "Booking cancelled.";
}
if (isset($_GET['delete'])) {
    $bid = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM bookings WHERE booking_id = ?");
    $stmt->bind_param("i", $bid); $stmt->execute();
    $msg = "Booking deleted.";
}

$data = $conn->query(
    "SELECT b.*, u.full_name, c.name AS court_name
     FROM bookings b
     JOIN users u  ON u.user_id  = b.user_id
     JOIN courts c ON c.court_id = b.court_id
     ORDER BY b.booking_date DESC, b.start_time"
);

$rows = '';
while ($b = $data->fetch_assoc()) {
    $actions = '<div class="row-actions">';
    if ($b['status'] === 'confirmed') {
        $actions .= '<a class="btn btn-dark btn-sm" href="bookings.php?cancel=' . $b['booking_id'] . '" data-confirm="Cancel this booking?">Cancel</a>';
    }
    $actions .= '<a class="btn btn-danger btn-sm" href="bookings.php?delete=' . $b['booking_id'] . '" data-confirm="Permanently delete this record?">Delete</a>';
    $actions .= '</div>';

    $rows .= view('partials/admin_booking_row.html', [
        'id'           => $b['booking_id'],
        'customer'     => htmlspecialchars($b['full_name']),
        'court_name'   => htmlspecialchars($b['court_name']),
        'date'         => $b['booking_date'],
        'time'         => substr($b['start_time'],0,5) . ' - ' . substr($b['end_time'],0,5),
        'total'        => number_format($b['total_price'], 2),
        'status_class' => $b['status'],
        'status'       => ucfirst($b['status']),
        'actions'      => $actions,
    ]);
}

$content = view('admin/bookings.html', [
    'alerts' => $msg ? alert_success($msg) : '',
    'rows'   => $rows,
]);

render_page('All bookings', $content, '../');
