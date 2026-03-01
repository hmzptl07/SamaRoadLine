<?php
/* ================================================================
   AgentPayments.php  —  Agent Trip Payment Management
================================================================ */
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

require_once "../../businessLogics/Admin.php";
require_once "../../businessLogics/AgentPayment.php";
Admin::checkAuth();

/* ── AJAX ── */
if (isset($_GET['getTripPayments'])) {
    header('Content-Type: application/json');
    echo json_encode(AgentPayment::getByTrip(intval($_GET['TripId'])));
    exit();
}
if (isset($_POST['addPayment'])) {
    header('Content-Type: application/json');
    echo json_encode(AgentPayment::addPayment(intval($_POST['TripId']), $_POST));
    exit();
}
if (isset($_POST['deletePayment'])) {
    header('Content-Type: application/json');
    echo json_encode(AgentPayment::deletePayment(intval($_POST['PaymentId'])));
    exit();
}

/* ── PAGE DATA ── */
$filterAgent  = !empty($_GET['agentId']) ? intval($_GET['agentId']) : null;
$trips        = AgentPayment::getAllTripsWithPaymentStatus($filterAgent);
$agentSummary = AgentPayment::getAgentSummary();
$agents       = AgentPayment::getAgents();

$totalPayable = array_sum(array_column($trips, 'NetAmount'));
$totalPaid    = array_sum(array_column($trips, 'TotalPaid'));
$totalBalance = array_sum(array_column($trips, 'Balance'));
$cntAll       = count($trips);
$cntUnpaid    = count(array_filter($trips, fn($t) => $t['PaymentStatus'] === 'Unpaid'));
$cntPartial   = count(array_filter($trips, fn($t) => $t['PaymentStatus'] === 'PartiallyPaid'));
$cntPaid      = count(array_filter($trips, fn($t) => $t['PaymentStatus'] === 'Paid'));

