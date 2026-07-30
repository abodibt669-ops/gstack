<?php
// admin/delete_court.php — deletes a court and redirects. No HTML, so no template.
require_once __DIR__ . '/../bootstrap.php';
requireAdmin('../');

// Deleting a court cascades to its bookings, so this is the most destructive
// action in the app. It used to happen on a GET link, which means any page an
// admin visited could fire it off with an <img> tag. POST plus a token now.
if (!isPost()) {
    redirect('dashboard.php');
}
csrf_check();

$id = (int)($_POST['court_id'] ?? 0);

// Refuse while customers still have upcoming bookings here. Wiping a court out
// from under a confirmed reservation is not something to do by accident; the
// admin can set it to maintenance instead, which stops new bookings and leaves
// the existing ones intact.
$chk = $conn->prepare(
    "SELECT COUNT(*) AS n FROM bookings
     WHERE court_id = ? AND status = 'confirmed' AND booking_date >= CURDATE()"
);
$chk->bind_param("i", $id);
$chk->execute();
$upcoming = (int)$chk->get_result()->fetch_assoc()['n'];

if ($upcoming > 0) {
    flash('error', "This court has {$upcoming} upcoming booking(s). Cancel them first, or set the court to maintenance instead.");
    redirect('dashboard.php');
}

$stmt = $conn->prepare("DELETE FROM courts WHERE court_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

$stmt->affected_rows
    ? flash('success', "Court deleted.")
    : flash('error', "That court no longer exists.");
redirect('dashboard.php');
