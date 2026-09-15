<?php
// ============================================================
//  bootstrap.php — the one line every page starts with.
//  It defines the MALAEB constant (the include files check for it and refuse
//  to run if someone browses straight to them), then loads the database
//  connection and the helpers, in that order.
//
//  Paths here are relative to THIS file, not to whatever page included it, so
//  admin/dashboard.php and index.php both load the same code with one line.
// ============================================================

define('MALAEB', true);

// Show mistakes on a development machine, never to a visitor on a real host.
// Set MALAEB_ENV=production on the server and errors go to the log only.
$isProduction = getenv('MALAEB_ENV') === 'production';
ini_set('display_errors', $isProduction ? '0' : '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/payments.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/template.php';
require_once __DIR__ . '/includes/payments.php';
