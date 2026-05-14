<?php
require_once __DIR__ . '/../server/connection.php';
require_once __DIR__ . '/header.php';
require_admin();
?>

<div class="container-fluid">
    <div class="row" style="min-height: 1000px;">
        <?php include __DIR__ . '/sidemenu.php'; ?>
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Admin Account</h1>
            </div>

            <div class="container">
                <p>ID: <?= e((string)($_SESSION['admin_id'] ?? '')) ?></p>
                <p>Name: <?= e((string)($_SESSION['admin_name'] ?? '')) ?></p>
                <p>Email: <?= e((string)($_SESSION['admin_email'] ?? '')) ?></p>
            </div>
        </main>
    </div>
</div>
</body>
</html>
