<?php
/* ================================================================
   AgentPayments.php  —  Agent Trip Payment Management
================================================================ */
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

require_once "../../businessLogics/Admin.php";
require_once "../../businessLogics/AgentPayment.php";
require_once "../../businessLogics/AgentTrip.php";
Admin::checkAuth();

/* ── AJAX: Get Trip Payments ── */
if (isset($_GET['getTripPayments'])) {
    header('Content-Type: application/json');
    echo json_encode(AgentPayment::getByTrip(intval($_GET['TripId'])));
    exit();
}

/* ── AJAX: Add Payment ── */
if (isset($_POST['addPayment'])) {
    header('Content-Type: application/json');
    echo json_encode(AgentPayment::addPayment(intval($_POST['TripId']), $_POST));
    exit();
}

/* ── AJAX: Delete Payment ── */
if (isset($_POST['deletePayment'])) {
    header('Content-Type: application/json');
    echo json_encode(AgentPayment::deletePayment(intval($_POST['PaymentId'])));
    exit();
}

/* ── AJAX: Get Trip Detail for View Popup ── */
if (isset($_GET['getTripDetail'])) {
    header('Content-Type: application/json');
    $tid  = intval($_GET['TripId'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT t.*,
               p3.PartyName AS AgentName,
               p3.MobileNo  AS AgentMobile,
               p3.City      AS AgentCity,
               v.VehicleNumber, v.VehicleName
        FROM TripMaster t
        LEFT JOIN PartyMaster   p3 ON t.AgentId   = p3.PartyId
        LEFT JOIN VehicleMaster v  ON t.VehicleId  = v.VehicleId
        WHERE t.TripId = ? AND t.TripType = 'Agent'
    ");
    $stmt->execute([$tid]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$trip) { echo json_encode(['error' => 'Not found']); exit; }
    $trip['Materials']  = AgentTrip::getMaterials($tid);
    $trip['Commission'] = AgentTrip::getCommission($tid);
    $payments           = AgentPayment::getByTrip($tid);
    $trip['Payments']   = $payments;
    $trip['TotalPaid']  = array_sum(array_column($payments, 'Amount'));
    echo json_encode($trip);
    exit();
}

/* ── Date Filter Logic ── */
$today      = date('Y-m-d');
$datePreset = $_GET['datePreset'] ?? 'all';
$dateFrom   = $_GET['dateFrom']   ?? '';
$dateTo     = $_GET['dateTo']     ?? '';

switch ($datePreset) {
    case 'today':     $dateFrom = $dateTo = $today; break;
    case 'yesterday': $dateFrom = $dateTo = date('Y-m-d', strtotime('-1 day')); break;
    case 'thisweek':
        $dateFrom = date('Y-m-d', strtotime('monday this week'));
        $dateTo   = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'custom': break;
    default: $dateFrom = $dateTo = '';
}

$dateFilter  = array_filter(['from' => $dateFrom, 'to' => $dateTo]);
$filterAgent = !empty($_GET['agentId']) ? intval($_GET['agentId']) : null;

$trips        = AgentPayment::getAllTripsWithPaymentStatus($filterAgent, $dateFilter);
$agentSummary = AgentPayment::getAgentSummary();
$agents       = AgentPayment::getAgents();

$tripsNormal    = array_values(array_filter($trips, fn($t) => $t['PaymentStatus'] !== 'OwnerPaid'));
$tripsOwnerPaid = array_values(array_filter($trips, fn($t) => $t['PaymentStatus'] === 'OwnerPaid'));

$totalPayable = array_sum(array_column($tripsNormal, 'NetAmount'));
$totalPaid    = array_sum(array_column($tripsNormal, 'TotalPaid'));
$totalBalance = array_sum(array_column($tripsNormal, 'Balance'));
$cntAll       = count($tripsNormal);
$cntUnpaid    = count(array_filter($tripsNormal, fn($t) => $t['PaymentStatus'] === 'Unpaid'));
$cntPartial   = count(array_filter($tripsNormal, fn($t) => $t['PaymentStatus'] === 'PartiallyPaid'));
$cntPaid      = count(array_filter($tripsNormal, fn($t) => $t['PaymentStatus'] === 'Paid'));
$cntOwner     = count($tripsOwnerPaid);

require_once "../layout/header.php";
require_once "../layout/sidebar.php";
?>
<style>
.page-hdr{background:linear-gradient(135deg,#78350f 0%,#b45309 60%,#d97706 100%);border-radius:16px;padding:22px 28px;margin-bottom:22px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
.ph-left h4{color:#fff;font-weight:800;font-size:20px;margin:0;display:flex;align-items:center;gap:10px;}
.ph-left p{color:rgba(255,255,255,.6);font-size:12px;margin:4px 0 0;}
.stat-row{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:22px;}
.stat-pill{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 20px;display:flex;align-items:center;gap:14px;flex:1;min-width:130px;box-shadow:0 1px 4px rgba(0,0,0,.05);}
.sp-ico{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:19px;flex-shrink:0;}
.sp-num{font-size:22px;font-weight:900;line-height:1;color:#78350f;}
.sp-lbl{font-size:11px;color:#64748b;margin-top:3px;}
.date-filter-bar{background:#fff;border:1px solid #fcd34d;border-radius:12px;padding:14px 18px;margin-bottom:18px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.df-btn{padding:6px 14px;border-radius:20px;font-size:12px;font-weight:700;border:2px solid #e2e8f0;background:#f8fafc;color:#64748b;cursor:pointer;transition:all .15s;white-space:nowrap;text-decoration:none;display:inline-block;}
.df-btn:hover{border-color:#d97706;color:#92400e;background:#fffbeb;}
.df-btn.active{border-color:#d97706;background:#d97706;color:#fff;}
.df-sep{width:1px;height:30px;background:#e2e8f0;margin:0 4px;}
.df-range{display:flex;align-items:center;gap:8px;}
.df-range input[type=date]{border:2px solid #e2e8f0;border-radius:8px;padding:5px 10px;font-size:12px;font-weight:600;color:#374151;outline:none;}
.df-range input[type=date]:focus{border-color:#d97706;}
.df-apply{padding:6px 16px;border-radius:8px;background:#d97706;color:#fff;border:none;font-size:12px;font-weight:700;cursor:pointer;}
.df-apply:hover{background:#b45309;}
.df-label{font-size:12px;font-weight:700;color:#92400e;white-space:nowrap;}
.df-active-tag{background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:3px 10px;font-size:11px;font-weight:700;color:#92400e;}
.tab-nav{display:flex;border-bottom:2px solid #fcd34d;margin-bottom:0;gap:2px;flex-wrap:wrap;}
.tnav{padding:11px 20px;font-size:13px;font-weight:700;cursor:pointer;border:none;background:transparent;border-bottom:3px solid transparent;margin-bottom:-2px;display:flex;align-items:center;gap:8px;color:#64748b;border-radius:8px 8px 0 0;transition:all .15s;}
.tnav:hover{background:#f8fafc;color:#78350f;}
.tnav.t-all{color:#b45309;border-bottom-color:#d97706;background:#fffbeb;}
.tnav.t-unpaid{color:#dc2626;border-bottom-color:#dc2626;background:#fef2f2;}
.tnav.t-partial{color:#b45309;border-bottom-color:#f59e0b;background:#fffbeb;}
.tnav.t-paid{color:#15803d;border-bottom-color:#16a34a;background:#f0fdf4;}
.tnav.t-owner{color:#7c3aed;border-bottom-color:#7c3aed;background:#f5f3ff;}
.tbadge{display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:20px;border-radius:10px;font-size:10px;font-weight:800;padding:0 5px;}
.b-a{background:#fef3c7;color:#92400e;}.b-u{background:#fee2e2;color:#dc2626;}.b-p{background:#fef9c3;color:#b45309;}.b-d{background:#dcfce7;color:#15803d;}.b-o{background:#ede9fe;color:#7c3aed;}
.fbar{padding:14px 20px;border-bottom:1px solid #fcd34d;}
.fbar-all{background:#fffbeb;}.fbar-unpaid{background:#fff5f5;}.fbar-partial{background:#fffbeb;}.fbar-paid{background:#f0fdf4;}.fbar-owner{background:#f5f3ff;}
.ap-head th{background:#78350f;color:#fff;font-size:12px;font-weight:700;padding:10px 12px;border-bottom:2px solid #92400e;white-space:nowrap;}
.tab-card{border-radius:0 0 14px 14px;overflow:hidden;border:1px solid #e2e8f0;border-top:none;}
.r-unpaid{background:#fff5f5 !important;}.r-partial{background:#fffbeb !important;}.r-paid{background:#f0fdf4 !important;}.r-owner{background:#f5f3ff !important;}
.agent-pill{font-size:11px;background:#fef3c7;color:#92400e;border:1px solid #fcd34d;border-radius:20px;padding:2px 10px;display:inline-block;}
.bs-u{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.bs-p{background:#fef9c3;color:#b45309;border:1px solid #fde68a;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.bs-d{background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.bs-o{background:linear-gradient(90deg,#ede9fe,#ddd6fe);color:#5b21b6;border:1px solid #c4b5fd;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:800;display:inline-flex;align-items:center;gap:4px;}
.prog-wrap{height:5px;background:#e2e8f0;border-radius:3px;overflow:hidden;margin-top:4px;}
.prog-bar{height:100%;border-radius:3px;}
.btn-ic{width:30px;height:30px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:7px;font-size:13px;}
.owner-note{font-size:10px;color:#7c3aed;background:#ede9fe;border-radius:6px;padding:2px 8px;display:inline-block;margin-top:3px;}
/* view modal */
.vt-tab-btn{background:none;border:none;padding:10px 18px;font-size:13px;font-weight:600;color:#64748b;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;transition:.15s;}
.vt-tab-btn:hover{color:#92400e;background:#fffbeb;}
.vt-tab-active{color:#92400e !important;border-bottom-color:#d97706 !important;background:#fffbeb;}
.vt-row{display:flex;padding:7px 0;border-bottom:1px solid #f1f5f9;font-size:13px;gap:8px;}
.vt-row:last-child{border-bottom:none;}
.vt-lbl{width:140px;flex-shrink:0;color:#64748b;font-size:11.5px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;padding-top:1px;}
.vt-val{flex:1;color:#1e293b;font-size:13px;}
/* select2 fix inside filter bars */
.fbar .select2-container{width:100% !important;}
.fbar .select2-container--bootstrap-5 .select2-selection{min-height:32px;font-size:12px;}
</style>

<div class="main-content app-content">
<div class="container-fluid">

<!-- HEADER -->
<div class="page-hdr">
    <div class="ph-left">
        <h4><i class="ri-wallet-3-line"></i> Agent Payments</h4>
        <p>Trip-wise payment tracking — Agent is our customer</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-warning fw-bold px-4" style="border-radius:10px;height:40px;font-size:13px;"
            onclick="new bootstrap.Modal(document.getElementById('sumModal')).show()">
            <i class="ri-bar-chart-grouped-line me-1"></i> Agent Summary
        </button>
        <a href="AgentTrips.php" class="btn btn-outline-light fw-semibold"
            style="border-radius:10px;height:40px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
            <i class="ri-arrow-left-line"></i> Back to Trips
        </a>
    </div>
</div>

<!-- STATS -->
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
        <div class="sp-ico" style="background:#ede9fe;"><i class="ri-truck-line" style="color:#7c3aed;"></i></div>
        <div><div class="sp-num" style="color:#7c3aed;"><?= $cntOwner ?></div><div class="sp-lbl">Paid to Owner</div></div>
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

<!-- DATE FILTER BAR -->
<div class="date-filter-bar">
    <span class="df-label"><i class="ri-calendar-line me-1"></i>Filter:</span>
    <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <a href="?<?= http_build_query(array_merge($_GET,['datePreset'=>'all','dateFrom'=>'','dateTo'=>''])) ?>"
           class="df-btn <?= $datePreset==='all'?'active':'' ?>">All</a>
        <a href="?<?= http_build_query(array_merge($_GET,['datePreset'=>'today','dateFrom'=>'','dateTo'=>''])) ?>"
           class="df-btn <?= $datePreset==='today'?'active':'' ?>">Today</a>
        <a href="?<?= http_build_query(array_merge($_GET,['datePreset'=>'yesterday','dateFrom'=>'','dateTo'=>''])) ?>"
           class="df-btn <?= $datePreset==='yesterday'?'active':'' ?>">Yesterday</a>
        <a href="?<?= http_build_query(array_merge($_GET,['datePreset'=>'thisweek','dateFrom'=>'','dateTo'=>''])) ?>"
           class="df-btn <?= $datePreset==='thisweek'?'active':'' ?>">This Week</a>
    </div>
    <div class="df-sep"></div>
    <form method="GET" class="df-range" id="rangeForm">
        <?php foreach($_GET as $k=>$v): if(in_array($k,['datePreset','dateFrom','dateTo'])) continue; ?>
        <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
        <?php endforeach; ?>
        <input type="hidden" name="datePreset" value="custom">
        <span class="df-label">From</span>
        <input type="date" name="dateFrom" id="dfFrom" value="<?= htmlspecialchars($datePreset==='custom'?$dateFrom:'') ?>">
        <span class="df-label">To</span>
        <input type="date" name="dateTo" id="dfTo" value="<?= htmlspecialchars($datePreset==='custom'?$dateTo:'') ?>">
        <button type="submit" class="df-apply"><i class="ri-search-line"></i> Go</button>
    </form>
    <?php if($datePreset!=='all' && $datePreset!==''): ?>
    <span class="df-active-tag">
        <i class="ri-filter-3-line me-1"></i>
        <?php
        if($datePreset==='today')         echo 'Today: '.$today;
        elseif($datePreset==='yesterday') echo 'Yesterday: '.date('d-m-Y',strtotime('-1 day'));
        elseif($datePreset==='thisweek')  echo 'This Week';
        elseif($datePreset==='custom' && $dateFrom && $dateTo) echo date('d-m-Y',strtotime($dateFrom)).' → '.date('d-m-Y',strtotime($dateTo));
        ?>
    </span>
    <a href="AgentPayments.php" class="df-btn" style="border-color:#dc2626;color:#dc2626;"><i class="ri-close-line"></i> Clear</a>
    <?php endif; ?>
</div>

<!-- TABS -->
<div class="tab-nav">
    <button class="tnav t-all" id="nav-all"     onclick="switchTab('all')"><i class="ri-list-check"></i> All <span class="tbadge b-a"><?= $cntAll ?></span></button>
    <button class="tnav"       id="nav-unpaid"  onclick="switchTab('unpaid')"><i class="ri-time-line"></i> Unpaid <span class="tbadge b-u"><?= $cntUnpaid ?></span></button>
    <button class="tnav"       id="nav-partial" onclick="switchTab('partial')"><i class="ri-loader-line"></i> Partial <span class="tbadge b-p"><?= $cntPartial ?></span></button>
    <button class="tnav"       id="nav-paid"    onclick="switchTab('paid')"><i class="ri-checkbox-circle-line"></i> Paid <span class="tbadge b-d"><?= $cntPaid ?></span></button>
    <button class="tnav"       id="nav-owner"   onclick="switchTab('owner')"><i class="ri-truck-line"></i> Paid to Owner <span class="tbadge b-o"><?= $cntOwner ?></span></button>
</div>

<?php
$thead = '<thead><tr class="ap-head">
    <th>#</th><th>Date</th><th>Vehicle</th><th>Agent</th><th>Route / LR</th>
    <th class="text-end">Freight</th><th class="text-end">Extra</th>
    <th class="text-end">Cash Adv.</th><th class="text-end">Online Adv.</th>
    <th class="text-end">TDS</th><th class="text-end">Net Payable</th>
    <th>Received</th><th class="text-end">Balance</th>
    <th>Status</th><th class="text-center">Actions</th>
</tr></thead>';

/* Filter bar per tab */
function fbar(string $id, string $cls, string $color): void {
    global $agents; ?>
<div class="fbar <?= $cls ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label fw-semibold mb-1" style="font-size:12px;">Agent</label>
            <select id="fa_<?= $id ?>" name="fa_<?= $id ?>" class="s2agent">
                <option value=""></option>
                <?php foreach($agents as $a): ?>
                <option value="<?= htmlspecialchars($a['AgentName']) ?>"><?= htmlspecialchars($a['AgentName']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary btn-sm" onclick="clearF('<?= $id ?>')">
                <i class="ri-refresh-line me-1"></i>Clear
            </button>
        </div>
        <div class="col ms-auto" style="max-width:420px;">
            <label class="form-label fw-semibold mb-1" style="font-size:12px;">Search</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0"><i class="ri-search-line text-muted"></i></span>
                <input type="text" id="sr_<?= $id ?>" class="form-control border-start-0" placeholder="Vehicle, Route, LR No...">
                <span id="fi_<?= $id ?>" class="input-group-text fw-bold text-white"
                    style="background:<?= $color ?>;min-width:54px;justify-content:center;font-size:11px;"></span>
            </div>
        </div>
    </div>
</div>
<?php }

/* Table row */
function apRow(array $t, int $i): void {
    $pct     = floatval($t['NetAmount']) > 0 ? min(100, round($t['TotalPaid']/$t['NetAmount']*100)) : 0;
    $isOwner = ($t['PaymentStatus'] === 'OwnerPaid');
    $rc      = $isOwner ? 'r-owner' : (['Paid'=>'r-paid','PartiallyPaid'=>'r-partial','Unpaid'=>'r-unpaid'][$t['PaymentStatus']] ?? '');
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
            <?php if(!empty($t['LRNo'])): ?><div style="font-size:10px;color:#94a3b8;">LR: <?= htmlspecialchars($t['LRNo']) ?></div><?php endif; ?>
            <?php if($isOwner): ?><div class="owner-note"><i class="ri-truck-line"></i> Owner ko direct payment</div><?php endif; ?>
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
            <?php if($isOwner): ?>
                <span class="bs-o"><i class="ri-truck-line"></i> Paid to Owner</span>
            <?php elseif($t['PaymentStatus']==='Unpaid'):   echo '<span class="bs-u">Unpaid</span>';
            elseif($t['PaymentStatus']==='PartiallyPaid'):  echo '<span class="bs-p">Partial</span>';
            else: echo '<span class="bs-d">✓ Paid</span>';
            endif; ?>
        </td>
        <td>
            <div class="d-flex gap-1 justify-content-center">
                <?php if(!$isOwner && $t['PaymentStatus']!=='Paid'): ?>
                <button class="btn btn-success btn-ic" title="Add Payment"
                    onclick="openPay(<?= $t['TripId'] ?>,'<?= addslashes($t['VehicleNumber']??'—') ?>','<?= addslashes($t['AgentName']??'—') ?>',<?= floatval($t['NetAmount']) ?>,<?= floatval($t['TotalPaid']) ?>)">
                    <i class="ri-add-circle-line"></i>
                </button>
                <?php endif; ?>
                <?php if($isOwner): ?>
                <span title="Already paid to owner — no payment allowed"
                    style="width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;color:#7c3aed;font-size:16px;">
                    <i class="ri-lock-2-line"></i>
                </span>
                <?php endif; ?>
                <button class="btn btn-outline-primary btn-ic" title="View Trip Detail"
                    onclick="viewTrip(<?= $t['TripId'] ?>)">
                    <i class="ri-eye-line"></i>
                </button>
                <button class="btn btn-outline-info btn-ic" title="Payment History"
                    onclick="viewHist(<?= $t['TripId'] ?>,'<?= addslashes($t['VehicleNumber']??'—') ?>','<?= addslashes($t['AgentName']??'') ?>','<?= $t['PaymentStatus'] ?>')">
                    <i class="ri-history-line"></i>
                </button>
            </div>
        </td>
    </tr>
    <?php
}

/* Render tabs */
$tabData = [
    'all'     => ['trips' => $tripsNormal,  'cls'=>'fbar-all',    'color'=>'#b45309'],
    'unpaid'  => ['trips' => array_values(array_filter($tripsNormal,fn($t)=>$t['PaymentStatus']==='Unpaid')),        'cls'=>'fbar-unpaid',  'color'=>'#dc2626'],
    'partial' => ['trips' => array_values(array_filter($tripsNormal,fn($t)=>$t['PaymentStatus']==='PartiallyPaid')), 'cls'=>'fbar-partial', 'color'=>'#f59e0b'],
    'paid'    => ['trips' => array_values(array_filter($tripsNormal,fn($t)=>$t['PaymentStatus']==='Paid')),          'cls'=>'fbar-paid',    'color'=>'#16a34a'],
    'owner'   => ['trips' => $tripsOwnerPaid, 'cls'=>'fbar-owner', 'color'=>'#7c3aed'],
];
foreach($tabData as $tabId => $td):
    $display = ($tabId==='all') ? 'block' : 'none';
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
        </div>
    </div>
</div>
<?php endforeach; ?>

</div><!-- /container-fluid -->
</div><!-- /main-content -->

<!-- ════════════════════════════════════════
     VIEW TRIP MODAL
════════════════════════════════════════ -->
<div class="modal fade" id="viewTripModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 20px 60px rgba(0,0,0,.15);">
    <div style="background:linear-gradient(135deg,#78350f,#d97706);border-radius:16px 16px 0 0;padding:16px 24px;display:flex;align-items:center;justify-content:space-between;">
        <div>
            <div id="vtTitle" style="font-size:16px;font-weight:800;color:#fff;display:flex;align-items:center;gap:8px;"><i class="ri-road-map-line"></i> Trip Details</div>
            <div id="vtSubtitle" style="font-size:12px;color:rgba(255,255,255,.65);margin-top:3px;"></div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <!-- View Modal Tabs -->
    <div style="display:flex;border-bottom:2px solid #e2e8f0;background:#f8fafc;padding:0 20px;">
        <button class="vt-tab-btn vt-tab-active" id="vtbtn-info"      onclick="vtSwitch('info',this)"><i class="ri-information-line me-1"></i>Trip Info</button>
        <button class="vt-tab-btn"               id="vtbtn-materials" onclick="vtSwitch('materials',this)"><i class="ri-box-3-line me-1"></i>Materials</button>
        <button class="vt-tab-btn"               id="vtbtn-charges"   onclick="vtSwitch('charges',this)"><i class="ri-money-rupee-circle-line me-1"></i>Charges</button>
        <button class="vt-tab-btn"               id="vtbtn-payments"  onclick="vtSwitch('payments',this)"><i class="ri-wallet-3-line me-1"></i>Payments</button>
    </div>
    <div class="modal-body" style="padding:20px 24px;max-height:65vh;overflow-y:auto;">
        <!-- Info Pane -->
        <div id="vtpane-info">
            <div class="row g-3">
                <div class="col-md-6">
                    <div style="background:#fffbeb;border-radius:10px;padding:14px 16px;">
                        <div style="font-size:10px;font-weight:800;color:#92400e;text-transform:uppercase;letter-spacing:.8px;border-left:3px solid #d97706;padding-left:8px;margin-bottom:10px;">Trip Info</div>
                        <div class="vt-row"><span class="vt-lbl">LR No.</span><span class="vt-val fw-bold" id="vti-lr"></span></div>
                        <div class="vt-row"><span class="vt-lbl">Date</span><span class="vt-val" id="vti-date"></span></div>
                        <div class="vt-row"><span class="vt-lbl">Vehicle</span><span class="vt-val" id="vti-vehicle"></span></div>
                        <div class="vt-row"><span class="vt-lbl">Invoice No.</span><span class="vt-val" id="vti-invoice"></span></div>
                        <div class="vt-row"><span class="vt-lbl">Route</span><span class="vt-val" id="vti-route"></span></div>
                        <div class="vt-row"><span class="vt-lbl">Status</span><span class="vt-val" id="vti-status"></span></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div style="background:#fef3c7;border-radius:10px;padding:14px 16px;">
                        <div style="font-size:10px;font-weight:800;color:#92400e;text-transform:uppercase;letter-spacing:.8px;border-left:3px solid #d97706;padding-left:8px;margin-bottom:10px;">Agent</div>
                        <div class="vt-row"><span class="vt-lbl">Agent Name</span><span class="vt-val fw-bold" id="vti-agent"></span></div>
                        <div class="vt-row"><span class="vt-lbl">Mobile</span><span class="vt-val" id="vti-mobile"></span></div>
                        <div class="vt-row"><span class="vt-lbl">City</span><span class="vt-val" id="vti-agentcity"></span></div>

                    </div>
                </div>
            </div>
        </div>
        <!-- Materials Pane -->
        <div id="vtpane-materials" style="display:none;">
            <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" style="font-size:12px;">
                <thead><tr style="background:#92400e;color:#fff;font-size:12px;">
                    <th style="padding:9px 12px;">Qty/Wt</th>
                    <th style="padding:9px 12px;">Material</th>
                    <th style="padding:9px 12px;text-align:right;">Rate</th>
                    <th style="padding:9px 12px;text-align:right;">Amount</th>
                </tr></thead>
                <tbody id="vtMatBody"></tbody>
            </table>
            </div>
        </div>
        <!-- Charges Pane -->
        <div id="vtpane-charges" style="display:none;"><div id="vtChargesContent"></div></div>
        <!-- Payments Pane -->
        <div id="vtpane-payments" style="display:none;"><div id="vtPayContent"></div></div>
    </div>
    <div class="modal-footer" style="padding:12px 20px;">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><i class="ri-close-line me-1"></i>Close</button>
    </div>
</div></div></div>

<!-- ════════════════════════════════════════
     AGENT SUMMARY MODAL
════════════════════════════════════════ -->
<div class="modal fade" id="sumModal" tabindex="-1">
<div class="modal-dialog modal-lg">
<div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;">
    <div class="modal-header text-white py-3" style="background:linear-gradient(135deg,#78350f,#d97706);">
        <h5 class="modal-title fw-bold"><i class="ri-bar-chart-grouped-line me-2"></i>Agent-wise Summary</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body p-0">
        <table class="table table-hover mb-0" style="font-size:13px;">
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
                    <a href="?agentId=<?= $as['AgentId'] ?>" class="btn btn-sm btn-outline-warning py-0 px-2" style="font-size:11px;">
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

<!-- ════════════════════════════════════════
     ADD PAYMENT MODAL
════════════════════════════════════════ -->
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
                    <div class="fw-bold mt-1" style="font-size:12px;" id="pm_trip">—</div>
                </div>
                <div class="col-4" style="border-right:1px solid #bbf7d0;">
                    <div style="font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;">Agent</div>
                    <div class="fw-bold mt-1" style="font-size:12px;color:#92400e;" id="pm_agent">—</div>
                </div>
                <div class="col-4">
                    <div style="font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;">Pending</div>
                    <div class="fw-bold mt-1" style="font-size:20px;color:#dc2626;" id="pm_due">₹0</div>
                </div>
            </div>
            <div class="prog-wrap mt-3"><div class="prog-bar" id="pm_prog" style="width:0%;background:#f59e0b;"></div></div>
            <div class="d-flex justify-content-between mt-1">
                <small class="text-muted">Received: <b id="pm_paid">₹0</b></small>
                <small class="text-muted">Net Payable: <b id="pm_total">₹0</b></small>
            </div>
        </div>
        <input type="hidden" id="pay_TripId">
        <div class="row g-3">
            <div class="col-6">
                <label class="form-label fw-semibold" style="font-size:13px;">Date <span class="text-danger">*</span></label>
                <input type="date" id="pay_Date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6">
                <label class="form-label fw-semibold" style="font-size:13px;">Amount (Rs.) <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text bg-light fw-bold">Rs.</span>
                    <input type="number" id="pay_Amount" class="form-control fw-bold" step="0.01" min="0.01" placeholder="0.00">
                </div>
            </div>
            <div class="col-6">
                <label class="form-label fw-semibold" style="font-size:13px;">Mode <span class="text-danger">*</span></label>
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
                <label class="form-label fw-semibold" style="font-size:13px;">Reference / Cheque No.</label>
                <input type="text" id="pay_Ref" class="form-control" placeholder="Optional">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold" style="font-size:13px;">Remarks</label>
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

<!-- ════════════════════════════════════════
     HISTORY MODAL
════════════════════════════════════════ -->
<div class="modal fade" id="histModal" tabindex="-1">
<div class="modal-dialog modal-lg">
<div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;">
    <div class="modal-header text-white py-3" style="background:linear-gradient(135deg,#0369a1,#0284c7);">
        <h5 class="modal-title fw-bold"><i class="ri-history-line me-2"></i>Payment History — <span id="hist_label"></span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body p-0">
        <table class="table table-bordered table-sm mb-0" style="font-size:13px;">
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

<!-- ════════════════════════════════════════
     validation.js — must be before main script
════════════════════════════════════════ -->
<script src="/Sama_Roadlines/assets/js/validation.js"></script>

<script>
/* ══════════════════════════════════════════
   MAIN JS — runs after footer loads jQuery,
   DataTables, Select2, Bootstrap, SweetAlert2
══════════════════════════════════════════ */

var dts = {};   // DataTable instances keyed by tab id

$(function(){   // $(document).ready shorthand — runs after ALL scripts loaded

    var TAB_IDS = ['all','unpaid','partial','paid','owner'];

    TAB_IDS.forEach(function(id){

        /* ── Select2 Bootstrap-5 ── */
        $('#fa_' + id).select2({
            theme:       'bootstrap-5',
            placeholder: 'All Agents',
            allowClear:  true,
            width:       '100%'
        });

        /* ── DataTable ── */
        dts[id] = $('#dt_' + id).DataTable({
            scrollX:    true,
            pageLength: 25,
            dom:        'rtip',
            columnDefs: [{ orderable: false, targets: [0, 14] }],
            language:   { paginate: { previous: '‹', next: '›' } },
            drawCallback: function(){
                var info = this.api().page.info();
                $('#fi_' + id).text(info.recordsDisplay + '/' + info.recordsTotal);
            }
        });

        /* ── Search box live filter ── */
        $('#sr_' + id).on('keyup input', function(){
            dts[id].search($(this).val()).draw();
        });

        /* ── Agent dropdown filter (column 3) ── */
        $('#fa_' + id).on('change', function(){
            var val = $(this).val() || '';
            dts[id].column(3).search(val, false, false).draw();
        });
    });
});

/* ── Tab Switch ── */
function switchTab(name){
    var clsMap = {all:'t-all', unpaid:'t-unpaid', partial:'t-partial', paid:'t-paid', owner:'t-owner'};
    ['all','unpaid','partial','paid','owner'].forEach(function(t){
        document.getElementById('nav-'  + t).className       = 'tnav';
        document.getElementById('tab-'  + t).style.display   = 'none';
    });
    document.getElementById('nav-' + name).classList.add(clsMap[name]);
    document.getElementById('tab-' + name).style.display = 'block';
    if(dts[name]){ dts[name].columns.adjust().draw(); }
}

/* ── Clear Filter ── */
function clearF(id){
    $('#fa_' + id).val(null).trigger('change');
    $('#sr_' + id).val('');
    if(dts[id]){ dts[id].search('').columns().search('').draw(); }
}

/* ── Open Add-Payment Modal ── */
function openPay(tripId, vehicle, agent, net, paid){
    var rem = Math.max(0, net - paid);
    var pct = net > 0 ? Math.min(100, Math.round(paid / net * 100)) : 0;
    $('#pay_TripId').val(tripId);
    $('#pm_trip').text('Trip #' + tripId + ' — ' + vehicle);
    $('#pm_agent').text(agent);
    $('#pm_due').text('₹' + rem.toLocaleString('en-IN', {minimumFractionDigits:2}));
    $('#pm_paid').text('₹' + parseFloat(paid).toLocaleString('en-IN', {minimumFractionDigits:2}));
    $('#pm_total').text('₹' + parseFloat(net).toLocaleString('en-IN', {minimumFractionDigits:2}));
    $('#pm_prog').css({width: pct + '%', background: pct >= 100 ? '#16a34a' : '#f59e0b'});
    $('#pay_Amount').val(rem > 0 ? rem.toFixed(2) : '');
    $('#pay_Date').val('<?= date('Y-m-d') ?>');
    $('#pay_Mode').val('Cash');
    $('#pay_Ref, #pay_Remarks').val('');
    new bootstrap.Modal(document.getElementById('payModal')).show();
}

/* ── Submit Payment ── */
function submitPay(){
    if(!$('#pay_Date').val()){
        Swal.fire({icon:'warning', title:'Date required!', toast:true, position:'top-end', showConfirmButton:false, timer:2500});
        return;
    }
    var amt = parseFloat($('#pay_Amount').val());
    if(!amt || amt <= 0){
        Swal.fire({icon:'warning', title:'Valid amount required!', toast:true, position:'top-end', showConfirmButton:false, timer:2500});
        return;
    }

    Swal.fire({title:'Saving...', allowOutsideClick:false, didOpen:function(){ Swal.showLoading(); }});

    var fd = new FormData();
    fd.append('addPayment',  1);
    fd.append('TripId',      $('#pay_TripId').val());
    fd.append('PaymentDate', $('#pay_Date').val());
    fd.append('Amount',      $('#pay_Amount').val());
    fd.append('PaymentMode', $('#pay_Mode').val());
    fd.append('Reference',   $('#pay_Ref').val());
    fd.append('Remarks',     $('#pay_Remarks').val());

    fetch('AgentPayments.php', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(res){
        Swal.close();
        if(res.status === 'success'){
            bootstrap.Modal.getInstance(document.getElementById('payModal')).hide();
            Swal.fire({icon:'success', title:'Saved!', toast:true, position:'top-end', showConfirmButton:false, timer:2500});
            setTimeout(function(){ location.reload(); }, 1800);
        } else {
            Swal.fire({icon:'error', title:'Error', text: res.msg || 'Something went wrong'});
        }
    })
    .catch(function(){
        Swal.fire({icon:'error', title:'Server Error', text:'Could not save payment.'});
    });
}

/* ── View Payment History ── */
function viewHist(tripId, vehicle, agent, payStatus){
    $('#hist_label').text('Trip #' + tripId + ' — ' + vehicle + ' (' + agent + ')');
    $('#histBody').html('<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Loading...</td></tr>');
    new bootstrap.Modal(document.getElementById('histModal')).show();

    fetch('AgentPayments.php?getTripPayments=1&TripId=' + tripId)
    .then(function(r){ return r.json(); })
    .then(function(rows){
        var html = '', total = 0;
        var icons = {Cash:'💵', Cheque:'📋', NEFT:'🏦', RTGS:'🏦', UPI:'📱', Online:'🌐'};
        var canDel = (payStatus !== 'Paid' && payStatus !== 'OwnerPaid');

        if(!rows.length){
            html = '<tr><td colspan="8" class="text-center text-muted py-3">No payments yet</td></tr>';
        } else {
            rows.forEach(function(p, i){
                total += parseFloat(p.Amount || 0);
                var delBtn = canDel
                    ? '<button class="btn btn-sm btn-outline-danger btn-ic" onclick="delPay(' + p.AgentPaymentId + ')"><i class="ri-delete-bin-line"></i></button>'
                    : '<span class="text-muted" title="Cannot delete"><i class="ri-lock-line"></i></span>';
                html += '<tr id="pr-' + p.AgentPaymentId + '">'
                    + '<td>' + (i+1) + '</td>'
                    + '<td style="white-space:nowrap">' + p.PaymentDate + '</td>'
                    + '<td>' + (icons[p.PaymentMode]||'') + ' ' + p.PaymentMode + '</td>'
                    + '<td><small>' + (p.Reference||'—') + '</small></td>'
                    + '<td class="text-end fw-bold text-success">₹' + parseFloat(p.Amount).toFixed(2) + '</td>'
                    + '<td><small>' + (p.Remarks||'—') + '</small></td>'
                    + '<td><small class="text-muted">' + (p.CreatedDate ? p.CreatedDate.substring(0,16) : '—') + '</small></td>'
                    + '<td>' + delBtn + '</td></tr>';
            });
        }
        $('#histBody').html(html);
        $('#histTotal').text('₹' + total.toFixed(2));
    })
    .catch(function(){
        $('#histBody').html('<tr><td colspan="8" class="text-center text-danger py-3">Failed to load</td></tr>');
    });
}

/* ── Delete Single Payment ── */
function delPay(pid){
    Swal.fire({title:'Delete payment?', icon:'warning', showCancelButton:true, confirmButtonText:'Delete', confirmButtonColor:'#dc3545'})
    .then(function(r){
        if(!r.isConfirmed) return;
        var fd = new FormData();
        fd.append('deletePayment', 1);
        fd.append('PaymentId', pid);
        fetch('AgentPayments.php', {method:'POST', body:fd})
        .then(function(r){ return r.json(); })
        .then(function(res){
            if(res.status === 'success'){
                $('#pr-' + pid).fadeOut(300, function(){ $(this).remove(); });
                setTimeout(function(){ location.reload(); }, 1500);
            } else {
                Swal.fire({icon:'error', title:'Error', text: res.msg});
            }
        });
    });
}

/* ══════════════════════
   VIEW TRIP DETAIL
══════════════════════ */
function viewTrip(tripId){
    Swal.fire({title:'Loading...', allowOutsideClick:false, didOpen:function(){ Swal.showLoading(); }});

    fetch('AgentPayments.php?getTripDetail=1&TripId=' + tripId)
    .then(function(r){ return r.json(); })
    .then(function(t){
        Swal.close();
        function rs(n){ return 'Rs.' + parseFloat(n||0).toLocaleString('en-IN',{minimumFractionDigits:2}); }
        if(t.error){ Swal.fire({icon:'error', title:'Error', text:t.error}); return; }

        /* ── Header ── */
        document.getElementById('vtTitle').innerHTML    = '<i class="ri-road-map-line me-2"></i>Trip #' + String(tripId).padStart(4,'0') + ' — ' + (t.VehicleNumber||'—');
        document.getElementById('vtSubtitle').textContent = (t.FromLocation||'?') + ' → ' + (t.ToLocation||'?') + '  |  ' + (t.TripDate||'');

        /* ── Info Pane ── */
        document.getElementById('vti-lr').textContent      = t.LRNo       || '—';
        document.getElementById('vti-date').textContent    = t.TripDate    || '—';
        document.getElementById('vti-vehicle').textContent = (t.VehicleNumber||'—') + (t.VehicleName ? ' (' + t.VehicleName + ')' : '');
        document.getElementById('vti-invoice').textContent = t.InvoiceNo   || '—';
        document.getElementById('vti-route').innerHTML     = '<span style="color:#d97706;font-weight:700;">' + (t.FromLocation||'?') + '</span>'
                                                           + ' <i class="ri-arrow-right-line" style="font-size:10px;color:#94a3b8;"></i> '
                                                           + '<span style="color:#dc2626;font-weight:700;">' + (t.ToLocation||'?') + '</span>';
        var sClr = {Open:'#1d4ed8', Billed:'#d97706', Closed:'#15803d'};
        var sc   = sClr[t.TripStatus] || '#64748b';
        document.getElementById('vti-status').innerHTML    = '<span style="background:' + sc + '22;color:' + sc + ';border:1px solid ' + sc + '44;padding:2px 12px;border-radius:12px;font-size:11px;font-weight:700;">' + (t.TripStatus||'—') + '</span>';
        document.getElementById('vti-agent').textContent       = t.AgentName         || '—';
        document.getElementById('vti-mobile').textContent      = t.AgentMobile        || '—';
        document.getElementById('vti-agentcity').textContent   = t.AgentCity          || '—';


        /* ── Materials Pane ── */
        var mats = t.Materials || [], mHtml = '';
        var totalWt = 0, totalAmt = 0;
        if(!mats.length){
            mHtml = '<tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:20px;">No materials added</td></tr>';
        } else {
            mats.forEach(function(m, i){
                var isU   = m.MaterialType === 'Units';
                var wt    = isU ? parseFloat(m.TotalWeight||0) : parseFloat(m.Weight||0);
                var qty   = parseInt(m.Quantity||0);
                var utype = m.UnitType || 'unit';
                var wpu   = parseFloat(m.WeightPerUnit||0);
                var qtyCol = isU
                    ? '<span style="font-size:11px;font-weight:700;color:#92400e;">' + qty + ' ' + utype + '</span>'
                    : '<span style="font-size:11px;font-weight:600;">' + wt.toFixed(3) + ' T</span>';
                var descExtra = isU
                    ? '<div style="font-size:10px;color:#64748b;margin-top:1px;">' + qty + '&times;' + (wpu*1000).toFixed(1) + 'kg = ' + wt.toFixed(3) + ' T</div>'
                    : '';
                totalWt  += wt;
                totalAmt += parseFloat(m.Amount || 0);
                mHtml += '<tr style="background:' + (i%2===0?'#fff':'#fafbfc') + ';">'
                    + '<td style="padding:7px 10px;font-size:12px;">' + qtyCol + '</td>'
                    + '<td style="padding:7px 10px;font-weight:600;font-size:13px;">' + (m.MaterialName||'—') + descExtra + '</td>'
                    + '<td style="padding:7px 10px;text-align:right;font-size:13px;">' + rs(m.Rate) + '</td>'
                    + '<td style="padding:7px 10px;text-align:right;font-size:13px;font-weight:700;color:#92400e;">' + rs(m.Amount) + '</td>'
                    + '</tr>';
            });
            mHtml += '<tr style="background:#fef3c7;font-weight:800;border-top:2px solid #fcd34d;">'
                + '<td style="padding:8px 10px;font-size:11px;">' + totalWt.toFixed(3) + ' T</td>'
                + '<td style="padding:8px 10px;color:#92400e;">Total</td>'
                + '<td></td>'
                + '<td style="padding:8px 10px;text-align:right;color:#92400e;">' + rs(totalAmt) + '</td>'
                + '</tr>';
        }
        document.getElementById('vtMatBody').innerHTML = mHtml;

        /* ── Charges Pane ── */
        function crow(lbl, val, clr){
            return '<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #fef3c7;font-size:13px;">'
                + '<span style="color:#64748b;">' + lbl + '</span>'
                + '<span style="font-weight:700;color:' + (clr||'#1e293b') + ';">' + val + '</span></div>';
        }
        var fr   = parseFloat(t.FreightAmount||0),
            lab  = parseFloat(t.LabourCharge||0),
            hold = parseFloat(t.HoldingCharge||0),
            oth  = parseFloat(t.OtherCharge||0),
            tot  = parseFloat(t.TotalAmount||0),
            cadv = parseFloat(t.CashAdvance||0),
            oadv = parseFloat(t.OnlineAdvance||0),
            tds  = parseFloat(t.TDS||0),
            net  = parseFloat(t.NetAmount||0);

        var cHtml = '<div class="row g-3">'
            + '<div class="col-md-6"><div style="background:#fffbeb;border-radius:10px;padding:14px;">'
            + '<div style="font-size:10px;font-weight:800;color:#92400e;text-transform:uppercase;border-left:3px solid #d97706;padding-left:8px;margin-bottom:10px;">Freight Charges</div>'
            + crow('Freight',           rs(fr))
            + crow('Labour',            rs(lab),  '#64748b')
            + crow('Holding',           rs(hold), '#64748b')
            + crow('Other' + (t.OtherChargeNote?' ('+t.OtherChargeNote+')':''), rs(oth), '#64748b')
            + crow('Total',             rs(tot),  '#92400e')
            + crow('Cash Advance',      '- ' + rs(cadv), '#dc2626')
            + crow('Online Advance',    '- ' + rs(oadv), '#dc2626')
            + crow('TDS',               '- ' + rs(tds),  '#7c3aed')
            + '<div style="display:flex;justify-content:space-between;padding:8px 0;font-size:14px;font-weight:900;border-top:2px solid #d97706;margin-top:4px;">'
            + '<span style="color:#92400e;">Net Payable</span><span style="color:#15803d;">' + rs(net) + '</span></div>'
            + '</div></div>'
            + '<div class="col-md-6"><div style="background:#f0fdf4;border-radius:10px;padding:14px;">'
            + '<div style="font-size:10px;font-weight:800;color:#15803d;text-transform:uppercase;border-left:3px solid #16a34a;padding-left:8px;margin-bottom:10px;">Commission</div>';

        var comm = t.Commission;
        var hasComm = comm && parseFloat(comm.CommissionAmount||0) > 0;

        if(hasComm){
            var cs = comm.CommissionStatus==='Received'
                ? '<span style="background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;padding:1px 8px;border-radius:8px;font-size:10px;font-weight:700;">✓ Received</span>'
                : '<span style="background:#fef3c7;color:#d97706;border:1px solid #fde68a;padding:1px 8px;border-radius:8px;font-size:10px;font-weight:700;">Pending</span>';
            var rf = comm.RecoveryFrom ? ' <span style="font-size:10px;color:#64748b;">(Recover from: '+comm.RecoveryFrom+')</span>' : '';
            cHtml += '<div style="padding:8px 0;">'
                + '<div style="font-size:11px;color:#15803d;font-weight:700;margin-bottom:4px;">🤝 Commission' + rf + '</div>'
                + '<div style="display:flex;justify-content:space-between;font-size:13px;align-items:center;">' + rs(comm.CommissionAmount) + cs + '</div></div>';
        }
        if(!hasComm){
            cHtml += '<div class="text-center text-muted py-3" style="font-size:12px;">No commission</div>';
        }
        cHtml += '</div></div></div>';
        document.getElementById('vtChargesContent').innerHTML = cHtml;

        /* ── Payments Pane ── */
        var pmts  = t.Payments || [];
        var icons2 = {Cash:'💵', Cheque:'📋', NEFT:'🏦', RTGS:'🏦', UPI:'📱', Online:'🌐'};
        var pHtml = '';
        if(!pmts.length){
            pHtml = '<div class="text-center text-muted py-4" style="font-size:13px;"><i class="ri-wallet-3-line me-1"></i>Koi payment nahi aayi abhi.</div>';
        } else {
            var ptotal = 0;
            pHtml = '<div class="table-responsive"><table class="table table-sm table-hover mb-0" style="font-size:13px;">'
                + '<thead style="background:#fef3c7;"><tr><th>#</th><th>Date</th><th>Mode</th><th>Reference</th><th class="text-end">Amount</th><th>Remarks</th></tr></thead><tbody>';
            pmts.forEach(function(p, i){
                ptotal += parseFloat(p.Amount||0);
                pHtml += '<tr>'
                    + '<td class="text-muted">' + (i+1) + '</td>'
                    + '<td>' + p.PaymentDate + '</td>'
                    + '<td><span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;">' + (icons2[p.PaymentMode]||'') + '&nbsp;' + p.PaymentMode + '</span></td>'
                    + '<td>' + (p.Reference||'—') + '</td>'
                    + '<td class="text-end fw-bold" style="color:#15803d;">' + rs(p.Amount) + '</td>'
                    + '<td style="font-size:11px;color:#64748b;">' + (p.Remarks||'—') + '</td></tr>';
            });
            pHtml += '</tbody><tfoot style="background:#fef3c7;font-weight:800;">'
                + '<tr><td colspan="4" class="text-end" style="color:#92400e;">Total Paid:</td>'
                + '<td class="text-end" style="color:#15803d;">' + rs(ptotal) + '</td><td></td></tr>'
                + '</tfoot></table></div>';
        }
        document.getElementById('vtPayContent').innerHTML = pHtml;

        /* Reset to info tab and show modal */
        vtSwitch('info', document.getElementById('vtbtn-info'));
        new bootstrap.Modal(document.getElementById('viewTripModal')).show();
    })
    .catch(function(){
        Swal.fire({icon:'error', title:'Error', text:'Could not load trip details.'});
    });
}

/* ── View Modal Tab Switch ── */
function vtSwitch(name, btn){
    ['info','materials','charges','payments'].forEach(function(t){
        document.getElementById('vtbtn-'  + t).className     = 'vt-tab-btn';
        document.getElementById('vtpane-' + t).style.display = 'none';
    });
    btn.classList.add('vt-tab-active');
    document.getElementById('vtpane-' + name).style.display = 'block';
}

/* ── Date range form validation ── */
document.getElementById('rangeForm').addEventListener('submit', function(e){
    var from = document.getElementById('dfFrom').value;
    var to   = document.getElementById('dfTo').value;
    if(!from){
        e.preventDefault();
        Swal.fire({icon:'warning', title:'"From" date required!', toast:true, position:'top-end', showConfirmButton:false, timer:2500});
        return;
    }
    if(!to){
        e.preventDefault();
        Swal.fire({icon:'warning', title:'"To" date required!', toast:true, position:'top-end', showConfirmButton:false, timer:2500});
        return;
    }
    if(new Date(from) > new Date(to)){
        e.preventDefault();
        Swal.fire({icon:'warning', title:'"From" cannot be after "To" date!', toast:true, position:'top-end', showConfirmButton:false, timer:2500});
    }
});
</script>

<?php require_once "../layout/footer.php"; ?>
