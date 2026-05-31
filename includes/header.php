<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?? 'DMC Hospital' ?> — DMC</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="/dmc/assets/css/dmc.css" rel="stylesheet">
<?= $extraHead ?? '' ?>
</head>
<body>
<div class="dmc-wrapper">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="dmc-main">
<?php include __DIR__ . '/topbar.php'; ?>
<div class="dmc-content">
