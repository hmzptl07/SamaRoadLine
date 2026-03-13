<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once "../../businessLogics/Admin.php";
require_once "../../businessLogics/RegularTrip.php";
require_once "../../config/database.php";
Admin::checkAuth();

/* ── AJAX: Get trip materials + commission ── */
if (isset($_POST['get_trip_detail'])) {
    header('Content-Type: application/json');
    $tid = intval($_POST['trip_id']);
    $materials  = RegularTrip::getMaterials($tid);
    $commission = RegularTrip::getCommission($tid);
    $vasuli     = RegularTrip::getVasuli($tid);
    echo json_encode(['materials' => $materials, 'commission' => $commission, 'vasuli' => $vasuli]);
    exit();
}

/* ── AJAX: Dropdown options (Consigners + Vehicles) ── */
if (isset($_GET['get_filters'])) {
    header('Content-Type: application/json');
    $stmt = $pdo->query("
        SELECT DISTINCT p.PartyName AS label, p.PartyId AS value
        FROM TripMaster t
        JOIN PartyMaster p ON t.ConsignerId = p.PartyId
        WHERE t.TripType = 'Regular' AND p.PartyName IS NOT NULL
        ORDER BY p.PartyName ASC
    ");
    $consigners = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt2 = $pdo->query("
        SELECT DISTINCT v.VehicleNumber AS label, v.VehicleId AS value
        FROM TripMaster t
        JOIN VehicleMaster v ON t.VehicleId = v.VehicleId
        WHERE t.TripType = 'Regular' AND v.VehicleNumber IS NOT NULL
        ORDER BY v.VehicleNumber ASC
    ");
    $vehicles = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['consigners' => $consigners, 'vehicles' => $vehicles]);
    exit();
}

/* ── Server-side Date Filter (same as OwnerPayment) ── */
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
    case 'thismonth':
        $dateFrom = date('Y-m-d', strtotime('first day of this month'));
        $dateTo   = date('Y-m-d', strtotime('last day of this month'));
        break;
    case 'custom':    break;
    default:          $dateFrom = $dateTo = '';
}
/* Active tab from GET */
$activeTab = $_GET['tab'] ?? 'open';

$allTrips = RegularTrip::getAll();

/* Apply date filter in PHP */
if ($dateFrom || $dateTo) {
    $allTrips = array_values(array_filter($allTrips, function($t) use ($dateFrom, $dateTo) {
        $d = substr($t['TripDate'] ?? '', 0, 10);
        if ($dateFrom && $d < $dateFrom) return false;
        if ($dateTo   && $d > $dateTo)   return false;
        return true;
    }));
}

$total        = count($allTrips);
$openTrips    = array_values(array_filter($allTrips, fn($t) => $t['TripStatus'] === 'Open'));
$billedTrips  = array_values(array_filter($allTrips, fn($t) => $t['TripStatus'] === 'Billed'));
$closedTrips  = array_values(array_filter($allTrips, fn($t) => $t['TripStatus'] === 'Closed'));
$totalFreight = array_sum(array_column($allTrips, 'FreightAmount'));


