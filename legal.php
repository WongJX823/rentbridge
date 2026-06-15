<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Legal — Privacy & Terms';
$activeNav = 'legal';
ob_start();
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <h1 style="font-family:'Fraunces',serif;">Legal</h1>
        <p class="text-secondary">Last updated: <?= date('F Y') ?></p>

        <ul class="nav nav-pills mb-4">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#privacyPanel">Privacy Policy</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#termsPanel">Terms of Use</a>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="privacyPanel">
                <h4>What we collect</h4>
                <p>RentBridge collects only the information necessary to provide our rental platform service: name, contact details, NRIC (for landlords and tenancy contracts), property information, and tenancy history.</p>
                <h4>How we use it</h4>
                <p>Personal information is used solely for facilitating the rental process — connecting students with landlords, agent verification, contract generation, and communication between parties.</p>
                <h4>Who sees what</h4>
                <ul>
                    <li>Property documents (ownership proof, IC copies) are visible ONLY to the landlord, assigned agent, and admin.</li>
                    <li>Students never see private landlord documents.</li>
                    <li>NRIC numbers appear on tenancy contracts but are not displayed on the public verification page.</li>
                </ul>
                <h4>Data retention</h4>
                <p>Tenancy contracts are retained for legal record-keeping per Malaysian regulations.</p>
                <h4>Security</h4>
                <p>Passwords are hashed using bcrypt. Password changes require email verification.</p>
            </div>

            <div class="tab-pane fade" id="termsPanel">
                <h4>Platform purpose</h4>
                <p>RentBridge is a marketplace facilitating connections between UTeM students, property landlords, and verified UTeM-staff agents. We are not a party to any tenancy contract.</p>
                <h4>User obligations</h4>
                <ul>
                    <li>Provide accurate information during registration and property listing.</li>
                    <li>Comply with all signed tenancy agreements.</li>
                    <li>Report disputes through the platform's chat or contact form.</li>
                </ul>
                <h4>Commission</h4>
                <p>Successful tenancies incur a commission paid by the landlord equivalent to 1 month rent + 6% SST, per BOVAEP commission guidelines.</p>
                <h4>Disclaimer</h4>
                <p>RentBridge facilitates introductions but is not liable for property condition, landlord conduct, or tenant behavior beyond the platform's verification scope.</p>
            </div>
        </div>
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