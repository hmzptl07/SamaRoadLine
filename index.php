<?php
session_start();
require_once "businessLogics/Admin.php";
Admin::checkAuth();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once "config/database.php";
require_once "businessLogics/Dashboard.php";

$stats       = Dashboard::getStats($pdo);
$monthlyData = Dashboard::getMonthlyFreight($pdo);
$recentTrips = Dashboard::getRecentTrips($pdo, 8);
$unpaidBills = Dashboard::getUnpaidBills($pdo, 6);
$commPending = Dashboard::getCommissionPendingParty($pdo, 5);
$ownerPend   = Dashboard::getOwnerPendingTrips($pdo, 5);

$chartLabels  = json_encode(array_column($monthlyData, 'mon'));
$chartFreight = json_encode(array_map(fn($r) => floatval($r['freight']), $monthlyData));
$chartTrips   = json_encode(array_map(fn($r) => intval($r['trips']),    $monthlyData));

// Helper pcts
$billPct  = $stats['totalBillAmt']  > 0 ? min(100,round($stats['receivedBillAmt'] / $stats['totalBillAmt']  * 100)) : 0;
$commPct  = $stats['totalComm']     > 0 ? min(100,round($stats['receivedComm']    / $stats['totalComm']     * 100)) : 0;
$opPct    = $stats['opPayable']     > 0 ? min(100,round($stats['opTotalPaid']     / $stats['opPayable']     * 100)) : 0;
$oaPct    = $stats['oaTotal']       > 0 ? min(100,round($stats['oaAdjusted']      / $stats['oaTotal']       * 100)) : 0;
$paPct    = $stats['paTotal']       > 0 ? min(100,round($stats['paAdjusted']      / $stats['paTotal']       * 100)) : 0;
$tripPct  = $stats['totalTrips']    > 0 ? min(100,round($stats['tripCompleted']   / $stats['totalTrips']    * 100)) : 0;

