<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once "../../businessLogics/Admin.php";
require_once "../../businessLogics/Trip.php";
require_once "../../businessLogics/RegularTrip.php";
require_once "../../businessLogics/AgentTrip.php";
require_once "../../config/database.php";
Admin::checkAuth();

/* ── AJAX: Dropdown options from DB ── */
if (isset($_GET['get_filters'])) {
    header('Content-Type: application/json');
    $type = $_GET['type'] ?? 'Regular';

    if ($type === 'Regular') {
        $s1 = $pdo->query("
            SELECT DISTINCT p.PartyName AS label
            FROM TripMaster t
            JOIN PartyMaster p ON t.ConsignerId = p.PartyId
            WHERE t.TripType = 'Regular' AND t.FreightType = 'ToPay'
              AND p.PartyName IS NOT NULL
            ORDER BY p.PartyName ASC
        ");
    } else {
        $s1 = $pdo->query("
            SELECT DISTINCT p.PartyName AS label
            FROM TripMaster t
            JOIN PartyMaster p ON t.AgentId = p.PartyId
            WHERE t.TripType = 'Agent' AND t.FreightType = 'ToPay'
              AND p.PartyName IS NOT NULL
            ORDER BY p.PartyName ASC
        ");
    }
    $parties = $s1->fetchAll(PDO::FETCH_ASSOC);

    $typeEsc = $pdo->quote($type);
    $s2 = $pdo->query("
        SELECT DISTINCT v.VehicleNumber AS label
        FROM TripMaster t
        JOIN VehicleMaster v ON t.VehicleId = v.VehicleId
        WHERE t.TripType = $typeEsc AND t.FreightType = 'ToPay'
          AND v.VehicleNumber IS NOT NULL
        ORDER BY v.VehicleNumber ASC
    ");
    $vehicles = $s2->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['parties' => $parties, 'vehicles' => $vehicles]);
    exit();
}


/* ── AJAX: Trip Detail ── */
if (isset($_GET['getTripDetail'])) {
    header('Content-Type: application/json');
    $tid  = intval($_GET['TripId'] ?? 0);
    $type = $_GET['type'] ?? 'Regular';
    if ($type === 'Agent') {
        $trip = AgentTrip::getById($tid);
    } else {
        $trip = RegularTrip::getById($tid);
        if ($trip) {
            $materials = RegularTrip::getMaterials($tid);
            $comm      = RegularTrip::getCommission($tid);
            $vasuli    = RegularTrip::getVasuli($tid);
            $trip['Materials']  = $materials;
            $trip['Commission'] = $comm;
            $trip['Vasuli']     = $vasuli;
        }
    }
    if (!$trip) { echo json_encode(['error'=>'Not found']); exit; }
    echo json_encode($trip);
    exit();
}

/* ── Server-side Date Filter ── */
$today      = date('Y-m-d');
$datePreset = $_GET['datePreset'] ?? 'all';
$dateFrom   = $_GET['dateFrom']   ?? '';
$dateTo     = $_GET['dateTo']     ?? '';
$activeTab  = $_GET['tab']        ?? 'regular';

switch ($datePreset) {
    case 'today':     $dateFrom = $dateTo = $today; break;
    case 'yesterday': $dateFrom = $dateTo = date('Y-m-d', strtotime('-1 day')); break;
    case 'thisweek':
        $dateFrom = date('Y-m-d', strtotime('monday this week'));
        $dateTo   = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'thismonth':
        $dateFrom = date('Y-m-d', strtotime('first day of this month'));
        $dateTo   = date('Y-m-d', strtotime('last day of this month'));
        break;
    case 'custom': break;
    default: $dateFrom = $dateTo = '';
}

$allTrips = Trip::getDirectPaymentTrips();
if ($dateFrom || $dateTo) {
    $allTrips = array_values(array_filter($allTrips, function($t) use ($dateFrom, $dateTo) {
        $d = substr($t['TripDate'] ?? '', 0, 10);
        if ($dateFrom && $d < $dateFrom) return false;
        if ($dateTo   && $d > $dateTo)   return false;
        return true;
    }));
}

$regularTrips = array_values(array_filter($allTrips, fn($t) => $t['TripType'] === 'Regular'));
$agentTrips   = array_values(array_filter($allTrips, fn($t) => $t['TripType'] === 'Agent'));
$totalFreight = array_sum(array_column($allTrips, 'FreightAmount'));

/* ── Locked trips: commission received OR vasuli received ── */
$lockedTripIds = [];
if (!empty($allTrips)) {
    $ids = implode(',', array_map('intval', array_column($allTrips, 'TripId')));
    $lckStmt = $pdo->query("
        SELECT TripId FROM tripcommission WHERE TripId IN ($ids) AND CommissionStatus = 'Received'
        UNION
        SELECT TripId FROM tripvasuli    WHERE TripId IN ($ids) AND VasuliStatus      = 'Received'
    ");
    $lockedTripIds = array_flip($lckStmt->fetchAll(PDO::FETCH_COLUMN));
}

require_once "../layout/header.php";
require_once "../layout/sidebar.php";
?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<style>
/* ── Page Header ── */
.page-header-card{background:linear-gradient(135deg,#1a237e 0%,#1d4ed8 100%);border-radius:14px;padding:20px 24px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
.ph-title{font-size:20px;font-weight:800;color:#fff;margin:0;display:flex;align-items:center;gap:10px;}
.ph-sub{font-size:12px;color:rgba(255,255,255,0.65);margin-top:3px;}
/* ── Stats ── */
.stats-bar{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;}
.stat-pill{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:10px 18px;display:flex;align-items:center;gap:10px;box-shadow:0 1px 4px rgba(0,0,0,.05);flex:1;min-width:120px;}
.sp-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.sp-val{font-size:20px;font-weight:800;color:#1a237e;line-height:1;}
.sp-lbl{font-size:11px;color:#64748b;margin-top:2px;}
/* ── Tabs ── */
.trip-tabs{display:flex;border-bottom:2px solid #e2e8f0;margin-bottom:0;}
.trip-tab{padding:11px 28px;font-size:13px;font-weight:700;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;display:flex;align-items:center;gap:8px;color:#64748b;transition:all .18s;border-radius:8px 8px 0 0;user-select:none;}
.trip-tab:hover{background:#f8fafc;color:#1a237e;}
.trip-tab.active-regular{color:#1a237e;border-bottom-color:#1a237e;background:#eff6ff;}
.trip-tab.active-agent{color:#92400e;border-bottom-color:#d97706;background:#fffbeb;}
.tab-cnt{display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:20px;border-radius:10px;font-size:11px;font-weight:800;padding:0 6px;}
.tab-cnt-r{background:#e0e7ff;color:#1a237e;}
.tab-cnt-a{background:#fef3c7;color:#92400e;}
.tab-pane{display:none;}
.tab-pane.active{display:block;}
/* ── Filter card ── */
.filter-card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:0;padding:16px 20px;margin-bottom:0;}
/* ── Table heads ── */
.blue-head th{background:#f0f4ff;color:#1a237e;font-size:12px;font-weight:700;padding:10px 12px;border:none;white-space:nowrap;}
.amber-head th{background:#92400e;color:#fff;font-size:12px;font-weight:700;padding:10px 12px;border:none;white-space:nowrap;}
.card-tab{border-radius:0 0 12px 12px;border-top:none;overflow:hidden;}
/* ── Badges / Status ── */
.badge-regular{background:#e0e7ff;color:#1a237e;border:1px solid #c7d7fc;padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700;}
.badge-agent{background:#fef3c7;color:#92400e;border:1px solid #fcd34d;padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700;}
.agent-badge{background:#fef3c7;color:#92400e;border:1px solid #fcd34d;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;}
.s-open{background:#dbeafe;color:#1d4ed8;border:1px solid #93c5fd;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;}
.s-billed{background:#fef9c3;color:#854d0e;border:1px solid #fde047;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;}
.s-closed{background:#dcfce7;color:#15803d;border:1px solid #86efac;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;}
.btn-icon{width:30px;height:30px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:7px;font-size:13px;}
.action-btn-group{display:flex;gap:4px;}
/* ── Info Banner ── */
.info-banner{background:linear-gradient(135deg,#eff6ff,#dbeafe);border:1.5px solid #93c5fd;border-radius:10px;padding:10px 16px;margin-bottom:16px;font-size:12.5px;color:#1e40af;display:flex;align-items:center;gap:8px;}
/* ── Date Filter ── */
.df-preset-btn{padding:4px 13px;border-radius:20px;font-size:12px;font-weight:700;border:2px solid #e2e8f0;background:#f8fafc;color:#64748b;cursor:pointer;transition:.15s;white-space:nowrap;text-decoration:none;display:inline-block;}
.df-preset-btn:hover{border-color:#0284c7;color:#0284c7;background:#f0f9ff;}
.df-preset-btn.df-active{border-color:#0284c7;background:#0284c7;color:#fff;}
.df-range-inp{font-size:12px;font-weight:600;border:2px solid #e2e8f0 !important;border-radius:8px !important;padding:4px 8px !important;height:32px;width:140px;}
.df-range-inp:focus{border-color:#0284c7 !important;box-shadow:none !important;}
.df-tag{background:#e0f2fe;border:1px solid #bae6fd;border-radius:6px;padding:2px 10px;font-size:11px;font-weight:700;color:#0284c7;white-space:nowrap;}

/* ── Trip Detail Modal ── */
.td-tab-btn{background:none;border:none;padding:12px 18px;font-size:13px;font-weight:600;color:#64748b;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;transition:.15s;}
.td-tab-btn:hover{color:#1a237e;background:#f0f4ff;}
.td-tab-active{color:#1a237e !important;border-bottom-color:#1a237e !important;background:#eff6ff;}
.td-section-head{font-size:10px;font-weight:800;color:#1a237e;text-transform:uppercase;letter-spacing:1px;border-left:3px solid #1a237e;padding-left:8px;margin:4px 0 10px;}
.td-row{display:flex;padding:7px 0;border-bottom:1px solid #f1f5f9;font-size:13px;gap:8px;}
.td-row:last-child{border-bottom:none;}
.td-lbl{width:150px;flex-shrink:0;color:#64748b;font-size:11.5px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;padding-top:1px;}
.td-val{flex:1;color:#1e293b;font-size:13px;}
</style>

<div class="main-content app-content">
<div class="container-fluid">

<!-- ── Page Header ── -->
<div class="page-header-card">
    <div>
        <div class="ph-title"><i class="ri-arrow-right-circle-line"></i> Direct Payment Trips</div>
        <div class="ph-sub">Trips where owner received freight directly — commission tracking only</div>
    </div>
    <div class="d-flex gap-2">
        <a href="RegularTripForm.php" class="btn btn-light fw-bold" style="border-radius:9px;height:38px;font-size:13px;color:#1a237e;">
            <i class="ri-add-line me-1"></i>New Regular
        </a>
        <a href="AgentTripForm.php" class="btn btn-warning fw-bold" style="border-radius:9px;height:38px;font-size:13px;">
            <i class="ri-add-line me-1"></i>New Agent
        </a>
    </div>
</div>

<!-- ── Info Banner ── -->
<div class="info-banner">
    <i class="ri-information-line" style="font-size:18px;flex-shrink:0;"></i>
    <span><strong>Direct Pay Trips</strong> — Owner ne freight seedha party se liya. Owner payment process nahi hogi — sirf <strong>commission track</strong> hogi.</span>
</div>

<!-- ── Stats ── -->
<div class="stats-bar">
    <div class="stat-pill">
        <div class="sp-icon" style="background:#e0e7ff;"><i class="ri-arrow-right-circle-line" style="color:#1a237e;"></i></div>
        <div><div class="sp-val"><?= count($allTrips) ?></div><div class="sp-lbl">Total Direct</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-icon" style="background:#e0e7ff;"><i class="ri-file-list-line" style="color:#1a237e;"></i></div>
        <div><div class="sp-val" style="color:#1a237e;"><?= count($regularTrips) ?></div><div class="sp-lbl">Regular</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-icon" style="background:#fef3c7;"><i class="ri-user-star-line" style="color:#92400e;"></i></div>
        <div><div class="sp-val" style="color:#92400e;"><?= count($agentTrips) ?></div><div class="sp-lbl">Agent</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-icon" style="background:#f0fdf4;"><i class="ri-money-rupee-circle-line" style="color:#16a34a;"></i></div>
        <div><div class="sp-val" style="font-size:14px;color:#16a34a;">Rs.<?= number_format($totalFreight,0) ?></div><div class="sp-lbl">Total Freight</div></div>
    </div>
</div>

<!-- ── Tabs Nav ── -->
<div class="trip-tabs">
    <div class="trip-tab active-regular" id="nav-regular" onclick="switchTab('regular')">
        <i class="ri-file-list-line"></i> Regular
        <span class="tab-cnt tab-cnt-r"><?= count($regularTrips) ?></span>
    </div>
    <div class="trip-tab" id="nav-agent" onclick="switchTab('agent')">
        <i class="ri-user-star-line"></i> Agent
        <span class="tab-cnt tab-cnt-a"><?= count($agentTrips) ?></span>
    </div>
</div>

<?php
/* ── Helper: date filter row ── */
function dateFilterRow($tabId) {
    global $datePreset, $dateFrom, $dateTo, $activeTab;
    $dp    = ($activeTab === $tabId) ? $datePreset : 'all';
    $qAll  = http_build_query(array_merge($_GET, ['tab'=>$tabId,'datePreset'=>'all',      'dateFrom'=>'','dateTo'=>'']));
    $qTod  = http_build_query(array_merge($_GET, ['tab'=>$tabId,'datePreset'=>'today',    'dateFrom'=>'','dateTo'=>'']));
    $qYes  = http_build_query(array_merge($_GET, ['tab'=>$tabId,'datePreset'=>'yesterday','dateFrom'=>'','dateTo'=>'']));
    $qWeek = http_build_query(array_merge($_GET, ['tab'=>$tabId,'datePreset'=>'thisweek', 'dateFrom'=>'','dateTo'=>'']));
    $qMon  = http_build_query(array_merge($_GET, ['tab'=>$tabId,'datePreset'=>'thismonth','dateFrom'=>'','dateTo'=>'']));
    ?>
    <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
        <span style="font-size:12px;font-weight:800;color:#0284c7;white-space:nowrap;"><i class="ri-calendar-line me-1"></i>Date:</span>
        <a href="?<?= $qAll  ?>" class="df-preset-btn <?= $dp==='all'       ?'df-active':'' ?>">All</a>
        <a href="?<?= $qTod  ?>" class="df-preset-btn <?= $dp==='today'     ?'df-active':'' ?>">Today</a>
        <a href="?<?= $qYes  ?>" class="df-preset-btn <?= $dp==='yesterday' ?'df-active':'' ?>">Yesterday</a>
        <a href="?<?= $qWeek ?>" class="df-preset-btn <?= $dp==='thisweek'  ?'df-active':'' ?>">This Week</a>
        <a href="?<?= $qMon  ?>" class="df-preset-btn <?= $dp==='thismonth' ?'df-active':'' ?>">This Month</a>
        <div style="width:1px;height:24px;background:#e2e8f0;flex-shrink:0;"></div>
        <form method="GET" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
            <?php foreach($_GET as $k=>$v): if(in_array($k,['datePreset','dateFrom','dateTo','tab'])) continue; ?>
            <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
            <?php endforeach; ?>
            <input type="hidden" name="tab" value="<?= $tabId ?>">
            <input type="hidden" name="datePreset" value="custom">
            <span style="font-size:12px;font-weight:700;color:#64748b;">From</span>
            <input type="date" name="dateFrom" value="<?= htmlspecialchars($dp==='custom'?$dateFrom:'') ?>" class="form-control df-range-inp">
            <span style="font-size:12px;font-weight:700;color:#64748b;">To</span>
            <input type="date" name="dateTo"   value="<?= htmlspecialchars($dp==='custom'?$dateTo:'') ?>"   class="form-control df-range-inp">
            <button type="submit" class="btn btn-sm btn-primary fw-bold" style="height:32px;border-radius:8px;font-size:12px;padding:0 12px;"><i class="ri-search-line"></i> Go</button>
        </form>
        <?php if($dp!=='all'&&$dp!==''): ?>
        <span class="df-tag">
            <?php
            if($dp==='today')     echo 'Today: '.date('d-m-Y');
            elseif($dp==='yesterday') echo 'Yesterday: '.date('d-m-Y',strtotime('-1 day'));
            elseif($dp==='thisweek')  echo date('d-m-Y',strtotime('monday this week')).' → '.date('d-m-Y',strtotime('sunday this week'));
            elseif($dp==='thismonth') echo date('F Y');
            elseif($dp==='custom')    echo ($dateFrom?date('d-m-Y',strtotime($dateFrom)):'').' → '.($dateTo?date('d-m-Y',strtotime($dateTo)):'');
            ?>
        </span>
        <?php endif; ?>
    </div>
    <?php
}

/* ── Helper: table row (shared for both tabs) ── */
function tripRow($r, $lockedTripIds = []) {
    $isAgent  = $r['TripType'] === 'Agent';
    $isLocked = isset($lockedTripIds[$r['TripId']]);
    $editUrl  = $isAgent ? "AgentTripForm.php?TripId={$r['TripId']}" : "RegularTripForm.php?TripId={$r['TripId']}";
    $st      = $r['TripStatus'] ?? 'Open';
    $fr      = floatval($r['FreightAmount']  ?? 0);
    $lab     = floatval($r['LabourCharge']   ?? 0);
    $hld     = floatval($r['HoldingCharge']  ?? 0);
    $oth     = floatval($r['OtherCharge']    ?? 0);
    $tds     = floatval($r['TDS']            ?? 0);
    $cash    = floatval($r['CashAdvance']    ?? 0);
    $online  = floatval($r['OnlineAdvance']  ?? 0);
    $adv     = floatval($r['AdvanceAmount']  ?? 0);
    $net     = floatval($r['NetAmount']      ?? 0);
    ?>
    <tr>
        <td style="font-size:12px;white-space:nowrap;"><?= htmlspecialchars($r['TripDate']??'') ?></td>
        <td>
            <div class="fw-bold" style="font-size:12.5px;"><?= htmlspecialchars($r['VehicleNumber']??'—') ?></div>
            <?php if(!empty($r['VehicleName'])): ?><div style="font-size:11px;color:#64748b;"><?= htmlspecialchars($r['VehicleName']) ?></div><?php endif; ?>
        </td>
        <td>
            <?php if($isAgent): ?>
            <span class="agent-badge"><?= htmlspecialchars($r['AgentName']??'—') ?></span>
            <?php else: ?>
            <div style="font-size:12px;font-weight:600;"><?= htmlspecialchars($r['ConsignerName']??'—') ?></div>
            <div style="font-size:11px;color:#64748b;">→ <?= htmlspecialchars($r['ConsigneeName']??'—') ?></div>
            <?php endif; ?>
        </td>
        <td style="font-size:12px;">
            <?php if(!empty($r['FromLocation'])||!empty($r['ToLocation'])): ?>
            <span style="color:#1d4ed8;font-weight:600;"><?= htmlspecialchars($r['FromLocation']??'?') ?></span>
            <i class="ri-arrow-right-line" style="font-size:10px;color:#94a3b8;"></i>
            <span style="color:#dc2626;font-weight:600;"><?= htmlspecialchars($r['ToLocation']??'?') ?></span>
            <?php else: echo '<span class="text-muted">—</span>'; endif; ?>
        </td>
        <td class="text-end" style="font-size:13px;font-weight:800;color:#1a237e;white-space:nowrap;">Rs.<?= number_format($fr,0) ?></td>
        <td class="text-end" style="font-size:11px;line-height:1.75;white-space:nowrap;">
            <?php if($lab>0): ?><div style="color:#b45309;font-weight:600;">+Labour <b>Rs.<?= number_format($lab,0) ?></b></div><?php endif; ?>
            <?php if($hld>0): ?><div style="color:#b45309;font-weight:600;">+Holding <b>Rs.<?= number_format($hld,0) ?></b></div><?php endif; ?>
            <?php if($oth>0): ?><div style="color:#b45309;font-weight:600;">+Other <b>Rs.<?= number_format($oth,0) ?></b></div><?php endif; ?>
            <?php if($lab==0&&$hld==0&&$oth==0): ?><span style="color:#94a3b8;">—</span><?php endif; ?>
        </td>
        <td class="text-end" style="font-size:12px;white-space:nowrap;<?= $tds>0?'font-weight:800;color:#dc2626;':'color:#94a3b8;' ?>">
            <?= $tds>0 ? 'Rs.'.number_format($tds,0) : '—' ?>
        </td>
        <td class="text-end" style="font-size:11px;line-height:1.75;white-space:nowrap;">
            <?php if($cash>0): ?><div style="color:#7c3aed;font-weight:600;">Cash <b>Rs.<?= number_format($cash,0) ?></b></div><?php endif; ?>
            <?php if($online>0): ?><div style="color:#7c3aed;font-weight:600;">Online <b>Rs.<?= number_format($online,0) ?></b></div><?php endif; ?>
            <?php if($adv>0&&$cash==0&&$online==0): ?><div style="color:#7c3aed;font-weight:800;">Rs.<?= number_format($adv,0) ?></div><?php endif; ?>
            <?php if($adv==0): ?><span style="color:#94a3b8;">—</span><?php endif; ?>
        </td>
        <td class="text-end" style="font-size:13px;font-weight:900;color:#15803d;white-space:nowrap;">Rs.<?= number_format($net,0) ?></td>
        <!-- Commission / Vasuli -->
        <td style="font-size:11px;line-height:1.85;white-space:nowrap;">
            <?php
            $comm  = floatval($r['CommissionAmount'] ?? 0);
            $vas   = floatval($r['VasuliAmount']     ?? 0);
            $cStat = $r['CommissionStatus'] ?? 'Regular';
            $vStat = $r['VasuliStatus']     ?? 'Regular';
            if ($comm > 0):
                $cClr = $cStat==='Received' ? '#15803d' : '#d97706';
                $cBg  = $cStat==='Received' ? '#dcfce7' : '#fef3c7';
            ?><div><span style="background:<?=$cBg?>;color:<?=$cClr?>;border:1px solid <?=$cClr?>44;padding:2px 7px;border-radius:10px;font-size:10px;font-weight:700;">🤝 Rs.<?=number_format($comm,0)?><?=$cStat==='Received'?' ✓':''?></span></div><?php endif; ?>
            <?php if ($vas > 0):
                $vClr = $vStat==='Received' ? '#15803d' : '#0284c7';
                $vBg  = $vStat==='Received' ? '#dcfce7' : '#e0f2fe';
            ?><div><span style="background:<?=$vBg?>;color:<?=$vClr?>;border:1px solid <?=$vClr?>44;padding:2px 7px;border-radius:10px;font-size:10px;font-weight:700;">🪙 Rs.<?=number_format($vas,0)?><?=$vStat==='Received'?' ✓':''?></span></div><?php endif; ?>
            <?php if($comm==0 && $vas==0): ?><span style="color:#cbd5e1;">—</span><?php endif; ?>
        </td>
        <td>
            <?php
            if($st==='Open')       echo '<span class="s-open"><i class="ri-time-line me-1"></i>Open</span>';
            elseif($st==='Billed') echo '<span class="s-billed"><i class="ri-bill-line me-1"></i>Billed</span>';
            elseif($st==='Closed') echo '<span class="s-closed"><i class="ri-checkbox-circle-line me-1"></i>Closed</span>';
            else                   echo '<span class="s-open">'.$st.'</span>';
            ?>
        </td>
        <td>
            <div class="action-btn-group">
                <?php if($isLocked): ?>
                <span class="btn btn-sm btn-secondary btn-icon" title="Commission/Vasuli receive ho chuki — edit locked" style="cursor:not-allowed;opacity:.6;">
                    <i class="ri-lock-line"></i>
                </span>
                <?php else: ?>
                <a href="<?= $editUrl ?>" class="btn btn-sm btn-primary btn-icon" title="Edit"><i class="ri-edit-line"></i></a>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-info btn-icon" title="View Details"
                    onclick='showTrip(<?= $r["TripId"] ?>, "<?= $r["TripType"] ?>")'><i class="ri-eye-line"></i></button>
                <?php if(!$isAgent): ?>
                <a href="GCNote_print.php?TripId=<?= $r['TripId'] ?>" target="_blank" class="btn btn-sm btn-outline-dark btn-icon" title="Print GC"><i class="ri-printer-line"></i></a>
                <?php endif; ?>
            </div>
        </td>
    </tr>
    <?php
}
?>

<!-- ══════════════════════════
     TAB: REGULAR
══════════════════════════ -->
<div class="tab-pane active" id="tab-regular">
    <div class="filter-card">
        <?php dateFilterRow('regular'); ?>
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold fs-12 mb-1"><i class="ri-building-line me-1"></i>Consigner</label>
                <select id="fCons_reg" style="width:100%;"><option value="">-- All Consigners --</option></select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold fs-12 mb-1"><i class="ri-truck-line me-1"></i>Vehicle</label>
                <select id="fVeh_reg" style="width:100%;"><option value="">-- All Vehicles --</option></select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button class="btn btn-outline-secondary btn-sm w-100" onclick="clearFilter('reg')" title="Clear"><i class="ri-refresh-line"></i></button>
            </div>
            <div class="col-md-4 ms-auto">
                <label class="form-label fw-semibold fs-12 mb-1"><i class="ri-search-line me-1"></i>Search</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0" style="border-radius:8px 0 0 8px;"><i class="ri-search-line text-muted"></i></span>
                    <input type="text" id="srch_reg" class="form-control border-start-0 ps-1" placeholder="Vehicle, Consigner, Route..." style="border-radius:0;box-shadow:none;">
                    <span id="fi_reg" class="input-group-text bg-primary text-white fw-bold" style="border-radius:0 8px 8px 0;font-size:11px;min-width:52px;justify-content:center;"></span>
                </div>
            </div>
        </div>
    </div>
    <div class="card custom-card shadow-sm card-tab">
    <div class="card-body p-0"><div class="table-responsive">
    <table id="dt_reg" class="table table-hover align-middle mb-0 w-100">
        <thead><tr class="blue-head">
            <th style="min-width:92px;">Date</th>
            <th style="min-width:110px;">Vehicle</th>
            <th style="min-width:160px;">Consigner → Consignee</th>
            <th style="min-width:140px;">Route</th>
            <th class="text-end" style="min-width:82px;">Freight</th>
            <th class="text-end" style="min-width:105px;">+ Charges</th>
            <th class="text-end" style="min-width:70px;">TDS</th>
            <th class="text-end" style="min-width:90px;">− Advance</th>
            <th class="text-end" style="min-width:90px;">Net Payable</th>
            <th style="min-width:120px;">Comm / Vasuli</th>
            <th style="min-width:80px;">Status</th>
            <th style="width:75px;">Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach($regularTrips as $r): tripRow($r, $lockedTripIds); endforeach; ?>
        </tbody>
    </table>
    </div></div></div>
</div>

<!-- ══════════════════════════
     TAB: AGENT
══════════════════════════ -->
<div class="tab-pane" id="tab-agent">
    <div class="filter-card">
        <?php dateFilterRow('agent'); ?>
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold fs-12 mb-1"><i class="ri-user-star-line me-1"></i>Agent</label>
                <select id="fAgent_agt" style="width:100%;"><option value="">-- All Agents --</option></select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold fs-12 mb-1"><i class="ri-truck-line me-1"></i>Vehicle</label>
                <select id="fVeh_agt" style="width:100%;"><option value="">-- All Vehicles --</option></select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button class="btn btn-outline-secondary btn-sm w-100" onclick="clearFilter('agt')" title="Clear"><i class="ri-refresh-line"></i></button>
            </div>
            <div class="col-md-4 ms-auto">
                <label class="form-label fw-semibold fs-12 mb-1"><i class="ri-search-line me-1"></i>Search</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0" style="border-radius:8px 0 0 8px;"><i class="ri-search-line text-muted"></i></span>
                    <input type="text" id="srch_agt" class="form-control border-start-0 ps-1" placeholder="Vehicle, Agent, Route..." style="border-radius:0;box-shadow:none;">
                    <span id="fi_agt" class="input-group-text bg-primary text-white fw-bold" style="border-radius:0 8px 8px 0;font-size:11px;min-width:52px;justify-content:center;"></span>
                </div>
            </div>
        </div>
    </div>
    <div class="card custom-card shadow-sm card-tab">
    <div class="card-body p-0"><div class="table-responsive">
    <table id="dt_agt" class="table table-hover align-middle mb-0 w-100">
        <thead><tr class="amber-head">
            <th style="min-width:92px;">Date</th>
            <th style="min-width:110px;">Vehicle</th>
            <th style="min-width:130px;">Agent</th>
            <th style="min-width:140px;">Route</th>
            <th class="text-end" style="min-width:82px;">Freight</th>
            <th class="text-end" style="min-width:105px;">+ Charges</th>
            <th class="text-end" style="min-width:70px;">TDS</th>
            <th class="text-end" style="min-width:90px;">− Advance</th>
            <th class="text-end" style="min-width:90px;">Net Payable</th>
            <th style="min-width:120px;">Comm / Vasuli</th>
            <th style="min-width:80px;">Status</th>
            <th style="width:75px;">Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach($agentTrips as $r): tripRow($r, $lockedTripIds); endforeach; ?>
        </tbody>
    </table>
    </div></div></div>
</div>

</div><!-- container-fluid -->
</div><!-- main-content -->

<script>
var dtReg, dtAgt;

$(document).ready(function(){
    var baseCfg = {
        scrollX: true, pageLength: 25, dom: 'rtip',
        columnDefs: [{ orderable: false, targets: [11] }],
        language: { paginate: { previous: '‹', next: '›' }, emptyTable: 'Koi trip nahi mili.' }
    };

    dtReg = $('#dt_reg').DataTable(Object.assign({}, baseCfg, {
        drawCallback: function(){ var i=this.api().page.info(); $('#fi_reg').text(i.recordsDisplay+'/'+i.recordsTotal); }
    }));
    dtAgt = $('#dt_agt').DataTable(Object.assign({}, baseCfg, {
        drawCallback: function(){ var i=this.api().page.info(); $('#fi_agt').text(i.recordsDisplay+'/'+i.recordsTotal); }
    }));

    // Search boxes
    $('#srch_reg').on('keyup input', function(){ dtReg.search($(this).val()).draw(); });
    $('#srch_agt').on('keyup input', function(){ dtAgt.search($(this).val()).draw(); });

    // Init Select2 — bootstrap-5 theme
    $('#fCons_reg').select2({ theme:'bootstrap-5', allowClear:true, placeholder:'-- All Consigners --', width:'100%' });
    $('#fVeh_reg').select2({  theme:'bootstrap-5', allowClear:true, placeholder:'-- All Vehicles --',   width:'100%' });
    $('#fAgent_agt').select2({ theme:'bootstrap-5', allowClear:true, placeholder:'-- All Agents --',    width:'100%' });
    $('#fVeh_agt').select2({   theme:'bootstrap-5', allowClear:true, placeholder:'-- All Vehicles --',  width:'100%' });

    // Load Regular dropdowns (Consigner + Vehicle)
    fetch(window.location.pathname + '?get_filters=1&type=Regular')
        .then(r => r.json())
        .then(function(data){
            data.parties.forEach(function(item){
                $('#fCons_reg').append(new Option(item.label, item.label, false, false));
            });
            data.vehicles.forEach(function(item){
                $('#fVeh_reg').append(new Option(item.label, item.label, false, false));
            });
            $('#fCons_reg, #fVeh_reg').trigger('change.select2');
        })
        .catch(function(){ console.warn('Regular filter load failed'); });

    // Load Agent dropdowns (Agent + Vehicle)
    fetch(window.location.pathname + '?get_filters=1&type=Agent')
        .then(r => r.json())
        .then(function(data){
            data.parties.forEach(function(item){
                $('#fAgent_agt').append(new Option(item.label, item.label, false, false));
            });
            data.vehicles.forEach(function(item){
                $('#fVeh_agt').append(new Option(item.label, item.label, false, false));
            });
            $('#fAgent_agt, #fVeh_agt').trigger('change.select2');
        })
        .catch(function(){ console.warn('Agent filter load failed'); });

    function escReg(s){ return s.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'); }

    // Regular tab: col 0=Date, 1=Vehicle, 2=Consigner, 3=Route
    $('#fCons_reg').on('change', function(){ dtReg.column(2).search(this.value ? '^'+escReg(this.value)+'$' : '', true, false).draw(); });
    $('#fVeh_reg').on('change',  function(){ dtReg.column(1).search(this.value || '', false, false).draw(); });

    // Agent tab: col 0=Date, 1=Vehicle, 2=Agent, 3=Route
    $('#fAgent_agt').on('change', function(){ dtAgt.column(2).search(this.value ? '^'+escReg(this.value)+'$' : '', true, false).draw(); });
    $('#fVeh_agt').on('change',   function(){ dtAgt.column(1).search(this.value || '', false, false).draw(); });

    // Restore active tab from URL
    var urlTab = '<?= htmlspecialchars($activeTab) ?>';
    if (urlTab && urlTab !== 'regular') switchTab(urlTab);
});

function switchTab(name) {
    ['regular','agent'].forEach(function(t){
        document.getElementById('nav-'+t).className = 'trip-tab';
        document.getElementById('tab-'+t).classList.remove('active');
    });
    var cls = { regular:'active-regular', agent:'active-agent' };
    document.getElementById('nav-'+name).classList.add(cls[name]);
    document.getElementById('tab-'+name).classList.add('active');
    // Adjust DataTable columns for proper display
    ({ regular: dtReg, agent: dtAgt })[name].columns.adjust();
    // Update URL without reload
    var url = new URL(window.location.href);
    url.searchParams.set('tab', name);
    history.replaceState(null, '', url.toString());
}

function clearFilter(tab) {
    if (tab === 'reg') {
        $('#fCons_reg').val(null).trigger('change');
        $('#fVeh_reg').val(null).trigger('change');
        $('#srch_reg').val('');
        dtReg.search('').columns().search('').draw();
    } else {
        $('#fAgent_agt').val(null).trigger('change');
        $('#fVeh_agt').val(null).trigger('change');
        $('#srch_agt').val('');
        dtAgt.search('').columns().search('').draw();
    }
}

    <?php if(!empty($_GET['locked'])): ?>
    $(document).ready(function(){
        <?php $r = $_GET['reason'] ?? ''; ?>
        <?php if($r === 'commission_received'): ?>
        SRV.toast.error('⛔ Trip lock hai — Commission recover ho chuki hai, edit nahi kar sakte.');
        <?php elseif($r === 'vasuli_received'): ?>
        SRV.toast.error('⛔ Trip lock hai — Vasuli recover ho chuki hai, edit nahi kar sakte.');
        <?php elseif($r === 'owner_paid'): ?>
        SRV.toast.error('⛔ Trip lock hai — Owner Payment ho chuki hai, edit nahi kar sakte.');
        <?php elseif($r === 'agent_paid'): ?>
        SRV.toast.error('⛔ Trip lock hai — Agent Payment ho chuki hai, edit nahi kar sakte.');
        <?php else: ?>
        SRV.toast.error('⛔ Trip lock hai — editing is not allowed.');
        <?php endif; ?>
    });
    <?php endif; ?>


    function rupee(n){ return 'Rs.'+parseFloat(n||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}); }

    function showTrip(tripId, tripType) {
        Swal.fire({ title:'Loading...', allowOutsideClick:false, didOpen:()=>Swal.showLoading() });

        fetch('DirectTrips.php?getTripDetail=1&TripId='+tripId+'&type='+tripType)
        .then(r=>r.json())
        .then(function(t){
            Swal.close();
            if(t.error){ Swal.fire({icon:'error',title:'Error',text:t.error}); return; }

            var isAgent = tripType === 'Agent';
            var headerColor = isAgent ? 'linear-gradient(135deg,#78350f,#d97706)' : 'linear-gradient(135deg,#1a237e,#1d4ed8)';
            var accentClr   = isAgent ? '#92400e' : '#1a237e';
            var statusBgMap = {Open:'#dbeafe',Billed:'#fef9c3',Closed:'#dcfce7'};
            var statusClMap = {Open:'#1d4ed8',Billed:'#854d0e',Closed:'#15803d'};
            var sb = statusBgMap[t.TripStatus]||'#f8fafc';
            var sc = statusClMap[t.TripStatus]||'#64748b';

            // ── Badges ──
            var ownerClr = t.FreightType==='ToPay' ? '#16a34a' : '#dc2626';
            var badges = `
                <span style="background:#eff6ff;color:#1a237e;border:1px solid #93c5fd;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;">Trip #${t.TripId}</span>
                <span style="background:${sb};color:${sc};border:1px solid ${sc}40;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;">${t.TripStatus}</span>
                <span style="color:${ownerClr};border:1px solid currentColor;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;">
                  ${t.FreightType==='ToPay'?'⚡ ToPay':'⏳ Owner Pending'}
                </span>`;

            // ── TAB 1: Trip Info ──
            var partySection = isAgent
                ? `<div class="td-section-head mt-3">⭐ Agent</div>
                   <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:10px;padding:12px 16px;display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                     <div><div style="font-size:10px;color:#92400e;font-weight:700;text-transform:uppercase;margin-bottom:3px;">Name</div><div style="font-size:13px;font-weight:800;color:#78350f;">${t.AgentName||'—'}</div></div>
                     <div><div style="font-size:10px;color:#92400e;font-weight:700;text-transform:uppercase;margin-bottom:3px;">Mobile</div><div style="font-size:13px;">${t.AgentMobile?`<a href="tel:${t.AgentMobile}" style="color:#0284c7;font-weight:700;text-decoration:none;">📞 ${t.AgentMobile}</a>`:'<span style="color:#94a3b8;">—</span>'}</div></div>
                     <div><div style="font-size:10px;color:#92400e;font-weight:700;text-transform:uppercase;margin-bottom:3px;">City</div><div style="font-size:13px;font-weight:600;">${t.AgentCity||'—'}</div></div>
                     <div><div style="font-size:10px;color:#92400e;font-weight:700;text-transform:uppercase;margin-bottom:3px;">Address</div><div style="font-size:12px;color:#475569;">${t.AgentAddress||'—'}</div></div>
                   </div>`
                : `<div class="td-section-head mt-3">🤝 Parties</div>
                   <div style="overflow-x:auto;margin-top:6px;">
                   <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                     <thead><tr style="background:#f0f4ff;">
                       <th style="padding:8px 10px;color:#64748b;font-size:11px;font-weight:700;border-bottom:2px solid #e2e8f0;width:90px;"></th>
                       <th style="padding:8px 10px;color:#1a237e;font-weight:800;font-size:12px;border-bottom:2px solid #e2e8f0;">📤 Consigner</th>
                       <th style="padding:8px 10px;color:#15803d;font-weight:800;font-size:12px;border-bottom:2px solid #e2e8f0;">📥 Consignee</th>
                     </tr></thead>
                     <tbody>
                       <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:7px 10px;color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;">Name</td><td style="padding:7px 10px;font-weight:700;">${t.ConsignerName||'—'}</td><td style="padding:7px 10px;font-weight:700;">${t.ConsigneeName||'—'}</td></tr>
                       <tr style="background:#fafafa;border-bottom:1px solid #f1f5f9;"><td style="padding:7px 10px;color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;">Mobile</td>
                         <td style="padding:7px 10px;">${t.ConsignerMobile?`<a href="tel:${t.ConsignerMobile}" style="color:#0284c7;font-weight:600;text-decoration:none;">📞 ${t.ConsignerMobile}</a>`:'<span style="color:#94a3b8;">—</span>'}</td>
                         <td style="padding:7px 10px;">${t.ConsigneeContactNo?`<a href="tel:${t.ConsigneeContactNo}" style="color:#0284c7;font-weight:600;text-decoration:none;">📞 ${t.ConsigneeContactNo}</a>`:'<span style="color:#94a3b8;">—</span>'}</td></tr>
                       <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:7px 10px;color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;">City</td><td style="padding:7px 10px;">${t.ConsignerCity||'—'}</td><td style="padding:7px 10px;">${t.ConsigneeCity||'—'}</td></tr>
                       <tr><td style="padding:7px 10px;color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;">Address</td><td style="padding:7px 10px;font-size:12px;color:#475569;">${t.ConsignerAddress||'—'}</td><td style="padding:7px 10px;font-size:12px;color:#475569;">${t.ConsigneeAddress||'—'}</td></tr>
                     </tbody>
                   </table></div>`;

            var infoHtml = `
                <div class="td-section-head">📋 Basic</div>
                <div class="td-row"><span class="td-lbl">📅 Trip Date</span><span class="td-val fw-bold" style="color:${accentClr};">${t.TripDate||'—'}</span></div>
                ${!isAgent ? `<div class="td-row"><span class="td-lbl">🧾 Invoice / LR</span><span class="td-val">${t.InvoiceNo||'—'} / ${t.LRNo||'—'}</span></div>` : ''}
                <div class="td-row"><span class="td-lbl">🚛 Vehicle</span><span class="td-val fw-bold">${t.VehicleNumber||'—'}${t.VehicleName?' <small class="text-muted">'+t.VehicleName+'</small>':''}</span></div>
                <div class="td-row"><span class="td-lbl">📍 Route</span><span class="td-val"><span style="color:#1d4ed8;font-weight:700;">${t.FromLocation||'?'}</span> <i class="ri-arrow-right-line mx-1" style="font-size:11px;color:#94a3b8;"></i> <span style="color:#dc2626;font-weight:700;">${t.ToLocation||'?'}</span></span></div>
                ${partySection}
                <div class="td-section-head mt-3">🧑 Driver</div>
                <div class="td-row"><span class="td-lbl">👨 Name</span><span class="td-val">${t.DriverName||'—'}</span></div>
                <div class="td-row"><span class="td-lbl">📞 Contact</span><span class="td-val">${t.DriverContactNo||'—'}</span></div>
                <div class="td-row"><span class="td-lbl">🪪 Aadhar</span><span class="td-val">${t.DriverAadharNo||'—'}</span></div>
                ${t.Remarks?`<div class="mt-2" style="background:#fefce8;border:1px solid #fde68a;border-radius:8px;padding:8px 12px;font-size:12px;color:#854d0e;"><i class="ri-chat-1-line me-1"></i>${t.Remarks}</div>`:''}`;

            // ── TAB 3: Charges ──
            var cash   = parseFloat(t.CashAdvance||0);
            var online = parseFloat(t.OnlineAdvance||0);
            var advHtml = (cash>0||online>0)
                ? `<tr><td class="text-danger">➖ Cash Advance</td><td class="text-end text-danger">- ${rupee(cash)}</td></tr>
                   <tr><td class="text-danger">➖ Online Advance</td><td class="text-end text-danger">- ${rupee(online)}</td></tr>`
                : `<tr><td class="text-danger">➖ Advance Paid</td><td class="text-end text-danger">- ${rupee(t.AdvanceAmount||0)}</td></tr>`;
            var chargesHtml = `
                <table class="table table-sm mb-0" style="font-size:13px;">
                  <tr style="background:#f0f4ff;"><td class="fw-bold">🚛 Freight Amount</td><td class="text-end fw-bold" style="color:${accentClr};">${rupee(t.FreightAmount)}</td></tr>
                  <tr><td class="text-muted">👷 Labour Charge</td><td class="text-end">${rupee(t.LabourCharge)}</td></tr>
                  <tr><td class="text-muted">⏱️ Holding / Detention</td><td class="text-end">${rupee(t.HoldingCharge)}</td></tr>
                  <tr><td class="text-muted">➕ Other Charge ${t.OtherChargeNote?'<small class="text-muted">('+t.OtherChargeNote+')</small>':''}</td><td class="text-end">${rupee(t.OtherCharge)}</td></tr>
                  <tr style="border-top:2px solid #e2e8f0;"><td class="fw-bold">📊 Total Amount</td><td class="text-end fw-bold">${rupee(t.TotalAmount)}</td></tr>
                  ${advHtml}
                  <tr><td class="text-danger">➖ TDS</td><td class="text-end text-danger">- ${rupee(t.TDS)}</td></tr>
                  <tr style="background:#dcfce7;"><td class="fw-bold" style="color:#15803d;">💰 Net Payable</td><td class="text-end fw-bold" style="color:#15803d;">${rupee(t.NetAmount)}</td></tr>
                </table>`;

            // ── TAB 2: Materials ──
            var mats = isAgent ? (t.Materials||[]) : (t.Materials||[]);
            var matHtml = '';
            if(mats.length === 0){
                matHtml = '<div class="text-center text-muted py-4" style="font-size:13px;">No materials added for this trip.</div>';
            } else {
                var totalWt=0, totalAmt=0;
                var rows = mats.map(function(m,i){
                    var isU  = m.MaterialType==='Units';
                    var wt   = isU ? parseFloat(m.TotalWeight||0) : parseFloat(m.Weight||0);
                    var qty  = parseInt(m.Quantity||0);
                    var utype= m.UnitType||'unit';
                    var wpu  = parseFloat(m.WeightPerUnit||0);
                    var qtyCol = isU
                        ? `<span style="font-size:11px;font-weight:700;color:${accentClr};">${qty} ${utype}</span>`
                        : `<span style="font-size:11px;">${wt.toFixed(3)} T</span>`;
                    var descExtra = isU
                        ? `<div style="font-size:10px;color:#64748b;margin-top:2px;">${qty} × ${(wpu*1000).toFixed(1)}kg = ${wt.toFixed(3)} T</div>`
                        : '';
                    totalWt  += wt; totalAmt += parseFloat(m.Amount||0);
                    return `<tr><td class="text-muted" style="font-size:11px;">${i+1}</td><td>${qtyCol}</td><td class="fw-semibold">${m.MaterialName||'—'}${descExtra}</td><td class="text-center" style="font-size:11px;">${rupee(m.Rate)}</td><td class="text-end fw-bold" style="color:${accentClr};">${rupee(m.Amount)}</td></tr>`;
                }).join('');
                matHtml = `<div class="table-responsive">
                  <table class="table table-sm table-hover mb-0" style="font-size:13px;">
                    <thead style="background:${accentClr};color:#fff;">
                      <tr><th style="width:28px;">#</th><th style="width:80px;">Qty/Wt</th><th>Material</th><th class="text-center">Rate</th><th class="text-end">Amount</th></tr>
                    </thead>
                    <tbody>${rows}</tbody>
                    <tfoot style="background:#f0f4ff;font-weight:800;">
                      <tr><td colspan="2" class="text-end" style="color:${accentClr};">Total</td><td style="font-size:11px;color:${accentClr};">${totalWt.toFixed(3)} T</td><td></td><td class="text-end" style="color:${accentClr};">${rupee(totalAmt)}</td></tr>
                    </tfoot>
                  </table></div>`;
            }

            // Commission + Vasuli (both types have these)
            var comm   = isAgent ? null : (t.Commission||null);
            var vasuli = isAgent ? null : (t.Vasuli||null);
            // For agent, commission is inline in trip data
            var commHtml = '';
            if(!isAgent && comm && parseFloat(comm.CommissionAmount||0)>0){
                var cs = comm.CommissionStatus==='Received'
                    ? '<span style="background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;">Received ✓</span>'
                    : '<span style="background:#fef2f2;color:#dc2626;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;">Pending</span>';
                commHtml = `<div class="mt-3" style="background:#f0f4ff;border:1px solid #c7d7fc;border-radius:8px;padding:10px 14px;display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:12px;color:#1a237e;font-weight:700;"><i class="ri-percent-line me-1"></i>Commission &nbsp;<small style="color:#64748b;font-size:10px;">(Recovery: ${comm.RecoveryFrom})</small></span>
                    <span style="font-weight:800;color:#1a237e;">${rupee(comm.CommissionAmount)} &nbsp;${cs}</span></div>`;
            } else if(isAgent && parseFloat(t.CommissionAmount||0)>0){
                commHtml = `<div class="mt-3" style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:10px 14px;display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:12px;color:#92400e;font-weight:700;"><i class="ri-percent-line me-1"></i>Commission</span>
                    <span style="font-weight:800;color:#92400e;">${rupee(t.CommissionAmount)}</span></div>`;
            }
            var vasuliHtml = '';
            if(!isAgent && vasuli && parseFloat(vasuli.VasuliAmount||0)>0){
                var vs = vasuli.VasuliStatus==='Received'
                    ? '<span style="background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;">Received ✓</span>'
                    : '<span style="background:#fef2f2;color:#dc2626;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;">Pending</span>';
                vasuliHtml = `<div class="mt-2" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:12px;color:#15803d;font-weight:700;"><i class="ri-hand-coin-line me-1"></i>Vasuli &nbsp;<small style="color:#64748b;font-size:10px;">(From: ${vasuli.RecoverFrom})</small></span>
                    <span style="font-weight:800;color:#15803d;">${rupee(vasuli.VasuliAmount)} &nbsp;${vs}</span></div>`;
            }

            // ── Build modal HTML ──
            var html = `
              <style>
                .dt2-nav{display:flex;border-bottom:2px solid #e2e8f0;background:#f8fafc;}
                .dt2-btn{background:none;border:none;padding:12px 18px;font-size:13px;font-weight:600;color:#64748b;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;transition:.15s;}
                .dt2-btn:hover{color:${accentClr};background:#f0f4ff;}
                .dt2-active{color:${accentClr}!important;border-bottom-color:${accentClr}!important;background:#eff6ff;}
                .dt2-body{padding:18px 22px;max-height:62vh;overflow-y:auto;}
                .dt2-section{font-size:10px;font-weight:800;color:${accentClr};text-transform:uppercase;letter-spacing:1px;border-left:3px solid ${accentClr};padding-left:8px;margin:4px 0 10px;}
                .dt2-row{display:flex;padding:7px 0;border-bottom:1px solid #f1f5f9;font-size:13px;gap:8px;}
                .dt2-row:last-child{border-bottom:none;}
                .dt2-lbl{width:140px;flex-shrink:0;color:#64748b;font-size:11.5px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;padding-top:1px;}
              </style>

              <!-- Badges -->
              <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">${badges}</div>

              <!-- Tab Nav -->
              <div class="dt2-nav">
                <button class="dt2-btn dt2-active" id="dt2-info"      onclick="dt2Switch('info',this)"><i class="ri-information-line me-1"></i>Trip Info</button>
                <button class="dt2-btn"            id="dt2-materials" onclick="dt2Switch('materials',this)"><i class="ri-box-3-line me-1"></i>Materials (${mats.length})</button>
                <button class="dt2-btn"            id="dt2-charges"   onclick="dt2Switch('charges',this)"><i class="ri-money-rupee-circle-line me-1"></i>Charges</button>
              </div>

              <!-- Tab Content -->
              <div class="dt2-body">
                <div id="dt2p-info">${infoHtml}</div>
                <div id="dt2p-materials" style="display:none;">${matHtml}${commHtml}${vasuliHtml}</div>
                <div id="dt2p-charges"   style="display:none;">${chargesHtml}</div>
              </div>`;

            Swal.fire({
                title: '<span style="color:'+accentClr+';font-size:16px;">Trip Details</span>',
                html: html,
                width: 640,
                showConfirmButton: false,
                showCloseButton: true,
                customClass: { popup:'text-start' }
            });
        })
        .catch(function(){
            Swal.fire({icon:'error',title:'Error',text:'Could not load trip details.'});
        });
    }

    function dt2Switch(name, btn){
        ['info','materials','charges'].forEach(function(t){
            document.getElementById('dt2-'+t).className = 'dt2-btn';
            document.getElementById('dt2p-'+t).style.display = 'none';
        });
        btn.classList.add('dt2-active');
        document.getElementById('dt2p-'+name).style.display = 'block';
    }
    window.addEventListener('offline', () => { if(typeof SRV!=='undefined') SRV.toast.warning('Internet Disconnected!'); });
window.addEventListener('online',  () => { if(typeof SRV!=='undefined') SRV.toast.success('Back Online!'); });
</script>
<?php require_once "../layout/footer.php"; ?>
