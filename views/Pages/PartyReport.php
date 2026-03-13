<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once "../../businessLogics/Admin.php";
require_once "../../businessLogics/PartyReport_logic.php";
Admin::checkAuth();

$activeTab  = $_GET['tab']       ?? 'consigner';
$datePreset = $_GET['datePreset'] ?? 'all';
$selectedId = intval($_GET['partyId'] ?? 0);
$searched   = isset($_GET['search']) && $selectedId > 0;

/* ── EXCEL EXPORT ── */
if (isset($_GET['export']) && $_GET['export'] === 'excel' && $searched) {
    list($dateFrom, $dateTo) = PartyReport::resolveDateRange($datePreset, $_GET['dateFrom'] ?? '', $_GET['dateTo'] ?? '');
    $trips   = $activeTab === 'consigner'
        ? PartyReport::getConsignerTrips($selectedId, $dateFrom, $dateTo)
        : PartyReport::getAgentTrips($selectedId, $dateFrom, $dateTo);
    $totals  = PartyReport::totals($trips);
    $pName   = '';
    $list    = $activeTab === 'consigner' ? PartyReport::getConsignerList() : PartyReport::getAgentList();
    foreach ($list as $p) { if ($p['PartyId'] == $selectedId) { $pName = $p['PartyName']; break; } }
    $label   = $activeTab === 'consigner' ? 'Consigner' : 'Agent';
    $fname   = 'PartyReport_' . preg_replace('/[^A-Za-z0-9]/', '_', $pName) . '_' . date('Y-m-d') . '.xls';

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Cache-Control: max-age=0');
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8"><style>
    th{background:#1e3a8a;color:#fff;font-weight:bold;padding:6px 10px;text-align:center;border:1px solid #ccc;}
    td{padding:5px 8px;border:1px solid #ddd;white-space:nowrap;}
    .amt{text-align:right;font-family:Courier New,monospace;}
    .total-row td{background:#0a3d26;color:#fff;font-weight:bold;}
    .hdr{background:#1e3a8a;color:#fff;font-weight:bold;font-size:14pt;padding:8px;}
    .sub{color:#555;padding:4px 8px;}
    </style></head><body>';

    echo '<table><tr><td colspan="' . ($activeTab === 'consigner' ? '15' : '13') . '" class="hdr">' . htmlspecialchars($label) . ' Trip Report — ' . htmlspecialchars($pName) . '</td></tr>';
    if ($datePreset !== 'all' && $dateFrom && $dateTo)
        echo '<tr><td colspan="15" class="sub">Period: ' . date('d M Y', strtotime($dateFrom)) . ' to ' . date('d M Y', strtotime($dateTo)) . '</td></tr>';
    echo '<tr><td colspan="15" class="sub">Generated: ' . date('d M Y, h:i A') . ' | Total Trips: ' . $totals['cnt'] . '</td></tr>';
    echo '<tr><td colspan="15"></td></tr>';

    // Header row
    echo '<tr>';
    foreach (['GC No.','Date','Route','Freight','Labour','Holding','Other','Total Amt','Advance','TDS','Net Amount','Commission'] as $h)
        echo '<th>' . $h . '</th>';
    if ($activeTab === 'consigner') { echo '<th>Bill No.</th><th>Bill Status</th>'; }
    echo '<th>Status</th>';
    echo '</tr>';

    // Data rows
    foreach ($trips as $t) {
        $total = $t['FreightAmount']+$t['LabourCharge']+$t['HoldingCharge']+$t['OtherCharge'];
        $net   = $total - $t['AdvanceAmount'] - $t['TDS'];
        echo '<tr>';
        echo '<td>' . str_pad($t['TripId'], 4, '0', STR_PAD_LEFT) . '</td>';
        echo '<td>' . date('d M Y', strtotime($t['TripDate'])) . '</td>';
        echo '<td>' . htmlspecialchars(($t['FromLocation']??'') . ' → ' . ($t['ToLocation']??'')) . '</td>';
        echo '<td class="amt">' . number_format($t['FreightAmount'], 0) . '</td>';
        echo '<td class="amt">' . ($t['LabourCharge']  > 0 ? number_format($t['LabourCharge'],  0) : '') . '</td>';
        echo '<td class="amt">' . ($t['HoldingCharge'] > 0 ? number_format($t['HoldingCharge'], 0) : '') . '</td>';
        echo '<td class="amt">' . ($t['OtherCharge']   > 0 ? number_format($t['OtherCharge'],   0) : '') . '</td>';
        echo '<td class="amt" style="background:#e8f5e9;font-weight:bold;">' . number_format($total, 0) . '</td>';
        echo '<td class="amt">' . ($t['AdvanceAmount']    > 0 ? number_format($t['AdvanceAmount'],    0) : '') . '</td>';
        echo '<td class="amt">' . ($t['TDS']               > 0 ? number_format($t['TDS'],               0) : '') . '</td>';
        echo '<td class="amt" style="background:#e3f2fd;font-weight:bold;">' . number_format($net, 0) . '</td>';
        echo '<td class="amt">' . ($t['CommissionAmount'] > 0 ? number_format($t['CommissionAmount'], 0) : '') . '</td>';
        if ($activeTab === 'consigner') {
            echo '<td>' . htmlspecialchars($t['BillNo'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($t['BillStatus'] ?? '') . '</td>';
        }
        echo '<td>' . htmlspecialchars($t['TripStatus']) . '</td>';
        echo '</tr>';
    }

    // Totals row
    $cols = $activeTab === 'consigner' ? 15 : 13;
    echo '<tr class="total-row">';
    echo '<td colspan="3" style="text-align:right;background:#1e3a8a;color:#fff;font-weight:bold;">TOTAL &mdash; ' . $totals['cnt'] . ' Trips</td>';
    foreach (['freight','labour','holding','other','total','adv','tds','net','comm'] as $k)
        echo '<td class="amt" style="background:#0a3d26;color:#fff;font-weight:bold;">' . ($totals[$k] > 0 ? number_format($totals[$k], 0) : '') . '</td>';
    if ($activeTab === 'consigner') echo '<td colspan="2" style="background:#166534;color:#fff;"></td>';
    echo '<td></td></tr>';

    // Financial Summary
    $unbilled  = $totals['total'] - $totals['bill'];
    $pending   = max(0, $totals['bill'] - $totals['received']);
    $lbl       = $activeTab === 'consigner' ? 'Consigner' : 'Agent';
    $cL        = 5;
    $cR        = $cols - $cL;
    $b         = 'font-weight:bold;';
    $a         = 'text-align:right;font-family:Courier New,monospace;font-weight:bold;';

    echo '<tr><td colspan="' . $cols . '"></td></tr>';
    echo '<tr><td colspan="' . $cols . '" style="background:#1e3a8a;color:#fff;font-weight:bold;font-size:12pt;padding:8px;">Financial Summary &mdash; ' . htmlspecialchars($pName) . '</td></tr>';
    echo '<tr><td colspan="' . $cols . '"></td></tr>';

    echo '<tr><td colspan="' . $cols . '" style="background:#e2e8f0;' . $b . 'padding:5px 8px;">&#9654; TO RECEIVE (from ' . $lbl . ')</td></tr>';

    echo '<tr><td colspan="' . $cL . '" style="background:#f0fdf4;' . $b . '">Total Trip Amount</td><td colspan="' . $cR . '" style="background:#f0fdf4;' . $a . '">&#8377;' . number_format($totals['total'], 0) . '</td></tr>';

    if ($activeTab === 'consigner') {
        echo '<tr><td colspan="' . $cL . '" style="background:#eff6ff;' . $b . '">Total Billed</td><td colspan="' . $cR . '" style="background:#eff6ff;' . $a . '">&#8377;' . number_format($totals['bill'], 0) . '</td></tr>';
        if ($unbilled > 0)
            echo '<tr><td colspan="' . $cL . '" style="background:#fffbeb;' . $b . '">Not Yet Billed</td><td colspan="' . $cR . '" style="background:#fffbeb;' . $a . '">&#8377;' . number_format($unbilled, 0) . '</td></tr>';
        echo '<tr><td colspan="' . $cL . '" style="background:#f0fdf4;' . $b . '">Already Received</td><td colspan="' . $cR . '" style="background:#f0fdf4;' . $a . '">&#8377;' . number_format($totals['received'], 0) . '</td></tr>';
    }

    if ($totals['comm'] > 0)
        echo '<tr><td colspan="' . $cL . '" style="background:#fffbeb;' . $b . '">Commission Earned</td><td colspan="' . $cR . '" style="background:#fffbeb;' . $a . '">&#8377;' . number_format($totals['comm'], 0) . '</td></tr>';

    $pendingAmt   = $activeTab === 'consigner' ? $pending : $totals['comm'];
    $pendingLabel = $activeTab === 'consigner' ? 'Pending to Receive' : 'Commission Pending';
    $pendingBg    = $pendingAmt > 0 ? '#fef2f2' : '#f0fdf4';
    echo '<tr>';
    echo '<td colspan="' . $cL . '" style="background:' . $pendingBg . ';font-weight:bold;font-size:11pt;border-top:2px solid #166534;">' . $pendingLabel . '</td>';
    echo '<td colspan="' . $cR . '" style="background:' . $pendingBg . ';' . $a . 'font-size:13pt;border-top:2px solid #166534;">&#8377;' . number_format($pendingAmt, 0) . '</td>';
    echo '</tr>';

    // Trip status
    echo '<tr><td colspan="' . $cols . '"></td></tr>';
    echo '<tr><td colspan="' . $cols . '" style="background:#e2e8f0;' . $b . 'padding:5px 8px;">&#9654; TRIP STATUS BREAKDOWN</td></tr>';
    foreach (['Open' => '#fffbeb', 'Billed' => '#eff6ff', 'Completed' => '#f0fdf4'] as $st => $bg) {
        $cnt = count(array_filter($trips, fn($t) => $t['TripStatus'] === $st));
        if (!$cnt) continue;
        $pct = round($cnt / $totals['cnt'] * 100);
        echo '<tr><td colspan="' . $cL . '" style="background:' . $bg . ';' . $b . '">' . $st . '</td><td colspan="' . $cR . '" style="background:' . $bg . ';' . $a . '">' . $cnt . ' trips (' . $pct . '%)</td></tr>';
    }

    if ($activeTab === 'consigner') {
        echo '<tr><td colspan="' . $cols . '"></td></tr>';
        echo '<tr><td colspan="' . $cols . '" style="background:#e2e8f0;' . $b . 'padding:5px 8px;">&#9654; BILL STATUS BREAKDOWN</td></tr>';
        foreach (['Generated' => '#eff6ff', 'Paid' => '#f0fdf4', 'PartiallyPaid' => '#fffbeb'] as $st => $bg) {
            $cnt = count(array_filter($trips, fn($t) => ($t['BillStatus'] ?? '') === $st));
            if (!$cnt) continue;
            echo '<tr><td colspan="' . $cL . '" style="background:' . $bg . ';' . $b . '">' . $st . '</td><td colspan="' . $cR . '" style="background:' . $bg . ';' . $a . '">' . $cnt . ' bills</td></tr>';
        }
        $noBill = count(array_filter($trips, fn($t) => empty($t['BillNo'])));
        if ($noBill)
            echo '<tr><td colspan="' . $cL . '" style="background:#f8fafc;' . $b . '">No Bill</td><td colspan="' . $cR . '" style="background:#f8fafc;' . $a . '">' . $noBill . ' trips</td></tr>';
    }

    echo '</table></body></html>';
    exit;
}

list($dateFrom, $dateTo) = PartyReport::resolveDateRange(
    $datePreset,
    $_GET['dateFrom'] ?? '',
    $_GET['dateTo']   ?? ''
);

/* Party lists for dropdowns */
$consignerList = PartyReport::getConsignerList();
$agentList     = PartyReport::getAgentList();

/* Only fetch trips if search clicked */
$trips = [];
$totals = [];
$partyName = '';

if ($searched) {
    if ($activeTab === 'consigner') {
        $trips  = PartyReport::getConsignerTrips($selectedId, $dateFrom, $dateTo);
        $totals = PartyReport::totals($trips);
        foreach ($consignerList as $p) { if ($p['PartyId'] == $selectedId) { $partyName = $p['PartyName']; break; } }
    } else {
        $trips  = PartyReport::getAgentTrips($selectedId, $dateFrom, $dateTo);
        $totals = PartyReport::totals($trips);
        foreach ($agentList as $p) { if ($p['PartyId'] == $selectedId) { $partyName = $p['PartyName']; break; } }
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
function bsBadge($s){ $s=$s??'';
    if($s==='Paid')          return '<span class="rb rb-paid">Paid</span>';
    if($s==='PartiallyPaid') return '<span class="rb rb-part">Partial</span>';
    if($s==='Generated')     return '<span class="rb rb-bld">Generated</span>';
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
.pr-hdr{background:linear-gradient(135deg,#1e1b4b,#4338ca 60%,#6366f1);border-radius:14px;padding:20px 26px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
.pr-hdr h4{color:#fff;font-weight:900;font-size:19px;margin:0;display:flex;align-items:center;gap:9px;}
.pr-hdr p{color:rgba(255,255,255,.65);font-size:12px;margin:3px 0 0;}
/* ── Tabs ── */
.main-tab-nav{display:flex;gap:4px;margin-bottom:0;}
.mtnav{padding:11px 24px;font-size:13px;font-weight:800;cursor:pointer;border:1px solid #e2e8f0;border-bottom:none;border-radius:12px 12px 0 0;background:#f1f5f9;color:#64748b;transition:.15s;display:flex;align-items:center;gap:8px;text-decoration:none;}
.mtnav:hover{background:#e2e8f0;color:#374151;}
.mtnav.act-cgr{background:#1e3a8a;color:#fff;border-color:#1e3a8a;}
.mtnav.act-agt{background:#92400e;color:#fff;border-color:#92400e;}
.mtbadge{display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:18px;border-radius:9px;font-size:10px;font-weight:800;padding:0 5px;background:rgba(255,255,255,.25);color:#fff;}
.mtbadge-gray{background:#e2e8f0;color:#64748b;}
.tab-wrap{border:1px solid #e2e8f0;border-radius:0 12px 12px 12px;background:#fff;}
/* ── Search panel ── */
.search-panel{padding:20px 22px;border-bottom:1px solid #e2e8f0;background:#f8fafc;}
.search-panel-cgr{background:#eff6ff;border-bottom:1px solid #bfdbfe;}
.search-panel-agt{background:#fffbeb;border-bottom:1px solid #fde68a;}
.sp-title{font-size:12px;font-weight:800;color:#374151;margin-bottom:10px;display:flex;align-items:center;gap:6px;text-transform:uppercase;letter-spacing:.5px;}
select.party-select{font-size:13px;font-weight:600;height:38px;border-radius:9px;border:2px solid #d1d5db;padding:0 12px;background:#fff;color:#1e293b;width:100%;cursor:pointer;outline:none;max-width:360px;}
select.party-select:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.15);}
select.party-select.cgr-select:focus{border-color:#1d4ed8;box-shadow:0 0 0 3px rgba(29,78,216,.15);}
select.party-select.agt-select:focus{border-color:#d97706;box-shadow:0 0 0 3px rgba(217,119,6,.15);}
.btn-search{padding:8px 22px;border-radius:9px;font-size:13px;font-weight:800;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:.15s;}
.btn-search:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,.15);}
.btn-search-cgr{background:#1d4ed8;color:#fff;}
.btn-search-agt{background:#d97706;color:#fff;}
/* ── Date filter row ── */
.date-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:10px;}
.df-btn{padding:4px 11px;border-radius:16px;font-size:11.5px;font-weight:700;border:2px solid #e2e8f0;background:#fff;color:#64748b;cursor:pointer;text-decoration:none;display:inline-block;transition:.15s;white-space:nowrap;}
.df-btn:hover,.df-btn:focus{border-color:#6366f1;color:#6366f1;}
.df-btn.active{border-color:#1d4ed8;background:#1d4ed8;color:#fff;}
.df-btn.agt-active{border-color:#d97706;background:#d97706;color:#fff;}
.df-lbl{font-size:11.5px;font-weight:700;color:#6b7280;white-space:nowrap;}
/* ── Empty state ── */
.empty-state{padding:60px 20px;text-align:center;color:#94a3b8;}
.empty-state i{font-size:52px;opacity:.3;display:block;margin-bottom:12px;}
.empty-state p{font-size:14px;margin:0;}
/* ── Result header ── */
.result-hdr{padding:14px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;border-bottom:1px solid #e2e8f0;}
.result-hdr-cgr{background:#eff6ff;}
.result-hdr-agt{background:#fffbeb;}
.result-party-name{font-size:16px;font-weight:900;display:flex;align-items:center;gap:9px;}
.result-meta{display:flex;gap:8px;flex-wrap:wrap;}
.meta-pill{font-size:11.5px;font-weight:700;padding:4px 11px;border-radius:20px;}
/* ── Summary pills ── */
.srow{display:flex;gap:8px;flex-wrap:wrap;padding:14px 20px;border-bottom:1px solid #e2e8f0;}
.spill{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:9px 13px;display:flex;align-items:center;gap:8px;flex:1;min-width:105px;box-shadow:0 1px 3px rgba(0,0,0,.04);}
.sico{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;}
.snum{font-size:12.5px;font-weight:900;line-height:1.1;}
.slbl{font-size:10px;color:#64748b;margin-top:1px;}
/* ── Table ── */
.pr-table{width:100%;border-collapse:collapse;font-size:12.5px;}
.pr-table thead th{padding:9px 11px;font-size:11px;font-weight:800;white-space:nowrap;text-align:center;letter-spacing:.4px;text-transform:uppercase;border:1px solid rgba(255,255,255,.2);color:#fff;}
.pr-table td{padding:7px 11px;border:1px solid #e9f0f8;vertical-align:middle;white-space:nowrap;}
.pr-table tbody tr:nth-child(odd) td{background:#fafbfe;}
.pr-table tbody tr:nth-child(even) td{background:#fff;}
.pr-table tbody tr:hover td{background:#eff6ff!important;transition:background .1s;}
/* ── Tfoot ── */
.pr-table tfoot th{padding:9px 11px;font-size:12px;font-weight:800;white-space:nowrap;border:1px solid rgba(255,255,255,.15);}
/* ── Amount cells ── */
.amt{text-align:right!important;font-family:'Courier New',monospace;font-weight:700;color:#1e3a8a;}
.amt-zero{text-align:right!important;color:#c8d5e3;}
.gc-cell{font-family:'Courier New',monospace;font-size:12.5px;font-weight:900;letter-spacing:1px;}
/* ── Badges ── */
.rb{padding:3px 8px;border-radius:20px;font-size:10.5px;font-weight:700;white-space:nowrap;display:inline-block;}
.rb-open{background:#fef9c3;color:#854d0e;border:1px solid #fde68a;}
.rb-bld{background:#dbeafe;color:#1e40af;border:1px solid #93c5fd;}
.rb-done{background:#dcfce7;color:#166534;border:1px solid #86efac;}
.rb-paid{background:#dcfce7;color:#166534;border:1px solid #86efac;}
.rb-part{background:#ffedd5;color:#c2410c;border:1px solid #fdba74;}
.rb-cn{color:#a0aec0;font-size:11px;font-style:italic;}
@media print{.pr-hdr,.main-tab-nav,.search-panel,.no-print{display:none!important;}.tab-wrap{border:none!important;}}
</style>

<div class="main-content app-content">
<div class="container-fluid" style="padding-bottom:40px;">

<!-- ══ Page Header ══ -->
<div class="pr-hdr">
  <div>
    <h4><i class="ri-group-2-line"></i> Party-wise Trip Report</h4>
    <p>Select a consigner or agent to view their complete trip history</p>
  </div>
  <div class="d-flex gap-2 no-print">
    <?php if($searched && !empty($trips)): ?>
    <button class="btn btn-sm fw-bold text-white" style="background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.35);border-radius:9px;" onclick="window.print()"><i class="ri-printer-line me-1"></i>Print</button>
    <?php
      $exQs = http_build_query(['tab'=>$activeTab,'datePreset'=>$datePreset,'dateFrom'=>$dateFrom,'dateTo'=>$dateTo,'partyId'=>$selectedId,'search'=>1,'export'=>'excel']);
    ?>
    <a href="?<?= $exQs ?>" class="btn btn-sm fw-bold" style="background:#217346;color:#fff;border-radius:9px;border:none;" title="Download Excel">
      <i class="ri-file-excel-2-line me-1"></i>Excel
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- ══ TABS ══ -->
<div class="main-tab-nav no-print">
  <?php
    $cgrQs = http_build_query(['tab'=>'consigner','datePreset'=>$datePreset,'dateFrom'=>$datePreset==='custom'?$dateFrom:'','dateTo'=>$datePreset==='custom'?$dateTo:'']);
    $agtQs = http_build_query(['tab'=>'agent','datePreset'=>$datePreset,'dateFrom'=>$datePreset==='custom'?$dateFrom:'','dateTo'=>$datePreset==='custom'?$dateTo:'']);
  ?>
  <a href="?<?= $cgrQs ?>" class="mtnav <?= $activeTab==='consigner'?'act-cgr':'' ?>">
    <i class="ri-building-line"></i> Consigner Report
    <span class="mtbadge <?= $activeTab!=='consigner'?'mtbadge-gray':'' ?>"><?= count($consignerList) ?></span>
  </a>
  <a href="?<?= $agtQs ?>" class="mtnav <?= $activeTab==='agent'?'act-agt':'' ?>">
    <i class="ri-user-star-line"></i> Agent Report
    <span class="mtbadge <?= $activeTab!=='agent'?'mtbadge-gray':'' ?>"><?= count($agentList) ?></span>
  </a>
</div>

<div class="tab-wrap">

  <!-- ══ SEARCH PANEL ══ -->
  <div class="search-panel <?= $activeTab==='consigner' ? 'search-panel-cgr' : 'search-panel-agt' ?>">

    <div class="sp-title">
      <?php if($activeTab==='consigner'): ?>
      <i class="ri-building-line" style="color:#1d4ed8;"></i> Select Consigner
      <?php else: ?>
      <i class="ri-user-star-line" style="color:#d97706;"></i> Select Agent
      <?php endif; ?>
    </div>

    <form method="GET" action="PartyReport.php">
      <input type="hidden" name="tab" value="<?= $activeTab ?>">
      <input type="hidden" name="datePreset" value="<?= htmlspecialchars($datePreset) ?>">
      <?php if($datePreset==='custom'): ?>
      <input type="hidden" name="dateFrom" value="<?= htmlspecialchars($dateFrom) ?>">
      <input type="hidden" name="dateTo"   value="<?= htmlspecialchars($dateTo) ?>">
      <?php endif; ?>
      <input type="hidden" name="search" value="1">

      <div class="d-flex align-items-center gap-3 flex-wrap">
        <select name="partyId" class="party-select <?= $activeTab==='consigner'?'cgr-select':'agt-select' ?>" required>
          <option value="">
            <?= $activeTab==='consigner' ? '-- Select Consigner --' : '-- Select Agent --' ?>
          </option>
          <?php
            $list = $activeTab==='consigner' ? $consignerList : $agentList;
            foreach($list as $p):
          ?>
          <option value="<?= $p['PartyId'] ?>" <?= $selectedId==$p['PartyId']?'selected':'' ?>>
            <?= htmlspecialchars($p['PartyName']) ?>
          </option>
          <?php endforeach; ?>
        </select>

        <button type="submit" class="btn-search <?= $activeTab==='consigner'?'btn-search-cgr':'btn-search-agt' ?>">
          <i class="ri-search-line"></i> Search
        </button>

        <?php if($searched): ?>
        <a href="?tab=<?= $activeTab ?>" class="btn btn-sm btn-outline-secondary fw-bold" style="border-radius:9px;height:38px;padding:0 14px;display:inline-flex;align-items:center;gap:5px;">
          <i class="ri-close-line"></i> Clear
        </a>
        <?php endif; ?>
      </div>

      <!-- Date filter row -->
      <div class="date-row mt-3">
        <span class="df-lbl"><i class="ri-calendar-line me-1"></i>Period:</span>
        <?php
        $presets = ['all'=>'All Time','today'=>'Today','yesterday'=>'Yesterday','thisweek'=>'This Week','thismonth'=>'This Month'];
        foreach($presets as $key=>$label):
          $isAct = $datePreset===$key;
          $cls   = $isAct ? ('active '.($activeTab==='agent'?'agt-active':'')) : '';
        ?>
        <a href="?tab=<?= $activeTab ?>&datePreset=<?= $key ?><?= $selectedId?'&partyId='.$selectedId.'&search=1':'' ?>"
           class="df-btn <?= $cls ?>"><?= $label ?></a>
        <?php endforeach; ?>
        <span class="df-lbl" style="margin-left:4px;">Custom:</span>
        <input type="date" name="dateFrom" value="<?= htmlspecialchars($datePreset==='custom'?$dateFrom:'') ?>"
          style="border:2px solid #e2e8f0;border-radius:8px;padding:3px 8px;font-size:12px;font-weight:600;outline:none;height:32px;" onchange="this.form.elements['datePreset'].value='custom'">
        <span class="df-lbl">to</span>
        <input type="date" name="dateTo" value="<?= htmlspecialchars($datePreset==='custom'?$dateTo:'') ?>"
          style="border:2px solid #e2e8f0;border-radius:8px;padding:3px 8px;font-size:12px;font-weight:600;outline:none;height:32px;" onchange="this.form.elements['datePreset'].value='custom'">
        <?php if($datePreset==='custom'): ?>
        <span style="background:#e0f2fe;border:1px solid #bae6fd;border-radius:6px;padding:3px 10px;font-size:11px;font-weight:700;color:#0369a1;">
          <?= date('d-m-Y',strtotime($dateFrom)) ?> &rarr; <?= date('d-m-Y',strtotime($dateTo)) ?>
        </span>
        <?php endif; ?>
      </div>

    </form>
  </div>

  <!-- ══ RESULTS ══ -->
  <?php if(!$searched): ?>
  <!-- Empty — nothing searched yet -->
  <div class="empty-state">
    <i class="ri-search-eye-line"></i>
    <p style="font-size:16px;font-weight:700;color:#64748b;">
      <?= $activeTab==='consigner' ? 'Consigner' : 'Agent' ?> — Select a party and click Search
    </p>
    <p style="font-size:13px;color:#94a3b8;margin-top:6px;">Their complete trip history will appear here</p>
  </div>

  <?php elseif(empty($trips)): ?>
  <!-- Searched but no trips -->
  <div class="empty-state">
    <i class="ri-inbox-line"></i>
    <p style="font-size:15px;font-weight:700;color:#64748b;">
      "<?= htmlspecialchars($partyName) ?>" — No trips found
    </p>
    <p style="font-size:13px;color:#94a3b8;margin-top:6px;">Try adjusting the date range and search again</p>
  </div>

  <?php else: ?>
  <!-- ══ Results found ══ -->

  <!-- Result header -->
  <div class="result-hdr <?= $activeTab==='consigner'?'result-hdr-cgr':'result-hdr-agt' ?>">
    <div class="result-party-name" style="color:<?= $activeTab==='consigner'?'#1e3a8a':'#92400e' ?>;">
      <i class="<?= $activeTab==='consigner'?'ri-building-line':'ri-user-star-line' ?>"
         style="background:<?= $activeTab==='consigner'?'#1e3a8a':'#92400e' ?>;color:#fff;padding:6px;border-radius:8px;font-size:14px;"></i>
      <?= htmlspecialchars($partyName) ?>
    </div>
    <div class="result-meta">
      <span class="meta-pill" style="background:<?= $activeTab==='consigner'?'#dbeafe':'#fef9c3' ?>;color:<?= $activeTab==='consigner'?'#1e40af':'#92400e' ?>;">
        <?= $totals['cnt'] ?> Trips
      </span>
      <?php if($datePreset!=='all'): ?>
      <span class="meta-pill" style="background:#e0f2fe;color:#0369a1;">
        <?php
          if($datePreset==='today') echo 'Today';
          elseif($datePreset==='yesterday') echo 'Yesterday';
          elseif($datePreset==='thisweek') echo 'This Week';
          elseif($datePreset==='thismonth') echo date('F Y');
          elseif($datePreset==='custom') echo date('d-m-Y',strtotime($dateFrom)).' → '.date('d-m-Y',strtotime($dateTo));
        ?>
      </span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Summary pills -->
  <div class="srow">
    <div class="spill"><div class="sico" style="background:#dbeafe;"><i class="ri-route-line" style="color:#1d4ed8;"></i></div><div><div class="snum" style="color:#1d4ed8;"><?= $totals['cnt'] ?></div><div class="slbl">Trips</div></div></div>
    <div class="spill"><div class="sico" style="background:#dcfce7;"><i class="ri-money-dollar-circle-line" style="color:#15803d;"></i></div><div><div class="snum" style="font-size:11px;color:#15803d;"><?= r($totals['freight']) ?></div><div class="slbl">Freight</div></div></div>
    <div class="spill"><div class="sico" style="background:#e0f2fe;"><i class="ri-calculator-line" style="color:#0369a1;"></i></div><div><div class="snum" style="font-size:11px;color:#0369a1;"><?= r($totals['total']) ?></div><div class="slbl">Total Amt</div></div></div>
    <div class="spill"><div class="sico" style="background:#eff6ff;"><i class="ri-hand-coin-line" style="color:#1d4ed8;"></i></div><div><div class="snum" style="font-size:11px;color:#1d4ed8;"><?= r($totals['adv']) ?></div><div class="slbl">Advance</div></div></div>
    <div class="spill"><div class="sico" style="background:#fee2e2;"><i class="ri-percent-line" style="color:#dc2626;"></i></div><div><div class="snum" style="font-size:11px;color:#dc2626;"><?= r($totals['tds']) ?></div><div class="slbl">TDS</div></div></div>
    <div class="spill" style="border-left:4px solid #0c4a6e;"><div class="sico" style="background:#dbeafe;"><i class="ri-file-list-line" style="color:#0c4a6e;"></i></div><div><div class="snum" style="font-size:11px;color:#0c4a6e;"><?= r($totals['net']) ?></div><div class="slbl">Net Amount</div></div></div>
    <div class="spill" style="border-left:4px solid #d97706;"><div class="sico" style="background:#fef9c3;"><i class="ri-percent-line" style="color:#d97706;"></i></div><div><div class="snum" style="font-size:11px;color:#d97706;"><?= r($totals['comm']) ?></div><div class="slbl">Commission</div></div></div>
    <?php if($activeTab==='consigner' && $totals['bill']>0): ?>
    <div class="spill" style="border-left:4px solid #166534;"><div class="sico" style="background:#dcfce7;"><i class="ri-bill-line" style="color:#166534;"></i></div><div><div class="snum" style="font-size:11px;color:#166534;"><?= r($totals['bill']) ?></div><div class="slbl">Billed Amt</div></div></div>
    <?php endif; ?>
  </div>

  <!-- Trips Table -->
  <div style="overflow-x:auto;padding:0 0 20px;">
  <table class="pr-table">
    <thead>
      <?php $hdrBg = $activeTab==='consigner' ? '#1e3a8a' : '#92400e'; ?>
      <tr style="background:<?= $hdrBg ?>;">
        <th style="min-width:60px;">GC No.</th>
        <th style="min-width:85px;">Date</th>
        <th style="min-width:160px;">Route</th>
        <th style="min-width:90px;">Freight</th>
        <th style="min-width:75px;">Labour</th>
        <th style="min-width:75px;">Holding</th>
        <th style="min-width:75px;">Other</th>
        <th style="min-width:90px;background:#0a3d26;">Total Amt</th>
        <th style="min-width:85px;">Advance</th>
        <th style="min-width:72px;background:#7c2d12;">TDS</th>
        <th style="min-width:90px;background:#0c4a6e;">Net Amount</th>
        <th style="min-width:90px;background:#713f12;">Commission</th>
        <?php if($activeTab==='consigner'): ?>
        <th style="min-width:95px;background:#166534;">Bill No.</th>
        <th style="min-width:80px;background:#166534;">Bill Status</th>
        <?php endif; ?>
        <th style="min-width:75px;background:#374151;">Status</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach($trips as $t):
      $total = $t['FreightAmount']+$t['LabourCharge']+$t['HoldingCharge']+$t['OtherCharge'];
      $net   = $total - $t['AdvanceAmount'] - $t['TDS'];
      $gcNo  = str_pad($t['TripId'],4,'0',STR_PAD_LEFT);
      $rbg   = $t['TripStatus']==='Open' ? '#fffef0' : ($t['TripStatus']==='Billed' ? '#f0f4ff' : ($t['TripStatus']==='Completed' ? '#f0fdf5' : '#fff'));
    ?>
    <tr style="background:<?= $rbg ?>!important;">
      <td class="gc-cell" style="color:<?= $activeTab==='consigner'?'#1a237e':'#92400e' ?>;"><?= $gcNo ?></td>
      <td style="font-size:12px;font-weight:500;color:#374151;"><?= date('d M Y',strtotime($t['TripDate'])) ?></td>
      <td style="font-size:12px;font-weight:600;color:#374151;"><?= htmlspecialchars($t['FromLocation']??'') ?> &#8594; <?= htmlspecialchars($t['ToLocation']??'') ?></td>
      <td class="amt">&#8377;<?= number_format($t['FreightAmount'],0) ?></td>
      <?= amtTd($t['LabourCharge']) ?>
      <?= amtTd($t['HoldingCharge']) ?>
      <?= amtTd($t['OtherCharge']) ?>
      <td class="amt" style="background:#e8f5e9;color:#1b5e20;font-weight:900;">&#8377;<?= number_format($total,0) ?></td>
      <?= amtTd($t['AdvanceAmount']) ?>
      <td class="amt" style="color:#dc2626;"><?= $t['TDS']>0 ? '&#8377;'.number_format($t['TDS'],0) : '<span style="color:#c8d5e3;">&#8212;</span>' ?></td>
      <td class="amt" style="background:#e3f2fd;color:#0d47a1;font-weight:900;">&#8377;<?= number_format($net,0) ?></td>
      <td class="amt" style="color:#b45309;"><?= $t['CommissionAmount']>0 ? '&#8377;'.number_format($t['CommissionAmount'],0) : '<span style="color:#c8d5e3;">&#8212;</span>' ?></td>
      <?php if($activeTab==='consigner'): ?>
      <td style="font-size:12px;font-weight:700;color:#166534;text-align:center;">
        <?= !empty($t['BillNo']) ? htmlspecialchars($t['BillNo']) : '<span class="rb-cn">&#8212;</span>' ?>
      </td>
      <td style="text-align:center;"><?= bsBadge($t['BillStatus']) ?></td>
      <?php endif; ?>
      <td style="text-align:center;"><?= stBadge($t['TripStatus']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="background:<?= $hdrBg ?>;">
        <th colspan="3" style="color:#fff;text-align:right;">TOTAL &mdash; <?= $totals['cnt'] ?> Trips</th>
        <th style="text-align:right;color:#fff;font-family:'Courier New',monospace;font-weight:900;">&#8377;<?= number_format($totals['freight'],0) ?></th>
        <th style="text-align:right;color:rgba(255,255,255,.7);font-family:'Courier New',monospace;"><?= $totals['labour']>0  ? '&#8377;'.number_format($totals['labour'],0)  : '&#8212;' ?></th>
        <th style="text-align:right;color:rgba(255,255,255,.7);font-family:'Courier New',monospace;"><?= $totals['holding']>0 ? '&#8377;'.number_format($totals['holding'],0) : '&#8212;' ?></th>
        <th style="text-align:right;color:rgba(255,255,255,.7);font-family:'Courier New',monospace;"><?= $totals['other']>0   ? '&#8377;'.number_format($totals['other'],0)   : '&#8212;' ?></th>
        <th style="text-align:right;font-family:'Courier New',monospace;font-weight:900;font-size:13px;background:#0a3d26;color:#fff;">&#8377;<?= number_format($totals['total'],0) ?></th>
        <th style="text-align:right;color:rgba(255,255,255,.8);font-family:'Courier New',monospace;"><?= $totals['adv']>0 ? '&#8377;'.number_format($totals['adv'],0) : '&#8212;' ?></th>
        <th style="text-align:right;font-family:'Courier New',monospace;background:#7c2d12;color:#fecaca;"><?= $totals['tds']>0 ? '&#8377;'.number_format($totals['tds'],0) : '&#8212;' ?></th>
        <th style="text-align:right;font-family:'Courier New',monospace;font-weight:900;font-size:13px;background:#0c4a6e;color:#fff;">&#8377;<?= number_format($totals['net'],0) ?></th>
        <th style="text-align:right;font-family:'Courier New',monospace;color:#fde68a;"><?= $totals['comm']>0 ? '&#8377;'.number_format($totals['comm'],0) : '&#8212;' ?></th>
        <?php if($activeTab==='consigner'): ?>
        <th colspan="2" style="background:#166534;color:rgba(255,255,255,.7);text-align:center;font-size:11px;">
          <?= $totals['bill']>0 ? 'Billed: &#8377;'.number_format($totals['bill'],0) : '&#8212;' ?>
        </th>
        <?php endif; ?>
        <th></th>
      </tr>
    </tfoot>
  </table>
  </div>

  <!-- ══ FINANCIAL SUMMARY ══ -->
  <?php
    $unbilledAmt  = $totals['total'] - $totals['bill'];   // trips not yet billed
    $pendingAmt   = $totals['pending'];                    // billed but not received
    $toReceive    = $totals['bill'] - $totals['received']; // total outstanding
    $isConsigner  = $activeTab === 'consigner';
  ?>
  <div style="margin:0 20px 24px;border:2px solid <?= $isConsigner?'#1e3a8a':'#92400e' ?>;border-radius:14px;overflow:hidden;">
    <div style="background:<?= $isConsigner?'linear-gradient(135deg,#0c4a6e,#1e3a8a)':'linear-gradient(135deg,#78350f,#92400e)' ?>;padding:12px 20px;display:flex;align-items:center;gap:8px;">
      <i class="ri-scales-3-line" style="color:#fff;font-size:16px;"></i>
      <span style="color:#fff;font-size:14px;font-weight:900;letter-spacing:.3px;">Financial Summary</span>
      <span style="margin-left:auto;color:rgba(255,255,255,.65);font-size:12px;"><?= htmlspecialchars($partyName) ?></span>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;background:#fff;">

      <!-- LEFT — What we should RECEIVE -->
      <div style="padding:20px 24px;border-right:2px solid #e2e8f0;">
        <div style="font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.8px;margin-bottom:14px;display:flex;align-items:center;gap:6px;">
          <i class="ri-arrow-down-circle-fill" style="color:#16a34a;font-size:15px;"></i> To Receive (from <?= $isConsigner?'Consigner':'Agent' ?>)
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;">
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#f8fafc;border-radius:8px;border-left:3px solid #e2e8f0;">
            <span style="font-size:12.5px;font-weight:600;color:#374151;">Total Trip Amount</span>
            <span style="font-family:'Courier New',monospace;font-weight:800;font-size:13px;color:#374151;">&#8377;<?= number_format($totals['total'],0) ?></span>
          </div>
          <?php if($isConsigner): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#f8fafc;border-radius:8px;border-left:3px solid #e2e8f0;">
            <span style="font-size:12.5px;font-weight:600;color:#374151;">Total Billed</span>
            <span style="font-family:'Courier New',monospace;font-weight:800;font-size:13px;color:#1d4ed8;">&#8377;<?= number_format($totals['bill'],0) ?></span>
          </div>
          <?php if($unbilledAmt > 0): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#fffbeb;border-radius:8px;border-left:3px solid #f59e0b;">
            <span style="font-size:12.5px;font-weight:600;color:#92400e;">Not Yet Billed</span>
            <span style="font-family:'Courier New',monospace;font-weight:800;font-size:13px;color:#b45309;">&#8377;<?= number_format($unbilledAmt,0) ?></span>
          </div>
          <?php endif; ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#dcfce7;border-radius:8px;border-left:3px solid #16a34a;">
            <span style="font-size:12.5px;font-weight:600;color:#166534;">Already Received</span>
            <span style="font-family:'Courier New',monospace;font-weight:800;font-size:13px;color:#166534;">&#8377;<?= number_format($totals['received'],0) ?></span>
          </div>
          <?php endif; ?>
          <?php if($totals['comm'] > 0): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#fffbeb;border-radius:8px;border-left:3px solid #d97706;">
            <span style="font-size:12.5px;font-weight:600;color:#92400e;">Commission Earned</span>
            <span style="font-family:'Courier New',monospace;font-weight:800;font-size:13px;color:#d97706;">&#8377;<?= number_format($totals['comm'],0) ?></span>
          </div>
          <?php endif; ?>
          <!-- OUTSTANDING -->
          <div style="display:flex;justify-content:space-between;align-items:center;padding:11px 14px;background:<?= $toReceive>0?'#dcfce7':'#f0fdf4' ?>;border-radius:10px;border:2px solid #16a34a;margin-top:4px;">
            <span style="font-size:13px;font-weight:900;color:#14532d;">
              <i class="ri-arrow-down-circle-fill me-1"></i>
              <?= $isConsigner ? 'Pending to Receive' : 'Commission Pending' ?>
            </span>
            <span style="font-family:'Courier New',monospace;font-weight:900;font-size:16px;color:#14532d;">
              &#8377;<?= $isConsigner ? number_format($toReceive > 0 ? $toReceive : ($totals['bill'] > 0 ? 0 : $totals['net']),0) : number_format($totals['comm'],0) ?>
            </span>
          </div>
        </div>
      </div>

      <!-- RIGHT — Breakdown / Status -->
      <div style="padding:20px 24px;">
        <div style="font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.8px;margin-bottom:14px;display:flex;align-items:center;gap:6px;">
          <i class="ri-pie-chart-2-line" style="color:#6366f1;font-size:15px;"></i> Trip Status Breakdown
        </div>
        <?php
          $cntOpen  = count(array_filter($trips,fn($t)=>$t['TripStatus']==='Open'));
          $cntBld   = count(array_filter($trips,fn($t)=>$t['TripStatus']==='Billed'));
          $cntDone  = count(array_filter($trips,fn($t)=>$t['TripStatus']==='Completed'));
          $rows = [
            ['Open',      $cntOpen, '#fef9c3','#854d0e','#fde68a'],
            ['Billed',    $cntBld,  '#dbeafe','#1e40af','#93c5fd'],
            ['Completed', $cntDone, '#dcfce7','#166534','#86efac'],
          ];
          foreach($rows as [$lbl,$cnt,$bg,$tc,$bc]):
            if(!$cnt) continue;
            $pct = $totals['cnt'] > 0 ? round($cnt/$totals['cnt']*100) : 0;
        ?>
        <div style="margin-bottom:10px;">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
            <span style="font-size:12px;font-weight:700;color:<?= $tc ?>;"><?= $lbl ?></span>
            <span style="font-size:12px;font-weight:800;color:#374151;"><?= $cnt ?> trips (<?= $pct ?>%)</span>
          </div>
          <div style="background:#f1f5f9;border-radius:6px;height:8px;overflow:hidden;">
            <div style="width:<?= $pct ?>%;height:8px;background:<?= $tc ?>;border-radius:6px;transition:width .3s;"></div>
          </div>
        </div>
        <?php endforeach; ?>

        <?php if($isConsigner): ?>
        <div style="margin-top:16px;padding-top:14px;border-top:1px solid #e2e8f0;">
          <div style="font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px;">Bill Status</div>
          <?php
            $billRows = [
              ['Generated', count(array_filter($trips,fn($t)=>$t['BillStatus']==='Generated')), '#dbeafe','#1e40af'],
              ['Paid',       count(array_filter($trips,fn($t)=>$t['BillStatus']==='Paid')),      '#dcfce7','#166534'],
              ['Partial',    count(array_filter($trips,fn($t)=>$t['BillStatus']==='PartiallyPaid')), '#ffedd5','#c2410c'],
              ['No Bill',    count(array_filter($trips,fn($t)=>empty($t['BillNo']))),            '#f1f5f9','#64748b'],
            ];
            foreach($billRows as [$lbl,$cnt,$bg,$tc]):
              if(!$cnt) continue;
          ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 10px;background:<?= $bg ?>;border-radius:6px;margin-bottom:5px;">
            <span style="font-size:12px;font-weight:700;color:<?= $tc ?>;"><?= $lbl ?></span>
            <span style="font-size:12px;font-weight:800;color:<?= $tc ?>;"><?= $cnt ?> trips</span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
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
