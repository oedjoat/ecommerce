<?php
require_once __DIR__ . '/server/connection.php';

if (!empty($_SESSION['logged_in'])) {
    header('Location: account.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    csrf_check();

    $name             = trim((string)($_POST['name'] ?? ''));
    $email            = trim((string)($_POST['email'] ?? ''));
    $password         = (string)($_POST['password'] ?? '');
    $confirmPassword  = (string)($_POST['confirmPassword'] ?? '');

    // Server-side validation
    if ($name === '' || mb_strlen($name) > 100) {
        $error = "Please enter a valid name (max 100 characters).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
        $error = "Please enter a valid email address.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords don't match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $stmt1 = $conn->prepare("SELECT COUNT(*) FROM users WHERE user_email=?");
        $stmt1->bind_param('s', $email);
        $stmt1->execute();
        $stmt1->bind_result($num_rows);
        $stmt1->fetch();
        $stmt1->close();

        if ($num_rows != 0) {
            $error = "A user with this email already exists.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "INSERT INTO users (user_name, user_email, user_password) VALUES (?,?,?)"
            );
            $stmt->bind_param('sss', $name, $email, $hash);

            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                $stmt->close();

                // Rotate session ID after privilege change
                session_rotate();
                $_SESSION['user_id']    = $user_id;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name']  = $name;
                $_SESSION['logged_in']  = true;

                header('Location: account.php?register_success=1');
                exit;
            } else {
                $stmt->close();
                $error = "Couldn't create account at the moment.";
            }
        }
    }
}

include __DIR__ . '/layouts/header.php';
?>

    <!-- Register -->
    <section class="my-5 py-5">
        <div class="container text-center mt-3 pt-5">
            <h2 class="form-weight-bold">Register</h2>
            <hr class="mx-auto">
        </div>

        <div class="mx-auto container">
            <form id="register-form" method="POST" action="register.php" autocomplete="off">
                <?= csrf_field() ?>
                <?php if ($error): ?>
                    <p style="color:red;" class="text-center"><?= e($error) ?></p>
                <?php endif; ?>

                <div class="form-group">
                    <label>Name</label>
                    <input type="text" class="form-control" id="register-name" name="name"
                           maxlength="100" placeholder="Name" required
                           value="<?= e($_POST['name'] ?? '') ?>" />
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" class="form-control" id="register-email" name="email"
                           maxlength="254" placeholder="Email" required
                           value="<?= e($_POST['email'] ?? '') ?>" />
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" class="form-control" id="register-password" name="password"
                           minlength="8" placeholder="Password (min 8 chars)" required />
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" class="form-control" id="register-confirm-password"
                           name="confirmPassword" minlength="8" placeholder="Confirm Password" required />
                </div>
                <div class="form-group">
                    <input type="submit" class="btn" id="register-btn" name="register" value="Register" />
                </div>
                <div class="form-group">
                    <a id="login-url" href="login.php" class="btn">Already have an account? Login</a>
                </div>
            </form>
        </div>
    </section>

<?php include __DIR__ . '/layouts/footer.php'; ?>
