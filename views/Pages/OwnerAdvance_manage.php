<?php
/* ================================================================
   OwnerAdvance_manage.php  —  Vehicle Owner Advance Management
   Purple theme · Tabs: All / Open / Partial / Fully Used
   Date filter · Owner Summary popup · DataTables per tab
================================================================ */
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

require_once "../../businessLogics/Admin.php";
require_once "../../businessLogics/OwnerAdvance.php";
require_once "../../businessLogics/OwnerPayment.php";
Admin::checkAuth();

/* ── AJAX HANDLERS ── */
if (isset($_POST['addAdvance'])) {
  header('Content-Type: application/json');
  echo json_encode(OwnerAdvance::insert($_POST));
  exit();
}
if (isset($_GET['getOwnerTrips'])) {
  header('Content-Type: application/json');
  echo json_encode(OwnerAdvance::getOwnerUnpaidTrips(intval($_GET['OwnerId'])));
  exit();
}
if (isset($_POST['adjustAdvance'])) {
  header('Content-Type: application/json');
  echo json_encode(OwnerAdvance::adjustAgainstTrip(
    intval($_POST['OwnerAdvanceId']),
    intval($_POST['TripId']),
    intval($_POST['OwnerId']),
    floatval($_POST['AdjustedAmount']),
    $_POST['AdjustmentDate'] ?? date('Y-m-d'),
    trim($_POST['Remarks'] ?? '')
  ));
  exit();
}
if (isset($_GET['getAdjustments'])) {
  header('Content-Type: application/json');
  echo json_encode(OwnerAdvance::getAdjustments(intval($_GET['AdvanceId'])));
  exit();
}

/* ── Date Filter ── */
$today      = date('Y-m-d');
$datePreset = $_GET['datePreset'] ?? 'all';
$dateFrom   = $_GET['dateFrom']   ?? '';
$dateTo     = $_GET['dateTo']     ?? '';

switch ($datePreset) {
  case 'today':
    $dateFrom = $dateTo = $today;
    break;
  case 'yesterday':
    $dateFrom = $dateTo = date('Y-m-d', strtotime('-1 day'));
    break;
  case 'thisweek':
    $dateFrom = date('Y-m-d', strtotime('monday this week'));
    $dateTo   = date('Y-m-d', strtotime('sunday this week'));
    break;
  case 'custom':
    break;
  default:
    $dateFrom = $dateTo = '';
}

/* ── PAGE DATA ── */
$filterOwner = !empty($_GET['ownerId']) ? intval($_GET['ownerId']) : null;
$allAdvances = OwnerAdvance::getAll($filterOwner);
$owners      = OwnerPayment::getOwners();
$summary     = OwnerAdvance::getSummary();

/* Filter by date if set */
$advances = $allAdvances;
if ($dateFrom && $dateTo) {
  $advances = array_values(array_filter($advances, function ($a) use ($dateFrom, $dateTo) {
    return $a['AdvanceDate'] >= $dateFrom && $a['AdvanceDate'] <= $dateTo;
  }));
}

/* Split by status */
$advOpen    = array_values(array_filter($advances, fn($a) => $a['Status'] === 'Open'));
$advPartial = array_values(array_filter($advances, fn($a) => $a['Status'] === 'PartiallyAdjusted'));
$advFull    = array_values(array_filter($advances, fn($a) => $a['Status'] === 'FullyAdjusted'));
$cntAll = count($advances);
$cntO = count($advOpen);
$cntP   = count($advPartial);
$cntF = count($advFull);

