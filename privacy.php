<?php
require_once __DIR__ . '/includes/auth.php';

$pageTitle     = 'Privacy & Security';
$activeNav     = 'privacy';
$showPageTitle = false;
$lastUpdated  = 'June 2026';

ob_start();
?>

<style>
.policy-doc { max-width: 780px; margin: 0 auto; }
.policy-doc h2 {
    font-family: 'Fraunces', serif;
    font-size: 1.15rem;
    color: var(--rb-navy);
    margin-top: 36px;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 2px solid var(--rb-line);
}
.policy-doc h3 {
    font-family: 'Manrope', sans-serif;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--rb-navy);
    margin-top: 20px;
    margin-bottom: 6px;
}
.policy-doc p, .policy-doc li {
    font-size: 0.9rem;
    color: #2F3C4E;
    line-height: 1.75;
}
.policy-doc ul, .policy-doc ol { padding-left: 1.5rem; margin-bottom: 12px; }
.policy-toc {
    background: #F4F4EE;
    border-radius: 10px;
    padding: 20px 24px;
    margin-bottom: 36px;
}
.policy-toc h5 { font-size: 0.85rem; font-weight: 700; margin-bottom: 10px; color: var(--rb-navy); }
.policy-toc a  { display: block; font-size: 0.82rem; color: var(--rb-text-soft); padding: 2px 0; text-decoration: none; }
.policy-toc a:hover { color: var(--rb-navy); }
.policy-chip {
    display: inline-block;
    background: #E4F2EA;
    color: var(--rb-emerald);
    border-radius: 999px;
    padding: 2px 12px;
    font-size: 0.75rem;
    font-weight: 700;
    margin-bottom: 4px;
}
.policy-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
    margin-bottom: 16px;
}
.policy-table th {
    background: #E6ECF4;
    color: var(--rb-navy);
    padding: 8px 12px;
    text-align: left;
    font-weight: 700;
}
.policy-table td {
    padding: 8px 12px;
    border-bottom: 1px solid var(--rb-line);
    vertical-align: top;
}
.policy-table tr:last-child td { border-bottom: none; }
</style>

