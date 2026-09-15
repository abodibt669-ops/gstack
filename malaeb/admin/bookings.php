<?php
// admin/bookings.php — ALL BOOKINGS (logic). Fills templates/admin/bookings.html.
require_once __DIR__ . '/../bootstrap.php';
requireAdmin('../');

// Cancel and delete were links (bookings.php?delete=12). A link is something a
// browser can be made to follow from anywhere, so an admin who was logged in
// and visited the wrong page could delete records without ever clicking. Both
// are POST with a token now.
if (isPost()) {
    csrf_check();
    $action = input($_POST, 'action');
    $bid    = (int)($_POST['booking_id'] ?? 0);

    if ($action === 'cancel') {
        $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE booking_id = ? AND status = 'confirmed'");
        $stmt->bind_param("i", $bid);
        $stmt->execute();
        $stmt->affected_rows
            ? flash('success', "Booking cancelled.")
            : flash('error', "That booking was not cancelled. It may already be cancelled.");
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM bookings WHERE booking_id = ?");
        $stmt->bind_param("i", $bid);
        $stmt->execute();
        $stmt->affected_rows
            ? flash('success', "Booking deleted.")
            : flash('error', "That booking no longer exists.");
    }
    redirect('bookings.php');
}

$data = $conn->query(
    "SELECT b.booking_id, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
            u.full_name, c.name AS court_name
     FROM bookings b
     JOIN users u  ON u.user_id  = b.user_id
     JOIN courts c ON c.court_id = b.court_id
     ORDER BY b.booking_date DESC, b.start_time"
);

$rows = '';
while ($b = $data->fetch_assoc()) {
    $actions = '<div class="row-actions">';
    if ($b['status'] === 'confirmed') {
        $actions .= post_button('bookings.php', ['action' => 'cancel', 'booking_id' => $b['booking_id']],
                      'Cancel', 'btn btn-dark btn-sm', 'Cancel this booking?')->html;
    }
    $actions .= post_button('bookings.php', ['action' => 'delete', 'booking_id' => $b['booking_id']],
                  'Delete', 'btn btn-danger btn-sm', 'Permanently delete this record?')->html;
    $actions .= '</div>';

    $rows .= view('partials/admin_booking_row.html', [
        'id'           => $b['booking_id'],
        'customer'     => $b['full_name'],
        'court_name'   => $b['court_name'],
        'date'         => $b['booking_date'],
        'time'         => substr($b['start_time'], 0, 5) . ' - ' . substr($b['end_time'], 0, 5),
        'total'        => number_format((float)$b['total_price'], 2),
        'status_class' => $b['status'],
        'status'       => ucfirst($b['status']),
        'actions'      => raw($actions),
    ])->html;
}

$content = view('admin/bookings.html', [
    'alerts' => take_flash(),
    'rows'   => raw($rows),
]);

render_page('All bookings', $content, '../');
