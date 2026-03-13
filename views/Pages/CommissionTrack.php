<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once "../../businessLogics/Admin.php";
require_once "../../config/database.php";
require_once "../../businessLogics/Commission.php";
Admin::checkAuth();

/* ═══ AJAX ═══ */
if (isset($_POST['saveCommission'])) {
  header('Content-Type: application/json');
  echo json_encode(Commission::save($pdo, intval($_POST['TripId']), floatval($_POST['CommissionAmount']), $_POST['RecoveryFrom'] ?? 'Party'));
  exit();
}
if (isset($_POST['markOwnerReceived'])) {
  header('Content-Type: application/json');
  $ids  = json_decode($_POST['commIds'], true);
  $date = $_POST['ReceivedDate'] ?? date('Y-m-d');
  echo json_encode(Commission::markReceived($pdo, $ids, $date));
  exit();
}
if (isset($_GET['getBilledTrips'])) {
  header('Content-Type: application/json');
  echo json_encode(Commission::getAllTripsForEntry($pdo));
  exit();
}

/* ═══ Date Filter ═══ */
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

/* ═══ PAGE DATA ═══ */
$summary = Commission::getSummary($pdo);
$allComm = Commission::getAll($pdo);

if ($dateFrom && $dateTo) {
  $allComm = array_values(array_filter(
    $allComm,
    fn($c) => $c['TripDate'] >= $dateFrom && $c['TripDate'] <= $dateTo
  ));
}

/* Tab 1 — All commissions (trip-wise, Party recovery) */
$tabComm  = array_values($allComm); // all trips with commission

/* Tab 2 — Owner recovery only */
$tabOwner = array_values(array_filter(
  $allComm,
  fn($c) => $c['RecoveryFrom'] === 'Owner'
));

$cntComm  = count($tabComm);
$cntOwner = count($tabOwner);
$cntOwnerPend = count(array_filter($tabOwner, fn($c) => $c['CommissionStatus'] === 'Pending'));

require_once "../layout/header.php";
require_once "../layout/sidebar.php";
?>
<style>
  .cm-hdr {
    background: linear-gradient(135deg, #0c4a6e, #0369a1 60%, #0284c7);
    border-radius: 14px;
    padding: 20px 26px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
  }

  .cm-hdr h4 {
    color: #fff;
    font-weight: 800;
    font-size: 19px;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 9px;
  }

  .cm-hdr p {
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
    min-width: 130px;
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

  .date-filter-bar {
    background: #fff;
    border: 1px solid #bae6fd;
    border-radius: 12px;
    padding: 13px 18px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }

  .df-btn {
    padding: 5px 13px;
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
    border-color: #0284c7;
    color: #0284c7;
    background: #f0f9ff;
  }

  .df-btn.active {
    border-color: #0284c7;
    background: #0284c7;
    color: #fff;
  }

  .df-sep {
    width: 1px;
    height: 28px;
    background: #e2e8f0;
  }

  .df-range {
    display: flex;
    align-items: center;
    gap: 7px;
  }

  .df-range input[type=date] {
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    padding: 4px 9px;
    font-size: 12px;
    font-weight: 600;
    color: #374151;
    outline: none;
  }

  .df-range input[type=date]:focus {
    border-color: #0284c7;
  }

  .df-apply {
    padding: 5px 15px;
    border-radius: 8px;
    background: #0284c7;
    color: #fff;
    border: none;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
  }

  .df-label {
    font-size: 12px;
    font-weight: 700;
    color: #0284c7;
    white-space: nowrap;
  }

  .df-active-tag {
    background: #e0f2fe;
    border: 1px solid #bae6fd;
    border-radius: 6px;
    padding: 3px 10px;
    font-size: 11px;
    font-weight: 700;
    color: #0284c7;
  }

  /* 2 main tabs */
  .main-tab-nav {
    display: flex;
    gap: 4px;
    margin-bottom: 0;
  }

  .mtnav {
    padding: 12px 28px;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    border: none;
    border-radius: 12px 12px 0 0;
    display: flex;
    align-items: center;
    gap: 8px;
    background: #f1f5f9;
    color: #64748b;
    border: 1px solid #e2e8f0;
    border-bottom: none;
    transition: all .15s;
  }

  .mtnav:hover {
    background: #e2e8f0;
  }

  .mtnav.act-comm {
    background: #0c4a6e;
    color: #fff;
    border-color: #0c4a6e;
  }

  .mtnav.act-owner {
    background: #5b21b6;
    color: #fff;
    border-color: #5b21b6;
  }

  .mtbadge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 20px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 800;
    padding: 0 6px;
  }

  .mb-blue {
    background: rgba(255, 255, 255, .25);
    color: #fff;
  }

  .mb-pur {
    background: rgba(255, 255, 255, .25);
    color: #fff;
  }

  .mb-gray {
    background: #e2e8f0;
    color: #64748b;
  }

  .tab-wrap {
    border: 1px solid #e2e8f0;
    border-radius: 0 12px 12px 12px;
    overflow: hidden;
  }

  /* inner sub-tabs for commission tab */
  .sub-nav {
    display: flex;
    gap: 2px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 8px 12px 0;
  }

  .snav {
    padding: 7px 15px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    border: none;
    background: transparent;
    border-bottom: 3px solid transparent;
    margin-bottom: -1px;
    display: flex;
    align-items: center;
    gap: 6px;
    color: #64748b;
    border-radius: 6px 6px 0 0;
  }

  .snav.s-all {
    color: #0284c7;
    border-bottom-color: #0284c7;
    background: #e0f2fe;
  }

  .snav.s-pend {
    color: #dc2626;
    border-bottom-color: #dc2626;
    background: #fee2e2;
  }

  .snav.s-recv {
    color: #15803d;
    border-bottom-color: #16a34a;
    background: #dcfce7;
  }

  .sbadge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 16px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 800;
    padding: 0 4px;
  }

  .fbar {
    padding: 10px 14px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
  }

  .tab-card {
    overflow: hidden;
  }

  th.tw {
    background: #0c4a6e !important;
    color: #fff !important;
    font-size: 12px;
    font-weight: 700;
    padding: 9px 12px;
    white-space: nowrap;
    border: none !important;
  }

  th.tw-pur {
    background: #5b21b6 !important;
    color: #fff !important;
    font-size: 12px;
    font-weight: 700;
    padding: 9px 12px;
    white-space: nowrap;
    border: none !important;
  }

  .cm-row td {
    padding: 9px 12px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
  }

  .cm-row:hover td {
    filter: brightness(.97);
  }

  .r-pend td {
    background: #fff7ed !important;
  }

  .r-recv td {
    background: #f0fdf4 !important;
  }

  .r-own-pend td {
    background: #faf5ff !important;
  }

  .r-own-recv td {
    background: #f0fdf4 !important;
  }

  .bs-recv {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
  }

  .bs-pend {
    background: #fff7ed;
    color: #c2410c;
    border: 1px solid #fed7aa;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
  }

  .bs-own-pend {
    background: #faf5ff;
    color: #7c3aed;
    border: 1px solid #ddd6fe;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
  }

  .ic-btn {
    width: 30px;
    height: 30px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 7px;
    font-size: 13px;
    border: 1px solid;
  }
