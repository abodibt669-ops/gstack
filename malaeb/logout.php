<?php
// logout.php — ends the session and redirects. No HTML, so no template.
require_once 'includes/auth.php';
session_destroy();
header("Location: index.php");
exit;
