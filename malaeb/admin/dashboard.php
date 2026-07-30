<?php
// admin/dashboard.php — ADMIN DASHBOARD (logic). Fills templates/admin/dashboard.html.
require_once __DIR__ . '/../bootstrap.php';
requireAdmin('../');

// The confirmation note used to arrive as ?msg=... in the address bar, which
// meant anyone could send an admin a link that displayed whatever green
// "success" banner they liked. It now travels in the session instead.
$stats = [
    'courts'   => $conn->query("SELECT COUNT(*) n FROM courts")->fetch_assoc()['n'],
    'users'    => $conn->query("SELECT COUNT(*) n FROM users WHERE role='customer'")->fetch_assoc()['n'],
    'bookings' => $conn->query("SELECT COUNT(*) n FROM bookings WHERE status='confirmed'")->fetch_assoc()['n'],
    'revenue'  => $conn->query("SELECT COALESCE(SUM(total_price),0) n FROM bookings WHERE status='confirmed'")->fetch_assoc()['n'],
];

$courts = $conn->query("SELECT court_id, name, sport_type, location, price_per_hour, status FROM courts ORDER BY court_id");
$rows = '';
while ($c = $courts->fetch_assoc()) {
    $status = $c['status'] === 'available'
        ? '<span class="status-confirmed">Available</span>'
        : '<span class="badge-maint">Maintenance</span>';

    $rows .= view('partials/court_admin_row.html', [
        'id'       => $c['court_id'],
        'name'     => $c['name'],
        'sport'    => $c['sport_type'],
        'location' => $c['location'],
        'price'    => number_format((float)$c['price_per_hour'], 0),
        'status'   => raw($status),
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
