<?php
// admin/dashboard.php — ADMIN DASHBOARD (logic). Fills templates/admin/dashboard.html.
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/template.php';
requireAdmin();

$msg = $_GET['msg'] ?? '';

$stats = [
    'courts'   => $conn->query("SELECT COUNT(*) n FROM courts")->fetch_assoc()['n'],
    'users'    => $conn->query("SELECT COUNT(*) n FROM users WHERE role='customer'")->fetch_assoc()['n'],
    'bookings' => $conn->query("SELECT COUNT(*) n FROM bookings WHERE status='confirmed'")->fetch_assoc()['n'],
    'revenue'  => $conn->query("SELECT COALESCE(SUM(total_price),0) n FROM bookings WHERE status='confirmed'")->fetch_assoc()['n'],
];

$courts = $conn->query("SELECT * FROM courts ORDER BY court_id");
$rows = '';
while ($c = $courts->fetch_assoc()) {
    $status = $c['status'] === 'available'
        ? '<span class="status-confirmed">Available</span>'
        : '<span class="badge-maint">Maintenance</span>';
    $rows .= view('partials/court_admin_row.html', [
        'id'       => $c['court_id'],
        'name'     => htmlspecialchars($c['name']),
        'sport'    => htmlspecialchars($c['sport_type']),
        'location' => htmlspecialchars($c['location']),
        'price'    => number_format($c['price_per_hour'], 0),
        'status'   => $status,
    ]);
}

$content = view('admin/dashboard.html', [
    'alerts'        => $msg ? alert_success(htmlspecialchars($msg)) : '',
    'stat_courts'   => $stats['courts'],
    'stat_users'    => $stats['users'],
    'stat_bookings' => $stats['bookings'],
    'stat_revenue'  => number_format($stats['revenue'], 0),
    'rows'          => $rows,
]);

render_page('Admin Dashboard', $content, '../');