require_once "../layout/header.php";
require_once "../layout/sidebar.php";
?>
<style>
/* Header */
.page-hdr {
    background: linear-gradient(135deg,#78350f 0%,#b45309 60%,#d97706 100%);
    border-radius:16px; padding:22px 28px; margin-bottom:22px;
    display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;
}
.ph-left h4 { color:#fff; font-weight:800; font-size:20px; margin:0; display:flex; align-items:center; gap:10px; }
.ph-left p  { color:rgba(255,255,255,.6); font-size:12px; margin:4px 0 0; }

/* Stat Pills */
.stat-row { display:flex; gap:14px; flex-wrap:wrap; margin-bottom:22px; }
.stat-pill {
    background:#fff; border:1px solid #e2e8f0; border-radius:12px;
    padding:14px 20px; display:flex; align-items:center; gap:14px;
    flex:1; min-width:130px; box-shadow:0 1px 4px rgba(0,0,0,.05);
}
.sp-ico { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:19px; flex-shrink:0; }
.sp-num { font-size:22px; font-weight:900; line-height:1; color:#78350f; }
.sp-lbl { font-size:11px; color:#64748b; margin-top:3px; }

/* Tabs */
.tab-nav { display:flex; border-bottom:2px solid #fcd34d; margin-bottom:0; gap:2px; }
.tnav {
    padding:11px 24px; font-size:13px; font-weight:700; cursor:pointer;
    border:none; background:transparent; border-bottom:3px solid transparent;
    margin-bottom:-2px; display:flex; align-items:center; gap:8px;
    color:#64748b; border-radius:8px 8px 0 0; transition:all .15s;
}
.tnav:hover { background:#f8fafc; color:#1a237e; }
.tnav.t-all     { color:#b45309; border-bottom-color:#d97706; background:#fffbeb; }
.tnav.t-unpaid  { color:#dc2626; border-bottom-color:#dc2626; background:#fef2f2; }
.tnav.t-partial { color:#b45309; border-bottom-color:#f59e0b; background:#fffbeb; }
.tnav.t-paid    { color:#15803d; border-bottom-color:#16a34a; background:#f0fdf4; }
.tbadge { display:inline-flex; align-items:center; justify-content:center; min-width:22px; height:20px; border-radius:10px; font-size:10px; font-weight:800; padding:0 5px; }
.b-a { background:#fef3c7; color:#92400e; }
.b-u { background:#fee2e2; color:#dc2626; }
.b-p { background:#fef9c3; color:#b45309; }
.b-d { background:#dcfce7; color:#15803d; }

/* Filter bar */
.fbar { padding:14px 20px; border-bottom:1px solid #fcd34d; }
.fbar-all     { background:#fffbeb; }
.fbar-unpaid  { background:#fff5f5; }
.fbar-partial { background:#fffbeb; }
.fbar-paid    { background:#f0fdf4; }

/* Table */
.ap-head th { background:#78350f; color:#fff; font-size:12px; font-weight:700; padding:10px 12px; border-bottom:2px solid #92400e; white-space:nowrap; }
.tab-card { border-radius:0 0 14px 14px; overflow:hidden; border:1px solid #e2e8f0; border-top:none; }

/* Row tints */
.r-unpaid  { background:#fff5f5 !important; }
.r-partial { background:#fffbeb !important; }
.r-paid    { background:#f0fdf4 !important; }

/* Inline badges */
.agent-pill { font-size:11px; background:#fef3c7; color:#92400e; border:1px solid #fcd34d; border-radius:20px; padding:2px 10px; display:inline-block; }
.bs-u { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.bs-p { background:#fef9c3; color:#b45309; border:1px solid #fde68a; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.bs-d { background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.prog-wrap { height:5px; background:#e2e8f0; border-radius:3px; overflow:hidden; margin-top:4px; }
.prog-bar  { height:100%; border-radius:3px; }
.btn-ic { width:30px; height:30px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:7px; font-size:13px; }
</style>

<div class="main-content app-content">
<div class="container-fluid">

<!-- ── HEADER ── -->
<div class="page-hdr">
    <div class="ph-left">
        <h4><i class="ri-wallet-3-line"></i> Agent Payments</h4>
        <p>Trip-wise payment tracking — Agent is our customer</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-warning fw-bold px-4"
            style="border-radius:10px;height:40px;font-size:13px;"
            onclick="new bootstrap.Modal('#sumModal').show()">
            <i class="ri-bar-chart-grouped-line me-1"></i> Agent Summary
        </button>
        <a href="AgentTrips.php" class="btn btn-outline-light fw-semibold"
            style="border-radius:10px;height:40px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
            <i class="ri-arrow-left-line"></i> Back to Trips
        </a>
    </div>
</div>

<!-- ── STATS ── -->
<div class="stat-row">
    <div class="stat-pill">
        <div class="sp-ico" style="background:#fef3c7;"><i class="ri-road-map-line" style="color:#92400e;"></i></div>
        <div><div class="sp-num"><?= $cntAll ?></div><div class="sp-lbl">Total</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-ico" style="background:#fee2e2;"><i class="ri-time-line" style="color:#dc2626;"></i></div>
        <div><div class="sp-num" style="color:#dc2626;"><?= $cntUnpaid ?></div><div class="sp-lbl">Unpaid</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-ico" style="background:#fef9c3;"><i class="ri-loader-line" style="color:#b45309;"></i></div>
        <div><div class="sp-num" style="color:#b45309;"><?= $cntPartial ?></div><div class="sp-lbl">Partial</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-ico" style="background:#dcfce7;"><i class="ri-checkbox-circle-line" style="color:#15803d;"></i></div>
        <div><div class="sp-num" style="color:#15803d;"><?= $cntPaid ?></div><div class="sp-lbl">Paid</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-ico" style="background:#fef3c7;"><i class="ri-funds-line" style="color:#92400e;"></i></div>
        <div><div class="sp-num" style="font-size:15px;color:#92400e;">Rs.<?= number_format($totalPayable,0) ?></div><div class="sp-lbl">Total Receivable</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-ico" style="background:#dcfce7;"><i class="ri-arrow-down-circle-line" style="color:#15803d;"></i></div>
        <div><div class="sp-num" style="font-size:15px;color:#15803d;">Rs.<?= number_format($totalPaid,0) ?></div><div class="sp-lbl">Total Received</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-ico" style="background:#fee2e2;"><i class="ri-error-warning-line" style="color:#dc2626;"></i></div>
        <div><div class="sp-num" style="font-size:15px;color:#dc2626;">Rs.<?= number_format($totalBalance,0) ?></div><div class="sp-lbl">Still Pending</div></div>
    </div>
</div>

<!-- ── TABS ── -->
<div class="tab-nav">
    <button class="tnav t-all" id="nav-all" onclick="switchTab('all')">
        <i class="ri-list-check"></i> All <span class="tbadge b-a"><?= $cntAll ?></span>
    </button>
    <button class="tnav" id="nav-unpaid" onclick="switchTab('unpaid')">
        <i class="ri-time-line"></i> Unpaid <span class="tbadge b-u"><?= $cntUnpaid ?></span>
    </button>
    <button class="tnav" id="nav-partial" onclick="switchTab('partial')">
        <i class="ri-loader-line"></i> Partial <span class="tbadge b-p"><?= $cntPartial ?></span>
    </button>
    <button class="tnav" id="nav-paid" onclick="switchTab('paid')">
        <i class="ri-checkbox-circle-line"></i> Paid <span class="tbadge b-d"><?= $cntPaid ?></span>
    </button>
</div>

<?php
/* ── TABLE COLUMNS ── */
$thead = '<thead><tr class="ap-head">
    <th>#</th><th>Date</th><th>Vehicle</th><th>Agent</th><th>Route / LR</th>
    <th class="text-end">Freight</th><th class="text-end">Extra</th>
    <th class="text-end">Cash Adv.</th><th class="text-end">Online Adv.</th>
    <th class="text-end">TDS</th><th class="text-end">Net Payable</th>
    <th>Received</th><th class="text-end">Balance</th>
    <th>Status</th><th class="text-center">Actions</th>
</tr></thead>';

/* ── FILTER BAR ── */
function fbar($id, $cls, $color) {
    global $agents; ?>
<div class="fbar <?= $cls ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label fw-semibold fs-12 mb-1">Agent</label>
            <select id="fa_<?= $id ?>" class="form-select form-select-sm">
                <option value="">All Agents</option>
                <?php foreach($agents as $a): ?>
                <option value="<?= htmlspecialchars($a['AgentName']) ?>">
                    <?= htmlspecialchars($a['AgentName']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary btn-sm" onclick="clearF('<?= $id ?>')">
                <i class="ri-refresh-line me-1"></i>Clear
            </button>
        </div>
        <div class="col ms-auto" style="max-width:400px;">
            <label class="form-label fw-semibold fs-12 mb-1">Search</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0"><i class="ri-search-line text-muted"></i></span>
                <input type="text" id="sr_<?= $id ?>" class="form-control border-start-0"
                    placeholder="Vehicle, Route, LR No...">
                <span id="fi_<?= $id ?>" class="input-group-text fw-bold text-white"
                    style="background:<?= $color ?>;min-width:52px;justify-content:center;font-size:11px;"></span>
            </div>
        </div>
    </div>
</div>
<?php }

/* ── ROW ── */
function apRow($t, $i) {
    $pct = floatval($t['NetAmount']) > 0 ? min(100, round($t['TotalPaid']/$t['NetAmount']*100)) : 0;
    $rc  = ['Paid'=>'r-paid','PartiallyPaid'=>'r-partial','Unpaid'=>'r-unpaid'][$t['PaymentStatus']] ?? '';
    ?>
    <tr class="<?= $rc ?>">
        <td class="text-muted fw-medium" style="font-size:13px;"><?= $i ?></td>
        <td style="font-size:12px;white-space:nowrap;"><?= date('d-m-Y',strtotime($t['TripDate'])) ?></td>
        <td>
            <div class="fw-bold" style="font-size:13px;"><?= htmlspecialchars($t['VehicleNumber']??'—') ?></div>
            <?php if(!empty($t['VehicleName'])): ?>
            <div style="font-size:11px;color:#64748b;"><?= htmlspecialchars($t['VehicleName']) ?></div>
            <?php endif; ?>
        </td>
        <td><span class="agent-pill"><?= htmlspecialchars($t['AgentName']??'—') ?></span></td>
        <td style="font-size:12px;">
            <span style="color:#d97706;font-weight:600;"><?= htmlspecialchars($t['FromLocation']??'') ?></span>
            <i class="ri-arrow-right-line" style="font-size:10px;color:#94a3b8;"></i>
            <span style="color:#dc2626;font-weight:600;"><?= htmlspecialchars($t['ToLocation']??'') ?></span>
            <?php if(!empty($t['LRNo'])): ?>
            <div style="font-size:10px;color:#94a3b8;">LR: <?= htmlspecialchars($t['LRNo']) ?></div>
            <?php endif; ?>
        </td>
        <td class="text-end" style="font-size:13px;font-weight:600;">Rs.<?= number_format($t['FreightAmount'],0) ?></td>
        <td class="text-end" style="font-size:12px;color:#64748b;">Rs.<?= number_format($t['ExtraCharges'],0) ?></td>
        <td class="text-end" style="font-size:12px;color:#dc2626;">Rs.<?= number_format($t['CashAdvance'],0) ?></td>
        <td class="text-end" style="font-size:12px;color:#dc2626;">Rs.<?= number_format($t['OnlineAdvance'],0) ?></td>
        <td class="text-end" style="font-size:12px;color:#64748b;">Rs.<?= number_format($t['TDS'],0) ?></td>
        <td class="text-end" style="font-size:14px;font-weight:800;color:#78350f;">Rs.<?= number_format($t['NetAmount'],0) ?></td>
        <td>
            <div style="font-size:13px;font-weight:700;color:#15803d;">Rs.<?= number_format($t['TotalPaid'],0) ?></div>
            <div class="prog-wrap"><div class="prog-bar" style="width:<?= $pct ?>%;background:<?= $pct>=100?'#16a34a':'#f59e0b' ?>;"></div></div>
            <div style="font-size:10px;color:#94a3b8;"><?= $pct ?>% · <?= $t['PaymentCount'] ?> entries</div>
        </td>
        <td class="text-end" style="font-size:13px;font-weight:800;color:<?= $t['Balance']>0?'#dc2626':'#15803d' ?>;">
            Rs.<?= number_format($t['Balance'],0) ?>
        </td>
        <td>
            <?php if($t['PaymentStatus']==='Unpaid'):   echo '<span class="bs-u">Unpaid</span>';
            elseif($t['PaymentStatus']==='PartiallyPaid'): echo '<span class="bs-p">Partial</span>';
            else:                                           echo '<span class="bs-d">✓ Paid</span>';
            endif; ?>
        </td>
        <td>
            <div class="d-flex gap-1 justify-content-center">
                <?php if($t['PaymentStatus']!=='Paid'): ?>
                <button class="btn btn-success btn-ic" title="Add Payment"
                    onclick="openPay(<?= $t['TripId'] ?>,'<?= addslashes($t['VehicleNumber']??'—') ?>','<?= addslashes($t['AgentName']??'—') ?>',<?= floatval($t['NetAmount']) ?>,<?= floatval($t['TotalPaid']) ?>)">
                    <i class="ri-add-circle-line"></i>
                </button>
                <?php endif; ?>
                <button class="btn btn-outline-info btn-ic" title="History"
                    onclick="viewHist(<?= $t['TripId'] ?>,'<?= addslashes($t['VehicleNumber']??'—') ?>','<?= addslashes($t['AgentName']??'') ?>','<?= $t['PaymentStatus'] ?>')">
                    <i class="ri-history-line"></i>
                </button>
            </div>
        </td>
    </tr>
    <?php
}

/* ── Render 4 tabs ── */
$tabData = [
    'all'     => ['trips'=>$trips,       'cls'=>'fbar-all',     'color'=>'#1d4ed8'],
    'unpaid'  => ['trips'=>array_values(array_filter($trips,fn($t)=>$t['PaymentStatus']==='Unpaid')),    'cls'=>'fbar-unpaid',  'color'=>'#dc2626'],
    'partial' => ['trips'=>array_values(array_filter($trips,fn($t)=>$t['PaymentStatus']==='PartiallyPaid')),'cls'=>'fbar-partial','color'=>'#f59e0b'],
    'paid'    => ['trips'=>array_values(array_filter($trips,fn($t)=>$t['PaymentStatus']==='Paid')),       'cls'=>'fbar-paid',   'color'=>'#16a34a'],
];
foreach($tabData as $tabId => $td):
    $display = $tabId==='all' ? 'block' : 'none';
?>
<div id="tab-<?= $tabId ?>" style="display:<?= $display ?>;">
    <?php fbar($tabId, $td['cls'], $td['color']); ?>
    <div class="tab-card">
    <div class="table-responsive">
    <table id="dt_<?= $tabId ?>" class="table table-hover align-middle mb-0 w-100">
        <?= $thead ?>
        <tbody>
        <?php $i=1; foreach($td['trips'] as $t){ apRow($t,$i++); } ?>
        </tbody>
    </table>
    </div></div>
</div>
<?php endforeach; ?>

</div>
</div>

<!-- ════ AGENT SUMMARY MODAL ════ -->
<div class="modal fade" id="sumModal" tabindex="-1">
<div class="modal-dialog modal-lg">
<div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;">
    <div class="modal-header text-white py-3" style="background:linear-gradient(135deg,#78350f,#d97706);">
        <h5 class="modal-title fw-bold"><i class="ri-bar-chart-grouped-line me-2"></i>Agent-wise Summary</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body p-0">
        <table class="table table-hover mb-0 fs-13">
            <thead><tr style="background:#fef3c7;">
                <th style="padding:10px 14px;color:#78350f;font-weight:700;">Agent</th>
                <th style="padding:10px 14px;color:#78350f;font-weight:700;">Mobile</th>
                <th style="padding:10px 14px;color:#78350f;font-weight:700;text-align:center;">Trips</th>
                <th style="padding:10px 14px;color:#78350f;font-weight:700;text-align:right;">Receivable</th>
                <th style="padding:10px 14px;color:#78350f;font-weight:700;text-align:right;">Received</th>
                <th style="padding:10px 14px;color:#78350f;font-weight:700;text-align:right;">Pending</th>
                <th style="padding:10px 14px;color:#78350f;font-weight:700;">Progress</th>
                <th style="padding:10px 14px;"></th>
            </tr></thead>
            <tbody>
            <?php foreach($agentSummary as $as):
                $rem = max(0, floatval($as['TotalPayable']) - floatval($as['TotalPaid']));
                $pct = floatval($as['TotalPayable']) > 0
                    ? min(100, round(floatval($as['TotalPaid'])/floatval($as['TotalPayable'])*100)) : 0;
            ?>
            <tr>
                <td style="padding:10px 14px;"><span class="agent-pill"><?= htmlspecialchars($as['AgentName']) ?></span></td>
                <td style="padding:10px 14px;font-size:12px;color:#64748b;"><?= htmlspecialchars($as['AgentMobile']??'—') ?></td>
                <td style="padding:10px 14px;text-align:center;"><span class="badge bg-secondary"><?= $as['TotalTrips'] ?></span></td>
                <td style="padding:10px 14px;text-align:right;font-weight:700;">Rs.<?= number_format($as['TotalPayable'],0) ?></td>
                <td style="padding:10px 14px;text-align:right;font-weight:700;color:#15803d;">Rs.<?= number_format($as['TotalPaid'],0) ?></td>
                <td style="padding:10px 14px;text-align:right;font-weight:800;color:<?= $rem>0?'#dc2626':'#15803d' ?>;">Rs.<?= number_format($rem,0) ?></td>
                <td style="padding:10px 14px;min-width:100px;">
                    <div style="font-size:10px;color:#64748b;margin-bottom:3px;"><?= $pct ?>%</div>
                    <div class="prog-wrap"><div class="prog-bar" style="width:<?= $pct ?>%;background:<?= $pct>=100?'#16a34a':'#f59e0b' ?>;"></div></div>
                </td>
                <td style="padding:10px 14px;">
                    <a href="?agentId=<?= $as['AgentId'] ?>"
                        onclick="$('#sumModal').modal('hide');"
                        class="btn btn-xs btn-outline-warning py-0 px-2 fs-11">
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
</div></div></div>

<!-- ════ PAY MODAL ════ -->
<div class="modal fade" id="payModal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;">
    <div class="modal-header text-white py-3" style="background:linear-gradient(135deg,#15803d,#16a34a);">
        <h5 class="modal-title fw-bold"><i class="ri-wallet-3-line me-2"></i>Record Payment Received</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <div class="rounded p-3 mb-3" style="background:#f0fdf4;border:1px solid #bbf7d0;">
            <div class="row g-0 text-center">
                <div class="col-4" style="border-right:1px solid #bbf7d0;">
                    <div style="font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;">Trip</div>
                    <div class="fw-bold fs-12 mt-1" id="pm_trip">—</div>
                </div>
                <div class="col-4" style="border-right:1px solid #bbf7d0;">
                    <div style="font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;">Agent</div>
                    <div class="fw-bold fs-12 mt-1" style="color:#92400e;" id="pm_agent">—</div>
                </div>
                <div class="col-4">
                    <div style="font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;">Pending</div>
                    <div class="fw-bold mt-1" style="font-size:20px;color:#dc2626;" id="pm_due">₹0</div>
                </div>
            </div>
            <div class="prog-wrap mt-3"><div class="prog-bar" id="pm_prog" style="width:0%;"></div></div>
            <div class="d-flex justify-content-between mt-1">
                <small class="text-muted">Received: <b id="pm_paid">₹0</b></small>
                <small class="text-muted">Net Payable: <b id="pm_total">₹0</b></small>
            </div>
        </div>
        <input type="hidden" id="pay_TripId">
        <div class="row g-3">
            <div class="col-6">
                <label class="form-label fw-semibold fs-13">Date <span class="text-danger">*</span></label>
                <input type="date" id="pay_Date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6">
                <label class="form-label fw-semibold fs-13">Amount (Rs.) <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text bg-light fw-bold">Rs.</span>
                    <input type="number" id="pay_Amount" class="form-control fw-bold" step="0.01" min="0.01" placeholder="0.00">
                </div>
            </div>
            <div class="col-6">
                <label class="form-label fw-semibold fs-13">Mode</label>
                <select id="pay_Mode" class="form-select">
                    <option value="Cash">💵 Cash</option>
                    <option value="Cheque">📋 Cheque</option>
                    <option value="NEFT">🏦 NEFT</option>
                    <option value="RTGS">🏦 RTGS</option>
                    <option value="UPI">📱 UPI</option>
                    <option value="Online">🌐 Online</option>
                </select>
            </div>
            <div class="col-6">
                <label class="form-label fw-semibold fs-13">Reference / Cheque No.</label>
                <input type="text" id="pay_Ref" class="form-control" placeholder="Optional">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold fs-13">Remarks</label>
                <input type="text" id="pay_Remarks" class="form-control" placeholder="Optional...">
            </div>
        </div>
    </div>
    <div class="modal-footer py-2">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success fw-bold" onclick="submitPay()">
            <i class="ri-save-3-line me-1"></i>Save Payment
        </button>
    </div>
</div></div></div>

<!-- ════ HISTORY MODAL ════ -->
<div class="modal fade" id="histModal" tabindex="-1">
<div class="modal-dialog modal-lg">
<div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;">
    <div class="modal-header text-white py-3" style="background:linear-gradient(135deg,#0369a1,#0284c7);">
        <h5 class="modal-title fw-bold"><i class="ri-history-line me-2"></i>Payment History — <span id="hist_label"></span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body p-0">
        <table class="table table-bordered table-sm mb-0 fs-13">
            <thead class="table-light">
                <tr><th>#</th><th>Date</th><th>Mode</th><th>Reference</th><th class="text-end">Amount</th><th>Remarks</th><th>Created</th><th></th></tr>
            </thead>
            <tbody id="histBody"></tbody>
            <tfoot>
                <tr class="table-success">
                    <td colspan="4" class="text-end fw-bold">Total Received:</td>
                    <td class="fw-bold" id="histTotal">₹0</td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="modal-footer py-2">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
    </div>
</div></div></div>

<script>
var dts = {};
$(document).ready(function(){
    var cfg = {
        scrollX:true, pageLength:25, dom:'rtip',
        columnDefs:[{orderable:false,targets:[0,14]}],
        language:{paginate:{previous:'‹',next:'›'}}
    };
    ['all','unpaid','partial','paid'].forEach(function(id){
        dts[id] = $('#dt_'+id).DataTable({
            ...cfg,
            drawCallback: function(){
                var info = this.api().page.info();
                $('#fi_'+id).text(info.recordsDisplay+'/'+info.recordsTotal);
            }
        });
        $('#sr_'+id).on('keyup input', function(){ dts[id].search($(this).val()).draw(); });
        $('#fa_'+id).on('change', function(){ dts[id].column(3).search(this.value||'').draw(); });
    });
});

function switchTab(name){
    var tabs=['all','unpaid','partial','paid'];
    var clsMap={all:'t-all',unpaid:'t-unpaid',partial:'t-partial',paid:'t-paid'};
    tabs.forEach(function(t){
        document.getElementById('nav-'+t).className='tnav';
        document.getElementById('tab-'+t).style.display='none';
    });
    document.getElementById('nav-'+name).classList.add(clsMap[name]);
    document.getElementById('tab-'+name).style.display='block';
    if(dts[name]) dts[name].columns.adjust();
}

function clearF(id){
    $('#fa_'+id).val('').trigger('change');
    $('#sr_'+id).val('');
    if(dts[id]) dts[id].search('').draw();
}

function openPay(tripId,vehicle,agent,net,paid){
    var rem=Math.max(0,net-paid), pct=net>0?Math.min(100,Math.round(paid/net*100)):0;
    $('#pay_TripId').val(tripId);
    $('#pm_trip').text('Trip #'+tripId+' — '+vehicle);
    $('#pm_agent').text(agent);
    $('#pm_due').text('₹'+rem.toLocaleString('en-IN',{minimumFractionDigits:2}));
    $('#pm_paid').text('₹'+paid.toLocaleString('en-IN',{minimumFractionDigits:2}));
    $('#pm_total').text('₹'+net.toLocaleString('en-IN',{minimumFractionDigits:2}));
    $('#pm_prog').css('width',pct+'%');
    $('#pay_Amount').val(rem>0?rem.toFixed(2):'');
    $('#pay_Date').val('<?= date('Y-m-d') ?>');
    $('#pay_Mode').val('Cash');
    $('#pay_Ref,#pay_Remarks').val('');
    new bootstrap.Modal('#payModal').show();
}

function submitPay(){
    var amt=parseFloat($('#pay_Amount').val());
    if(!amt||amt<=0){
        Swal.fire({icon:'warning',title:'Enter valid amount!',toast:true,position:'top-end',timer:2000,showConfirmButton:false});
        return;
    }
    Swal.fire({title:'Saving...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
    var fd=new FormData();
    fd.append('addPayment',1); fd.append('TripId',$('#pay_TripId').val());
    fd.append('PaymentDate',$('#pay_Date').val()); fd.append('Amount',amt);
    fd.append('PaymentMode',$('#pay_Mode').val()); fd.append('Reference',$('#pay_Ref').val());
    fd.append('Remarks',$('#pay_Remarks').val());
    fetch('AgentPayments.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
        Swal.close();
        if(res.status==='success'){
            bootstrap.Modal.getInstance(document.getElementById('payModal')).hide();
            Swal.fire({icon:'success',title:'Saved!',toast:true,position:'top-end',showConfirmButton:false,timer:2500});
            setTimeout(()=>location.reload(),1800);
        } else Swal.fire({icon:'error',title:'Error',text:res.msg});
    }).catch(()=>Swal.fire({icon:'error',title:'Server Error'}));
}

function viewHist(tripId,vehicle,agent,payStatus){
    $('#hist_label').text('Trip #'+tripId+' — '+vehicle+' ('+agent+')');
    $('#histBody').html('<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>');
    new bootstrap.Modal('#histModal').show();
    fetch('AgentPayments.php?getTripPayments=1&TripId='+tripId).then(r=>r.json()).then(rows=>{
        var html='',total=0;
        var icons={Cash:'💵',Cheque:'📋',NEFT:'🏦',RTGS:'🏦',UPI:'📱',Online:'🌐'};
        if(!rows.length) html='<tr><td colspan="8" class="text-center text-muted py-3">No payments yet</td></tr>';
        rows.forEach(function(p,i){
            total+=parseFloat(p.Amount||0);
            html+='<tr id="pr-'+p.PaymentId+'">'
                +'<td>'+(i+1)+'</td>'
                +'<td style="white-space:nowrap">'+p.PaymentDate+'</td>'
                +'<td>'+(icons[p.PaymentMode]||'')+' '+p.PaymentMode+'</td>'
                +'<td><small>'+(p.Reference||'—')+'</small></td>'
                +'<td class="text-end fw-bold text-success">₹'+parseFloat(p.Amount).toFixed(2)+'</td>'
                +'<td><small>'+(p.Remarks||'—')+'</small></td>'
                +'<td><small class="text-muted">'+(p.CreatedDate?p.CreatedDate.substring(0,16):'—')+'</small></td>'
                +'<td>'+(payStatus!=='Paid'?'<button class="btn btn-sm btn-outline-danger btn-ic" onclick="delPay('+p.PaymentId+')"><i class="ri-delete-bin-line"></i></button>':'<span class="text-muted" title="Fully paid — cannot delete"><i class="ri-lock-line"></i></span>')+'</td>'
                +'</tr>';
        });
        $('#histBody').html(html);
        $('#histTotal').text('₹'+total.toFixed(2));
    });
}

function delPay(pid){
    Swal.fire({title:'Delete payment?',icon:'warning',showCancelButton:true,confirmButtonText:'Delete',confirmButtonColor:'#dc3545'})
    .then(r=>{
        if(!r.isConfirmed) return;
        var fd=new FormData(); fd.append('deletePayment',1); fd.append('PaymentId',pid);
        fetch('AgentPayments.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
            if(res.status==='success'){
                $('#pr-'+pid).fadeOut(300,function(){$(this).remove();});
                setTimeout(()=>location.reload(),1500);
            } else Swal.fire({icon:'error',title:'Error',text:res.msg});
        });
    });
}
</script>
<?php require_once "../layout/footer.php"; ?>
