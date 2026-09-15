<?php
// logout.php — ends the session and redirects. No HTML, so no template.
require_once __DIR__ . '/bootstrap.php';

logoutUser();
redirect('index.php');
