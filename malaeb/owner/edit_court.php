<?php
// owner/edit_court.php — edit a court you own.
// ownedCourt() is the whole access control: it returns null both when the court
// doesn't exist and when it belongs to someone else, so the response is
// identical either way and can't be used to discover which ids exist.
require_once __DIR__ . '/../bootstrap.php';
requireOwner('../');

$courtId = (int)($_GET['id'] ?? $_POST['court_id'] ?? 0);
$court   = ownedCourt($conn, $courtId);

if (!$court) {
    flash('error', 'Court not found.');
    redirect('dashboard.php');
}

$error = '';
$name  = $court['name'];
$sport = $court['sport_type'];
$loc   = $court['location'];
$price = $court['price_per_hour'];
$stat  = $court['status'];

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
        // The WHERE re-checks ownership, so even a race between the load above
        // and this update can't write to a court the user no longer owns.
        $sql = "UPDATE courts SET name=?, sport_type=?, location=?, price_per_hour=?, status=?
                WHERE court_id=?" . (isAdmin() ? '' : ' AND owner_id=?');
        $stmt = $conn->prepare($sql);
        if (isAdmin()) {
            $stmt->bind_param("sssdsi", $name, $sport, $loc, $priceValue, $stat, $courtId);
        } else {
            $ownerId = (int)$_SESSION['user_id'];
            $stmt->bind_param("sssdsii", $name, $sport, $loc, $priceValue, $stat, $courtId, $ownerId);
        }
        $stmt->execute();

        flash('success', "Court updated.");
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
    'heading'        => 'Edit court',
    'submit_label'   => 'Save changes',
    'action'         => 'edit_court.php',
    'court_id'       => (int)$court['court_id'],
    'alerts'         => $error ? alert_error($error) : '',
    'name'           => $name,
    'location'       => $loc,
    'price'          => $price,
    'sport_options'  => raw($sportOptions),
    'status_options' => raw($statusOptions),
    'csrf'           => csrf_field(),
]);

render_page('Edit court', $content, '../');