require_once "views/layout/header.php";
require_once "views/layout/sidebar.php";
?>
<style>
/* ─── BANNER ─── */
.db-banner {
  background: linear-gradient(135deg, #07204a 0%, #0f4c8a 50%, #0369a1 100%);
  border-radius: 16px; padding: 24px 30px; margin-bottom: 24px;
  display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;
}
.db-banner h4 { color: #fff; font-weight: 900; font-size: 21px; margin: 0; letter-spacing: -.3px; }
.db-banner p  { color: rgba(255,255,255,.65); font-size: 12.5px; margin: 5px 0 0; }
.bq { padding: 9px 18px; border-radius: 10px; font-size: 12px; font-weight: 800;
  text-decoration: none; display: inline-flex; align-items: center; gap: 7px; transition: .15s; }
.bq-white { background: #fff; color: #0f4c8a; }
.bq-white:hover { background: #e0f2fe; color: #0c4a6e; }
.bq-ghost { background: rgba(255,255,255,.14); color: #fff; border: 1.5px solid rgba(255,255,255,.35); }
.bq-ghost:hover { background: rgba(255,255,255,.26); }

/* ─── MODULE CARDS ─── */
.mc {
  background: #fff; border-radius: 14px; border: 1px solid #e8edf5;
  box-shadow: 0 1px 4px rgba(0,0,0,.06); padding: 18px 20px;
  transition: .2s; height: 100%;
}
.mc:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.1); }
.mc-ico { width: 46px; height: 46px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
.mc-num { font-size: 22px; font-weight: 900; line-height: 1.1; }
.mc-lbl { font-size: 11.5px; color: #64748b; margin-top: 2px; }
.mc-sub { font-size: 10.5px; margin-top: 4px; font-weight: 700; }
.pgw { height: 5px; background: #edf2f7; border-radius: 4px; overflow: hidden; margin-top: 7px; }
.pgb { height: 100%; border-radius: 4px; }
/* accent left borders */
.bl-blue   { border-left: 4px solid #2563eb !important; }
.bl-green  { border-left: 4px solid #16a34a !important; }
.bl-red    { border-left: 4px solid #dc2626 !important; }
.bl-amber  { border-left: 4px solid #d97706 !important; }
.bl-purple { border-left: 4px solid #7c3aed !important; }
.bl-cyan   { border-left: 4px solid #0891b2 !important; }
.bl-rose   { border-left: 4px solid #e11d48 !important; }
/* mini badges in cards */
.mb-pill { display: inline-flex; align-items: center; gap: 4px;
  padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 800; }

/* ─── SECTION HEADER ─── */
.sh { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.sh-t { font-size: 13px; font-weight: 800; color: #1a237e;
  border-left: 3px solid #1a237e; padding-left: 9px;
  display: flex; align-items: center; gap: 7px; }

/* ─── CARD WRAPPER ─── */
.dcard { background: #fff; border-radius: 14px; border: 1px solid #e8edf5;
  box-shadow: 0 1px 4px rgba(0,0,0,.05); overflow: hidden; }
.dcard-hdr { padding: 12px 16px; border-bottom: 1px solid #edf2f7;
  display: flex; align-items: center; justify-content: space-between; }
.dcard-hdr-t { font-size: 13px; font-weight: 800;
  display: flex; align-items: center; gap: 7px; }

/* ─── TABLES ─── */
.dtbl { width: 100%; border-collapse: collapse; }
.dtbl th { font-size: 10.5px; font-weight: 700; color: #94a3b8;
  text-transform: uppercase; padding: 9px 13px;
  background: #f8fafc; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
.dtbl td { font-size: 12px; vertical-align: middle;
  padding: 9px 13px; border-bottom: 1px solid #f1f5f9; }
.dtbl tr:last-child td { border-bottom: none; }
.dtbl tr:hover td { background: #f8fafc; }

/* ─── QUICK LINKS GRID ─── */
.ql-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 8px; }
.ql { display: flex; align-items: center; gap: 9px; padding: 11px 13px;
  border-radius: 10px; border: 1.5px solid #e2e8f0; text-decoration: none;
  color: #374151; font-size: 12px; font-weight: 700; transition: .15s; background: #fff; }
.ql:hover { background: #f0f9ff; border-color: #0284c7; color: #0c4a6e;
  transform: translateX(2px); }
.ql i { font-size: 17px; width: 22px; text-align: center; flex-shrink: 0; }
.ql-span2 { grid-column: span 2; }
.ql-span3 { grid-column: span 3; }

/* ─── ALERT STRIP ─── */
.astrip { padding: 9px 14px; font-size: 12px; font-weight: 700;
  display: flex; align-items: center; gap: 8px; border-radius: 10px; margin-bottom: 8px; }

/* ─── EMPTY STATE ─── */
.empty-st { text-align: center; padding: 36px 16px; }
.empty-st .eico { font-size: 44px; margin-bottom: 8px; }
.empty-st .etxt { font-size: 13px; font-weight: 700; color: #16a34a; }
</style>

<div class="main-content app-content">
<div class="container-fluid" style="padding-bottom: 36px;">

<!-- ══════════ BANNER ══════════ -->
<div class="db-banner">
  <div>
    <h4>🚛 Sama Roadlines</h4>
    <p>
      <?= date('l, d F Y') ?>
      &nbsp;·&nbsp; Welcome back, <strong style="color:#fff;"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></strong>
    </p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="views/pages/TripForm.php?type=Regular" class="bq bq-white">
      <i class="ri-add-line"></i> New Regular Trip
    </a>
    <a href="views/pages/TripForm.php?type=Agent" class="bq bq-ghost">
      <i class="ri-add-line"></i> New Agent Trip
    </a>
    <a href="views/pages/RegularBill_generate.php" class="bq bq-ghost">
      <i class="ri-bill-line"></i> Generate Bill
    </a>
  </div>
</div>

<!-- ══════════ ROW 1 — MAIN MODULE CARDS ══════════ -->
<div class="row g-3" style="margin-bottom:20px;">

  <!-- TRIPS -->
  <div class="col-6 col-md-4 col-xl-2">
    <a href="views/pages/RegularTrips.php" style="text-decoration:none;">
    <div class="mc bl-blue">
      <div class="d-flex align-items-start justify-content-between mb-3">
        <div class="mc-ico" style="background:#dbeafe;">
          <i class="ri-truck-line" style="color:#2563eb;"></i>
        </div>
        <div class="text-end">
          <div style="font-size:10px;color:#94a3b8;font-weight:700;">THIS MONTH</div>
          <div style="font-size:13px;font-weight:900;color:#2563eb;">+<?= $stats['monthTrips'] ?></div>
        </div>
      </div>
      <div class="mc-num" style="color:#2563eb;"><?= $stats['totalTrips'] ?></div>
      <div class="mc-lbl">Total Trips</div>
      <div class="d-flex gap-1 flex-wrap mt-2">
        <span class="mb-pill" style="background:#dbeafe;color:#2563eb;"><?= $stats['regularCnt'] ?> Reg</span>
        <span class="mb-pill" style="background:#fef9c3;color:#b45309;"><?= $stats['agentCnt'] ?> Agent</span>
      </div>
      <div class="pgw"><div class="pgb" style="width:<?= $tripPct ?>%;background:#2563eb;"></div></div>
      <div style="font-size:9.5px;color:#94a3b8;margin-top:3px;"><?= $tripPct ?>% completed</div>
    </div>
    </a>
  </div>

  <!-- BILLS -->
  <div class="col-6 col-md-4 col-xl-2">
    <a href="views/pages/BillPayment_manage.php" style="text-decoration:none;">
    <div class="mc bl-green">
      <div class="d-flex align-items-start justify-content-between mb-3">
        <div class="mc-ico" style="background:#dcfce7;">
          <i class="ri-receipt-line" style="color:#16a34a;"></i>
        </div>
        <div class="text-end">
          <div style="font-size:10px;color:#94a3b8;font-weight:700;">RECEIVED</div>
          <div style="font-size:11px;font-weight:900;color:#16a34a;">₹<?= number_format($stats['receivedBillAmt'],0) ?></div>
        </div>
      </div>
      <div class="mc-num" style="color:#16a34a;font-size:16px;">₹<?= number_format($stats['totalBillAmt'],0) ?></div>
      <div class="mc-lbl">Total Billed</div>
      <div class="d-flex gap-1 flex-wrap mt-2">
        <span class="mb-pill" style="background:#fee2e2;color:#dc2626;"><?= $stats['billGenCnt'] ?> Unpaid</span>
        <span class="mb-pill" style="background:#fef9c3;color:#b45309;"><?= $stats['billPartCnt'] ?> Partial</span>
      </div>
      <div class="pgw"><div class="pgb" style="width:<?= $billPct ?>%;background:#16a34a;"></div></div>
      <div style="font-size:9.5px;color:#94a3b8;margin-top:3px;"><?= $billPct ?>% collected</div>
    </div>
    </a>
  </div>

  <!-- BILL PENDING -->
  <div class="col-6 col-md-4 col-xl-2">
    <a href="views/pages/BillPayment_manage.php" style="text-decoration:none;">
    <div class="mc bl-red">
      <div class="d-flex align-items-start justify-content-between mb-3">
        <div class="mc-ico" style="background:#fee2e2;">
          <i class="ri-time-line" style="color:#dc2626;"></i>
        </div>
        <div class="text-end">
          <div style="font-size:10px;color:#94a3b8;font-weight:700;">BILLS</div>
          <div style="font-size:13px;font-weight:900;color:#dc2626;"><?= $stats['regBillTotal'] ?></div>
        </div>
      </div>
      <div class="mc-num" style="color:#dc2626;font-size:16px;">₹<?= number_format($stats['pendingBillAmt'],0) ?></div>
      <div class="mc-lbl">Bill Pending</div>
      <div class="d-flex gap-1 flex-wrap mt-2">
        <span class="mb-pill" style="background:#fee2e2;color:#dc2626;"><?= $stats['billGenCnt'] ?> Generated</span>
        <span class="mb-pill" style="background:#fef9c3;color:#b45309;"><?= $stats['billPartCnt'] ?> Partial</span>
      </div>
      <div class="pgw"><div class="pgb" style="width:<?= $stats['regBillTotal']>0?min(100,round($stats['billPaidCnt']/$stats['regBillTotal']*100)):0 ?>%;background:#dc2626;"></div></div>
      <div style="font-size:9.5px;color:#94a3b8;margin-top:3px;"><?= $stats['billPaidCnt'] ?> bills fully paid</div>
    </div>
    </a>
  </div>

  <!-- OWNER PAYMENT -->
  <div class="col-6 col-md-4 col-xl-2">
    <a href="views/pages/OwnerPayment_manage.php" style="text-decoration:none;">
    <div class="mc bl-purple">
      <div class="d-flex align-items-start justify-content-between mb-3">
        <div class="mc-ico" style="background:#ede9fe;">
          <i class="ri-user-star-line" style="color:#7c3aed;"></i>
        </div>
        <div class="text-end">
          <div style="font-size:10px;color:#94a3b8;font-weight:700;">PAID</div>
          <div style="font-size:11px;font-weight:900;color:#7c3aed;">₹<?= number_format($stats['opTotalPaid'],0) ?></div>
        </div>
      </div>
      <div class="mc-num" style="color:#dc2626;font-size:16px;">₹<?= number_format($stats['opRemaining'],0) ?></div>
      <div class="mc-lbl">Owner Pay Due</div>
      <div class="d-flex gap-1 flex-wrap mt-2">
        <span class="mb-pill" style="background:#fee2e2;color:#dc2626;"><?= $stats['opUnpaid'] ?> Unpaid</span>
        <span class="mb-pill" style="background:#fef9c3;color:#b45309;"><?= $stats['opPartial'] ?> Part</span>
        <span class="mb-pill" style="background:#dcfce7;color:#16a34a;"><?= $stats['opPaid'] ?> Paid</span>
      </div>
      <div class="pgw"><div class="pgb" style="width:<?= $opPct ?>%;background:#7c3aed;"></div></div>
      <div style="font-size:9.5px;color:#94a3b8;margin-top:3px;"><?= $opPct ?>% paid to owners</div>
    </div>
    </a>
  </div>

  <!-- COMMISSION -->
  <div class="col-6 col-md-4 col-xl-2">
    <a href="views/pages/CommissionTrack.php" style="text-decoration:none;">
    <div class="mc bl-amber">
      <div class="d-flex align-items-start justify-content-between mb-3">
        <div class="mc-ico" style="background:#fef9c3;">
          <i class="ri-percent-line" style="color:#d97706;"></i>
        </div>
        <div class="text-end">
          <div style="font-size:10px;color:#94a3b8;font-weight:700;">RCVD</div>
          <div style="font-size:11px;font-weight:900;color:#16a34a;">₹<?= number_format($stats['receivedComm'],0) ?></div>
        </div>
      </div>
      <div class="mc-num" style="color:#d97706;font-size:16px;">₹<?= number_format($stats['totalComm'],0) ?></div>
      <div class="mc-lbl">Commission</div>
      <div class="d-flex gap-1 flex-wrap mt-2">
        <span class="mb-pill" style="background:#fee2e2;color:#dc2626;"><?= $stats['commPendCnt'] ?> Pending</span>
        <?php if($stats['ownerCommCnt']>0): ?>
        <span class="mb-pill" style="background:#ede9fe;color:#7c3aed;"><?= $stats['ownerCommCnt'] ?> Owner</span>
        <?php endif; ?>
      </div>
      <div class="pgw"><div class="pgb" style="width:<?= $commPct ?>%;background:#d97706;"></div></div>
      <div style="font-size:9.5px;color:#94a3b8;margin-top:3px;"><?= $commPct ?>% recovered</div>
    </div>
    </a>
  </div>

  <!-- ADVANCES -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="mc bl-cyan" style="cursor:default;">
      <div class="d-flex align-items-start justify-content-between mb-2">
        <div class="mc-ico" style="background:#cffafe;">
          <i class="ri-hand-coin-line" style="color:#0891b2;"></i>
        </div>
        <div class="text-end">
          <div style="font-size:10px;color:#94a3b8;font-weight:700;">OPEN</div>
          <div style="font-size:12px;font-weight:900;color:#dc2626;"><?= $stats['oaOpenCnt']+$stats['paOpenCnt'] ?></div>
        </div>
      </div>
      <div class="mc-lbl mb-1" style="font-weight:800;color:#374151;">Advances</div>
      <div style="font-size:11px;font-weight:700;color:#0891b2;margin-bottom:2px;">
        Owner: ₹<?= number_format($stats['oaRemaining'],0) ?> left
      </div>
      <div style="font-size:11px;font-weight:700;color:#7c3aed;">
        Party: ₹<?= number_format($stats['paRemaining'],0) ?> left
      </div>
      <div class="d-flex gap-2 mt-2">
        <a href="views/pages/OwnerAdvance_manage.php" style="font-size:10px;color:#0891b2;font-weight:700;text-decoration:none;">Owner →</a>
        <a href="views/pages/PartyAdvance.php" style="font-size:10px;color:#7c3aed;font-weight:700;text-decoration:none;">Party →</a>
      </div>
    </div>
  </div>

</div>

<!-- ══════════ ROW 2 — CHART + QUICK LINKS ══════════ -->
<div class="row g-3" style="margin-bottom:20px;">

  <!-- Chart -->
  <div class="col-xl-8 col-lg-7">
    <div class="dcard h-100">
      <div class="dcard-hdr">
        <div class="dcard-hdr-t" style="color:#1a237e;">
          <i class="ri-bar-chart-2-line" style="color:#2563eb;font-size:16px;"></i>
          Monthly Freight & Trips — Last 6 Months
        </div>
        <div class="d-flex gap-3" style="font-size:11px;color:#64748b;">
          <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#1a237e;margin-right:4px;"></span>Freight</span>
          <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#16a34a;margin-right:4px;"></span>Trips</span>
        </div>
      </div>
      <div style="padding:16px;">
        <canvas id="freightChart" height="130"></canvas>
      </div>
    </div>
  </div>

  <!-- Quick Links -->
  <div class="col-xl-4 col-lg-5">
    <div class="dcard h-100">
      <div class="dcard-hdr">
        <div class="dcard-hdr-t" style="color:#1a237e;">
          <i class="ri-flashlight-line" style="color:#f59e0b;font-size:16px;"></i> Quick Access
        </div>
      </div>
      <div style="padding:14px;">
        <div class="ql-grid">

          <a href="views/pages/TripForm.php?type=Regular" class="ql">
            <i class="ri-add-circle-line" style="color:#2563eb;"></i> Regular Trip
          </a>
          <a href="views/pages/TripForm.php?type=Agent" class="ql">
            <i class="ri-add-circle-line" style="color:#d97706;"></i> Agent Trip
          </a>

          <a href="views/pages/RegularTrips.php" class="ql">
            <i class="ri-list-check" style="color:#0891b2;"></i> All Trips
          </a>
          <a href="views/pages/AgentTrips.php" class="ql">
            <i class="ri-list-check" style="color:#d97706;"></i> Agent Trips
          </a>

          <a href="views/pages/RegularBill_generate.php" class="ql">
            <i class="ri-bill-line" style="color:#16a34a;"></i> Generate Bill
          </a>
          <a href="views/pages/BillPayment_manage.php" class="ql">
            <i class="ri-secure-payment-line" style="color:#dc2626;"></i> Bill Payment
          </a>

          <a href="views/pages/OwnerPayment_manage.php" class="ql">
            <i class="ri-user-star-line" style="color:#7c3aed;"></i> Owner Pay
          </a>
          <a href="views/pages/OwnerAdvance_manage.php" class="ql">
            <i class="ri-hand-coin-line" style="color:#0891b2;"></i> Owner Advance
          </a>

          <a href="views/pages/PartyAdvance.php" class="ql">
            <i class="ri-building-line" style="color:#7c3aed;"></i> Party Advance
          </a>
          <a href="views/pages/CommissionTrack.php" class="ql" style="position:relative;">
            <i class="ri-percent-line" style="color:#d97706;"></i> Commission
            <?php if($stats['commPendCnt']>0): ?>
            <span class="ms-auto" style="background:#dc2626;color:#fff;font-size:9px;font-weight:800;padding:1px 6px;border-radius:10px;"><?= $stats['commPendCnt'] ?></span>
            <?php endif; ?>
          </a>

          <a href="views/pages/AgentPayments.php" class="ql ql-span2">
            <i class="ri-bank-line" style="color:#0891b2;"></i> Agent Payments
          </a>

        </div>

        <!-- Alert strips for urgent items -->
        <?php if($stats['opRemaining'] > 0): ?>
        <div class="astrip mt-3" style="background:#ede9fe;color:#5b21b6;border-left:3px solid #7c3aed;">
          <i class="ri-user-star-line"></i>
          <span>₹<?= number_format($stats['opRemaining'],0) ?> owner payment due — <?= $stats['opUnpaid']  ?> unpaid trips</span>
        </div>
        <?php endif; ?>
        <?php if($stats['ownerComm'] > 0): ?>
        <div class="astrip" style="background:#fef3c7;color:#92400e;border-left:3px solid #d97706;">
          <i class="ri-percent-line"></i>
          <span>₹<?= number_format($stats['ownerComm'],0) ?> commission from owner pending</span>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </div>

</div>

<!-- ══════════ ROW 3 — RECENT TRIPS + UNPAID BILLS ══════════ -->
<div class="row g-3" style="margin-bottom:20px;">

  <!-- Recent Trips -->
  <div class="col-xl-7">
    <div class="dcard">
      <div class="dcard-hdr">
        <div class="dcard-hdr-t" style="color:#1a237e;">
          <i class="ri-map-pin-time-line" style="color:#2563eb;font-size:15px;"></i> Recent Trips
        </div>
        <div class="d-flex gap-1">
          <a href="views/pages/RegularTrips.php" class="btn btn-sm btn-outline-primary" style="font-size:11px;padding:3px 10px;">Regular</a>
          <a href="views/pages/AgentTrips.php"   class="btn btn-sm btn-outline-warning" style="font-size:11px;padding:3px 10px;">Agent</a>
        </div>
      </div>
      <div class="table-responsive">
        <table class="dtbl">
          <thead>
            <tr>
              <th>#</th><th>Date</th><th>Vehicle</th><th>Route</th>
              <th>Type</th><th>Freight</th><th>Status</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($recentTrips as $r):
            $tsc  = ['Open'=>'bg-primary','Billed'=>'bg-info text-dark','Completed'=>'bg-success'];
            $ts   = $tsc[$r['TripStatus']] ?? 'bg-secondary';
            $party = $r['TripType']==='Agent' ? ($r['AgentName']??'—') : ($r['ConsignerName']??'—');
          ?>
            <tr>
              <td class="fw-bold text-muted" style="font-size:11px;"><?= $r['TripId'] ?></td>
              <td style="white-space:nowrap;font-size:11px;"><?= date('d-m-Y',strtotime($r['TripDate'])) ?></td>
              <td>
                <span class="badge bg-secondary" style="font-size:10px;"><?= htmlspecialchars($r['VehicleNumber']??'—') ?></span>
              </td>
              <td style="font-size:11px;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                title="<?= htmlspecialchars($r['FromLocation'].' → '.$r['ToLocation']) ?>">
                <?= htmlspecialchars($r['FromLocation']) ?> → <?= htmlspecialchars($r['ToLocation']) ?>
              </td>
              <td>
                <span class="badge <?= $r['TripType']==='Agent'?'bg-warning text-dark':'bg-primary' ?>"
                  style="font-size:10px;"><?= $r['TripType'] ?></span>
                <?php if($r['FreightType']==='ToPay'): ?>
                <span class="badge" style="background:#7c3aed;font-size:9px;margin-left:2px;">Direct</span>
                <?php endif; ?>
              </td>
              <td class="fw-bold" style="color:#1a237e;font-size:12px;">₹<?= number_format($r['FreightAmount'],0) ?></td>
              <td><span class="badge <?= $ts ?>" style="font-size:10px;"><?= $r['TripStatus'] ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Unpaid Bills -->
  <div class="col-xl-5">
    <div class="dcard">
      <div class="dcard-hdr" style="background:#fff5f5;border-bottom-color:#fee2e2;">
        <div class="dcard-hdr-t" style="color:#dc2626;">
          <i class="ri-error-warning-line" style="font-size:15px;"></i> Bills Awaiting Payment
        </div>
        <a href="views/pages/BillPayment_manage.php"
          class="btn btn-sm btn-outline-danger" style="font-size:11px;padding:3px 10px;">Manage</a>
      </div>
      <?php if(empty($unpaidBills)): ?>
      <div class="empty-st">
        <div class="eico">✅</div>
        <div class="etxt">All bills collected!</div>
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="dtbl">
          <thead>
            <tr><th>Bill No.</th><th>Party</th><th>Net Amt</th><th>Remaining</th><th>Status</th></tr>
          </thead>
          <tbody>
          <?php foreach($unpaidBills as $b):
            $rem = floatval($b['NetBillAmount']) - floatval($b['PaidAmt']);
            $pct = floatval($b['NetBillAmount'])>0 ? min(100,round(floatval($b['PaidAmt'])/floatval($b['NetBillAmount'])*100)):0;
            $ss  = ['Generated'=>'bg-secondary','PartiallyPaid'=>'bg-warning text-dark'][$b['BillStatus']]??'bg-secondary';
          ?>
            <tr>
              <td class="fw-bold" style="color:#2563eb;font-size:12px;"><?= htmlspecialchars($b['BillNo']) ?></td>
              <td style="font-size:11px;max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                title="<?= htmlspecialchars($b['PartyName']) ?>"><?= htmlspecialchars($b['PartyName']) ?></td>
              <td class="fw-semibold" style="font-size:12px;">₹<?= number_format(floatval($b['NetBillAmount']),0) ?></td>
              <td>
                <div style="height:4px;background:#e2e8f0;border-radius:3px;min-width:60px;margin-bottom:3px;">
                  <div style="height:100%;width:<?= $pct ?>%;background:#16a34a;border-radius:3px;"></div>
                </div>
                <div style="font-size:10.5px;font-weight:800;color:#dc2626;">₹<?= number_format($rem,0) ?></div>
              </td>
              <td><span class="badge <?= $ss ?>" style="font-size:10px;"><?= $b['BillStatus'] ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- ══════════ ROW 4 — OWNER PENDING + COMMISSION ══════════ -->
<div class="row g-3">

  <!-- Owner Payment Pending -->
  <div class="col-xl-6">
    <div class="dcard">
      <div class="dcard-hdr" style="background:#eff6ff;border-bottom-color:#bfdbfe;">
        <div class="dcard-hdr-t" style="color:#1d4ed8;">
          <i class="ri-user-star-line" style="font-size:15px;"></i> Owner Payment Pending
          <?php if($stats['opUnpaid']+$stats['opPartial']>0): ?>
          <span class="badge bg-danger" style="font-size:10px;margin-left:4px;"><?= $stats['opUnpaid']+$stats['opPartial'] ?></span>
          <?php endif; ?>
        </div>
        <a href="views/pages/OwnerPayment_manage.php"
          class="btn btn-sm btn-outline-primary" style="font-size:11px;padding:3px 10px;">Manage</a>
      </div>
      <?php if(empty($ownerPend)): ?>
      <div class="empty-st">
        <div class="eico">✅</div>
        <div class="etxt">All owners paid!</div>
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="dtbl">
          <thead>
            <tr><th>Trip</th><th>Date</th><th>Vehicle · Owner</th><th>Net Pay</th><th>Remaining</th><th>Status</th></tr>
          </thead>
          <tbody>
          <?php foreach($ownerPend as $op2):
            $rem2 = max(0, floatval($op2['NetPayable']) - floatval($op2['TotalPaid']));
            $pct2 = floatval($op2['NetPayable'])>0 ? min(100,round(floatval($op2['TotalPaid'])/floatval($op2['NetPayable'])*100)):0;
            $sc = ['Unpaid'=>'bg-danger','PartiallyPaid'=>'bg-warning text-dark'][$op2['OwnerPaymentStatus']]??'bg-secondary';
          ?>
            <tr>
              <td class="fw-bold text-muted" style="font-size:11px;">#<?= $op2['TripId'] ?></td>
              <td style="white-space:nowrap;font-size:11px;"><?= date('d-m-Y',strtotime($op2['TripDate'])) ?></td>
              <td>
                <div style="font-size:11px;font-weight:700;"><?= htmlspecialchars($op2['VehicleNumber']??'—') ?></div>
                <div style="font-size:10px;color:#7c3aed;font-weight:600;"><?= htmlspecialchars($op2['OwnerName']??'—') ?></div>
              </td>
              <td class="fw-semibold" style="font-size:11px;">₹<?= number_format(floatval($op2['NetPayable']),0) ?></td>
              <td>
                <div style="height:4px;background:#e2e8f0;border-radius:3px;min-width:55px;margin-bottom:3px;">
                  <div style="height:100%;width:<?= $pct2 ?>%;background:#7c3aed;border-radius:3px;"></div>
                </div>
                <div style="font-size:10.5px;font-weight:800;color:#dc2626;">₹<?= number_format($rem2,0) ?></div>
              </td>
              <td><span class="badge <?= $sc ?>" style="font-size:10px;"><?= $op2['OwnerPaymentStatus'] ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Commission Pending -->
  <div class="col-xl-6">
    <div class="dcard">
      <div class="dcard-hdr" style="background:#fffbeb;border-bottom-color:#fde68a;">
        <div class="dcard-hdr-t" style="color:#b45309;">
          <i class="ri-percent-line" style="font-size:15px;"></i> Commission Pending
          <?php if($stats['commPendCnt']>0): ?>
          <span class="badge bg-danger" style="font-size:10px;margin-left:4px;"><?= $stats['commPendCnt'] ?></span>
          <?php endif; ?>
        </div>
        <div class="d-flex gap-2 align-items-center">
          <?php if($stats['ownerComm']>0): ?>
          <span class="badge" style="background:#7c3aed;font-size:10px;">
            <i class="ri-home-line"></i> Owner ₹<?= number_format($stats['ownerComm'],0) ?>
          </span>
          <?php endif; ?>
          <a href="views/pages/CommissionTrack.php"
            class="btn btn-sm btn-outline-warning" style="font-size:11px;padding:3px 10px;">View All</a>
        </div>
      </div>

      <?php if($stats['ownerComm']>0): ?>
      <div class="astrip" style="background:#faf5ff;color:#5b21b6;border-left:4px solid #7c3aed;border-radius:0;margin:0;">
        <i class="ri-home-line"></i>
        ₹<?= number_format($stats['ownerComm'],0) ?> owner commission pending — manually recover.
        <a href="views/pages/CommissionTrack.php" style="color:#5b21b6;margin-left:6px;text-decoration:underline;">Go →</a>
      </div>
      <?php endif; ?>

      <?php if(empty($commPending)): ?>
      <div class="empty-st">
        <div class="eico">✅</div>
        <div class="etxt">All party commissions received!</div>
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="dtbl">
          <thead>
            <tr><th>Trip</th><th>Date</th><th>Route</th><th>Party</th><th>Commission</th></tr>
          </thead>
          <tbody>
          <?php foreach($commPending as $c): ?>
            <tr>
              <td class="fw-bold text-muted" style="font-size:11px;">#<?= $c['TripId'] ?></td>
              <td style="white-space:nowrap;font-size:11px;"><?= date('d-m-Y',strtotime($c['TripDate'])) ?></td>
              <td style="font-size:11px;max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?= htmlspecialchars($c['FromLocation']) ?> → <?= htmlspecialchars($c['ToLocation']) ?>
              </td>
              <td style="font-size:11px;"><?= htmlspecialchars($c['ConsignerName']??'—') ?></td>
              <td class="fw-bold" style="color:#d97706;font-size:13px;">₹<?= number_format($c['CommissionAmount'],0) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>
<!-- end container -->
</div></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
(function(){
  var ctx = document.getElementById('freightChart');
  if(!ctx) return;
  new Chart(ctx, {
    data: {
      labels: <?= $chartLabels ?>,
      datasets: [
        {
          type: 'bar',
          label: 'Freight (₹)',
          data: <?= $chartFreight ?>,
          backgroundColor: 'rgba(26,35,126,0.78)',
          borderColor: '#1a237e',
          borderWidth: 1,
          borderRadius: 7,
          borderSkipped: false,
          yAxisID: 'yFreight'
        },
        {
          type: 'line',
          label: 'Trips',
          data: <?= $chartTrips ?>,
          borderColor: '#16a34a',
          backgroundColor: 'rgba(22,163,74,0.1)',
          borderWidth: 2.5,
          pointBackgroundColor: '#16a34a',
          pointRadius: 5,
          pointHoverRadius: 7,
          tension: 0.4,
          fill: true,
          yAxisID: 'yTrips'
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function(ctx) {
              if(ctx.datasetIndex === 0)
                return '  Freight: ₹' + Number(ctx.raw).toLocaleString('en-IN');
              return '  Trips: ' + ctx.raw;
            }
          }
        }
      },
      scales: {
        yFreight: {
          type: 'linear', position: 'left', beginAtZero: true,
          ticks: {
            callback: function(v){ return '₹'+Number(v).toLocaleString('en-IN'); },
            font: { size: 10 }
          },
          grid: { color: 'rgba(0,0,0,0.05)' }
        },
        yTrips: {
          type: 'linear', position: 'right', beginAtZero: true,
          ticks: { stepSize: 1, font: { size: 10 } },
          grid: { display: false }
        },
        x: {
          grid: { display: false },
          ticks: { font: { size: 11 } }
        }
      }
    }
  });
})();
</script>
<?php require_once "views/layout/footer.php"; ?>