<div class="policy-doc">

    <!-- Header -->
    <div class="mb-4">
        <h1 style="font-family:'Fraunces',serif;">Privacy &amp; Security Policy</h1>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <span class="policy-chip">Last updated: <?= $lastUpdated ?></span>
            <span class="text-secondary small">Applicable law: Personal Data Protection Act 2010 (PDPA), Malaysia</span>
        </div>
    </div>

    <!-- Table of contents -->
    <div class="policy-toc">
        <h5><i class="bi bi-list-ul me-1"></i> Contents</h5>
        <div class="row g-0">
            <div class="col-md-6">
                <a href="#s1">1. Who we are</a>
                <a href="#s2">2. Information we collect</a>
                <a href="#s3">3. How we use your information</a>
                <a href="#s4">4. Who sees what</a>
                <a href="#s5">5. Data sharing &amp; third parties</a>
            </div>
            <div class="col-md-6">
                <a href="#s6">6. Data retention</a>
                <a href="#s7">7. Security measures</a>
                <a href="#s8">8. Your rights (PDPA 2010)</a>
                <a href="#s9">9. Cookies &amp; local storage</a>
                <a href="#s10">10. Children's privacy</a>
                <a href="#s11">11. Changes &amp; contact</a>
            </div>
        </div>
    </div>

    <p>
        This Privacy &amp; Security Policy explains how <strong>RentBridge</strong>
        ("we", "us", "our", "the platform") collects, stores, uses, and protects
        personal data when you use our rental facilitation service. By using RentBridge,
        you agree to the practices described in this policy.
    </p>

    <!-- 1 -->
    <h2 id="s1">1. Who We Are</h2>
    <p>
        RentBridge is a digital property rental platform developed for the community of
        Universiti Teknikal Malaysia Melaka (UTeM). The platform is operated under the
        supervision of UTeM and serves students, property landlords, and UTeM staff agents.
    </p>
    <p>
        For data protection purposes, RentBridge is the <strong>data processor</strong>
        and UTeM is the <strong>data controller</strong> under the PDPA 2010.
        Queries about your personal data may be directed via the
        <a href="/rentbridge/contact.php">Feedback &amp; Contact</a> page.
    </p>

    <!-- 2 -->
    <h2 id="s2">2. Information We Collect</h2>

    <h3>2.1 Information you provide directly</h3>
    <table class="policy-table">
        <thead><tr><th>Data type</th><th>Who provides it</th><th>Purpose</th></tr></thead>
        <tbody>
            <tr><td>Full name &amp; preferred name</td><td>All users</td><td>Account identification &amp; display</td></tr>
            <tr><td>Email address</td><td>All users</td><td>Login, notifications</td></tr>
            <tr><td>Password (bcrypt hash)</td><td>All users</td><td>Authentication — raw password never stored</td></tr>
            <tr><td>Phone number</td><td>All users</td><td>Landlord/agent contact; WhatsApp option</td></tr>
            <tr><td>Matric number</td><td>Students</td><td>Student identity verification</td></tr>
            <tr><td>NRIC / IC number</td><td>Landlords, agents, contract co-tenants</td><td>Identity verification &amp; tenancy contracts</td></tr>
            <tr><td>Profile photo (avatar)</td><td>Optional — all roles</td><td>Platform personalisation</td></tr>
            <tr><td>Housing preferences</td><td>Students (optional)</td><td>Housemate matching &amp; listing recommendations</td></tr>
            <tr><td>Property details &amp; photos</td><td>Landlords</td><td>Listings &amp; agent inspection</td></tr>
            <tr><td>Ownership documents</td><td>Landlords</td><td>Verification — restricted access only</td></tr>
            <tr><td>Tenancy contract data</td><td>All parties to a contract</td><td>Legal record</td></tr>
            <tr><td>Chat messages</td><td>All users</td><td>Rental communication &amp; dispute records</td></tr>
            <tr><td>Feedback &amp; reports</td><td>Any user</td><td>Platform support &amp; safety</td></tr>
        </tbody>
    </table>

    <h3>2.2 Information collected automatically</h3>
    <ul>
        <li>Browser and device type (for UI compatibility — not sold or profiled).</li>
        <li>Session identifiers stored in encrypted PHP session cookies.</li>
        <li>Timestamps of logins, message sends, and key platform actions (for audit and dispute resolution).</li>
    </ul>

    <h3>2.3 What we do NOT collect</h3>
    <ul>
        <li>We do not track your browsing behaviour outside RentBridge.</li>
        <li>We do not collect payment card or banking details — all payments are handled offline between tenants and landlords.</li>
        <li>We do not use advertising trackers, fingerprinting, or analytics SDKs.</li>
    </ul>

    <!-- 3 -->
    <h2 id="s3">3. How We Use Your Information</h2>
    <ul>
        <li><strong>To provide the service</strong> — account management, property listings, agent assignment, tenancy contracts, and chat.</li>
        <li><strong>To verify identity</strong> — landlord IC and property documents are checked before listings go live; student matric numbers confirm enrolment.</li>
        <li><strong>To generate legal documents</strong> — tenancy contracts are produced from information supplied by all signing parties.</li>
        <li><strong>To send notifications</strong> — contract deadlines, inspection confirmations, application updates, and platform announcements.</li>
        <li><strong>To resolve disputes</strong> — message logs and timestamps are accessible to admins when a formal dispute is raised.</li>
        <li><strong>To improve the platform</strong> — aggregate, non-identifiable usage patterns (e.g., which cities see the most searches) may be reviewed internally.</li>
    </ul>
    <p>We do <strong>not</strong> use your data for targeted advertising, sell it to third parties, or share it with data brokers.</p>

    <!-- 4 -->
    <h2 id="s4">4. Who Sees What</h2>
    <table class="policy-table">
        <thead><tr><th>Data</th><th>Visible to</th><th>Not visible to</th></tr></thead>
        <tbody>
            <tr>
                <td>Student name, matric, profile photo</td>
                <td>Landlords &amp; agents they communicate with; admins</td>
                <td>Other students (unless in the same group chat)</td>
            </tr>
            <tr>
                <td>Landlord name, phone, email</td>
                <td>Assigned agent; students in active chat; admins</td>
                <td>General public; unrelated students</td>
            </tr>
            <tr>
                <td>Landlord IC / ownership documents</td>
                <td>Assigned agent; admins only</td>
                <td>Students — never</td>
            </tr>
            <tr>
                <td>NRIC on tenancy contracts</td>
                <td>Signing parties (student, landlord, agent); admins</td>
                <td>All other users</td>
            </tr>
            <tr>
                <td>Chat messages</td>
                <td>The two participants in that conversation (or group members); admins in disputes</td>
                <td>Anyone else</td>
            </tr>
            <tr>
                <td>Agent verification documents</td>
                <td>Admins only</td>
                <td>All other users</td>
            </tr>
            <tr>
                <td>Property listings (approved)</td>
                <td>All platform users including guests</td>
                <td>—</td>
            </tr>
        </tbody>
    </table>

    <!-- 5 -->
    <h2 id="s5">5. Data Sharing &amp; Third Parties</h2>
    <p>
        RentBridge does not sell, rent, or trade personal data with any third party for commercial purposes.
        Data may be disclosed in the following limited circumstances:
    </p>
    <ul>
        <li><strong>UTeM administration</strong> — as the platform operator and data controller, UTeM may access platform data for governance, auditing, or student welfare purposes.</li>
        <li><strong>Legal obligation</strong> — we may disclose data to Malaysian law enforcement or regulatory bodies when required by law (e.g., PDRM investigation, court order).</li>
        <li><strong>MCMC / PDPA authority</strong> — in response to lawful requests under the PDPA 2010 or the Communications and Multimedia Act 1998.</li>
        <li><strong>Hosting infrastructure</strong> — the platform runs on university-managed servers. The hosting provider has no independent access to user data.</li>
    </ul>

    <!-- 6 -->
    <h2 id="s6">6. Data Retention</h2>
    <table class="policy-table">
        <thead><tr><th>Data type</th><th>Retention period</th><th>Reason</th></tr></thead>
        <tbody>
            <tr><td>Active account data</td><td>Duration of active account</td><td>Service provision</td></tr>
            <tr><td>Tenancy contracts</td><td>7 years from contract end date</td><td>Malaysian tenancy law obligations</td></tr>
            <tr><td>Chat messages</td><td>3 years from conversation creation</td><td>Dispute resolution records</td></tr>
            <tr><td>Landlord ownership documents</td><td>Duration of landlord account + 2 years</td><td>Legal liability</td></tr>
            <tr><td>Deleted account data</td><td>90 days post-deletion (then purged)</td><td>Fraud prevention grace period</td></tr>
            <tr><td>System logs (timestamps, actions)</td><td>1 year rolling</td><td>Security &amp; audit</td></tr>
        </tbody>
    </table>
    <p>
        After the retention period, data is permanently deleted from all servers.
        Anonymised aggregate statistics (e.g., number of contracts per city) may be retained indefinitely.
    </p>

    <!-- 7 -->
    <h2 id="s7">7. Security Measures</h2>
    <h3>7.1 Authentication &amp; password security</h3>
    <ul>
        <li>All passwords are hashed using <strong>bcrypt</strong> with a cost factor of 12. The raw password is never stored or logged.</li>
        <li>Session tokens are regenerated on login to prevent session fixation attacks.</li>
        <li>CSRF tokens are embedded in every form — requests without a valid token are rejected.</li>
    </ul>
    <h3>7.2 File &amp; upload security</h3>
    <ul>
        <li>Uploaded files (property photos, documents) are stored in a directory outside the public web root and served through access-controlled PHP endpoints.</li>
        <li>File types are validated server-side; executable file uploads are blocked.</li>
        <li>Uploaded file names are randomised to prevent enumeration.</li>
    </ul>
    <h3>7.3 Database security</h3>
    <ul>
        <li>All database queries use <strong>PDO prepared statements</strong>. SQL injection is structurally prevented.</li>
        <li>Database credentials are stored in server-level environment configuration, not in source code.</li>
    </ul>
    <h3>7.4 Output &amp; XSS prevention</h3>
    <ul>
        <li>All user-supplied content is escaped using <code>htmlspecialchars()</code> before rendering. Cross-site scripting (XSS) attacks are prevented at the output layer.</li>
    </ul>
    <h3>7.5 Incident response</h3>
    <p>
        In the event of a data breach affecting personal data, affected users will be notified
        within 72 hours of discovery in line with PDPA best-practice guidelines. Incidents
        will be reported to the relevant UTeM data protection officer.
    </p>

    <!-- 8 -->
    <h2 id="s8">8. Your Rights Under PDPA 2010</h2>
    <p>
        Under the Personal Data Protection Act 2010 (Malaysia), you have the following rights
        regarding your personal data processed by RentBridge:
    </p>
    <ul>
        <li><strong>Right of access</strong> — You may request a copy of personal data we hold about you.</li>
        <li><strong>Right of correction</strong> — You may request correction of inaccurate personal data.</li>
        <li><strong>Right to withdraw consent</strong> — You may withdraw consent to processing where processing is consent-based. Note that withdrawing consent may limit your ability to use certain platform features.</li>
        <li><strong>Right to prevent processing for direct marketing</strong> — RentBridge does not use your data for direct marketing; this right is not applicable.</li>
        <li><strong>Right to erasure</strong> — You may request deletion of your account and associated personal data, subject to retention obligations outlined in Section 6.</li>
    </ul>
    <p>
        To exercise any of these rights, contact us via the
        <a href="/rentbridge/contact.php">Feedback &amp; Contact</a> page with the subject line
        "PDPA Data Request". We will respond within <strong>14 working days</strong>.
    </p>

    <!-- 9 -->
    <h2 id="s9">9. Cookies &amp; Local Storage</h2>
    <ul>
        <li><strong>Session cookie (<code>PHPSESSID</code>)</strong> — Used solely for login state. Expires when you close your browser or log out. No tracking or advertising data is stored in this cookie.</li>
        <li><strong>Sidebar preference (<code>rb-user-sidebar</code>)</strong> — Stored in <code>localStorage</code> to remember whether you collapsed the sidebar. Contains no personal information.</li>
        <li><strong>No third-party cookies</strong> — We do not load Google Analytics, Facebook Pixel, or any other third-party tracker.</li>
    </ul>

    <!-- 10 -->
    <h2 id="s10">10. Children's Privacy</h2>
    <p>
        RentBridge is intended for use by university students (age 18 and above) and adult
        landlords. We do not knowingly collect personal data from individuals under 18 years
        of age. If you believe a minor has created an account, please contact us immediately
        via the <a href="/rentbridge/contact.php">Feedback &amp; Contact</a> page and we will
        delete the account promptly.
    </p>

    <!-- 11 -->
    <h2 id="s11">11. Changes to This Policy &amp; Contact</h2>
    <p>
        We may update this Privacy &amp; Security Policy from time to time. When we make
        material changes, we will post a notice on the platform and update the "Last updated"
        date at the top of this page. Continued use of RentBridge after changes are posted
        constitutes acceptance of the updated policy.
    </p>
    <p>
        For any privacy-related queries or data access requests, contact us through the
        <a href="/rentbridge/contact.php">Feedback &amp; Contact</a> page.
        For matters specifically related to Malaysian data protection law, you may also
        contact the <strong>Department of Personal Data Protection</strong>
        (Jabatan Perlindungan Data Peribadi) at
        <a href="https://www.pdp.gov.my" target="_blank" rel="noopener">www.pdp.gov.my</a>.
    </p>

    <div class="mt-4 pt-4 border-top small text-secondary">
        <p class="mb-1">© <?= date('Y') ?> RentBridge · Universiti Teknikal Malaysia Melaka</p>
        <p class="mb-0">
            <a href="/rentbridge/legal.php" class="text-secondary me-3">Terms &amp; Conditions</a>
            <a href="/rentbridge/how_it_works.php#safety" class="text-secondary">Policy &amp; Safety</a>
        </p>
    </div>

</div>

<?php
$pageContent = ob_get_clean();
if (is_logged_in()) {
    $layoutFile = match(current_role()) {
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
