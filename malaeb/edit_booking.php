<?php
// edit_booking.php — EDIT A BOOKING (logic). Fills templates/edit_booking.html.
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/template.php';
requireLogin();

$error = '';
$bid = (int)($_GET['id'] ?? $_POST['booking_id'] ?? 0);

$stmt = $conn->prepare(
    "SELECT b.*, c.name AS court_name, c.price_per_hour
     FROM bookings b JOIN courts c ON c.court_id = b.court_id
     WHERE b.booking_id = ? AND b.user_id = ? AND b.status = 'confirmed'"
);
$stmt->bind_param("ii", $bid, $_SESSION['user_id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $booking) {
    $date  = $_POST['booking_date'];
    $start = $_POST['start_time'];
    $end   = $_POST['end_time'];

    if ($end <= $start) {
        $error = "End time must be after start time.";
    } else {
        $chk = $conn->prepare(
            "SELECT COUNT(*) AS n FROM bookings
             WHERE court_id = ? AND booking_date = ? AND status = 'confirmed'
               AND booking_id <> ? AND start_time < ? AND end_time > ?"
        );
        $chk->bind_param("isiss", $booking['court_id'], $date, $bid, $end, $start);
        $chk->execute();

        if ($chk->get_result()->fetch_assoc()['n'] > 0) {
            $error = "This time slot is already booked.";
        } else {
            $hours = (strtotime($end) - strtotime($start)) / 3600;
            $total = round($hours * $booking['price_per_hour'], 2);
            $upd = $conn->prepare(
                "UPDATE bookings SET booking_date = ?, start_time = ?, end_time = ?, total_price = ?
                 WHERE booking_id = ? AND user_id = ?"
            );
            $upd->bind_param("sssdii", $date, $start, $end, $total, $bid, $_SESSION['user_id']);
            $upd->execute();
            header("Location: my_bookings.php");
            exit;
        }
    }
}

if (!$booking) {
    $content = view('partials/message.html', [
        'body' => alert_error('Booking not found. <a href="my_bookings.php">Back</a>.'),
    ]);
} else {
    $content = view('edit_booking.html', [
        'court_name' => htmlspecialchars($booking['court_name']),
        'price'      => number_format($booking['price_per_hour'], 0),
        'rate'       => $booking['price_per_hour'],
        'booking_id' => $booking['booking_id'],
        'date'       => $booking['booking_date'],
        'start'      => substr($booking['start_time'],0,5),
        'end'        => substr($booking['end_time'],0,5),
        'alerts'     => $error ? alert_error($error) : '',
    ]);
}

render_page('Edit booking', $content);
