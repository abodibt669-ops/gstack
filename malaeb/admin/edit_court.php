<?php
// admin/edit_court.php — EDIT COURT (logic). Fills templates/admin/edit_court.html.
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/template.php';
requireAdmin();

$error = '';
$id = (int)($_GET['id'] ?? $_POST['court_id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM courts WHERE court_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$court = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $court) {
    $name  = trim($_POST['name']);
    $sport = $_POST['sport_type'];
    $loc   = trim($_POST['location']);
    $price = (float)$_POST['price_per_hour'];
    $stat  = $_POST['status'];

    if ($price <= 0) {
        $error = "Price must be greater than zero.";
    } else {
        $upd = $conn->prepare(
            "UPDATE courts SET name=?, sport_type=?, location=?, price_per_hour=?, status=? WHERE court_id=?"
        );
        $upd->bind_param("sssdsi", $name, $sport, $loc, $price, $stat, $id);
        $upd->execute();
        header("Location: dashboard.php?msg=" . urlencode("Court updated."));
        exit;
    }
}

if (!$court) {
    $content = view('partials/message.html', [
        'body' => alert_error('Court not found. <a href="dashboard.php">Back</a>.'),
    ]);
} else {
    // sport options with the current one selected
    $sportOptions = '';
    foreach (['Football','Padel','Basketball','Volleyball','Tennis'] as $s) {
        $sel = $court['sport_type'] === $s ? ' selected' : '';
        $sportOptions .= '<option value="' . $s . '"' . $sel . '>' . $s . '</option>';
    }
    // status options with the current one selected
    $statusOptions = '';
    foreach (['available' => 'Available', 'maintenance' => 'Maintenance'] as $val => $label) {
        $sel = $court['status'] === $val ? ' selected' : '';
        $statusOptions .= '<option value="' . $val . '"' . $sel . '>' . $label . '</option>';
    }

    $content = view('admin/edit_court.html', [
        'id'             => $court['court_id'],
        'name'           => htmlspecialchars($court['name']),
        'location'       => htmlspecialchars($court['location']),
        'price'          => $court['price_per_hour'],
        'sport_options'  => $sportOptions,
        'status_options' => $statusOptions,
        'alerts'         => $error ? alert_error($error) : '',
    ]);
}

render_page('Edit court', $content, '../');
