<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once "../../businessLogics/Admin.php";
require_once "../../businessLogics/OwnerReport_logic.php";
Admin::checkAuth();

$datePreset = $_GET['datePreset'] ?? 'all';
$selectedId = intval($_GET['ownerId'] ?? 0);
$searched   = isset($_GET['search']) && $selectedId > 0;

/* ── EXCEL EXPORT ── */
if (isset($_GET['export']) && $_GET['export'] === 'excel' && $searched) {
    list($dateFrom, $dateTo) = OwnerReport::resolveDateRange($datePreset, $_GET['dateFrom'] ?? '', $_GET['dateTo'] ?? '');
    $trips   = OwnerReport::getOwnerTrips($selectedId, $dateFrom, $dateTo);
    $totals  = OwnerReport::totals($trips);
    $oName   = '';
    foreach (OwnerReport::getOwnerList() as $o) { if ($o['VehicleOwnerId'] == $selectedId) { $oName = $o['OwnerName']; break; } }
    $fname   = 'OwnerReport_' . preg_replace('/[^A-Za-z0-9]/', '_', $oName) . '_' . date('Y-m-d') . '.xls';

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Cache-Control: max-age=0');
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8"><style>
    th{background:#15803d;color:#fff;font-weight:bold;padding:6px 10px;text-align:center;border:1px solid #ccc;}
    td{padding:5px 8px;border:1px solid #ddd;white-space:nowrap;}
    .amt{text-align:right;font-family:Courier New,monospace;}
    .hdr{background:#14532d;color:#fff;font-weight:bold;font-size:14pt;padding:8px;}
    .sub{color:#555;padding:4px 8px;}
    </style></head><body>';

    echo '<table><tr><td colspan="14" class="hdr">Owner Trip Report â ' . htmlspecialchars($oName) . '</td></tr>';
    if ($datePreset !== 'all' && $dateFrom && $dateTo)
        echo '<tr><td colspan="14" class="sub">Period: ' . date('d M Y', strtotime($dateFrom)) . ' to ' . date('d M Y', strtotime($dateTo)) . '</td></tr>';
    echo '<tr><td colspan="14" class="sub">Generated: ' . date('d M Y, h:i A') . ' | Total Trips: ' . $totals['cnt'] . ' | Owner Payable: â¹' . number_format($totals['ownerPayable'], 0) . ' | Balance Due: â¹' . number_format($totals['balance'], 0) . '</td></tr>';
    echo '<tr><td colspan="14"></td></tr>';

    echo '<tr>';
    foreach (['GC No.','Date','Type','Vehicle','Route','Freight','Labour','Holding','Other','Total Amt','Commission','Owner Payable','Pay Status','Trip Status'] as $h)
        echo '<th>' . $h . '</th>';
    echo '</tr>';

    foreach ($trips as $t) {
        $total    = $t['FreightAmount']+$t['LabourCharge']+$t['HoldingCharge']+$t['OtherCharge'];
        $comm     = floatval($t['CommissionAmount']);
        $ownerPay = $total - $comm;
        echo '<tr>';
        echo '<td>' . str_pad($t['TripId'], 4, '0', STR_PAD_LEFT) . '</td>';
        echo '<td>' . date('d M Y', strtotime($t['TripDate'])) . '</td>';
        echo '<td>' . htmlspecialchars($t['TripType']) . '</td>';
        echo '<td>' . htmlspecialchars($t['VehicleNumber'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars(($t['FromLocation']??'') . ' to ' . ($t['ToLocation']??'')) . '</td>';
        echo '<td class="amt">' . number_format($t['FreightAmount'], 0) . '</td>';
        echo '<td class="amt">' . ($t['LabourCharge']  > 0 ? number_format($t['LabourCharge'],  0) : '') . '</td>';
        echo '<td class="amt">' . ($t['HoldingCharge'] > 0 ? number_format($t['HoldingCharge'], 0) : '') . '</td>';
        echo '<td class="amt">' . ($t['OtherCharge']   > 0 ? number_format($t['OtherCharge'],   0) : '') . '</td>';
        echo '<td class="amt" style="background:#e8f5e9;font-weight:bold;">' . number_format($total, 0) . '</td>';
        echo '<td class="amt">' . ($comm > 0 ? number_format($comm, 0) : '') . '</td>';
        echo '<td class="amt" style="background:#dcfce7;font-weight:bold;">' . number_format($ownerPay, 0) . '</td>';
        echo '<td>' . htmlspecialchars($t['OwnerPaymentStatus']) . '</td>';
        echo '<td>' . htmlspecialchars($t['TripStatus']) . '</td>';
        echo '</tr>';
    }

    // Totals row
    echo '<tr>';
    echo '<td colspan="5" style="text-align:right;background:#15803d;color:#fff;font-weight:bold;">TOTAL â ' . $totals['cnt'] . ' Trips</td>';
    foreach (['freight','labour','holding','other','total','comm','ownerPayable'] as $k)
        echo '<td class="amt" style="background:#0a3d26;color:#fff;font-weight:bold;">' . ($totals[$k] > 0 ? number_format($totals[$k], 0) : '') . '</td>';
    echo '<td colspan="2"></td></tr>';

    // Summary
    echo '<tr><td colspan="14"></td></tr>';
    echo '<tr><td colspan="5" style="font-weight:bold;background:#f0fdf4;">Owner Payable (Total â Commission)</td><td colspan="9" style="font-weight:bold;background:#f0fdf4;" class="amt">â¹' . number_format($totals['ownerPayable'], 0) . '</td></tr>';
    echo '<tr><td colspan="5" style="font-weight:bold;background:#eff6ff;">Already Paid</td><td colspan="9" style="font-weight:bold;background:#eff6ff;" class="amt">â¹' . number_format($totals['paid'], 0) . '</td></tr>';
    echo '<tr><td colspan="5" style="font-weight:bold;background:' . ($totals['balance']>0?'#fef2f2':'#dcfce7') . ';">Balance Due to Owner</td><td colspan="9" style="font-weight:bold;background:' . ($totals['balance']>0?'#fef2f2':'#dcfce7') . ';" class="amt">â¹' . number_format($totals['balance'], 0) . '</td></tr>';

    echo '</table></body></html>';
    echo '</table></body></html>';
    exit;
}

list($dateFrom, $dateTo) = OwnerReport::resolveDateRange(
    $datePreset,
    $_GET['dateFrom'] ?? '',
    $_GET['dateTo']   ?? ''
);

$ownerList  = OwnerReport::getOwnerList();
$trips      = [];
$totals     = [];
$ownerName  = '';

if ($searched) {
    $trips     = OwnerReport::getOwnerTrips($selectedId, $dateFrom, $dateTo);
    $totals    = OwnerReport::totals($trips);
    foreach ($ownerList as $o) {
        if ($o['VehicleOwnerId'] == $selectedId) { $ownerName = $o['OwnerName']; break; }
    }
}

require_once "../layout/header.php";
require_once "../layout/sidebar.php";

/* ── helpers ── */
function stBadge($s){ $s=$s??'';
    if($s==='Open')      return '<span class="rb rb-open">Open</span>';
    if($s==='Billed')    return '<span class="rb rb-bld">Billed</span>';
    if($s==='Completed') return '<span class="rb rb-done">Completed</span>';
    return '<span class="rb rb-cn">&#8212;</span>';
}
function opBadge($s){ $s=$s??'';
    if($s==='Paid')          return '<span class="rb rb-paid">Paid</span>';
    if($s==='PartiallyPaid') return '<span class="rb rb-part">Partial</span>';
    if($s==='Unpaid')        return '<span class="rb rb-unpd">Unpaid</span>';
    return '<span class="rb rb-cn">&#8212;</span>';
}
function typeBadge($s){ $s=$s??'';
    if($s==='Regular') return '<span class="rb rb-reg">Regular</span>';
    if($s==='Agent')   return '<span class="rb rb-agt">Agent</span>';
    return '<span class="rb rb-cn">&#8212;</span>';
}
function r($v){ return '&#8377;'.number_format(floatval($v),0); }
function amtTd($v,$extra=''){
    $v=floatval($v);
    if($v<=0) return '<td class="amt-zero"'.$extra.'>&#8212;</td>';
    return '<td class="amt"'.$extra.'>'.r($v).'</td>';
}
?>
<style>
/* ── Header ── */
.or-hdr{background:linear-gradient(135deg,#14532d,#15803d 55%,#16a34a);border-radius:14px;padding:20px 26px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
.or-hdr h4{color:#fff;font-weight:900;font-size:19px;margin:0;display:flex;align-items:center;gap:9px;}
.or-hdr p{color:rgba(255,255,255,.65);font-size:12px;margin:3px 0 0;}
/* ── Card wrap ── */
.tab-wrap{border:1px solid #e2e8f0;border-radius:12px;background:#fff;overflow:hidden;}
/* ── Search panel ── */
.search-panel{padding:20px 22px;border-bottom:1px solid #bbf7d0;background:#f0fdf4;}
.sp-title{font-size:12px;font-weight:800;color:#374151;margin-bottom:10px;display:flex;align-items:center;gap:6px;text-transform:uppercase;letter-spacing:.5px;}
select.owner-select{font-size:13px;font-weight:600;height:38px;border-radius:9px;border:2px solid #d1d5db;padding:0 12px;background:#fff;color:#1e293b;width:100%;cursor:pointer;outline:none;max-width:360px;}
select.owner-select:focus{border-color:#15803d;box-shadow:0 0 0 3px rgba(21,128,61,.15);}
.btn-search{padding:8px 22px;border-radius:9px;font-size:13px;font-weight:800;border:none;cursor:pointer;background:#15803d;color:#fff;display:inline-flex;align-items:center;gap:7px;transition:.15s;}
.btn-search:hover{background:#166534;transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,.15);}
/* ── Date filter row ── */
.date-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:12px;}
.df-btn{padding:4px 11px;border-radius:16px;font-size:11.5px;font-weight:700;border:2px solid #e2e8f0;background:#fff;color:#64748b;cursor:pointer;text-decoration:none;display:inline-block;transition:.15s;white-space:nowrap;}
.df-btn:hover{border-color:#15803d;color:#15803d;}
.df-btn.active{border-color:#15803d;background:#15803d;color:#fff;}
.df-lbl{font-size:11.5px;font-weight:700;color:#6b7280;white-space:nowrap;}
/* ── Empty state ── */
.empty-state{padding:60px 20px;text-align:center;color:#94a3b8;}
.empty-state i{font-size:52px;opacity:.3;display:block;margin-bottom:12px;}
.empty-state p{font-size:14px;margin:0;}
/* ── Result header ── */
.result-hdr{padding:14px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;border-bottom:1px solid #bbf7d0;background:#f0fdf4;}
.result-owner-name{font-size:16px;font-weight:900;color:#14532d;display:flex;align-items:center;gap:9px;}
.result-meta{display:flex;gap:8px;flex-wrap:wrap;}
.meta-pill{font-size:11.5px;font-weight:700;padding:4px 11px;border-radius:20px;}
/* ── Summary pills ── */
.srow{display:flex;gap:8px;flex-wrap:wrap;padding:14px 20px;border-bottom:1px solid #e2e8f0;}
.spill{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:9px 13px;display:flex;align-items:center;gap:8px;flex:1;min-width:110px;box-shadow:0 1px 3px rgba(0,0,0,.04);}
.sico{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;}
.snum{font-size:12.5px;font-weight:900;line-height:1.1;}
.slbl{font-size:10px;color:#64748b;margin-top:1px;}
/* ── Table ── */
.or-table{width:100%;border-collapse:collapse;font-size:12.5px;}
.or-table thead th{padding:9px 11px;font-size:11px;font-weight:800;white-space:nowrap;text-align:center;letter-spacing:.4px;text-transform:uppercase;border:1px solid rgba(255,255,255,.2);color:#fff;}
.or-table td{padding:7px 11px;border:1px solid #e4f0e8;vertical-align:middle;white-space:nowrap;}
.or-table tbody tr:nth-child(odd) td{background:#f9fdf9;}
.or-table tbody tr:nth-child(even) td{background:#fff;}
.or-table tbody tr:hover td{background:#dcfce7!important;transition:background .1s;}
/* row tints */
.tr-open      td{background:#fffef0!important;}
.tr-completed td{background:#f0fdf5!important;}
/* ── Tfoot ── */
.or-table tfoot th{padding:9px 11px;font-size:12px;font-weight:800;white-space:nowrap;border:1px solid rgba(255,255,255,.15);}
/* ── Amount cells ── */
.amt{text-align:right!important;font-family:'Courier New',monospace;font-weight:700;color:#14532d;}
.amt-zero{text-align:right!important;color:#c8d5e3;}
.gc-cell{font-family:'Courier New',monospace;font-size:12.5px;font-weight:900;letter-spacing:1px;}
/* ── Badges ── */
.rb{padding:3px 8px;border-radius:20px;font-size:10.5px;font-weight:700;white-space:nowrap;display:inline-block;}
.rb-open{background:#fef9c3;color:#854d0e;border:1px solid #fde68a;}
.rb-bld{background:#dbeafe;color:#1e40af;border:1px solid #93c5fd;}
.rb-done{background:#dcfce7;color:#166534;border:1px solid #86efac;}
.rb-paid{background:#dcfce7;color:#166534;border:1px solid #86efac;}
.rb-part{background:#ffedd5;color:#c2410c;border:1px solid #fdba74;}
.rb-unpd{background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;}
.rb-reg{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;}
.rb-agt{background:#fffbeb;color:#b45309;border:1px solid #fde68a;}
.rb-cn{color:#a0aec0;font-size:11px;font-style:italic;}
@media print{.or-hdr,.search-panel,.no-print{display:none!important;}.tab-wrap{border:none!important;}}
</style>

<div class="main-content app-content">
<div class="container-fluid" style="padding-bottom:40px;">

<!-- ══ Page Header ══ -->
<div class="or-hdr">
  <div>
    <h4><i class="ri-user-star-line"></i> Owner-wise Trip Report</h4>
    <p>Select a vehicle owner to view their complete trip &amp; payable summary</p>
  </div>
  <div class="d-flex gap-2 no-print">
    <?php if($searched && !empty($trips)): ?>
    <button class="btn btn-sm fw-bold text-white" style="background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.35);border-radius:9px;" onclick="window.print()">
      <i class="ri-printer-line me-1"></i>Print
    </button>
    <?php
      $exQs = http_build_query(['datePreset'=>$datePreset,'dateFrom'=>$dateFrom,'dateTo'=>$dateTo,'ownerId'=>$selectedId,'search'=>1,'export'=>'excel']);
    ?>
    <a href="?<?= $exQs ?>" class="btn btn-sm fw-bold" style="background:#217346;color:#fff;border-radius:9px;border:none;" title="Download Excel">
      <i class="ri-file-excel-2-line me-1"></i>Excel
    </a>
    <?php endif; ?>
    <a href="OwnerReport.php" class="btn btn-sm btn-light fw-bold" style="border-radius:9px;" title="Reset"><i class="ri-refresh-line"></i></a>
  </div>
</div>

<div class="tab-wrap">

  <!-- ══ SEARCH PANEL ══ -->
  <div class="search-panel">
    <div class="sp-title"><i class="ri-user-star-line" style="color:#15803d;"></i> Select Vehicle Owner</div>

    <form method="GET" action="OwnerReport.php">
      <input type="hidden" name="datePreset" value="<?= htmlspecialchars($datePreset) ?>">
      <?php if($datePreset==='custom'): ?>
      <input type="hidden" name="dateFrom" value="<?= htmlspecialchars($dateFrom) ?>">
      <input type="hidden" name="dateTo"   value="<?= htmlspecialchars($dateTo) ?>">
      <?php endif; ?>
      <input type="hidden" name="search" value="1">

      <div class="d-flex align-items-center gap-3 flex-wrap">
        <select name="ownerId" class="owner-select" required>
          <option value="">-- Select Owner --</option>
          <?php foreach($ownerList as $o): ?>
          <option value="<?= $o['VehicleOwnerId'] ?>" <?= $selectedId==$o['VehicleOwnerId']?'selected':'' ?>>
            <?= htmlspecialchars($o['OwnerName']) ?>
          </option>
          <?php endforeach; ?>
        </select>

        <button type="submit" class="btn-search">
          <i class="ri-search-line"></i> Search
        </button>

        <?php if($searched): ?>
        <a href="OwnerReport.php" class="btn btn-sm btn-outline-secondary fw-bold" style="border-radius:9px;height:38px;padding:0 14px;display:inline-flex;align-items:center;gap:5px;">
          <i class="ri-close-line"></i> Clear
        </a>
        <?php endif; ?>
      </div>

      <!-- Date filter -->
      <div class="date-row">
        <span class="df-lbl"><i class="ri-calendar-line me-1"></i>Period:</span>
        <?php
        $presets = ['all'=>'All Time','today'=>'Today','yesterday'=>'Yesterday','thisweek'=>'This Week','thismonth'=>'This Month'];
        foreach($presets as $key=>$label):
          $isAct = $datePreset===$key;
        ?>
        <a href="?datePreset=<?= $key ?><?= $selectedId?'&ownerId='.$selectedId.'&search=1':'' ?>"
           class="df-btn <?= $isAct?'active':'' ?>"><?= $label ?></a>
        <?php endforeach; ?>
        <span class="df-lbl" style="margin-left:4px;">Custom:</span>
        <input type="date" name="dateFrom" value="<?= htmlspecialchars($datePreset==='custom'?$dateFrom:'') ?>"
          style="border:2px solid #e2e8f0;border-radius:8px;padding:3px 8px;font-size:12px;font-weight:600;outline:none;height:32px;"
          onchange="this.form.elements['datePreset'].value='custom'">
        <span class="df-lbl">to</span>
        <input type="date" name="dateTo" value="<?= htmlspecialchars($datePreset==='custom'?$dateTo:'') ?>"
          style="border:2px solid #e2e8f0;border-radius:8px;padding:3px 8px;font-size:12px;font-weight:600;outline:none;height:32px;"
          onchange="this.form.elements['datePreset'].value='custom'">
        <?php if($datePreset==='custom' && $dateFrom && $dateTo): ?>
        <span style="background:#dcfce7;border:1px solid #86efac;border-radius:6px;padding:3px 10px;font-size:11px;font-weight:700;color:#166534;">
          <?= date('d M Y',strtotime($dateFrom)) ?> &rarr; <?= date('d M Y',strtotime($dateTo)) ?>
        </span>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- ══ RESULTS ══ -->

  <?php if(!$searched): ?>
  <!-- Nothing searched yet -->
  <div class="empty-state">
    <i class="ri-user-star-line"></i>
    <p style="font-size:16px;font-weight:700;color:#64748b;">Select an owner and click Search</p>
    <p style="font-size:13px;color:#94a3b8;margin-top:6px;">Their complete trip &amp; payable history will appear here</p>
  </div>

  <?php elseif(empty($trips)): ?>
  <!-- Searched but no results -->
  <div class="empty-state">
    <i class="ri-inbox-line"></i>
    <p style="font-size:15px;font-weight:700;color:#64748b;">"<?= htmlspecialchars($ownerName) ?>" — No trips found</p>
    <p style="font-size:13px;color:#94a3b8;margin-top:6px;">Try adjusting the date range and search again</p>
  </div>

  <?php else: ?>
  <!-- ══ Results ══ -->

  <!-- Result header -->
  <div class="result-hdr">
    <div class="result-owner-name">
      <i class="ri-user-star-line" style="background:#15803d;color:#fff;padding:6px;border-radius:8px;font-size:14px;"></i>
      <?= htmlspecialchars($ownerName) ?>
    </div>
    <div class="result-meta">
      <span class="meta-pill" style="background:#dcfce7;color:#166534;"><?= $totals['cnt'] ?> Trips</span>
      <?php
        // count by type
        $regCnt = count(array_filter($trips, fn($t)=>$t['TripType']==='Regular'));
        $agtCnt = count(array_filter($trips, fn($t)=>$t['TripType']==='Agent'));
        if($regCnt) echo '<span class="meta-pill" style="background:#eff6ff;color:#1d4ed8;">'.$regCnt.' Regular</span>';
        if($agtCnt) echo '<span class="meta-pill" style="background:#fffbeb;color:#b45309;">'.$agtCnt.' Agent</span>';
      ?>
      <?php if($datePreset!=='all'): ?>
      <span class="meta-pill" style="background:#e0f2fe;color:#0369a1;">
        <?php
          if($datePreset==='today') echo 'Today';
          elseif($datePreset==='yesterday') echo 'Yesterday';
          elseif($datePreset==='thisweek') echo 'This Week';
          elseif($datePreset==='thismonth') echo date('F Y');
          elseif($datePreset==='custom'&&$dateFrom&&$dateTo) echo date('d M Y',strtotime($dateFrom)).' &rarr; '.date('d M Y',strtotime($dateTo));
        ?>
      </span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Summary pills -->
  <?php
    $ownerPayable = $totals['total'] - $totals['comm'];
    $paidTrips    = count(array_filter($trips, fn($t)=>$t['OwnerPaymentStatus']==='Paid'));
    $unpaidTrips  = count(array_filter($trips, fn($t)=>$t['OwnerPaymentStatus']==='Unpaid'));
    $partTrips    = count(array_filter($trips, fn($t)=>$t['OwnerPaymentStatus']==='PartiallyPaid'));
  ?>
  <div class="srow">
    <div class="spill"><div class="sico" style="background:#dcfce7;"><i class="ri-route-line" style="color:#15803d;"></i></div><div><div class="snum" style="color:#15803d;"><?= $totals['cnt'] ?></div><div class="slbl">Total Trips</div></div></div>
    <div class="spill"><div class="sico" style="background:#dcfce7;"><i class="ri-money-dollar-circle-line" style="color:#15803d;"></i></div><div><div class="snum" style="font-size:11px;color:#15803d;"><?= r($totals['freight']) ?></div><div class="slbl">Freight</div></div></div>
    <div class="spill"><div class="sico" style="background:#e0f2fe;"><i class="ri-calculator-line" style="color:#0369a1;"></i></div><div><div class="snum" style="font-size:11px;color:#0369a1;"><?= r($totals['total']) ?></div><div class="slbl">Total Amount</div></div></div>
    <div class="spill"><div class="sico" style="background:#fef9c3;"><i class="ri-percent-line" style="color:#d97706;"></i></div><div><div class="snum" style="font-size:11px;color:#d97706;"><?= r($totals['comm']) ?></div><div class="slbl">Commission</div></div></div>
    <div class="spill" style="border-left:4px solid #15803d;"><div class="sico" style="background:#dcfce7;"><i class="ri-wallet-3-line" style="color:#15803d;"></i></div><div><div class="snum" style="font-size:11px;color:#15803d;"><?= r($ownerPayable) ?></div><div class="slbl">Owner Payable</div></div></div>
    <?php if($unpaidTrips>0): ?>
    <div class="spill" style="border-left:4px solid #dc2626;"><div class="sico" style="background:#fee2e2;"><i class="ri-time-line" style="color:#dc2626;"></i></div><div><div class="snum" style="color:#dc2626;"><?= $unpaidTrips ?></div><div class="slbl">Unpaid Trips</div></div></div>
    <?php endif; ?>
    <?php if($paidTrips>0): ?>
    <div class="spill" style="border-left:4px solid #166534;"><div class="sico" style="background:#dcfce7;"><i class="ri-checkbox-circle-line" style="color:#166534;"></i></div><div><div class="snum" style="color:#166534;"><?= $paidTrips ?></div><div class="slbl">Paid Trips</div></div></div>
    <?php endif; ?>
  </div>

  <!-- Trips Table -->
  <div style="overflow-x:auto;padding:0 0 20px;">
  <table class="or-table">
    <thead>
      <tr style="background:#15803d;">
        <th style="min-width:60px;">GC No.</th>
        <th style="min-width:85px;">Date</th>
        <th style="min-width:75px;">Type</th>
        <th style="min-width:115px;">Vehicle</th>
        <th style="min-width:155px;">Route</th>
        <th style="min-width:90px;">Freight</th>
        <th style="min-width:75px;">Labour</th>
        <th style="min-width:75px;">Holding</th>
        <th style="min-width:75px;">Other</th>
        <th style="min-width:90px;background:#0a3d26;">Total Amt</th>
        <th style="min-width:90px;background:#713f12;">Commission</th>
        <th style="min-width:100px;background:#166534;">Owner Payable</th>
        <th style="min-width:90px;background:#4c1d95;">Pay Status</th>
        <th style="min-width:75px;background:#374151;">Trip Status</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach($trips as $t):
      $total        = $t['FreightAmount']+$t['LabourCharge']+$t['HoldingCharge']+$t['OtherCharge'];
      $commission   = floatval($t['CommissionAmount']);
      $ownerPay     = $total - $commission;
      $gcNo         = str_pad($t['TripId'],4,'0',STR_PAD_LEFT);
      $rc = $t['TripStatus']==='Open' ? 'tr-open' : ($t['TripStatus']==='Completed' ? 'tr-completed' : '');
    ?>
    <tr class="<?= $rc ?>">
      <td class="gc-cell" style="color:#14532d;"><?= $gcNo ?></td>
      <td style="font-size:12px;font-weight:500;color:#374151;"><?= date('d M Y',strtotime($t['TripDate'])) ?></td>
      <td style="text-align:center;"><?= typeBadge($t['TripType']) ?></td>
      <td>
        <span style="display:inline-block;background:#15803d;color:#fff;font-size:10.5px;font-weight:700;padding:2px 8px;border-radius:5px;letter-spacing:.5px;">
          <?= htmlspecialchars($t['VehicleNumber']??'&#8212;') ?>
        </span>
      </td>
      <td style="font-size:12px;font-weight:600;color:#374151;">
        <?= htmlspecialchars($t['FromLocation']??'') ?> &#8594; <?= htmlspecialchars($t['ToLocation']??'') ?>
      </td>
      <td class="amt">&#8377;<?= number_format($t['FreightAmount'],0) ?></td>
      <?= amtTd($t['LabourCharge']) ?>
      <?= amtTd($t['HoldingCharge']) ?>
      <?= amtTd($t['OtherCharge']) ?>
      <td class="amt" style="background:#e8f5e9;color:#1b5e20;font-weight:900;">&#8377;<?= number_format($total,0) ?></td>
      <td class="amt" style="color:#d97706;"><?= $commission>0 ? '&#8377;'.number_format($commission,0) : '<span style="color:#c8d5e3;">&#8212;</span>' ?></td>
      <td class="amt" style="background:#dcfce7;color:#14532d;font-weight:900;font-size:13px;">&#8377;<?= number_format($ownerPay,0) ?></td>
      <td style="text-align:center;">
        <?= opBadge($t['OwnerPaymentStatus']) ?>
        <?php if($t['DirectPayStatus']==='PaidDirectly'): ?>
        <div style="margin-top:3px;"><span class="rb" style="background:#f5f3ff;color:#6d28d9;border:1px solid #c4b5fd;font-size:9.5px;">Direct</span></div>
        <?php endif; ?>
      </td>
      <td style="text-align:center;"><?= stBadge($t['TripStatus']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="background:#15803d;">
        <th colspan="5" style="color:#fff;text-align:right;">TOTAL &mdash; <?= $totals['cnt'] ?> Trips</th>
        <th style="text-align:right;color:#fff;font-family:'Courier New',monospace;font-weight:900;">&#8377;<?= number_format($totals['freight'],0) ?></th>
        <th style="text-align:right;color:rgba(255,255,255,.7);font-family:'Courier New',monospace;"><?= $totals['labour']>0  ? '&#8377;'.number_format($totals['labour'],0)  : '&#8212;' ?></th>
        <th style="text-align:right;color:rgba(255,255,255,.7);font-family:'Courier New',monospace;"><?= $totals['holding']>0 ? '&#8377;'.number_format($totals['holding'],0) : '&#8212;' ?></th>
        <th style="text-align:right;color:rgba(255,255,255,.7);font-family:'Courier New',monospace;"><?= $totals['other']>0   ? '&#8377;'.number_format($totals['other'],0)   : '&#8212;' ?></th>
        <th style="text-align:right;font-family:'Courier New',monospace;font-weight:900;font-size:13px;background:#0a3d26;color:#fff;">&#8377;<?= number_format($totals['total'],0) ?></th>
        <th style="text-align:right;font-family:'Courier New',monospace;background:#713f12;color:#fde68a;"><?= $totals['comm']>0 ? '&#8377;'.number_format($totals['comm'],0) : '&#8212;' ?></th>
        <th style="text-align:right;font-family:'Courier New',monospace;font-weight:900;font-size:13px;background:#166534;color:#fff;">&#8377;<?= number_format($ownerPayable,0) ?></th>
        <th colspan="2"></th>
      </tr>
    </tfoot>
  </table>
  </div>

  <!-- ══ FINANCIAL SUMMARY ══ -->
  <div style="margin:0 20px 24px;border:2px solid #15803d;border-radius:14px;overflow:hidden;">
    <div style="background:linear-gradient(135deg,#14532d,#15803d);padding:12px 20px;display:flex;align-items:center;gap:8px;">
      <i class="ri-scales-3-line" style="color:#fff;font-size:16px;"></i>
      <span style="color:#fff;font-size:14px;font-weight:900;letter-spacing:.3px;">Financial Summary</span>
      <span style="margin-left:auto;color:rgba(255,255,255,.65);font-size:12px;"><?= htmlspecialchars($ownerName) ?></span>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;background:#fff;">

      <!-- LEFT — What we OWE owner -->
      <div style="padding:20px 24px;border-right:2px solid #e2e8f0;">
        <div style="font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.8px;margin-bottom:14px;display:flex;align-items:center;gap:6px;">
          <i class="ri-arrow-up-circle-fill" style="color:#dc2626;font-size:15px;"></i> To Pay (to Owner)
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;">
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#f8fafc;border-radius:8px;border-left:3px solid #e2e8f0;">
            <span style="font-size:12.5px;font-weight:600;color:#374151;">Total Trip Amount</span>
            <span style="font-family:'Courier New',monospace;font-weight:800;font-size:13px;color:#374151;">&#8377;<?= number_format($totals['total'],0) ?></span>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#fffbeb;border-radius:8px;border-left:3px solid #d97706;">
            <span style="font-size:12.5px;font-weight:600;color:#92400e;">Commission Deducted</span>
            <span style="font-family:'Courier New',monospace;font-weight:800;font-size:13px;color:#d97706;">&#8211; &#8377;<?= number_format($totals['comm'],0) ?></span>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#f0fdf4;border-radius:8px;border-left:3px solid #15803d;">
            <span style="font-size:12.5px;font-weight:600;color:#166534;">Owner Payable</span>
            <span style="font-family:'Courier New',monospace;font-weight:800;font-size:13px;color:#166534;">&#8377;<?= number_format($totals['ownerPayable'],0) ?></span>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#dcfce7;border-radius:8px;border-left:3px solid #16a34a;">
            <span style="font-size:12.5px;font-weight:600;color:#166534;">Already Paid</span>
            <span style="font-family:'Courier New',monospace;font-weight:800;font-size:13px;color:#166534;">&#8211; &#8377;<?= number_format($totals['paid'],0) ?></span>
          </div>

          <!-- BALANCE DUE -->
          <div style="display:flex;justify-content:space-between;align-items:center;padding:11px 14px;background:<?= $totals['balance']>0?'#fef2f2':'#f0fdf4' ?>;border-radius:10px;border:2px solid <?= $totals['balance']>0?'#dc2626':'#16a34a' ?>;margin-top:4px;">
            <span style="font-size:13px;font-weight:900;color:<?= $totals['balance']>0?'#b91c1c':'#14532d' ?>;">
              <i class="<?= $totals['balance']>0?'ri-arrow-up-circle-fill':'ri-checkbox-circle-fill' ?> me-1"></i>
              <?= $totals['balance']>0 ? 'Balance Due to Owner' : 'Fully Settled' ?>
            </span>
            <span style="font-family:'Courier New',monospace;font-weight:900;font-size:16px;color:<?= $totals['balance']>0?'#b91c1c':'#14532d' ?>;">
              &#8377;<?= number_format($totals['balance'],0) ?>
            </span>
          </div>
        </div>
      </div>

      <!-- RIGHT — Payment status breakdown -->
      <div style="padding:20px 24px;">
        <div style="font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.8px;margin-bottom:14px;display:flex;align-items:center;gap:6px;">
          <i class="ri-pie-chart-2-line" style="color:#15803d;font-size:15px;"></i> Payment Status Breakdown
        </div>
        <?php
          $paidAmt    = array_sum(array_column(array_filter($trips,fn($t)=>$t['OwnerPaymentStatus']==='Paid'),         'TotalPaid'));
          $partAmt    = array_sum(array_column(array_filter($trips,fn($t)=>$t['OwnerPaymentStatus']==='PartiallyPaid'), 'TotalPaid'));
          $unpaidCnt  = count(array_filter($trips,fn($t)=>$t['OwnerPaymentStatus']==='Unpaid'));
          $partCnt    = count(array_filter($trips,fn($t)=>$t['OwnerPaymentStatus']==='PartiallyPaid'));
          $paidCnt    = count(array_filter($trips,fn($t)=>$t['OwnerPaymentStatus']==='Paid'));
          $statusRows = [
            ['Paid',         $paidCnt,  '#dcfce7','#166534'],
            ['Partial',      $partCnt,  '#ffedd5','#c2410c'],
            ['Unpaid',       $unpaidCnt,'#fee2e2','#b91c1c'],
          ];
          foreach($statusRows as [$lbl,$cnt,$bg,$tc]):
            if(!$cnt) continue;
            $pct = $totals['cnt'] > 0 ? round($cnt/$totals['cnt']*100) : 0;
        ?>
        <div style="margin-bottom:10px;">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
            <span style="font-size:12px;font-weight:700;color:<?= $tc ?>;"><?= $lbl ?></span>
            <span style="font-size:12px;font-weight:800;color:#374151;"><?= $cnt ?> trips (<?= $pct ?>%)</span>
          </div>
          <div style="background:#f1f5f9;border-radius:6px;height:8px;overflow:hidden;">
            <div style="width:<?= $pct ?>%;height:8px;background:<?= $tc ?>;border-radius:6px;"></div>
          </div>
        </div>
        <?php endforeach; ?>

        <!-- Trip type split -->
        <?php
          $regCnt2 = count(array_filter($trips,fn($t)=>$t['TripType']==='Regular'));
          $agtCnt2 = count(array_filter($trips,fn($t)=>$t['TripType']==='Agent'));
        ?>
        <div style="margin-top:16px;padding-top:14px;border-top:1px solid #e2e8f0;">
          <div style="font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px;">Trip Type</div>
          <?php if($regCnt2): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 10px;background:#eff6ff;border-radius:6px;margin-bottom:5px;">
            <span style="font-size:12px;font-weight:700;color:#1d4ed8;">Regular</span>
            <span style="font-size:12px;font-weight:800;color:#1d4ed8;"><?= $regCnt2 ?> trips</span>
          </div>
          <?php endif; ?>
          <?php if($agtCnt2): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 10px;background:#fffbeb;border-radius:6px;margin-bottom:5px;">
            <span style="font-size:12px;font-weight:700;color:#b45309;">Agent</span>
            <span style="font-size:12px;font-weight:800;color:#b45309;"><?= $agtCnt2 ?> trips</span>
          </div>
          <?php endif; ?>
        </div>

        <!-- Net position box -->
        <div style="margin-top:14px;padding:12px 14px;background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1.5px solid #86efac;border-radius:10px;text-align:center;">
          <div style="font-size:10.5px;font-weight:800;color:#166534;text-transform:uppercase;letter-spacing:.5px;">Commission Saved (Net)</div>
          <div style="font-family:'Courier New',monospace;font-weight:900;font-size:18px;color:#14532d;margin-top:4px;">&#8377;<?= number_format($totals['comm'],0) ?></div>
          <div style="font-size:10.5px;color:#4ade80;margin-top:2px;">Deducted from owner payable</div>
        </div>
      </div>

    </div>
  </div>

  <?php endif; ?>

</div><!-- /tab-wrap -->

</div></div>

<script>
window.addEventListener('offline', function(){ if(typeof SRV!=='undefined') SRV.toast.warning('Internet Disconnected!'); });
window.addEventListener('online',  function(){ if(typeof SRV!=='undefined') SRV.toast.success('Back Online!'); });
</script>
<?php require_once "../layout/footer.php"; ?>
