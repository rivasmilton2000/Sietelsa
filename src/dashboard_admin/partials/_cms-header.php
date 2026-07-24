<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/content.php';
auth_require_admin();
$cmsPageTitle = $cmsPageTitle ?? 'Administración del website';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= auth_escape($cmsPageTitle) ?> | SIETELSA</title>
  <link rel="stylesheet" href="<?= auth_escape(app_url('src/dashboard_admin/assets/vendors/feather/feather.css')) ?>">
  <link rel="stylesheet" href="<?= auth_escape(app_url('src/dashboard_admin/assets/vendors/mdi/css/materialdesignicons.min.css')) ?>">
  <link rel="stylesheet" href="<?= auth_escape(app_url('src/dashboard_admin/assets/vendors/css/vendor.bundle.base.css')) ?>">
  <link rel="stylesheet" href="<?= auth_escape(app_url('src/dashboard_admin/assets/css/style.css')) ?>">
  <link rel="stylesheet" href="<?= auth_escape(app_url('src/website/assets/css/sietelsa.css')) ?>">
  <link rel="icon" href="<?= auth_escape(sietelsa_logo_url()) ?>">
</head>
<body>
<div class="container-scroller">
  <?php require __DIR__ . '/_navbar.php'; ?>
  <div class="container-fluid page-body-wrapper">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <main class="main-panel">
      <div class="content-wrapper">
