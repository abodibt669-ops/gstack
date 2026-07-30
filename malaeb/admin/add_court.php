<?php
// admin/add_court.php — ADD COURT (logic). Fills templates/admin/add_court.html.
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/template.php';
requireAdmin();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']);
    $sport = $_POST['sport_type'];
    $loc   = trim($_POST['location']);
    $price = (float)$_POST['price_per_hour'];
    $stat  = $_POST['status'];

    if ($price <= 0) {
        $error = "Price must be greater than zero.";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO courts (name, sport_type, location, price_per_hour, status) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssds", $name, $sport, $loc, $price, $stat);
        $stmt->execute();
        header("Location: dashboard.php?msg=" . urlencode("Court added."));
        exit;
    }
}

// sport dropdown options
$sportOptions = '';
foreach (['Football','Padel','Basketball','Volleyball','Tennis'] as $s) {
    $sportOptions .= '<option value="' . $s . '">' . $s . '</option>';
}

$content = view('admin/add_court.html', [
    'alerts'        => $error ? alert_error($error) : '',
    'sport_options' => $sportOptions,
]);

render_page('Add court', $content, '../');
