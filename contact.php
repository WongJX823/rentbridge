<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

$pageTitle     = 'Contact Us';
$activeNav     = 'contact';
$showPageTitle = false;

$errors = [];
$old = [
    'name'    => is_logged_in() ? (current_user_display_name() ?? '') : '',
    'email'   => '',
    'subject' => '',
    'message' => '',
];

// Pre-fill email if logged in
if (is_logged_in()) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([current_user_id()]);
    $old['email'] = $stmt->fetchColumn() ?: '';
}

$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $old['name']    = trim($_POST['name'] ?? '');
    $old['email']   = trim($_POST['email'] ?? '');
    $old['subject'] = trim($_POST['subject'] ?? '');
    $old['message'] = trim($_POST['message'] ?? '');

    if ($old['name'] === '')    $errors['name']    = 'Name required';
    if ($old['email'] === '')   $errors['email']   = 'Email required';
    elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email';
    if ($old['subject'] === '') $errors['subject'] = 'Subject required';
    if (strlen($old['subject']) > 200) $errors['subject'] = 'Subject too long (200 chars max)';
    if ($old['message'] === '') $errors['message'] = 'Message required';
    if (strlen($old['message']) < 10) $errors['message'] = 'Message too short (min 10 chars)';

    // Simple rate limit: max 3 submissions per hour from same IP
    $pdo = db();
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    if ($ip) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM contact_messages
             WHERE ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$ip]);
        if ((int)$stmt->fetchColumn() >= 3) {
            $errors['general'] = 'Too many submissions. Please wait an hour before trying again.';
        }
    }

    if (empty($errors)) {
        try {
            // Save to database
            $stmt = $pdo->prepare("
                INSERT INTO contact_messages (name, email, subject, message, user_id, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $old['name'],
                $old['email'],
                $old['subject'],
                $old['message'],
                is_logged_in() ? current_user_id() : null,
                $ip,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);

            $messageId = (int)$pdo->lastInsertId();

            // Email notification to admin
            $adminEmail = 'admin@rentbridge.local'; // fallback if no admin user
            $stmt = $pdo->query("SELECT email FROM users WHERE primary_role = 'admin' ORDER BY id ASC LIMIT 1");
            $adminRow = $stmt->fetch();
            if ($adminRow) {
                $adminEmail = $adminRow['email'];
            }

            $emailHtml = "
                <h3>New contact form submission</h3>
                <table style='border-collapse: collapse; width: 100%;'>
                    <tr><td style='padding: 8px; background: #F4F4EE; width: 120px;'><strong>From:</strong></td><td style='padding: 8px;'>" . e($old['name']) . " &lt;" . e($old['email']) . "&gt;</td></tr>
                    <tr><td style='padding: 8px; background: #F4F4EE;'><strong>Subject:</strong></td><td style='padding: 8px;'>" . e($old['subject']) . "</td></tr>
                    <tr><td style='padding: 8px; background: #F4F4EE;'><strong>Message ID:</strong></td><td style='padding: 8px;'>#" . $messageId . "</td></tr>
                </table>
                <div style='margin-top: 16px; padding: 16px; background: #FAF8F3; border-left: 4px solid #2E8B57;'>
                    " . nl2br(e($old['message'])) . "
                </div>
                <p style='color: #666; font-size: 12px; margin-top: 16px;'>
                    Reply to this message in the admin dashboard at /rentbridge/admin/messages.php
                </p>
            ";

            send_email(
                $adminEmail,
                'RentBridge Admin',
                '[RentBridge Contact] ' . $old['subject'],
                $emailHtml
            );

            // Also send confirmation to user
            $confirmHtml = "
                <p>Hi " . e($old['name']) . ",</p>
                <p>Thank you for contacting RentBridge. We've received your message and will respond within 1-2 business days.</p>
                <p><strong>Your message:</strong></p>
                <div style='padding: 12px; background: #F4F4EE; border-left: 3px solid #2E8B57;'>
                    " . nl2br(e($old['message'])) . "
                </div>
                <p>Reference: #" . $messageId . "</p>
                <p>— The RentBridge team</p>
            ";
            send_email(
                $old['email'],
                $old['name'],
                'We received your message — RentBridge',
                $confirmHtml
            );

            $success = true;
            // Clear form
            $old = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
        } catch (Throwable $e) {
            $errors['general'] = 'Submit failed: ' . $e->getMessage();
        }
    }
}

ob_start();
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="text-center mb-4">
            <h1 style="font-family:'Fraunces',serif;">Contact us</h1>
            <p class="text-secondary">
                Questions, feedback, or report an issue — we'll get back to you.
            </p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success border-success">
                <h5 class="mb-2"><i class="bi bi-check-circle-fill me-1"></i> Message sent successfully</h5>
                <p class="mb-0">
                    We've received your message and sent a confirmation to your email.
                    We'll respond within 1-2 business days.
                </p>
            </div>
            <div class="text-center mt-3">
                <a href="/rentbridge/" class="btn btn-outline-primary">Back to home</a>
            </div>
        <?php else: ?>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger"><?= e($errors['general']) ?></div>
            <?php endif; ?>

            <form method="POST" class="bg-white border rounded-3 p-4">
                <?= csrf_field() ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Your name <small class="text-danger">*</small></label>
                        <input type="text" name="name"
                               class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                               value="<?= e($old['name']) ?>" required>
                        <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email <small class="text-danger">*</small></label>
                        <input type="email" name="email"
                               class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                               value="<?= e($old['email']) ?>" required>
                        <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Subject <small class="text-danger">*</small></label>
                        <input type="text" name="subject" maxlength="200"
                               class="form-control <?= isset($errors['subject']) ? 'is-invalid' : '' ?>"
                               value="<?= e($old['subject']) ?>" placeholder="e.g. Question about contract" required>
                        <?php if (isset($errors['subject'])): ?><div class="invalid-feedback"><?= e($errors['subject']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Message <small class="text-danger">*</small></label>
                        <textarea name="message" rows="6"
                                  class="form-control <?= isset($errors['message']) ? 'is-invalid' : '' ?>"
                                  placeholder="Tell us how we can help..." required><?= e($old['message']) ?></textarea>
                        <?php if (isset($errors['message'])): ?><div class="invalid-feedback"><?= e($errors['message']) ?></div><?php endif; ?>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-4">
                    <small class="text-secondary">
                        We'll never share your email. Read our
                        <a href="/rentbridge/faq.php" class="text-decoration-none">FAQ</a> first for quick answers.
                    </small>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-send me-1"></i> Send message
                    </button>
                </div>
            </form>

            <div class="text-center mt-4 text-secondary small">
                <i class="bi bi-shield-check"></i>
                Your message goes directly to the RentBridge admin team.
                We typically respond within 1-2 business days.
            </div>

        <?php endif; ?>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
if (is_logged_in()) {
    $role = current_role();
    $layoutFile = match($role) {
        'student'  => 'student_layout.php',
        'landlord' => 'landlord_layout.php',
        'agent'    => 'agent_layout.php',
        'admin'    => 'admin_layout.php',
        default    => 'public_layout.php',
    };
    require __DIR__ . '/includes/' . $layoutFile;
} else {
    require __DIR__ . '/includes/public_layout.php';
}