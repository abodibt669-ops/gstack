<?php
// booking.php — CREATE A BOOKING (logic). Fills templates/booking.html.
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/template.php';
requireLogin();

$success = $error = '';

$courtId = (int)($_GET['court_id'] ?? $_POST['court_id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM courts WHERE court_id = ? AND status = 'available'");
$stmt->bind_param("i", $courtId);
$stmt->execute();
$court = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $court) {
    $date  = $_POST['booking_date'];
    $start = $_POST['start_time'];
    $end   = $_POST['end_time'];

    if ($end <= $start) {
        $error = "End time must be after start time.";
    } elseif ($date < date('Y-m-d')) {
        $error = "You cannot book a date in the past.";
    } else {
        $chk = $conn->prepare(
            "SELECT COUNT(*) AS n FROM bookings
             WHERE court_id = ? AND booking_date = ? AND status = 'confirmed'
               AND start_time < ? AND end_time > ?"
        );
        $chk->bind_param("isss", $courtId, $date, $end, $start);
        $chk->execute();
        $taken = $chk->get_result()->fetch_assoc()['n'] > 0;

        if ($taken) {
            $error = "This time slot is already booked. Please choose another time.";
        } else {
            $hours = (strtotime($end) - strtotime($start)) / 3600;
            $total = round($hours * $court['price_per_hour'], 2);
            $ins = $conn->prepare(
                "INSERT INTO bookings (user_id, court_id, booking_date, start_time, end_time, total_price)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $ins->bind_param("iisssd", $_SESSION['user_id'], $courtId, $date, $start, $end, $total);
            $ins->execute();
            $success = "Booking confirmed! Total: {$total} SAR. See it in My Bookings.";
        }
    }
}

if (!$court) {
    // no court found -> show a message page
    $content = view('partials/message.html', [
        'body' => alert_error('Court not found or unavailable. <a href="courts.php">Back to courts</a>.'),
    ]);
} else {
    $alerts = '';
    if ($success) $alerts .= alert_success($success);
    if ($error)   $alerts .= alert_error($error);

    $content = view('booking.html', [
        'sport'    => htmlspecialchars($court['sport_type']),
        'name'     => htmlspecialchars($court['name']),
        'location' => htmlspecialchars($court['location']),
        'price'    => number_format($court['price_per_hour'], 0),
        'rate'     => $court['price_per_hour'],
        'court_id' => $court['court_id'],
        'alerts'   => $alerts,
    ]);
}

render_page('Book a court', $content);
