<?php
$title = 'RentBridge — System Workflow Reference';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $title ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body { background:#f4f6fb; font-family:'Segoe UI',sans-serif; font-size:0.85rem; }

  /* Role colours */
  .r-student  { background:#f8d7da; color:#58151c; border-color:#f1aeb5; }
  .r-landlord { background:#d1e7dd; color:#0a3622; border-color:#a3cfbb; }
  .r-agent    { background:#fff3cd; color:#664d03; border-color:#ffda6a; }
  .r-admin    { background:#cfe2ff; color:#084298; border-color:#9ec5fe; }
  .r-system   { background:#e2e3e5; color:#41464b; border-color:#c4c8cb; }
  .r-group    { background:#e0cffc; color:#3b0764; border-color:#c29ffa; }

  .section-badge {
    display:inline-flex; align-items:center; gap:8px;
    font-size:0.72rem; padding:4px 14px; border-radius:20px;
    border:1.5px solid; margin-bottom:14px; font-weight:700; letter-spacing:.3px;
  }

  /* Flow table */
  .ftbl { width:100%; border-collapse:separate; border-spacing:0; }
  .ftbl th {
    text-align:center; font-size:0.73rem; font-weight:700;
    padding:7px 10px; border-bottom:2px solid #dee2e6; white-space:nowrap;
  }
  .ftbl td {
    vertical-align:top; padding:8px 10px; font-size:0.79rem;
    border-bottom:1px solid #eee; line-height:1.5;
  }
  .ftbl tr:last-child td { border-bottom:none; }
  .ftbl tr.tr-ok  { background:#f0fff4; }
  .ftbl tr.tr-err { background:#fff5f5; }
  .ftbl tr.tr-warn{ background:#fffde7; }
  .ftbl tr.tr-sys { background:#f8f9fa; }

  .sn {
    display:inline-block; width:20px; height:20px; border-radius:50%;
    background:#495057; color:#fff; font-size:0.66rem; font-weight:700;
    text-align:center; line-height:20px; flex-shrink:0; margin-right:5px;
  }
  .db { font-family:monospace; font-size:0.72rem; background:#f1f3f5;
        border-radius:3px; padding:1px 5px; color:#495057; }

  .role-chip {
    font-size:0.68rem; font-weight:700; border-radius:20px; padding:2px 8px;
    display:inline-block; border:1px solid transparent;
  }

  /* Callout */
  .callout {
    border-left:4px solid #0d6efd; background:#f0f6ff;
    padding:10px 14px; border-radius:0 6px 6px 0;
    font-size:0.79rem; margin-top:8px;
  }
  .callout.warn  { border-color:#ffc107; background:#fffde7; }
  .callout.ok    { border-color:#198754; background:#f0fff4; }
  .callout.danger{ border-color:#dc3545; background:#fff5f5; }

  .flow-section { margin-bottom:2.5rem; }

  /* Status pill */
  .sp {
    font-family:monospace; font-size:0.68rem; padding:2px 7px;
    border-radius:4px; display:inline-block; border:1px solid #dee2e6;
    background:#fff; color:#212529; white-space:nowrap;
  }

  @media print { body{background:#fff;} }
</style>
</head>
<body>

<!-- HEADER -->
<div class="bg-dark text-white py-4 px-4 mb-4">
  <div class="container-xl">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-diagram-3-fill fs-2 text-warning"></i>
      <div>
        <h1 class="h4 mb-0 fw-bold">RentBridge — System Workflow Reference</h1>
        <small class="text-secondary">All roles · All flows · Every status transition</small>
      </div>
    </div>
    <div class="mt-3 d-flex flex-wrap gap-2">
      <span class="role-chip r-student border">Student</span>
      <span class="role-chip r-landlord border">Landlord</span>
      <span class="role-chip r-agent border">Agent</span>
      <span class="role-chip r-admin border">Admin / System</span>
      <span class="role-chip r-group border">Housemate Group</span>
    </div>
    <div class="callout mt-3">
      <strong>Key design:</strong> RentBridge has <strong>no booking-request/approval gate</strong>.
      A student never submits a booking and waits for landlord/agent to approve it.
      The flow goes: <strong>Chat → Agree → Tenant Form → Contract → Sign → Active</strong>.
      The <code>bookings</code> row is created only when the tenant form is submitted
      (<span class="sp">contract_pending</span>) — it is the contract container, not an approval gate.
    </div>
  </div>
</div>

<div class="container-xl px-3 pb-5">

<!-- ══════════════════════════════════════════════════════════ -->
<!-- FLOW 1 — Property Registration                           -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="flow-section">
  <div class="section-badge r-landlord"><i class="bi bi-house-add-fill"></i>Flow 1 — Property Registration &amp; Listing Inspection</div>

  <div class="table-responsive">
  <table class="ftbl">
    <thead><tr>
      <th style="width:12%"><span class="role-chip r-landlord border">Landlord</span></th>
      <th style="width:12%"><span class="role-chip r-admin border">System / Admin</span></th>
      <th style="width:12%"><span class="role-chip r-agent border">Agent</span></th>
      <th>Notes / DB</th>
    </tr></thead>
    <tbody>
      <tr>
        <td><span class="sn">1</span>Fill property form<br><small class="text-muted">landlord/add_property.php</small><br>
          Type, price, deposit, furnishing, city, viewing mode, Google Maps URL</td>
        <td></td><td></td>
        <td class="text-muted">viewing_mode must be set: <code>landlord_led</code> or <code>agent_led</code>. Map URL → lat/lng extracted automatically.</td>
      </tr>
      <tr>
        <td><span class="sn">2</span>Upload ≥1 photo &amp; ownership doc</td>
        <td></td><td></td>
        <td class="text-muted">Max 10 photos · 5 MB each. Docs: Geran / SPA / Utility Bill / IC / Other. MIME validated via finfo.</td>
      </tr>
      <tr>
        <td><span class="sn">3</span>Submit listing</td>
        <td><span class="sn">4</span>Create property row<br><span class="sp">pending_approval</span><br>Auto-assign agent (FIFO)</td>
        <td></td>
        <td>DB: <code>properties.status = 'pending_approval'</code>, <code>assigned_agent_id</code> set,<br>
          <code>property_agent_assignments</code> row inserted (<code>outcome = 'pending'</code>)</td>
      </tr>
      <tr>
        <td></td><td></td>
        <td><span class="sn">5</span>Open property review<br><small class="text-muted">agent/property_review.php</small><br>
          Check: photos, docs, pricing benchmark</td>
        <td class="text-muted">Agent has a 24h window to respond. Agent sees pricing benchmark to detect overpricing.</td>
      </tr>
      <tr class="tr-err">
        <td></td><td></td>
        <td><i class="bi bi-x-circle text-danger"></i> <strong>Reject listing</strong><br>Enter rejection reason</td>
        <td>DB: <code>property_agent_assignments.outcome = 'rejected'</code>, reason stored.<br>
          Admin notified → manually reassigns or next FIFO agent picks up.</td>
      </tr>
      <tr class="tr-ok">
        <td></td><td></td>
        <td><span class="sn">6</span><i class="bi bi-check-circle text-success"></i> <strong>Accept assignment</strong></td>
        <td>DB: <code>agent_status = 'accepted'</code>, <code>agent_assigned_at</code> recorded.<br>
          System opens landlord–agent chat (<code>context_type = 'agent_case'</code>).</td>
      </tr>
      <tr>
        <td></td><td></td>
        <td><span class="sn">7</span>Send inspection schedule request in chat<br>
          1–2 proposed time slots + access method note</td>
        <td class="text-muted">Chat message type: <code>inspection_schedule_request</code>. Landlord replies in same conversation.</td>
      </tr>
      <tr>
        <td><span class="sn">8</span>Confirm inspection slot<br>
          Select access method:<br>
          landlord_present / lockbox / other<br>
          Tick consent statement</td>
        <td><span class="sn">9</span>Create property_inspections row<br>
          <span class="sp">status: scheduled</span></td>
        <td></td>
        <td>DB: <code>property_inspections.status = 'scheduled'</code>, <code>consent_given_at</code>, <code>access_details</code> stored.<br>
          System posts confirmation notice in chat.</td>
      </tr>
      <tr>
        <td></td><td></td>
        <td><span class="sn">10</span>Conduct physical inspection <em>(offline)</em><br>
          Mark inspection complete</td>
        <td>DB: <code>property_inspections.status = 'completed'</code>, <code>completed_at</code> recorded.</td>
      </tr>
      <tr class="tr-err">
        <td></td><td></td>
        <td><i class="bi bi-x-circle text-danger"></i> <strong>Reject listing after inspection</strong></td>
        <td><span class="sp">properties.status = 'rejected'</span> — Landlord must fix docs and relist (new property row).</td>
      </tr>
      <tr class="tr-ok">
        <td></td><td></td>
        <td><span class="sn">11</span><i class="bi bi-check-circle text-success"></i> <strong>Approve listing</strong></td>
        <td><span class="sp">properties.status = 'available'</span> — <code>agent_verified_at</code>, <code>agent_verified_by</code> set.<br>
          Property visible on listings — students can browse and chat.</td>
      </tr>
      <tr class="tr-sys">
        <td></td>
        <td><i class="bi bi-clock text-warning"></i> 24h timeout (no response from agent)</td>
        <td></td>
        <td>System auto-reassigns to next FIFO agent. Old record: <code>outcome = 'timeout'</code>. New row inserted with <code>round_number + 1</code>.<br>
          If all agents exhausted → admin notified to force-assign.</td>
      </tr>
    </tbody>
  </table>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- FLOW 2 — Landlord-Led Rental, 1 Tenant, E-sign          -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="flow-section">
  <div class="section-badge r-landlord"><i class="bi bi-key-fill"></i>Flow 2 — Landlord-Led Rental · 1 Tenant · E-sign
    <span class="badge bg-success ms-1" style="font-size:.65rem;">viewing_mode = landlord_led</span>
  </div>

  <div class="callout mb-3">
    <strong>Landlord-led:</strong> Property page shows <em>"Chat with Landlord"</em> button.
    Landlord handles all tenant communication and fills the tenant form. Agent enters only at contract stage.
  </div>

  <div class="table-responsive">
  <table class="ftbl">
    <thead><tr>
      <th style="width:14%"><span class="role-chip r-student border">Student</span></th>
      <th style="width:14%"><span class="role-chip r-landlord border">Landlord</span></th>
      <th style="width:14%"><span class="role-chip r-agent border">Agent</span></th>
      <th style="width:12%"><span class="role-chip r-admin border">System</span></th>
      <th>Notes / DB</th>
    </tr></thead>
    <tbody>
      <tr>
        <td><span class="sn">1</span>Browse listings<br>Open property page<br>Click <strong>Chat with Landlord</strong></td>
        <td></td><td></td>
        <td>Create conversation<br><span class="sp">property_inquiry</span></td>
        <td class="text-muted">Student sees "Chat with Landlord" because <code>viewing_mode = 'landlord_led'</code>.</td>
      </tr>
      <tr>
        <td><span class="sn">2</span>Send inquiry, negotiate terms</td>
        <td><span class="sn">3</span>Reply, agree on rent, move-in date, duration</td>
        <td></td><td></td>
        <td class="text-muted">Standard chat. Poll every 5s delivers new messages.</td>
      </tr>
      <tr>
        <td></td>
        <td><span class="sn">4</span>Click <strong>Request Contract Preparation</strong><br><small>Yellow bar in chat</small></td>
        <td></td>
        <td>Create landlord–agent conversation<br><span class="sp">contract_prep</span><br>System notice in both chats</td>
        <td>This is where the agent enters the flow. Agent was assigned at property registration (Flow 1) — not a new assignment.</td>
      </tr>
      <tr>
        <td></td><td></td>
        <td><span class="sn">5</span>Click <strong>Send Tenant Info Form</strong><br>recipient = landlord</td>
        <td>Form posted in landlord–agent chat</td>
        <td><code>send_tenant_form.php</code> — <code>recipient_role = 'landlord'</code>.</td>
      </tr>
      <tr>
        <td></td>
        <td><span class="sn">6</span>Open tenant form modal<br>Fill primary tenant details:<br>Full name, IC, phone, email, home address<br>Fill tenancy terms:<br>Start/end date, duration, rent, deposit</td>
        <td></td><td></td>
        <td class="text-muted">No co-tenants — <code>is_primary = 1</code> only. Landlord can prefill from student profile data.</td>
      </tr>
      <tr>
        <td></td>
        <td><span class="sn">7</span>Submit form</td>
        <td></td>
        <td><span class="sn">8</span>Create booking row<br><span class="sp">contract_pending</span><br>1 co_tenants row (is_primary=1)</td>
        <td>DB: <code>bookings.status = 'contract_pending'</code>. <strong>This is the first and only bookings row — no approval gate before this.</strong></td>
      </tr>
      <tr>
        <td></td><td></td>
        <td><span class="sn">9</span>Click <strong>Generate Contract</strong><br><small>agent/generate_contract.php</small></td>
        <td>PDF generated via mPDF<br>Saved to <code>uploads/generated_contracts/</code></td>
        <td>Contract code: <code>RB-YYYY-NNNNN</code>. Watermarked unsigned PDF saved.</td>
      </tr>
      <tr>
        <td></td><td></td>
        <td><span class="sn">10</span>Share e-sign link with student<br><small>/contracts/sign.php?booking_id=X</small></td>
        <td></td><td></td>
      </tr>
      <tr>
        <td><span class="sn">11</span>Open e-sign canvas<br>Review contract summary<br>Draw signature<br>Click <strong>Sign Contract</strong></td>
        <td></td><td></td>
        <td>Record <code>co_tenants.signed_at</code><br><strong>Signature data discarded immediately after PDF embed</strong><br><code>co_tenants.signature_data = NULL</code></td>
        <td class="text-muted">⚠ Legal: signature canvas data is <strong>never stored</strong> in the DB. Only <code>signed_at</code> timestamp is kept.</td>
      </tr>
      <tr class="tr-ok">
        <td></td><td></td><td></td>
        <td><span class="sn">12</span><strong>Activate booking</strong><br><span class="sp">bookings → active</span><br><span class="sp">properties → rented</span><br>Public verify URL live</td>
        <td>Verify: <code>/verify.php?ref=RB-YYYY-NNNNN</code> — accessible without login. Shows: property, landlord, tenant name, dates.</td>
      </tr>
    </tbody>
  </table>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- FLOW 3 — Landlord-Led Rental, 3 Tenants, Wet-sign       -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="flow-section">
  <div class="section-badge r-landlord"><i class="bi bi-file-earmark-arrow-up-fill"></i>Flow 3 — Landlord-Led Rental · 3 Tenants · Wet-sign PDF Upload
    <span class="badge bg-success ms-1" style="font-size:.65rem;">viewing_mode = landlord_led</span>
  </div>
  <div class="callout mb-3">
    Co-tenants 2 &amp; 3 do <strong>not</strong> need platform accounts — landlord enters their details manually.
    All three sign a physical contract; agent scans and uploads.
  </div>

  <div class="table-responsive">
  <table class="ftbl">
    <thead><tr>
      <th style="width:14%"><span class="role-chip r-student border">Student 1</span></th>
      <th style="width:14%"><span class="role-chip r-landlord border">Landlord</span></th>
      <th style="width:14%"><span class="role-chip r-agent border">Agent</span></th>
      <th style="width:12%"><span class="role-chip r-admin border">System</span></th>
      <th>Notes / DB</th>
    </tr></thead>
    <tbody>
      <tr>
        <td><span class="sn">1</span>Chat with Landlord<br>State group of 3 interested</td>
        <td><span class="sn">2</span>Agree to 3-person group tenancy<br>Discuss terms in chat</td>
        <td></td><td></td>
        <td>context_type = <code>property_inquiry</code></td>
      </tr>
      <tr>
        <td></td>
        <td><span class="sn">3</span>Request Contract Preparation</td>
        <td></td>
        <td>Landlord–agent conversation created<br><span class="sp">contract_prep</span></td>
        <td></td>
      </tr>
      <tr>
        <td></td><td></td>
        <td><span class="sn">4</span>Send Tenant Info Form<br>recipient = landlord</td>
        <td></td>
        <td><code>recipient_role = 'landlord'</code></td>
      </tr>
      <tr>
        <td></td>
        <td><span class="sn">5</span>Fill Primary Tenant (Student 1) details<br>Add Co-Tenant — Student 2 (full details)<br>Add Co-Tenant — Student 3 (full details)<br>Fill tenancy terms → Submit</td>
        <td></td>
        <td>Create booking row<br><span class="sp">contract_pending</span><br>3 co_tenants rows</td>
        <td>Students 2 &amp; 3 details entered by landlord — no platform account required for wet-sign path.</td>
      </tr>
      <tr>
        <td></td><td></td>
        <td><span class="sn">6</span>Generate contract PDF<br>(all 3 tenant names printed)<br>Print contract</td>
        <td>PDF saved to <code>uploads/generated_contracts/</code></td>
        <td></td>
      </tr>
      <tr>
        <td colspan="2" class="text-center text-muted"><em>All 3 parties wet-sign paper contract (offline)</em></td>
        <td><span class="sn">7</span>Scan signed contract to PDF</td>
        <td></td>
        <td class="text-muted">Offline step — outside system involvement.</td>
      </tr>
      <tr>
        <td></td><td></td>
        <td><span class="sn">8</span>Upload scanned PDF<br><small>agent/upload_signed_contract.php</small></td>
        <td>Save <code>bookings.signed_contract_path</code><br><code>signed_uploaded_at</code>, <code>signed_uploaded_by</code></td>
        <td>Validation: PDF MIME type, max size.</td>
      </tr>
      <tr class="tr-ok">
        <td></td><td></td><td></td>
        <td><strong>Activate booking</strong><br><span class="sp">bookings → active</span><br><span class="sp">properties → rented</span><br>All 3 <code>co_tenants.status = 'signed'</code></td>
        <td>Verify URL lists all 3 tenant names.</td>
      </tr>
    </tbody>
  </table>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- FLOW 4 — Agent-Led Rental, 1 Tenant, E-sign             -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="flow-section">
  <div class="section-badge r-agent"><i class="bi bi-person-badge-fill"></i>Flow 4 — Agent-Led Rental · 1 Tenant · E-sign
    <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem;">viewing_mode = agent_led</span>
  </div>
  <div class="callout mb-3">
    <strong>Agent-led:</strong> Property page shows <em>"Chat with Agent"</em>.
    Student talks directly to agent. <strong>Student fills the tenant form themselves</strong> — landlord has no form involvement.
    Agent is the primary contact for the entire rental.
  </div>

  <div class="table-responsive">
  <table class="ftbl">
    <thead><tr>
      <th style="width:15%"><span class="role-chip r-student border">Student</span></th>
      <th style="width:15%"><span class="role-chip r-agent border">Agent</span></th>
      <th style="width:12%"><span class="role-chip r-admin border">System</span></th>
      <th>Notes / DB</th>
    </tr></thead>
    <tbody>
      <tr>
        <td><span class="sn">1</span>Browse listings<br>Open property page<br>Click <strong>Chat with Agent</strong></td>
        <td></td>
        <td>Create conversation<br><span class="sp">agent_case</span></td>
        <td>"Chat with Agent" shown because <code>viewing_mode = 'agent_led'</code>.</td>
      </tr>
      <tr>
        <td><span class="sn">2</span>Discuss: property details, viewing slot, rental terms</td>
        <td><span class="sn">3</span>Reply, agree on terms</td>
        <td></td><td></td>
      </tr>
      <tr>
        <td></td>
        <td><span class="sn">4</span>Click <strong>Send Tenant Info Form</strong><br>recipient = student</td>
        <td>Form posted in student–agent chat<br>message_type = <code>tenant_info_form</code></td>
        <td><code>recipient_role = 'student'</code> — key difference from landlord-led.</td>
      </tr>
      <tr>
        <td><span class="sn">5</span>Open tenant form in chat<br>Fill own IC, phone, email, home address<br>Fill tenancy terms (dates, rent, deposit)<br>Submit</td>
        <td></td>
        <td>Create booking row<br><span class="sp">contract_pending</span><br>1 co_tenants row (is_primary=1)</td>
        <td>Student fills their own details. Landlord has no involvement in this step.</td>
      </tr>
      <tr>
        <td></td>
        <td><span class="sn">6</span>Generate Contract → Share e-sign link</td>
        <td>PDF saved</td>
        <td></td>
      </tr>
      <tr>
        <td><span class="sn">7</span>Open e-sign canvas → Draw signature → Submit</td>
        <td></td>
        <td>Record <code>co_tenants.signed_at</code><br>Signature data discarded<br><code>signature_data = NULL</code></td>
        <td>⚠ Signature canvas data deleted immediately after PDF embed. Never stored in DB.</td>
      </tr>
      <tr class="tr-ok">
        <td></td><td></td>
        <td><strong>Activate booking</strong><br><span class="sp">bookings → active</span><br><span class="sp">properties → rented</span><br>Verify URL live</td>
        <td>Landlord sees completed booking in dashboard — had no form involvement.</td>
      </tr>
    </tbody>
  </table>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- FLOW 5 — Agent-Led Rental, 3 Tenants                    -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="flow-section">
  <div class="section-badge r-agent"><i class="bi bi-people-fill"></i>Flow 5 — Agent-Led Rental · 3 Tenants · E-sign or Wet-sign
    <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem;">viewing_mode = agent_led</span>
  </div>
  <div class="callout mb-3">
    Student 1 chats with agent, fills form, and enters S2 &amp; S3 details themselves.
    S2 and S3 do not need platform accounts if using the wet-sign upload path.
  </div>

  <div class="table-responsive">
  <table class="ftbl">
    <thead><tr>
      <th style="width:15%"><span class="role-chip r-student border">Student 1</span></th>
      <th style="width:15%"><span class="role-chip r-agent border">Agent</span></th>
      <th style="width:12%"><span class="role-chip r-admin border">System</span></th>
      <th>Notes / DB</th>
    </tr></thead>
    <tbody>
      <tr>
        <td><span class="sn">1</span>Chat with Agent<br>Inform agent of group of 3</td>
        <td><span class="sn">2</span>Confirm group, discuss terms</td>
        <td></td>
        <td>context_type = <code>agent_case</code></td>
      </tr>
      <tr>
        <td></td>
        <td><span class="sn">3</span>Send Tenant Info Form<br>recipient = student</td>
        <td>Form in student–agent chat</td>
        <td></td>
      </tr>
      <tr>
        <td><span class="sn">4</span>Fill own primary details<br>Add Co-Tenant S2 (full details)<br>Add Co-Tenant S3 (full details)<br>Fill tenancy terms → Submit</td>
        <td></td>
        <td>Create booking row<br><span class="sp">contract_pending</span><br>3 co_tenants rows</td>
        <td>S1 enters S2 &amp; S3 details — platform accounts not required for S2/S3 on wet-sign path.</td>
      </tr>
      <tr>
        <td></td>
        <td><span class="sn">5</span>Generate Contract (3 tenant names)<br>Choose signing path:</td>
        <td>PDF saved</td>
        <td></td>
      </tr>
      <tr class="tr-ok">
        <td><span class="sn">6a</span><strong>PATH A — E-sign</strong><br>S1 opens e-sign canvas<br>Signs contract</td>
        <td></td>
        <td>Record <code>signed_at</code> for S1<br>Signature data discarded<br><strong>Booking activates after S1 signs</strong></td>
        <td>S2 and S3 remain as unsigned co_tenants rows — booking still activates on S1 (primary) sign.</td>
      </tr>
      <tr class="tr-ok">
        <td colspan="2" class="text-center text-muted"><em>PATH B — All 3 wet-sign paper contract (offline)</em></td>
        <td></td>
        <td></td>
      </tr>
      <tr class="tr-ok">
        <td></td>
        <td><strong>PATH B — Upload wet-signed PDF</strong><br><small>agent/upload_signed_contract.php</small></td>
        <td><strong>Booking activates on upload</strong><br>All 3 <code>co_tenants.status = 'signed'</code></td>
        <td>Verify URL lists all 3 tenants.</td>
      </tr>
    </tbody>
  </table>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- FLOW 6 — Housemate Post → Group Rental                  -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="flow-section">
  <div class="section-badge r-group"><i class="bi bi-chat-dots-fill"></i>Flow 6 — Rent via Housemate Post (Co-tenancy Group)</div>
  <div class="callout mb-3">
    A student creates a post to find housemates. Applicants apply, are accepted, and join a group chat.
    Once the group is formed, the primary tenant initiates the rental via Flow 2 or Flow 4 depending on viewing_mode.
    Co-tenants are added via the tenant form at contract stage.
  </div>

  <div class="row g-3 mb-3">
    <!-- Poster side -->
    <div class="col-md-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header fw-bold" style="background:#e0cffc; color:#3b0764; font-size:.82rem;">
          <i class="bi bi-megaphone-fill me-2"></i>Poster — Primary Tenant
          <small class="text-muted ms-2">student/manage_post.php</small>
        </div>
        <div class="card-body p-3" style="font-size:.81rem;">
          <div class="d-flex flex-column gap-2">
            <div><span class="sn" style="background:#6f42c1;">1</span> Create housemate post<br>
              <small class="text-muted">Fields: property type, city, max rent per person, move-in date, semesters needed, spots, intro</small></div>
            <div class="text-center text-muted">↓</div>
            <div><span class="sn" style="background:#6f42c1;">2</span> Post published — status <span class="sp">open</span><br>
              Visible on <code>student/partners.php</code></div>
            <div class="text-center text-muted">↓</div>
            <div><span class="sn" style="background:#6f42c1;">3</span> Receive applications — review each</div>
            <div class="text-center text-muted">↓</div>
            <div class="row g-2">
              <div class="col-6">
                <div class="p-2 rounded border border-success bg-light">
                  <strong class="text-success">Accept</strong><br>
                  <small>Application → <span class="sp">accepted</span><br>
                    ✅ <strong>Group chat created</strong><br>context_type = <code>housemate_group</code><br>
                    Applicant added to <code>conversation_participants</code></small>
                </div>
              </div>
              <div class="col-6">
                <div class="p-2 rounded border border-danger bg-light">
                  <strong class="text-danger">Reject</strong><br>
                  <small>Application → <span class="sp">rejected</span><br>Applicant notified</small>
                </div>
              </div>
            </div>
            <div class="text-center text-muted">↓</div>
            <div><span class="sn" style="background:#6f42c1;">4</span> All spots filled → post <span class="sp">closed</span><br>
              Or poster manually closes</div>
            <div class="text-center text-muted">↓</div>
            <div><span class="sn" style="background:#6f42c1;">5</span> Primary tenant initiates rental<br>
              → Follow <strong>Flow 2</strong> (landlord_led) or <strong>Flow 4</strong> (agent_led)<br>
              <small class="text-muted">Adds accepted housemates as co-tenants in tenant form (Step 5 of those flows)</small></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Applicant side -->
    <div class="col-md-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header fw-bold" style="background:#f8d7da; color:#58151c; font-size:.82rem;">
          <i class="bi bi-person-plus-fill me-2"></i>Applicant — Other Student
          <small class="text-muted ms-2">student/housemate_post.php</small>
        </div>
        <div class="card-body p-3" style="font-size:.81rem;">
          <div class="d-flex flex-column gap-2">
            <div><span class="sn">1</span> Browse housemate posts<br>
              <small class="text-muted">Filter: city, rent, property type, semesters</small></div>
            <div class="text-center text-muted">↓</div>
            <div><span class="sn">2</span> View post details<br>
              <small class="text-muted">Estimated per-person rent, poster profile, spots left</small></div>
            <div class="text-center text-muted">↓</div>
            <div><span class="sn">3</span> Submit application — write introduction<br>
              Status → <span class="sp">pending</span></div>
            <div class="text-center text-muted">↓</div>
            <div class="row g-2">
              <div class="col-6">
                <div class="p-2 rounded border border-success bg-light">
                  <strong class="text-success">Accepted</strong><br>
                  <small>Joined group chat<br>context_type = <code>housemate_group</code><br>
                    Coordinate move-in, split costs</small>
                </div>
              </div>
              <div class="col-6">
                <div class="p-2 rounded border border-danger bg-light">
                  <strong class="text-danger">Rejected</strong><br>
                  <small>Can apply to other posts</small>
                </div>
              </div>
            </div>
            <div class="text-center text-muted">↓</div>
            <div><span class="sn">4</span> <strong>Group chat</strong> with all accepted housemates<br>
              Quick replies: <em>"Hi everyone!", "When can we meet to discuss?",<br>"Sounds good to me.", "Let me check and get back."</em></div>
            <div class="text-center text-muted">↓</div>
            <div><span class="sn">5</span> Primary tenant adds them as co-tenants<br>
              in tenant form — recorded in <code>co_tenants</code> table</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- FLOW 7 — Agent Transfer                                  -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="flow-section">
  <div class="section-badge r-admin"><i class="bi bi-arrow-left-right"></i>Flow 7 — Agent Transfer</div>

  <div class="table-responsive">
  <table class="ftbl">
    <thead><tr>
      <th style="width:14%"><span class="role-chip r-agent border">Agent (current)</span></th>
      <th style="width:14%"><span class="role-chip r-admin border">System / Admin</span></th>
      <th style="width:14%"><span class="role-chip r-agent border">Candidate Agents</span></th>
      <th>Notes / DB</th>
    </tr></thead>
    <tbody>
      <tr>
        <td><span class="sn">1</span>Initiate transfer request<br><small>agent/request_transfer.php</small><br>Provide reason</td>
        <td></td><td></td>
        <td>DB: <code>agent_transfer_requests</code> row, <code>status = 'pending'</code>, <code>batch_number = 1</code>.</td>
      </tr>
      <tr>
        <td></td>
        <td><span class="sn">2</span>Select next 5 agents (FIFO)<br>Excludes: current agent + previously declined<br>Insert 5 <code>agent_transfer_notifications</code> rows</td>
        <td></td>
        <td><code>response = 'pending'</code>, <code>notified_at</code> set.</td>
      </tr>
      <tr class="tr-ok">
        <td></td><td></td>
        <td><span class="sn">3a</span><i class="bi bi-check-circle text-success"></i> <strong>Accept</strong><br><small>agent/respond_transfer.php?action=accept</small></td>
        <td>DB: <code>properties.assigned_agent_id</code> updated, new <code>property_agent_assignments</code> row (<code>assignment_type = 'transfer'</code>), <code>agent_transfer_requests.status = 'completed'</code>.</td>
      </tr>
      <tr class="tr-err">
        <td></td><td></td>
        <td><span class="sn">3b</span><i class="bi bi-x-circle text-danger"></i> <strong>Decline</strong></td>
        <td>If all 5 in batch declined → <code>batch_number</code> incremented, next 5 notified.<br>
          If all agents exhausted → <code>status = 'failed'</code>, admin notified.</td>
      </tr>
      <tr class="tr-warn">
        <td></td>
        <td><span class="sn">4</span>Admin force-assign<br><small>admin/transfer_requests.php</small><br>Select new agent from dropdown</td>
        <td></td>
        <td>Same DB updates as agent-accept path. Also triggered when admin suspends an agent — auto-initiates transfer for all that agent's properties.</td>
      </tr>
      <tr class="tr-sys">
        <td></td>
        <td colspan="2" class="text-muted"><em>Mid-booking transfers:</em> new agent inherits the booking. <code>bookings.agent_id</code> derived from <code>properties.assigned_agent_id</code> at booking creation.</td>
        <td></td>
      </tr>
    </tbody>
  </table>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- STATUS REFERENCE                                         -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="flow-section">
  <div class="section-badge r-admin"><i class="bi bi-tag-fill"></i>Status Reference</div>

  <div class="row g-3">
    <div class="col-md-6">
      <div class="card border-0 shadow-sm">
        <div class="card-header fw-bold" style="font-size:.8rem;">Booking Status — Active States</div>
        <div class="card-body p-3" style="font-size:.79rem;">
          <table class="table table-sm table-borderless mb-0">
            <tbody>
              <tr><td><span class="sp">contract_pending</span></td><td>Tenant form submitted — agent generating contract</td></tr>
              <tr><td><span class="sp">active</span></td><td>Contract signed &amp; activated — lease in progress</td></tr>
              <tr><td><span class="sp">completed</span></td><td>Lease ended normally</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="callout mt-2 warn">
        <strong>Legacy statuses</strong> (exist in DB schema but not used in active rental flows):<br>
        <code>pending_landlord</code>, <code>pending_agent</code>, <code>agent_assigned</code>,
        <code>agent_verifying</code>, <code>agent_verified</code>, <code>inspection_aborted</code>,
        <code>verification_failed</code>, <code>rejected_by_landlord</code>,
        <code>cancelled_by_student</code>, <code>cancelled_by_landlord</code>, <code>cancelled_by_admin</code>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card border-0 shadow-sm">
        <div class="card-header fw-bold" style="font-size:.8rem;">Property Status</div>
        <div class="card-body p-3" style="font-size:.79rem;">
          <table class="table table-sm table-borderless mb-0">
            <tbody>
              <tr><td><span class="sp">pending_approval</span></td><td>Submitted by landlord — awaiting agent review</td></tr>
              <tr><td><span class="sp">available</span></td><td>Agent approved — visible on listings</td></tr>
              <tr><td><span class="sp">rented</span></td><td>Active contract — not on listings</td></tr>
              <tr><td><span class="sp">rejected</span></td><td>Agent rejected at registration or post-inspection</td></tr>
              <tr><td><span class="sp">hidden</span></td><td>Landlord temporarily hid the listing</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- CONVERSATION TYPES                                        -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="flow-section">
  <div class="section-badge r-admin"><i class="bi bi-chat-fill"></i>Conversation Context Types</div>
  <div class="row g-2">
    <?php
    $ctypes = [
      ['property_inquiry','Student ↔ Landlord','Initial enquiry before any contract. Created when student clicks "Chat with Landlord".','r-landlord'],
      ['agent_case',      'Student ↔ Agent OR Agent ↔ Landlord','Opened when student clicks "Chat with Agent" (agent_led), or when agent accepts property assignment (for listing coordination).','r-agent'],
      ['contract_prep',   'Landlord ↔ Agent or Student ↔ Agent','Opened when landlord clicks "Request Contract Preparation". Used for tenant form, contract generation, signing.','r-agent'],
      ['housemate_group', 'Group — all accepted housemates + poster','Group chat opened when first applicant is accepted on a co-tenancy post. All participants in conversation_participants table.','r-group'],
    ];
    foreach ($ctypes as [$type,$parties,$desc,$cls]):
    ?>
    <div class="col-md-6">
      <div class="d-flex gap-3 p-3 rounded border align-items-start <?= $cls ?>" style="font-size:.81rem;">
        <div>
          <div class="fw-bold" style="font-family:monospace;"><?= $type ?></div>
          <div style="font-size:.73rem; opacity:.8;"><?= $parties ?></div>
          <div><?= $desc ?></div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

</div><!-- /container -->

<footer class="border-top py-3 text-center text-muted" style="font-size:.72rem;">
  RentBridge Internal Reference &mdash; flow.php &mdash; <?= date('d M Y') ?>
</footer>
</body>
</html>
