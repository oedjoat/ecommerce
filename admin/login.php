<?php
require_once __DIR__ . '/../server/connection.php';

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_btn'])) {
    csrf_check();

    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Invalid email or password.';
    } else {
        $stmt = $conn->prepare(
            "SELECT admin_id, admin_name, admin_email, admin_password
             FROM admins WHERE admin_email=? LIMIT 1"
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->bind_result($admin_id, $admin_name, $admin_email, $admin_password);

        $authed = false;
        if ($stmt->fetch()) {
            $stmt->close();

            if (password_verify($password, $admin_password)) {
                $authed = true;
                if (password_needs_rehash($admin_password, PASSWORD_DEFAULT)) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $u = $conn->prepare("UPDATE admins SET admin_password=? WHERE admin_id=?");
                    $u->bind_param('si', $newHash, $admin_id);
                    $u->execute();
                    $u->close();
                }
            } elseif (strlen($admin_password) === 32 && hash_equals($admin_password, md5($password))) {
                $authed = true;
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $u = $conn->prepare("UPDATE admins SET admin_password=? WHERE admin_id=?");
                $u->bind_param('si', $newHash, $admin_id);
                $u->execute();
                $u->close();
            }
        } else {
            $stmt->close();
        }

        if ($authed) {
            session_rotate();
            $_SESSION['admin_id']        = $admin_id;
            $_SESSION['admin_name']      = $admin_name;
            $_SESSION['admin_email']     = $admin_email;
            $_SESSION['admin_logged_in'] = true;
            header('Location: index.php');
            exit;
        }

        $error = 'Invalid email or password.';
    }
}

include __DIR__ . '/header.php';
?>

<div class="container mt-5" style="max-width: 480px;">
    <h2>Kimmi Admin</h2>
    <form id="login-form" action="login.php" method="post" autocomplete="off">
        <?= csrf_field() ?>
        <?php if ($error): ?>
            <p style="color: red;"><?= e($error) ?></p>
        <?php endif; ?>
        <div class="form-group mt-2">
            <label>Email</label>
            <input type="email" class="form-control" placeholder="Enter Email" name="email" required
                   value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group mt-2">
            <label>Password</label>
            <input type="password" class="form-control" placeholder="Enter Password" name="password" required>
        </div>
        <div class="form-group mt-3">
            <input type="submit" class="btn btn-primary" name="login_btn" value="Login" />
        </div>
    </form>
</div>
</body>
</html>
