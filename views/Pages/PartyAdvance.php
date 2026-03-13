<?php
/* ================================================================
   PartyAdvance.php — Party Advance Management
   One row per party | Full ledger in history modal
================================================================ */
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once "../../businessLogics/Admin.php";
require_once "../../config/database.php";
require_once "../../businessLogics/Party.php";
require_once "../../businessLogics/PartyAdvanceLogic.php";
Admin::checkAuth();

/* ── AJAX ── */
if (isset($_POST['addAdvance'])) {
    header('Content-Type: application/json');
    echo json_encode(PartyAdvanceLogic::addAdvance($pdo, $_POST)); exit();
}
if (isset($_GET['getOpenAdvances'])) {
    header('Content-Type: application/json');
    echo json_encode(PartyAdvanceLogic::getOpenAdvances($pdo, intval($_GET['PartyId']))); exit();
}
if (isset($_GET['getConsignerBills'])) {
    header('Content-Type: application/json');
    echo json_encode(PartyAdvanceLogic::getConsignerBills($pdo, intval($_GET['PartyId']))); exit();
}
if (isset($_GET['getAgentTrips'])) {
    header('Content-Type: application/json');
    echo json_encode(PartyAdvanceLogic::getAgentTrips($pdo, intval($_GET['AgentId']))); exit();
}
if (isset($_GET['getPartyLedger'])) {
    header('Content-Type: application/json');
    echo json_encode(PartyAdvanceLogic::getPartyLedger($pdo, intval($_GET['PartyId']))); exit();
}
if (isset($_POST['adjustConsigner'])) {
    header('Content-Type: application/json');
    echo json_encode(PartyAdvanceLogic::adjustConsigner($pdo, $_POST)); exit();
}
if (isset($_POST['adjustAgent'])) {
    header('Content-Type: application/json');
    echo json_encode(PartyAdvanceLogic::adjustAgent($pdo, $_POST)); exit();
}

/* ── PAGE DATA ── */
$grouped    = PartyAdvanceLogic::getGroupedByParty($pdo);
$summary    = PartyAdvanceLogic::getSummary($pdo);
$allParties = array_filter(Party::getAll(), fn($p) => $p['IsActive'] === 'Yes');
$consigners = array_values(array_filter($allParties, fn($p) => strtolower($p['PartyType'] ?? '') !== 'agent'));
$agents     = array_values(array_filter($allParties, fn($p) => strtolower($p['PartyType'] ?? '') === 'agent'));

$consRows  = array_values(array_filter($grouped, fn($r) => strtolower($r['PartyType']) !== 'agent'));
$agentRows = array_values(array_filter($grouped, fn($r) => strtolower($r['PartyType']) === 'agent'));

