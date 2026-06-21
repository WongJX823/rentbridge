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
                <a href="/rentbridge/chat.php"
                    class="position-relative me-3 d-inline-flex align-items-center justify-content-center"
                    style="width: 40px; height: 40px; color: white; border-radius: 50%;
                            text-decoration: none; transition: background 0.15s;"
                    onmouseover="this.style.background='rgba(255,255,255,0.15)'"
                    onmouseout="this.style.background='transparent'"
                    title="Messages">
                    <i class="bi bi-chat-dots" style="font-size: 1.25rem;"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="position-absolute translate-middle badge rounded-pill bg-danger"
                            style="top: 8px; right: -4px; font-size: 0.65rem;">
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
                    <a class="btn btn-success" href="/rentbridge/auth/login.php">Log in</a>
                    <a class="btn btn-outline-light" href="/rentbridge/auth/register.php">Register</a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</nav>