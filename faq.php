<?php
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Frequently Asked Questions';
$activeNav = 'faq';

$faqs = [
    [
        'category' => 'For students',
        'items' => [
            ['q' => 'Who can register as a student on RentBridge?',
             'a' => 'Any current UTeM student with a valid matric number. Registration is free.'],
            ['q' => 'How do I find a property?',
             'a' => 'Use the Browse page to filter by city, property type, monthly rent, and furnishing level. Click any listing to see full details, photos, and the agent assigned to that property.'],
            ['q' => 'Is it safe to book through RentBridge?',
             'a' => 'Yes. Every property is inspected by a UTeM-staff agent before tenancy is finalized. The agent verifies the landlord\'s ownership, checks the physical property, and witnesses contract signing.'],
            ['q' => 'How do I save a property for later?',
             'a' => 'Click the heart icon on any property card or detail page. Your saved properties appear in the "Saved" tab in your sidebar.'],
            ['q' => 'Can I rent with friends?',
             'a' => 'Yes! Use the "Find Partners" feature to discover other students looking for the same property. The primary tenant (you) creates the booking; additional co-tenants are added via a chat form sent by the agent before contract generation. Only the primary tenant needs a RentBridge account.'],
            ['q' => 'What happens after I apply for a property?',
             'a' => 'The landlord receives your application and reviews it. If accepted, a UTeM agent is auto-assigned to inspect the property within 5 days. Once inspection passes, the agent generates a tenancy contract for all parties to sign offline. The agent then uploads the signed contract back to the system.'],
        ],
    ],
    [
        'category' => 'For landlords',
        'items' => [
            ['q' => 'Who can list properties on RentBridge?',
             'a' => 'Any Malaysian property owner. You\'ll need to provide your NRIC and upload proof of ownership (geran, SPA, or utility bill) during property registration.'],
            ['q' => 'Is there a listing fee?',
             'a' => 'No. Listing is free. RentBridge earns commission only when a property is successfully rented through the platform (paid by the landlord, equivalent to 1 month rent + 6% SST per BOVAEP guidelines).'],
            ['q' => 'How do agents verify my property?',
             'a' => 'After your listing is approved by admin, an agent will be auto-assigned. They\'ll physically visit your property within 5 days, verify ownership documents, and check the physical condition matches your listing.'],
            ['q' => 'What if a student causes damage?',
             'a' => 'The signed tenancy contract includes a security deposit (typically 2 months rent) and utility deposit. Deductions can be made for damages upon termination of tenancy.'],
            ['q' => 'How do I price my property?',
             'a' => 'When adding a property, RentBridge shows a market benchmark suggestion based on similar listings in the same area, with adjustments for amenities and distance to UTeM campus. This helps you price competitively without undercharging.'],
            ['q' => 'How does WhatsApp contact work?',
             'a' => 'You can opt-in to allow students to contact you via WhatsApp from your Profile settings. When enabled, a "WhatsApp me" button appears on your property pages. Your phone number is never shown publicly without this opt-in.'],
        ],
    ],
    [
        'category' => 'About contracts',
        'items' => [
            ['q' => 'Are RentBridge contracts legally binding?',
             'a' => 'Yes. Contracts follow the standard Malaysian tenancy agreement format with all required clauses (parties, term, rent, deposits, covenants). They are signed by all tenants and the landlord; the agent signs as a platform witness. The signed PDF is stored as the authoritative copy.'],
            ['q' => 'How are multi-tenant contracts handled?',
             'a' => 'For shared rentals, all co-tenants are listed in Part 3 of the First Schedule with their full names and NRIC numbers. The contract includes a joint-and-several liability clause meaning all tenants are equally responsible. Each tenant signs the contract individually.'],
            ['q' => 'Can I verify a contract reference?',
             'a' => 'Yes. Every contract has a unique reference code (e.g. RB-2026-00012). Anyone can verify it at <code>/verify.php</code> on this site. The page shows public details: property, period, rent, tenants — but not private information like IC numbers.'],
            ['q' => 'What if a tenant wants to move out early?',
             'a' => 'Per the standard agreement, either party can terminate with one month\'s written notice after the initial term. Early termination during the first year may result in security deposit forfeiture.'],
        ],
    ],
    [
        'category' => 'Privacy & security',
        'items' => [
            ['q' => 'Who can see my personal information?',
             'a' => 'Only the parties involved in a transaction can see relevant details. Students don\'t see landlord IC numbers or addresses. Property documents (geran, IC copies) are private to the landlord, the assigned agent, and admin only.'],
            ['q' => 'How are passwords protected?',
             'a' => 'Passwords are stored hashed using bcrypt (PHP\'s password_hash function). Password changes require email verification with a 6-digit code that expires in 10 minutes. Brute-force attempts are limited to 5 per code.'],
            ['q' => 'Can I delete my account?',
             'a' => 'Yes — contact us through the form below. Active tenancies must be closed before account deletion. Past contracts are retained for legal record-keeping per Malaysian regulations.'],
        ],
    ],
];

ob_start();
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="text-center mb-5">
            <h1 style="font-family:'Fraunces',serif;">Frequently Asked Questions</h1>
            <p class="text-secondary">
                Common questions about RentBridge — for students, landlords, and everyone in between.
            </p>
        </div>

        <?php foreach ($faqs as $cIdx => $category): ?>
            <div class="mb-5">
                <h4 class="mb-3 text-secondary" style="font-family:'Fraunces',serif;">
                    <?= e($category['category']) ?>
                </h4>

                <div class="accordion" id="faqAccordion<?= $cIdx ?>">
                    <?php foreach ($category['items'] as $iIdx => $item):
                        $id = 'faq_' . $cIdx . '_' . $iIdx;
                    ?>
                        <div class="accordion-item" style="background: white; border: 1px solid rgba(15,44,82,0.08);">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#<?= $id ?>">
                                    <?= e($item['q']) ?>
                                </button>
                            </h2>
                            <div id="<?= $id ?>" class="accordion-collapse collapse"
                                 data-bs-parent="#faqAccordion<?= $cIdx ?>">
                                <div class="accordion-body text-secondary">
                                    <?= $item['a'] ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- CTA to contact -->
        <div class="bg-white border rounded-3 p-4 text-center mt-5"
             style="border-left: 4px solid #2E8B57 !important;">
            <h5 class="mb-2">Didn't find your answer?</h5>
            <p class="text-secondary mb-3">
                Reach out and we'll get back to you within 1-2 business days.
            </p>
            <a href="/rentbridge/contact.php" class="btn btn-primary">
                <i class="bi bi-envelope-fill me-1"></i> Contact us
            </a>
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