require_once "../layout/header.php";
require_once "../layout/sidebar.php";
?>
<style>
  /* ── Header ── */
  .adv-hdr {
    background: linear-gradient(135deg, #3b0764, #6d28d9 60%, #7c3aed);
    border-radius: 14px;
    padding: 20px 26px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
  }

  .adv-hdr h4 {
    color: #fff;
    font-weight: 800;
    font-size: 19px;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 9px;
  }

  .adv-hdr p {
    color: rgba(255, 255, 255, .65);
    font-size: 12px;
    margin: 3px 0 0;
  }

  /* ── Stat pills ── */
  .srow {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 18px;
  }

  .spill {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 11px;
    padding: 11px 16px;
    display: flex;
    align-items: center;
    gap: 11px;
    flex: 1;
    min-width: 120px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, .04);
  }

  .sico {
    width: 38px;
    height: 38px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    flex-shrink: 0;
  }

  .snum {
    font-size: 15px;
    font-weight: 900;
    line-height: 1.1;
  }

  .slbl {
    font-size: 11px;
    color: #64748b;
    margin-top: 1px;
  }

  .pgw {
    height: 6px;
    background: #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 4px;
  }

  .pgb {
    height: 100%;
    border-radius: 4px;
  }

  /* ── Date Filter ── */
  .date-filter-bar {
    background: #fff;
    border: 1px solid #ddd6fe;
    border-radius: 12px;
    padding: 14px 18px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }

  .df-preset {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
  }

  .df-btn {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    border: 2px solid #e2e8f0;
    background: #f8fafc;
    color: #64748b;
    cursor: pointer;
    transition: all .15s;
    white-space: nowrap;
    text-decoration: none;
    display: inline-block;
  }

  .df-btn:hover {
    border-color: #7c3aed;
    color: #7c3aed;
    background: #f5f3ff;
  }

  .df-btn.active {
    border-color: #7c3aed;
    background: #7c3aed;
    color: #fff;
  }

  .df-sep {
    width: 1px;
    height: 30px;
    background: #e2e8f0;
    margin: 0 4px;
  }

  .df-range {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .df-range input[type=date] {
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    padding: 5px 10px;
    font-size: 12px;
    font-weight: 600;
    color: #374151;
    outline: none;
    transition: border-color .15s;
  }

  .df-range input[type=date]:focus {
    border-color: #7c3aed;
  }

  .df-apply {
    padding: 6px 16px;
    border-radius: 8px;
    background: #7c3aed;
    color: #fff;
    border: none;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
  }

  .df-apply:hover {
    background: #6d28d9;
  }

  .df-label {
    font-size: 12px;
    font-weight: 700;
    color: #7c3aed;
    white-space: nowrap;
  }

  .df-active-tag {
    background: #ede9fe;
    border: 1px solid #ddd6fe;
    border-radius: 6px;
    padding: 3px 10px;
    font-size: 11px;
    font-weight: 700;
    color: #7c3aed;
  }

  /* ── Tabs ── */
  .tab-nav {
    display: flex;
    border-bottom: 2px solid #ddd6fe;
    gap: 2px;
    flex-wrap: wrap;
  }

  .tnav {
    padding: 10px 18px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    border: none;
    background: transparent;
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
    display: flex;
    align-items: center;
    gap: 7px;
    color: #64748b;
    border-radius: 8px 8px 0 0;
    transition: all .15s;
  }

  .tnav:hover {
    background: #f5f3ff;
  }

  .tnav.t-all {
    color: #7c3aed;
    border-bottom-color: #7c3aed;
    background: #f5f3ff;
  }

  .tnav.t-open {
    color: #b45309;
    border-bottom-color: #f59e0b;
    background: #fffbeb;
  }

  .tnav.t-partial {
    color: #0369a1;
    border-bottom-color: #0284c7;
    background: #f0f9ff;
  }

  .tnav.t-full {
    color: #15803d;
    border-bottom-color: #16a34a;
    background: #f0fdf4;
  }

  .tbadge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 18px;
    border-radius: 9px;
    font-size: 10px;
    font-weight: 800;
    padding: 0 5px;
  }

  .b-a {
    background: #ede9fe;
    color: #7c3aed;
  }

  .b-o {
    background: #fef9c3;
    color: #b45309;
  }

  .b-p {
    background: #dbeafe;
    color: #0369a1;
  }

  .b-f {
    background: #dcfce7;
    color: #15803d;
  }

  /* ── Filter bar per tab ── */
  .fbar {
    padding: 12px 16px;
    border-bottom: 1px solid #e2e8f0;
  }

  .fbar-all {
    background: #f5f3ff;
  }

  .fbar-open {
    background: #fffbeb;
  }

  .fbar-partial {
    background: #f0f9ff;
  }

  .fbar-full {
    background: #f0fdf4;
  }

  .tab-card {
    border-radius: 0 0 12px 12px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-top: none;
  }

  /* ── Table header ── */
  th.tw {
    background: #5b21b6 !important;
    color: #fff !important;
    font-size: 12px;
    font-weight: 700;
    padding: 10px 12px;
    white-space: nowrap;
    border: none !important;
  }

  /* ── Row colors ── */
  .r-open td {
    background: #fffbeb !important;
  }

  .r-partial td {
    background: #f0f9ff !important;
  }

  .r-full td {
    background: #f0fdf4 !important;
  }

  .adv-row td {
    padding: 10px 12px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
  }

  .adv-row:hover td {
    filter: brightness(.97);
  }

  /* ── Badges ── */
  .bs-open {
    background: #fef9c3;
    color: #b45309;
    border: 1px solid #fde68a;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
  }

  .bs-partial {
    background: #dbeafe;
    color: #0369a1;
    border: 1px solid #bfdbfe;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
  }

  .bs-full {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
  }

  .owner-chip {
    font-size: 11px;
    background: #ede9fe;
    color: #5b21b6;
    border-radius: 20px;
    padding: 2px 9px;
  }

  .ic-btn {
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 14px;
    border: 1px solid;
  }

  /* ── Modals ── */
  .pm-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    font-size: 13px;
    border-bottom: 1px solid #f1f5f9;
  }

  .pm-row:last-child {
    border: none;
  }

  /* ── Summary modal ── */
  .sum-tbl th {
    background: #f5f3ff;
    font-size: 12px;
    font-weight: 700;
    padding: 10px 14px;
    color: #5b21b6;
  }

  .sum-tbl td {
    font-size: 12px;
    padding: 10px 14px;
    vertical-align: middle;
  }
</style>

