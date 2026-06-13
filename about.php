<?php
require_once __DIR__ . '/includes/auth.php';

// Different layout depending on logged-in state
if (is_logged_in()) {
    $pageTitle = 'About RentBridge';
    $activeNav = 'about';

    ob_start();
    ?>

    <div class="bg-white border rounded-3 p-4 mb-4">
        <h2 style="font-family:'Fraunces',serif;">About RentBridge</h2>
        <p class="text-secondary">
            RentBridge is a trust-first rental platform built for university students in Malaysia.
        </p>

        <h5 class="mt-4">Our mission</h5>
        <p>
            Renting student housing in Malaysia can be risky — fake listings, unverified landlords,
            no recourse if things go wrong. RentBridge solves this by adding a trusted university-staff
            agent to every tenancy. The agent physically verifies the property, witnesses the contract,
            and protects both students and landlords.
        </p>

        <h5 class="mt-4">How it works</h5>
        <ol>
            <li>Students browse verified properties near UTeM.</li>
            <li>Chat directly with landlords or agents.</li>
            <li>When ready, agent inspects the property and witnesses the contract.</li>
            <li>All three parties sign — student, landlord, and agent.</li>
            <li>Move in with confidence.</li>
        </ol>

        <h5 class="mt-4">Trust model</h5>
        <p>
            Every tenancy involves three verified parties: a registered student (UTeM matric),
            a verified landlord (IC + property ownership confirmed), and a UTeM staff agent
            (BOVAEP-compliant). All chat history is preserved. All contracts are digitally signed
            with audit trails.
        </p>

        <p class="text-secondary small mt-4 mb-0">
            RentBridge is a Final Year Project at Universiti Teknikal Malaysia Melaka (UTeM).
        </p>
    </div>

    <?php
    $pageContent = ob_get_clean();
    require __DIR__ . '/includes/student_layout.php';
} else {
    // Public version — use existing public header
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>About · RentBridge</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link href="/rentbridge/assets/css/style.css" rel="stylesheet">
    </head>
    <body>
        <?php include 'includes/header.php'; ?>
        <div class="container py-5">
            <h1 style="font-family:'Fraunces',serif;">About RentBridge</h1>
            <p class="lead">A trust-first rental platform for university students in Malaysia.</p>
            <p>RentBridge connects students, landlords, and verified UTeM staff agents to ensure every rental is safe and witnessed.</p>
            <a href="/rentbridge/auth/login.php" class="btn btn-primary">Sign in to learn more</a>
        </div>
    </body>
    </html>
    <?php
}