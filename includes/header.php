<?php
declare(strict_types=1);
/**
 * Page header. Set before including:
 *   $pageTitle  (string)  required
 *   $hideNavbar (bool)    optional, e.g. on the login page
 */
require_once __DIR__ . '/functions.php';

$pageTitle  = $pageTitle ?? APP_NAME;
$hideNavbar = $hideNavbar ?? false;
$logo       = logo_url();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(page_title($pageTitle)) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="/assets/css/theme.css">
</head>
<body class="d-flex flex-column min-vh-100">
<?php if (!$hideNavbar): ?>
<nav class="navbar navbar-expand-lg navbar-dark navbar-brand-bg">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/">
            <?php if ($logo !== null): ?>
                <img src="<?= e($logo) ?>" alt="<?= e(SCHOOL_SHORT_NAME) ?> logo" class="navbar-logo">
            <?php endif; ?>
            <span><?= e(APP_NAME) ?></span>
        </a>
    </div>
</nav>
<?php endif; ?>
<main class="flex-grow-1">
