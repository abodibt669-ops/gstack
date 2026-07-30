<?php
// admin/delete_court.php — deletes a court and redirects. No HTML, so no template.
require_once '../config/db.php';
require_once '../includes/auth.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("DELETE FROM courts WHERE court_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: dashboard.php?msg=" . urlencode("Court deleted."));
exit;
