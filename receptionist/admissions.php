<?php
require_once __DIR__ . '/../config/functions.php';
requireRoles(['receptionist','admin']);
header('Location: /dmc/nurse/admissions.php');