</style>

<div class="main-content app-content">
  <div class="container-fluid" style="padding-bottom:30px;">

    <!-- HEADER -->
    <div class="cm-hdr">
      <div>
        <h4><i class="ri-percent-line"></i> Commission Tracking</h4>
        <p>Trip-wise commission record · Owner recovery manual tracking</p>
      </div>
      <div class="d-flex gap-2 align-items-center flex-wrap">
       
        <a href="CommissionTrack.php" class="btn btn-sm btn-light fw-bold"><i class="ri-refresh-line"></i></a>
      </div>
    </div>

    <!-- STATS -->
    <div class="srow">
      <div class="spill">
        <div class="sico" style="background:#e0f2fe;"><i class="ri-percent-line" style="color:#0284c7;"></i></div>
        <div>
          <div class="snum" style="color:#0284c7;">₹<?= number_format($summary['total_amount'] ?? 0, 0) ?></div>
          <div class="slbl">Total Commission</div>
          <div style="font-size:10px;color:#64748b;"><?= $summary['total'] ?? 0 ?> trips</div>
        </div>
      </div>
      <div class="spill">
        <div class="sico" style="background:#dcfce7;"><i class="ri-checkbox-circle-line" style="color:#15803d;"></i></div>
        <div>
          <div class="snum" style="font-size:13px;color:#15803d;">₹<?= number_format($summary['received'] ?? 0, 0) ?></div>
          <div class="slbl">Received</div>
          <div style="font-size:10px;color:#15803d;"><?= $summary['received_count'] ?? 0 ?> trips</div>
        </div>
      </div>
      <div class="spill" style="border-left:4px solid #dc2626;">
        <div class="sico" style="background:#fee2e2;"><i class="ri-time-line" style="color:#dc2626;"></i></div>
        <div>
          <?php $pendAmt = ($summary['party_pending'] ?? 0) + ($summary['owner_pending'] ?? 0); ?>
          <div class="snum" style="font-size:13px;color:#dc2626;">₹<?= number_format($pendAmt, 0) ?></div>
          <div class="slbl">Pending (All)</div>
          <div style="font-size:10px;color:#64748b;"><?= ($summary['pending_count'] ?? 0) ?> trips</div>
        </div>
      </div>
      <div class="spill" style="border-left:4px solid #7c3aed;">
        <div class="sico" style="background:#ede9fe;"><i class="ri-home-line" style="color:#7c3aed;"></i></div>
        <div>
          <div class="snum" style="font-size:13px;color:#7c3aed;">₹<?= number_format($summary['owner_pending'] ?? 0, 0) ?></div>
          <div class="slbl">Owner Recovery Pending</div>
          <div style="font-size:10px;color:#dc2626;font-weight:700;"><?= $cntOwnerPend ?> trips · manual</div>
        </div>
      </div>
      <div class="spill">
        <div class="sico" style="background:#f1f5f9;"><i class="ri-road-map-line" style="color:#475569;"></i></div>
        <div>
          <?php $rcvPct = ($summary['total_amount'] ?? 0) > 0
            ? min(100, round(($summary['received'] ?? 0) / ($summary['total_amount']) * 100)) : 0; ?>
          <div class="snum" style="font-size:13px;color:#475569;"><?= $rcvPct ?>%</div>
          <div class="slbl">Recovery Rate</div>
          <div class="pgw" style="min-width:80px;">
            <div class="pgb" style="width:<?= $rcvPct ?>%;background:#15803d;"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- DATE FILTER -->
    <div class="date-filter-bar">
      <span class="df-label"><i class="ri-calendar-line me-1"></i>Filter:</span>
      <div class="d-flex gap-2 flex-wrap">
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
          <?php
          if ($datePreset === 'today')         echo 'Today: ' . $today;
          elseif ($datePreset === 'yesterday') echo 'Yesterday: ' . date('d-m-Y', strtotime('-1 day'));
          elseif ($datePreset === 'thisweek')  echo 'This Week';
          elseif ($datePreset === 'custom' && $dateFrom && $dateTo)
            echo date('d-m-Y', strtotime($dateFrom)) . ' → ' . date('d-m-Y', strtotime($dateTo));
          ?>
        </span>
        <a href="CommissionTrack.php" class="df-btn" style="border-color:#dc2626;color:#dc2626;"><i class="ri-close-line"></i> Clear</a>
      <?php endif; ?>
    </div>

    <!-- ══ 2 MAIN TABS ══ -->
    <div class="main-tab-nav">
      <button class="mtnav act-comm" id="mtnav-comm" onclick="switchMain('comm')">
        <i class="ri-percent-line"></i> Commission
        <span class="mtbadge mb-blue"><?= $cntComm ?></span>
      </button>
      <button class="mtnav" id="mtnav-owner" onclick="switchMain('owner')">
        <i class="ri-home-line"></i> Recover from Owner
        <span class="mtbadge mb-gray" id="ownerBadge"><?= $cntOwner ?></span>
        <?php if ($cntOwnerPend > 0): ?>
          <span class="mtbadge" style="background:#dc2626;color:#fff;"><?= $cntOwnerPend ?> pending</span>
        <?php endif; ?>
      </button>
    </div>

    <?php
    /* ═══════════════════════════════════════
   TAB 1 — COMMISSION (trip-wise, read-only)
   Columns: #, Date, Vehicle, Route, Freight, Commission, Status, Received Date, Edit
═══════════════════════════════════════ */
    $commAll  = $tabComm;
    $commPend = array_values(array_filter($tabComm, fn($c) => $c['CommissionStatus'] === 'Pending'));
    $commRecv = array_values(array_filter($tabComm, fn($c) => $c['CommissionStatus'] === 'Received'));

    function commRow($c, $i)
    {
      $isPend  = $c['CommissionStatus'] === 'Pending';
      $party   = $c['TripType'] === 'Agent' ? htmlspecialchars($c['AgentName'] ?? '—') : htmlspecialchars($c['ConsignerName'] ?? '—');
      $rowCls  = $isPend ? 'cm-row r-pend' : 'cm-row r-recv';
    ?>
      <tr class="<?= $rowCls ?>">
        <td class="text-muted fw-medium"><?= $i ?></td>
        <td style="white-space:nowrap;font-size:12px;"><?= date('d-m-Y', strtotime($c['TripDate'])) ?></td>
        <td><span class="badge bg-secondary" style="font-size:11px;"><?= htmlspecialchars($c['VehicleNumber'] ?? '—') ?></span></td>
        <td style="font-size:11px;white-space:nowrap;"><?= htmlspecialchars($c['FromLocation']) ?> → <?= htmlspecialchars($c['ToLocation']) ?></td>
        <td style="font-size:12px;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= $party ?>">
          <span class="badge <?= $c['TripType'] === 'Agent' ? 'bg-warning text-dark' : 'bg-primary' ?>" style="font-size:10px;"><?= $c['TripType'] ?></span>
          <?= $party ?>
        </td>
        <td class="text-end fw-semibold" style="color:#374151;">₹<?= number_format($c['FreightAmount'], 0) ?></td>
        <td class="text-end fw-bold" style="color:#0284c7;font-size:14px;">₹<?= number_format($c['CommissionAmount'], 0) ?></td>
        <td>
          <?= $isPend
            ? '<span class="bs-pend"><i class="ri-time-line me-1"></i>Pending</span>'
            : '<span class="bs-recv"><i class="ri-check-line me-1"></i>Received</span>' ?>
        </td>
        <td style="font-size:12px;white-space:nowrap;">
          <?= $c['ReceivedDate'] ? date('d-m-Y', strtotime($c['ReceivedDate'])) : '<span class="text-muted">—</span>' ?>
        </td>
        <td class="text-center">
          <button class="ic-btn btn-outline-warning text-warning" title="Edit Commission"
            onclick="openEdit(<?= $c['TripCommissionId'] ?>,<?= $c['TripId'] ?>,<?= $c['CommissionAmount'] ?>,'<?= $c['RecoveryFrom'] ?>')">
            <i class="ri-edit-line"></i>
          </button>
        </td>
      </tr>
    <?php }
    ?>

    <!-- TAB 1 WRAP -->
    <div id="mt-comm" class="tab-wrap">
      <!-- Sub tabs: All / Pending / Received -->
      <div class="sub-nav">
        <button class="snav s-all" id="snav-all" onclick="switchSub('all')">
          All <span class="sbadge" style="background:#dbeafe;color:#0284c7;"><?= count($commAll) ?></span>
        </button>
        <button class="snav" id="snav-pend" onclick="switchSub('pend')">
          Pending <span class="sbadge" style="background:#fee2e2;color:#dc2626;"><?= count($commPend) ?></span>
        </button>
        <button class="snav" id="snav-recv" onclick="switchSub('recv')">
          Received <span class="sbadge" style="background:#dcfce7;color:#15803d;"><?= count($commRecv) ?></span>
        </button>
      </div>

      <?php
      $subTabs = [
        'all'  => $commAll,
        'pend' => $commPend,
        'recv' => $commRecv,
      ];
      foreach ($subTabs as $sid => $rows):
        $disp = $sid === 'all' ? 'block' : 'none';
      ?>
        <div id="sub-<?= $sid ?>" style="display:<?= $disp ?>;">
          <div class="fbar">
            <div class="row g-2 align-items-center">
              <div class="col ms-auto" style="max-width:380px;">
                <div class="input-group input-group-sm">
                  <span class="input-group-text bg-white border-end-0"><i class="ri-search-line text-muted"></i></span>
                  <input type="text" id="sr_<?= $sid ?>" class="form-control border-start-0" placeholder="Vehicle, route, party...">
                  <span id="fi_<?= $sid ?>" class="input-group-text fw-bold text-white"
                    style="background:#0c4a6e;min-width:52px;justify-content:center;font-size:11px;"></span>
                </div>
              </div>
            </div>
          </div>
          <div class="tab-card">
            <div class="table-responsive">
              <table id="dt_<?= $sid ?>" class="table table-hover align-middle mb-0 w-100">
                <thead>
                  <tr>
                    <th class="tw" style="width:36px;">#</th>
                    <th class="tw">Date</th>
                    <th class="tw">Vehicle</th>
                    <th class="tw">Route</th>
                    <th class="tw">Party / Agent</th>
                    <th class="tw text-end">Freight</th>
                    <th class="tw text-end">Commission</th>
                    <th class="tw">Status</th>
                    <th class="tw">Received Date</th>
                    <th class="tw text-center">Edit</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $i = 1;
                  foreach ($rows as $c) {
                    commRow($c, $i++);
                  } ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php
    /* ═══════════════════════════════════════
   TAB 2 — RECOVER FROM OWNER
   Columns: #, Date, Vehicle, Route, Owner, Freight, Commission, Status, Received Date, Mark
═══════════════════════════════════════ */
    $ownerPend = array_values(array_filter($tabOwner, fn($c) => $c['CommissionStatus'] === 'Pending'));
    $ownerRecv = array_values(array_filter($tabOwner, fn($c) => $c['CommissionStatus'] === 'Received'));

    function ownerRow($c, $i)
    {
      $isPend = $c['CommissionStatus'] === 'Pending';
      $party  = $c['TripType'] === 'Agent' ? htmlspecialchars($c['AgentName'] ?? '—') : htmlspecialchars($c['ConsignerName'] ?? '—');
      $rowCls = $isPend ? 'cm-row r-own-pend' : 'cm-row r-own-recv';
    ?>
      <tr class="<?= $rowCls ?>">
        <td class="text-center">
          <?php if ($isPend): ?>
            <input type="checkbox" class="own-chk" data-id="<?= $c['TripCommissionId'] ?>" onchange="updateOwnerSel()">
          <?php else: ?>
            <i class="ri-lock-2-line text-muted" style="font-size:14px;"></i>
          <?php endif; ?>
        </td>
        <td class="text-muted fw-medium"><?= $i ?></td>
        <td style="white-space:nowrap;font-size:12px;"><?= date('d-m-Y', strtotime($c['TripDate'])) ?></td>
        <td><span class="badge bg-secondary" style="font-size:11px;"><?= htmlspecialchars($c['VehicleNumber'] ?? '—') ?></span></td>
        <td style="font-size:11px;white-space:nowrap;"><?= htmlspecialchars($c['FromLocation']) ?> → <?= htmlspecialchars($c['ToLocation']) ?></td>
        <td style="font-size:12px;"><?= $party ?></td>
        <td class="text-end fw-semibold" style="color:#374151;">₹<?= number_format($c['FreightAmount'], 0) ?></td>
        <td class="text-end fw-bold" style="color:#7c3aed;font-size:14px;">₹<?= number_format($c['CommissionAmount'], 0) ?></td>
        <td>
          <?= $isPend
            ? '<span class="bs-own-pend"><i class="ri-time-line me-1"></i>Pending</span>'
            : '<span class="bs-recv"><i class="ri-check-line me-1"></i>Received</span>' ?>
        </td>
        <td style="font-size:12px;white-space:nowrap;">
          <?= $c['ReceivedDate'] ? date('d-m-Y', strtotime($c['ReceivedDate'])) : '<span class="text-muted">—</span>' ?>
        </td>
      </tr>
    <?php }
    ?>

    <!-- TAB 2 WRAP -->
    <div id="mt-owner" style="display:none;" class="tab-wrap">

      <!-- Action bar -->
      <div class="fbar d-flex align-items-center justify-content-between flex-wrap gap-2"
        style="background:#faf5ff;border-bottom:1px solid #ddd6fe;">
        <div class="d-flex align-items-center gap-3">
          <div>
            <input type="checkbox" id="chkAllOwner" onchange="toggleAllOwner(this)" class="form-check-input me-1">
            <label for="chkAllOwner" class="fw-semibold fs-13 text-muted">Select All Pending</label>
          </div>
          <span id="ownerSelInfo" class="fw-bold" style="color:#7c3aed;font-size:13px;"></span>
        </div>
        <div class="d-flex gap-2 align-items-center">
          <button class="btn fw-bold text-white" id="markOwnerBtn"
            style="display:none;background:#7c3aed; width: 200px; border-radius:9px;height:36px;font-size:13px;"
            onclick="openOwnerMarkModal()">
             Mark as Received
          </button>
          <div class="input-group input-group-sm" style="max-width:300px;">
            <span class="input-group-text bg-white border-end-0"><i class="ri-search-line text-muted"></i></span>
            <input type="text" id="sr_owner" class="form-control border-start-0" placeholder="Vehicle, route...">
            <span id="fi_owner" class="input-group-text fw-bold text-white"
              style="background:#5b21b6;min-width:52px;justify-content:center;font-size:11px;"></span>
          </div>
        </div>
      </div>

      <!-- Sub tabs: Pending / Received / All -->
      <div class="sub-nav" style="background:#faf5ff;">
        <button class="snav" id="osnav-pend" style="color:#7c3aed;border-bottom-color:#7c3aed;background:#ede9fe;" onclick="switchOwnerSub('pend')">
          Pending <span class="sbadge" style="background:#ede9fe;color:#7c3aed;"><?= count($ownerPend) ?></span>
        </button>
        <button class="snav" id="osnav-recv" onclick="switchOwnerSub('recv')">
          Received <span class="sbadge" style="background:#dcfce7;color:#15803d;"><?= count($ownerRecv) ?></span>
        </button>
        <button class="snav" id="osnav-oall" onclick="switchOwnerSub('oall')">
          All <span class="sbadge" style="background:#dbeafe;color:#0284c7;"><?= count($tabOwner) ?></span>
        </button>
      </div>

      <?php
      $ownerSubs = ['pend' => $ownerPend, 'recv' => $ownerRecv, 'oall' => $tabOwner];
      foreach ($ownerSubs as $sid => $rows):
        $disp = $sid === 'pend' ? 'block' : 'none';
      ?>
        <div id="osub-<?= $sid ?>" style="display:<?= $disp ?>;">
          <div class="tab-card">
            <div class="table-responsive">
              <table id="odt_<?= $sid ?>" class="table table-hover align-middle mb-0 w-100">
                <thead>
                  <tr>
                    <th class="tw-pur" style="width:36px;"></th>
                    <th class="tw-pur" style="width:36px;">#</th>
                    <th class="tw-pur">Date</th>
                    <th class="tw-pur">Vehicle</th>
                    <th class="tw-pur">Route</th>
                    <th class="tw-pur">Party / Agent</th>
                    <th class="tw-pur text-end">Freight</th>
                    <th class="tw-pur text-end">Commission</th>
                    <th class="tw-pur">Status</th>
                    <th class="tw-pur">Received Date</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $i = 1;
                  foreach ($rows as $c) {
                    ownerRow($c, $i++);
                  } ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>
