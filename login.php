<?php
require_once __DIR__ . '/server/connection.php';

if (!empty($_SESSION['logged_in'])) {
    header('Location: account.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_btn'])) {
    csrf_check();

    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Please enter a valid email and password.';
    } else {
        $stmt = $conn->prepare(
            "SELECT user_id, user_name, user_email, user_password FROM users WHERE user_email=? LIMIT 1"
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->bind_result($user_id, $user_name, $user_email, $user_password);

        if ($stmt->fetch()) {
            $stmt->close();

            $authed = false;

            // Modern bcrypt/argon2 hash check
            if (password_verify($password, $user_password)) {
                $authed = true;
                // Rehash if algorithm has been upgraded
                if (password_needs_rehash($user_password, PASSWORD_DEFAULT)) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $upd = $conn->prepare("UPDATE users SET user_password=? WHERE user_id=?");
                    $upd->bind_param('si', $newHash, $user_id);
                    $upd->execute();
                    $upd->close();
                }
            }
            // Legacy MD5 fallback - silently upgrade to bcrypt on first successful login
            elseif (strlen($user_password) === 32 && hash_equals($user_password, md5($password))) {
                $authed = true;
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE users SET user_password=? WHERE user_id=?");
                $upd->bind_param('si', $newHash, $user_id);
                $upd->execute();
                $upd->close();
            }

            if ($authed) {
                session_rotate();
                $_SESSION['user_id']    = $user_id;
                $_SESSION['user_name']  = $user_name;
                $_SESSION['user_email'] = $user_email;
                $_SESSION['logged_in']  = true;
                header('Location: account.php?login_success=1');
                exit;
            }
        } else {
            $stmt->close();
        }

        // Generic error - never reveal whether email exists
        $error = 'Invalid email or password.';
    }
}

include __DIR__ . '/layouts/header.php';
?>

    <!-- Login -->
    <section class="my-5 py-5">
        <div class="container text-center mt-3 pt-5">
            <h2 class="form-weight-bold">Login</h2>
            <hr class="mx-auto">
        </div>

        <div class="mx-auto container">
            <form id="login-form" action="login.php" method="POST" autocomplete="off">
                <?= csrf_field() ?>
                <?php if ($error): ?>
                    <p style="color: red;" class="text-center"><?= e($error) ?></p>
                <?php endif; ?>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" class="form-control" id="login-email" name="email"
                           placeholder="Email" required
                           value="<?= e($_POST['email'] ?? '') ?>" />
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" class="form-control" id="login-password" name="password"
                           placeholder="Password" required />
                </div>
                <div class="form-group">
                    <input type="submit" class="btn" id="login-btn" name="login_btn" value="Login"/>
                </div>
                <div class="form-group">
                    <a id="register-url" href="register.php" class="btn">Don't have an account? Register</a>
                </div>
            </form>
        </div>
    </section>

<?php include __DIR__ . '/layouts/footer.php'; ?>
