<?php
// pay.php — start payment for a pending booking.
//  - live mode: creates a Moyasar invoice and sends the customer to it.
//  - simulate mode: shows a local test checkout so the whole flow works
//    without a Moyasar account.
require_once __DIR__ . '/bootstrap.php';
requireLogin();

$bookingId = (int)($_GET['booking_id'] ?? 0);

$stmt = $conn->prepare(
    "SELECT b.booking_id, b.total_price, b.status,
            b.booking_date, b.start_time, b.end_time,
            c.name AS court_name, c.sport_type
     FROM bookings b JOIN courts c ON c.court_id = b.court_id
     WHERE b.booking_id = ? AND b.user_id = ?"
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

$amountHalalas = payment_amount_halalas($b['total_price']);
$amountLabel   = number_format((float)$b['total_price'], 2);
$description   = $b['sport_type'] . ' — ' . $b['court_name']
               . ' (' . $b['booking_date'] . ' '
               . substr($b['start_time'], 0, 5) . '–' . substr($b['end_time'], 0, 5) . ')';

// Simulate-mode local checkout screen (stands in for the Moyasar page).
if (PAYMENTS_MODE !== 'live' && !empty($_GET['checkout'])) {
    $content = view('pay.html', [
        'desc'       => $description,
        'amount'     => $amountLabel,
        'booking_id' => $b['booking_id'],
        'csrf'       => csrf_field(),
    ]);
    render_page('Checkout', $content);
    return;
}

// Create the invoice. The callback carries our booking_id back; Moyasar adds
// its own ?id=&status= to it after the customer pays.
$callback = payment_url_to('payment_callback.php', ['booking_id' => (int)$b['booking_id']]);
$invoice  = moyasar_create_invoice($amountHalalas, $description, $callback, (int)$b['booking_id']);

if (!$invoice['ok']) {
    flash('error', 'Could not start payment right now. Please try again.');
    redirect('my_bookings.php');
}

// Record which invoice/payment belongs to this booking.
$up = $conn->prepare("UPDATE bookings SET payment_ref = ? WHERE booking_id = ? AND user_id = ?");
$up->bind_param("sii", $invoice['id'], $b['booking_id'], $_SESSION['user_id']);
$up->execute();

// Off to the payment page (Moyasar hosted, or our local checkout in simulate).
redirect($invoice['url']);