require_once "../layout/header.php";
require_once "../layout/sidebar.php";
?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<style>
.page-header-card{background:linear-gradient(135deg,#1a237e 0%,#1d4ed8 100%);border-radius:14px;padding:20px 24px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
.ph-title{font-size:20px;font-weight:800;color:#fff;margin:0;display:flex;align-items:center;gap:10px;}
.ph-sub{font-size:12px;color:rgba(255,255,255,0.65);margin-top:3px;}
.stats-bar{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;}
.stat-pill{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:10px 18px;display:flex;align-items:center;gap:10px;box-shadow:0 1px 4px rgba(0,0,0,.05);flex:1;min-width:120px;}
.sp-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.sp-val{font-size:20px;font-weight:800;color:#1a237e;line-height:1;}
.sp-lbl{font-size:11px;color:#64748b;margin-top:2px;}
/* Tabs */
.trip-tabs{display:flex;border-bottom:2px solid #e2e8f0;margin-bottom:0;}
.trip-tab{padding:11px 26px;font-size:13px;font-weight:700;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;display:flex;align-items:center;gap:8px;color:#64748b;transition:all .18s;border-radius:8px 8px 0 0;user-select:none;}
.trip-tab:hover{background:#f8fafc;color:#1a237e;}
.trip-tab.active-open  {color:#1d4ed8;border-bottom-color:#1d4ed8;background:#eff6ff;}
.trip-tab.active-billed{color:#854d0e;border-bottom-color:#ca8a04;background:#fefce8;}
.trip-tab.active-closed{color:#15803d;border-bottom-color:#16a34a;background:#f0fdf4;}
.tc{display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;padding:0 6px;border-radius:12px;font-size:11px;font-weight:800;}
.tc-o{background:#dbeafe;color:#1d4ed8;}
.tc-b{background:#fef9c3;color:#854d0e;}
.tc-c{background:#dcfce7;color:#15803d;}
.tab-pane{display:none;}
.tab-pane.active{display:block;}
.filter-card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px 20px;margin-bottom:16px;}
.fc-open  {background:#f8fafc;border:1px solid #e2e8f0;border-top:none;}
.fc-billed{background:#fffbeb;border:1px solid #fde68a;border-top:none;}
.fc-closed{background:#f0fdf4;border:1px solid #bbf7d0;border-top:none;}
.action-btn-group{display:flex;gap:4px;}
.btn-icon{width:30px;height:30px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:7px;font-size:13px;}
.owner-badge-pending{background:#dbeafe;color:#1d4ed8;border:1px solid #93c5fd;padding:3px 8px;border-radius:20px;font-size:10px;font-weight:700;}
.owner-badge-paid   {background:#dcfce7;color:#16a34a;border:1px solid #bbf7d0;padding:3px 8px;border-radius:20px;font-size:10px;font-weight:700;}
.lock-badge{display:inline-flex;align-items:center;gap:4px;background:#f1f5f9;color:#94a3b8;border:1px solid #cbd5e1;padding:3px 9px;border-radius:6px;font-size:10px;font-weight:600;}
.blue-head th{background:#f0f4ff;color:#1a237e;font-size:12px;font-weight:700;}
.card-tab{border-radius:0 0 12px 12px;border-top:none;}
/* Trip Detail Modal */
.td-tab-btn{background:none;border:none;padding:12px 18px;font-size:13px;font-weight:600;color:#64748b;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;transition:.15s;}
.td-tab-btn:hover{color:#1a237e;background:#f0f4ff;}
.td-tab-active{color:#1a237e !important;border-bottom-color:#1a237e !important;background:#eff6ff;}
.td-section-head{font-size:10px;font-weight:800;color:#1a237e;text-transform:uppercase;letter-spacing:1px;border-left:3px solid #1a237e;padding-left:8px;margin:4px 0 10px;}
.td-row{display:flex;padding:7px 0;border-bottom:1px solid #f1f5f9;font-size:13px;gap:8px;}
.td-row:last-child{border-bottom:none;}
.td-lbl{width:160px;flex-shrink:0;color:#64748b;font-size:11.5px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;padding-top:1px;}
.td-val{color:#1e293b;font-weight:500;flex:1;}
/* ── Date Filter ── */
.df-preset-btn{padding:4px 13px;border-radius:20px;font-size:12px;font-weight:700;
  border:2px solid #e2e8f0;background:#f8fafc;color:#64748b;cursor:pointer;transition:.15s;white-space:nowrap;}
.df-preset-btn:hover{border-color:#0284c7;color:#0284c7;background:#f0f9ff;}
.df-preset-btn.df-active{border-color:#0284c7;background:#0284c7;color:#fff;}
.df-range-inp{font-size:12px;font-weight:600;border:2px solid #e2e8f0 !important;
  border-radius:8px !important;padding:4px 8px !important;height:32px;width:140px;}
.df-range-inp:focus{border-color:#0284c7 !important;box-shadow:none !important;}
.df-tag{background:#e0f2fe;border:1px solid #bae6fd;border-radius:6px;
  padding:2px 10px;font-size:11px;font-weight:700;color:#0284c7;white-space:nowrap;}
</style>

<div class="main-content app-content">
<div class="container-fluid">

<!-- Header -->
<div class="page-header-card">
    <div>
        <div class="ph-title"><i class="ri-file-list-line"></i>Regular Trips</div>
        <div class="ph-sub">All regular trips with Consigner / Consignee</div>
    </div>
    <a href="RegularTripForm.php" class="btn btn-warning fw-bold px-4"
        style="border-radius:9px;height:38px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
        <i class="ri-add-circle-line"></i> New Regular Trip
    </a>
</div>

<!-- Stats -->
<div class="stats-bar">
    <div class="stat-pill">
        <div class="sp-icon" style="background:#e0e7ff;"><i class="ri-road-map-line" style="color:#1a237e;"></i></div>
        <div><div class="sp-val"><?= $total ?></div><div class="sp-lbl">Total</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-icon" style="background:#dbeafe;"><i class="ri-time-line" style="color:#1d4ed8;"></i></div>
        <div><div class="sp-val" style="color:#1d4ed8;"><?= count($openTrips) ?></div><div class="sp-lbl">Open</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-icon" style="background:#fef9c3;"><i class="ri-bill-line" style="color:#854d0e;"></i></div>
        <div><div class="sp-val" style="color:#854d0e;"><?= count($billedTrips) ?></div><div class="sp-lbl">Billed</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-icon" style="background:#dcfce7;"><i class="ri-checkbox-circle-line" style="color:#15803d;"></i></div>
        <div><div class="sp-val" style="color:#15803d;"><?= count($closedTrips) ?></div><div class="sp-lbl">Closed</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-icon" style="background:#f0fdf4;"><i class="ri-money-rupee-circle-line" style="color:#16a34a;"></i></div>
        <div><div class="sp-val" style="font-size:14px;color:#16a34a;">Rs.<?= number_format($totalFreight,0) ?></div><div class="sp-lbl">Total Freight</div></div>
    </div>
</div>

<!-- Tab Nav -->
<div class="trip-tabs">
    <div class="trip-tab active-open" id="nav-open" onclick="switchTab('open')">
        <i class="ri-time-line"></i> Open
        <span class="tc tc-o"><?= count($openTrips) ?></span>
    </div>
    <div class="trip-tab" id="nav-billed" onclick="switchTab('billed')">
        <i class="ri-bill-line"></i> Billed
        <span class="tc tc-b"><?= count($billedTrips) ?></span>
    </div>
    <div class="trip-tab" id="nav-closed" onclick="switchTab('closed')">
        <i class="ri-checkbox-circle-line"></i> Closed
        <span class="tc tc-c"><?= count($closedTrips) ?></span>
    </div>
</div>

<?php
// Helper to render table rows
function tripRow($r, $locked = false, $i = 1) {
    $fr     = floatval($r['FreightAmount']  ?? 0);
    $lab    = floatval($r['LabourCharge']   ?? 0);
    $hld    = floatval($r['HoldingCharge']  ?? 0);
    $oth    = floatval($r['OtherCharge']    ?? 0);
    $tds    = floatval($r['TDS']            ?? 0);
    $cash   = floatval($r['CashAdvance']    ?? 0);
    $online = floatval($r['OnlineAdvance']  ?? 0);
    $adv    = floatval($r['AdvanceAmount']  ?? 0);
    $net    = floatval($r['NetAmount']      ?? 0);
?>
<tr>
    <!-- GC No. -->
    <td style="white-space:nowrap;">
        <div style="font-size:10px;color:#94a3b8;font-weight:700;text-transform:uppercase;line-height:1;">GC No.</div>
        <div style="font-size:13px;font-weight:800;color:#1a237e;"><?= str_pad($r['TripId'],4,'0',STR_PAD_LEFT) ?></div>
    </td>
    <!-- Date -->
    <td style="font-size:12px;white-space:nowrap;"><?= htmlspecialchars($r['TripDate']??'') ?></td>
    <!-- Vehicle -->
    <td>
        <div class="fw-bold" style="font-size:12.5px;"><?= htmlspecialchars($r['VehicleNumber']??'—') ?></div>
        <?php if(!empty($r['VehicleName'])): ?><div style="font-size:11px;color:#64748b;"><?= htmlspecialchars($r['VehicleName']) ?></div><?php endif; ?>
    </td>
    <!-- Consigner → Consignee -->
    <td>
        <div style="font-size:12px;font-weight:600;"><?= htmlspecialchars($r['ConsignerName']??'—') ?></div>
        <div style="font-size:11px;color:#64748b;">→ <?= htmlspecialchars($r['ConsigneeName']??'—') ?></div>
    </td>
    <!-- Route -->
    <td style="font-size:12px;">
        <?php if(!empty($r['FromLocation'])||!empty($r['ToLocation'])): ?>
        <span style="color:#1d4ed8;"><?= htmlspecialchars($r['FromLocation']??'?') ?></span>
        <i class="ri-arrow-right-line" style="font-size:10px;color:#94a3b8;"></i>
        <span style="color:#dc2626;"><?= htmlspecialchars($r['ToLocation']??'?') ?></span>
        <?php else: echo '<span class="text-muted">—</span>'; endif; ?>
    </td>
    <!-- Freight -->
    <td class="text-end" style="font-size:13px;font-weight:800;color:#1a237e;white-space:nowrap;">Rs.<?= number_format($fr,0) ?></td>
    <!-- + Charges -->
    <td class="text-end" style="font-size:11px;line-height:1.75;white-space:nowrap;">
        <?php if($lab>0): ?><div style="color:#b45309;font-weight:600;">+Labour <b>Rs.<?= number_format($lab,0) ?></b></div><?php endif; ?>
        <?php if($hld>0): ?><div style="color:#b45309;font-weight:600;">+Holding <b>Rs.<?= number_format($hld,0) ?></b></div><?php endif; ?>
        <?php if($oth>0): ?><div style="color:#b45309;font-weight:600;">+Other <b>Rs.<?= number_format($oth,0) ?></b></div><?php endif; ?>
        <?php if($lab==0&&$hld==0&&$oth==0): ?><span style="color:#94a3b8;">—</span><?php endif; ?>
    </td>
    <!-- TDS -->
    <td class="text-end" style="font-size:12px;white-space:nowrap;<?= $tds>0 ? 'font-weight:800;color:#dc2626;' : 'color:#94a3b8;' ?>">
        <?= $tds>0 ? 'Rs.'.number_format($tds,0) : '—' ?>
    </td>
    <!-- − Advance -->
    <td class="text-end" style="font-size:11px;line-height:1.75;white-space:nowrap;">
        <?php if($cash>0): ?><div style="color:#7c3aed;font-weight:600;">Cash <b>Rs.<?= number_format($cash,0) ?></b></div><?php endif; ?>
        <?php if($online>0): ?><div style="color:#7c3aed;font-weight:600;">Online <b>Rs.<?= number_format($online,0) ?></b></div><?php endif; ?>
        <?php if($adv>0&&$cash==0&&$online==0): ?><div style="color:#7c3aed;font-weight:800;">Rs.<?= number_format($adv,0) ?></div><?php endif; ?>
        <?php if($adv==0): ?><span style="color:#94a3b8;">—</span><?php endif; ?>
    </td>
    <!-- Net Payable -->
    <td class="text-end" style="font-size:13px;font-weight:900;color:#15803d;white-space:nowrap;">Rs.<?= number_format($net,0) ?></td>
    <!-- Owner Pay -->
    <td>
        <?php if(($r['FreightPaymentToOwnerStatus']??'')==='PaidDirectly'): ?>
        <span class="owner-badge-paid">ToPay</span>
        <?php else: ?><span class="owner-badge-pending">Regular</span><?php endif; ?>
    </td>
    <!-- Commission / Vasuli -->
    <td style="font-size:11px;line-height:1.85;white-space:nowrap;">
        <?php
        $comm  = floatval($r['CommissionAmount'] ?? 0);
        $vas   = floatval($r['VasuliAmount']     ?? 0);
        $cStat = $r['CommissionStatus'] ?? 'Pending';
        $vStat = $r['VasuliStatus']     ?? 'Pending';
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
    <!-- Actions -->
    <td>
        <div class="action-btn-group">
            <?php if($locked): ?>
            <span class="lock-badge"><i class="ri-lock-line"></i> Locked</span>
            <?php else: ?>
            <a href="RegularTripForm.php?TripId=<?= $r['TripId'] ?>" class="btn btn-sm btn-primary btn-icon" title="Edit"><i class="ri-edit-line"></i></a>
            <?php endif; ?>
            <button class="btn btn-sm btn-outline-info btn-icon" title="View Details" onclick='showTrip(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)'><i class="ri-eye-line"></i></button>
            <a href="GCNote_print.php?TripId=<?= $r['TripId'] ?>" target="_blank" class="btn btn-sm btn-outline-dark btn-icon" title="Print GC"><i class="ri-printer-line"></i></a>
        </div>
    </td>
</tr>
<?php } ?>

<?php
function tabHeader($id, $srchId, $fiId, $fcClass, $fiColor, $dtId) { ?>
<div class="filter-card <?= $fcClass ?>">

    <!-- Date filter — server-side PHP (like OwnerPayment) -->
    <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
        <span style="font-size:12px;font-weight:800;color:#0284c7;white-space:nowrap;"><i class="ri-calendar-line me-1"></i>Date:</span>
        <?php
        $qAll  = http_build_query(array_merge($_GET, ['tab'=>$id,'datePreset'=>'all',      'dateFrom'=>'','dateTo'=>'']));
        $qTod  = http_build_query(array_merge($_GET, ['tab'=>$id,'datePreset'=>'today',    'dateFrom'=>'','dateTo'=>'']));
        $qYes  = http_build_query(array_merge($_GET, ['tab'=>$id,'datePreset'=>'yesterday','dateFrom'=>'','dateTo'=>'']));
        $qWeek = http_build_query(array_merge($_GET, ['tab'=>$id,'datePreset'=>'thisweek', 'dateFrom'=>'','dateTo'=>'']));
        $qMon  = http_build_query(array_merge($_GET, ['tab'=>$id,'datePreset'=>'thismonth','dateFrom'=>'','dateTo'=>'']));
        $dp    = ($GLOBALS['activeTab']===$id) ? ($GLOBALS['datePreset'] ?? 'all') : 'all';
        ?>
        <a href="?<?= $qAll  ?>" class="df-preset-btn <?= $dp==='all'       ? 'df-active':'' ?>">All</a>
        <a href="?<?= $qTod  ?>" class="df-preset-btn <?= $dp==='today'     ? 'df-active':'' ?>">Today</a>
        <a href="?<?= $qYes  ?>" class="df-preset-btn <?= $dp==='yesterday' ? 'df-active':'' ?>">Yesterday</a>
        <a href="?<?= $qWeek ?>" class="df-preset-btn <?= $dp==='thisweek'  ? 'df-active':'' ?>">This Week</a>
        <a href="?<?= $qMon  ?>" class="df-preset-btn <?= $dp==='thismonth' ? 'df-active':'' ?>">This Month</a>
        <div style="width:1px;height:24px;background:#e2e8f0;flex-shrink:0;"></div>
        <!-- Custom range form -->
        <form method="GET" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
            <?php foreach($_GET as $k=>$v): if(in_array($k,['datePreset','dateFrom','dateTo','tab'])) continue; ?>
            <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
            <?php endforeach; ?>
            <input type="hidden" name="tab" value="<?= $id ?>">
            <input type="hidden" name="datePreset" value="custom">
            <span style="font-size:12px;font-weight:700;color:#64748b;">From</span>
            <input type="date" name="dateFrom" value="<?= htmlspecialchars($dp==='custom'?($GLOBALS['dateFrom']??''):'') ?>" class="form-control df-range-inp">
            <span style="font-size:12px;font-weight:700;color:#64748b;">To</span>
            <input type="date" name="dateTo"   value="<?= htmlspecialchars($dp==='custom'?($GLOBALS['dateTo']??''):'') ?>"   class="form-control df-range-inp">
            <button type="submit" class="btn btn-sm btn-primary" style="height:32px;border-radius:8px;font-size:12px;font-weight:700;padding:0 12px;"><i class="ri-search-line"></i> Go</button>
        </form>
        <?php if($dp!=='all'&&$dp!==''): ?>
        <span class="df-tag">
            <?php if($dp==='today') echo 'Today: '.date('d-m-Y');
            elseif($dp==='yesterday') echo 'Yesterday: '.date('d-m-Y',strtotime('-1 day'));
            elseif($dp==='thisweek') echo date('d-m-Y',strtotime('monday this week')).' → '.date('d-m-Y',strtotime('sunday this week'));
            elseif($dp==='thismonth') echo date('F Y');
            elseif($dp==='custom') echo ($GLOBALS['dateFrom']?date('d-m-Y',strtotime($GLOBALS['dateFrom'])):'').' → '.($GLOBALS['dateTo']?date('d-m-Y',strtotime($GLOBALS['dateTo'])):'');
            ?>
        </span>
        <?php endif; ?>
    </div>

    <!-- Consigner + Vehicle + Clear + Search -->
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label fw-semibold fs-12 mb-1"><i class="ri-building-line me-1"></i>Consigner</label>
            <select id="fCons_<?= $id ?>" class="s2-cons" style="width:100%;">
                <option value="">-- All Consigners --</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold fs-12 mb-1"><i class="ri-truck-line me-1"></i>Vehicle</label>
            <select id="fVeh_<?= $id ?>" class="s2-veh" style="width:100%;">
                <option value="">-- All Vehicles --</option>
            </select>
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button class="btn btn-outline-secondary btn-sm w-100" onclick="clearFilter('<?= $id ?>')" title="Clear"><i class="ri-refresh-line"></i></button>
        </div>
        <div class="col-md-4 ms-auto">
            <label class="form-label fw-semibold fs-12 mb-1"><i class="ri-search-line me-1"></i>Search</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0" style="border-radius:8px 0 0 8px;"><i class="ri-search-line text-muted"></i></span>
                <input type="text" id="<?= $srchId ?>" class="form-control border-start-0 ps-1" placeholder="GC No, Route, Consignee..." style="border-radius:0;box-shadow:none;">
                <span id="<?= $fiId ?>" class="input-group-text bg-primary text-white fw-bold"
                    style="border-radius:0 8px 8px 0;font-size:11px;min-width:52px;justify-content:center;"></span>
            </div>
        </div>
    </div>
</div>
<div class="card custom-card shadow-sm card-tab">
<div class="card-body p-0"><div class="table-responsive">
<table id="<?= $dtId ?>" class="table table-hover align-middle mb-0 w-100">
    <thead><tr class="blue-head">
        <th style="width:68px;">GC No.</th>
        <th style="min-width:92px;">Date</th>
        <th style="min-width:105px;">Vehicle</th>
        <th style="min-width:155px;">Consigner → Consignee</th>
        <th style="min-width:140px;">Route</th>
        <th class="text-end" style="min-width:82px;">Freight</th>
        <th class="text-end" style="min-width:105px;">+ Charges</th>
        <th class="text-end" style="min-width:70px;">TDS</th>
        <th class="text-end" style="min-width:90px;">− Advance</th>
        <th class="text-end" style="min-width:90px;">Net Payable</th>
        <th style="min-width:88px;">Bhadu</th>
        <th style="min-width:120px;">Comm / Vasuli</th>
        <th style="width:108px;">Actions</th>
    </tr></thead>
    <tbody>
<?php } ?>

<!-- ── OPEN ── -->
<div id="tab-open" class="tab-pane active">
<?php tabHeader('open','srch_open','fi_open','fc-open','#1d4ed8','dt_open');
$i=1; foreach($openTrips as $r){ tripRow($r,false,$i++); } ?>
    </tbody>
</table></div></div></div>
</div>

<!-- ── BILLED ── -->
<div id="tab-billed" class="tab-pane">
<?php tabHeader('billed','srch_billed','fi_billed','fc-billed','#ca8a04','dt_billed');
$i=1; foreach($billedTrips as $r){ tripRow($r,true,$i++); } ?>
    </tbody>
</table></div></div></div>
</div>

<!-- ── CLOSED ── -->
<div id="tab-closed" class="tab-pane">
<?php tabHeader('closed','srch_closed','fi_closed','fc-closed','#15803d','dt_closed');
$i=1; foreach($closedTrips as $r){ tripRow($r,true,$i++); } ?>
    </tbody>
</table></div></div></div>
</div>

</div></div>

<script>
var dtOpen, dtBilled, dtClosed;

$(document).ready(function(){
    var cfg = {
        scrollX:true, pageLength:25, dom:'rtip',
        columnDefs:[{orderable:false,targets:[0,11]}],
        language:{paginate:{previous:'‹',next:'›'}}
    };
    dtOpen   = $('#dt_open').DataTable({...cfg,
        drawCallback:function(){ var i=this.api().page.info(); $('#fi_open').text(i.recordsDisplay+'/'+i.recordsTotal); }
    });
    dtBilled = $('#dt_billed').DataTable({...cfg,
        drawCallback:function(){ var i=this.api().page.info(); $('#fi_billed').text(i.recordsDisplay+'/'+i.recordsTotal); }
    });
    dtClosed = $('#dt_closed').DataTable({...cfg,
        drawCallback:function(){ var i=this.api().page.info(); $('#fi_closed').text(i.recordsDisplay+'/'+i.recordsTotal); }
    });

    // Search boxes
    $('#srch_open').on('keyup input',function(){ dtOpen.search($(this).val()).draw(); });
    $('#srch_billed').on('keyup input',function(){ dtBilled.search($(this).val()).draw(); });
    $('#srch_closed').on('keyup input',function(){ dtClosed.search($(this).val()).draw(); });

    // Init Select2 — bootstrap-5 theme, empty options first
    $('.s2-cons').select2({theme:'bootstrap-5', allowClear:true, placeholder:'-- All Consigners --', width:'100%'});
    $('.s2-veh').select2({ theme:'bootstrap-5', allowClear:true, placeholder:'-- All Vehicles --',   width:'100%'});

    // ── Load dropdown options from DB via AJAX ──
    fetch(window.location.pathname + '?get_filters=1')
        .then(r => r.json())
        .then(function(data){
            data.consigners.forEach(function(item){
                var opt = new Option(item.label, item.label, false, false);
                $('#fCons_open, #fCons_billed, #fCons_closed').append($(opt).clone());
            });
            data.vehicles.forEach(function(item){
                var opt = new Option(item.label, item.label, false, false);
                $('#fVeh_open, #fVeh_billed, #fVeh_closed').append($(opt).clone());
            });
            $('.s2-cons, .s2-veh').trigger('change.select2');
        })
        .catch(function(){ console.warn('Dropdown load failed'); });

    // ── Dropdown change → DataTable column filter (col 3 = Consigner, col 2 = Vehicle) ──
    function escReg(s){ return s.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'); }

    $('#fCons_open').on('change',   function(){ dtOpen.column(3).search(this.value   ? '^'+escReg(this.value)+'$':'', true,false).draw(); });
    $('#fCons_billed').on('change', function(){ dtBilled.column(3).search(this.value ? '^'+escReg(this.value)+'$':'', true,false).draw(); });
    $('#fCons_closed').on('change', function(){ dtClosed.column(3).search(this.value ? '^'+escReg(this.value)+'$':'', true,false).draw(); });

    $('#fVeh_open').on('change',   function(){ dtOpen.column(2).search(this.value   || '', false, false).draw(); });
    $('#fVeh_billed').on('change', function(){ dtBilled.column(2).search(this.value || '', false, false).draw(); });
    $('#fVeh_closed').on('change', function(){ dtClosed.column(2).search(this.value || '', false, false).draw(); });

    if(activeTab !== 'open') switchTab(activeTab);
});

var activeTab = '<?= htmlspecialchars($activeTab) ?>';
function switchTab(name){
    ['open','billed','closed'].forEach(function(t){
        document.getElementById('nav-'+t).className = 'trip-tab';
    });
    document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));
    var cls = {open:'active-open',billed:'active-billed',closed:'active-closed'};
    document.getElementById('nav-'+name).classList.add(cls[name]);
    document.getElementById('tab-'+name).classList.add('active');
    activeTab = name;
    ({open:dtOpen,billed:dtBilled,closed:dtClosed})[name].columns.adjust();
    var url = new URL(window.location.href);
    url.searchParams.set('tab', name);
    history.replaceState(null,'', url.toString());
}

function clearFilter(tab){
    $('#fCons_'+tab).val(null).trigger('change');
    $('#fVeh_'+tab).val(null).trigger('change');
    $('#srch_'+tab).val('');
    ({open:dtOpen,billed:dtBilled,closed:dtClosed})[tab].search('').columns().search('').draw();
}

/* Date filter handled server-side via PHP + GET params */


window.addEventListener('offline',()=>SRV.toast.warning('Internet Disconnected!'));
window.addEventListener('online', ()=>SRV.toast.success('Back Online!'));
<?php if(!empty($_GET['locked'])): ?>
$(document).ready(function(){
    <?php $r = $_GET['reason'] ?? ''; ?>
    <?php if($r === 'owner_paid'): ?>
    SRV.toast.error('⛔ Trip lock hai — Owner Payment ho chuki hai, edit nahi kar sakte.');
    <?php elseif($r === 'billed_closed'): ?>
    SRV.toast.error('⛔ Trip lock hai — Bill generate ho chuka hai, edit nahi kar sakte.');
    <?php else: ?>
    SRV.toast.error('⛔ Trip lock hai — editing is not allowed.');
    <?php endif; ?>
});
<?php endif; ?>

function rupee(n){ return 'Rs.'+parseFloat(n||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}); }

function showTrip(t){
    // Set header badges
    var statusClr = {Open:'#1d4ed8',Billed:'#ca8a04',Closed:'#15803d'};
    var statusBg  = {Open:'#eff6ff',Billed:'#fefce8',Closed:'#f0fdf4'};
    var sc = statusClr[t.TripStatus]||'#64748b';
    var sb = statusBg[t.TripStatus]||'#f8fafc';

    document.getElementById('td_badges').innerHTML = `
        <span style="background:#eff6ff;color:#1d4ed8;border:1px solid #93c5fd;padding:4px 13px;border-radius:20px;font-size:12px;font-weight:700;">
            GC No. ${String(t.TripId).padStart(4,"0")}
        </span>
        <span style="background:${sb};color:${sc};border:1px solid ${sc}40;padding:4px 13px;border-radius:20px;font-size:12px;font-weight:700;">
            ${t.TripStatus}
        </span>
        <span style="color:${t.FreightPaymentToOwnerStatus==='PaidDirectly'?'#16a34a':'#dc2626'};border:1px solid currentColor;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;">
            ${t.FreightPaymentToOwnerStatus==='PaidDirectly'?'ToPay':'Regular'}
        </span>`;

    // ── TAB 1: Trip Info ──
    document.getElementById('td_info').innerHTML = `
        <div class="td-section-head">📋 Basic</div>
        <div class="td-row"><span class="td-lbl">📄 GC No.</span><span class="td-val" style="font-size:15px;font-weight:900;color:#1a237e;letter-spacing:1px;">${String(t.TripId).padStart(4,'0')}</span></div>
        <div class="td-row"><span class="td-lbl">📅 Trip Date</span><span class="td-val fw-bold">${t.TripDate||'—'}</span></div>
        <div class="td-row"><span class="td-lbl">🧾 Invoice No</span><span class="td-val">${t.InvoiceNo||'—'}</span></div>
        <div class="td-row"><span class="td-lbl">📆 Invoice Date</span><span class="td-val">${t.InvoiceDate||'—'}</span></div>
        <div class="td-row"><span class="td-lbl">🚛 Vehicle</span><span class="td-val fw-bold">${t.VehicleNumber||'—'}${t.VehicleName?' <small class="text-muted">'+t.VehicleName+'</small>':''}</span></div>
        <div class="td-row">
            <span class="td-lbl">📍 Route</span>
            <span class="td-val">
                <span style="color:#1d4ed8;font-weight:700;">${t.FromLocation||'?'}</span>
                <i class="ri-arrow-right-line mx-1" style="color:#94a3b8;font-size:11px;"></i>
                <span style="color:#dc2626;font-weight:700;">${t.ToLocation||'?'}</span>
            </span>
        </div>

        <div class="td-section-head mt-3">🤝 Parties</div>
        <div style="overflow-x:auto;margin-top:6px;">
        <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
            <thead>
                <tr style="background:#f0f4ff;">
                    <th style="padding:8px 12px;color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;width:100px;border-bottom:2px solid #e2e8f0;"></th>
                    <th style="padding:8px 12px;color:#1a237e;font-weight:800;font-size:12px;border-bottom:2px solid #e2e8f0;">📤 Consigner</th>
                    <th style="padding:8px 12px;color:#15803d;font-weight:800;font-size:12px;border-bottom:2px solid #e2e8f0;">📥 Consignee</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:7px 12px;color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;">Name</td>
                    <td style="padding:7px 12px;font-weight:700;color:#1e293b;">${t.ConsignerName||'—'}</td>
                    <td style="padding:7px 12px;font-weight:700;color:#1e293b;">${t.ConsigneeName||'—'}</td>
                </tr>
                <tr style="background:#fafafa;border-bottom:1px solid #f1f5f9;">
                    <td style="padding:7px 12px;color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;">Mobile</td>
                    <td style="padding:7px 12px;">
                        ${t.ConsignerMobile ? `<a href="tel:${t.ConsignerMobile}" style="color:#0284c7;font-weight:600;text-decoration:none;">📞 ${t.ConsignerMobile}</a>` : '<span style="color:#94a3b8;">—</span>'}
                    </td>
                    <td style="padding:7px 12px;">
                        ${t.ConsigneeContactNo ? `<a href="tel:${t.ConsigneeContactNo}" style="color:#0284c7;font-weight:600;text-decoration:none;">📞 ${t.ConsigneeContactNo}</a>` : '<span style="color:#94a3b8;">—</span>'}
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:7px 12px;color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;">City</td>
                    <td style="padding:7px 12px;color:#1e293b;">${t.ConsignerCity||'<span style="color:#94a3b8;">—</span>'}</td>
                    <td style="padding:7px 12px;color:#1e293b;">${t.ConsigneeCity||'<span style="color:#94a3b8;">—</span>'}</td>
                </tr>
                <tr style="background:#fafafa;">
                    <td style="padding:7px 12px;color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;">Address</td>
                    <td style="padding:7px 12px;color:#475569;font-size:12px;">${t.ConsignerAddress||'<span style="color:#94a3b8;">—</span>'}</td>
                    <td style="padding:7px 12px;color:#475569;font-size:12px;">${t.ConsigneeAddress||'<span style="color:#94a3b8;">—</span>'}</td>
                </tr>
            </tbody>
        </table>
        </div>
        ${t.AgentName ? `<div class="td-row mt-2"><span class="td-lbl">⭐ Agent</span><span class="td-val">${t.AgentName}</span></div>` : ''}

        <div class="td-section-head mt-3">🧑 Driver</div>
        <div class="td-row"><span class="td-lbl">👨 Name</span><span class="td-val">${t.DriverName||'—'}</span></div>
        <div class="td-row"><span class="td-lbl">📞 Contact</span><span class="td-val">${t.DriverContactNo||'—'}</span></div>
        <div class="td-row"><span class="td-lbl">🪪 Aadhar</span><span class="td-val">${t.DriverAadharNo||'—'}</span></div>
        <div class="td-row"><span class="td-lbl">🏠 Address</span><span class="td-val">${t.DriverAddress||'—'}</span></div>
        ${t.Remarks ? `<div class="mt-2" style="background:#fefce8;border:1px solid #fde68a;border-radius:8px;padding:8px 12px;font-size:12px;color:#854d0e;"><i class="ri-chat-1-line me-1"></i>${t.Remarks}</div>` : ''}`;

    // ── TAB 2: Materials — load via AJAX ──
    document.getElementById('td_materials').innerHTML = `<div class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary me-2"></span>Loading...</div>`;

    // ── TAB 3: Charges ──
    var cash   = parseFloat(t.CashAdvance||0);
    var online = parseFloat(t.OnlineAdvance||0);
    var advHtml = (cash > 0 || online > 0)
        ? `<tr><td class="text-danger">➖ Cash Advance</td><td class="text-end text-danger">- ${rupee(cash)}</td></tr>
           <tr><td class="text-danger">➖ Online Advance</td><td class="text-end text-danger">- ${rupee(online)}</td></tr>`
        : `<tr><td class="text-danger">➖ Advance Paid</td><td class="text-end text-danger">- ${rupee(t.AdvanceAmount)}</td></tr>`;

    document.getElementById('td_charges').innerHTML = `
        <table class="table table-sm mb-0" style="font-size:13px;">
            <tr style="background:#f0f4ff;">
                <td class="fw-bold" style="border-radius:6px 0 0 0;">🚛 Freight Amount</td>
                <td class="text-end fw-bold" style="color:#1a237e;">${rupee(t.FreightAmount)}</td>
            </tr>
            <tr><td class="text-muted">👷 Labour Charge</td><td class="text-end">${rupee(t.LabourCharge)}</td></tr>
            <tr><td class="text-muted">⏱️ Holding / Detention</td><td class="text-end">${rupee(t.HoldingCharge)}</td></tr>
            <tr><td class="text-muted">➕ Other Charge ${t.OtherChargeNote?'<small class=\'text-muted\'>('+t.OtherChargeNote+')</small>':''}</td><td class="text-end">${rupee(t.OtherCharge)}</td></tr>
            <tr style="border-top:2px solid #e2e8f0;">
                <td class="fw-bold">📊 Total Amount</td>
                <td class="text-end fw-bold">${rupee(t.TotalAmount)}</td>
            </tr>
            ${advHtml}
            <tr><td class="text-danger">➖ TDS</td><td class="text-end text-danger">- ${rupee(t.TDS)}</td></tr>
            <tr style="background:#dcfce7;">
                <td class="fw-bold" style="color:#15803d;border-radius:0 0 0 6px;">💰 Net Payable</td>
                <td class="text-end fw-bold" style="color:#15803d;border-radius:0 0 6px 0;">${rupee(t.NetAmount)}</td>
            </tr>
            <tr style="background:#fef9c3;">
                <td style="color:#854d0e;">🪙 Material Value</td>
                <td class="text-end" style="color:#854d0e;">${rupee(t.MaterialTotalValue)}</td>
            </tr>
        </table>`;

    // Reset to first tab
    tdSwitchTab('info');

    // Show modal
    new bootstrap.Modal(document.getElementById('tripDetailModal')).show();

    // Fetch materials
    fetch('', {method:'POST', body: new URLSearchParams({get_trip_detail:1, trip_id:t.TripId})})
    .then(r=>r.json()).then(data=>{
        var mats = data.materials || [];
        var comm    = data.commission;
        var vasuli  = data.vasuli;
        if(mats.length === 0){
            document.getElementById('td_materials').innerHTML = '<div class="text-center text-muted py-4" style="font-size:13px;">No materials added for this trip.</div>';
            return;
        }
        var totalWt = 0, totalAmt = 0;
        var rows = mats.map((m,i) => {
            var isU   = m.MaterialType === 'Units';
            var wt    = isU ? parseFloat(m.TotalWeight||0) : parseFloat(m.Weight||0);
            var qty   = parseInt(m.Quantity||0);
            var utype = m.UnitType||'unit';
            var wpu   = parseFloat(m.WeightPerUnit||0);
            var qtyCol = isU
                ? `<span style="font-size:11px;font-weight:700;color:#1d4ed8;">${qty} ${utype}</span>`
                : `<span style="font-size:11px;">${wt.toFixed(3)} T</span>`;
            var descExtra = isU
                ? `<div style="font-size:10px;color:#64748b;margin-top:2px;">${qty} &times; ${(wpu*1000).toFixed(1)}kg = ${wt.toFixed(3)} T</div>`
                : '';
            totalWt  += wt;
            totalAmt += parseFloat(m.Amount||0);
            return `<tr>
                <td class="text-muted" style="font-size:11px;">${i+1}</td>
                <td>${qtyCol}</td>
                <td class="fw-semibold">${m.MaterialName}${descExtra}</td>
                <td class="text-center" style="font-size:11px;">${rupee(m.Rate)}</td>
                <td class="text-end fw-bold" style="color:#1a237e;">${rupee(m.Amount)}</td>
            </tr>`;
        }).join('');

        var commHtml = '';
        if(comm && parseFloat(comm.CommissionAmount) > 0){
            commHtml = `<div class="mt-3" style="background:#f0f4ff;border:1px solid #c7d7fc;border-radius:8px;padding:10px 14px;display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:12px;color:#1a237e;font-weight:700;"><i class="ri-percent-line me-1"></i>Commission</span>
                <span style="font-weight:800;color:#1a237e;">${rupee(comm.CommissionAmount)}
                    <small class="ms-2" style="font-size:10px;color:#64748b;font-weight:600;">(Recovery: ${comm.RecoveryFrom})</small>
                </span>
            </div>`;
        }

        var vasuliHtml = '';
        if(vasuli && parseFloat(vasuli.VasuliAmount||0) > 0){
            var vs = vasuli.VasuliStatus === 'Received'
                ? '<span style="background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;">Received</span>'
                : '<span style="background:#fef2f2;color:#dc2626;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;">Pending</span>';
            vasuliHtml = `<div class="mt-2" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:12px;color:#15803d;font-weight:700;"><i class="ri-hand-coin-line me-1"></i>Vasuli &nbsp;<small style="color:#64748b;font-size:10px;">(From: ${vasuli.RecoverFrom})</small></span>
                <span style="font-weight:800;color:#15803d;">${rupee(vasuli.VasuliAmount)} &nbsp;${vs}</span>
            </div>`;
        }

        document.getElementById('td_materials').innerHTML = `
            <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" style="font-size:13px;">
                <thead style="background:#1a237e;color:#fff;">
                    <tr><th style="width:28px;">#</th><th style="width:80px;">Qty/Wt</th><th>Material</th><th class="text-center">Rate</th><th class="text-end">Amount</th></tr>
                </thead>
                <tbody>${rows}</tbody>
                <tfoot style="background:#f0f4ff;">
                    <tr>
                        <td colspan="2" class="fw-bold text-end" style="color:#1a237e;">Total</td>
                        <td class="fw-bold" style="font-size:11px;color:#1a237e;">${totalWt.toFixed(3)} T</td>
                        <td></td>
                        <td class="text-end fw-bold" style="color:#1a237e;">${rupee(totalAmt)}</td>
                    </tr>
                </tfoot>
            </table>
            </div>
            ${commHtml}${vasuliHtml}`;
    }).catch(()=>{
        document.getElementById('td_materials').innerHTML = '<div class="text-danger text-center py-3">Failed to load materials.</div>';
    });
}

function tdSwitchTab(name){
    ['info','materials','charges'].forEach(t=>{
        document.getElementById('tdbtn_'+t).classList.toggle('td-tab-active', t===name);
        document.getElementById('td_'+t).style.display = t===name ? 'block' : 'none';
    });
}
</script>

<!-- ══ Trip Detail Modal ══ -->
<div class="modal fade" id="tripDetailModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered modal-lg">
<div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 20px 60px rgba(0,0,0,.18);">
    <div class="modal-header" style="background:linear-gradient(135deg,#1a237e,#1d4ed8);border-radius:16px 16px 0 0;padding:16px 22px;">
        <div>
            <div style="font-size:16px;font-weight:800;color:#fff;margin-bottom:6px;"><i class="ri-road-map-line me-2"></i>Trip Details</div>
            <div id="td_badges" style="display:flex;gap:6px;flex-wrap:wrap;"></div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body p-0">
        <!-- Tab Nav -->
        <div style="display:flex;border-bottom:2px solid #e2e8f0;padding:0 20px;background:#f8fafc;">
            <button class="td-tab-btn" id="tdbtn_info"      onclick="tdSwitchTab('info')">      <i class="ri-information-line me-1"></i>Trip Info</button>
            <button class="td-tab-btn" id="tdbtn_materials" onclick="tdSwitchTab('materials')"> <i class="ri-box-3-line me-1"></i>Materials</button>
            <button class="td-tab-btn" id="tdbtn_charges"   onclick="tdSwitchTab('charges')">   <i class="ri-money-rupee-circle-line me-1"></i>Charges</button>
        </div>
        <!-- Tab Content -->
        <div style="padding:18px 22px;max-height:65vh;overflow-y:auto;">
            <div id="td_info"></div>
            <div id="td_materials" style="display:none;"></div>
            <div id="td_charges"   style="display:none;"></div>
        </div>
    </div>
    <div class="modal-footer" style="border-top:1px solid #e2e8f0;padding:12px 20px;border-radius:0 0 16px 16px;">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><i class="ri-close-line me-1"></i>Close</button>
    </div>
</div>
</div>
</div>

<?php require_once "../layout/footer.php"; ?>
