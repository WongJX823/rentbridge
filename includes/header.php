<?php
// Make sure auth helpers are loaded (so is_logged_in / current_role work)
require_once __DIR__ . '/auth.php';
?>
<!-- This is the RentBridge navbar — included on every page -->
<nav class="rb-navbar navbar navbar-expand-lg">
    <div class="container">

        <!-- Brand (logo + name) -->
        <a class="navbar-brand" href="/rentbridge/index.php">
            <span class="brand-mark">R</span>
            RentBridge
        </a>

        <!-- Mobile hamburger button -->
        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#rbNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Nav links + buttons -->
        <div class="collapse navbar-collapse" id="rbNav">

            <ul class="navbar-nav me-auto ms-lg-4">
                <li class="nav-item">
                    <a class="nav-link" href="#listings">Browse</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#how">How it works</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#trust">Why RentBridge</a>
                </li>
            </ul>

            <?php if (is_logged_in()):
                require_once __DIR__ . '/chat.php';
                $unreadCount = unread_message_count(current_user_id());
            ?>
                <a href="/rentbridge/chat.php" class="btn btn-ghost position-relative me-2">
                    <i class="bi bi-chat-dots"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                            <?= $unreadCount > 99 ? '99+' : $unreadCount ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <div class="d-flex gap-2">
                <?php if (is_logged_in()): ?>
                    <a class="btn btn-outline-light" href="/rentbridge/<?= e(current_role()) ?>/dashboard.php">
                        <i class="bi bi-person-circle me-1"></i> <?= e(current_user_display_name()) ?>
                    </a>
                    <a class="btn btn-success" href="/rentbridge/auth/logout.php">
                        Sign out
                    </a>
                <?php else: ?>
                    <a class="btn btn-outline-light" href="/rentbridge/auth/login.php">Log in</a>
                    <a class="btn btn-success" href="/rentbridge/auth/register.php">Register</a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</nav>