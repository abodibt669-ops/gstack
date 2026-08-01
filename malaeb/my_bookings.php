<?php
// my_bookings.php — MY BOOKINGS (logic). Fills templates/my_bookings.html.
require_once __DIR__ . '/bootstrap.php';
requireLogin();

// Cancelling used to be a plain link: my_bookings.php?cancel=7. Any other site
// could then cancel a logged-in customer's booking just by putting
// <img src="http://localhost/malaeb/my_bookings.php?cancel=7"> on a page they
// visit — the browser sends the session cookie along without being asked.
// Now it is a POST carrying a token only our own pages know.
if (isPost() && input($_POST, 'action') === 'cancel') {
    csrf_check();
    $bid = (int)($_POST['booking_id'] ?? 0);
    $stmt = $conn->prepare(
        "UPDATE bookings SET status = 'cancelled'
         WHERE booking_id = ? AND user_id = ? AND status = 'confirmed'"
    );
    $stmt->bind_param("ii", $bid, $_SESSION['user_id']);
    $stmt->execute();

    // Say something either way. The old code showed a blank message when the
    // cancel did nothing, so a failed cancel looked identical to a page reload.
    $stmt->affected_rows
        ? flash('success', "Booking cancelled.")
        : flash('error', "That booking could not be cancelled. It may already be cancelled.");
    redirect('my_bookings.php');
}

$stmt = $conn->prepare(
    "SELECT b.booking_id, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
            c.name AS court_name, c.sport_type
     FROM bookings b JOIN courts c ON c.court_id = b.court_id
     WHERE b.user_id = ? ORDER BY b.booking_date DESC, b.start_time"
);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$bookings = $stmt->get_result();

$today = (new DateTimeImmutable('today'))->format('Y-m-d');

if ($bookings->num_rows === 0) {
    $body = view('my_bookings_empty.html');
} else {
    $rows = '';
    while ($b = $bookings->fetch_assoc()) {
        // A booking whose date has passed cannot be edited or cancelled, and
        // saying so beats offering buttons that only produce an error.
        $isPast = $b['booking_date'] < $today;

        if ($b['status'] === 'confirmed' && !$isPast) {
            $actions = '<div class="row-actions">'
                . '<a class="btn btn-dark btn-sm" href="edit_booking.php?id=' . (int)$b['booking_id'] . '">Edit</a>'
                . post_button('my_bookings.php',
                    ['action' => 'cancel', 'booking_id' => $b['booking_id']],
                    'Cancel', 'btn btn-danger btn-sm', 'Cancel this booking?')->html
                . '</div>';
        } elseif ($b['status'] === 'confirmed') {
            $actions = '<span class="muted">Past</span>';
        } elseif ($b['status'] === 'pending' && !$isPast) {
            $actions = '<a class="btn btn-primary btn-sm" href="pay.php?booking_id='
                . (int)$b['booking_id'] . '">Pay now</a>';
        } else {
            $actions = '<span class="muted">-</span>';
        }

        $rows .= view('partials/booking_row.html', [
            'court_name'   => $b['court_name'],
            'sport'        => $b['sport_type'],
            'date'         => $b['booking_date'],
            'time'         => substr($b['start_time'], 0, 5) . ' - ' . substr($b['end_time'], 0, 5),
            'total'        => number_format((float)$b['total_price'], 2),
            'status_class' => $b['status'],
            'status'       => ucfirst($b['status']),
            'actions'      => raw($actions),
        ])->html;
    }
    $body = view('my_bookings_table.html', ['rows' => raw($rows)]);
}

$content = view('my_bookings.html', [
    'alerts' => take_flash(),
    'body'   => $body,
]);

render_page('My Bookings', $content);
