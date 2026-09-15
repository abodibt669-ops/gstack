<?php
// admin/dashboard.php — ADMIN DASHBOARD (logic). Fills templates/admin/dashboard.html.
require_once __DIR__ . '/../bootstrap.php';
requireAdmin('../');

// The confirmation note used to arrive as ?msg=... in the address bar, which
// meant anyone could send an admin a link that displayed whatever green
// "success" banner they liked. It now travels in the session instead.

// Publish / unpublish a court. Unlisting only changes whether players are
// offered the court; it deliberately leaves bookings alone, so anyone who has
// already booked a slot keeps it. A POST with a token, like every other write,
// because a link is something another site can make a browser follow.
if (isPost() && input($_POST, 'action') === 'toggle_publish') {
    csrf_check();
    $courtId = (int)($_POST['court_id'] ?? 0);
    $publish = input($_POST, 'publish') === '1' ? 1 : 0;

    $stmt = $conn->prepare("UPDATE courts SET is_published = ? WHERE court_id = ?");
    $stmt->bind_param("ii", $publish, $courtId);
    $stmt->execute();

    if ($stmt->affected_rows) {
        flash('success', $publish
            ? "Court published — players can find it again."
            : "Court unlisted. Existing bookings are unaffected; it just stops appearing to players.");
    } else {
        flash('error', "That court could not be updated.");
    }
    redirect('dashboard.php');
}
$stats = [
    'courts'   => $conn->query("SELECT COUNT(*) n FROM courts")->fetch_assoc()['n'],
    'users'    => $conn->query("SELECT COUNT(*) n FROM users WHERE role='customer'")->fetch_assoc()['n'],
    'bookings' => $conn->query("SELECT COUNT(*) n FROM bookings WHERE status='confirmed'")->fetch_assoc()['n'],
    'revenue'  => $conn->query("SELECT COALESCE(SUM(total_price),0) n FROM bookings WHERE status='confirmed'")->fetch_assoc()['n'],
];

$courts = $conn->query("SELECT court_id, name, sport_type, location, price_per_hour, status, is_published FROM courts ORDER BY court_id");
$rows = '';
while ($c = $courts->fetch_assoc()) {
    $status = $c['status'] === 'available'
        ? '<span class="status-confirmed">Available</span>'
        : '<span class="badge-maint">Maintenance</span>';

    // Two different ideas, kept in two columns: 'status' is whether the court
    // is usable at all (maintenance), 'visibility' is whether players are shown
    // it. A court can be perfectly available and still deliberately unlisted.
    $published  = (int)$c['is_published'] === 1;
    $visibility = $published
        ? '<span class="status-confirmed">Listed</span>'
        : '<span class="badge-hidden">Not listed</span>';
    $toggle = post_button('dashboard.php',
        ['action' => 'toggle_publish', 'court_id' => $c['court_id'], 'publish' => $published ? '0' : '1'],
        $published ? 'Unlist' : 'Publish',
        'btn btn-dark btn-sm',
        $published ? 'Hide this court from players? Existing bookings are unaffected.' : '');

    $rows .= view('partials/court_admin_row.html', [
        'id'       => $c['court_id'],
        'name'     => $c['name'],
        'sport'    => $c['sport_type'],
        'location' => $c['location'],
        'price'    => number_format((float)$c['price_per_hour'], 0),
        'status'   => raw($status),
        'visibility' => raw($visibility),
        'toggle'   => $toggle,
        // Deleting a court removes its bookings with it, so it cannot stay a
        // link that any page on the internet can make an admin's browser follow.
        'delete'   => post_button('delete_court.php', ['court_id' => $c['court_id']],
                        'Delete', 'btn btn-danger btn-sm',
                        'Delete this court? Its past bookings will also be removed.'),
    ])->html;
}

$content = view('admin/dashboard.html', [
    'alerts'        => take_flash(),
    'stat_courts'   => $stats['courts'],
    'stat_users'    => $stats['users'],
    'stat_bookings' => $stats['bookings'],
    'stat_revenue'  => number_format((float)$stats['revenue'], 0),
    'rows'          => raw($rows),
]);

render_page('Admin Dashboard', $content, '../');
