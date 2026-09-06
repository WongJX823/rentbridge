<?php
/**
 * Agent<->student case-status banner: a single persistent card summarizing
 * where a specific property+student pairing sits in the tenancy pipeline
 * (negotiating -> form sent -> form returned -> contract out -> signed),
 * plus the 4-step progress indicator shown alongside it.
 */

/**
 * Computes the current stage for an agent<->student conversation about a
 * property, once the agent has been accepted as the property's inspector.
 * Returns null if the agent hasn't been accepted yet (feature doesn't apply).
 */
function agent_student_case_stage(int $propertyId, int $studentId, int $agentId, int $convId): ?array
{
    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT viewing_mode, assigned_agent_id, agent_status, monthly_rent, deposit
          FROM properties WHERE id = ?
    ");
    $stmt->execute([$propertyId]);
    $prop = $stmt->fetch();
    if (
        !$prop
        || !in_array($prop['viewing_mode'], ['landlord_led', 'agent_led', 'either'], true)
        || (int)$prop['assigned_agent_id'] !== $agentId
        || $prop['agent_status'] !== 'accepted'
    ) {
        return null;
    }

    // Most recent tenancy for this exact property+student+agent triple.
    $stmt = $pdo->prepare("
        SELECT t.id AS tenancy_id,
               c.id AS contract_id, c.contract_code, c.landlord_signed_at,
               c.contract_pdf_path, c.signed_pdf_path, c.generated_pdf_path
          FROM tenancies t
          LEFT JOIN contracts c ON c.tenancy_id = t.id
         WHERE t.property_id = ? AND t.student_id = ? AND t.agent_id = ?
         ORDER BY t.id DESC LIMIT 1
    ");
    $stmt->execute([$propertyId, $studentId, $agentId]);
    $case = $stmt->fetch();

    if ($case && $case['contract_id']) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total, SUM(status = 'signed') AS signed
              FROM co_tenants WHERE tenancy_id = ?
        ");
        $stmt->execute([$case['tenancy_id']]);
        $sig    = $stmt->fetch();
        $total  = (int)($sig['total']  ?? 0);
        $signed = (int)($sig['signed'] ?? 0);
        $allSigned = $total > 0 && $signed === $total && !empty($case['landlord_signed_at']);

        if ($allSigned) {
            return [
                'stage'           => 'signed',
                'step'            => 'signed',
                'color'           => 'neutral',
                'heading'         => 'Contract signed by all parties',
                'sub'             => 'All signatures collected. Download the executed PDF or open the case file for deposit tracking.',
                'metric'          => "{$signed} of {$total} signed",
                'tenancy_id'      => (int)$case['tenancy_id'],
                'contract_id'     => (int)$case['contract_id'],
            ];
        }
        return [
            'stage'       => 'contract_out',
            'step'        => 'contract',
            'color'       => 'blue',
            'heading'     => 'Contract ' . $case['contract_code'] . ' out for signature',
            'sub'         => 'Resend the signing link or regenerate the PDF if anything needs to change.',
            'metric'      => "{$signed} of {$total} signed",
            'tenancy_id'  => (int)$case['tenancy_id'],
            'contract_id' => (int)$case['contract_id'],
        ];
    }

    if ($case) {
        // Tenancy exists (form was returned) but no contract generated yet.
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM co_tenants WHERE tenancy_id = ?");
        $stmt->execute([$case['tenancy_id']]);
        $tenants = (int)$stmt->fetchColumn();
        return [
            'stage'      => 'form_returned',
            'step'       => 'info_form',
            'color'      => 'green',
            'heading'    => 'Tenant info received — ready for contract',
            'sub'        => 'All required fields are in. Generate the contract PDF and send it for signing.',
            'metric'     => $tenants . ' tenant' . ($tenants === 1 ? '' : 's'),
            'tenancy_id' => (int)$case['tenancy_id'],
        ];
    }

    // No tenancy yet — check for an active (unresponded, uncancelled) form.
    $stmt = $pdo->prepare("
        SELECT id, sent_at FROM messages
         WHERE conversation_id = ?
           AND message_type = 'tenant_info_form'
           AND JSON_EXTRACT(metadata, '$.student_id') = ?
           AND JSON_EXTRACT(metadata, '$.recipient_role') = 'student'
           AND (JSON_EXTRACT(metadata, '$.cancelled') IS NULL OR JSON_EXTRACT(metadata, '$.cancelled') = false)
           AND NOT EXISTS (
               SELECT 1 FROM messages r
                WHERE r.message_type = 'tenant_info_response'
                  AND JSON_EXTRACT(r.metadata, '$.source_form_id') = messages.id
           )
         ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$convId, $studentId]);
    $pending = $stmt->fetch();

    if ($pending) {
        return [
            'stage'   => 'form_sent',
            'step'    => 'info_form',
            'color'   => 'amber',
            'heading' => 'Waiting on the tenant info form',
            'sub'     => 'Sent ' . date('j M, g:ia', strtotime($pending['sent_at'])) . ' · no reply yet. Nudge the student or send the form again.',
            'metric'  => '0 of 1 returned',
            'form_id' => (int)$pending['id'],
        ];
    }

    return [
        'stage'        => 'negotiating',
        'step'         => 'agreed',
        'color'        => 'green',
        'heading'      => 'Ready to start tenant paperwork?',
        'sub'          => 'Agreed with the student? Send the tenant info form — details and co-tenants come back here.',
        'metric'       => null,
        'monthly_rent' => (float)$prop['monthly_rent'],
        'deposit'      => (float)$prop['deposit'],
    ];
}

