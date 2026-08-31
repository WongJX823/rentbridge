<?php
/**
 * Reusable property status progress bar.
 *
 * Stages:  1 Pending  ->  2 Awaiting inspection  ->  3 Inspection complete  ->  4 Available
 * (with a distinct "Rejected" terminal state).
 *
 * Usage:
 *   require_once __DIR__ . '/../includes/property_status_bar.php';
 *   render_property_status_bar($property);   // $property must include:
 *       status, agent_status, assigned_agent_id, inspection_completed_at, agent_verified_at
 */

/**
 * Work out which stage a property is at (1-4), derived from its fields so it is
 * correct regardless of which code path advanced it.
 *
 * @return array{current:int, rejected:bool}
 */
function property_status_stage(array $p): array
{
    $status        = $p['status'] ?? 'pending_approval';
    $agentAssigned = !empty($p['assigned_agent_id']);
    $inspected     = !empty($p['inspection_completed_at']) || !empty($p['agent_verified_at']);

    if ($status === 'rejected') {
        return ['current' => 1, 'rejected' => true];
    }
    // Live (or once-live) listings have reached the final stage.
    if (in_array($status, ['available', 'reserved', 'rented'], true)
        || ($status === 'hidden' && $inspected)) {
        return ['current' => 4, 'rejected' => false];
    }
    // Still pending approval / hidden-before-going-live.
    if ($inspected)     return ['current' => 3, 'rejected' => false]; // inspection done, going live
    if ($agentAssigned) return ['current' => 2, 'rejected' => false]; // agent assigned, awaiting inspection
    return ['current' => 1, 'rejected' => false];                     // freshly submitted
}

/**
 * Render the progress bar HTML (styles printed once per request).
 * $compact = true renders a small inline variant suitable for list/card views.
 */
function render_property_status_bar(array $p, bool $compact = false): void
{
    static $stylePrinted = false;
    if (!$stylePrinted) {
        echo <<<'CSS'
<style>
.rb-mini{display:flex;align-items:center;gap:5px;flex-wrap:wrap;}
.rb-mini__dots{display:flex;align-items:center;}
.rb-mini .d{width:11px;height:11px;border-radius:50%;background:#e9ecef;flex:0 0 auto;}
.rb-mini .d.done{background:#198754;}
.rb-mini .d.active{background:#0d6efd;box-shadow:0 0 0 3px rgba(13,110,253,.15);}
.rb-mini .d.rej{background:#dc3545;box-shadow:0 0 0 3px rgba(220,53,69,.15);}
.rb-mini .l{width:12px;height:2px;background:#e9ecef;}
.rb-mini .l.done{background:#198754;}
.rb-mini__label{font-size:.72rem;font-weight:600;color:#495057;}
.rb-mini__label.rej{color:#dc3545;}
.rb-steps{display:flex;align-items:flex-start;gap:0;margin:0 0 1.5rem;flex-wrap:nowrap;overflow-x:auto;padding:.25rem 0;}
.rb-step{display:flex;flex-direction:column;align-items:center;text-align:center;min-width:96px;flex:0 0 auto;}
.rb-step__dot{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  font-weight:600;font-size:.9rem;background:#e9ecef;color:#6c757d;border:2px solid #e9ecef;transition:.2s;}
.rb-step__label{margin-top:.4rem;font-size:.78rem;line-height:1.15;color:#6c757d;max-width:100px;}
.rb-step__line{flex:1 1 auto;height:3px;background:#e9ecef;margin-top:16px;min-width:24px;}
.rb-step.done  .rb-step__dot{background:#198754;border-color:#198754;color:#fff;}
.rb-step.done  .rb-step__label{color:#198754;}
.rb-step.active .rb-step__dot{background:#0d6efd;border-color:#0d6efd;color:#fff;box-shadow:0 0 0 4px rgba(13,110,253,.15);}
.rb-step.active .rb-step__label{color:#0d6efd;font-weight:600;}
.rb-step__line.done{background:#198754;}
.rb-step.rejected .rb-step__dot{background:#dc3545;border-color:#dc3545;color:#fff;box-shadow:0 0 0 4px rgba(220,53,69,.15);}
.rb-step.rejected .rb-step__label{color:#dc3545;font-weight:600;}
</style>
CSS;
        $stylePrinted = true;
    }

    $stages = ['Pending', 'Awaiting inspection', 'Inspection complete', 'Available now'];
    $info   = property_status_stage($p);

    // ---- compact inline variant (for list / card views) ----
    if ($compact) {
        if ($info['rejected']) {
            echo '<div class="rb-mini"><div class="rb-mini__dots">'
               . '<span class="d done"></span><span class="l"></span>'
               . '<span class="d rej"></span></div>'
               . '<span class="rb-mini__label rej">Rejected</span></div>';
            return;
        }
        $current = $info['current'];
        echo '<div class="rb-mini"><div class="rb-mini__dots">';
        for ($n = 1; $n <= 4; $n++) {
            $cls = $n < $current ? 'done' : ($n === $current ? 'active' : '');
            echo '<span class="d ' . $cls . '"></span>';
            if ($n < 4) echo '<span class="l ' . ($n < $current ? 'done' : '') . '"></span>';
        }
        echo '</div><span class="rb-mini__label">'
           . htmlspecialchars($stages[$current - 1], ENT_QUOTES) . '</span></div>';
        return;
    }

    echo '<div class="rb-steps" role="list" aria-label="Property status progress">';

    if ($info['rejected']) {
        // Terminal rejected state.
        echo '<div class="rb-step done"><div class="rb-step__dot">&#10003;</div>'
           . '<div class="rb-step__label">Submitted</div></div>';
        echo '<div class="rb-step__line"></div>';
        echo '<div class="rb-step rejected"><div class="rb-step__dot">&#10005;</div>'
           . '<div class="rb-step__label">Rejected</div></div>';
        echo '</div>';
        return;
    }

    $current = $info['current'];
    foreach ($stages as $i => $label) {
        $n = $i + 1;
        $cls = $n < $current ? 'done' : ($n === $current ? 'active' : 'todo');
        $mark = $n < $current ? '&#10003;' : (string)$n;
        echo '<div class="rb-step ' . $cls . '">'
           . '<div class="rb-step__dot">' . $mark . '</div>'
           . '<div class="rb-step__label">' . htmlspecialchars($label, ENT_QUOTES) . '</div></div>';
        if ($n < count($stages)) {
            $lineCls = $n < $current ? 'done' : '';
            echo '<div class="rb-step__line ' . $lineCls . '"></div>';
        }
    }
    echo '</div>';
}
