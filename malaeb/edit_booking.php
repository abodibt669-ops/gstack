<?php
// edit_booking.php — EDIT A BOOKING (logic). Fills templates/edit_booking.html.
require_once __DIR__ . '/bootstrap.php';
requireLogin();

$error = '';
$bid = (int)($_GET['id'] ?? $_POST['booking_id'] ?? 0);

// The user_id condition is what stops one customer from editing another
// customer's booking by changing the id in the address bar.
$stmt = $conn->prepare(
    "SELECT b.booking_id, b.court_id, b.booking_date, b.start_time, b.end_time,
            c.name AS court_name, c.price_per_hour, c.status AS court_status
     FROM bookings b JOIN courts c ON c.court_id = b.court_id
     WHERE b.booking_id = ? AND b.user_id = ? AND b.status = 'confirmed'"
);
$stmt->bind_param("ii", $bid, $_SESSION['user_id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

// Values shown in the form: whatever was submitted, falling back to what is
// stored. Without this a rejected edit threw the customer's typing away.
$formDate  = $booking['booking_date'] ?? '';
$formStart = substr($booking['start_time'] ?? '', 0, 5);
$formEnd   = substr($booking['end_time'] ?? '', 0, 5);

if (isPost() && $booking) {
    csrf_check();
    $formDate = $date = input($_POST, 'booking_date');
    $formStart = $start = input($_POST, 'start_time');
    $formEnd = $end = input($_POST, 'end_time');

    if ($booking['court_status'] !== 'available') {
        // The court went into maintenance after the booking was made. Moving the
        // slot around is not something we should quietly allow.
        $error = "This court is under maintenance. Please cancel the booking instead.";
    } else {
        // Same rules as booking.php, including the past-date check this page
        // used to be missing entirely.
        $error = validate_slot($date, $start, $end);
    }

    if ($error === '') {
        $start = normalize_time($start);
        $end   = normalize_time($end);

        $conn->begin_transaction();
        try {
            $chk = $conn->prepare(
                "SELECT COUNT(*) AS n FROM bookings
                 WHERE court_id = ? AND booking_date = ? AND status = 'confirmed'
                   AND booking_id <> ? AND start_time < ? AND end_time > ?
                 FOR UPDATE"
            );
            $chk->bind_param("isiss", $booking['court_id'], $date, $bid, $end, $start);
            $chk->execute();

            if ((int)$chk->get_result()->fetch_assoc()['n'] > 0) {
                $conn->rollback();
                $error = "This time slot is already booked.";
            } else {
                $total = round(slot_hours($start, $end) * (float)$booking['price_per_hour'], 2);
                $upd = $conn->prepare(
                    "UPDATE bookings SET booking_date = ?, start_time = ?, end_time = ?, total_price = ?
                     WHERE booking_id = ? AND user_id = ?"
                );
                $upd->bind_param("sssdii", $date, $start, $end, $total, $bid, $_SESSION['user_id']);
                $upd->execute();
                $conn->commit();

                flash('success', "Booking updated. New total: " . number_format($total, 2) . " SAR.");
                redirect('my_bookings.php');
            }
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            throw $e;
        }
    }
}

if (!$booking) {
    $content = view('partials/message.html', [
        'body' => alert_error(raw('Booking not found. <a href="my_bookings.php">Back</a>.')),
    ]);
} else {
    $content = view('edit_booking.html', [
        'court_name' => $booking['court_name'],
        'price'      => number_format((float)$booking['price_per_hour'], 0),
        'rate'       => $booking['price_per_hour'],
        'booking_id' => $booking['booking_id'],
        'date'       => $formDate,
        'start'      => $formStart,
        'end'        => $formEnd,
        'max_date'   => (new DateTimeImmutable('today'))->modify('+' . MAX_BOOKING_DAYS_AHEAD . ' days')->format('Y-m-d'),
        'alerts'     => $error ? alert_error($error) : '',
        'csrf'       => csrf_field(),
    ]);
}

render_page('Edit booking', $content);
