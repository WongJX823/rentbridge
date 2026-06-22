<?php
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'How RentBridge Works';
$activeNav = 'how_it_works';

ob_start();
?>

<style>
.hiw-section { margin-bottom: 56px; }
.hiw-hero {
    background: linear-gradient(135deg, var(--rb-navy) 0%, #1a4a7a 100%);
    border-radius: 16px;
    padding: 48px;
    color: #fff;
    margin-bottom: 48px;
}
.hiw-hero h1 { color: #fff; font-family: 'Fraunces', serif; margin-bottom: 12px; }
.hiw-hero p  { color: rgba(255,255,255,0.82); max-width: 560px; margin: 0; }

.hiw-role-tab { display: flex; gap: 8px; margin-bottom: 32px; flex-wrap: wrap; }
.hiw-role-btn {
    border: 2px solid var(--rb-line);
    background: #fff;
    border-radius: 999px;
    padding: 6px 20px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--rb-text-soft);
    cursor: pointer;
    transition: all 0.15s;
}
.hiw-role-btn.active, .hiw-role-btn:hover {
    border-color: var(--rb-navy);
    color: var(--rb-navy);
    background: #E6ECF4;
}

.hiw-step-row { display: flex; gap: 0; flex-wrap: wrap; margin-bottom: 8px; }
.hiw-step {
    flex: 1;
    min-width: 140px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    position: relative;
}
.hiw-step:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 20px;
    left: 50%;
    width: 100%;
    height: 2px;
    background: var(--rb-line);
    z-index: 0;
}
.hiw-step-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--rb-navy);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
    position: relative;
    z-index: 1;
    flex-shrink: 0;
}
.hiw-step-circle.emerald { background: var(--rb-emerald); }
.hiw-step-circle.gold    { background: #C9921A; }
.hiw-step-label {
    margin-top: 8px;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--rb-navy);
    padding: 0 4px;
    line-height: 1.3;
}
.hiw-step-sub {
    font-size: 0.72rem;
    color: var(--rb-text-soft);
    margin-top: 4px;
    padding: 0 4px;
}

.hiw-detail-card {
    background: #fff;
    border: 1px solid var(--rb-line);
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 12px;
    display: flex;
    gap: 16px;
    align-items: flex-start;
}
.hiw-detail-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}