<div class="main-content app-content">
  <div class="container-fluid" style="padding-bottom:30px;">

    <!-- HEADER -->
    <div class="adv-hdr">
      <div>
        <h4><i class="ri-hand-coin-line"></i> Owner Advance</h4>
        <p>Advance given to vehicle owners · Adjust against trip freight payments</p>
      </div>
      <div class="d-flex gap-2 align-items-center flex-wrap">
        <button class="btn btn-warning fw-bold px-4" style="border-radius:10px;height:38px;font-size:13px;"
          onclick="new bootstrap.Modal('#sumModal').show()">
          <i class="ri-bar-chart-grouped-line me-1"></i> Owner Summary
        </button>
        <select id="ownerFilter" class="form-select" style="max-width:190px;border-radius:8px;display:inline-block;"
          onchange="window.location.href=this.value?'?ownerId='+this.value:'?'">
          <option value="">All Owners</option>
          <?php foreach ($owners as $o): ?>
            <option value="<?= $o['VehicleOwnerId'] ?>" <?= $filterOwner == $o['VehicleOwnerId'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($o['OwnerName']) ?><?= $o['City'] ? ' — ' . $o['City'] : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button class="btn fw-bold text-white px-4" style="background:#7c3aed;border-radius:10px;height:38px;font-size:13px;"
          onclick="new bootstrap.Modal('#addAdvModal').show()">
          <i class="ri-add-circle-line me-1"></i> New Advance
        </button>
        <a href="OwnerAdvance_manage.php" class="btn btn-sm btn-light fw-bold"><i class="ri-refresh-line"></i></a>
      </div>
    </div>

    <!-- STATS -->
    <div class="srow">
      <div class="spill">
        <div class="sico" style="background:#ede9fe;"><i class="ri-hand-coin-line" style="color:#7c3aed;"></i></div>
        <div>
          <div class="snum" style="color:#7c3aed;">₹<?= number_format($summary['TotalAmount'] ?? 0, 0) ?></div>
          <div class="slbl">Total Advances</div>
          <div style="font-size:10px;color:#64748b;"><?= $summary['TotalEntries'] ?? 0 ?> entries</div>
        </div>
      </div>
      <div class="spill">
        <div class="sico" style="background:#fef9c3;"><i class="ri-lock-unlock-line" style="color:#b45309;"></i></div>
        <div>
          <div class="snum" style="color:#b45309;"><?= $cntO ?></div>
          <div class="slbl">Open</div>
          <div style="font-size:10px;color:#b45309;">not yet adjusted</div>
        </div>
      </div>
      <div class="spill">
        <div class="sico" style="background:#dbeafe;"><i class="ri-loader-line" style="color:#0369a1;"></i></div>
        <div>
          <div class="snum" style="color:#0369a1;"><?= $cntP ?></div>
          <div class="slbl">Partially Used</div>
        </div>
      </div>
      <div class="spill">
        <div class="sico" style="background:#dcfce7;"><i class="ri-checkbox-circle-line" style="color:#15803d;"></i></div>
        <div>
          <div class="snum" style="color:#15803d;"><?= $cntF ?></div>
          <div class="slbl">Fully Used</div>
        </div>
      </div>
      <div class="spill" style="border-left:4px solid #15803d;">
        <div class="sico" style="background:#dcfce7;"><i class="ri-checkbox-circle-line" style="color:#15803d;"></i></div>
        <div>
          <div class="snum" style="font-size:13px;color:#15803d;">₹<?= number_format($summary['TotalAdjusted'] ?? 0, 0) ?></div>
          <div class="slbl">Total Adjusted</div>
        </div>
      </div>
      <div class="spill" style="border-left:4px solid #dc2626;">
        <div class="sico" style="background:#fee2e2;"><i class="ri-error-warning-line" style="color:#dc2626;"></i></div>
        <div>
          <div class="snum" style="font-size:13px;color:#dc2626;">₹<?= number_format($summary['TotalRemaining'] ?? 0, 0) ?></div>
          <div class="slbl">Remaining Balance</div>
          <?php
          $usedPct = ($summary['TotalAmount'] ?? 0) > 0
            ? min(100, round(($summary['TotalAdjusted'] ?? 0) / $summary['TotalAmount'] * 100)) : 0;
          ?>
          <div class="pgw" style="min-width:80px;">
            <div class="pgb" style="width:<?= $usedPct ?>%;background:#15803d;"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- DATE FILTER BAR -->
    <div class="date-filter-bar">
      <span class="df-label"><i class="ri-calendar-line me-1"></i>Filter:</span>
      <div class="df-preset">
        <a href="?<?= http_build_query(array_merge($_GET, ['datePreset' => 'all', 'dateFrom' => '', 'dateTo' => ''])) ?>"
          class="df-btn <?= $datePreset === 'all' ? 'active' : '' ?>">All</a>
        <a href="?<?= http_build_query(array_merge($_GET, ['datePreset' => 'today', 'dateFrom' => '', 'dateTo' => ''])) ?>"
          class="df-btn <?= $datePreset === 'today' ? 'active' : '' ?>">Today</a>
        <a href="?<?= http_build_query(array_merge($_GET, ['datePreset' => 'yesterday', 'dateFrom' => '', 'dateTo' => ''])) ?>"
          class="df-btn <?= $datePreset === 'yesterday' ? 'active' : '' ?>">Yesterday</a>
        <a href="?<?= http_build_query(array_merge($_GET, ['datePreset' => 'thisweek', 'dateFrom' => '', 'dateTo' => ''])) ?>"
          class="df-btn <?= $datePreset === 'thisweek' ? 'active' : '' ?>">This Week</a>
      </div>
      <div class="df-sep"></div>
      <form method="GET" class="df-range">
        <?php foreach ($_GET as $k => $v): if (in_array($k, ['datePreset', 'dateFrom', 'dateTo'])) continue; ?>
          <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
        <?php endforeach; ?>
        <input type="hidden" name="datePreset" value="custom">
        <span class="df-label">From</span>
        <input type="date" name="dateFrom" value="<?= htmlspecialchars($datePreset === 'custom' ? $dateFrom : '') ?>">
        <span class="df-label">To</span>
        <input type="date" name="dateTo" value="<?= htmlspecialchars($datePreset === 'custom' ? $dateTo : '') ?>">
        <button type="submit" class="df-apply"><i class="ri-search-line"></i> Go</button>
      </form>
      <?php if ($datePreset !== 'all' && $datePreset !== ''): ?>
        <span class="df-active-tag">
          <i class="ri-filter-3-line me-1"></i>
          <?php
          if ($datePreset === 'today')     echo 'Today: ' . $today;
          elseif ($datePreset === 'yesterday') echo 'Yesterday: ' . date('d-m-Y', strtotime('-1 day'));
          elseif ($datePreset === 'thisweek')  echo 'This Week';
          elseif ($datePreset === 'custom' && $dateFrom && $dateTo)
            echo date('d-m-Y', strtotime($dateFrom)) . ' → ' . date('d-m-Y', strtotime($dateTo));
          ?>
        </span>
        <a href="OwnerAdvance_manage.php" class="df-btn" style="border-color:#dc2626;color:#dc2626;">
          <i class="ri-close-line"></i> Clear
        </a>
      <?php endif; ?>
    </div>

    <!-- TABS -->
    <div class="tab-nav">
      <button class="tnav t-all" id="nav-all" onclick="switchTab('all')">
        <i class="ri-list-check"></i> All <span class="tbadge b-a"><?= $cntAll ?></span>
      </button>
      <button class="tnav" id="nav-open" onclick="switchTab('open')">
        <i class="ri-lock-unlock-line"></i> Open <span class="tbadge b-o"><?= $cntO ?></span>
      </button>
      <button class="tnav" id="nav-partial" onclick="switchTab('partial')">
        <i class="ri-loader-line"></i> Partial <span class="tbadge b-p"><?= $cntP ?></span>
      </button>
      <button class="tnav" id="nav-full" onclick="switchTab('full')">
        <i class="ri-checkbox-circle-line"></i> Fully Used <span class="tbadge b-f"><?= $cntF ?></span>
      </button>
    </div>

    <?php
    $thead = '<thead><tr>
  <th class="tw" style="width:36px;">#</th>
  <th class="tw">Date</th>
  <th class="tw">Owner</th>
  <th class="tw">Mode</th>
  <th class="tw">Reference</th>
  <th class="tw text-end">Amount</th>
  <th class="tw text-center" style="min-width:130px;">Used / Progress</th>
  <th class="tw text-end">Remaining</th>
  <th class="tw">Status</th>
  <th class="tw">Remarks</th>
  <th class="tw text-center" style="width:100px;">Actions</th>
</tr></thead>';

    function advFbar($id, $cls)
    {
      global $owners; ?>
      <div class="fbar <?= $cls ?>">
        <div class="row g-2 align-items-center">
          <div class="col-md-3">
            <select id="fo_<?= $id ?>" class="form-select form-select-sm">
              <option value="">All Owners</option>
              <?php foreach ($owners as $o): ?>
                <option value="<?= htmlspecialchars($o['OwnerName']) ?>"><?= htmlspecialchars($o['OwnerName']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-auto">
            <button class="btn btn-outline-secondary btn-sm" onclick="clearF('<?= $id ?>')">
              <i class="ri-refresh-line me-1"></i>Clear
            </button>
          </div>
          <div class="col ms-auto" style="max-width:380px;">
            <div class="input-group input-group-sm">
              <span class="input-group-text bg-white border-end-0"><i class="ri-search-line text-muted"></i></span>
              <input type="text" id="sr_<?= $id ?>" class="form-control border-start-0" placeholder="Owner, reference, remarks...">
              <span id="fi_<?= $id ?>" class="input-group-text fw-bold text-white"
                style="background:#5b21b6;min-width:52px;justify-content:center;font-size:11px;"></span>
            </div>
          </div>
        </div>
      </div>
    <?php }

    function advRow($a, $i)
    {
      $pct     = $a['Amount'] > 0 ? min(100, round($a['AdjustedAmount'] / $a['Amount'] * 100)) : 0;
      $rowCls  = match ($a['Status']) {
        'FullyAdjusted' => 'adv-row r-full',
        'PartiallyAdjusted' => 'adv-row r-partial',
        default => 'adv-row r-open'
      };
      $stBadge = match ($a['Status']) {
        'FullyAdjusted' => '<span class="bs-full">✓ Fully Used</span>',
        'PartiallyAdjusted' => '<span class="bs-partial">Partial</span>',
        default => '<span class="bs-open">Open</span>'
      };
      $canAdj  = $a['Status'] !== 'FullyAdjusted';
    ?>
      <tr class="<?= $rowCls ?>">
        <td class="text-muted fw-medium"><?= $i ?></td>
        <td style="white-space:nowrap;font-size:12px;"><?= date('d-m-Y', strtotime($a['AdvanceDate'])) ?></td>
        <td>
          <div class="fw-semibold" style="font-size:13px;"><?= htmlspecialchars($a['OwnerName']) ?></div>
          <?php if ($a['City'] || $a['MobileNo']): ?>
            <div style="font-size:10px;color:#94a3b8;margin-top:2px;">
              <?= htmlspecialchars($a['City'] ?? '') ?>
              <?= ($a['City'] && $a['MobileNo']) ? ' · ' : '' ?>
              <?= $a['MobileNo'] ? '<i class="ri-smartphone-line"></i> ' . $a['MobileNo'] : '' ?>
            </div>
          <?php endif; ?>
        </td>
        <td>
          <span class="badge bg-light text-dark border" style="font-size:11px;">
            <?= match ($a['PaymentMode']) {
              'Cash' => '💵',
              'Cheque' => '📋',
              'NEFT' => '🏦',
              'RTGS' => '🏦',
              'UPI' => '📱',
              default => '💳'
            } ?>
            <?= $a['PaymentMode'] ?>
          </span>
        </td>
        <td><small class="text-muted"><?= htmlspecialchars($a['ReferenceNo'] ?? '—') ?></small></td>
        <td class="text-end fw-bold" style="color:#7c3aed;font-size:14px;">₹<?= number_format($a['Amount'], 0) ?></td>
        <td>
          <div class="d-flex justify-content-between" style="font-size:11px;">
            <span class="text-success fw-semibold">₹<?= number_format($a['AdjustedAmount'], 0) ?></span>
            <span class="text-muted"><?= $pct ?>%</span>
          </div>
          <div class="pgw">
            <div class="pgb" style="width:<?= $pct ?>%;background:<?= $pct >= 100 ? '#15803d' : '#7c3aed' ?>;"></div>
          </div>
        </td>
        <td class="text-end fw-bold" style="color:<?= floatval($a['RemainingAmount']) > 0 ? '#dc2626' : '#15803d' ?>;font-size:14px;">
          ₹<?= number_format($a['RemainingAmount'], 0) ?>
        </td>
        <td><?= $stBadge ?></td>
        <td><small class="text-muted"><?= htmlspecialchars($a['Remarks'] ?? '—') ?></small></td>
        <td>
          <div class="d-flex gap-1 justify-content-center">
            <?php if ($canAdj): ?>
              <button class="ic-btn btn-outline-primary text-primary" title="Adjust against Trip"
                onclick="openAdjModal(<?= $a['OwnerAdvanceId'] ?>,<?= $a['OwnerId'] ?>,<?= $a['RemainingAmount'] ?>,'<?= addslashes($a['OwnerName']) ?>','<?= date('d-m-Y', strtotime($a['AdvanceDate'])) ?>')">
                <i class="ri-links-line"></i>
              </button>
            <?php else: ?>
              <span title="Fully adjusted — no action needed"
                style="width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;color:#15803d;font-size:16px;">
                <i class="ri-lock-2-line"></i>
              </span>
            <?php endif; ?>
            <button class="ic-btn btn-outline-info text-info" title="Adjustment History"
              onclick="viewAdj(<?= $a['OwnerAdvanceId'] ?>,'<?= addslashes($a['OwnerName']) ?>')">
              <i class="ri-history-line"></i>
            </button>
          </div>
        </td>
      </tr>
    <?php }

    $tabData = [
      'all'     => ['rows' => $advances,   'cls' => 'fbar-all'],
      'open'    => ['rows' => $advOpen,    'cls' => 'fbar-open'],
      'partial' => ['rows' => $advPartial, 'cls' => 'fbar-partial'],
      'full'    => ['rows' => $advFull,    'cls' => 'fbar-full'],
    ];
    foreach ($tabData as $tabId => $td):
      $display = $tabId === 'all' ? 'block' : 'none';
    ?>
      <div id="tab-<?= $tabId ?>" style="display:<?= $display ?>;">
        <?php advFbar($tabId, $td['cls']); ?>
        <div class="tab-card">
          <div class="table-responsive">
            <table id="dt_<?= $tabId ?>" class="table table-hover align-middle mb-0 w-100">
              <?= $thead ?>
              <tbody>
                <?php $i = 1;
                foreach ($td['rows'] as $a) {
                  advRow($a, $i++);
                } ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

  </div>
</div>

<!-- ════ OWNER SUMMARY MODAL ════ -->
<div class="modal fade" id="sumModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;">
      <div class="modal-header text-white py-3" style="background:linear-gradient(135deg,#3b0764,#7c3aed);">
        <h5 class="modal-title fw-bold"><i class="ri-bar-chart-grouped-line me-2"></i>Owner-wise Advance Summary</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <table class="table table-hover mb-0 sum-tbl">
          <thead>
            <tr>
              <th>Owner</th>
              <th class="text-center">Entries</th>
              <th class="text-end">Total Given</th>
              <th class="text-end">Adjusted</th>
              <th class="text-end">Remaining</th>
              <th style="min-width:110px;">Progress</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php
            /* Group advances by owner */
            $ownerMap = [];
            foreach ($allAdvances as $a) {
              $oid = $a['OwnerId'];
              if (!isset($ownerMap[$oid])) {
                $ownerMap[$oid] = [
                  'OwnerName' => $a['OwnerName'],
                  'City' => $a['City'] ?? '',
                  'MobileNo' => $a['MobileNo'] ?? '',
                  'OwnerId' => $oid,
                  'Entries' => 0,
                  'Total' => 0,
                  'Adjusted' => 0,
                  'Remaining' => 0
                ];
              }
              $ownerMap[$oid]['Entries']++;
              $ownerMap[$oid]['Total']     += floatval($a['Amount']);
              $ownerMap[$oid]['Adjusted']  += floatval($a['AdjustedAmount']);
              $ownerMap[$oid]['Remaining'] += floatval($a['RemainingAmount']);
            }
            usort($ownerMap, fn($a, $b) => strcmp($a['OwnerName'], $b['OwnerName']));
            foreach ($ownerMap as $om):
              $pct = $om['Total'] > 0 ? min(100, round($om['Adjusted'] / $om['Total'] * 100)) : 0;
            ?>
              <tr>
                <td class="fw-semibold">
                  <?= htmlspecialchars($om['OwnerName']) ?>
                  <?= $om['City'] ? '<br><small class="text-muted">' . htmlspecialchars($om['City']) . '</small>' : '' ?>
                  <?= $om['MobileNo'] ? '<br><small class="text-muted"><i class="ri-smartphone-line"></i> ' . $om['MobileNo'] . '</small>' : '' ?>
                </td>
                <td class="text-center"><span class="badge bg-secondary"><?= $om['Entries'] ?></span></td>
                <td class="text-end fw-bold" style="color:#7c3aed;">₹<?= number_format($om['Total'], 0) ?></td>
                <td class="text-end fw-semibold text-success">₹<?= number_format($om['Adjusted'], 0) ?></td>
                <td class="text-end fw-bold <?= $om['Remaining'] > 0 ? 'text-danger' : 'text-success' ?>">
                  ₹<?= number_format($om['Remaining'], 0) ?>
                </td>
                <td>
                  <div style="font-size:10px;color:#64748b;margin-bottom:3px;"><?= $pct ?>%</div>
                  <div class="pgw">
                    <div class="pgb" style="width:<?= $pct ?>%;background:<?= $pct >= 100 ? '#15803d' : '#7c3aed' ?>;"></div>
                  </div>
                </td>
                <td>
                  <a href="?ownerId=<?= $om['OwnerId'] ?>"
                    onclick="bootstrap.Modal.getInstance(document.getElementById('sumModal')).hide();"
                    class="btn btn-sm py-0 px-2 fs-11" style="border:1px solid #7c3aed;color:#7c3aed;">
                    <i class="ri-filter-line"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="modal-footer py-2">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ════ ADD ADVANCE MODAL ════ -->
<div class="modal fade" id="addAdvModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;">
      <div class="modal-header text-white py-3" style="background:linear-gradient(135deg,#3b0764,#7c3aed);">
        <h5 class="modal-title fw-bold"><i class="ri-hand-coin-line me-2"></i>New Owner Advance Entry</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Vehicle Owner <span class="text-danger">*</span></label>
            <select id="adv_Owner" class="form-select">
              <option value="">-- Select Owner --</option>
              <?php foreach ($owners as $o): ?>
                <option value="<?= $o['VehicleOwnerId'] ?>"><?= htmlspecialchars($o['OwnerName']) ?><?= $o['City'] ? ' — ' . $o['City'] : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
            <input type="date" id="adv_Date" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Amount (₹) <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text fw-bold bg-light">₹</span>
              <input type="number" id="adv_Amount" class="form-control fw-bold" step="0.01" min="0.01" placeholder="0.00">
            </div>
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Payment Mode</label>
            <select id="adv_Mode" class="form-select">
              <option value="Cash">💵 Cash</option>
              <option value="Cheque">📋 Cheque</option>
              <option value="NEFT">🏦 NEFT</option>
              <option value="RTGS">🏦 RTGS</option>
              <option value="UPI">📱 UPI</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Reference No.</label>
            <input type="text" id="adv_Ref" class="form-control" placeholder="Cheque / UTR">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Remarks</label>
            <input type="text" id="adv_Remarks" class="form-control" placeholder="Optional...">
          </div>
        </div>
      </div>
      <div class="modal-footer py-2 gap-2">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn fw-bold text-white px-4" style="background:#7c3aed;" onclick="saveAdvance()">
          <i class="ri-save-3-line me-1"></i>Save Advance
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ════ ADJUST MODAL ════ -->
<div class="modal fade" id="adjModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;">
      <div class="modal-header text-white py-3" style="background:linear-gradient(135deg,#1e40af,#0284c7);">
        <h5 class="modal-title fw-bold"><i class="ri-links-line me-2"></i>Adjust Advance → Trip Payment</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- Info strip -->
        <div class="rounded p-3 mb-3 d-flex gap-0" style="background:#f0f9ff;border:1px solid #bfdbfe;">
          <div class="flex-fill text-center border-end pe-3">
            <div style="font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;">Owner</div>
            <div class="fw-bold fs-13 mt-1" id="adj_ownerName">—</div>
          </div>
          <div class="flex-fill text-center border-end px-3">
            <div style="font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;">Advance Date</div>
            <div class="fw-bold fs-13 mt-1" id="adj_advDate">—</div>
          </div>
          <div class="flex-fill text-center ps-3">
            <div style="font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;">Available</div>
            <div class="fw-bold mt-1" style="font-size:20px;color:#15803d;" id="adj_avail">₹0</div>
          </div>
        </div>

        <input type="hidden" id="adj_AdvId">
        <input type="hidden" id="adj_OwnerId">

        <div class="mb-3">
          <label class="form-label fw-semibold">Select Trip <span class="text-danger">*</span></label>
          <select id="adj_Trip" class="form-select" onchange="tripSelected()">
            <option value="">-- Loading... --</option>
          </select>
        </div>

        <div id="adj_tripInfo" class="rounded p-3 mb-3" style="background:#f0fdf4;border:1px solid #bbf7d0;display:none;">
          <div class="d-flex justify-content-between" style="font-size:12px;">
            <span class="text-muted">Net Payable</span><span class="fw-bold" id="adj_tripNet">₹0</span>
          </div>
          <div class="d-flex justify-content-between mt-1" style="font-size:12px;">
            <span class="text-muted">Already Paid</span><span class="fw-bold text-success" id="adj_tripPaid">₹0</span>
          </div>
          <div class="d-flex justify-content-between mt-1" style="font-size:12px;border-top:1px solid #bbf7d0;padding-top:6px;margin-top:6px;">
            <span class="fw-bold">Balance Due</span><span class="fw-bold text-danger" style="font-size:15px;" id="adj_tripDue">₹0</span>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-6">
            <label class="form-label fw-semibold">Adjustment Date</label>
            <input type="date" id="adj_Date" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Amount (₹) <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text fw-bold bg-light">₹</span>
              <input type="number" id="adj_Amount" class="form-control fw-bold" step="0.01" min="0.01">
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
        <button class="btn fw-bold text-white px-4" style="background:#0284c7;" onclick="saveAdj()">
          <i class="ri-save-3-line me-1"></i>Save Adjustment
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ════ ADJUSTMENT HISTORY MODAL ════ -->
<div class="modal fade" id="adjHistModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;">
      <div class="modal-header text-white py-3" style="background:linear-gradient(135deg,#0369a1,#0284c7);">
        <h5 class="modal-title fw-bold"><i class="ri-history-line me-2"></i>Adjustments — <span id="adjHist_label"></span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <table class="table table-bordered table-sm mb-0 fs-13">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Date</th>
              <th>Trip</th>
              <th>Route</th>
              <th class="text-end">Adjusted</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody id="adjHistBody"></tbody>
          <tfoot>
            <tr class="table-success fw-bold">
              <td colspan="4" class="text-end">Total Adjusted:</td>
              <td id="adjHistTotal">₹0</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div class="modal-footer py-2">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
  var dts = {};
  $(document).ready(function() {
    var cfg = {
      scrollX: true,
      pageLength: 25,
      dom: 'rtip',
      columnDefs: [{
        orderable: false,
        targets: [0, 10]
      }],
      language: {
        paginate: {
          previous: '‹',
          next: '›'
        }
      }
    };
    ['all', 'open', 'partial', 'full'].forEach(function(id) {
      dts[id] = $('#dt_' + id).DataTable({
        ...cfg,
        drawCallback: function() {
          var info = this.api().page.info();
          $('#fi_' + id).text(info.recordsDisplay + '/' + info.recordsTotal);
        }
      });
      $('#sr_' + id).on('keyup input', function() {
        dts[id].search($(this).val()).draw();
      });
      $('#fo_' + id).on('change', function() {
        dts[id].column(2).search(this.value || '').draw();
      });
    });
  });

  function switchTab(name) {
    var tabs = ['all', 'open', 'partial', 'full'];
    var cls = {
      all: 't-all',
      open: 't-open',
      partial: 't-partial',
      full: 't-full'
    };
    tabs.forEach(function(t) {
      document.getElementById('nav-' + t).className = 'tnav';
      document.getElementById('tab-' + t).style.display = 'none';
    });
    document.getElementById('nav-' + name).classList.add(cls[name]);
    document.getElementById('tab-' + name).style.display = 'block';
    if (dts[name]) dts[name].columns.adjust();
  }

  function clearF(id) {
    $('#fo_' + id).val('').trigger('change');
    $('#sr_' + id).val('');
    if (dts[id]) dts[id].search('').draw();
  }

  function saveAdvance() {
    var ownerId = $('#adv_Owner').val(),
      amt = parseFloat($('#adv_Amount').val());
    if (!ownerId) {
      Swal.fire({
        icon: 'warning',
        title: 'Select an owner!',
        toast: true,
        position: 'top-end',
        timer: 2000,
        showConfirmButton: false
      });
      return;
    }
    if (!amt || amt <= 0) {
      Swal.fire({
        icon: 'warning',
        title: 'Enter valid amount!',
        toast: true,
        position: 'top-end',
        timer: 2000,
        showConfirmButton: false
      });
      return;
    }
    Swal.fire({
      title: 'Saving...',
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading()
    });
    var fd = new FormData();
    fd.append('addAdvance', 1);
    fd.append('OwnerId', ownerId);
    fd.append('AdvanceDate', $('#adv_Date').val());
    fd.append('Amount', amt);
    fd.append('PaymentMode', $('#adv_Mode').val());
    fd.append('ReferenceNo', $('#adv_Ref').val());
    fd.append('Remarks', $('#adv_Remarks').val());
    fetch('OwnerAdvance_manage.php', {
      method: 'POST',
      body: fd
    }).then(r => r.json()).then(res => {
      Swal.close();
      if (res.status === 'success') {
        bootstrap.Modal.getInstance(document.getElementById('addAdvModal')).hide();
        Swal.fire({
          icon: 'success',
          title: 'Advance Saved!',
          toast: true,
          position: 'top-end',
          showConfirmButton: false,
          timer: 2500
        });
        setTimeout(() => location.reload(), 1800);
      } else Swal.fire({
        icon: 'error',
        title: 'Error',
        text: res.msg
      });
    }).catch(() => Swal.fire({
      icon: 'error',
      title: 'Server Error'
    }));
  }

  function openAdjModal(advId, ownerId, remaining, ownerName, advDate) {
    window._adjRemaining = remaining;
    $('#adj_AdvId').val(advId);
    $('#adj_OwnerId').val(ownerId);
    $('#adj_ownerName').text(ownerName);
    $('#adj_advDate').text(advDate);
    $('#adj_avail').text('₹' + parseFloat(remaining).toFixed(2));
    $('#adj_Amount').val(parseFloat(remaining).toFixed(2));
    $('#adj_tripInfo').hide();
    $('#adj_Remarks').val('');
    $('#adj_Trip').html('<option value="">Loading trips...</option>');
    fetch('OwnerAdvance_manage.php?getOwnerTrips=1&OwnerId=' + ownerId)
      .then(r => r.json()).then(trips => {
        window._adjTrips = trips;
        var opts = '<option value="">-- Select Trip --</option>';
        if (!trips.length) opts += '<option disabled>No unpaid trips for this owner</option>';
        trips.forEach(function(t) {
          opts += '<option value="' + t.TripId + '" data-net="' + t.NetPayable + '" data-paid="' + t.Paid + '" data-rem="' + t.Remaining + '">' +
            'Trip #' + t.TripId + ' — ' + t.VehicleNumber + ' — ' + t.FromLocation + ' → ' + t.ToLocation +
            ' (Due: ₹' + parseFloat(t.Remaining).toFixed(0) + ')</option>';
        });
        $('#adj_Trip').html(opts);
      });
    new bootstrap.Modal('#adjModal').show();
  }

  function tripSelected() {
    var opt = $('#adj_Trip option:selected');
    if ($('#adj_Trip').val()) {
      var rem = parseFloat(opt.data('rem') || 0);
      $('#adj_tripNet').text('₹' + parseFloat(opt.data('net') || 0).toFixed(2));
      $('#adj_tripPaid').text('₹' + parseFloat(opt.data('paid') || 0).toFixed(2));
      $('#adj_tripDue').text('₹' + rem.toFixed(2));
      $('#adj_tripInfo').show();
      $('#adj_Amount').val(Math.min(window._adjRemaining, rem).toFixed(2));
    } else {
      $('#adj_tripInfo').hide();
    }
  }

  function saveAdj() {
    var tripId = $('#adj_Trip').val(),
      amt = parseFloat($('#adj_Amount').val());
    if (!tripId) {
      Swal.fire({
        icon: 'warning',
        title: 'Select a trip!',
        toast: true,
        position: 'top-end',
        timer: 2000,
        showConfirmButton: false
      });
      return;
    }
    if (!amt || amt <= 0) {
      Swal.fire({
        icon: 'warning',
        title: 'Enter valid amount!',
        toast: true,
        position: 'top-end',
        timer: 2000,
        showConfirmButton: false
      });
      return;
    }
    if (amt > window._adjRemaining) {
      Swal.fire({
        icon: 'warning',
        title: 'Exceeds available balance!',
        text: 'Max: ₹' + window._adjRemaining.toFixed(2)
      });
      return;
    }
    Swal.fire({
      title: 'Adjusting...',
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading()
    });
    var fd = new FormData();
    fd.append('adjustAdvance', 1);
    fd.append('OwnerAdvanceId', $('#adj_AdvId').val());
    fd.append('TripId', tripId);
    fd.append('OwnerId', $('#adj_OwnerId').val());
    fd.append('AdjustedAmount', amt);
    fd.append('AdjustmentDate', $('#adj_Date').val());
    fd.append('Remarks', $('#adj_Remarks').val());
    fetch('OwnerAdvance_manage.php', {
      method: 'POST',
      body: fd
    }).then(r => r.json()).then(res => {
      Swal.close();
      if (res.status === 'success') {
        bootstrap.Modal.getInstance(document.getElementById('adjModal')).hide();
        Swal.fire({
          icon: 'success',
          title: 'Adjusted! Remaining: ₹' + parseFloat(res.newRemaining).toFixed(2),
          timer: 3000,
          showConfirmButton: false
        });
        setTimeout(() => location.reload(), 2200);
      } else Swal.fire({
        icon: 'error',
        title: 'Error',
        text: res.msg
      });
    }).catch(() => Swal.fire({
      icon: 'error',
      title: 'Server Error'
    }));
  }

  function viewAdj(advId, ownerName) {
    $('#adjHist_label').text(ownerName);
    $('#adjHistBody').html('<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>');
    new bootstrap.Modal('#adjHistModal').show();
    fetch('OwnerAdvance_manage.php?getAdjustments=1&AdvanceId=' + advId)
      .then(r => r.json()).then(rows => {
        var html = '',
          total = 0;
        if (!rows.length) html = '<tr><td colspan="6" class="text-center text-muted py-3">No adjustments yet</td></tr>';
        rows.forEach(function(r, i) {
          total += parseFloat(r.AdjustedAmount || 0);
          html += '<tr><td>' + (i + 1) + '</td><td style="white-space:nowrap;">' + r.AdjustmentDate + '</td>' +
            '<td><b>Trip #' + r.TripId + '</b></td>' +
            '<td style="font-size:11px;">' + (r.VehicleNumber || '') + ' ' + (r.FromLocation || '') + ' → ' + (r.ToLocation || '') + '</td>' +
            '<td class="text-end fw-bold text-success">₹' + parseFloat(r.AdjustedAmount).toFixed(2) + '</td>' +
            '<td><small class="text-muted">' + (r.Remarks || '—') + '</small></td></tr>';
        });
        $('#adjHistBody').html(html);
        $('#adjHistTotal').text('₹' + total.toFixed(2));
      });
  }
  window.addEventListener('offline', function() {
    Swal.fire({
      icon: 'warning',
      title: 'Internet Disconnected!',
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000
    });
  });
  window.addEventListener('online', function() {
    Swal.fire({
      icon: 'success',
      title: 'Back Online!',
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 2000
    });
  });
</script>
<?php require_once "../layout/footer.php"; ?>