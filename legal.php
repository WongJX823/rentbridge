<?php
require_once __DIR__ . '/includes/auth.php';

$pageTitle   = 'Terms & Conditions';
$activeNav   = 'legal';
$lastUpdated = 'June 2026';

ob_start();
?>

<style>
.legal-doc { max-width: 780px; margin: 0 auto; }
.legal-doc h2 {
    font-family: 'Fraunces', serif;
    font-size: 1.15rem;
    color: var(--rb-navy);
    margin-top: 40px;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 2px solid var(--rb-line);
}
.legal-doc h3 {
    font-family: 'Manrope', sans-serif;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--rb-navy);
    margin-top: 20px;
    margin-bottom: 6px;
}
.legal-doc p, .legal-doc li {
    font-size: 0.9rem;
    color: #2F3C4E;
    line-height: 1.8;
}
.legal-doc ul, .legal-doc ol { padding-left: 1.5rem; margin-bottom: 12px; }
.legal-toc {
    background: #F4F4EE;
    border-radius: 10px;
    padding: 20px 24px;
    margin-bottom: 36px;
}
.legal-toc h5 { font-size: 0.85rem; font-weight: 700; margin-bottom: 10px; color: var(--rb-navy); }
.legal-toc a  { display: block; font-size: 0.82rem; color: var(--rb-text-soft); padding: 2px 0; text-decoration: none; }
.legal-toc a:hover { color: var(--rb-navy); }
.legal-chip {
    display: inline-block;
    background: #E6ECF4;
    color: var(--rb-navy);
    border-radius: 999px;
    padding: 2px 12px;
    font-size: 0.75rem;
    font-weight: 700;
    margin-bottom: 4px;
}
.legal-box {
    background: #FFF4D6;
    border: 1px solid #D4A017;
    border-radius: 10px;
    padding: 16px 20px;
    margin-bottom: 16px;
    font-size: 0.88rem;
    color: #5A3E0A;
}
</style>