/* Scam section */
.scam-card {
    background: #fff;
    border: 1px solid #F5C6CB;
    border-left: 4px solid #DC3545;
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 12px;
}
.scam-card h6 { color: #7C1C24; font-weight: 700; margin-bottom: 6px; }
.scam-card p  { color: #4B2529; font-size: 0.88rem; margin: 0; }

.protect-card {
    background: #E4F2EA;
    border: 1px solid #A3D4B0;
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 12px;
    display: flex;
    gap: 14px;
    align-items: flex-start;
}
.protect-card i { color: var(--rb-emerald); font-size: 1.2rem; flex-shrink: 0; margin-top: 2px; }

.redflag-list { list-style: none; padding: 0; margin: 0; }
.redflag-list li {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding: 10px 0;
    border-bottom: 1px solid var(--rb-line);
    font-size: 0.88rem;
}
.redflag-list li:last-child { border-bottom: none; }
.redflag-list .flag-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #DC3545;
    flex-shrink: 0;
    margin-top: 5px;
}
</style>

<!-- Hero -->
<div class="hiw-hero">
    <p class="small mb-2" style="color:rgba(255,255,255,0.5); letter-spacing:0.1em; text-transform:uppercase;">Platform guide</p>
    <h1>How RentBridge Works</h1>
    <p>
        RentBridge is built for the UTeM community — every listing is agent-inspected,
        every contract is digital, and every scam is flagged before it reaches you.
    </p>
</div>

<!-- ============================================================
     SECTION 1 — ROLE FLOWS
     ============================================================ -->
<div class="hiw-section">
    <h2 class="mb-1">Step-by-step guide</h2>
    <p class="text-secondary mb-4">Choose your role to see your journey on RentBridge.</p>

    <div class="hiw-role-tab" id="roleTabBar">
        <button class="hiw-role-btn active" data-role="student">
            <i class="bi bi-mortarboard me-1"></i> Student
        </button>
        <button class="hiw-role-btn" data-role="landlord">
            <i class="bi bi-building me-1"></i> Landlord
        </button>
        <button class="hiw-role-btn" data-role="agent">
            <i class="bi bi-person-badge me-1"></i> Agent
        </button>
    </div>

    <!-- -------- STUDENT -------- -->
    <div id="flow-student" class="role-flow">
        <div class="hiw-step-row mb-4">
            <?php
            $studentSteps = [
                ['1','Register',       'Create a free account',   'navy'],
                ['2','Browse',         'Filter by city & budget', 'navy'],
                ['3','Shortlist',      'Save your favourites',    'navy'],
                ['4','Chat',           'Message the landlord',    'navy'],
                ['5','Apply',          'Submit tenancy form',     'navy'],
                ['6','Sign contract',  'E-sign from any device',  'emerald'],
                ['7','Move in',        'Start your tenancy',      'emerald'],
            ];
            foreach ($studentSteps as [$n, $label, $sub, $color]):
            ?>
            <div class="hiw-step">
                <div class="hiw-step-circle <?= $color ?>"><?= $n ?></div>
                <div class="hiw-step-label"><?= $label ?></div>
                <div class="hiw-step-sub"><?= $sub ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-3">
            <?php
            $studentDetail = [
                ['bi-person-plus',         '#E6ECF4', 'Register as student',
                 'Create a free account with your UTeM email. Your matric number is used to verify your student status — we never share it publicly.'],
                ['bi-search',              '#E4F2EA', 'Browse & filter listings',
                 'Search by city, property type, budget, and furnishing. Every live listing has been inspected by a RentBridge agent.'],
                ['bi-bookmark-heart',      '#FFF4D6', 'Shortlist properties',
                 'Save properties you like. Review them side by side before reaching out to landlords.'],
                ['bi-chat-dots',           '#E4F2EA', 'Chat with the landlord',
                 'Ask about included utilities, deposit terms, house rules, and move-in date. All messages are logged through the platform.'],
                ['bi-file-earmark-person', '#E6ECF4', 'Submit tenancy application',
                 'The landlord reviews your application. If approved, the assigned agent prepares the digital tenancy contract.'],
                ['bi-pen',                 '#E4F2EA', 'E-sign the contract',
                 'Sign electronically using a canvas signature. You and the landlord each receive a PDF copy.'],
                ['bi-house-heart',         '#FFF4D6', 'Move in & enjoy',
                 'Your dashboard tracks your contract dates. You\'ll receive a reminder 4 months before your contract ends.'],
            ];
            foreach ($studentDetail as [$icon, $bg, $title, $desc]):
            ?>
            <div class="col-md-6">
                <div class="hiw-detail-card">
                    <div class="hiw-detail-icon" style="background:<?= $bg ?>;">
                        <i class="bi <?= $icon ?>" style="color:var(--rb-navy);"></i>
                    </div>
                    <div>
                        <strong class="d-block mb-1"><?= $title ?></strong>
                        <span class="small text-secondary"><?= $desc ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- -------- LANDLORD -------- -->
    <div id="flow-landlord" class="role-flow d-none">
        <div class="hiw-step-row mb-4">
            <?php
            $llSteps = [
                ['1','Register',      'Verify your identity',       'navy'],
                ['2','List property', 'Upload photos & details',    'navy'],
                ['3','Inspection',    'Agent visits the property',  'navy'],
                ['4','Go live',       'Listing appears publicly',   'emerald'],
                ['5','Receive inquiries','Chat with students',      'emerald'],
                ['6','Approve tenant','Accept the application',     'emerald'],
                ['7','Sign contract', 'E-sign & collect deposit',   'emerald'],
            ];
            foreach ($llSteps as [$n, $label, $sub, $color]):
            ?>
            <div class="hiw-step">
                <div class="hiw-step-circle <?= $color ?>"><?= $n ?></div>
                <div class="hiw-step-label"><?= $label ?></div>
                <div class="hiw-step-sub"><?= $sub ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-3">
            <?php
            $llDetail = [
                ['bi-person-vcard',        '#E6ECF4', 'Register as landlord',
                 'Provide your IC/passport and contact details. Admin verifies your identity before your first listing can be submitted.'],
                ['bi-house-add',           '#E4F2EA', 'List your property',
                 'Fill in property details, upload photos, and specify rent, deposit, and utilities. Include accurate access information for the inspection.'],
                ['bi-clipboard2-check',    '#FFF4D6', 'Agent inspection',
                 'A RentBridge agent contacts you to schedule an in-person inspection. You can be present or provide lockbox access. The agent files a condition report.'],
                ['bi-check-circle',        '#E4F2EA', 'Listing goes live',
                 'Once the agent approves, your property becomes publicly searchable. Only agent-verified listings are visible to students.'],
                ['bi-chat-dots',           '#E6ECF4', 'Respond to student inquiries',
                 'Students message you directly. You control who you accept — review profiles and chat before agreeing.'],
                ['bi-person-check',        '#E4F2EA', 'Approve a tenant',
                 'Accept a student\'s tenancy application. The agent then prepares the digital contract based on agreed terms.'],
                ['bi-pen',                 '#FFF4D6', 'Sign the contract',
                 'Both parties e-sign. You receive a commission invoice (1 month base rent; 70% goes to UTeM, 30% to your assigned agent). Rental payments happen outside the platform.'],
            ];
            foreach ($llDetail as [$icon, $bg, $title, $desc]):
            ?>
            <div class="col-md-6">
                <div class="hiw-detail-card">
                    <div class="hiw-detail-icon" style="background:<?= $bg ?>;">
                        <i class="bi <?= $icon ?>" style="color:var(--rb-navy);"></i>
                    </div>
                    <div>
                        <strong class="d-block mb-1"><?= $title ?></strong>
                        <span class="small text-secondary"><?= $desc ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- -------- AGENT -------- -->
    <div id="flow-agent" class="role-flow d-none">
        <div class="hiw-step-row mb-4">
            <?php
            $agSteps = [
                ['1','Assignment',      'System assigns property',     'navy'],
                ['2','Review docs',     'Check ownership proof',       'navy'],
                ['3','Schedule visit',  'Coordinate with landlord',    'navy'],
                ['4','Inspect',         'Visit & file report',         'navy'],
                ['5','Approve/Reject',  'Set listing live or reject',  'gold'],
                ['6','Contract prep',   'Prepare digital contract',    'emerald'],
                ['7','Earn commission', '30% of one month rent',       'emerald'],
            ];
            foreach ($agSteps as [$n, $label, $sub, $color]):
            ?>
            <div class="hiw-step">
                <div class="hiw-step-circle <?= $color ?>"><?= $n ?></div>
                <div class="hiw-step-label"><?= $label ?></div>
                <div class="hiw-step-sub"><?= $sub ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-3">
            <?php
            $agDetail = [
                ['bi-person-badge',        '#E6ECF4', 'Property auto-assigned',
                 'When a landlord submits a new listing, the system queues it to the next available agent using a FIFO assignment. You receive a notification.'],
                ['bi-folder2-open',        '#E4F2EA', 'Review submitted documents',
                 'Check the landlord\'s ownership proof, IC copy, and property photos. Reject early if documents are missing or suspicious.'],
                ['bi-calendar-check',      '#FFF4D6', 'Propose inspection times',
                 'Use the agent–landlord chat to propose 2–3 inspection time slots. The landlord confirms one and provides access details.'],
                ['bi-geo-alt',             '#E6ECF4', 'Conduct physical inspection',
                 'Visit the property at the agreed time. Check structural condition, utilities, safety, and whether the listing matches the submitted photos.'],
                ['bi-clipboard2-check',    '#E4F2EA', 'Approve or reject the listing',
                 'Approve: the property goes live on the platform. Reject: the landlord is notified with a reason. You can reject at any stage if red flags appear.'],
                ['bi-file-earmark-text',   '#FFF4D6', 'Prepare the tenancy contract',
                 'Once the landlord accepts a student, prepare the digital contract with the correct term dates, rent amount, deposit, and co-tenant details.'],
                ['bi-cash-coin',           '#E4F2EA', 'Receive your commission',
                 'You earn 30% of one month\'s base rent per completed contract. Track earnings on your Earnings dashboard.'],
            ];
            foreach ($agDetail as [$icon, $bg, $title, $desc]):
            ?>
            <div class="col-md-6">
                <div class="hiw-detail-card">
                    <div class="hiw-detail-icon" style="background:<?= $bg ?>;">
                        <i class="bi <?= $icon ?>" style="color:var(--rb-navy);"></i>
                    </div>
                    <div>
                        <strong class="d-block mb-1"><?= $title ?></strong>
                        <span class="small text-secondary"><?= $desc ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ============================================================
     SECTION 2 — SCAM PREVENTION / POLICY & SAFETY
     ============================================================ -->
<hr class="my-5">
<div class="hiw-section" id="safety">
    <div class="d-flex gap-3 align-items-start mb-4">
        <div style="width:48px; height:48px; border-radius:12px; background:#FDECEA;
                    color:#DC3545; display:flex; align-items:center; justify-content:center;
                    font-size:1.4rem; flex-shrink:0;">
            <i class="bi bi-shield-exclamation"></i>
        </div>
        <div>
            <h2 class="mb-1">Policy &amp; Safety — Scam Prevention</h2>
            <p class="text-secondary mb-0">
                Rental fraud is one of the most common crimes targeting Malaysian university students.
                Know the tactics, recognise the signs, and understand how RentBridge protects you.
            </p>
        </div>
    </div>

    <!-- Common scams -->
    <h4 class="mb-3">Common rental scams students face</h4>
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="scam-card">
                <h6><i class="bi bi-camera-off me-2"></i>Fake / stolen listing photos</h6>
                <p>
                    Scammers copy real property photos from legitimate platforms and post them
                    at suspiciously low prices to attract desperate students. The "landlord"
                    collects a deposit then disappears — the property either doesn't exist or
                    belongs to someone else.
                </p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="scam-card">
                <h6><i class="bi bi-cash-coin me-2"></i>Advance fee fraud (deposit scam)</h6>
                <p>
                    The fraudster claims the room is in high demand and pressures you to
                    transfer a deposit immediately via bank transfer "to hold the room."
                    Once you pay, they stop responding. No physical viewing is ever arranged.
                </p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="scam-card">
                <h6><i class="bi bi-person-x me-2"></i>Impersonating landlords</h6>
                <p>
                    Fraudsters reply to legitimate listings pretending to be the real landlord,
                    directing you to pay them instead. They may even provide fake tenancy
                    agreements with forged IC numbers to appear credible.
                </p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="scam-card">
                <h6><i class="bi bi-lock me-2"></i>Ghost landlord / rental without authority</h6>
                <p>
                    Someone rents a property legitimately, then illegally sublets it to students
                    — sometimes subletting the same room to multiple people simultaneously.
                    Students arrive on move-in day to find others in the same room.
                </p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="scam-card">
                <h6><i class="bi bi-whatsapp me-2"></i>WhatsApp / Telegram "agent" scam</h6>
                <p>
                    Unsolicited messages from "property agents" on WhatsApp or Telegram offer
                    cheap rooms near campus. They ask for a registration fee or deposit before
                    any viewing. Legitimate agents never ask for money before showing a property.
                </p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="scam-card">
                <h6><i class="bi bi-file-earmark-x me-2"></i>No written contract</h6>
                <p>
                    Some landlords insist on a verbal agreement or a non-standard handwritten
                    note to avoid accountability. Without a proper signed tenancy agreement,
                    you have no legal protection if rent is raised, deposit is withheld, or
                    you are asked to leave without notice.
                </p>
            </div>
        </div>
    </div>

    <!-- Red flags -->
    <h4 class="mb-3">Red flags — stop and verify</h4>
    <div class="bg-white border rounded-3 p-4 mb-4">
        <ul class="redflag-list">
            <?php
            $redFlags = [
                'Rent is 30–50% below the market rate for the area — too good to be true usually is.',
                'Landlord refuses to meet in person or conduct a physical viewing.',
                'Pressure to pay a deposit or "registration fee" before viewing the property.',
                'Communication happens only via WhatsApp; no platform chat record.',
                'Landlord is "overseas" and cannot meet — offers to mail the keys after payment.',
                'Contract is vague, handwritten, or has no landlord IC number or property address.',
                'Property photos look professionally staged but don\'t match the stated location.',
                'You are asked to pay via personal bank account rather than any official channel.',
                'Landlord cannot produce the property ownership title (geran tanah) on request.',
                'Repeated calls to "act fast" — "three other students already offered a deposit".',
            ];
            foreach ($redFlags as $flag):
            ?>
            <li>
                <span class="flag-dot"></span>
                <span><?= $flag ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- How RentBridge protects you -->
    <h4 class="mb-3">How RentBridge protects you</h4>
    <div class="row g-3 mb-4">
        <?php
        $protections = [
            ['bi-shield-check',       'Agent-verified listings only',
             'Every property must pass a physical inspection by a UTeM staff agent before it appears publicly. Unverified listings are invisible to students.'],
            ['bi-person-vcard',       'Landlord identity verification',
             'Landlords upload IC copies and ownership documents during registration. Admin reviews these before any listing is allowed.'],
            ['bi-chat-square-dots',   'All communication on-platform',
             'Messaging happens through RentBridge — not WhatsApp, Telegram, or email. Every message is logged and accessible to admins in disputes.'],
            ['bi-file-earmark-lock2', 'Digital signed contracts',
             'Contracts are generated and signed inside the platform. Both parties get a timestamped PDF. No verbal agreements, no handwritten notes.'],
            ['bi-currency-exchange',  'No money changes hands through us',
             'RentBridge never asks you to pay deposit or rent through the platform. Any request to pay via the platform or to "RentBridge staff" is a scam.'],
            ['bi-telephone-outbound', 'Report & escalate',
             'Suspect a fraudulent listing or user? Use the Feedback & Contact page. Reported listings are reviewed within 24 hours.'],
        ];
        foreach ($protections as [$icon, $title, $desc]):
        ?>
        <div class="col-md-6">
            <div class="protect-card">
                <i class="bi <?= $icon ?>"></i>
                <div>
                    <strong class="d-block mb-1 small"><?= $title ?></strong>
                    <span class="small" style="color:#2A4E35;"><?= $desc ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- What to do -->
    <div class="bg-white border rounded-3 p-4">
        <h5 class="mb-3"><i class="bi bi-exclamation-triangle text-warning me-2"></i>If you think you've been scammed</h5>
        <ol class="mb-0" style="line-height: 2; font-size: 0.9rem;">
            <li><strong>Stop all payments immediately.</strong> Do not send any more money.</li>
            <li><strong>Screenshot everything</strong> — messages, bank transfers, receipts, profile pages.</li>
            <li><strong>Report through RentBridge</strong> using the <a href="/rentbridge/contact.php">Feedback &amp; Contact</a> page — include screenshots.</li>
            <li><strong>File a police report</strong> at your nearest Balai Polis. Bring all evidence.</li>
            <li><strong>Contact your bank</strong> immediately if you made a transfer — request a fraud investigation (within 24 hours is critical).</li>
            <li><strong>Report to MCMC</strong> via <a href="https://aduan.mcmc.gov.my" target="_blank" rel="noopener">aduan.mcmc.gov.my</a> for online fraud cases.</li>
        </ol>
    </div>
</div>

<script>
// Role tab switcher
document.querySelectorAll('.hiw-role-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.hiw-role-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.role-flow').forEach(f => f.classList.add('d-none'));
        this.classList.add('active');
        document.getElementById('flow-' + this.dataset.role).classList.remove('d-none');
    });
});
</script>

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
