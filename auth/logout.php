<?php
require_once __DIR__ . '/../config/functions.php';
audit('logout', 'users', currentUserId(), 'User logged out');
session_destroy();
header('Location: /dmc/index.php');
exit;
