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
    echo json_encode(OwnerPayment::getByTrip(intval($_GET['TripId']))); exit();
}
if (isset($_POST['addPayment'])) {
    header('Content-Type: application/json');
    echo json_encode(OwnerPayment::addPayment(intval($_POST['TripId']), intval($_POST['OwnerId']), $_POST)); exit();
}
if (isset($_POST['deletePayment'])) {
    header('Content-Type: application/json');
    echo json_encode(OwnerPayment::deletePayment(intval($_POST['PaymentId']))); exit();
}

$filterOwner   = !empty($_GET['ownerId']) ? intval($_GET['ownerId']) : null;
$trips         = OwnerPayment::getAllTripsWithPaymentStatus($filterOwner);
$ownerSummary  = OwnerPayment::getOwnerSummary();
$owners        = OwnerPayment::getOwners();

$totalFreight    = array_sum(array_column($trips, 'FreightAmount'));
$totalCharges    = array_sum(array_column($trips, 'TotalCharges'));
$totalTDS        = array_sum(array_column($trips, 'TDS'));
$totalCommission = array_sum(array_column($trips, 'Commission'));
$totalPayable    = array_sum(array_column($trips, 'NetPayable'));
$totalPaid       = array_sum(array_column($trips, 'TotalPaid'));
$totalRemaining  = array_sum(array_column($trips, 'Remaining'));
$unpaidCount     = count(array_filter($trips, fn($t) => $t['OwnerPaymentStatus'] !== 'Paid'));
$commRecvCount   = count(array_filter($trips, fn($t) => ($t['CommissionStatus'] ?? '') === 'Received'));

