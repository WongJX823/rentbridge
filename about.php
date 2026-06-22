<?php
require_once __DIR__ . '/includes/auth.php';

$pageTitle     = 'About RentBridge';
$activeNav     = 'about';
$showPageTitle = false;

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

    <?php if (!is_logged_in()): ?>
        <div class="mt-4 pt-3 border-top">
            <p class="mb-3">Ready to get started?</p>
            <a href="/rentbridge/auth/register.php" class="btn btn-primary me-2">
                <i class="bi bi-person-plus me-1"></i> Register
            </a>
            <a href="/rentbridge/auth/login.php" class="btn btn-outline-primary">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
            </a>
        </div>
    <?php endif; ?>

    <p class="text-secondary small mt-4 mb-0">
        RentBridge is a Final Year Project at Universiti Teknikal Malaysia Melaka (UTeM).
    </p>
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