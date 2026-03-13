<?php
/* ================================================================
   OwnerPayment_manage.php  —  Vehicle Owner Trip-wise Payment
   Net Payable = FreightAmt + Charges + TDS − Commission
   Commission auto-marked Received when trip fully paid
================================================================ */
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

require_once "../../businessLogics/Admin.php";
require_once "../../businessLogics/OwnerPayment.php";
Admin::checkAuth();

if (isset($_GET['getTripPayments'])) {
  header('Content-Type: application/json');
  echo json_encode(OwnerPayment::getByTrip(intval($_GET['TripId'])));
  exit();
}
if (isset($_POST['addPayment'])) {
  header('Content-Type: application/json');
  echo json_encode(OwnerPayment::addPayment(intval($_POST['TripId']), intval($_POST['OwnerId']), $_POST));
  exit();
}
if (isset($_POST['deletePayment'])) {
  header('Content-Type: application/json');
  echo json_encode(OwnerPayment::deletePayment(intval($_POST['PaymentId'])));
  exit();
}

/* ── Date Filter Logic ── */
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
    break; // dateFrom / dateTo from GET
  default:
    $dateFrom = $dateTo = '';
}

$dateFilter  = array_filter(['from' => $dateFrom, 'to' => $dateTo]);
$filterOwner = !empty($_GET['ownerId']) ? intval($_GET['ownerId']) : null;

$trips        = OwnerPayment::getAllTripsWithPaymentStatus($filterOwner, $dateFilter);
$ownerSummary = OwnerPayment::getOwnerSummary();
$owners       = OwnerPayment::getOwners();

/* Split trips */
$tripsNormal  = array_values(array_filter($trips, fn($t) => !$t['IsPaidDirectly']));
$tripsDirect  = array_values(array_filter($trips, fn($t) =>  $t['IsPaidDirectly']));
$tripsUnpaid  = array_values(array_filter($tripsNormal, fn($t) => $t['OwnerPaymentStatus'] === 'Unpaid'));
$tripsPartial = array_values(array_filter($tripsNormal, fn($t) => $t['OwnerPaymentStatus'] === 'PartiallyPaid'));
$tripsPaid    = array_values(array_filter($tripsNormal, fn($t) => $t['OwnerPaymentStatus'] === 'Paid'));

$cntAll = count($tripsNormal);
$cntU   = count($tripsUnpaid);
$cntP   = count($tripsPartial);
$cntD   = count($tripsPaid);
$cntDir = count($tripsDirect);

$totalCommission = array_sum(array_column($tripsNormal, 'Commission'));
$totalPayable    = array_sum(array_column($tripsNormal, 'NetPayable'));
$totalPaid       = array_sum(array_column($tripsNormal, 'TotalPaid'));
$totalRemaining  = array_sum(array_column($tripsNormal, 'Remaining'));
$commRecvCount   = count(array_filter($tripsNormal, fn($t) => ($t['CommissionStatus'] ?? '') === 'Received'));

