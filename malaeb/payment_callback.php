<?php
// ============================================================
//  payment_callback.php — verify a payment, then confirm the booking.
//
//  Reached two ways:
//    live mode     — Moyasar redirects here (GET) after the customer pays,
//                    adding ?id=<payment_id>&status=...
//    simulate mode — our local test checkout (pay.html) POSTs here with the
//                    outcome it chose (paid / failed).
//
//  We NEVER trust the redirect about whether money moved. moyasar_verify_payment
//  reads the payment back from the API (in live mode) and checks the status,
//  amount and currency itself before we confirm anything.
// ============================================================
require_once __DIR__ . '/bootstrap.php';
requireLogin();

$bookingId = (int)($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);

// Load the booking — must belong to the logged-in user.
$stmt = $conn->prepare(
    "SELECT booking_id, court_id, booking_date, start_time, end_time, total_price, status, invoice_ref
     FROM bookings WHERE booking_id = ? AND user_id = ?"
);
$stmt->bind_param("ii", $bookingId, $_SESSION['user_id']);
$stmt->execute();
$b = $stmt->get_result()->fetch_assoc();

if (!$b) {
    flash('error', 'Booking not found.');
    redirect('my_bookings.php');
}
if ($b['status'] === 'confirmed') {
    flash('success', 'This booking is already paid.');
    redirect('my_bookings.php');
}
if ($b['status'] !== 'pending') {
    flash('error', 'This booking can no longer be paid.');
    redirect('my_bookings.php');
}

// Work out the payment id to verify.
if (PAYMENTS_MODE !== 'live') {
    // The local test checkout is a real POST from our own form, so it carries
    // a CSRF token. The outcome it chose is encoded into the payment id exactly
    // the way moyasar_verify_payment expects (sim_paid_<id> / sim_failed_<id>).
    csrf_check();
    $outcome   = input($_POST, 'outcome') === 'paid' ? 'paid' : 'failed';
    $paymentId = 'sim_' . $outcome . '_' . $bookingId;
} else {
    // Moyasar hands the real payment id back on the redirect URL.
    $paymentId = input($_GET, 'id');
}

$expected = payment_amount_halalas($b['total_price']);

// The invoice we created for this booking, recorded by pay.php. The payment
// coming back has to belong to it, or it is not paying for this booking.
$expectedInvoice = (string)($b['invoice_ref'] ?? '');
$result = moyasar_verify_payment($paymentId, $expected, $expectedInvoice);

if (!$result['paid']) {
    // Worth a log line either way, but the last three reasons are not a
    // customer whose card was declined — they are a payment that does not
    // belong to this booking, which is someone trying it on.
    $suspicious = in_array($result['reason'], ['wrong_invoice', 'unbound_payment', 'amount_mismatch'], true);
    error_log(sprintf(
        '%s payment rejected: booking=%d user=%d reason=%s',
        $suspicious ? 'SUSPICIOUS' : 'Failed',
        $bookingId,
        (int)$_SESSION['user_id'],
        $result['reason']
    ));

    // Say the same thing to the customer whichever it was. A rejection message
    // that distinguishes "declined" from "that payment is not yours" would tell
    // someone probing exactly which ids are worth trying.
    flash('error', 'Payment was not completed. You can try again from My Bookings.');
    redirect('my_bookings.php');
}

// Paid. Confirm the booking — but re-check the slot inside a transaction first,
// because the pending hold may have lapsed and another player could have taken
// it while this payment was in flight.
$conn->begin_transaction();
try {
    $chk = $conn->prepare(
        "SELECT COUNT(*) AS n FROM bookings
         WHERE court_id = ? AND booking_date = ?
           AND start_time < ? AND end_time > ?
           AND booking_id <> ?
           AND (status = 'confirmed'
                OR (status = 'pending'
                    AND created_at > NOW() - INTERVAL " . PENDING_HOLD_MINUTES . " MINUTE))
         FOR UPDATE"
    );
    // start_time < end, end_time > start (same overlap test as booking.php)
    $chk->bind_param("isssi", $b['court_id'], $b['booking_date'], $b['end_time'], $b['start_time'], $bookingId);
    $chk->execute();
    $clash = (int)$chk->get_result()->fetch_assoc()['n'] > 0;

    if ($clash) {
        // The slot was taken while payment was processing. Release this booking.
        // NOTE (live mode): this is where a refund would be triggered, since the
        // customer paid but cannot be given the slot.
        $rel = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE booking_id = ? AND user_id = ?");
        $rel->bind_param("ii", $bookingId, $_SESSION['user_id']);
        $rel->execute();
        $conn->commit();
        flash('error', 'Sorry — that slot was taken while your payment was processing. It has been cancelled; you have not kept a confirmed booking.');
        redirect('my_bookings.php');
    }

    $up = $conn->prepare(
        // Records the payment alongside the invoice rather than on top of it,
        // so afterwards it is still possible to see which invoice this payment
        // answered.
        "UPDATE bookings SET status = 'confirmed', payment_id = ?
         WHERE booking_id = ? AND user_id = ? AND status = 'pending'"
    );
    $up->bind_param("sii", $result['id'], $bookingId, $_SESSION['user_id']);
    $up->execute();
    $conn->commit();
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    throw $e;
}

flash('success', 'Payment confirmed — your court is booked. See you on the pitch!');
redirect('my_bookings.php');
