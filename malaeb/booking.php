<?php
// booking.php — CREATE A BOOKING (logic). Fills templates/booking.html.
require_once __DIR__ . '/bootstrap.php';
requireLogin();

$error = '';

$courtId = (int)($_GET['court_id'] ?? $_POST['court_id'] ?? 0);
$stmt = $conn->prepare(
    "SELECT court_id, name, sport_type, location, price_per_hour
     FROM courts WHERE court_id = ? AND status = 'available'"
);
$stmt->bind_param("i", $courtId);
$stmt->execute();
$court = $stmt->get_result()->fetch_assoc();

if (isPost() && $court) {
    csrf_check();
    $date  = input($_POST, 'booking_date');
    $start = input($_POST, 'start_time');
    $end   = input($_POST, 'end_time');

    // One shared rule set, so this page and edit_booking.php cannot drift apart
    // again. (They had: only this one refused dates in the past.)
    $error = validate_slot($date, $start, $end);

    if ($error === '') {
        $start = normalize_time($start);
        $end   = normalize_time($end);

        // Checking "is this slot free?" and then inserting are two separate
        // trips to the database. Two customers booking the same court at the
        // same moment could both be told the slot was free. Doing both inside
        // one transaction, with the check locking the rows (and the gap where a
        // conflicting row would go), makes the second one wait and then lose.
        $conn->begin_transaction();
        try {
            // A slot is taken if there's a confirmed booking, OR a still-fresh
            // pending one someone is paying for right now. Pending holds older
            // than PENDING_HOLD_MINUTES are ignored, so an abandoned checkout
            // never locks a slot forever. NOW() and created_at are both DB
            // server time, so this stays correct whatever PHP's timezone is.
            $chk = $conn->prepare(
                "SELECT COUNT(*) AS n FROM bookings
                 WHERE court_id = ? AND booking_date = ?
                   AND start_time < ? AND end_time > ?
                   AND (status = 'confirmed'
                        OR (status = 'pending'
                            AND created_at > NOW() - INTERVAL " . PENDING_HOLD_MINUTES . " MINUTE))
                 FOR UPDATE"
            );
            $chk->bind_param("isss", $courtId, $date, $end, $start);
            $chk->execute();

            if ((int)$chk->get_result()->fetch_assoc()['n'] > 0) {
                $conn->rollback();
                $error = "This time slot is already booked. Please choose another time.";
            } else {
                $total = round(slot_hours($start, $end) * (float)$court['price_per_hour'], 2);
                // Saved as 'pending' (unpaid). It only becomes 'confirmed' after
                // the payment is verified in payment_callback.php.
                $ins = $conn->prepare(
                    "INSERT INTO bookings (user_id, court_id, booking_date, start_time, end_time, total_price, status)
                     VALUES (?, ?, ?, ?, ?, ?, 'pending')"
                );
                $ins->bind_param("iisssd", $_SESSION['user_id'], $courtId, $date, $start, $end, $total);
                $ins->execute();
                $bookingId = $conn->insert_id;
                $conn->commit();

                // Hand off to payment. Refreshing the booking page can't now
                // double-book, because the slot is already held as pending.
                redirect('pay.php?booking_id=' . $bookingId);
            }
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            throw $e;
        }
    }
}

if (!$court) {
    // no court found -> show a message page
    $content = view('partials/message.html', [
        'body' => alert_error(raw('Court not found or unavailable. <a href="courts.php">Back to courts</a>.')),
    ]);
} else {
    $alerts = take_flash()->html;
    if ($error) $alerts .= alert_error($error)->html;

    $content = view('booking.html', [
        'sport'    => $court['sport_type'],
        'name'     => $court['name'],
        'location' => $court['location'],
        'price'    => number_format((float)$court['price_per_hour'], 0),
        'rate'     => $court['price_per_hour'],
        'court_id' => $court['court_id'],
        'max_date' => (new DateTimeImmutable('today'))->modify('+' . MAX_BOOKING_DAYS_AHEAD . ' days')->format('Y-m-d'),
        'alerts'   => raw($alerts),
        'csrf'     => csrf_field(),
    ]);
}

render_page('Book a court', $content);