<div class="legal-doc">

    <!-- Header -->
    <div class="mb-4">
        <h1 style="font-family:'Fraunces',serif;">Terms &amp; Conditions</h1>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <span class="legal-chip">Last updated: <?= $lastUpdated ?></span>
            <span class="text-secondary small">Governed by the laws of Malaysia</span>
        </div>
    </div>

    <!-- TOC -->
    <div class="legal-toc">
        <h5><i class="bi bi-list-ul me-1"></i> Contents</h5>
        <div class="row g-0">
            <div class="col-md-6">
                <a href="#t1">1. Introduction &amp; acceptance</a>
                <a href="#t2">2. Definitions</a>
                <a href="#t3">3. Platform description</a>
                <a href="#t4">4. Eligibility</a>
                <a href="#t5">5. User accounts</a>
                <a href="#t6">6. Student obligations</a>
                <a href="#t7">7. Landlord obligations</a>
                <a href="#t8">8. Agent obligations</a>
                <a href="#t9">9. Property listings</a>
                <a href="#t10">10. Inspection process</a>
            </div>
            <div class="col-md-6">
                <a href="#t11">11. Tenancy contracts</a>
                <a href="#t12">12. Commission &amp; fees</a>
                <a href="#t13">13. Communication &amp; conduct</a>
                <a href="#t14">14. Intellectual property</a>
                <a href="#t15">15. Prohibited conduct</a>
                <a href="#t16">16. Dispute resolution</a>
                <a href="#t17">17. Limitation of liability</a>
                <a href="#t18">18. Indemnification</a>
                <a href="#t19">19. Suspension &amp; termination</a>
                <a href="#t20">20. Governing law &amp; amendments</a>
            </div>
        </div>
    </div>

    <div class="legal-box">
        <strong><i class="bi bi-info-circle me-1"></i> Please read carefully.</strong>
        By creating a RentBridge account or using any feature of the platform, you acknowledge that
        you have read, understood, and agree to be bound by these Terms &amp; Conditions.
        If you do not agree, you must not use the platform.
    </div>

    <!-- 1 -->
    <h2 id="t1">1. Introduction &amp; Acceptance</h2>
    <p>
        These Terms &amp; Conditions ("Terms") govern your use of the <strong>RentBridge</strong>
        platform ("Platform", "Service"), a digital rental facilitation service developed under
        Universiti Teknikal Malaysia Melaka ("UTeM"). The Platform is operated by UTeM for the
        benefit of its student community, registered landlords, and UTeM staff agents.
    </p>
    <p>
        These Terms apply to all users of the Platform, including students, landlords, agents, and
        administrators. Use of the Platform constitutes acceptance of these Terms in their entirety.
        UTeM reserves the right to amend these Terms at any time; continued use after amendments
        are published constitutes acceptance of the revised Terms.
    </p>

    <!-- 2 -->
    <h2 id="t2">2. Definitions</h2>
    <ul>
        <li><strong>"Platform" / "RentBridge"</strong> — the web application accessible at the UTeM RentBridge URL and all associated features.</li>
        <li><strong>"Student"</strong> — a registered UTeM student with an active matric number, using the Platform to find and rent accommodation.</li>
        <li><strong>"Landlord"</strong> — an individual or entity who has registered to list residential properties on the Platform for rent.</li>
        <li><strong>"Agent"</strong> — a UTeM staff member registered and verified to conduct property inspections and facilitate tenancy contracts.</li>
        <li><strong>"Admin"</strong> — a UTeM employee with administrative access to the Platform for governance and moderation.</li>
        <li><strong>"Listing"</strong> — a property entry on the Platform, including all associated photos, descriptions, and documents.</li>
        <li><strong>"Tenancy Contract"</strong> — a digital agreement between a Student and a Landlord, facilitated by an Agent, governing the rental of a property.</li>
        <li><strong>"Commission"</strong> — the platform facilitation fee equivalent to one (1) month's base monthly rent, charged upon successful contract execution.</li>
        <li><strong>"Inspection"</strong> — the physical visit to a property conducted by an assigned Agent prior to listing approval.</li>
    </ul>

    <!-- 3 -->
    <h2 id="t3">3. Platform Description</h2>
    <p>
        RentBridge is a <strong>marketplace platform</strong> that facilitates connections between UTeM
        students seeking accommodation and landlords offering residential properties. RentBridge is
        <strong>not a party to any tenancy contract</strong> and does not own, manage, or control
        any listed property.
    </p>
    <p>
        The Platform provides tools for property discovery, landlord–student communication, agent-led
        property inspection, digital contract preparation and e-signing, and post-contract notifications.
        Actual rental payments, utility arrangements, and property access occur entirely outside the
        Platform between the relevant parties.
    </p>

    <!-- 4 -->
    <h2 id="t4">4. Eligibility</h2>
    <h3>4.1 Students</h3>
    <p>
        To register as a Student, you must be an active UTeM student with a valid matric number.
        You must be at least 18 years of age. Providing a false matric number or impersonating
        another student is grounds for immediate account suspension and may constitute a criminal offence.
    </p>
    <h3>4.2 Landlords</h3>
    <p>
        To register as a Landlord, you must be an individual or entity legally entitled to offer the
        listed property for rental. You must provide a valid Malaysian IC or company registration
        number. Entities representing corporations must have authority to bind the corporation.
    </p>
    <h3>4.3 Agents</h3>
    <p>
        Agents must be current UTeM staff members. Registration is subject to admin verification
        of employment status. Agents must not hold a concurrent Landlord account on the Platform.
    </p>

    <!-- 5 -->
    <h2 id="t5">5. User Accounts</h2>
    <ul>
        <li>You are responsible for maintaining the confidentiality of your login credentials. Do not share your password with anyone.</li>
        <li>You must immediately notify UTeM if you suspect unauthorized access to your account via the <a href="/rentbridge/contact.php">Feedback &amp; Contact</a> page.</li>
        <li>Each person may hold only one account per role. Creating duplicate accounts to circumvent restrictions is prohibited.</li>
        <li>Account information must be accurate and kept up to date. Outdated contact information that causes notification failures is your responsibility.</li>
        <li>UTeM reserves the right to verify account information at any time and to suspend accounts that cannot be verified.</li>
    </ul>

    <!-- 6 -->
    <h2 id="t6">6. Student Obligations</h2>
    <ul>
        <li>Provide accurate personal information during registration and when completing tenancy application forms.</li>
        <li>Comply fully with the terms of any signed Tenancy Contract, including timely rent payment and adherence to house rules.</li>
        <li>Conduct all initial communications with landlords through the Platform's messaging system, not via external channels arranged independently.</li>
        <li>Not engage in co-tenancy arrangements (housemate groups) outside of what is disclosed in the Tenancy Contract.</li>
        <li>Notify the assigned Agent of any significant change to tenancy circumstances (e.g., early termination, additional occupants) through the Platform.</li>
        <li>Not use the Platform's housemate-matching feature to arrange subletting or commercial accommodation-sharing.</li>
        <li>Report fraudulent listings, suspicious landlord behaviour, or safety concerns via the Feedback &amp; Contact page.</li>
    </ul>

    <!-- 7 -->
    <h2 id="t7">7. Landlord Obligations</h2>
    <ul>
        <li>Provide truthful, accurate, and complete property information in all listings. Misrepresentation (false photos, inflated room counts, incorrect location) constitutes fraud.</li>
        <li>Upload valid and current proof of property ownership (geran tanah, sale and purchase agreement, or equivalent) for every listing submitted.</li>
        <li>Cooperate fully with the assigned Agent during the Inspection process — provide property access within a reasonable timeframe and respond to Agent communications promptly.</li>
        <li>Not list a property that is subject to a mortgage default, legal dispute, or court order preventing its rental.</li>
        <li>Not sublease a property to a Student if you yourself are a tenant without the written consent of the superior landlord/owner.</li>
        <li>Comply with Malaysian tenancy law, including provisions of the National Land Code 1965 and any applicable rent control regulations.</li>
        <li>Not request or accept deposits or advance payments before a Tenancy Contract is signed through the Platform.</li>
        <li>Notify the assigned Agent if a rented property becomes uninhabitable due to damage, hazards, or legal action.</li>
        <li>Ensure the listed monthly rent accurately reflects the agreed rental — undisclosed fees or charges added after contract signing are prohibited.</li>
    </ul>

    <!-- 8 -->
    <h2 id="t8">8. Agent Obligations</h2>
    <ul>
        <li>Conduct property inspections diligently, objectively, and in person. Remote or desk-based approvals without physical inspection are not permitted.</li>
        <li>File accurate and honest inspection reports. Approving a property that fails safety, documentation, or condition criteria is a serious breach of these Terms.</li>
        <li>Maintain confidentiality of all landlord documents, student information, and contract details accessed through the Platform.</li>
        <li>Not solicit gifts, payments, or benefits from Landlords or Students in connection with Platform activities.</li>
        <li>Not hold a financial interest in any property listed on the Platform.</li>
        <li>Prepare tenancy contracts that accurately reflect the agreed terms — do not alter key terms (rent, dates, deposit) without written agreement from both parties.</li>
        <li>Respond to assigned properties within the timeframe specified in Platform guidelines.</li>
        <li>Declare any conflict of interest (e.g., family member is the landlord) to an Admin before proceeding.</li>
    </ul>

    <!-- 9 -->
    <h2 id="t9">9. Property Listings</h2>
    <h3>9.1 Submission</h3>
    <p>
        Listings are submitted by Landlords and are only published to the public after passing the
        Agent inspection and receiving Admin approval. Pending listings are not visible to Students.
    </p>
    <h3>9.2 Accuracy</h3>
    <p>
        All listing content — photos, descriptions, amenities, address, rent, and deposit —
        must accurately represent the property at the time of listing. Landlords must update
        listings promptly if any material detail changes.
    </p>
    <h3>9.3 Prohibited listings</h3>
    <p>The following may not be listed on RentBridge:</p>
    <ul>
        <li>Properties located more than 20 km from UTeM main campus (Durian Tunggal) without Admin approval.</li>
        <li>Properties without a valid residential occupancy certificate.</li>
        <li>Short-term rentals intended for fewer than one academic semester.</li>
        <li>Properties for which the submitting Landlord does not hold rental authority.</li>
        <li>Properties subject to any legal restriction on rental.</li>
    </ul>
    <h3>9.4 Removal</h3>
    <p>
        UTeM may remove any listing at any time without notice if it violates these Terms,
        is found to be inaccurate, or is subject to a complaint under investigation.
    </p>

    <!-- 10 -->
    <h2 id="t10">10. Inspection Process</h2>
    <p>
        Every new property listing must undergo a physical inspection by an assigned Agent before
        it is published. The inspection process is as follows:
    </p>
    <ol>
        <li>The Landlord submits the listing; the system assigns it to the next available Agent.</li>
        <li>The Agent reviews submitted documents and contacts the Landlord to schedule an inspection date.</li>
        <li>The Landlord provides access to the property on the agreed date (either in person, lockbox code, or other arrangement disclosed in the chat).</li>
        <li>The Agent conducts a physical inspection of the property, checking condition, safety, utilities, and photo accuracy.</li>
        <li>The Agent marks the inspection complete and either approves (listing goes live) or rejects (Landlord is notified with a reason).</li>
    </ol>
    <p>
        A passed inspection does not constitute a warranty, guarantee, or representation by UTeM
        or the Agent of the property's condition. Inspections assess fitness for listing at a point
        in time and do not create ongoing liability.
    </p>
    <p>
        Landlords who obstruct, mislead, or fail to facilitate the inspection within 14 days of
        assignment may have their listing cancelled and account flagged for review.
    </p>

    <!-- 11 -->
    <h2 id="t11">11. Tenancy Contracts</h2>
    <h3>11.1 Contract structure</h3>
    <p>
        Tenancy contracts on RentBridge follow <strong>semester-based durations</strong> aligned
        with the UTeM academic calendar. Standard tenancies span <strong>1 or 2 semesters</strong>.
        The exact start and end dates are agreed between the Student and Landlord and recorded in the contract.
    </p>
    <h3>11.2 Contract execution</h3>
    <p>
        Contracts are prepared by the assigned Agent using details provided by the Student and Landlord.
        Both parties must e-sign the contract through the Platform. A PDF copy is generated and available
        to all signing parties and the assigned Agent.
    </p>
    <h3>11.3 Co-tenants</h3>
    <p>
        If a Student intends to co-rent with others (co-tenants), all co-tenants must be named
        and recorded in the Tenancy Contract. Undisclosed additional occupants are a breach of these Terms and may be grounds for tenancy termination by the Landlord.
    </p>
    <h3>11.4 Renewal &amp; expiry notifications</h3>
    <ul>
        <li>Students receive an in-platform notification <strong>4 months before</strong> the contract end date.</li>
        <li>Landlords with contracts of 2 or more semesters receive a notification <strong>2 months before</strong> the contract end date.</li>
        <li>These are reminders only — notification failure does not relieve either party of contractual obligations.</li>
    </ul>
    <h3>11.5 Early termination</h3>
    <p>
        Either party wishing to terminate the tenancy before the contract end date must notify the
        other party through the Platform chat and the assigned Agent. Early termination terms —
        including notice period and deposit forfeiture — are governed by the signed contract itself
        and applicable Malaysian tenancy law.
    </p>
    <h3>11.6 Platform role</h3>
    <p>
        RentBridge facilitates the preparation and digital execution of tenancy contracts but is
        <strong>not a party to the contract</strong>. Disputes arising from a tenancy agreement
        are between the Student and Landlord. UTeM's facilitation role does not make it a
        guarantor or co-obligor under any tenancy.
    </p>

    <!-- 12 -->
    <h2 id="t12">12. Commission &amp; Fees</h2>
    <p>
        A commission fee equal to <strong>one (1) month's base monthly rent</strong> is charged
        upon successful execution of a Tenancy Contract (i.e., both parties have e-signed).
    </p>
    <ul>
        <li><strong>70%</strong> of the commission is remitted to UTeM as a platform facilitation fee.</li>
        <li><strong>30%</strong> is credited to the assigned Agent as their professional service fee.</li>
    </ul>
    <p>
        The commission is invoiced to the Landlord. It is <strong>not refundable</strong> once a
        contract has been duly signed by both parties. If a signed contract is subsequently
        voided by mutual written agreement before the tenancy commencement date, a refund
        may be considered at UTeM's sole discretion.
    </p>
    <p>
        There are no fees charged to Students for using the Platform. No listing fee is charged
        to Landlords. The commission is the sole source of revenue from the Platform.
    </p>

    <!-- 13 -->
    <h2 id="t13">13. Communication &amp; Conduct</h2>
    <p>
        All communications between platform users regarding properties, bookings, and contracts
        must be conducted through the Platform's built-in messaging system. Arranging payments
        or agreements through external channels (WhatsApp, Telegram, email) does not create
        any obligation on UTeM and is at the user's own risk.
    </p>
    <p>Users must not, in any communication on the Platform:</p>
    <ul>
        <li>Use threatening, abusive, defamatory, or sexually explicit language.</li>
        <li>Share personal data of third parties without their consent.</li>
        <li>Attempt to direct other users to conduct transactions outside the Platform to avoid commission.</li>
        <li>Impersonate UTeM, RentBridge, or any other user.</li>
        <li>Send unsolicited bulk messages or spam.</li>
    </ul>
    <p>
        Messages are stored and may be reviewed by Admins in the event of a dispute, complaint,
        or safety concern. See our <a href="/rentbridge/privacy.php">Privacy &amp; Security Policy</a>
        for details on message data retention.
    </p>

    <!-- 14 -->
    <h2 id="t14">14. Intellectual Property</h2>
    <p>
        All intellectual property rights in the RentBridge platform — including software, design,
        logos, and user interface — vest in UTeM. You are granted a limited, non-exclusive,
        non-transferable licence to use the Platform for its intended purpose.
    </p>
    <p>
        By uploading photos, documents, or other content to the Platform, you grant UTeM a
        non-exclusive, royalty-free licence to store, display, and use that content solely for
        the purpose of operating the Platform (e.g., displaying property photos in search results).
        You confirm that you hold all necessary rights to the content you upload.
    </p>
    <p>
        You may not reproduce, copy, redistribute, or commercially exploit any part of the
        Platform without written permission from UTeM.
    </p>

    <!-- 15 -->
    <h2 id="t15">15. Prohibited Conduct</h2>
    <p>The following are strictly prohibited and may result in immediate account termination and/or legal action:</p>
    <ul>
        <li>Submitting false, misleading, or fraudulent property listings or documents.</li>
        <li>Collecting deposits or rent payments while not being the legitimate owner or authorized rental agent of the property.</li>
        <li>Using the Platform to facilitate illegal subletting.</li>
        <li>Attempting to access, modify, or disrupt the Platform's code, database, or server infrastructure (hacking, SQL injection, XSS, etc.).</li>
        <li>Creating fake student, landlord, or agent accounts.</li>
        <li>Sharing login credentials with other persons or entities.</li>
        <li>Using the Platform to recruit students into any commercial, multi-level-marketing, or fraudulent scheme.</li>
        <li>Circumventing the Platform's commission structure by agreeing to execute tenancy contracts outside the Platform after an introduction made through it.</li>
        <li>Uploading malware, scripts, or files intended to harm users or the Platform.</li>
    </ul>

    <!-- 16 -->
    <h2 id="t16">16. Dispute Resolution</h2>
    <h3>16.1 Platform-level disputes</h3>
    <p>
        Disputes between platform users (e.g., landlord vs. student regarding a listing,
        agent conduct complaints, fraudulent listing reports) should be submitted through the
        <a href="/rentbridge/contact.php">Feedback &amp; Contact</a> page. Admins will investigate
        and respond within <strong>5 working days</strong>.
    </p>
    <h3>16.2 Tenancy disputes</h3>
    <p>
        Disputes arising from a signed Tenancy Contract are primarily a matter between the
        Student and the Landlord under Malaysian tenancy law. RentBridge will, upon request,
        provide relevant message logs and contract records to support dispute resolution.
    </p>
    <p>
        Parties are encouraged to seek resolution through:
    </p>
    <ul>
        <li>Direct negotiation via the Platform chat, with the Agent as a facilitating party.</li>
        <li>The Tribunal for Consumer Claims Malaysia (Tribunal Tuntutan Pengguna Malaysia) for consumer-related disputes.</li>
        <li>The Malaysian courts as a last resort, subject to the governing law provisions in Clause 20.</li>
    </ul>
    <h3>16.3 No arbitration</h3>
    <p>
        UTeM does not operate a formal arbitration service and cannot act as an adjudicator
        between parties to a private tenancy contract.
    </p>

    <!-- 17 -->
    <h2 id="t17">17. Limitation of Liability</h2>
    <p>
        To the fullest extent permitted by applicable law, UTeM and RentBridge shall not be
        liable for:
    </p>
    <ul>
        <li>The accuracy or completeness of any property listing, landlord-supplied document, or user-generated content.</li>
        <li>The conduct, actions, or omissions of any Student, Landlord, or Agent using the Platform.</li>
        <li>Loss of rental opportunity, financial loss, or property damage arising from tenancy agreements entered into through the Platform.</li>
        <li>Fraudulent activity by users acting in breach of these Terms.</li>
        <li>Service interruptions, data loss, or platform downtime.</li>
    </ul>
    <p>
        RentBridge's total aggregate liability to any user for any claim arising out of or
        relating to these Terms shall not exceed the commission fee paid in connection with
        the specific transaction giving rise to the claim.
    </p>

    <!-- 18 -->
    <h2 id="t18">18. Indemnification</h2>
    <p>
        You agree to indemnify, defend, and hold harmless UTeM, RentBridge, and their
        respective officers, staff, and agents from and against any claims, damages, losses,
        costs, and expenses (including legal fees) arising out of or relating to:
    </p>
    <ul>
        <li>Your breach of these Terms.</li>
        <li>Your violation of any applicable law or third-party right.</li>
        <li>Any content you upload to the Platform that infringes the intellectual property or privacy rights of a third party.</li>
        <li>Any fraudulent activity you engage in through the Platform.</li>
    </ul>

    <!-- 19 -->
    <h2 id="t19">19. Suspension &amp; Termination</h2>
    <h3>19.1 By UTeM</h3>
    <p>
        UTeM reserves the right to suspend or permanently terminate any account, at any time
        and without prior notice, if there is reasonable belief of a breach of these Terms,
        fraudulent activity, or conduct harmful to the Platform community.
    </p>
    <p>
        Where termination is for cause (verified fraud, criminal conduct), no refund of
        commission fees will be made and UTeM reserves the right to report the matter to
        the relevant authorities.
    </p>
    <h3>19.2 By the user</h3>
    <p>
        You may request account deletion at any time through the
        <a href="/rentbridge/contact.php">Feedback &amp; Contact</a> page. Deletion requests
        will be processed within 14 working days. Note that data subject to legal retention
        obligations (see Privacy Policy, Section 6) will be retained for the required period
        even after account deletion.
    </p>
    <p>
        Account deletion does not extinguish obligations arising under a signed Tenancy Contract.
        Tenancy contracts remain legally binding between the parties independently of Platform
        account status.
    </p>

    <!-- 20 -->
    <h2 id="t20">20. Governing Law &amp; Amendments</h2>
    <h3>20.1 Governing law</h3>
    <p>
        These Terms are governed by and construed in accordance with the laws of Malaysia.
        Any dispute not resolved under Clause 16 shall be subject to the exclusive jurisdiction
        of the courts of Malaysia.
    </p>
    <h3>20.2 Amendments</h3>
    <p>
        UTeM may amend these Terms at any time by posting an updated version on the Platform.
        The "Last updated" date at the top of this page will be revised. Where changes are
        material, an in-platform notification will be sent to all registered users.
        Continued use of the Platform after the updated Terms are posted constitutes acceptance.
    </p>
    <h3>20.3 Severability</h3>
    <p>
        If any provision of these Terms is found to be unenforceable or invalid under applicable
        law, that provision shall be limited or eliminated to the minimum extent necessary so
        that the remaining Terms shall otherwise remain in full force and effect.
    </p>
    <h3>20.4 Contact</h3>
    <p>
        For any queries relating to these Terms, contact us via the
        <a href="/rentbridge/contact.php">Feedback &amp; Contact</a> page
        with the subject line "Terms Query".
    </p>

    <div class="mt-4 pt-4 border-top small text-secondary">
        <p class="mb-1">© <?= date('Y') ?> RentBridge · Universiti Teknikal Malaysia Melaka</p>
        <p class="mb-0">
            <a href="/rentbridge/privacy.php" class="text-secondary me-3">Privacy &amp; Security</a>
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