/** 4-step "CASE PROGRESS" indicator: Agreed -> Info form -> Contract -> Signed. */
function render_case_progress_stepper(array $stage): void
{
    $steps    = ['agreed' => 'Agreed', 'info_form' => 'Info form', 'contract' => 'Contract', 'signed' => 'Signed'];
    $order    = array_keys($steps);
    $activeIdx = array_search($stage['step'], $order, true);
    ?>
    <div class="d-flex align-items-center gap-2 small text-secondary flex-wrap">
        <span class="text-uppercase fw-semibold" style="font-size:.68rem; letter-spacing:.04em;">Case progress</span>
        <?php foreach ($order as $i => $key): ?>
            <span class="d-flex align-items-center gap-1<?= $i <= $activeIdx ? ' text-dark fw-semibold' : '' ?>">
                <span style="width:7px; height:7px; border-radius:50%; display:inline-block;
                             background:<?= $i <= $activeIdx ? '#2E8B57' : '#D9D9D3' ?>;"></span>
                <?= e($steps[$key]) ?>
            </span>
            <?php if ($i < count($order) - 1): ?><span class="text-secondary">&rsaquo;</span><?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php
}

/** The persistent color-coded status card + its stage-appropriate action buttons. */
function render_case_status_banner(array $stage, int $convId, int $propertyId, int $studentId): void
{
    $palettes = [
        'green'   => ['bg' => '#E4F2EA', 'border' => '#2E8B57', 'text' => '#1F6D45'],
        'amber'   => ['bg' => '#FFF4D6', 'border' => '#D4A017', 'text' => '#7C5E0A'],
        'blue'    => ['bg' => '#E6ECF4', 'border' => '#3B6FA0', 'text' => '#1F3F63'],
        'neutral' => ['bg' => '#F4F4EE', 'border' => '#D9D9D3', 'text' => '#4B5A6E'],
    ];
    $p = $palettes[$stage['color']] ?? $palettes['neutral'];
    ?>
    <div class="case-status-banner p-3 mb-2 d-flex justify-content-between align-items-center gap-3 flex-wrap"
         style="background:<?= $p['bg'] ?>; border:1px solid <?= $p['border'] ?>; border-radius:10px;"
         id="caseStatusBanner-<?= (int)$convId ?>">
        <div class="flex-grow-1" style="min-width:220px;">
            <div class="fw-semibold" style="color:<?= $p['text'] ?>;"><?= e($stage['heading']) ?></div>
            <div class="small text-secondary"><?= e($stage['sub']) ?></div>
        </div>
        <?php if (!empty($stage['metric'])): ?>
            <span class="badge rounded-pill flex-shrink-0"
                  style="background:#fff; color:<?= $p['text'] ?>; border:1px solid <?= $p['border'] ?>; font-weight:600;">
                <?= e($stage['metric']) ?>
            </span>
        <?php endif; ?>
        <div class="d-flex align-items-center gap-2 flex-shrink-0">
            <?php switch ($stage['stage']):
                case 'negotiating': ?>
                    <a href="#" class="small text-secondary text-decoration-none" id="caseBannerLater-<?= (int)$convId ?>">Later</a>
                    <button type="button" id="agentSendFormBtn"
                            class="btn btn-success fw-semibold btn-sm"
                            data-bs-toggle="modal" data-bs-target="#agentTermsModal"
                            data-conv-id="<?= (int)$convId ?>" data-property-id="<?= (int)$propertyId ?>"
                            data-student-id="<?= (int)$studentId ?>"
                            data-monthly-rent="<?= (float)($stage['monthly_rent'] ?? 0) ?>"
                            data-deposit="<?= (float)($stage['deposit'] ?? 0) ?>">
                        Send tenant info form
                    </button>
                    <?php break;
                case 'form_sent': ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary cancel-tenant-form-btn"
                            data-form-id="<?= (int)$stage['form_id'] ?>" data-conv-id="<?= (int)$convId ?>">
                        Cancel request
                    </button>
                    <button type="button" class="btn btn-warning btn-sm fw-semibold"
                            data-bs-toggle="modal" data-bs-target="#agentTermsModal"
                            data-conv-id="<?= (int)$convId ?>" data-property-id="<?= (int)$propertyId ?>"
                            data-student-id="<?= (int)$studentId ?>">
                        Resend form
                    </button>
                    <?php break;
                case 'form_returned': ?>
                    <a href="<?= BASE_PATH ?>/agent/case.php?id=<?= (int)$stage['tenancy_id'] ?>"
                       class="btn btn-sm btn-outline-secondary">View submitted info</a>
                    <a href="<?= BASE_PATH ?>/agent/generate_contract.php?tenancy_id=<?= (int)$stage['tenancy_id'] ?>"
                       class="btn btn-success btn-sm fw-semibold" target="_blank">Generate contract PDF</a>
                    <?php break;
                case 'contract_out': ?>
                    <a href="<?= BASE_PATH ?>/agent/generate_contract.php?tenancy_id=<?= (int)$stage['tenancy_id'] ?>"
                       class="btn btn-sm btn-outline-secondary" target="_blank">Regenerate PDF</a>
                    <form method="POST" action="<?= BASE_PATH ?>/contracts/view.php?id=<?= (int)$stage['contract_id'] ?>" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="send_sign_links">
                        <button type="submit" class="btn btn-primary btn-sm fw-semibold">Resend contract link</button>
                    </form>
                    <?php break;
                case 'signed': ?>
                    <a href="<?= BASE_PATH ?>/agent/case.php?id=<?= (int)$stage['tenancy_id'] ?>"
                       class="btn btn-sm btn-outline-secondary">Open case</a>
                    <a href="<?= BASE_PATH ?>/contracts/pdf.php?id=<?= (int)$stage['contract_id'] ?>"
                       class="btn btn-dark btn-sm fw-semibold" target="_blank">Download signed PDF</a>
                    <?php break;
            endswitch; ?>
        </div>
    </div>
    <?php if ($stage['stage'] === 'negotiating'): ?>
    <script>
    (function () {
        var key    = 'rb_case_banner_later_<?= (int)$convId ?>';
        var later  = document.getElementById('caseBannerLater-<?= (int)$convId ?>');
        var banner = document.getElementById('caseStatusBanner-<?= (int)$convId ?>');
        try {
            if (sessionStorage.getItem(key) === '1' && banner) banner.hidden = true;
        } catch (err) {}
        if (later) {
            later.addEventListener('click', function (e) {
                e.preventDefault();
                try { sessionStorage.setItem(key, '1'); } catch (err) {}
                if (banner) banner.hidden = true;
            });
        }
    })();
    </script>
    <?php endif; ?>
    <?php
}
