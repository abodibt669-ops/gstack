<?php
// admin/edit_court.php — EDIT COURT (logic). Fills templates/admin/edit_court.html.
require_once __DIR__ . '/../bootstrap.php';
requireAdmin('../');

$error = '';
$id = (int)($_GET['id'] ?? $_POST['court_id'] ?? 0);

$stmt = $conn->prepare("SELECT court_id, name, sport_type, location, price_per_hour, status FROM courts WHERE court_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$court = $stmt->get_result()->fetch_assoc();

// What the form shows: the submitted values if this is a rejected save, so the
// admin does not lose their typing, otherwise what is stored.
$name  = $court['name'] ?? '';
$sport = $court['sport_type'] ?? SPORTS[0];
$loc   = $court['location'] ?? '';
$price = $court['price_per_hour'] ?? '';
$stat  = $court['status'] ?? 'available';

if (isPost() && $court) {
    csrf_check();
    $name  = input($_POST, 'name');
    $sport = input($_POST, 'sport_type');
    $loc   = input($_POST, 'location');
    $price = input($_POST, 'price_per_hour');
    $stat  = input($_POST, 'status');

    if ($name === '' || mb_strlen($name) > 100) {
        $error = "Court name is required (up to 100 characters).";
    } elseif (!valid_choice($sport, SPORTS)) {
        $error = "Please choose a sport from the list.";
    } elseif ($loc === '' || mb_strlen($loc) > 150) {
        $error = "Location is required (up to 150 characters).";
    } elseif (!is_numeric($price) || (float)$price <= 0 || (float)$price > 999999) {
        $error = "Price must be a number greater than zero.";
    } elseif (!valid_choice($stat, COURT_STATUSES)) {
        $error = "Please choose a valid status.";
    } else {
        $priceValue = round((float)$price, 2);
        $upd = $conn->prepare(
            "UPDATE courts SET name=?, sport_type=?, location=?, price_per_hour=?, status=? WHERE court_id=?"
        );
        $upd->bind_param("sssdsi", $name, $sport, $loc, $priceValue, $stat, $id);
        $upd->execute();

        // Changing the price does not rewrite what existing customers were
        // already quoted: each booking keeps the total it was made at.
        flash('success', "Court updated.");
        redirect('dashboard.php');
    }
}

if (!$court) {
    $content = view('partials/message.html', [
        'body' => alert_error(raw('Court not found. <a href="dashboard.php">Back</a>.')),
    ]);
} else {
    // sport options with the current one selected
    $sportOptions = '';
    foreach (SPORTS as $s) {
        $sel = $sport === $s ? ' selected' : '';
        $sportOptions .= '<option value="' . e($s) . '"' . $sel . '>' . e($s) . '</option>';
    }
    // status options with the current one selected
    $statusOptions = '';
    foreach (COURT_STATUSES as $val => $label) {
        $sel = $stat === $val ? ' selected' : '';
        $statusOptions .= '<option value="' . e($val) . '"' . $sel . '>' . e($label) . '</option>';
    }

    $content = view('admin/edit_court.html', [
        'id'             => $court['court_id'],
        'name'           => $name,
        'location'       => $loc,
        'price'          => $price,
        'sport_options'  => raw($sportOptions),
        'status_options' => raw($statusOptions),
        'alerts'         => $error ? alert_error($error) : '',
        'csrf'           => csrf_field(),
    ]);
}

render_page('Edit court', $content, '../');
