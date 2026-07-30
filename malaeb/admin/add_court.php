<?php
// admin/add_court.php — ADD COURT (logic). Fills templates/admin/add_court.html.
require_once __DIR__ . '/../bootstrap.php';
requireAdmin('../');

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

    // sport_type and status are ENUM columns. Sending something outside the
    // list used to go straight to MySQL, which either rejects it with a raw SQL
    // error or silently stores an empty string, depending on the server's mode.
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
        $stmt = $conn->prepare(
            "INSERT INTO courts (name, sport_type, location, price_per_hour, status) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssds", $name, $sport, $loc, $priceValue, $stat);
        $stmt->execute();

        flash('success', "Court added.");
        redirect('dashboard.php');
    }
}

// sport dropdown options, keeping whatever the admin had picked
$sportOptions = '';
foreach (SPORTS as $s) {
    $sel = $sport === $s ? ' selected' : '';
    $sportOptions .= '<option value="' . e($s) . '"' . $sel . '>' . e($s) . '</option>';
}
$statusOptions = '';
foreach (COURT_STATUSES as $val => $label) {
    $sel = $stat === $val ? ' selected' : '';
    $statusOptions .= '<option value="' . e($val) . '"' . $sel . '>' . e($label) . '</option>';
}

$content = view('admin/add_court.html', [
    'alerts'         => $error ? alert_error($error) : '',
    'name'           => $name,
    'location'       => $loc,
    'price'          => $price,
    'sport_options'  => raw($sportOptions),
    'status_options' => raw($statusOptions),
    'csrf'           => csrf_field(),
]);

render_page('Add court', $content, '../');