</div>

<!-- ════ SET COMMISSION MODAL ════ -->
<div class="modal fade" id="setCommModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;">
      <div class="modal-header text-white py-3" style="background:linear-gradient(135deg,#0c4a6e,#0284c7);">
        <h5 class="modal-title fw-bold"><i class="ri-percent-line me-2"></i>Set / Update Commission — All Trips</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div id="setCommLoading" class="text-center py-5">
          <div class="spinner-border text-info"></div>
          <div class="mt-2 text-muted">Loading trips...</div>
        </div>
        <table class="table table-hover table-sm mb-0 fs-12" id="setCommTable" style="display:none;">
          <thead>
            <tr>
              <th class="tw">#</th>
              <th class="tw">Date</th>
              <th class="tw">Vehicle</th>
              <th class="tw">Route</th>
              <th class="tw">Type</th>
              <th class="tw text-end">Freight</th>
              <th class="tw" style="min-width:150px;">Commission ₹</th>
              <th class="tw text-center">Save</th>
            </tr>
          </thead>
          <tbody id="setCommBody"></tbody>
        </table>
      </div>
      <div class="modal-footer py-2">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ════ EDIT MODAL ════ -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;">
      <div class="modal-header text-white py-3" style="background:linear-gradient(135deg,#b45309,#d97706);">
        <h5 class="modal-title fw-bold"><i class="ri-edit-line me-2"></i>Edit Commission Amount</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="ec_commId"><input type="hidden" id="ec_tripId"><input type="hidden" id="ec_recFrom">
        <label class="form-label fw-semibold">Commission Amount (₹)</label>
        <div class="input-group">
          <span class="input-group-text fw-bold bg-light">₹</span>
          <input type="number" id="ec_amount" class="form-control fw-bold fs-16" step="0.01" min="0">
        </div>
        <div class="mt-3 rounded p-2" style="background:#f0f9ff;font-size:12px;color:#0369a1;">
          <i class="ri-information-line me-1"></i>
          Recovery source is auto-set based on trip type (PaidDirectly → Owner, normal → Party)
        </div>
      </div>
      <div class="modal-footer py-2 gap-2">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn fw-bold px-4" style="background:#d97706;color:#fff;" onclick="saveEdit()">
          <i class="ri-save-3-line me-1"></i>Update
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ════ OWNER MARK RECEIVED MODAL ════ -->
<div class="modal fade" id="ownerMarkModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;">
      <div class="modal-header text-white py-3" style="background:linear-gradient(135deg,#3b0764,#7c3aed);">
        <h5 class="modal-title fw-bold"><i class="ri-check-double-line me-2"></i>Mark Owner Commission Received</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="rounded p-3 mb-3 d-flex align-items-center gap-3" style="background:#f5f3ff;border:1px solid #ddd6fe;">
          <div style="font-size:32px;color:#7c3aed;"><i class="ri-home-line"></i></div>
          <div>
            <div class="fw-bold" style="font-size:20px;color:#7c3aed;" id="omr_count">0</div>
            <div class="text-muted" style="font-size:12px;">commission(s) received from owner</div>
          </div>
        </div>
        <label class="form-label fw-semibold">Date Received from Owner</label>
        <input type="date" id="omr_date" class="form-control" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="modal-footer py-2 gap-2">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn fw-bold px-4 text-white" style="background:#7c3aed;" onclick="confirmOwnerMark()">
          <i class="ri-check-line me-1"></i>Confirm Received
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  var dts = {},
    odts = {};

  $(document).ready(function() {
    var cfg = {
      scrollX: true,
      pageLength: 25,
      dom: 'rtip',
      columnDefs: [{
        orderable: false,
        targets: [0, 9]
      }],
      language: {
        paginate: {
          previous: '‹',
          next: '›'
        }
      }
    };

    /* Commission sub-tabs */
    ['all', 'pend', 'recv'].forEach(function(id) {
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
    });

    /* Owner sub-tabs */
    var ocfg = {
      scrollX: true,
      pageLength: 25,
      dom: 'rtip',
      columnDefs: [{
        orderable: false,
        targets: [0, 9]
      }],
      language: {
        paginate: {
          previous: '‹',
          next: '›'
        }
      }
    };
    ['pend', 'recv', 'oall'].forEach(function(id) {
      odts[id] = $('#odt_' + id).DataTable({
        ...ocfg,
        drawCallback: function() {
          var info = this.api().page.info();
          $('#fi_owner').text(info.recordsDisplay + '/' + info.recordsTotal);
        }
      });
    });
    $('#sr_owner').on('keyup input', function() {
      var v = $(this).val();
      Object.values(odts).forEach(function(dt) {
        dt.search(v).draw();
      });
    });
  });

  /* ── Main Tab switch ── */
  function switchMain(name) {
    document.getElementById('mt-comm').style.display = name === 'comm' ? 'block' : 'none';
    document.getElementById('mt-owner').style.display = name === 'owner' ? 'block' : 'none';
    document.getElementById('mtnav-comm').className = 'mtnav' + (name === 'comm' ? ' act-comm' : '');
    document.getElementById('mtnav-owner').className = 'mtnav' + (name === 'owner' ? ' act-owner' : '');
    document.getElementById('ownerBadge').className = name === 'owner' ? 'mtbadge mb-pur' : 'mtbadge mb-gray';
    if (name === 'comm') Object.values(dts).forEach(dt => dt.columns.adjust());
    if (name === 'owner') Object.values(odts).forEach(dt => dt.columns.adjust());
  }

  /* ── Commission sub-tabs ── */
  function switchSub(name) {
    ['all', 'pend', 'recv'].forEach(function(s) {
      document.getElementById('sub-' + s).style.display = s === name ? 'block' : 'none';
      var el = document.getElementById('snav-' + s);
      el.className = 'snav' + (s === name ? ' s-' + s : '');
    });
    if (dts[name]) dts[name].columns.adjust();
  }

  /* ── Owner sub-tabs ── */
  function switchOwnerSub(name) {
    ['pend', 'recv', 'oall'].forEach(function(s) {
      document.getElementById('osub-' + s).style.display = s === name ? 'block' : 'none';
      var el = document.getElementById('osnav-' + s);
      if (s === name) {
        var color = s === 'pend' ? '#7c3aed' : (s === 'recv' ? '#15803d' : '#0284c7');
        var bg = s === 'pend' ? '#ede9fe' : (s === 'recv' ? '#dcfce7' : '#dbeafe');
        el.style.cssText = 'color:' + color + ';border-bottom:3px solid ' + color + ';background:' + bg + ';';
      } else {
        el.style.cssText = '';
      }
    });
    if (odts[name]) odts[name].columns.adjust();
  }

  /* ── Owner selection ── */
  function toggleAllOwner(cb) {
    $('.own-chk').prop('checked', cb.checked);
    updateOwnerSel();
  }

  function updateOwnerSel() {
    var n = $('.own-chk:checked').length;
    $('#ownerSelInfo').text(n > 0 ? n + ' selected' : '');
    $('#markOwnerBtn').toggle(n > 0);
  }

  function openOwnerMarkModal() {
    var ids = [];
    $('.own-chk:checked').each(function() {
      ids.push($(this).data('id'));
    });
    if (!ids.length) return;
    window._ownerIds = ids;
    $('#omr_count').text(ids.length);
    new bootstrap.Modal('#ownerMarkModal').show();
  }

  function confirmOwnerMark() {
    var ids = window._ownerIds || [];
    var date = $('#omr_date').val();
    if (!ids.length || !date) return;
    Swal.fire({
      title: 'Saving...',
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading()
    });
    var fd = new FormData();
    fd.append('markOwnerReceived', 1);
    fd.append('commIds', JSON.stringify(ids));
    fd.append('ReceivedDate', date);
    fetch('CommissionTrack.php', {
      method: 'POST',
      body: fd
    }).then(r => r.json()).then(res => {
      Swal.close();
      if (res.status === 'success') {
        bootstrap.Modal.getInstance(document.getElementById('ownerMarkModal')).hide();
        Swal.fire({
          icon: 'success',
          title: res.count + ' Commission(s) marked Received!',
          toast: true,
          position: 'top-end',
          showConfirmButton: false,
          timer: 2500
        });
        setTimeout(() => location.reload(), 2000);
      } else Swal.fire({
        icon: 'error',
        title: 'Error',
        text: res.msg
      });
    });
  }

  /* ── Edit ── */
  function openEdit(commId, tripId, amt, recFrom) {
    $('#ec_commId').val(commId);
    $('#ec_tripId').val(tripId);
    $('#ec_amount').val(parseFloat(amt).toFixed(2));
    $('#ec_recFrom').val(recFrom);
    new bootstrap.Modal('#editModal').show();
  }

  function saveEdit() {
    var fd = new FormData();
    fd.append('saveCommission', 1);
    fd.append('TripId', $('#ec_tripId').val());
    fd.append('CommissionAmount', $('#ec_amount').val());
    fd.append('RecoveryFrom', $('#ec_recFrom').val());
    Swal.fire({
      title: 'Saving...',
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading()
    });
    fetch('CommissionTrack.php', {
      method: 'POST',
      body: fd
    }).then(r => r.json()).then(res => {
      Swal.close();
      if (res.status === 'success') {
        bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
        Swal.fire({
          icon: 'success',
          title: 'Updated!',
          toast: true,
          position: 'top-end',
          showConfirmButton: false,
          timer: 2000
        });
        setTimeout(() => location.reload(), 1500);
      } else Swal.fire({
        icon: 'error',
        title: 'Error',
        text: res.msg
      });
    });
  }

  /* ── Set Commission Modal ── */
  function openSetModal() {
    $('#setCommLoading').show();
    $('#setCommTable').hide();
    new bootstrap.Modal('#setCommModal').show();
    fetch('CommissionTrack.php?getBilledTrips=1').then(r => r.json()).then(rows => {
      $('#setCommLoading').hide();
      var html = '';
      rows.forEach(function(r, i) {
        var tBadge = '<span class="badge ' + (r.TripType === 'Agent' ? 'bg-warning text-dark' : 'bg-primary') + '" style="font-size:10px;">' + r.TripType + '</span>';
        var cBadge = r.TripCommissionId ?
          (r.CommissionStatus === 'Received' ?
            '<span class="bs-recv" style="font-size:10px;">Received</span>' :
            '<span class="bs-pend" style="font-size:10px;">Pending</span>') :
          '<span class="badge bg-light text-dark border" style="font-size:10px;">New</span>';
        var isPD = r.FreightPaymentToOwnerStatus === 'PaidDirectly';
        html += '<tr style="' + (isPD ? 'background:#faf5ff;' : '') + '"><td class="text-muted">' + (i + 1) + '</td>' +
          '<td style="white-space:nowrap;font-size:11px;">' + r.TripDate + '</td>' +
          '<td><span class="badge bg-secondary" style="font-size:10px;">' + (r.VehicleNumber || '—') + '</span>' +
          (isPD ? ' <span class="badge" style="background:#7c3aed;font-size:9px;">PaidDirectly</span>' : '') + '</td>' +
          '<td style="white-space:nowrap;font-size:11px;">' + r.FromLocation + ' → ' + r.ToLocation + '</td>' +
          '<td>' + tBadge + ' ' + cBadge + '</td>' +
          '<td class="text-end fw-semibold">₹' + parseFloat(r.FreightAmount || 0).toFixed(0) + '</td>' +
          '<td><div class="input-group input-group-sm"><span class="input-group-text fw-bold bg-light">₹</span>' +
          '<input type="number" class="form-control comm-inp fw-bold" step="0.01" min="0" value="' + parseFloat(r.CommissionAmount || 0).toFixed(2) + '"></div></td>' +
          '<td class="text-center"><button class="btn btn-sm fw-bold text-white" style="background:#0284c7;width:34px;height:30px;padding:0;" onclick="saveRowComm(' + r.TripId + ',this)">' +
          '<i class="ri-save-3-line"></i></button></td></tr>';
      });
      $('#setCommBody').html(html || '<tr><td colspan="8" class="text-center py-3 text-muted">No trips found</td></tr>');
      $('#setCommTable').show();
    }).catch(() => $('#setCommLoading').html('<div class="text-danger text-center py-3">Failed to load</div>'));
  }

  function saveRowComm(tripId, btn) {
    var row = $(btn).closest('tr');
    var amt = row.find('.comm-inp').val();
    var fd = new FormData();
    fd.append('saveCommission', 1);
    fd.append('TripId', tripId);
    fd.append('CommissionAmount', amt);
    fd.append('RecoveryFrom', 'Party');
    $(btn).html('<i class="ri-loader-line"></i>').prop('disabled', true);
    fetch('CommissionTrack.php', {
      method: 'POST',
      body: fd
    }).then(r => r.json()).then(res => {
      if (res.status === 'success') {
        $(btn).html('<i class="ri-check-line"></i>').css('background', '#15803d').prop('disabled', false);
        setTimeout(() => $(btn).html('<i class="ri-save-3-line"></i>').css('background', '#0284c7'), 2500);
      } else $(btn).html('<i class="ri-close-line"></i>').css('background', '#dc2626').prop('disabled', false);
    });
  }

  window.addEventListener('offline', () => Swal.fire({
    icon: 'warning',
    title: 'Internet Disconnected!',
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000
  }));
  window.addEventListener('online', () => Swal.fire({
    icon: 'success',
    title: 'Back Online!',
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2000
  }));
</script>
<?php require_once "../layout/footer.php"; ?>