<?php
// owner/add_court.php — an owner listing a new court of their own.
// Same validation as the admin form, with one difference that matters:
// owner_id is taken from the session, never from the request, so nobody can
// create a court under someone else's account by editing the form.
require_once __DIR__ . '/../bootstrap.php';
requireOwner('../');

$error = '';
$name = $loc = $price = '';
$sport = SPORTS[0];
$stat  = 'available';

if (isPost()) {
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
        $ownerId    = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare(
            "INSERT INTO courts (name, sport_type, location, price_per_hour, status, owner_id)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssdsi", $name, $sport, $loc, $priceValue, $stat, $ownerId);
        $stmt->execute();

        flash('success', "Court listed — players can book it now.");
        redirect('dashboard.php');
    }
}

$sportOptions = '';
foreach (SPORTS as $s) {
    $sportOptions .= '<option value="' . e($s) . '"' . ($sport === $s ? ' selected' : '') . '>' . e($s) . '</option>';
}
$statusOptions = '';
foreach (COURT_STATUSES as $val => $label) {
    $statusOptions .= '<option value="' . e($val) . '"' . ($stat === $val ? ' selected' : '') . '>' . e($label) . '</option>';
}

$content = view('owner/court_form.html', [
    'heading'        => 'List a new court',
    'submit_label'   => 'Add court',
    'action'         => 'add_court.php',
    'court_id'       => '',
    'alerts'         => $error ? alert_error($error) : '',
    'name'           => $name,
    'location'       => $loc,
    'price'          => $price,
    'sport_options'  => raw($sportOptions),
    'status_options' => raw($statusOptions),
    'csrf'           => csrf_field(),
]);

render_page('List a court', $content, '../');