require_once "../layout/header.php";
require_once "../layout/sidebar.php";
?>
<style>
.ow-hdr{background:linear-gradient(135deg,#1a237e,#1565c0 60%,#1976d2);
  border-radius:14px;padding:20px 26px;margin-bottom:20px;
  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
.ow-hdr h4{color:#fff;font-weight:800;font-size:19px;margin:0;display:flex;align-items:center;gap:9px;}
.ow-hdr p{color:rgba(255,255,255,.65);font-size:12px;margin:3px 0 0;}

.srow{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;}
.spill{background:#fff;border:1px solid #e2e8f0;border-radius:11px;padding:11px 16px;
  display:flex;align-items:center;gap:11px;flex:1;min-width:120px;box-shadow:0 1px 3px rgba(0,0,0,.04);}
.sico{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;}
.snum{font-size:15px;font-weight:900;line-height:1.1;}
.slbl{font-size:11px;color:#64748b;margin-top:1px;}

/* formula tag */
.ftag{display:inline-flex;align-items:center;gap:3px;font-size:11px;font-weight:700;
  padding:3px 9px;border-radius:20px;white-space:nowrap;}
.ft-g{background:#f1f5f9;color:#475569;}
.ft-add{background:#dcfce7;color:#15803d;}
.ft-sub{background:#fee2e2;color:#dc2626;}
.ft-net{background:#dbeafe;color:#1d4ed8;}

/* table */
th.tw{background:#1a237e!important;color:#fff!important;font-size:12px;font-weight:700;
  padding:10px 12px;white-space:nowrap;border:none!important;}
.tr-paid    td{background:#f0fdf4!important;}
.tr-partial td{background:#fffbeb!important;}
.tr-unpaid  td{background:#fff5f5!important;}
.par td{padding:10px 12px;vertical-align:middle;border-bottom:1px solid #f1f5f9;font-size:13px;}
.par:hover td{filter:brightness(.97);}

/* calc breakdown inside table cell */
.calc{font-size:11px;line-height:1.9;min-width:170px;}
.calc-r{display:flex;justify-content:space-between;gap:12px;}
.calc-r.sub span:last-child{color:#dc2626;}
.calc-r.add span:last-child{color:#15803d;}
.calc-r.ttl{border-top:1px solid #ddd;margin-top:3px;padding-top:3px;font-weight:800;}
.calc-r.ttl span:last-child{color:#1d4ed8;}

/* badges */
.bs-paid   {background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;}
.bs-part   {background:#fef9c3;color:#b45309;border:1px solid #fde68a;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;}
.bs-unpaid {background:#fee2e2;color:#dc2626;border:1px solid #fecaca;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;}
.cm-recv   {background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;padding:2px 7px;border-radius:20px;font-size:10px;font-weight:700;display:inline-flex;align-items:center;gap:3px;}
.cm-pend   {background:#fef3c7;color:#92400e;border:1px solid #fcd34d;padding:2px 7px;border-radius:20px;font-size:10px;font-weight:700;}

.owner-chip{font-size:11px;background:#ede9fe;color:#5b21b6;border-radius:20px;padding:2px 9px;}
.pgw{height:6px;background:#e2e8f0;border-radius:4px;overflow:hidden;margin-top:4px;}
.pgb{height:100%;border-radius:4px;}
.ic-btn{width:32px;height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:8px;font-size:14px;border:1px solid;}
.fbar{background:#f0f4ff;padding:12px 16px;border-bottom:1px solid #e2e8f0;}
.si-wrap{position:relative;}
.si-wrap i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:14px;}
.si-wrap input{padding-left:32px;}
.sum-tbl th{background:#f8fafc;font-size:12px;font-weight:700;padding:8px 12px;}
.sum-tbl td{font-size:12px;padding:8px 12px;vertical-align:middle;}

/* pay modal */
.pm-row{display:flex;justify-content:space-between;align-items:center;
  padding:6px 0;font-size:13px;border-bottom:1px solid #f1f5f9;}
.pm-row:last-child{border:none;}
.pm-row.net{font-weight:800;font-size:15px;border-top:2px solid #e2e8f0;margin-top:4px;padding-top:8px;}
.comm-badge{background:#fef3c7;color:#92400e;border:1px solid #fcd34d;
  font-size:10px;font-weight:700;padding:1px 7px;border-radius:20px;}

/* commission auto-received alert */
.comm-alert{background:#d1fae5;border:1px solid #6ee7b7;border-radius:10px;
  padding:10px 14px;font-size:13px;color:#065f46;display:none;margin-bottom:12px;}
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
    <select id="ownerFilter" class="form-select form-select-sm" style="min-width:190px;border-radius:8px;"
      onchange="window.location.href=this.value?'?ownerId='+this.value:'?'">
      <option value="">All Owners</option>
      <?php foreach ($owners as $o): ?>
      <option value="<?= $o['VehicleOwnerId'] ?>" <?= $filterOwner == $o['VehicleOwnerId'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($o['OwnerName']) ?><?= $o['City'] ? ' — '.$o['City'] : '' ?>
      </option>
      <?php endforeach; ?>
    </select>
    <a href="OwnerPayment_manage.php" class="btn btn-sm btn-light fw-bold"><i class="ri-refresh-line"></i></a>
  </div>
</div>

<!-- FORMULA NOTE -->
<div class="rounded p-2 mb-3 fs-12 d-flex align-items-center flex-wrap gap-2"
  style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;">
  <i class="ri-calculator-line"></i><strong>Net Payable Formula:</strong>
  <span class="ftag ft-g">Freight</span>
  <span class="fw-bold">+</span>
  <span class="ftag ft-add">+ Charges</span>
  <span class="fw-bold">+</span>
  <span class="ftag ft-add">+ TDS</span>
  <span class="fw-bold">−</span>
  <span class="ftag ft-sub">− Commission</span>
  <span class="fw-bold">=</span>
  <span class="ftag ft-net">Net Payable to Owner</span>
  <span class="ms-2" style="color:#065f46;font-weight:700;">
    <i class="ri-check-double-line"></i> Commission auto-received when fully paid
  </span>
</div>

<!-- STATS -->
<div class="srow">
  <div class="spill">
    <div class="sico" style="background:#dbeafe;"><i class="ri-truck-line" style="color:#1d4ed8;"></i></div>
    <div>
      <div class="snum" style="color:#1d4ed8;"><?= count($trips) ?></div>
      <div class="slbl">Total Trips</div>
      <div style="font-size:10px;color:#dc2626;"><?= $unpaidCount ?> pending</div>
    </div>
  </div>
  <div class="spill">
    <div class="sico" style="background:#f3e8ff;"><i class="ri-money-cny-circle-line" style="color:#7c3aed;"></i></div>
    <div>
      <div class="snum" style="font-size:13px;color:#7c3aed;">₹<?= number_format($totalFreight, 0) ?></div>
      <div class="slbl">Total Freight</div>
      <?php if ($totalCharges > 0): ?>
      <div style="font-size:10px;color:#0284c7;">+ ₹<?= number_format($totalCharges, 0) ?> charges</div>
      <?php endif; ?>
    </div>
  </div>
  <div class="spill">
    <div class="sico" style="background:#fef3c7;"><i class="ri-scissors-cut-line" style="color:#d97706;"></i></div>
    <div>
      <div class="snum" style="font-size:13px;color:#d97706;">₹<?= number_format($totalCommission, 0) ?></div>
      <div class="slbl">Total Commission</div>
      <div style="font-size:10px;color:#15803d;"><?= $commRecvCount ?> trips received</div>
    </div>
  </div>
  <div class="spill" style="border-left:4px solid #1d4ed8;">
    <div class="sico" style="background:#dbeafe;"><i class="ri-wallet-3-line" style="color:#1d4ed8;"></i></div>
    <div>
      <div class="snum" style="font-size:13px;color:#1d4ed8;">₹<?= number_format($totalPayable, 0) ?></div>
      <div class="slbl">Net Payable (after commission)</div>
    </div>
  </div>
  <div class="spill" style="border-left:4px solid #15803d;">
    <div class="sico" style="background:#dcfce7;"><i class="ri-checkbox-circle-line" style="color:#15803d;"></i></div>
    <div>
      <div class="snum" style="font-size:13px;color:#15803d;">₹<?= number_format($totalPaid, 0) ?></div>
      <div class="slbl">Total Paid</div>
      <?php $gpct = $totalPayable > 0 ? min(100, round($totalPaid / $totalPayable * 100)) : 0; ?>
      <div class="pgw" style="min-width:80px;"><div class="pgb" style="width:<?= $gpct ?>%;background:#15803d;"></div></div>
    </div>
  </div>
  <div class="spill" style="border-left:4px solid #dc2626;">
    <div class="sico" style="background:#fee2e2;"><i class="ri-time-line" style="color:#dc2626;"></i></div>
    <div>
      <div class="snum" style="font-size:13px;color:#dc2626;">₹<?= number_format($totalRemaining, 0) ?></div>
      <div class="slbl">Still Due</div>
    </div>
  </div>
</div>

<!-- OWNER SUMMARY -->
<div class="card shadow-sm mb-4" style="border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">
  <div class="card-header d-flex align-items-center justify-content-between py-2" style="background:#f8fafc;"
    role="button" data-bs-toggle="collapse" data-bs-target="#ownerCollapse">
    <span class="fw-bold fs-13"><i class="ri-user-star-line me-2"></i>Owner-wise Summary</span>
    <i class="ri-arrow-down-s-line"></i>
  </div>
  <div class="collapse show" id="ownerCollapse">
    <div class="table-responsive">
      <table class="table mb-0 sum-tbl">
        <thead>
          <tr>
            <th>Owner</th><th>Trips</th>
            <th class="text-end">Net Payable</th>
            <th class="text-end">Paid</th>
            <th class="text-end">Remaining</th>
            <th class="text-end">Commission Recv'd</th>
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
              <?= $os['City'] ? '<br><small class="text-muted">'.htmlspecialchars($os['City']).'</small>' : '' ?>
              <?= !empty($os['MobileNo']) ? '<br><small class="text-muted"><i class="ri-smartphone-line"></i> '.$os['MobileNo'].'</small>' : '' ?>
            </td>
            <td><span class="badge bg-secondary"><?= $os['TotalTrips'] ?></span></td>
            <td class="text-end fw-semibold">₹<?= number_format($os['TotalPayable'], 0) ?></td>
            <td class="text-end text-success fw-semibold">₹<?= number_format($os['TotalPaid'], 0) ?></td>
            <td class="text-end fw-bold <?= $rem > 0 ? 'text-danger' : 'text-success' ?>">
              ₹<?= number_format($rem, 0) ?>
              <div class="pgw"><div class="pgb" style="width:<?= $pct ?>%;background:<?= $pct>=100?'#15803d':'#0284c7' ?>;"></div></div>
            </td>
            <td class="text-end">
              <?php if (floatval($os['CommissionReceived'] ?? 0) > 0): ?>
              <span class="cm-recv"><i class="ri-check-double-line"></i> ₹<?= number_format($os['CommissionReceived'], 0) ?></span>
              <?php else: ?>
              <span class="text-muted fs-12">—</span>
              <?php endif; ?>
            </td>
            <td>
              <a href="?ownerId=<?= $os['VehicleOwnerId'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2 fs-11">
                <i class="ri-filter-line"></i> Filter
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- TRIPS TABLE -->
<div class="card shadow-sm" style="border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">
  <div class="fbar">
    <div class="row g-2 align-items-center">
      <div class="col-md-4">
        <div class="si-wrap">
          <i class="ri-search-line"></i>
          <input type="text" id="srInput" class="form-control form-control-sm"
            placeholder="Search vehicle, owner, route..." oninput="doFilter()">
        </div>
      </div>
      <div class="col-auto">
        <div class="btn-group btn-group-sm">
          <button class="btn btn-outline-secondary active" id="fb-all"    onclick="setF('all')">All</button>
          <button class="btn btn-outline-danger"           id="fb-Unpaid" onclick="setF('Unpaid')">Unpaid</button>
          <button class="btn btn-outline-warning"          id="fb-Partial" onclick="setF('PartiallyPaid')">Partial</button>
          <button class="btn btn-outline-success"          id="fb-Paid"   onclick="setF('Paid')">Paid</button>
        </div>
      </div>
      <div class="col-auto ms-auto">
        <span id="fc" class="fw-bold text-white rounded px-2 py-1"
          style="background:#1d4ed8;font-size:11px;"><?= count($trips) ?>/<?= count($trips) ?></span>
      </div>
    </div>
  </div>

  <div class="table-responsive">
  <table class="table mb-0 w-100">
    <thead>
      <tr>
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
      </tr>
    </thead>
    <tbody id="tripBody">
    <?php foreach ($trips as $i => $t):
      $rowCls  = match($t['OwnerPaymentStatus']) {
        'Paid'          => 'par tr-paid',
        'PartiallyPaid' => 'par tr-partial',
        default         => 'par tr-unpaid'
      };
      $stBadge = match($t['OwnerPaymentStatus']) {
        'Paid'          => '<span class="bs-paid">✓ Paid</span>',
        'PartiallyPaid' => '<span class="bs-part">Partial</span>',
        default         => '<span class="bs-unpaid">Unpaid</span>'
      };
      $pct   = $t['NetPayable'] > 0 ? min(100, round($t['TotalPaid'] / $t['NetPayable'] * 100)) : 0;
      $party = $t['TripType'] === 'Agent' ? ($t['AgentName'] ?? '—') : ($t['ConsignerName'] ?? '—');
      $cmRecv = ($t['CommissionStatus'] ?? '') === 'Received';
    ?>
      <tr class="<?= $rowCls ?>"
        data-status="<?= $t['OwnerPaymentStatus'] ?>"
        data-search="<?= strtolower(($t['VehicleNumber']??'').' '.($t['OwnerName']??'').' '.($t['FromLocation']??'').' '.($t['ToLocation']??'')) ?>">
        <td class="text-muted fw-medium"><?= $i+1 ?></td>
        <td style="white-space:nowrap;font-size:12px;"><?= date('d-m-Y', strtotime($t['TripDate'])) ?></td>
        <td>
          <div class="fw-bold" style="font-size:12px;"><i class="ri-truck-line text-muted me-1"></i><?= htmlspecialchars($t['VehicleNumber'] ?? '—') ?></div>
          <span class="owner-chip"><?= htmlspecialchars($t['OwnerName'] ?? '—') ?></span>
          <?php if (!empty($t['MobileNo'])): ?>
          <div style="font-size:10px;color:#94a3b8;margin-top:2px;"><i class="ri-smartphone-line"></i> <?= $t['MobileNo'] ?></div>
          <?php endif; ?>
        </td>
        <td style="font-size:11px;max-width:130px;">
          <div><?= htmlspecialchars($t['FromLocation']) ?> → <?= htmlspecialchars($t['ToLocation']) ?></div>
          <div class="text-muted"><?= htmlspecialchars($party) ?></div>
        </td>
        <td>
          <span class="badge <?= $t['TripType']==='Agent'?'bg-warning text-dark':'bg-primary' ?>">
            <?= $t['TripType'] ?>
          </span>
        </td>
        <td>
          <div class="calc">
            <div class="calc-r">
              <span class="text-muted">Freight</span>
              <span class="fw-semibold">₹<?= number_format($t['FreightAmount'], 0) ?></span>
            </div>
            <?php if ($t['LabourCharge'] > 0): ?>
            <div class="calc-r add"><span class="text-muted">+ Labour</span><span>₹<?= number_format($t['LabourCharge'], 0) ?></span></div>
            <?php endif; ?>
            <?php if ($t['HoldingCharge'] > 0): ?>
            <div class="calc-r add"><span class="text-muted">+ Holding</span><span>₹<?= number_format($t['HoldingCharge'], 0) ?></span></div>
            <?php endif; ?>
            <?php if ($t['OtherCharge'] > 0): ?>
            <div class="calc-r add"><span class="text-muted">+ Other</span><span>₹<?= number_format($t['OtherCharge'], 0) ?></span></div>
            <?php endif; ?>
            <?php if ($t['TDS'] > 0): ?>
            <div class="calc-r add"><span class="text-muted">+ TDS</span><span>₹<?= number_format($t['TDS'], 0) ?></span></div>
            <?php endif; ?>
            <?php if ($t['Commission'] > 0): ?>
            <div class="calc-r sub"><span class="text-muted">− Commission</span><span>₹<?= number_format($t['Commission'], 0) ?></span></div>
            <?php endif; ?>
            <div class="calc-r ttl">
              <span>Net Payable</span>
              <span>₹<?= number_format($t['NetPayable'], 0) ?></span>
            </div>
          </div>
        </td>
        <td style="min-width:110px;">
          <div class="d-flex justify-content-between" style="font-size:11px;">
            <span class="text-success fw-semibold">₹<?= number_format($t['TotalPaid'], 0) ?></span>
            <span class="text-muted"><?= $pct ?>%</span>
          </div>
          <div class="pgw"><div class="pgb" style="width:<?= $pct ?>%;background:<?= $pct>=100?'#15803d':'#0284c7' ?>;"></div></div>
          <div style="font-size:10px;color:#94a3b8;margin-top:2px;"><?= $t['PaymentCount'] ?> payment(s)</div>
        </td>
        <td class="text-end fw-bold" style="color:<?= $t['Remaining']>0?'#dc2626':'#15803d' ?>;font-size:14px;">
          ₹<?= number_format($t['Remaining'], 0) ?>
        </td>
        <td><?= $stBadge ?></td>
        <td>
          <?php if ($t['Commission'] > 0): ?>
            <?php if ($cmRecv): ?>
            <span class="cm-recv"><i class="ri-check-double-line"></i> Received<br><small>₹<?= number_format($t['Commission'], 0) ?></small></span>
            <?php else: ?>
            <span class="cm-pend">Pending<br><small>₹<?= number_format($t['Commission'], 0) ?></small></span>
            <?php endif; ?>
          <?php else: ?>
          <span class="text-muted fs-12">—</span>
          <?php endif; ?>
        </td>
        <td>
          <div class="d-flex gap-1 justify-content-center">
            <?php if ($t['OwnerPaymentStatus'] !== 'Paid'): ?>
            <button class="ic-btn btn-outline-success text-success" title="Add Payment"
              onclick="openPay(
                <?= $t['TripId'] ?>,<?= $t['VehicleOwnerId'] ?>,
                '<?= addslashes($t['VehicleNumber']??'—') ?>',
                '<?= addslashes($t['OwnerName']??'—') ?>',
                <?= $t['FreightAmount'] ?>,
                <?= $t['LabourCharge'] ?>,
                <?= $t['HoldingCharge'] ?>,
                <?= $t['OtherCharge'] ?>,
                <?= $t['TDS'] ?>,
                <?= $t['Commission'] ?>,
                <?= $t['NetPayable'] ?>,
                <?= $t['TotalPaid'] ?>
              )">
              <i class="ri-money-dollar-circle-line"></i>
            </button>
            <?php endif; ?>
            <button class="ic-btn btn-outline-info text-info" title="Payment History"
              onclick="viewHist(<?= $t['TripId'] ?>,'<?= addslashes($t['VehicleNumber']??'—') ?>','<?= addslashes($t['OwnerName']??'') ?>')">
              <i class="ri-history-line"></i>
            </button>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

</div></div>

<!-- PAY MODAL -->
<div class="modal fade" id="payModal" tabindex="-1">
<div class="modal-dialog"><div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;">
  <div class="modal-header text-white py-3" style="background:linear-gradient(135deg,#15803d,#16a34a);">
    <h5 class="modal-title fw-bold"><i class="ri-money-dollar-circle-line me-2"></i>Add Owner Payment</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body">
    <!-- Commission auto-receive notice -->
    <div class="comm-alert" id="commAlert">
      <i class="ri-check-double-line me-2"></i>
      <strong>Commission will be auto-marked as Received</strong> when this trip is fully paid.
      Commission amount: <strong id="ca_amt">₹0</strong>
    </div>

    <!-- Party info strip -->
    <div class="rounded p-2 mb-3 d-flex align-items-center justify-content-between"
      style="background:#f0fdf4;border:1px solid #bbf7d0;">
      <div>
        <div class="fw-bold fs-13" id="pm_vehicle"></div>
        <span class="owner-chip" style="margin-top:4px;" id="pm_owner"></span>
      </div>
      <div class="text-end">
        <div style="font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;">Balance Due</div>
        <div class="fw-bold" style="font-size:22px;color:#dc2626;" id="pm_due">₹0</div>
      </div>
    </div>

    <!-- Breakdown -->
    <div class="rounded p-3 mb-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
      <div class="fs-11 fw-bold text-muted text-uppercase mb-2">Payment Calculation</div>
      <div class="pm-row"><span class="text-muted">Freight Amount</span><span class="fw-semibold" id="pm_freight">₹0</span></div>
      <div class="pm-row" id="r_labour"  style="display:none;"><span class="text-muted">+ Labour Charge</span>  <span class="text-success" id="pm_labour">₹0</span></div>
      <div class="pm-row" id="r_holding" style="display:none;"><span class="text-muted">+ Holding Charge</span> <span class="text-success" id="pm_holding">₹0</span></div>
      <div class="pm-row" id="r_other"   style="display:none;"><span class="text-muted">+ Other Charge</span>   <span class="text-success" id="pm_other">₹0</span></div>
      <div class="pm-row" id="r_tds"     style="display:none;"><span class="text-muted">+ TDS</span>             <span class="text-success" id="pm_tds">₹0</span></div>
      <div class="pm-row" id="r_comm">
        <span class="d-flex align-items-center gap-2">
          <span class="text-muted">− Commission</span>
          <span class="comm-badge">Our Earning</span>
        </span>
        <span class="text-danger" id="pm_comm">₹0</span>
      </div>
      <div class="pm-row net"><span>Net Payable to Owner</span><span class="text-primary" id="pm_net">₹0</span></div>
      <div class="mt-2" style="font-size:12px;">
        <div class="d-flex justify-content-between text-muted">
          <span>Already Paid:</span><span class="fw-semibold text-success" id="pm_paid">₹0</span>
        </div>
        <div class="pgw mt-1"><div class="pgb" id="pm_prog" style="width:0%;background:#15803d;"></div></div>
      </div>
    </div>

    <input type="hidden" id="pay_TripId">
    <input type="hidden" id="pay_OwnerId">

    <div class="row g-3">
      <div class="col-6">
        <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
        <input type="date" id="pay_Date" class="form-control" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="col-6">
        <label class="form-label fw-semibold">Amount (₹) <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text fw-bold bg-light">₹</span>
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
    <button class="btn btn-success fw-bold px-4" onclick="submitPay()">
      <i class="ri-save-3-line me-1"></i>Save Payment
    </button>
  </div>
</div></div></div>

<!-- HISTORY MODAL -->
<div class="modal fade" id="histModal" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;">
  <div class="modal-header text-white py-3" style="background:linear-gradient(135deg,#0369a1,#0284c7);">
    <h5 class="modal-title fw-bold"><i class="ri-history-line me-2"></i>Payment History — <span id="hist_lbl"></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body p-0">
    <table class="table table-bordered table-sm mb-0 fs-13">
      <thead class="table-light">
        <tr><th>#</th><th>Date</th><th>Mode</th><th>Reference</th><th class="text-end">Amount</th><th>Remarks</th><th class="text-center">Del</th></tr>
      </thead>
      <tbody id="histBody"></tbody>
      <tfoot>
        <tr class="table-success fw-bold">
          <td colspan="4" class="text-end">Total Paid:</td>
          <td id="histTotal">₹0</td><td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <div class="modal-footer py-2">
    <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
  </div>
</div></div></div>

<script>
var _curF = 'all';
function setF(s){
  _curF = s;
  document.querySelectorAll('[id^="fb-"]').forEach(function(b){ b.classList.remove('active'); });
  document.getElementById('fb-'+s).classList.add('active');
  doFilter();
}
function doFilter(){
  var q = document.getElementById('srInput').value.toLowerCase();
  var rows = document.querySelectorAll('#tripBody tr');
  var vis = 0;
  rows.forEach(function(r){
    var stOk = _curF === 'all' || (r.dataset.status||'') === _curF;
    var qOk  = !q || (r.dataset.search||'').includes(q);
    r.style.display = (stOk && qOk) ? '' : 'none';
    if(stOk && qOk) vis++;
  });
  document.getElementById('fc').textContent = vis + '/' + rows.length;
}

function openPay(tripId, ownerId, vehicle, owner, freight, labour, holding, other, tds, commission, net, paid){
  var rem = Math.max(0, net - paid);
  var pct = net > 0 ? Math.min(100, Math.round(paid / net * 100)) : 0;

  $('#pay_TripId').val(tripId); $('#pay_OwnerId').val(ownerId);
  $('#pm_vehicle').text('Trip #'+tripId+' — '+vehicle);
  $('#pm_owner').text(owner);
  $('#pm_freight').text('₹'+parseFloat(freight).toFixed(2));
  $('#pm_labour').text('₹'+parseFloat(labour).toFixed(2));
  $('#pm_holding').text('₹'+parseFloat(holding).toFixed(2));
  $('#pm_other').text('₹'+parseFloat(other).toFixed(2));
  $('#pm_tds').text('₹'+parseFloat(tds).toFixed(2));
  $('#pm_comm').text('₹'+parseFloat(commission).toFixed(2));
  $('#pm_net').text('₹'+parseFloat(net).toFixed(2));
  $('#pm_paid').text('₹'+parseFloat(paid).toFixed(2));
  $('#pm_due').text('₹'+rem.toFixed(2));
  $('#pm_prog').css('width', pct+'%');

  /* show/hide zero rows */
  $('#r_labour').toggle(parseFloat(labour) > 0);
  $('#r_holding').toggle(parseFloat(holding) > 0);
  $('#r_other').toggle(parseFloat(other) > 0);
  $('#r_tds').toggle(parseFloat(tds) > 0);
  $('#r_comm').toggle(parseFloat(commission) > 0);

  /* Commission notice — show if this payment will likely close the trip */
  if(parseFloat(commission) > 0 && rem > 0){
    $('#ca_amt').text('₹'+parseFloat(commission).toFixed(2));
    $('#commAlert').show();
  } else {
    $('#commAlert').hide();
  }

  $('#pay_Amount').val(rem > 0 ? rem.toFixed(2) : '');
  $('#pay_Date').val('<?= date('Y-m-d') ?>');
  $('#pay_Mode').val('Cash');
  $('#pay_Ref, #pay_Remarks').val('');
  new bootstrap.Modal('#payModal').show();
}

function submitPay(){
  var amt = parseFloat($('#pay_Amount').val());
  if(!amt || amt <= 0){
    Swal.fire({icon:'warning',title:'Please enter a valid amount!',toast:true,position:'top-end',timer:2000,showConfirmButton:false}); return;
  }
  Swal.fire({title:'Saving...',allowOutsideClick:false,didOpen:function(){Swal.showLoading();}});
  var fd = new FormData();
  fd.append('addPayment',1);
  fd.append('TripId',      $('#pay_TripId').val());
  fd.append('OwnerId',     $('#pay_OwnerId').val());
  fd.append('PaymentDate', $('#pay_Date').val());
  fd.append('Amount',      amt);
  fd.append('PaymentMode', $('#pay_Mode').val());
  fd.append('ReferenceNo', $('#pay_Ref').val());
  fd.append('Remarks',     $('#pay_Remarks').val());
  fetch('OwnerPayment_manage.php',{method:'POST',body:fd})
  .then(function(r){return r.json();}).then(function(res){
    Swal.close();
    if(res.status==='success'){
      bootstrap.Modal.getInstance(document.getElementById('payModal')).hide();
      var msg = 'Payment Saved!';
      if(res.commissionReceived) msg += ' Commission marked as Received ✓';
      Swal.fire({icon:'success',title:msg,toast:true,position:'top-end',showConfirmButton:false,timer:3500});
      setTimeout(function(){ location.reload(); }, 2000);
    } else {
      Swal.fire({icon:'error',title:'Error',text:res.msg});
    }
  }).catch(function(){ Swal.fire({icon:'error',title:'Server Error'}); });
}

function viewHist(tripId, vehicle, owner){
  $('#hist_lbl').text('Trip #'+tripId+' — '+vehicle+' ('+owner+')');
  $('#histBody').html('<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>');
  new bootstrap.Modal('#histModal').show();
  fetch('OwnerPayment_manage.php?getTripPayments=1&TripId='+tripId)
  .then(function(r){return r.json();}).then(function(rows){
    var html='',total=0;
    var ic={Cash:'💵',Cheque:'📋',NEFT:'🏦',RTGS:'🏦',UPI:'📱',Other:'💳'};
    if(!rows.length) html='<tr><td colspan="7" class="text-center text-muted py-3">No payments recorded yet</td></tr>';
    rows.forEach(function(p,i){
      total += parseFloat(p.Amount||0);
      html += '<tr id="pr-'+p.OwnerPaymentId+'">'
        +'<td>'+(i+1)+'</td><td style="white-space:nowrap;">'+p.PaymentDate+'</td>'
        +'<td>'+(ic[p.PaymentMode]||'')+' '+p.PaymentMode+'</td>'
        +'<td><small>'+(p.ReferenceNo||'—')+'</small></td>'
        +'<td class="text-end fw-bold text-success">₹'+parseFloat(p.Amount).toFixed(2)+'</td>'
        +'<td><small>'+(p.Remarks||'—')+'</small></td>'
        +'<td class="text-center"><button class="btn btn-sm btn-outline-danger" style="width:30px;height:30px;padding:0;" onclick="delPay('+p.OwnerPaymentId+')">'
        +'<i class="ri-delete-bin-line"></i></button></td></tr>';
    });
    $('#histBody').html(html);
    $('#histTotal').text('₹'+total.toFixed(2));
  });
}

function delPay(pid){
  Swal.fire({title:'Delete this payment?',icon:'warning',showCancelButton:true,
    confirmButtonText:'Delete',confirmButtonColor:'#dc2626'
  }).then(function(r){
    if(!r.isConfirmed) return;
    var fd=new FormData(); fd.append('deletePayment',1); fd.append('PaymentId',pid);
    fetch('OwnerPayment_manage.php',{method:'POST',body:fd})
    .then(function(r){return r.json();}).then(function(res){
      if(res.status==='success'){
        document.getElementById('pr-'+pid).remove();
        setTimeout(function(){ location.reload(); }, 1200);
      } else Swal.fire({icon:'error',title:'Error',text:res.msg});
    });
  });
}
window.addEventListener('offline',function(){ Swal.fire({icon:'warning',title:'Internet Disconnected!',toast:true,position:'top-end',showConfirmButton:false,timer:3000}); });
window.addEventListener('online', function(){ Swal.fire({icon:'success',title:'Back Online!',toast:true,position:'top-end',showConfirmButton:false,timer:2000}); });
</script>
<?php require_once "../layout/footer.php"; ?>
