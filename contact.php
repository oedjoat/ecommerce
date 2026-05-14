<?php
require_once __DIR__ . '/server/connection.php';

$message_status = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_send'])) {
    csrf_check();

    $name    = trim((string)($_POST['name']    ?? ''));
    $email   = trim((string)($_POST['email']   ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    if ($name === '' || mb_strlen($name) > 100) {
        $error = 'Please enter a valid name.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($message === '' || mb_strlen($message) > 2000) {
        $error = 'Please enter a message (max 2000 characters).';
    } else {
        // Persist to DB if a contact_messages table exists; otherwise just log.
        // Create with: CREATE TABLE contact_messages (id INT AUTO_INCREMENT PRIMARY KEY,
        //   name VARCHAR(100), email VARCHAR(254), message TEXT, created_at DATETIME);
        try {
            $stmt = $conn->prepare(
                "INSERT INTO contact_messages (name, email, message, created_at) VALUES (?,?,?,?)"
            );
            $now = date('Y-m-d H:i:s');
            $stmt->bind_param('ssss', $name, $email, $message, $now);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $t) {
            error_log('Contact message save failed: ' . $t->getMessage());
        }
        $message_status = 'Thanks for your message — we will get back to you soon.';
    }
}

include __DIR__ . '/layouts/header.php';
?>

    <!-- CONTACT -->
    <section id="contact" class="container my-5 py-5">
        <div class="container text-center mt-5">
            <h3>Contact Us</h3>
            <hr class="mx-auto">
            <p class="w-50 mx-auto">Phone: <span>123 456 7890</span></p>
            <p class="w-50 mx-auto">Email: <span>support@example.com</span></p>
            <p class="w-50 mx-auto">We work 24/7 to answer your queries</p>
        </div>

        <div class="container mt-5" style="max-width: 600px;">
            <?php if ($message_status): ?>
                <p style="color:green;" class="text-center"><?= e($message_status) ?></p>
            <?php endif; ?>
            <?php if ($error): ?>
                <p style="color:red;" class="text-center"><?= e($error) ?></p>
            <?php endif; ?>

            <form method="POST" action="contact.php">
                <?= csrf_field() ?>
                <div class="form-group mb-3">
                    <label>Name</label>
                    <input class="form-control" type="text" name="name" maxlength="100" required>
                </div>
                <div class="form-group mb-3">
                    <label>Email</label>
                    <input class="form-control" type="email" name="email" maxlength="254" required>
                </div>
                <div class="form-group mb-3">
                    <label>Message</label>
                    <textarea class="form-control" name="message" maxlength="2000" rows="5" required></textarea>
                </div>
                <input type="submit" name="contact_send" class="btn btn-primary" value="Send">
            </form>
        </div>
    </section>

<?php include __DIR__ . '/layouts/footer.php'; ?>
