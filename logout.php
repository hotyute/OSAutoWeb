<?php
/**
 * Logout handler — destroys session, redirects to login.
 */
require_once __DIR__ . '/includes/functions.php';
initSession();
$_SESSION = [];
session_destroy();
header('Location: /login.php');
exit;