<?php
require_once __DIR__ . '/../server/connection.php';

/** Require admin auth or redirect to login. Call BEFORE any output. */
function require_admin(): void {
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: login.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin — Kimmi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"
        integrity="sha384-DyZ88mC6Up2uqS4h/KRgHuoeGwBcD4Ng9SiP4dIRy0EXTlnuz47vAwmeGwVChigm" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/admin.css" />
</head>
<body>
    <header>
        <nav class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
            <a class="navbar-brand col-md-3 col-lg-2 mr-0 px-3" href="index.php">Kimmi Admin</a>
            <button class="navbar-toggler position-absolute d-md-none collapsed" type="button"
                    data-toggle="collapse" data-target="#sidebarMenu" aria-controls="sidebarMenu"
                    aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <ul class="navbar-nav px-3">
                <li class="nav-item text-nowrap">
                    <?php if (!empty($_SESSION['admin_logged_in'])): ?>
                        <a class="nav-link" href="logout.php?logout=1">Sign out</a>
                    <?php endif; ?>
                </li>
            </ul>
        </nav>
    </header>