require_once "../layout/header.php";
require_once "../layout/sidebar.php";
?>
<style>
/* ── Header ── */
.pa-hdr{background:linear-gradient(135deg,#3b0764,#6b21a8 50%,#7c3aed);
  border-radius:14px;padding:20px 26px;margin-bottom:20px;
  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
.pa-hdr h4{color:#fff;font-weight:800;font-size:19px;margin:0;display:flex;align-items:center;gap:9px;}
.pa-hdr p{color:rgba(255,255,255,.6);font-size:12px;margin:3px 0 0;}

/* ── Stat pills ── */
.srow{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;}
.spill{background:#fff;border:1px solid #e2e8f0;border-radius:11px;padding:11px 16px;
  display:flex;align-items:center;gap:11px;flex:1;min-width:110px;
  box-shadow:0 1px 3px rgba(0,0,0,.04);}
.sico{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;}
.snum{font-size:17px;font-weight:900;line-height:1.1;}
.slbl{font-size:11px;color:#64748b;margin-top:1px;}
.sp-bl{border-left:4px solid #1d4ed8!important;}
.sp-am{border-left:4px solid #d97706!important;}

/* ── Main tabs ── */
.mtabs{display:flex;gap:0;margin-bottom:0;}
.mtab{padding:12px 28px;font-size:13px;font-weight:800;cursor:pointer;border:none;
  border-radius:11px 11px 0 0;display:flex;align-items:center;gap:7px;transition:all .15s;}
.mc{background:#1a237e;color:#fff;}   .mc.off{background:#e0e7ff;color:#3730a3;}
.ma{background:#78350f;color:#fff;}   .ma.off{background:#fef3c7;color:#92400e;}
.mbg{display:inline-flex;align-items:center;justify-content:center;
  min-width:19px;height:18px;border-radius:9px;font-size:10px;font-weight:800;padding:0 4px;}

/* ── Table wrapper ── */
.tw{border:1px solid #e2e8f0;border-top:none;border-radius:0 0 12px 12px;overflow:hidden;background:#fff;}
.fbar{padding:12px 16px;border-bottom:1px solid #e2e8f0;}
.fbar-c{background:#f0f4ff;} .fbar-a{background:#fffbeb;}

/* ── Table heads ── */
th.hc{background:#1a237e!important;color:#fff!important;font-size:12px;font-weight:700;
  padding:10px 12px;white-space:nowrap;border:none!important;}
th.ha{background:#78350f!important;color:#fff!important;font-size:12px;font-weight:700;
  padding:10px 12px;white-space:nowrap;border:none!important;}

/* ── Party rows ── */
.pa-row td{padding:12px 12px;vertical-align:middle;border-bottom:1px solid #f1f5f9;font-size:13px;}
.pa-row:hover td{background:#f8faff;}
.pa-row.agent-row:hover td{background:#fffdf0;}

/* ── Badges ── */
.pill-c{background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;white-space:nowrap;}
.pill-a{background:#fef3c7;color:#92400e;border:1px solid #fcd34d;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;white-space:nowrap;}
.cnt-pill{display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:700;padding:2px 7px;border-radius:12px;cursor:default;}
.cnt-o{background:#fef9c3;color:#b45309;} .cnt-p{background:#dbeafe;color:#0369a1;} .cnt-f{background:#dcfce7;color:#15803d;}

/* ── Progress bar ── */
.pgw{height:6px;background:#e2e8f0;border-radius:4px;overflow:hidden;margin-top:5px;min-width:80px;}
.pgb{height:100%;border-radius:4px;transition:width .3s;}

/* ── Action buttons ── */
.ic-btn{width:32px;height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:8px;font-size:14px;border:1px solid;}

/* ── Ledger modal ── */
.ldg-row td{padding:8px 12px;font-size:12px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
.ldg-in td{background:#f0fdf4!important;}
.ldg-out td{background:#fff7ed!important;}
.ldg-bal{font-weight:900;font-size:13px;}

/* ── Search input ── */
.search-wrap{position:relative;}
.search-wrap .ri-search-line{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:14px;}
.search-wrap input{padding-left:32px;}

/* ── Empty state ── */
.empty-state{text-align:center;padding:50px 20px;color:#94a3b8;}
.empty-state i{font-size:48px;display:block;margin-bottom:12px;}
</style>

<div class="main-content app-content">
<div class="container-fluid" style="padding-bottom:30px;">

<!-- HEADER -->
<div class="pa-hdr">
  <div>
    <h4><i class="ri-hand-coin-line"></i> Party Advance Management</h4>
    <p><span style="opacity:.8;">Per party summary &nbsp;·&nbsp;</span>
      <span class="pill-c">Consigner</span> advance → Regular Bill &nbsp;&nbsp;
      <span class="pill-a">Agent</span> advance → Agent Trip</p>
  </div>
  <button class="btn fw-bold text-white"
    style="background:#7c3aed;border-radius:9px;height:38px;padding:0 18px;font-size:13px;display:inline-flex;align-items:center;gap:6px;"
    onclick="openAddAdv()">
    <i class="ri-add-circle-line"></i> New Advance
  </button>
</div>

<!-- STATS -->
<div class="srow">
  <div class="spill">
    <div class="sico" style="background:#ede9fe;"><i class="ri-group-line" style="color:#6b21a8;"></i></div>
    <div><div class="snum" style="color:#6b21a8;"><?= $summary['parties'] ?></div><div class="slbl">Total Parties</div></div>
  </div>
  <div class="spill">
    <div class="sico" style="background:#dbeafe;"><i class="ri-wallet-3-line" style="color:#1e40af;"></i></div>
    <div><div class="snum" style="color:#1e40af;font-size:14px;">Rs.<?= number_format($summary['total'],0) ?></div><div class="slbl">Total Received</div></div>
  </div>
  <div class="spill">
    <div class="sico" style="background:#fee2e2;"><i class="ri-money-cny-box-line" style="color:#dc2626;"></i></div>
    <div><div class="snum" style="color:#dc2626;font-size:14px;">Rs.<?= number_format($summary['remaining'],0) ?></div><div class="slbl">Total Balance</div></div>
  </div>
  <div class="spill sp-bl">
    <div class="sico" style="background:#dbeafe;"><i class="ri-user-line" style="color:#1e40af;"></i></div>
    <div>
      <div class="snum" style="font-size:13px;color:#1e40af;">Rs.<?= number_format($summary['cons_total'],0) ?></div>
      <div class="slbl">Consigner · <?= $summary['cons_parties'] ?> parties</div>
      <div style="font-size:10px;color:#dc2626;">Bal: Rs.<?= number_format($summary['cons_remaining'],0) ?></div>
    </div>
  </div>
  <div class="spill sp-am">
    <div class="sico" style="background:#fef3c7;"><i class="ri-user-star-line" style="color:#92400e;"></i></div>
    <div>
      <div class="snum" style="font-size:13px;color:#92400e;">Rs.<?= number_format($summary['agent_total'],0) ?></div>
      <div class="slbl">Agent · <?= $summary['agent_parties'] ?> parties</div>
      <div style="font-size:10px;color:#dc2626;">Bal: Rs.<?= number_format($summary['agent_remaining'],0) ?></div>
    </div>
  </div>
</div>

<!-- MAIN TABS -->
<div class="mtabs">
  <button class="mtab mc" id="mt-c" onclick="switchTab('c')">
    <i class="ri-user-line"></i> Consigner
    <span class="mbg" style="background:rgba(255,255,255,.22);color:#fff;"><?= count($consRows) ?></span>
  </button>
  <button class="mtab ma off" id="mt-a" onclick="switchTab('a')">
    <i class="ri-user-star-line"></i> Agent
    <span class="mbg" style="background:rgba(120,53,15,.2);color:#92400e;"><?= count($agentRows) ?></span>
  </button>
</div>

<?php
/* ── Helper: render one section ── */
function renderSection(array $rows, string $pfx, bool $isAgent): void {
    $thCls = $isAgent ? 'ha' : 'hc';
    $fb    = $isAgent ? 'fbar-a' : 'fbar-c';
    $srClr = $isAgent ? '#d97706' : '#1d4ed8';
    ?>
    <div class="tw">
      <!-- Filter bar -->
      <div class="fbar <?= $fb ?>">
        <div class="row g-2 align-items-center">
          <div class="col-md-4">
            <div class="search-wrap">
              <i class="ri-search-line"></i>
              <input type="text" id="sr_<?= $pfx ?>" class="form-control form-control-sm"
                placeholder="Search party name, city..." oninput="filterTable('<?= $pfx ?>')">
            </div>
          </div>
          <div class="col-auto ms-auto">
            <span id="fc_<?= $pfx ?>" class="fw-bold text-white rounded px-2 py-1"
              style="background:<?= $srClr ?>;font-size:11px;"><?= count($rows) ?>/<?= count($rows) ?></span>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="table-responsive">
      <table class="table mb-0 w-100" id="tbl_<?= $pfx ?>">
        <thead>
          <tr>
            <th class="<?= $thCls ?>" style="width:36px;">#</th>
            <th class="<?= $thCls ?>">Party</th>
            <th class="<?= $thCls ?>" style="width:80px;">Entries</th>
            <th class="<?= $thCls ?> text-end">Total Received</th>
            <th class="<?= $thCls ?>">Used / Progress</th>
            <th class="<?= $thCls ?> text-end">Balance</th>
            <th class="<?= $thCls ?>" style="width:90px;">Last Date</th>
            <th class="<?= $thCls ?> text-center" style="width:110px;">Actions</th>
          </tr>
        </thead>
        <tbody id="tb_<?= $pfx ?>">
        <?php if (empty($rows)): ?>
          <tr><td colspan="8">
            <div class="empty-state"><i class="ri-inbox-line"></i>No advances found</div>
          </td></tr>
        <?php else: ?>
        <?php foreach ($rows as $i => $r):
            $pct = $r['TotalReceived'] > 0 ? min(100, round($r['TotalAdjusted'] / $r['TotalReceived'] * 100)) : 0;
            $bar = $pct >= 100 ? '#16a34a' : ($isAgent ? '#d97706' : '#0284c7');
            $balClr = floatval($r['TotalRemaining']) > 0 ? '#dc2626' : '#15803d';
            $nameClr = $isAgent ? '#92400e' : '#1e40af';
            $rowCls  = $isAgent ? 'pa-row agent-row' : 'pa-row';
        ?>
          <tr class="<?= $rowCls ?>" data-name="<?= strtolower(htmlspecialchars($r['PartyName'])) ?>" data-city="<?= strtolower(htmlspecialchars($r['City']??'')) ?>">
            <td class="text-muted fw-medium"><?= $i+1 ?></td>
            <td>
              <div class="fw-bold" style="color:<?= $nameClr ?>;font-size:13px;"><?= htmlspecialchars($r['PartyName']) ?></div>
              <div style="font-size:11px;color:#64748b;">
                <?= htmlspecialchars($r['City'] ?? '') ?>
                <?= !empty($r['MobileNo']) ? ' &nbsp;·&nbsp; <i class="ri-smartphone-line"></i> '.$r['MobileNo'] : '' ?>
              </div>
              <?= $isAgent ? '<span class="pill-a" style="margin-top:3px;display:inline-block;">Agent</span>' : '<span class="pill-c" style="margin-top:3px;display:inline-block;">Consigner</span>' ?>
            </td>
            <td>
              <div class="d-flex flex-wrap gap-1">
                <?php if ($r['OpenCount'] > 0): ?>
                <span class="cnt-pill cnt-o" title="Open"><?= $r['OpenCount'] ?> Open</span>
                <?php endif; ?>
                <?php if ($r['PartialCount'] > 0): ?>
                <span class="cnt-pill cnt-p" title="Partial"><?= $r['PartialCount'] ?> Part</span>
                <?php endif; ?>
                <?php if ($r['FullCount'] > 0): ?>
                <span class="cnt-pill cnt-f" title="Fully used"><?= $r['FullCount'] ?> Full</span>
                <?php endif; ?>
              </div>
            </td>
            <td class="text-end fw-bold" style="color:<?= $nameClr ?>;font-size:13px;">
              Rs.<?= number_format($r['TotalReceived'], 0) ?>
            </td>
            <td style="min-width:130px;">
              <div class="d-flex justify-content-between" style="font-size:12px;">
                <span class="text-success fw-semibold">Rs.<?= number_format($r['TotalAdjusted'], 0) ?></span>
                <span style="color:#94a3b8;"><?= $pct ?>%</span>
              </div>
              <div class="pgw"><div class="pgb" style="width:<?= $pct ?>%;background:<?= $bar ?>;"></div></div>
            </td>
            <td class="text-end fw-bold" style="color:<?= $balClr ?>;font-size:14px;">
              Rs.<?= number_format($r['TotalRemaining'], 0) ?>
            </td>
            <td style="font-size:12px;color:#64748b;white-space:nowrap;">
              <?= $r['LastDate'] ? date('d-m-Y', strtotime($r['LastDate'])) : '—' ?>
            </td>
            <td>
              <div class="d-flex gap-1 justify-content-center">
                <!-- Adjust button -->
                <?php if (floatval($r['TotalRemaining']) > 0): ?>
                <button class="ic-btn <?= $isAgent ? 'btn-outline-warning text-warning' : 'btn-outline-primary text-primary' ?>"
                  title="<?= $isAgent ? 'Adjust vs Agent Trip' : 'Adjust vs Regular Bill' ?>"
                  onclick="openAdj(<?= $r['PartyId'] ?>,'<?= addslashes($r['PartyName']) ?>',<?= floatval($r['TotalRemaining']) ?>,<?= intval($isAgent) ?>)">
                  <i class="ri-exchange-line"></i>
                </button>
                <?php endif; ?>
                <!-- Ledger / History -->
                <button class="ic-btn btn-outline-info text-info" title="Party Ledger"
                  onclick="openLedger(<?= $r['PartyId'] ?>,'<?= addslashes($r['PartyName']) ?>',<?= intval($isAgent) ?>)">
                  <i class="ri-book-2-line"></i>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
      </div>
    </div>
    <?php
}
?>

<!-- CONSIGNER SECTION -->
<div id="sec-c">
  <?php renderSection($consRows, 'c', false); ?>
</div>

<!-- AGENT SECTION -->
<div id="sec-a" style="display:none;">
  <?php renderSection($agentRows, 'a', true); ?>
</div>

</div></div>

<!-- ══════════════ ADD ADVANCE MODAL ══════════════ -->
<div class="modal fade" id="addAdvModal" tabindex="-1">
<div class="modal-dialog modal-md">
<div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;">
  <div class="modal-header text-white py-3" style="background:linear-gradient(135deg,#3b0764,#7c3aed);">
    <h5 class="modal-title fw-bold"><i class="ri-hand-coin-line me-2"></i>New Party Advance Entry</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body">
    <!-- Type hint -->
    <div id="adv_hint" style="display:none;" class="rounded p-2 mb-3 fs-12 fw-medium"></div>

    <div class="row g-3">
      <div class="col-12">
        <label class="form-label fw-semibold fs-13">Party Type</label>
        <div class="btn-group w-100" role="group">
          <input type="radio" class="btn-check" name="adv_ptype" id="pt_cons" value="cons" autocomplete="off" checked>
          <label class="btn btn-outline-primary fw-bold" for="pt_cons">
            <i class="ri-user-line me-1"></i>Consigner
          </label>
          <input type="radio" class="btn-check" name="adv_ptype" id="pt_agent" value="agent" autocomplete="off">
          <label class="btn btn-outline-warning fw-bold" for="pt_agent">
            <i class="ri-user-star-line me-1"></i>Agent
          </label>
        </div>
      </div>
      <div class="col-12">
        <label class="form-label fw-semibold fs-13">Party <span class="text-danger">*</span></label>
        <!-- Consigner select wrapper -->
        <div id="wrap_ConsParty">
        <select id="adv_ConsParty" class="form-select">
          <option value="">-- Select Consigner --</option>
          <?php foreach ($consigners as $p): ?>
          <option value="<?= $p['PartyId'] ?>">
            <?= htmlspecialchars($p['PartyName']) ?>
            <?= !empty($p['MobileNo']) ? ' | '.$p['MobileNo'] : '' ?>
            <?= !empty($p['City']) ? ' — '.$p['City'] : '' ?>
          </option>
          <?php endforeach; ?>
        </select>
        </div>
        <!-- Agent select wrapper -->
        <div id="wrap_AgentParty" style="display:none;">
        <select id="adv_AgentParty" class="form-select">
          <option value="">-- Select Agent --</option>
          <?php foreach ($agents as $p): ?>
          <option value="<?= $p['PartyId'] ?>">
            <?= htmlspecialchars($p['PartyName']) ?>
            <?= !empty($p['MobileNo']) ? ' | '.$p['MobileNo'] : '' ?>
            <?= !empty($p['City']) ? ' — '.$p['City'] : '' ?>
          </option>
          <?php endforeach; ?>
        </select>
        </div>
      </div>
      <div class="col-6">
        <label class="form-label fw-semibold fs-13">Date <span class="text-danger">*</span></label>
        <input type="date" id="adv_Date" class="form-control" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="col-6">
        <label class="form-label fw-semibold fs-13">Amount <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text fw-bold bg-light">Rs.</span>
          <input type="number" id="adv_Amount" class="form-control fw-bold" step="0.01" min="0.01" placeholder="0.00">
        </div>
      </div>
      <div class="col-6">
        <label class="form-label fw-semibold fs-13">Mode</label>
        <select id="adv_Mode" class="form-select">
          <option value="Cash">💵 Cash</option><option value="Cheque">📋 Cheque</option>
          <option value="NEFT">🏦 NEFT</option><option value="RTGS">🏦 RTGS</option>
          <option value="UPI">📱 UPI</option><option value="Other">Other</option>
        </select>
      </div>
      <div class="col-6">
        <label class="form-label fw-semibold fs-13">Reference No.</label>
        <input type="text" id="adv_Ref" class="form-control" placeholder="Cheque / UTR">
      </div>
      <div class="col-12">
        <label class="form-label fw-semibold fs-13">Remarks</label>
        <input type="text" id="adv_Remarks" class="form-control" placeholder="Optional...">
      </div>
    </div>
  </div>
  <div class="modal-footer py-2 gap-2">
    <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
    <button class="btn fw-bold text-white btn-sm px-4" style="background:#7c3aed;" onclick="saveAdvance()">
      <i class="ri-save-3-line me-1"></i>Save Advance
    </button>
  </div>
</div></div></div>

<!-- ══════════════ ADJUST MODAL ══════════════ -->
<div class="modal fade" id="adjModal" tabindex="-1">
<div class="modal-dialog modal-lg">
<div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;">
  <div class="modal-header text-white py-3" id="adjModalHdr">
    <h5 class="modal-title fw-bold" id="adjModalTitle"><i class="ri-exchange-line me-2"></i>Adjust Advance</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body">
    <!-- Party info bar -->
    <div class="rounded p-3 mb-3" id="adj_info_bar">
      <div class="row g-0 text-center">
        <div class="col-4" id="adj_col1">
          <div class="fs-10 fw-bold text-uppercase text-muted">Party</div>
          <div class="fw-bold fs-13 mt-1" id="adj_partyName">—</div>
        </div>
        <div class="col-4" id="adj_col2">
          <div class="fs-10 fw-bold text-uppercase text-muted">Total Balance</div>
          <div class="fw-bold mt-1" style="font-size:22px;color:#15803d;" id="adj_totalBal">Rs.0</div>
        </div>
        <div class="col-4" id="adj_col3">
          <div class="fs-10 fw-bold text-uppercase text-muted">Will Adjust From</div>
          <div class="fw-bold fs-13 mt-1" style="color:#0369a1;">Oldest First (Auto)</div>
        </div>
      </div>
    </div>

    <input type="hidden" id="adj_PartyId">
    <input type="hidden" id="adj_IsAgent">

    <!-- Select bill/trip -->
    <div class="mb-3" id="adj_billWrap">
      <label class="form-label fw-semibold" id="adj_billLabel">Select Unpaid Bill <span class="text-danger">*</span></label>
      <select id="adj_BillSel" class="form-select" onchange="billSel()">
        <option value="">-- Select --</option>
      </select>
    </div>
    <div id="adj_billInfo" class="rounded p-2 mb-3 fs-13" style="display:none;border:1px solid #e2e8f0;background:#f8fafc;">
      Net: <b id="bi_net">Rs.0</b> &nbsp;|&nbsp; Paid: <b id="bi_paid">Rs.0</b>
      &nbsp;|&nbsp; Due: <b class="text-danger" id="bi_due">Rs.0</b>
    </div>

    <!-- Amount + Date + Remarks -->
    <div class="row g-3">
      <div class="col-6">
        <label class="form-label fw-semibold">Date</label>
        <input type="date" id="adj_Date" class="form-control" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="col-6">
        <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text fw-bold bg-light">Rs.</span>
          <input type="number" id="adj_Amount" class="form-control fw-bold" step="0.01" min="0.01" placeholder="0.00">
        </div>
      </div>
      <div class="col-12">
        <label class="form-label fw-semibold">Remarks</label>
        <input type="text" id="adj_Remarks" class="form-control" placeholder="Optional...">
      </div>
    </div>
  </div>
  <div class="modal-footer py-2 gap-2">
    <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
    <button class="btn fw-bold text-white btn-sm px-4" id="adj_SaveBtn" onclick="saveAdj()">
      <i class="ri-save-3-line me-1"></i>Save Adjustment
    </button>
  </div>
</div></div></div>

<!-- ══════════════ LEDGER MODAL ══════════════ -->
<div class="modal fade" id="ledgerModal" tabindex="-1">
<div class="modal-dialog modal-xl">
<div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;">
  <div class="modal-header text-white py-3" id="ldgHdr">
    <h5 class="modal-title fw-bold"><i class="ri-book-2-line me-2"></i>Party Ledger — <span id="ldg_name"></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body p-0">
    <!-- Summary bar -->
    <div id="ldg_summary" class="p-3 border-bottom" style="background:#f8fafc;"></div>
    <!-- Ledger table -->
    <div class="table-responsive">
    <table class="table mb-0 fs-13">
      <thead class="table-light">
        <tr>
          <th style="width:36px;">#</th>
          <th>Date</th>
          <th>Type</th>
          <th>Details</th>
          <th style="text-align:right;">Amount In ↑</th>
          <th style="text-align:right;">Amount Out ↓</th>
          <th style="text-align:right;width:120px;">Balance</th>
          <th>Remarks</th>
        </tr>
      </thead>
      <tbody id="ldg_body"></tbody>
      <tfoot id="ldg_foot"></tfoot>
    </table>
    </div>
    <div id="ldg_loading" class="text-center py-4" style="display:none;">
      <div class="spinner-border text-primary"></div><div class="mt-2 text-muted">Loading ledger...</div>
    </div>
  </div>
  <div class="modal-footer py-2">
    <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
  </div>
</div></div></div>

<script>
/* ── Tab switch ── */
function switchTab(t){
  if(t==='c'){
    $('#sec-c').show();$('#sec-a').hide();
    $('#mt-c').removeClass('off');$('#mt-a').addClass('off');
  } else {
    $('#sec-c').hide();$('#sec-a').show();
    $('#mt-c').addClass('off');$('#mt-a').removeClass('off');
  }
}

/* ── Table filter ── */
function filterTable(pfx){
  var q=$('#sr_'+pfx).val().toLowerCase();
  var rows=$('#tb_'+pfx+' tr.pa-row, #tb_'+pfx+' tr.agent-row');
  var vis=0;
  rows.each(function(){
    var match=$(this).data('name').includes(q)||$(this).data('city').includes(q)||(q==='');
    $(this).toggle(match);
    if(match) vis++;
  });
  $('#fc_'+pfx).text(vis+'/'+rows.length);
}

/* ── Open Add Advance Modal ── */
function openAddAdv(){
  $('#adv_ConsParty').val(null).trigger('change');
  $('#adv_AgentParty').val(null).trigger('change');
  $('#adv_Amount, #adv_Ref, #adv_Remarks').val('');
  $('#adv_Mode').val('Cash');
  $('#adv_Date').val('<?= date('Y-m-d') ?>');
  /* Reset to Consigner */
  $('#pt_cons').prop('checked', true).trigger('change');
  new bootstrap.Modal(document.getElementById('addAdvModal')).show();
}

/* Party type radio toggle — toggle WRAPPERS (not selects, because Select2 wraps them) */
$(document).on('change', 'input[name="adv_ptype"]', function(){
  var v = $(this).val();
  if(v === 'cons'){
    $('#wrap_ConsParty').show();
    $('#wrap_AgentParty').hide();
    $('#adv_hint')
      .html('<span class="pill-c">Consigner</span> &nbsp;&rarr; Advance will adjust against <b>Regular Bills</b>')
      .css({background:'#eff6ff', border:'1px solid #bfdbfe', color:'#1e40af'}).show();
  } else {
    $('#wrap_ConsParty').hide();
    $('#wrap_AgentParty').show();
    $('#adv_hint')
      .html('<span class="pill-a">Agent</span> &nbsp;&rarr; Advance will adjust against <b>Agent Trips</b>')
      .css({background:'#fffbeb', border:'1px solid #fde68a', color:'#92400e'}).show();
  }
});

/* ── Save Advance ── */
function saveAdvance(){
  var isAgent=$('input[name="adv_ptype"]:checked').val()==='agent';
  var pid = isAgent ? $('#adv_AgentParty').val() : $('#adv_ConsParty').val();
  var amt = parseFloat($('#adv_Amount').val());
  if(!pid){ toast('warning','Please select a party!'); return; }
  if(!amt||amt<=0){ toast('warning','Please enter a valid amount!'); return; }
  loading('Saving...');
  var fd=new FormData();
  fd.append('addAdvance',1); fd.append('PartyId',pid);
  fd.append('AdvanceDate',$('#adv_Date').val()); fd.append('Amount',amt);
  fd.append('PaymentMode',$('#adv_Mode').val()); fd.append('ReferenceNo',$('#adv_Ref').val());
  fd.append('Remarks',$('#adv_Remarks').val());
  post(fd).then(function(res){
    Swal.close();
    if(res.status==='success'){
      bootstrap.Modal.getInstance(document.getElementById('addAdvModal')).hide();
      toast('success','Advance saved!');
      setTimeout(function(){ location.reload(); },1800);
    } else Swal.fire({icon:'error',title:'Error',text:res.msg});
  });
}

/* ── Open Adjust Modal ── */
var _adjIsAgent=false, _advRem=0, _billRem=0;

function openAdj(partyId, partyName, totalRem, isAgent){
  _adjIsAgent = isAgent==1;
  $('#adj_PartyId').val(partyId);
  $('#adj_IsAgent').val(isAgent);
  $('#adj_partyName').text(partyName);
  $('#adj_totalBal').text('Rs.'+parseFloat(totalRem).toFixed(2));
  $('#adj_Amount,#adj_Remarks').val(''); $('#adj_billInfo').hide();
  $('#adj_Date').val('<?= date('Y-m-d') ?>');
  $('#adj_BillSel').html('<option value="">Loading...</option>');

  /* Style modal for type */
  if(_adjIsAgent){
    $('#adjModalHdr').css('background','linear-gradient(135deg,#78350f,#d97706)');
    $('#adjModalTitle').html('<i class="ri-road-map-line me-2"></i>Adjust vs Agent Trip');
    $('#adj_SaveBtn').removeClass('btn-primary').addClass('btn-warning');
    $('#adj_info_bar').css({'background':'#fffbeb','border':'1px solid #fde68a'});
    $('#adj_billLabel').html('Select Unpaid Agent Trip <span class="text-danger">*</span>');
  } else {
    $('#adjModalHdr').css('background','linear-gradient(135deg,#1a237e,#1d4ed8)');
    $('#adjModalTitle').html('<i class="ri-links-line me-2"></i>Adjust vs Regular Bill');
    $('#adj_SaveBtn').removeClass('btn-warning').addClass('btn-primary');
    $('#adj_info_bar').css({'background':'#eff6ff','border':'1px solid #bfdbfe'});
    $('#adj_billLabel').html('Select Unpaid Regular Bill <span class="text-danger">*</span>');
  }

  /* Load bills/trips directly */
  var url = _adjIsAgent
    ? 'PartyAdvance.php?getAgentTrips=1&AgentId='+partyId
    : 'PartyAdvance.php?getConsignerBills=1&PartyId='+partyId;
  fetch(url).then(function(r){return r.json();}).then(function(items){
    var opts='<option value="">-- Select --</option>';
    if(!items.length) opts+='<option disabled>No unpaid '+(_adjIsAgent?'trips':'bills')+' found</option>';
    items.forEach(function(b){
      if(_adjIsAgent){
        opts+='<option value="'+b.id+'" data-net="'+b.netamt+'" data-paid="'+b.paid+'" data-rem="'+b.remaining+'">'
          +'🟡 #'+b.id+' | '+(b.VehicleNumber||'')+' | '+(b.route||'')+' ('+b.TripDate+') — Due: Rs.'+parseFloat(b.remaining).toFixed(0)+'</option>';
      } else {
        opts+='<option value="'+b.id+'" data-net="'+b.netamt+'" data-paid="'+b.paid+'" data-rem="'+b.remaining+'">'
          +'🔵 '+b.billno+' ('+b.billdate+') — Due: Rs.'+parseFloat(b.remaining).toFixed(0)+'</option>';
      }
    });
    $('#adj_BillSel').html(opts);
  });

  new bootstrap.Modal(document.getElementById('adjModal')).show();
}



function billSel(){
  var opt=$('#adj_BillSel option:selected');
  if(!$('#adj_BillSel').val()){ $('#adj_billInfo').hide(); return; }
  _billRem=parseFloat(opt.data('rem')||0);
  $('#bi_net').text('Rs.'+parseFloat(opt.data('net')||0).toFixed(2));
  $('#bi_paid').text('Rs.'+parseFloat(opt.data('paid')||0).toFixed(2));
  $('#bi_due').text('Rs.'+_billRem.toFixed(2));
  $('#adj_billInfo').show();
  /* Auto-fill: min of party total balance vs bill due */
  $('#adj_Amount').val(Math.min(_adjIsAgent? parseFloat($('#adj_totalBal').text().replace('Rs.','').replace(/,/g,'')) : parseFloat($('#adj_totalBal').text().replace('Rs.','').replace(/,/g,'')), _billRem).toFixed(2));
}

function saveAdj(){
  var billId=$('#adj_BillSel').val();
  var amt=parseFloat($('#adj_Amount').val());
  var totalBal=parseFloat($('#adj_totalBal').text().replace('Rs.','').replace(/,/g,''))||0;
  if(!billId){ toast('warning',_adjIsAgent?'Please select a trip!':'Please select a bill!'); return; }
  if(!amt||amt<=0){ toast('warning','Enter valid amount!'); return; }
  if(amt>totalBal){ Swal.fire({icon:'warning',title:'Exceeds balance!',text:'Available Balance: Rs.'+totalBal.toFixed(2)}); return; }
  loading('Adjusting...');
  var fd=new FormData();
  if(_adjIsAgent){
    fd.append('adjustAgent',1); fd.append('TripId',billId);
  } else {
    fd.append('adjustConsigner',1); fd.append('BillId',billId);
  }
  fd.append('PartyId',$('#adj_PartyId').val()); fd.append('AdjustedAmount',amt);
  fd.append('AdjustmentDate',$('#adj_Date').val()); fd.append('Remarks',$('#adj_Remarks').val());
  post(fd).then(function(res){
    Swal.close();
    if(res.status==='success'){
      bootstrap.Modal.getInstance(document.getElementById('adjModal')).hide();
      Swal.fire({icon:'success',title:'Adjusted!',
        text:'Remaining Balance: Rs.'+parseFloat(res.newRemaining).toFixed(2),
        timer:3000,showConfirmButton:false});
      setTimeout(function(){ location.reload(); },2500);
    } else Swal.fire({icon:'error',title:'Error',text:res.msg});
  });
}

/* ── Open Ledger ── */
function openLedger(partyId, partyName, isAgent){
  var isA=isAgent==1;
  var hdrClr = isA?'linear-gradient(135deg,#78350f,#d97706)':'linear-gradient(135deg,#1a237e,#1d4ed8)';
  $('#ldgHdr').css('background',hdrClr);
  $('#ldg_name').text(partyName);
  $('#ldg_body').html('');
  $('#ldg_summary').html('');
  $('#ldg_loading').show();
  new bootstrap.Modal(document.getElementById('ledgerModal')).show();

  fetch('PartyAdvance.php?getPartyLedger=1&PartyId='+partyId)
  .then(function(r){return r.json();}).then(function(data){
    $('#ldg_loading').hide();
    var advances=data.advances, adjs=data.adjustments;

    /* Build summary */
    var totalIn=0, totalOut=0;
    advances.forEach(function(a){ totalIn+=parseFloat(a.amount||0); });
    adjs.forEach(function(a){ totalOut+=parseFloat(a.amount||0); });
    var bal=totalIn-totalOut;
    var summHtml='<div class="row g-3">'
      +'<div class="col-auto"><span class="fw-bold fs-12 text-muted">Total Received:</span> <span class="fw-bold text-success fs-14">Rs.'+totalIn.toFixed(2)+'</span></div>'
      +'<div class="col-auto"><span class="fw-bold fs-12 text-muted">Total Adjusted:</span> <span class="fw-bold text-danger fs-14">Rs.'+totalOut.toFixed(2)+'</span></div>'
      +'<div class="col-auto"><span class="fw-bold fs-12 text-muted">Available Balance:</span> <span class="fw-bold fs-14" style="color:'+(bal>0?'#dc2626':'#15803d')+'">Rs.'+bal.toFixed(2)+'</span></div>'
      +'<div class="col-auto"><span class="fw-bold fs-12 text-muted">Advance Entries:</span> <span class="fw-bold">'+advances.length+'</span></div>'
      +'<div class="col-auto"><span class="fw-bold fs-12 text-muted">Adjustments:</span> <span class="fw-bold">'+adjs.length+'</span></div>'
      +'</div>';
    $('#ldg_summary').html(summHtml);

    /* Merge and sort all txns by date */
    var txns=[];
    advances.forEach(function(a){
      txns.push({date:a.txn_date, type:'IN', amount:parseFloat(a.amount||0),
        mode:a.PaymentMode, ref:a.ReferenceNo, remarks:a.Remarks,
        detail:'Advance received ('+a.PaymentMode+(a.ReferenceNo?' | '+a.ReferenceNo:'')+')',
        advId:a.id, status:a.Status, adjAmt:parseFloat(a.AdjustedAmount||0), remAmt:parseFloat(a.RemainingAmount||0)
      });
    });
    adjs.forEach(function(a){
      var det=a.bill_type==='RegularBill'?('Bill: '+(a.BillNo||'#'+a.id)):('Trip: '+(a.VehicleNumber||'')+(a.route?' | '+a.route:''));
      txns.push({date:a.txn_date, type:'OUT', amount:parseFloat(a.amount||0),
        remarks:a.Remarks, detail:det, billType:a.bill_type, ref:a.ref_label
      });
    });
    txns.sort(function(x,y){ return x.date.localeCompare(y.date)||(x.type==='IN'?-1:1); });

    /* Build running balance */
    var running=0;
    var html='';
    if(!txns.length){
      html='<tr><td colspan="8" class="text-center text-muted py-4">No transactions found</td></tr>';
    }
    txns.forEach(function(t,i){
      if(t.type==='IN') running+=t.amount; else running-=t.amount;
      var isIn=t.type==='IN';
      var rowCls=isIn?'ldg-in':'ldg-out';
      var badge='', inAmt='', outAmt='';
      if(isIn){
        badge='<span style="background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:700;">▲ IN</span>';
        inAmt='<span class="fw-bold text-success">Rs.'+t.amount.toFixed(2)+'</span>';
        outAmt='<span class="text-muted">—</span>';
      } else {
  
        badge='<span style="background:#fee2e2;color:#dc2626;border:1px solid #fecaca;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:700;">▼ OUT</span>';
        inAmt='<span class="text-muted">—</span>';
        outAmt='<span class="fw-bold text-danger">Rs.'+t.amount.toFixed(2)+'</span>';
      }
      var balClr=running>0?'#dc2626':'#15803d';
      html+='<tr class="ldg-row '+rowCls+'">'
        +'<td class="text-muted">'+(i+1)+'</td>'
        +'<td style="white-space:nowrap;">'+t.date+'</td>'
        +'<td>'+badge+'</td>'
        +'<td><div class="fw-medium" style="font-size:12px;">'+t.detail+'</div>'
          +(t.remarks?'<div class="text-muted" style="font-size:11px;">'+t.remarks+'</div>':'')
          +'</td>'
        +'<td style="text-align:right;">'+inAmt+'</td>'
        +'<td style="text-align:right;">'+outAmt+'</td>'
        +'<td style="text-align:right;"><span class="ldg-bal" style="color:'+balClr+';">Rs.'+running.toFixed(2)+'</span></td>'
        +'<td><small class="text-muted">'+(t.remarks||'—')+'</small></td>'
        +'</tr>';
    });

    $('#ldg_body').html(html);
    /* Foot totals */
    $('#ldg_foot').html('<tr style="background:#f8fafc;font-weight:700;">'
      +'<td colspan="4" class="text-end" style="padding:10px 12px;">Total:</td>'
      +'<td style="text-align:right;padding:10px 12px;color:#15803d;">Rs.'+totalIn.toFixed(2)+'</td>'
      +'<td style="text-align:right;padding:10px 12px;color:#dc2626;">Rs.'+totalOut.toFixed(2)+'</td>'
      +'<td style="text-align:right;padding:10px 12px;color:'+(bal>0?'#dc2626':'#15803d')+';">Rs.'+bal.toFixed(2)+'</td>'
      +'<td></td></tr>');
  });
}

/* ── Utils ── */
function post(fd){ return fetch('PartyAdvance.php',{method:'POST',body:fd}).then(function(r){return r.json();}).catch(function(){return{status:'error',msg:'Server Error'};}); }
function toast(icon,title){ Swal.fire({icon:icon,title:title,toast:true,position:'top-end',timer:2000,showConfirmButton:false}); }
function loading(msg){ Swal.fire({title:msg,allowOutsideClick:false,didOpen:function(){Swal.showLoading();}}); }

$(document).ready(function(){
  /* Select2 for party dropdowns */
  $('#adv_ConsParty').select2({theme:'bootstrap-5', placeholder:'Search by name or mobile...', allowClear:true, dropdownParent:$('#addAdvModal'), width:'100%'});
  $('#adv_AgentParty').select2({theme:'bootstrap-5', placeholder:'Search agent by name or mobile...', allowClear:true, dropdownParent:$('#addAdvModal'), width:'100%'});
  /* Trigger initial state */
  $('#pt_cons').trigger('change');
});

window.addEventListener('offline',function(){ toast('warning','Internet Disconnected!'); });
window.addEventListener('online', function(){ toast('success','Back Online!'); });
</script>
<?php require_once "../layout/footer.php"; ?>
