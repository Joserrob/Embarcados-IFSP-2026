<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

logoutUser();
header('Location: ' . APP_BASE . '/login.php?logout=1');
exit;