require_once "../layout/header.php";
require_once "../layout/sidebar.php";
?>
<style>
  .ow-hdr {
    background: linear-gradient(135deg, #1a237e, #1565c0 60%, #1976d2);
    border-radius: 14px;
    padding: 20px 26px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
  }

  .ow-hdr h4 {
    color: #fff;
    font-weight: 800;
    font-size: 19px;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 9px;
  }

  .ow-hdr p {
    color: rgba(255, 255, 255, .65);
    font-size: 12px;
    margin: 3px 0 0;
  }

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

  /* Date Filter */
  .date-filter-bar {
    background: #fff;
    border: 1px solid #bfdbfe;
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
    border-color: #1d4ed8;
    color: #1d4ed8;
    background: #eff6ff;
  }

  .df-btn.active {
    border-color: #1d4ed8;
    background: #1d4ed8;
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
    border-color: #1d4ed8;
  }

  .df-apply {
    padding: 6px 16px;
    border-radius: 8px;
    background: #1d4ed8;
    color: #fff;
    border: none;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
  }

  .df-apply:hover {
    background: #1e40af;
  }

  .df-label {
    font-size: 12px;
    font-weight: 700;
    color: #1d4ed8;
    white-space: nowrap;
  }

  .df-active-tag {
    background: #dbeafe;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    padding: 3px 10px;
    font-size: 11px;
    font-weight: 700;
    color: #1d4ed8;
  }

  /* Tabs */
  .tab-nav {
    display: flex;
    border-bottom: 2px solid #bfdbfe;
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
    background: #f0f4ff;
  }

  .tnav.t-all {
    color: #1d4ed8;
    border-bottom-color: #1d4ed8;
    background: #eff6ff;
  }

  .tnav.t-unpaid {
    color: #dc2626;
    border-bottom-color: #dc2626;
    background: #fef2f2;
  }

  .tnav.t-partial {
    color: #b45309;
    border-bottom-color: #f59e0b;
    background: #fffbeb;
  }

  .tnav.t-paid {
    color: #15803d;
    border-bottom-color: #16a34a;
    background: #f0fdf4;
  }

  .tnav.t-direct {
    color: #7c3aed;
    border-bottom-color: #7c3aed;
    background: #f5f3ff;
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
    background: #dbeafe;
    color: #1d4ed8;
  }

  .b-u {
    background: #fee2e2;
    color: #dc2626;
  }

  .b-p {
    background: #fef9c3;
    color: #b45309;
  }

  .b-d {
    background: #dcfce7;
    color: #15803d;
  }

  .b-r {
    background: #ede9fe;
    color: #7c3aed;
  }

  /* Filter bar per tab */
  .fbar {
    padding: 12px 16px;
    border-bottom: 1px solid #e2e8f0;
  }

  .fbar-all {
    background: #eff6ff;
  }

  .fbar-unpaid {
    background: #fff5f5;
  }

  .fbar-partial {
    background: #fffbeb;
  }

  .fbar-paid {
    background: #f0fdf4;
  }

  .fbar-direct {
    background: #f5f3ff;
  }

  .tab-card {
    border-radius: 0 0 12px 12px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-top: none;
  }

  /* formula */
  .ftag {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 20px;
    white-space: nowrap;
  }

  .ft-g {
    background: #f1f5f9;
    color: #475569;
  }

  .ft-add {
    background: #dcfce7;
    color: #15803d;
  }

  .ft-sub {
    background: #fee2e2;
    color: #dc2626;
  }

  .ft-net {
    background: #dbeafe;
    color: #1d4ed8;
  }

  /* table */
  th.tw {
    background: #1a237e !important;
    color: #fff !important;
    font-size: 12px;
    font-weight: 700;
    padding: 10px 12px;
    white-space: nowrap;
    border: none !important;
  }

  th.tw-purple {
    background: #5b21b6 !important;
    color: #fff !important;
    font-size: 12px;
    font-weight: 700;
    padding: 10px 12px;
    white-space: nowrap;
    border: none !important;
  }

  .tr-paid td {
    background: #f0fdf4 !important;
  }

  .tr-partial td {
    background: #fffbeb !important;
  }

  .tr-unpaid td {
    background: #fff5f5 !important;
  }

  .tr-direct td {
    background: #f5f3ff !important;
  }

  .par td {
    padding: 10px 12px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
  }

  .par:hover td {
    filter: brightness(.97);
  }

  .calc {
    font-size: 11px;
    line-height: 1.9;
    min-width: 170px;
  }

  .calc-r {
    display: flex;
    justify-content: space-between;
    gap: 12px;
  }

  .calc-r.sub span:last-child {
    color: #dc2626;
  }

  .calc-r.add span:last-child {
    color: #15803d;
  }

  .calc-r.ttl {
    border-top: 1px solid #ddd;
    margin-top: 3px;
    padding-top: 3px;
    font-weight: 800;
  }

  .calc-r.ttl span:last-child {
    color: #1d4ed8;
  }

  .bs-paid {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
  }

  .bs-part {
    background: #fef9c3;
    color: #b45309;
    border: 1px solid #fde68a;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
  }

  .bs-unpaid {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
  }

  .bs-direct {
    background: linear-gradient(90deg, #ede9fe, #ddd6fe);
    color: #5b21b6;
    border: 1px solid #c4b5fd;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 4px;
  }

  .cm-recv {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #6ee7b7;
    padding: 2px 7px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 3px;
  }

  .cm-pend {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fcd34d;
    padding: 2px 7px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
  }

  .direct-note {
    font-size: 10px;
    color: #7c3aed;
    background: #ede9fe;
    border-radius: 6px;
    padding: 2px 8px;
    display: inline-block;
    margin-top: 3px;
  }

  .owner-chip {
    font-size: 11px;
    background: #ede9fe;
    color: #5b21b6;
    border-radius: 20px;
    padding: 2px 9px;
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

  /* pay modal */
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

  .pm-row.net {
    font-weight: 800;
    font-size: 15px;
    border-top: 2px solid #e2e8f0;
    margin-top: 4px;
    padding-top: 8px;
  }

  .comm-badge {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fcd34d;
    font-size: 10px;
    font-weight: 700;
    padding: 1px 7px;
    border-radius: 20px;
  }

  .comm-alert {
    background: #d1fae5;
    border: 1px solid #6ee7b7;
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 13px;
    color: #065f46;
    display: none;
    margin-bottom: 12px;
  }

  /* Summary Modal */
  .sum-tbl th {
    background: #eff6ff;
    font-size: 12px;
    font-weight: 700;
    padding: 10px 14px;
    color: #1a237e;
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
    <div class="ow-hdr">
      <div>
        <h4><i class="ri-truck-line"></i> Owner Freight Payment</h4>
        <p>Trip-wise payment · Commission auto-marked Received when trip is fully paid</p>
      </div>
      <div class="d-flex gap-2 align-items-center flex-wrap">
        <button class="btn btn-warning fw-bold px-4" style="border-radius:10px;height:38px;font-size:13px;"
          onclick="new bootstrap.Modal('#sumModal').show()">
          <i class="ri-bar-chart-grouped-line me-1"></i> Owner Summary
        </button>
        <select id="ownerFilter" class="form-select " style="max-width:190px;border-radius:8px;display:inline-block;"
          onchange="window.location.href=this.value?'?ownerId='+this.value:'?'">
          <option value="">All Owners</option>
          <?php foreach ($owners as $o): ?>
            <option value="<?= $o['VehicleOwnerId'] ?>" <?= $filterOwner == $o['VehicleOwnerId'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($o['OwnerName']) ?><?= $o['City'] ? ' — ' . $o['City'] : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <a href="OwnerPayment_manage.php" class="btn btn-sm btn-light fw-bold"><i class="ri-refresh-line"></i></a>
      </div>
    </div>

    <!-- FORMULA NOTE -->
    

    <!-- STATS -->
    <div class="srow">
      <div class="spill">
        <div class="sico" style="background:#dbeafe;"><i class="ri-road-map-line" style="color:#1d4ed8;"></i></div>
        <div>
          <div class="snum" style="color:#1d4ed8;"><?= $cntAll ?></div>
          <div class="slbl">Total Trips</div>
        </div>
      </div>
      <div class="spill">
        <div class="sico" style="background:#fee2e2;"><i class="ri-time-line" style="color:#dc2626;"></i></div>
        <div>
          <div class="snum" style="color:#dc2626;"><?= $cntU ?></div>
          <div class="slbl">Unpaid</div>
        </div>
      </div>
      <div class="spill">
        <div class="sico" style="background:#fef9c3;"><i class="ri-loader-line" style="color:#b45309;"></i></div>
        <div>
          <div class="snum" style="color:#b45309;"><?= $cntP ?></div>
          <div class="slbl">Partial</div>
        </div>
      </div>
      <div class="spill">
        <div class="sico" style="background:#dcfce7;"><i class="ri-checkbox-circle-line" style="color:#15803d;"></i></div>
        <div>
          <div class="snum" style="color:#15803d;"><?= $cntD ?></div>
          <div class="slbl">Paid</div>
        </div>
      </div>
      <div class="spill">
        <div class="sico" style="background:#ede9fe;"><i class="ri-truck-line" style="color:#7c3aed;"></i></div>
        <div>
          <div class="snum" style="color:#7c3aed;"><?= $cntDir ?></div>
          <div class="slbl">Paid Directly</div>
        </div>
      </div>
      <div class="spill">
        <div class="sico" style="background:#fef3c7;"><i class="ri-scissors-cut-line" style="color:#d97706;"></i></div>
        <div>
          <div class="snum" style="font-size:13px;color:#d97706;">₹<?= number_format($totalCommission, 0) ?></div>
          <div class="slbl">Commission</div>
          <div style="font-size:10px;color:#15803d;"><?= $commRecvCount ?> received</div>
        </div>
      </div>
      <div class="spill" style="border-left:4px solid #1d4ed8;">
        <div class="sico" style="background:#dbeafe;"><i class="ri-wallet-3-line" style="color:#1d4ed8;"></i></div>
        <div>
          <div class="snum" style="font-size:13px;color:#1d4ed8;">₹<?= number_format($totalPayable, 0) ?></div>
          <div class="slbl">Net Payable</div>
        </div>
      </div>
      <div class="spill" style="border-left:4px solid #15803d;">
        <div class="sico" style="background:#dcfce7;"><i class="ri-checkbox-circle-line" style="color:#15803d;"></i></div>
        <div>
          <div class="snum" style="font-size:13px;color:#15803d;">₹<?= number_format($totalPaid, 0) ?></div>
          <div class="slbl">Total Paid</div>
          <?php $gpct = $totalPayable > 0 ? min(100, round($totalPaid / $totalPayable * 100)) : 0; ?>
          <div class="pgw" style="min-width:80px;">
            <div class="pgb" style="width:<?= $gpct ?>%;background:#15803d;"></div>
          </div>
        </div>
      </div>
      <div class="spill" style="border-left:4px solid #dc2626;">
        <div class="sico" style="background:#fee2e2;"><i class="ri-error-warning-line" style="color:#dc2626;"></i></div>
        <div>
          <div class="snum" style="font-size:13px;color:#dc2626;">₹<?= number_format($totalRemaining, 0) ?></div>
          <div class="slbl">Still Due</div>
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
          elseif ($datePreset === 'custom')    echo date('d-m-Y', strtotime($dateFrom)) . ' → ' . date('d-m-Y', strtotime($dateTo));
          ?>
        </span>
        <a href="OwnerPayment_manage.php" class="df-btn" style="border-color:#dc2626;color:#dc2626;">
          <i class="ri-close-line"></i> Clear
        </a>
      <?php endif; ?>
    </div>

    <!-- TABS -->
    <div class="tab-nav">
      <button class="tnav t-all" id="nav-all" onclick="switchTab('all')">
        <i class="ri-list-check"></i> All <span class="tbadge b-a"><?= $cntAll ?></span>
      </button>
      <button class="tnav" id="nav-unpaid" onclick="switchTab('unpaid')">
        <i class="ri-time-line"></i> Unpaid <span class="tbadge b-u"><?= $cntU ?></span>
      </button>
      <button class="tnav" id="nav-partial" onclick="switchTab('partial')">
        <i class="ri-loader-line"></i> Partial <span class="tbadge b-p"><?= $cntP ?></span>
      </button>
      <button class="tnav" id="nav-paid" onclick="switchTab('paid')">
        <i class="ri-checkbox-circle-line"></i> Paid <span class="tbadge b-d"><?= $cntD ?></span>
      </button>
      <button class="tnav" id="nav-direct" onclick="switchTab('direct')">
        <i class="ri-truck-line"></i> Paid Directly <span class="tbadge b-r"><?= $cntDir ?></span>
      </button>
    </div>

    <?php
    /* TABLE HEADER — normal tabs */
    $thead = '<thead><tr>
  <th class="tw" style="width:36px;">#</th>
  <th class="tw">Date</th>
  <th class="tw">Vehicle / Owner</th>
  <th class="tw">Route</th>
  <th class="tw">Type</th>
  <th class="tw text-center">Calculation</th>
  <th class="tw">Paid / Progress</th>
  <th class="tw text-end">Remaining</th>
  <th class="tw">Status</th>
  <th class="tw">Commission</th>
  <th class="tw text-center" style="width:90px;">Actions</th>
</tr></thead>';

    /* TABLE HEADER — Paid Directly tab */
    $theadDirect = '<thead><tr>
  <th class="tw-purple" style="width:36px;">#</th>
  <th class="tw-purple">Date</th>
  <th class="tw-purple">Vehicle / Owner</th>
  <th class="tw-purple">Route</th>
  <th class="tw-purple">Type</th>
  <th class="tw-purple text-center">Calculation</th>
  <th class="tw-purple text-center">Status</th>
  <th class="tw-purple">Commission</th>
  <th class="tw-purple text-center">History</th>
</tr></thead>';

    /* FILTER BAR */
    function owFbar($id, $cls)
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
            <button class="btn btn-outline-secondary btn-sm" onclick="clearOwF('<?= $id ?>')">
              <i class="ri-refresh-line me-1"></i>Clear
            </button>
          </div>
          <div class="col ms-auto" style="max-width:380px;">
            <div class="input-group input-group-sm">
              <span class="input-group-text bg-white border-end-0"><i class="ri-search-line text-muted"></i></span>
              <input type="text" id="sr_<?= $id ?>" class="form-control border-start-0" placeholder="Vehicle, owner, route...">
              <span id="fi_<?= $id ?>" class="input-group-text fw-bold text-white"
                style="background:#1a237e;min-width:52px;justify-content:center;font-size:11px;"></span>
            </div>
          </div>
        </div>
      </div>
    <?php }

    /* NORMAL TRIP ROW */
    function owRow($t, $i)
    {
      $rowCls  = match ($t['OwnerPaymentStatus']) {
        'Paid' => 'par tr-paid',
        'PartiallyPaid' => 'par tr-partial',
        default => 'par tr-unpaid'
      };
      $stBadge = match ($t['OwnerPaymentStatus']) {
        'Paid' => '<span class="bs-paid">✓ Paid</span>',
        'PartiallyPaid' => '<span class="bs-part">Partial</span>',
        default => '<span class="bs-unpaid">Unpaid</span>'
      };
      $pct     = $t['NetPayable'] > 0 ? min(100, round($t['TotalPaid'] / $t['NetPayable'] * 100)) : 0;
      $party   = $t['TripType'] === 'Agent' ? ($t['AgentName'] ?? '—') : ($t['ConsignerName'] ?? '—');
      $cmRecv  = ($t['CommissionStatus'] ?? '') === 'Received';
    ?>
      <tr class="<?= $rowCls ?>">
        <td class="text-muted fw-medium"><?= $i ?></td>
        <td style="white-space:nowrap;font-size:12px;"><?= date('d-m-Y', strtotime($t['TripDate'])) ?></td>
        <td>
          <div class="fw-bold" style="font-size:12px;"><i class="ri-truck-line text-muted me-1"></i><?= htmlspecialchars($t['VehicleNumber'] ?? '—') ?></div>
          <span class="owner-chip"><?= htmlspecialchars($t['OwnerName'] ?? '—') ?></span>
          <?php if (!empty($t['MobileNo'])): ?><div style="font-size:10px;color:#94a3b8;margin-top:2px;"><i class="ri-smartphone-line"></i> <?= $t['MobileNo'] ?></div><?php endif; ?>
        </td>
        <td style="font-size:11px;max-width:130px;">
          <div><?= htmlspecialchars($t['FromLocation']) ?> → <?= htmlspecialchars($t['ToLocation']) ?></div>
          <div class="text-muted"><?= htmlspecialchars($party) ?></div>
        </td>
        <td><span class="badge <?= $t['TripType'] === 'Agent' ? 'bg-warning text-dark' : 'bg-primary' ?>"><?= $t['TripType'] ?></span></td>
        <td>
          <div class="calc">
            <div class="calc-r"><span class="text-muted">Freight</span><span class="fw-semibold">₹<?= number_format($t['FreightAmount'], 0) ?></span></div>
            <?php if ($t['LabourCharge'] > 0):  ?><div class="calc-r add"><span class="text-muted">+ Labour</span> <span>₹<?= number_format($t['LabourCharge'], 0)  ?></span></div><?php endif; ?>
            <?php if ($t['HoldingCharge'] > 0): ?><div class="calc-r add"><span class="text-muted">+ Holding</span><span>₹<?= number_format($t['HoldingCharge'], 0) ?></span></div><?php endif; ?>
            <?php if ($t['OtherCharge'] > 0):  ?><div class="calc-r add"><span class="text-muted">+ Other</span> <span>₹<?= number_format($t['OtherCharge'], 0)   ?></span></div><?php endif; ?>
            <?php if ($t['TDS'] > 0):          ?><div class="calc-r add"><span class="text-muted">+ TDS</span> <span>₹<?= number_format($t['TDS'], 0)           ?></span></div><?php endif; ?>
            <?php if ($t['Commission'] > 0):   ?><div class="calc-r sub"><span class="text-muted">− Comm.</span> <span>₹<?= number_format($t['Commission'], 0)     ?></span></div><?php endif; ?>
            <div class="calc-r ttl"><span>Net Payable</span><span>₹<?= number_format($t['NetPayable'], 0) ?></span></div>
          </div>
        </td>
        <td style="min-width:110px;">
          <div class="d-flex justify-content-between" style="font-size:11px;">
            <span class="text-success fw-semibold">₹<?= number_format($t['TotalPaid'], 0) ?></span>
            <span class="text-muted"><?= $pct ?>%</span>
          </div>
          <div class="pgw">
            <div class="pgb" style="width:<?= $pct ?>%;background:<?= $pct >= 100 ? '#15803d' : '#0284c7' ?>;"></div>
          </div>
          <div style="font-size:10px;color:#94a3b8;margin-top:2px;"><?= $t['PaymentCount'] ?> payment(s)</div>
        </td>
        <td class="text-end fw-bold" style="color:<?= $t['Remaining'] > 0 ? '#dc2626' : '#15803d' ?>;font-size:14px;">₹<?= number_format($t['Remaining'], 0) ?></td>
        <td><?= $stBadge ?></td>
        <td>
          <?php if ($t['Commission'] > 0): ?>
            <?= $cmRecv ? '<span class="cm-recv"><i class="ri-check-double-line"></i> Received<br><small>₹' . number_format($t['Commission'], 0) . '</small></span>' : '<span class="cm-pend">Pending<br><small>₹' . number_format($t['Commission'], 0) . '</small></span>' ?>
          <?php else: ?><span class="text-muted fs-12">—</span><?php endif; ?>
        </td>
        <td>
          <div class="d-flex gap-1 justify-content-center">
            <?php if ($t['OwnerPaymentStatus'] !== 'Paid'): ?>
              <button class="ic-btn btn-outline-success text-success" title="Add Payment"
                onclick="openPay(<?= $t['TripId'] ?>,<?= $t['VehicleOwnerId'] ?>,'<?= addslashes($t['VehicleNumber'] ?? '—') ?>','<?= addslashes($t['OwnerName'] ?? '—') ?>',<?= $t['FreightAmount'] ?>,<?= $t['LabourCharge'] ?>,<?= $t['HoldingCharge'] ?>,<?= $t['OtherCharge'] ?>,<?= $t['TDS'] ?>,<?= $t['Commission'] ?>,<?= $t['NetPayable'] ?>,<?= $t['TotalPaid'] ?>)">
                <i class="ri-money-dollar-circle-line"></i>
              </button>
            <?php endif; ?>
            <button class="ic-btn btn-outline-info text-info" title="Payment History"
              onclick="viewHist(<?= $t['TripId'] ?>,'<?= addslashes($t['VehicleNumber'] ?? '—') ?>','<?= addslashes($t['OwnerName'] ?? '') ?>')">
              <i class="ri-history-line"></i>
            </button>
          </div>
        </td>
      </tr>
    <?php }

    /* PAID DIRECTLY ROW */
    function owDirectRow($t, $i)
    {
      $party  = $t['TripType'] === 'Agent' ? ($t['AgentName'] ?? '—') : ($t['ConsignerName'] ?? '—');
      $cmRecv = ($t['CommissionStatus'] ?? '') === 'Received';
    ?>
      <tr class="par tr-direct">
        <td class="text-muted fw-medium"><?= $i ?></td>
        <td style="white-space:nowrap;font-size:12px;"><?= date('d-m-Y', strtotime($t['TripDate'])) ?></td>
        <td>
          <div class="fw-bold" style="font-size:12px;"><i class="ri-truck-line text-muted me-1"></i><?= htmlspecialchars($t['VehicleNumber'] ?? '—') ?></div>
          <span class="owner-chip"><?= htmlspecialchars($t['OwnerName'] ?? '—') ?></span>
          <?php if (!empty($t['MobileNo'])): ?><div style="font-size:10px;color:#94a3b8;margin-top:2px;"><i class="ri-smartphone-line"></i> <?= $t['MobileNo'] ?></div><?php endif; ?>
          <div class="direct-note"><i class="ri-shield-check-line"></i> Direct to Owner</div>
        </td>
        <td style="font-size:11px;max-width:130px;">
          <div><?= htmlspecialchars($t['FromLocation']) ?> → <?= htmlspecialchars($t['ToLocation']) ?></div>
          <div class="text-muted"><?= htmlspecialchars($party) ?></div>
        </td>
        <td><span class="badge <?= $t['TripType'] === 'Agent' ? 'bg-warning text-dark' : 'bg-primary' ?>"><?= $t['TripType'] ?></span></td>
        <td>
          <div class="calc">
            <div class="calc-r"><span class="text-muted">Freight</span><span class="fw-semibold">₹<?= number_format($t['FreightAmount'], 0) ?></span></div>
            <?php if ($t['LabourCharge'] > 0):  ?><div class="calc-r add"><span class="text-muted">+ Labour</span> <span>₹<?= number_format($t['LabourCharge'], 0)  ?></span></div><?php endif; ?>
            <?php if ($t['HoldingCharge'] > 0): ?><div class="calc-r add"><span class="text-muted">+ Holding</span><span>₹<?= number_format($t['HoldingCharge'], 0) ?></span></div><?php endif; ?>
            <?php if ($t['OtherCharge'] > 0):  ?><div class="calc-r add"><span class="text-muted">+ Other</span> <span>₹<?= number_format($t['OtherCharge'], 0)   ?></span></div><?php endif; ?>
            <?php if ($t['TDS'] > 0):          ?><div class="calc-r add"><span class="text-muted">+ TDS</span> <span>₹<?= number_format($t['TDS'], 0)           ?></span></div><?php endif; ?>
            <?php if ($t['Commission'] > 0):   ?><div class="calc-r sub"><span class="text-muted">− Comm.</span> <span>₹<?= number_format($t['Commission'], 0)     ?></span></div><?php endif; ?>
            <div class="calc-r ttl"><span>Net Payable</span><span>₹<?= number_format($t['NetPayable'], 0) ?></span></div>
          </div>
        </td>
        <td class="text-center">
          <span class="bs-direct"><i class="ri-shield-check-line"></i> Paid Directly</span>
        </td>
        <td>
          <?php if ($t['Commission'] > 0): ?>
            <?= $cmRecv ? '<span class="cm-recv"><i class="ri-check-double-line"></i> Received<br><small>₹' . number_format($t['Commission'], 0) . '</small></span>' : '<span class="cm-pend">Pending<br><small>₹' . number_format($t['Commission'], 0) . '</small></span>' ?>
          <?php else: ?><span class="text-muted fs-12">—</span><?php endif; ?>
        </td>
        <td class="text-center">
          <button class="ic-btn btn-outline-info text-info" title="Payment History"
            onclick="viewHist(<?= $t['TripId'] ?>,'<?= addslashes($t['VehicleNumber'] ?? '—') ?>','<?= addslashes($t['OwnerName'] ?? '') ?>')">
            <i class="ri-history-line"></i>
          </button>
          <span title="No further payment — paid directly to owner"
            style="width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;color:#7c3aed;font-size:16px;">
            <i class="ri-lock-2-line"></i>
          </span>
        </td>
      </tr>
    <?php }

    /* RENDER 5 TABS */
    $tabData = [
      'all'     => ['trips' => $tripsNormal,  'cls' => 'fbar-all',    'direct' => false],
      'unpaid'  => ['trips' => $tripsUnpaid,  'cls' => 'fbar-unpaid', 'direct' => false],
      'partial' => ['trips' => $tripsPartial, 'cls' => 'fbar-partial', 'direct' => false],
      'paid'    => ['trips' => $tripsPaid,    'cls' => 'fbar-paid',   'direct' => false],
      'direct'  => ['trips' => $tripsDirect,  'cls' => 'fbar-direct', 'direct' => true],
    ];
    foreach ($tabData as $tabId => $td):
      $display = $tabId === 'all' ? 'block' : 'none';
    ?>
      <div id="tab-<?= $tabId ?>" style="display:<?= $display ?>;">
        <?php owFbar($tabId, $td['cls']); ?>
        <div class="tab-card">
          <div class="table-responsive">
            <table id="dt_<?= $tabId ?>" class="table table-hover align-middle mb-0 w-100">
              <?= $td['direct'] ? $theadDirect : $thead ?>
              <tbody>
                <?php $i = 1;
                foreach ($td['trips'] as $t): ?>
                  <?php $td['direct'] ? owDirectRow($t, $i++) : owRow($t, $i++); ?>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

  </div>
</div>

<!-- OWNER SUMMARY MODAL -->
<div class="modal fade" id="sumModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;">
      <div class="modal-header text-white py-3" style="background:linear-gradient(135deg,#1a237e,#1976d2);">
        <h5 class="modal-title fw-bold"><i class="ri-bar-chart-grouped-line me-2"></i>Owner-wise Summary</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <table class="table table-hover mb-0 sum-tbl">
          <thead>
            <tr>
              <th>Owner</th>
              <th class="text-center">Trips</th>
              <th class="text-end">Net Payable</th>
              <th class="text-end">Paid</th>
              <th class="text-end">Remaining</th>
              <th class="text-end">Comm. Recv'd</th>
              <th class="text-center">Direct</th>
              <th style="min-width:110px;">Progress</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ownerSummary as $os):
              $rem = max(0, floatval($os['TotalPayable']) - floatval($os['TotalPaid']));
              $pct = floatval($os['TotalPayable']) > 0
                ? min(100, round(floatval($os['TotalPaid']) / floatval($os['TotalPayable']) * 100)) : 0;
            ?>
              <tr>
                <td class="fw-semibold">
                  <?= htmlspecialchars($os['OwnerName']) ?>
                  <?= $os['City'] ? '<br><small class="text-muted">' . htmlspecialchars($os['City']) . '</small>' : '' ?>
                  <?= !empty($os['MobileNo']) ? '<br><small class="text-muted"><i class="ri-smartphone-line"></i> ' . $os['MobileNo'] . '</small>' : '' ?>
                </td>
                <td class="text-center"><span class="badge bg-secondary"><?= $os['TotalTrips'] ?></span></td>
                <td class="text-end fw-semibold">₹<?= number_format($os['TotalPayable'], 0) ?></td>
                <td class="text-end fw-semibold text-success">₹<?= number_format($os['TotalPaid'], 0) ?></td>
                <td class="text-end fw-bold <?= $rem > 0 ? 'text-danger' : 'text-success' ?>">₹<?= number_format($rem, 0) ?></td>
                <td class="text-end">
                  <?php if (floatval($os['CommissionReceived'] ?? 0) > 0): ?>
                    <span class="cm-recv"><i class="ri-check-double-line"></i> ₹<?= number_format($os['CommissionReceived'], 0) ?></span>
                  <?php else: ?><span class="text-muted fs-12">—</span><?php endif; ?>
                </td>
                <td class="text-center">
                  <?php if (intval($os['DirectlyPaidTrips'] ?? 0) > 0): ?>
                    <span class="bs-direct" style="font-size:10px;"><i class="ri-shield-check-line"></i> <?= $os['DirectlyPaidTrips'] ?> trip<?= $os['DirectlyPaidTrips'] > 1 ? 's' : '' ?></span>
                  <?php else: ?><span class="text-muted fs-12">—</span><?php endif; ?>
                </td>
                <td>
                  <div style="font-size:10px;color:#64748b;margin-bottom:3px;"><?= $pct ?>%</div>
                  <div class="pgw">
                    <div class="pgb" style="width:<?= $pct ?>%;background:<?= $pct >= 100 ? '#15803d' : '#0284c7' ?>;"></div>
                  </div>
                </td>
                <td>
                  <a href="?ownerId=<?= $os['VehicleOwnerId'] ?>"
                    onclick="bootstrap.Modal.getInstance(document.getElementById('sumModal')).hide();"
                    class="btn btn-sm btn-outline-primary py-0 px-2 fs-11">
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

<!-- PAY MODAL -->
<div class="modal fade" id="payModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;">
      <div class="modal-header text-white py-3" style="background:linear-gradient(135deg,#15803d,#16a34a);">
        <h5 class="modal-title fw-bold"><i class="ri-money-dollar-circle-line me-2"></i>Add Owner Payment</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="comm-alert" id="commAlert">
          <i class="ri-check-double-line me-2"></i>
          <strong>Commission will be auto-marked Received</strong> when fully paid. Amount: <strong id="ca_amt">₹0</strong>
        </div>
        <div class="rounded p-2 mb-3 d-flex align-items-center justify-content-between"
          style="background:#f0fdf4;border:1px solid #bbf7d0;">
          <div>
            <div class="fw-bold fs-13" id="pm_vehicle"></div>
            <span class="owner-chip mt-1 d-inline-block" id="pm_owner"></span>
          </div>
          <div class="text-end">
            <div style="font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;">Balance Due</div>
            <div class="fw-bold" style="font-size:22px;color:#dc2626;" id="pm_due">₹0</div>
          </div>
        </div>
        <div class="rounded p-3 mb-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
          <div class="fs-11 fw-bold text-muted text-uppercase mb-2">Calculation</div>
          <div class="pm-row"><span class="text-muted">Freight</span><span class="fw-semibold" id="pm_freight">₹0</span></div>
          <div class="pm-row" id="r_labour" style="display:none;"><span class="text-muted">+ Labour</span> <span class="text-success" id="pm_labour">₹0</span></div>
          <div class="pm-row" id="r_holding" style="display:none;"><span class="text-muted">+ Holding</span> <span class="text-success" id="pm_holding">₹0</span></div>
          <div class="pm-row" id="r_other" style="display:none;"><span class="text-muted">+ Other</span> <span class="text-success" id="pm_other">₹0</span></div>
          <div class="pm-row" id="r_tds" style="display:none;"><span class="text-muted">+ TDS</span> <span class="text-success" id="pm_tds">₹0</span></div>
          <div class="pm-row" id="r_comm">
            <span class="d-flex align-items-center gap-2"><span class="text-muted">− Commission</span><span class="comm-badge">Our Earning</span></span>
            <span class="text-danger" id="pm_comm">₹0</span>
          </div>
          <div class="pm-row net"><span>Net Payable to Owner</span><span class="text-primary" id="pm_net">₹0</span></div>
          <div class="mt-2" style="font-size:12px;">
            <div class="d-flex justify-content-between text-muted"><span>Already Paid:</span><span class="fw-semibold text-success" id="pm_paid">₹0</span></div>
            <div class="pgw mt-1">
              <div class="pgb" id="pm_prog" style="width:0%;background:#15803d;"></div>
            </div>
          </div>
        </div>
        <input type="hidden" id="pay_TripId"><input type="hidden" id="pay_OwnerId">
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
            <input type="date" id="pay_Date" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Amount (₹) <span class="text-danger">*</span></label>
            <div class="input-group"><span class="input-group-text fw-bold bg-light">₹</span>
              <input type="number" id="pay_Amount" class="form-control fw-bold" step="0.01" min="0.01" placeholder="0.00">
            </div>
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Mode</label>
            <select id="pay_Mode" class="form-select">
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
            <input type="text" id="pay_Ref" class="form-control" placeholder="Cheque / UTR">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Remarks</label>
            <input type="text" id="pay_Remarks" class="form-control" placeholder="Optional...">
          </div>
        </div>
      </div>
      <div class="modal-footer py-2 gap-2">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success fw-bold px-4" onclick="submitPay()"><i class="ri-save-3-line me-1"></i>Save Payment</button>
      </div>
    </div>
  </div>
</div>

<!-- HISTORY MODAL -->
<div class="modal fade" id="histModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;">
      <div class="modal-header text-white py-3" style="background:linear-gradient(135deg,#0369a1,#0284c7);">
        <h5 class="modal-title fw-bold"><i class="ri-history-line me-2"></i>Payment History — <span id="hist_lbl"></span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <table class="table table-bordered table-sm mb-0 fs-13">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Date</th>
              <th>Mode</th>
              <th>Reference</th>
              <th class="text-end">Amount</th>
              <th>Remarks</th>
              <th class="text-center">Del</th>
            </tr>
          </thead>
          <tbody id="histBody"></tbody>
          <tfoot>
            <tr class="table-success fw-bold">
              <td colspan="4" class="text-end">Total Paid:</td>
              <td id="histTotal">₹0</td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div class="modal-footer py-2"><button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button></div>
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
    var cfgD = {
      scrollX: true,
      pageLength: 25,
      dom: 'rtip',
      columnDefs: [{
        orderable: false,
        targets: [0, 8]
      }],
      language: {
        paginate: {
          previous: '‹',
          next: '›'
        }
      }
    };
    ['all', 'unpaid', 'partial', 'paid', 'direct'].forEach(function(id) {
      dts[id] = $('#dt_' + id).DataTable({
        ...(id === 'direct' ? cfgD : cfg),
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
    var tabs = ['all', 'unpaid', 'partial', 'paid', 'direct'];
    var cls = {
      all: 't-all',
      unpaid: 't-unpaid',
      partial: 't-partial',
      paid: 't-paid',
      direct: 't-direct'
    };
    tabs.forEach(function(t) {
      document.getElementById('nav-' + t).className = 'tnav';
      document.getElementById('tab-' + t).style.display = 'none';
    });
    document.getElementById('nav-' + name).classList.add(cls[name]);
    document.getElementById('tab-' + name).style.display = 'block';
    if (dts[name]) dts[name].columns.adjust();
  }

  function clearOwF(id) {
    $('#fo_' + id).val('').trigger('change');
    $('#sr_' + id).val('');
    if (dts[id]) dts[id].search('').draw();
  }

  function openPay(tripId, ownerId, vehicle, owner, freight, labour, holding, other, tds, commission, net, paid) {
    var rem = Math.max(0, net - paid),
      pct = net > 0 ? Math.min(100, Math.round(paid / net * 100)) : 0;
    $('#pay_TripId').val(tripId);
    $('#pay_OwnerId').val(ownerId);
    $('#pm_vehicle').text('Trip #' + tripId + ' — ' + vehicle);
    $('#pm_owner').text(owner);
    $('#pm_freight').text('₹' + parseFloat(freight).toFixed(2));
    $('#pm_labour').text('₹' + parseFloat(labour).toFixed(2));
    $('#pm_holding').text('₹' + parseFloat(holding).toFixed(2));
    $('#pm_other').text('₹' + parseFloat(other).toFixed(2));
    $('#pm_tds').text('₹' + parseFloat(tds).toFixed(2));
    $('#pm_comm').text('₹' + parseFloat(commission).toFixed(2));
    $('#pm_net').text('₹' + parseFloat(net).toFixed(2));
    $('#pm_paid').text('₹' + parseFloat(paid).toFixed(2));
    $('#pm_due').text('₹' + rem.toFixed(2));
    $('#pm_prog').css('width', pct + '%');
    $('#r_labour').toggle(parseFloat(labour) > 0);
    $('#r_holding').toggle(parseFloat(holding) > 0);
    $('#r_other').toggle(parseFloat(other) > 0);
    $('#r_tds').toggle(parseFloat(tds) > 0);
    $('#r_comm').toggle(parseFloat(commission) > 0);
    if (parseFloat(commission) > 0 && rem > 0) {
      $('#ca_amt').text('₹' + parseFloat(commission).toFixed(2));
      $('#commAlert').show();
    } else {
      $('#commAlert').hide();
    }
    $('#pay_Amount').val(rem > 0 ? rem.toFixed(2) : '');
    $('#pay_Date').val('<?= date('Y-m-d') ?>');
    $('#pay_Mode').val('Cash');
    $('#pay_Ref,#pay_Remarks').val('');
    new bootstrap.Modal('#payModal').show();
  }

  function submitPay() {
    var amt = parseFloat($('#pay_Amount').val());
    if (!amt || amt <= 0) {
      Swal.fire({
        icon: 'warning',
        title: 'Please enter a valid amount!',
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
      didOpen: function() {
        Swal.showLoading();
      }
    });
    var fd = new FormData();
    fd.append('addPayment', 1);
    fd.append('TripId', $('#pay_TripId').val());
    fd.append('OwnerId', $('#pay_OwnerId').val());
    fd.append('PaymentDate', $('#pay_Date').val());
    fd.append('Amount', amt);
    fd.append('PaymentMode', $('#pay_Mode').val());
    fd.append('ReferenceNo', $('#pay_Ref').val());
    fd.append('Remarks', $('#pay_Remarks').val());
    fetch('OwnerPayment_manage.php', {
      method: 'POST',
      body: fd
    }).then(r => r.json()).then(res => {
      Swal.close();
      if (res.status === 'success') {
        bootstrap.Modal.getInstance(document.getElementById('payModal')).hide();
        var msg = 'Payment Saved!';
        if (res.commissionReceived) msg += ' Commission marked Received ✓';
        Swal.fire({
          icon: 'success',
          title: msg,
          toast: true,
          position: 'top-end',
          showConfirmButton: false,
          timer: 3500
        });
        setTimeout(() => location.reload(), 2000);
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

  function viewHist(tripId, vehicle, owner) {
    $('#hist_lbl').text('Trip #' + tripId + ' — ' + vehicle + ' (' + owner + ')');
    $('#histBody').html('<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>');
    new bootstrap.Modal('#histModal').show();
    fetch('OwnerPayment_manage.php?getTripPayments=1&TripId=' + tripId).then(r => r.json()).then(rows => {
      var html = '',
        total = 0;
      var ic = {
        Cash: '💵',
        Cheque: '📋',
        NEFT: '🏦',
        RTGS: '🏦',
        UPI: '📱',
        Other: '💳'
      };
      if (!rows.length) html = '<tr><td colspan="7" class="text-center text-muted py-3">No payments recorded yet</td></tr>';
      rows.forEach(function(p, i) {
        total += parseFloat(p.Amount || 0);
        html += '<tr id="pr-' + p.OwnerPaymentId + '"><td>' + (i + 1) + '</td><td style="white-space:nowrap;">' + p.PaymentDate + '</td>' +
          '<td>' + (ic[p.PaymentMode] || '') + ' ' + p.PaymentMode + '</td>' +
          '<td><small>' + (p.ReferenceNo || '—') + '</small></td>' +
          '<td class="text-end fw-bold text-success">₹' + parseFloat(p.Amount).toFixed(2) + '</td>' +
          '<td><small>' + (p.Remarks || '—') + '</small></td>' +
          '<td class="text-center"><button class="btn btn-sm btn-outline-danger" style="width:30px;height:30px;padding:0;" onclick="delPay(' + p.OwnerPaymentId + ')">' +
          '<i class="ri-delete-bin-line"></i></button></td></tr>';
      });
      $('#histBody').html(html);
      $('#histTotal').text('₹' + total.toFixed(2));
    });
  }

  function delPay(pid) {
    Swal.fire({
        title: 'Delete this payment?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#dc2626'
      })
      .then(r => {
        if (!r.isConfirmed) return;
        var fd = new FormData();
        fd.append('deletePayment', 1);
        fd.append('PaymentId', pid);
        fetch('OwnerPayment_manage.php', {
          method: 'POST',
          body: fd
        }).then(r => r.json()).then(res => {
          if (res.status === 'success') {
            document.getElementById('pr-' + pid).remove();
            setTimeout(() => location.reload(), 1200);
          } else Swal.fire({
            icon: 'error',
            title: 'Error',
            text: res.msg
          });
        });
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