<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once "../../businessLogics/Admin.php";
require_once "../../businessLogics/TripRegister_logic.php";
Admin::checkAuth();

/* ═══ Date Filter ═══ */
$datePreset = $_GET['datePreset'] ?? 'all';
$activeTab  = $_GET['tab']        ?? 'reg';

list($dateFrom, $dateTo) = TripRegister::resolveDateRange(
    $datePreset,
    $_GET['dateFrom'] ?? '',
    $_GET['dateTo']   ?? ''
);

/* ═══ Data ═══ */
$regTrips = TripRegister::getRegularTrips($dateFrom, $dateTo);
$agtTrips = TripRegister::getAgentTrips($dateFrom, $dateTo);
$rTot     = TripRegister::totals($regTrips);
$aTot     = TripRegister::totals($agtTrips);

require_once "../layout/header.php";
require_once "../layout/sidebar.php";

/* ════ HELPER FUNCTIONS ════ */
function stBadge($s){
    $s = $s ?? '';
    if($s==='Open')      return '<span class="rb rb-open">Open</span>';
    if($s==='Billed')    return '<span class="rb rb-bld">Billed</span>';
    if($s==='Completed') return '<span class="rb rb-done">Completed</span>';
    return '<span class="rb rb-cn">&#8212;</span>';
}
function opBadge($s){
    $s = $s ?? '';
    if($s==='Paid')          return '<span class="rb rb-paid">Paid</span>';
    if($s==='PartiallyPaid') return '<span class="rb rb-part">Partial</span>';
    if($s==='Unpaid')        return '<span class="rb rb-unpd">Unpaid</span>';
    return '<span class="rb rb-cn">&#8212;</span>';
}
function bsBadge($s){
    $s = $s ?? '';
    if($s==='Paid')          return '<span class="rb rb-paid">Paid</span>';
    if($s==='PartiallyPaid') return '<span class="rb rb-part">Partial</span>';
    if($s==='Generated')     return '<span class="rb rb-bld">Generated</span>';
    return '<span class="rb rb-cn">&#8212;</span>';
}
function cmBadge($s, $r){
    $s = $s ?? ''; $r = $r ?? '';
    if($s==='Recovered') $sb = '<span class="rb rb-done">Recovered</span>';
    elseif($s==='Pending') $sb = '<span class="rb rb-open">Pending</span>';
    else $sb = '<span class="rb rb-cn">&#8212;</span>';
    if($r==='Party') $rb = '<span class="rb rb-reg">Party</span>';
    elseif($r==='Owner') $rb = '<span class="rb rb-dir">Owner</span>';
    else $rb = '';
    return $sb.' '.$rb;
}
function amtCell($v, $cls='amt'){
    $v = floatval($v);
    if($v <= 0) return '<td class="amt-zero">&#8212;</td>';
    return '<td class="'.$cls.'">&#8377;'.number_format($v,0).'</td>';
}
?>
<style>
/* ── Header ── */
.tr-hdr{background:linear-gradient(135deg,#0c4a6e,#0369a1 60%,#0284c7);border-radius:14px;padding:20px 26px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
.tr-hdr h4{color:#fff;font-weight:900;font-size:19px;margin:0;display:flex;align-items:center;gap:9px;}
.tr-hdr p{color:rgba(255,255,255,.65);font-size:12px;margin:3px 0 0;}
/* ── Quick Nav ── */
.quick-nav{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;align-items:center;}
.qn-sep{color:#e2e8f0;font-weight:300;font-size:18px;}
.qn-lbl{font-size:11px;font-weight:800;color:#94a3b8;white-space:nowrap;}
.qn-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 13px;border-radius:9px;font-size:12px;font-weight:700;text-decoration:none;border:2px solid transparent;transition:.15s;white-space:nowrap;}
.qn-btn:hover{transform:translateY(-1px);box-shadow:0 3px 8px rgba(0,0,0,.1);}
.qn-blue{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe;}
.qn-green{background:#f0fdf4;color:#15803d;border-color:#bbf7d0;}
.qn-amber{background:#fffbeb;color:#b45309;border-color:#fde68a;}
.qn-purple{background:#faf5ff;color:#7c3aed;border-color:#ddd6fe;}
.qn-slate{background:#f8fafc;color:#475569;border-color:#e2e8f0;}
/* ── Summary Pills ── */
.srow{display:flex;gap:9px;flex-wrap:wrap;}
.spill{background:#fff;border:1px solid #e2e8f0;border-radius:11px;padding:10px 14px;display:flex;align-items:center;gap:10px;flex:1;min-width:110px;box-shadow:0 1px 3px rgba(0,0,0,.04);}
.sico{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
.snum{font-size:14px;font-weight:900;line-height:1.1;}
.slbl{font-size:10.5px;color:#64748b;margin-top:1px;}
/* ── Date Filter ── */
.date-filter-bar{background:#fff;border:1px solid #bae6fd;border-radius:12px;padding:12px 16px;margin-bottom:14px;display:flex;align-items:center;gap:9px;flex-wrap:wrap;}
.df-btn{padding:5px 13px;border-radius:20px;font-size:12px;font-weight:700;border:2px solid #e2e8f0;background:#f8fafc;color:#64748b;cursor:pointer;transition:.15s;white-space:nowrap;text-decoration:none;display:inline-block;}
.df-btn:hover{border-color:#0284c7;color:#0284c7;background:#f0f9ff;}
.df-btn.active{border-color:#0284c7;background:#0284c7;color:#fff;}
.df-sep{width:1px;height:26px;background:#e2e8f0;flex-shrink:0;}
.df-label{font-size:12px;font-weight:700;color:#0284c7;white-space:nowrap;}
.df-apply{padding:5px 14px;border-radius:8px;background:#0284c7;color:#fff;border:none;font-size:12px;font-weight:700;cursor:pointer;}
.df-active-tag{background:#e0f2fe;border:1px solid #bae6fd;border-radius:6px;padding:3px 10px;font-size:11px;font-weight:700;color:#0284c7;}
/* ── Tabs ── */
.main-tab-nav{display:flex;gap:4px;margin-bottom:0;}
.mtnav{padding:11px 24px;font-size:13px;font-weight:800;cursor:pointer;border:1px solid #e2e8f0;border-bottom:none;border-radius:12px 12px 0 0;display:flex;align-items:center;gap:8px;background:#f1f5f9;color:#64748b;transition:.15s;}
.mtnav:hover{background:#e2e8f0;}
.mtnav.act-reg{background:#1e3a8a;color:#fff;border-color:#1e3a8a;}
.mtnav.act-agt{background:#92400e;color:#fff;border-color:#92400e;}
.mtbadge{display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:20px;border-radius:10px;font-size:11px;font-weight:800;padding:0 6px;background:rgba(255,255,255,.25);color:#fff;}
.mtbadge-gray{background:#e2e8f0;color:#64748b;}
.tab-wrap{border:1px solid #e2e8f0;border-radius:0 12px 12px 12px;overflow:hidden;background:#fff;}
/* ── Filters ── */
.filter-card{background:#f8fafc;border-bottom:1px solid #e2e8f0;padding:14px 18px;}
.filter-lbl{font-size:11.5px;font-weight:700;color:#374151;margin-bottom:4px;display:block;}
.filter-lbl i{color:#6b7280;margin-right:3px;}
select.tr-filter{font-size:12px;font-weight:600;height:32px;border-radius:8px;border:1.5px solid #d1d5db;padding:0 8px;background:#fff;color:#374151;width:100%;cursor:pointer;outline:none;}
select.tr-filter:focus{border-color:#0284c7;box-shadow:0 0 0 2px rgba(2,132,199,.15);}
/* ── Table ── */
.gh th{font-size:11px;font-weight:800;padding:10px 12px;white-space:nowrap;text-align:center;color:#fff!important;letter-spacing:.5px;border:1px solid rgba(255,255,255,.18)!important;text-transform:uppercase;}
.sh-row th{font-size:10.5px;font-weight:700;padding:6px 10px;text-align:center;white-space:nowrap;color:#1e3a5f!important;background:#eef5fc!important;border:1px solid #c5d8eb!important;border-top:none!important;}
.reg-table td,.agt-table td{padding:8px 11px;font-size:12.5px;vertical-align:middle;white-space:nowrap;border:1px solid #e0eaf4!important;}
.reg-table tbody tr:nth-child(odd) td,.agt-table tbody tr:nth-child(odd) td{background:#ffffff;}
.reg-table tbody tr:nth-child(even) td,.agt-table tbody tr:nth-child(even) td{background:#f7fafd;}
.tr-open td{background:#fffef0!important;}
.tr-billed td{background:#f0f4ff!important;}
.tr-completed td{background:#f0fdf5!important;}
.reg-table tbody tr:hover td,.agt-table tbody tr:hover td{background:#e6f2ff!important;transition:background .12s;}
.tfoot-row th{font-size:12px;font-weight:800;padding:10px 11px;white-space:nowrap;background:#0c4a6e!important;color:#fff!important;border:1px solid #0369a1!important;}
.amt{text-align:right!important;font-family:'Courier New',monospace;font-weight:700;font-size:12.5px;color:#1e3a8a;}
.amt-zero{text-align:right!important;font-size:13px;color:#c8d5e3;}
.net-total{text-align:right!important;font-family:'Courier New',monospace;font-weight:900;font-size:13.5px;color:#fff!important;padding:8px 11px!important;}
.gc-cell{font-family:'Courier New',monospace;font-size:13px;font-weight:900;letter-spacing:1.5px;padding:8px 10px!important;}
/* ── Badges ── */
.rb{padding:3px 9px;border-radius:20px;font-size:10.5px;font-weight:700;white-space:nowrap;display:inline-block;}
.rb-open{background:#fef9c3;color:#854d0e;border:1px solid #fde68a;}
.rb-bld{background:#dbeafe;color:#1e40af;border:1px solid #93c5fd;}
.rb-done{background:#dcfce7;color:#166534;border:1px solid #86efac;}
.rb-paid{background:#dcfce7;color:#166534;border:1px solid #86efac;}
.rb-part{background:#ffedd5;color:#c2410c;border:1px solid #fdba74;}
.rb-unpd{background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;}
.rb-dir{background:#f5f3ff;color:#6d28d9;border:1px solid #c4b5fd;}
.rb-reg{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;}
.rb-cn{color:#a0aec0;font-size:11px;font-style:italic;}
/* ── Action Buttons ── */
.act-group{display:flex;gap:4px;}
.ab{width:29px;height:29px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:7px;font-size:13px;text-decoration:none;border:1.5px solid transparent;transition:.14s;}
.ab:hover{transform:translateY(-2px);box-shadow:0 4px 10px rgba(0,0,0,.15);}
.ab-edit{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe;}
.ab-owner{background:#f5f3ff;color:#6d28d9;border-color:#c4b5fd;}
.ab-bill{background:#f0fdf4;color:#15803d;border-color:#86efac;}
.ab-comm{background:#fffbeb;color:#92400e;border-color:#fde68a;}
.ab-gc{background:#f0f9ff;color:#0369a1;border-color:#bae6fd;}
@media print{.tr-hdr,.quick-nav,.date-filter-bar,.filter-card,.act-group,.sidebar,.main-tab-nav,header,footer{display:none!important;}.tab-wrap{border:none!important;}}
</style>

<div class="main-content app-content">
<div class="container-fluid" style="padding-bottom:30px;">

<!-- ══ Page Header ══ -->
<div class="tr-hdr">
  <div>
    <h4><i class="ri-file-list-3-line"></i> Trip Register</h4>
    <p>Regular and Agent trips &#8212; complete ledger in one place</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <button class="btn btn-sm fw-bold text-white" style="background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.35);border-radius:9px;" onclick="window.print()"><i class="ri-printer-line me-1"></i>Print</button>
    <button class="btn btn-sm fw-bold" style="background:#16a34a;color:#fff;border:none;border-radius:9px;" onclick="exportCSV()"><i class="ri-file-excel-2-line me-1"></i>Export CSV</button>
    <a href="TripRegister.php" class="btn btn-sm btn-light fw-bold" title="Reset"><i class="ri-refresh-line"></i></a>
  </div>
</div>

<!-- ══ Quick Nav ══ -->
<div class="quick-nav">
  <span class="qn-lbl">QUICK GO &rarr;</span>
  <a href="RegularTrips.php"        class="qn-btn qn-blue"  ><i class="ri-truck-line"></i> Regular Trips</a>
  <a href="AgentTrips.php"          class="qn-btn qn-amber" ><i class="ri-user-star-line"></i> Agent Trips</a>
  <a href="DirectTrips.php"         class="qn-btn qn-purple"><i class="ri-arrow-right-circle-line"></i> Direct Trips</a>
  <span class="qn-sep">|</span>
  <a href="OwnerPayment_manage.php" class="qn-btn qn-green" ><i class="ri-wallet-3-line"></i> Owner Payment</a>
  <a href="CommissionTrack.php"     class="qn-btn qn-amber" ><i class="ri-percent-line"></i> Commission</a>
  <a href="RegularBill.php"         class="qn-btn qn-blue"  ><i class="ri-bill-line"></i> Regular Bills</a>
  <a href="AgentBill.php"           class="qn-btn qn-amber" ><i class="ri-bill-line"></i> Agent Bills</a>
  <span class="qn-sep">|</span>
  <a href="RegularTripForm.php"     class="qn-btn qn-slate" ><i class="ri-add-line"></i> New Regular</a>
  <a href="AgentTripForm.php"       class="qn-btn qn-slate" ><i class="ri-add-line"></i> New Agent</a>
</div>

<!-- ══ Date Filter ══ -->
<div class="date-filter-bar">
  <span class="df-label"><i class="ri-calendar-line me-1"></i>Date:</span>
  <div class="d-flex gap-2 flex-wrap">
    <?php
    $presets = ['all'=>'All','today'=>'Today','yesterday'=>'Yesterday','thisweek'=>'This Week','thismonth'=>'This Month'];
    foreach($presets as $key=>$label):
      $qs = http_build_query(array_merge($_GET, ['datePreset'=>$key,'dateFrom'=>'','dateTo'=>'']));
    ?>
    <a href="?<?= $qs ?>" class="df-btn <?= $datePreset===$key?'active':'' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
  <div class="df-sep"></div>
  <form method="GET" style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;">
    <?php foreach($_GET as $k=>$v): if(in_array($k,['datePreset','dateFrom','dateTo'])) continue; ?>
    <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
    <?php endforeach; ?>
    <input type="hidden" name="datePreset" value="custom">
    <span class="df-label">From</span>
    <input type="date" name="dateFrom" value="<?= htmlspecialchars($datePreset==='custom'?$dateFrom:'') ?>" style="border:2px solid #e2e8f0;border-radius:8px;padding:4px 9px;font-size:12px;font-weight:600;outline:none;height:32px;">
    <span class="df-label">To</span>
    <input type="date" name="dateTo" value="<?= htmlspecialchars($datePreset==='custom'?$dateTo:'') ?>" style="border:2px solid #e2e8f0;border-radius:8px;padding:4px 9px;font-size:12px;font-weight:600;outline:none;height:32px;">
    <button type="submit" class="df-apply"><i class="ri-search-line"></i> Go</button>
  </form>
  <?php if($datePreset!=='all'): ?>
  <span class="df-active-tag">
    <?php
      if($datePreset==='today')         echo 'Today: '.date('d-m-Y');
      elseif($datePreset==='yesterday') echo 'Yesterday: '.date('d-m-Y',strtotime('-1 day'));
      elseif($datePreset==='thisweek')  echo date('d-m-Y',strtotime('monday this week')).' &rarr; '.date('d-m-Y',strtotime('sunday this week'));
      elseif($datePreset==='thismonth') echo date('F Y');
      elseif($datePreset==='custom'&&$dateFrom&&$dateTo) echo date('d-m-Y',strtotime($dateFrom)).' &rarr; '.date('d-m-Y',strtotime($dateTo));
    ?>
  </span>
  <a href="TripRegister.php" class="df-btn" style="border-color:#dc2626;color:#dc2626;"><i class="ri-close-line"></i> Clear</a>
  <?php endif; ?>
</div>

<!-- ══ TABS NAV ══ -->
<div class="main-tab-nav">
  <button class="mtnav <?= $activeTab==='reg'?'act-reg':'' ?>" id="mtnav-reg" onclick="switchTab('reg')">
    <i class="ri-truck-line"></i> Regular Trips
    <span class="mtbadge <?= $activeTab!=='reg'?'mtbadge-gray':'' ?>" id="reg-badge"><?= $rTot['cnt'] ?></span>
  </button>
  <button class="mtnav <?= $activeTab==='agt'?'act-agt':'' ?>" id="mtnav-agt" onclick="switchTab('agt')">
    <i class="ri-user-star-line"></i> Agent Trips
    <span class="mtbadge <?= $activeTab!=='agt'?'mtbadge-gray':'' ?>" id="agt-badge"><?= $aTot['cnt'] ?></span>
  </button>
</div>

<!-- ══════════════════════════════
     TAB 1 — REGULAR TRIPS
══════════════════════════════ -->
<div id="tab-reg" class="tab-wrap" style="display:<?= $activeTab==='reg'?'block':'none' ?>;">

  <!-- Summary -->
  <div class="srow" style="padding:12px 14px;border-bottom:1px solid #e2e8f0;background:#f8fafc;margin:0;">
    <div class="spill"><div class="sico" style="background:#dbeafe;"><i class="ri-truck-line" style="color:#1d4ed8;"></i></div><div><div class="snum" style="color:#1d4ed8;"><?= $rTot['cnt'] ?></div><div class="slbl">Trips</div></div></div>
    <div class="spill"><div class="sico" style="background:#dbeafe;"><i class="ri-money-dollar-circle-line" style="color:#1d4ed8;"></i></div><div><div class="snum" style="font-size:12px;color:#1d4ed8;">&#8377;<?= number_format($rTot['freight'],0) ?></div><div class="slbl">Total Freight</div></div></div>
    <div class="spill"><div class="sico" style="background:#fef9c3;"><i class="ri-add-circle-line" style="color:#b45309;"></i></div><div><div class="snum" style="font-size:11px;color:#b45309;">+&#8377;<?= number_format($rTot['labour']+$rTot['holding']+$rTot['other'],0) ?></div><div class="slbl">Extra Charges</div></div></div>
    <div class="spill"><div class="sico" style="background:#fee2e2;"><i class="ri-percent-line" style="color:#dc2626;"></i></div><div><div class="snum" style="font-size:12px;color:#dc2626;">&#8377;<?= number_format($rTot['tds'],0) ?></div><div class="slbl">TDS</div></div></div>
    <div class="spill" style="border-left:4px solid #1d4ed8;"><div class="sico" style="background:#dbeafe;"><i class="ri-bill-line" style="color:#1d4ed8;"></i></div><div><div class="snum" style="font-size:12px;color:#1d4ed8;">&#8377;<?= number_format($rTot['billed'],0) ?></div><div class="slbl">Total Billed</div></div></div>
    <div class="spill" style="border-left:4px solid #15803d;"><div class="sico" style="background:#dcfce7;"><i class="ri-checkbox-circle-line" style="color:#15803d;"></i></div><div><div class="snum" style="font-size:12px;color:#15803d;">&#8377;<?= number_format($rTot['paid'],0) ?></div><div class="slbl">Bill Received</div></div></div>
    <div class="spill" style="border-left:4px solid #dc2626;"><div class="sico" style="background:#fee2e2;"><i class="ri-time-line" style="color:#dc2626;"></i></div><div><div class="snum" style="font-size:12px;color:#dc2626;">&#8377;<?= number_format(max(0,$rTot['billed']-$rTot['paid']),0) ?></div><div class="slbl">Bill Pending</div></div></div>
    <div class="spill" style="border-left:4px solid #d97706;"><div class="sico" style="background:#fef9c3;"><i class="ri-percent-line" style="color:#d97706;"></i></div><div><div class="snum" style="font-size:12px;color:#d97706;">&#8377;<?= number_format($rTot['comm'],0) ?></div><div class="slbl">Commission</div></div></div>
  </div>

  <!-- Filters -->
  <div class="filter-card">
    <div class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="filter-lbl"><i class="ri-building-line"></i> Consigner</label>
        <select id="scgr_reg" class="tr-filter" onchange="regFilter()">
          <option value="">-- All Consigners --</option>
          <?php $cgrs=array_unique(array_filter(array_column($regTrips,'ConsignerName'))); sort($cgrs); foreach($cgrs as $x) echo '<option value="'.htmlspecialchars($x).'">'.htmlspecialchars($x).'</option>'; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="filter-lbl"><i class="ri-truck-line"></i> Vehicle</label>
        <select id="sveh_reg" class="tr-filter" onchange="regFilter()">
          <option value="">-- All Vehicles --</option>
          <?php $vehs=array_unique(array_filter(array_column($regTrips,'VehicleNumber'))); sort($vehs); foreach($vehs as $x) echo '<option value="'.htmlspecialchars($x).'">'.htmlspecialchars($x).'</option>'; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="filter-lbl"><i class="ri-user-line"></i> Owner</label>
        <select id="sown_reg" class="tr-filter" onchange="regFilter()">
          <option value="">-- All Owners --</option>
          <?php $owns=array_unique(array_filter(array_column($regTrips,'OwnerName'))); sort($owns); foreach($owns as $x) echo '<option value="'.htmlspecialchars($x).'">'.htmlspecialchars($x).'</option>'; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="filter-lbl"><i class="ri-toggle-line"></i> Trip Status</label>
        <select id="st_reg" class="tr-filter" onchange="regFilter()">
          <option value="">-- All Status --</option>
          <option value="Open">Open</option>
          <option value="Billed">Billed</option>
          <option value="Completed">Completed</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="filter-lbl"><i class="ri-wallet-3-line"></i> Owner Pay</label>
        <select id="sop_reg" class="tr-filter" onchange="regFilter()">
          <option value="">-- All Owner Pay --</option>
          <option value="Unpaid">Unpaid</option>
          <option value="PartiallyPaid">Partially Paid</option>
          <option value="Paid">Paid</option>
        </select>
      </div>
      <div class="col-1 d-flex align-items-end">
        <button class="btn btn-outline-secondary btn-sm w-100" onclick="clearRegFilters()" style="height:32px;border-radius:8px;" title="Clear"><i class="ri-refresh-line"></i></button>
      </div>
      <div class="col ms-auto" style="max-width:260px;">
        <label class="filter-lbl"><i class="ri-search-line"></i> Search</label>
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-white border-end-0" style="border-radius:8px 0 0 8px;"><i class="ri-search-line text-muted"></i></span>
          <input type="text" id="sr_reg" class="form-control border-start-0" placeholder="GC No, Party, Route..." style="box-shadow:none;" oninput="regFilter()">
          <span id="fi_reg" class="input-group-text bg-primary text-white fw-bold" style="border-radius:0 8px 8px 0;font-size:11px;min-width:55px;justify-content:center;"></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Table -->
  <div class="table-responsive">
  <table id="dt_reg" class="table table-bordered reg-table mb-0">
    <thead>
      <tr class="gh">
        <th rowspan="2" style="background:#0c4a6e;min-width:60px;">GC No.</th>
        <th rowspan="2" style="background:#0c4a6e;min-width:88px;">Date</th>
        <th rowspan="2" style="background:#0c4a6e;min-width:120px;">Vehicle &amp; Owner</th>
        <th rowspan="2" style="background:#0c4a6e;min-width:160px;">Consigner / Consignee</th>
        <th rowspan="2" style="background:#0c4a6e;min-width:140px;">Route</th>
        <th colspan="4" style="background:#065f46;">Charges (&#8377;)</th>
        <th rowspan="2" style="background:#0f5132;min-width:92px;">Total Amt</th>
        <th rowspan="2" style="background:#1e3a8a;min-width:105px;">Advance</th>
        <th rowspan="2" style="background:#7c2d12;min-width:80px;">TDS</th>
        <th rowspan="2" style="background:#0c4a6e;min-width:92px;">Net Amount</th>
        <th colspan="4" style="background:#7c2d12;">Bill Details</th>
        <th rowspan="2" style="background:#065f46;min-width:92px;">Owner Payable</th>
        <th style="background:#4c1d95;">Owner Pay</th>
        <th colspan="2" style="background:#713f12;">Commission</th>
        <th rowspan="2" style="background:#374151;min-width:80px;">Status</th>
        <th rowspan="2" style="background:#374151;min-width:108px;">Actions</th>
      </tr>
      <tr class="sh-row">
        <th>Freight</th><th>Labour</th><th>Holding</th><th>Other</th>
        <th>Bill No.</th><th>Bill Date</th><th>Bill Amt</th><th>Bill Status</th>
        <th>Pay Status</th>
        <th>Comm Amt</th><th>Comm Status</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach($regTrips as $t):
      $total        = $t['FreightAmount']+$t['LabourCharge']+$t['HoldingCharge']+$t['OtherCharge'];
      $adv          = floatval($t['AdvanceAmount']??0);
      $tds          = floatval($t['TDS']??0);
      $net          = $total - $adv - $tds;
      $ownerPayable = $total - floatval($t['CommissionAmount']??0);
      $rc = ''; if($t['TripStatus']==='Open') $rc='tr-open'; elseif($t['TripStatus']==='Billed') $rc='tr-billed'; elseif($t['TripStatus']==='Completed') $rc='tr-completed';
      $gcNo    = str_pad($t['TripId'],4,'0',STR_PAD_LEFT);
      $ownerId = $t['VehicleOwnerId']??'';
    ?>
    <tr class="<?= $rc ?>">
      <td class="gc-cell" style="color:#1a237e;"><?= $gcNo ?></td>
      <td style="font-size:12px;color:#374151;font-weight:500;"><?= date('d M Y',strtotime($t['TripDate'])) ?></td>
      <td>
        <span style="background:#1e3a8a;color:#fff;font-size:10.5px;font-weight:700;padding:2px 9px;border-radius:5px;display:inline-block;"><?= htmlspecialchars($t['VehicleNumber']??'&#8212;') ?></span>
        <?php if(!empty($t['OwnerName'])): ?>
        <div style="font-size:10.5px;color:#6d28d9;font-weight:600;margin-top:3px;"><?= htmlspecialchars($t['OwnerName']) ?></div>
        <?php endif; ?>
      </td>
      <td style="max-width:158px;">
        <div style="font-size:12.5px;font-weight:700;color:#1e3a8a;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($t['ConsignerName']??'&#8212;') ?></div>
        <div style="font-size:11px;color:#64748b;margin-top:2px;overflow:hidden;text-overflow:ellipsis;">&#8594; <?= htmlspecialchars($t['ConsigneeName']??'&#8212;') ?></div>
      </td>
      <td style="font-size:12px;font-weight:600;"><?= htmlspecialchars($t['FromLocation']??'') ?> &#8594; <?= htmlspecialchars($t['ToLocation']??'') ?></td>
      <td class="amt">&#8377;<?= number_format($t['FreightAmount'],0) ?></td>
      <?= amtCell($t['LabourCharge']) ?><?= amtCell($t['HoldingCharge']) ?><?= amtCell($t['OtherCharge']) ?>
      <td class="net-total" style="background:#0f5132!important;">&#8377;<?= number_format($total,0) ?></td>
      <td style="text-align:right;font-family:'Courier New',monospace;font-weight:800;padding:7px 10px;background:#eff6ff;">
        <?php if($adv>0): ?>
        <div style="font-weight:900;font-size:13px;color:#1e3a8a;">&#8377;<?= number_format($adv,0) ?></div>
        <div style="font-size:9.5px;color:#4b5563;margin-top:2px;display:flex;gap:4px;justify-content:flex-end;">
          <?php if($t['OnlineAdvance']>0): ?><span style="background:#dbeafe;color:#1d4ed8;padding:1px 5px;border-radius:8px;">Online <?= number_format($t['OnlineAdvance'],0) ?></span><?php endif; ?>
          <?php if($t['CashAdvance']>0):  ?><span style="background:#dcfce7;color:#166534;padding:1px 5px;border-radius:8px;">Cash <?= number_format($t['CashAdvance'],0) ?></span><?php endif; ?>
        </div>
        <?php else: ?><span style="color:#c8d5e3;">&#8212;</span><?php endif; ?>
      </td>
      <td class="amt" style="color:#dc2626;"><?= $tds>0 ? '&#8377;'.number_format($tds,0) : '<span style="color:#c8d5e3;">&#8212;</span>' ?></td>
      <td class="net-total" style="background:#0c4a6e!important;">&#8377;<?= number_format($net,0) ?></td>
      <td style="font-size:12px;font-weight:700;color:#1d4ed8;"><?= $t['BillNo'] ? htmlspecialchars($t['BillNo']) : '<span class="rb-cn">&#8212;</span>' ?></td>
      <td style="font-size:12px;"><?= $t['BillDate'] ? date('d M Y',strtotime($t['BillDate'])) : '<span class="rb-cn">&#8212;</span>' ?></td>
      <?= amtCell($t['NetBillAmount']) ?>
      <td><?= bsBadge($t['BillStatus']) ?></td>
      <td style="text-align:right;font-family:'Courier New',monospace;font-weight:800;font-size:13px;color:#166534;background:#f0fdf4;">&#8377;<?= number_format($ownerPayable,0) ?></td>
      <td>
        <?= opBadge($t['OwnerPaymentStatus']) ?>
        <?php if($t['FreightPaymentToOwnerStatus']==='PaidDirectly'): ?><div style="margin-top:3px;"><span class="rb rb-dir" style="font-size:9.5px;">Direct</span></div><?php endif; ?>
      </td>
      <?= amtCell($t['CommissionAmount']) ?>
      <td><?= cmBadge($t['CommissionStatus'],$t['RecoveryFrom']) ?></td>
      <td><?= stBadge($t['TripStatus']) ?></td>
      <td>
        <div class="act-group">
          <a href="RegularTripForm.php?TripId=<?= $t['TripId'] ?>" class="ab ab-edit" title="Edit"><i class="ri-edit-line"></i></a>
          <a href="OwnerPayment_manage.php<?= $ownerId?'?ownerId='.$ownerId:'' ?>" class="ab ab-owner" title="Owner Payment"><i class="ri-wallet-3-line"></i></a>
          <?php if(!empty($t['BillId'])): ?>
          <a href="RegularBill.php" class="ab ab-bill" title="View Bill"><i class="ri-bill-line"></i></a>
          <?php else: ?>
          <a href="RegularBill_generate.php?TripId=<?= $t['TripId'] ?>" class="ab ab-bill" title="Generate Bill"><i class="ri-file-add-line"></i></a>
          <?php endif; ?>
          <a href="CommissionTrack.php" class="ab ab-comm" title="Commission"><i class="ri-percent-line"></i></a>
          <a href="GCNote_print.php?TripId=<?= $t['TripId'] ?>" target="_blank" class="ab ab-gc" title="Print GC"><i class="ri-printer-line"></i></a>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr class="tfoot-row">
        <?php
          $rTotal        = $rTot['freight']+$rTot['labour']+$rTot['holding']+$rTot['other'];
          $rAdv          = $rTot['advance'];
          $rNet          = $rTotal - $rAdv - $rTot['tds'];
          $rOwnerPayable = $rTotal - $rTot['comm'];
        ?>
        <th colspan="5" style="text-align:right;">TOTAL &#8212; <?= $rTot['cnt'] ?> Trips</th>
        <th></th><th></th><th></th><th></th>
        <th style="text-align:right;font-family:'Courier New',monospace;font-weight:900;background:#0a3d26!important;color:#fff!important;">&#8377;<?= number_format($rTotal,0) ?></th>
        <th style="text-align:right;font-family:'Courier New',monospace;font-weight:900;background:#1e3a8a!important;color:#fff!important;">&#8377;<?= number_format($rAdv,0) ?></th>
        <th style="text-align:right;font-family:'Courier New',monospace;font-weight:700;background:#7f1d1d!important;color:#fff!important;">&#8377;<?= number_format($rTot['tds'],0) ?></th>
        <th style="text-align:right;font-family:'Courier New',monospace;font-weight:900;background:#0c4a6e!important;color:#fff!important;">&#8377;<?= number_format($rNet,0) ?></th>
        <th></th><th></th><th></th><th></th>
        <th style="text-align:right;font-family:'Courier New',monospace;font-weight:900;background:#166534!important;color:#fff!important;">&#8377;<?= number_format($rOwnerPayable,0) ?></th>
        <th></th>
        <th style="text-align:right;font-family:'Courier New',monospace;font-weight:900;color:#fde68a;">&#8377;<?= number_format($rTot['comm'],0) ?></th>
        <th></th><th></th><th></th>
      </tr>
    </tfoot>
  </table>
  </div>
</div><!-- /tab-reg -->

<!-- ══════════════════════════════
     TAB 2 — AGENT TRIPS
══════════════════════════════ -->
<div id="tab-agt" class="tab-wrap" style="display:<?= $activeTab==='agt'?'block':'none' ?>;">

  <!-- Summary -->
  <div class="srow" style="padding:12px 14px;border-bottom:1px solid #e2e8f0;background:#fffbeb;margin:0;">
    <div class="spill"><div class="sico" style="background:#fef9c3;"><i class="ri-user-star-line" style="color:#b45309;"></i></div><div><div class="snum" style="color:#b45309;"><?= $aTot['cnt'] ?></div><div class="slbl">Agent Trips</div></div></div>
    <div class="spill"><div class="sico" style="background:#fef9c3;"><i class="ri-money-dollar-circle-line" style="color:#b45309;"></i></div><div><div class="snum" style="font-size:12px;color:#b45309;">&#8377;<?= number_format($aTot['freight'],0) ?></div><div class="slbl">Total Freight</div></div></div>
    <div class="spill"><div class="sico" style="background:#fef9c3;"><i class="ri-add-circle-line" style="color:#b45309;"></i></div><div><div class="snum" style="font-size:11px;color:#b45309;">+&#8377;<?= number_format($aTot['labour']+$aTot['holding']+$aTot['other'],0) ?></div><div class="slbl">Extra Charges</div></div></div>
    <div class="spill"><div class="sico" style="background:#fee2e2;"><i class="ri-percent-line" style="color:#dc2626;"></i></div><div><div class="snum" style="font-size:12px;color:#dc2626;">&#8377;<?= number_format($aTot['tds'],0) ?></div><div class="slbl">TDS</div></div></div>
    <div class="spill" style="border-left:4px solid #d97706;"><div class="sico" style="background:#fef9c3;"><i class="ri-percent-line" style="color:#d97706;"></i></div><div><div class="snum" style="font-size:12px;color:#d97706;">&#8377;<?= number_format($aTot['comm'],0) ?></div><div class="slbl">Commission</div></div></div>
    <div class="spill" style="border-left:4px solid #15803d;"><div class="sico" style="background:#dcfce7;"><i class="ri-checkbox-circle-line" style="color:#15803d;"></i></div><div><div class="snum" style="font-size:12px;color:#15803d;">&#8377;<?= number_format($aTot['paid'],0) ?></div><div class="slbl">Bill Received</div></div></div>
  </div>

  <!-- Filters -->
  <div class="filter-card">
    <div class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="filter-lbl"><i class="ri-user-star-line"></i> Agent</label>
        <select id="sagt_agt" class="tr-filter" onchange="agtFilter()">
          <option value="">-- All Agents --</option>
          <?php $agts=array_unique(array_filter(array_column($agtTrips,'AgentName'))); sort($agts); foreach($agts as $x) echo '<option value="'.htmlspecialchars($x).'">'.htmlspecialchars($x).'</option>'; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="filter-lbl"><i class="ri-truck-line"></i> Vehicle</label>
        <select id="sveh_agt" class="tr-filter" onchange="agtFilter()">
          <option value="">-- All Vehicles --</option>
          <?php $vehs2=array_unique(array_filter(array_column($agtTrips,'VehicleNumber'))); sort($vehs2); foreach($vehs2 as $x) echo '<option value="'.htmlspecialchars($x).'">'.htmlspecialchars($x).'</option>'; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="filter-lbl"><i class="ri-user-line"></i> Owner</label>
        <select id="sown_agt" class="tr-filter" onchange="agtFilter()">
          <option value="">-- All Owners --</option>
          <?php $owns2=array_unique(array_filter(array_column($agtTrips,'OwnerName'))); sort($owns2); foreach($owns2 as $x) echo '<option value="'.htmlspecialchars($x).'">'.htmlspecialchars($x).'</option>'; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="filter-lbl"><i class="ri-toggle-line"></i> Trip Status</label>
        <select id="st_agt" class="tr-filter" onchange="agtFilter()">
          <option value="">-- All Status --</option>
          <option value="Open">Open</option>
          <option value="Completed">Completed</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="filter-lbl"><i class="ri-wallet-3-line"></i> Owner Pay</label>
        <select id="sop_agt" class="tr-filter" onchange="agtFilter()">
          <option value="">-- All Owner Pay --</option>
          <option value="Unpaid">Unpaid</option>
          <option value="PartiallyPaid">Partially Paid</option>
          <option value="Paid">Paid</option>
        </select>
      </div>
      <div class="col-1 d-flex align-items-end">
        <button class="btn btn-outline-secondary btn-sm w-100" onclick="clearAgtFilters()" style="height:32px;border-radius:8px;" title="Clear"><i class="ri-refresh-line"></i></button>
      </div>
      <div class="col ms-auto" style="max-width:260px;">
        <label class="filter-lbl"><i class="ri-search-line"></i> Search</label>
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-white border-end-0" style="border-radius:8px 0 0 8px;"><i class="ri-search-line text-muted"></i></span>
          <input type="text" id="sr_agt" class="form-control border-start-0" placeholder="GC No, Agent, Route..." style="box-shadow:none;" oninput="agtFilter()">
          <span id="fi_agt" class="input-group-text bg-warning text-dark fw-bold" style="border-radius:0 8px 8px 0;font-size:11px;min-width:55px;justify-content:center;"></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Table -->
  <div class="table-responsive">
  <table id="dt_agt" class="table table-bordered agt-table mb-0">
    <thead>
      <tr class="gh">
        <th rowspan="2" style="background:#92400e;min-width:60px;">GC No.</th>
        <th rowspan="2" style="background:#92400e;min-width:88px;">Date</th>
        <th rowspan="2" style="background:#92400e;min-width:120px;">Vehicle &amp; Owner</th>
        <th rowspan="2" style="background:#92400e;min-width:130px;">Agent</th>
        <th rowspan="2" style="background:#92400e;min-width:140px;">Route</th>
        <th colspan="4" style="background:#065f46;">Charges (&#8377;)</th>
        <th rowspan="2" style="background:#0f5132;min-width:92px;">Total Amt</th>
        <th rowspan="2" style="background:#1e3a8a;min-width:105px;">Advance</th>
        <th rowspan="2" style="background:#7c2d12;min-width:80px;">TDS</th>
        <th rowspan="2" style="background:#0c4a6e;min-width:92px;">Net Amount</th>
        <th rowspan="2" style="background:#065f46;min-width:92px;">Owner Payable</th>
        <th style="background:#4c1d95;">Owner Pay</th>
        <th colspan="2" style="background:#713f12;">Commission</th>
        <th rowspan="2" style="background:#374151;min-width:80px;">Status</th>
        <th rowspan="2" style="background:#374151;min-width:90px;">Actions</th>
      </tr>
      <tr class="sh-row">
        <th>Freight</th><th>Labour</th><th>Holding</th><th>Other</th>
        <th>Pay Status</th>
        <th>Comm Amt</th><th>Comm Status</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach($agtTrips as $t):
      $total        = $t['FreightAmount']+$t['LabourCharge']+$t['HoldingCharge']+$t['OtherCharge'];
      $adv          = floatval($t['AdvanceAmount']??0);
      $tds          = floatval($t['TDS']??0);
      $net          = $total - $adv - $tds;
      $ownerPayable = $total - floatval($t['CommissionAmount']??0);
      $rc = ''; if($t['TripStatus']==='Open') $rc='tr-open'; elseif($t['TripStatus']==='Completed') $rc='tr-completed';
      $gcNo    = str_pad($t['TripId'],4,'0',STR_PAD_LEFT);
      $ownerId = $t['VehicleOwnerId']??'';
    ?>
    <tr class="<?= $rc ?>">
      <td class="gc-cell" style="color:#92400e;"><?= $gcNo ?></td>
      <td style="font-size:12px;color:#374151;font-weight:500;"><?= date('d M Y',strtotime($t['TripDate'])) ?></td>
      <td>
        <span style="background:#92400e;color:#fff;font-size:10.5px;font-weight:700;padding:2px 9px;border-radius:5px;display:inline-block;"><?= htmlspecialchars($t['VehicleNumber']??'&#8212;') ?></span>
        <?php if(!empty($t['OwnerName'])): ?>
        <div style="font-size:10.5px;color:#6d28d9;font-weight:600;margin-top:3px;"><?= htmlspecialchars($t['OwnerName']) ?></div>
        <?php endif; ?>
      </td>
      <td style="font-size:12.5px;font-weight:600;color:#374151;max-width:125px;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($t['AgentName']??'&#8212;') ?></td>
      <td style="font-size:12px;font-weight:600;"><?= htmlspecialchars($t['FromLocation']??'') ?> &#8594; <?= htmlspecialchars($t['ToLocation']??'') ?></td>
      <td class="amt">&#8377;<?= number_format($t['FreightAmount'],0) ?></td>
      <?= amtCell($t['LabourCharge']) ?><?= amtCell($t['HoldingCharge']) ?><?= amtCell($t['OtherCharge']) ?>
      <td class="net-total" style="background:#0f5132!important;">&#8377;<?= number_format($total,0) ?></td>
      <td style="text-align:right;font-family:'Courier New',monospace;font-weight:800;padding:7px 10px;background:#eff6ff;">
        <?php if($adv>0): ?>
        <div style="font-weight:900;font-size:13px;color:#1e3a8a;">&#8377;<?= number_format($adv,0) ?></div>
        <div style="font-size:9.5px;color:#4b5563;margin-top:2px;display:flex;gap:4px;justify-content:flex-end;">
          <?php if($t['OnlineAdvance']>0): ?><span style="background:#dbeafe;color:#1d4ed8;padding:1px 5px;border-radius:8px;">Online <?= number_format($t['OnlineAdvance'],0) ?></span><?php endif; ?>
          <?php if($t['CashAdvance']>0):  ?><span style="background:#dcfce7;color:#166534;padding:1px 5px;border-radius:8px;">Cash <?= number_format($t['CashAdvance'],0) ?></span><?php endif; ?>
        </div>
        <?php else: ?><span style="color:#c8d5e3;">&#8212;</span><?php endif; ?>
      </td>
      <td class="amt" style="color:#dc2626;"><?= $tds>0 ? '&#8377;'.number_format($tds,0) : '<span style="color:#c8d5e3;">&#8212;</span>' ?></td>
      <td class="net-total" style="background:#0c4a6e!important;">&#8377;<?= number_format($net,0) ?></td>
      <td style="text-align:right;font-family:'Courier New',monospace;font-weight:800;font-size:13px;color:#166534;background:#f0fdf4;">&#8377;<?= number_format($ownerPayable,0) ?></td>
      <td>
        <?= opBadge($t['OwnerPaymentStatus']) ?>
        <?php if($t['FreightPaymentToOwnerStatus']==='PaidDirectly'): ?><div style="margin-top:3px;"><span class="rb rb-dir" style="font-size:9.5px;">Direct</span></div><?php endif; ?>
      </td>
      <?= amtCell($t['CommissionAmount']) ?>
      <td><?= cmBadge($t['CommissionStatus'],$t['RecoveryFrom']) ?></td>
      <td><?= stBadge($t['TripStatus']) ?></td>
      <td>
        <div class="act-group">
          <a href="AgentTripForm.php?TripId=<?= $t['TripId'] ?>" class="ab ab-edit" title="Edit"><i class="ri-edit-line"></i></a>
          <a href="OwnerPayment_manage.php<?= $ownerId?'?ownerId='.$ownerId:'' ?>" class="ab ab-owner" title="Owner Payment"><i class="ri-wallet-3-line"></i></a>
          <a href="CommissionTrack.php" class="ab ab-comm" title="Commission"><i class="ri-percent-line"></i></a>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr class="tfoot-row">
        <?php
          $aTotal        = $aTot['freight']+$aTot['labour']+$aTot['holding']+$aTot['other'];
          $aAdv          = $aTot['advance'];
          $aNet          = $aTotal - $aAdv - $aTot['tds'];
          $aOwnerPayable = $aTotal - $aTot['comm'];
        ?>
        <th colspan="5" style="text-align:right;">TOTAL &#8212; <?= $aTot['cnt'] ?> Trips</th>
        <th></th><th></th><th></th><th></th>
        <th style="text-align:right;font-family:'Courier New',monospace;font-weight:900;background:#0a3d26!important;color:#fff!important;">&#8377;<?= number_format($aTotal,0) ?></th>
        <th style="text-align:right;font-family:'Courier New',monospace;font-weight:900;background:#1e3a8a!important;color:#fff!important;">&#8377;<?= number_format($aAdv,0) ?></th>
        <th style="text-align:right;font-family:'Courier New',monospace;font-weight:700;background:#7f1d1d!important;color:#fff!important;">&#8377;<?= number_format($aTot['tds'],0) ?></th>
        <th style="text-align:right;font-family:'Courier New',monospace;font-weight:900;background:#0c4a6e!important;color:#fff!important;">&#8377;<?= number_format($aNet,0) ?></th>
        <th style="text-align:right;font-family:'Courier New',monospace;font-weight:900;background:#166534!important;color:#fff!important;">&#8377;<?= number_format($aOwnerPayable,0) ?></th>
        <th></th>
        <th style="text-align:right;font-family:'Courier New',monospace;font-weight:900;color:#fde68a;">&#8377;<?= number_format($aTot['comm'],0) ?></th>
        <th></th><th></th><th></th>
      </tr>
    </tfoot>
  </table>
  </div>
</div><!-- /tab-agt -->

</div></div>

<script>
var dtReg, dtAgt;

$(document).ready(function(){

  /* DataTables — $.extend only, zero spread operators */
  var base = {
    scrollX:    true,
    pageLength: 50,
    dom:        'rtip',
    order:      [[1, 'desc']],
    language:   { paginate: { previous: '&lsaquo;', next: '&rsaquo;' }, emptyTable: 'No trips found.' }
  };

  dtReg = $('#dt_reg').DataTable($.extend({}, base, {
    columnDefs: [{ orderable: false, targets: [0, 22] }],
    drawCallback: function(){
      var i = this.api().page.info();
      $('#fi_reg').text(i.recordsDisplay + '/' + i.recordsTotal);
    }
  }));

  dtAgt = $('#dt_agt').DataTable($.extend({}, base, {
    columnDefs: [{ orderable: false, targets: [0, 18] }],
    drawCallback: function(){
      var i = this.api().page.info();
      $('#fi_agt').text(i.recordsDisplay + '/' + i.recordsTotal);
    }
  }));

  /* Custom search — Vehicle + Owner both in col 2 */
  $.fn.dataTable.ext.search.push(function(settings, data){
    var id = settings.nTable.id;
    if(id === 'dt_reg'){
      var veh = document.getElementById('sveh_reg').value;
      var own = document.getElementById('sown_reg').value;
      var c2  = data[2] || '';
      if(veh && c2.indexOf(veh) < 0) return false;
      if(own && c2.indexOf(own) < 0) return false;
    }
    if(id === 'dt_agt'){
      var veh = document.getElementById('sveh_agt').value;
      var own = document.getElementById('sown_agt').value;
      var c2  = data[2] || '';
      if(veh && c2.indexOf(veh) < 0) return false;
      if(own && c2.indexOf(own) < 0) return false;
    }
    return true;
  });

  /* Show initial counts */
  dtReg.draw(false);
  dtAgt.draw(false);
});

/* ── Regular filter ── */
function regFilter(){
  if(!dtReg) return;
  dtReg.column(3).search(document.getElementById('scgr_reg').value, false, false);
  dtReg.column(21).search(document.getElementById('st_reg').value, false, false);
  dtReg.column(18).search(document.getElementById('sop_reg').value, false, false);
  dtReg.search(document.getElementById('sr_reg').value);
  dtReg.draw();
}

/* ── Agent filter ── */
function agtFilter(){
  if(!dtAgt) return;
  dtAgt.column(3).search(document.getElementById('sagt_agt').value, false, false);
  dtAgt.column(17).search(document.getElementById('st_agt').value, false, false);
  dtAgt.column(14).search(document.getElementById('sop_agt').value, false, false);
  dtAgt.search(document.getElementById('sr_agt').value);
  dtAgt.draw();
}

/* ── Clear filters ── */
function clearRegFilters(){
  ['scgr_reg','sveh_reg','sown_reg','st_reg','sop_reg'].forEach(function(id){
    document.getElementById(id).value = '';
  });
  document.getElementById('sr_reg').value = '';
  if(dtReg) dtReg.search('').columns().search('').draw();
}
function clearAgtFilters(){
  ['sagt_agt','sveh_agt','sown_agt','st_agt','sop_agt'].forEach(function(id){
    document.getElementById(id).value = '';
  });
  document.getElementById('sr_agt').value = '';
  if(dtAgt) dtAgt.search('').columns().search('').draw();
}

/* ── Tab switch ── */
function switchTab(name){
  document.getElementById('tab-reg').style.display = (name === 'reg') ? 'block' : 'none';
  document.getElementById('tab-agt').style.display = (name === 'agt') ? 'block' : 'none';
  document.getElementById('mtnav-reg').className   = 'mtnav' + (name === 'reg' ? ' act-reg' : '');
  document.getElementById('mtnav-agt').className   = 'mtnav' + (name === 'agt' ? ' act-agt' : '');
  document.getElementById('reg-badge').className   = (name === 'reg') ? 'mtbadge' : 'mtbadge mtbadge-gray';
  document.getElementById('agt-badge').className   = (name === 'agt') ? 'mtbadge' : 'mtbadge mtbadge-gray';
  if(name === 'reg' && dtReg) dtReg.columns.adjust();
  if(name === 'agt' && dtAgt) dtAgt.columns.adjust();
  var url = new URL(window.location.href);
  url.searchParams.set('tab', name);
  history.replaceState(null, '', url.toString());
}

/* ── Export CSV ── */
function exportCSV(){
  var isReg = document.getElementById('tab-reg').style.display !== 'none';
  var sel   = isReg ? '#dt_reg' : '#dt_agt';
  var rows  = [];
  var hdr   = [];
  $(sel + ' thead tr:last th').each(function(){ hdr.push($(this).text().trim()); });
  rows.push(hdr);
  $(sel + ' tbody tr:visible').each(function(){
    var r = [], tds = $(this).find('td'), last = tds.length - 1;
    tds.each(function(i){ if(i < last) r.push($(this).text().trim().replace(/\s+/g,' ')); });
    rows.push(r);
  });
  var foot = [];
  $(sel + ' tfoot tr th').each(function(){ foot.push($(this).text().trim()); });
  rows.push(foot);
  var csv = rows.map(function(r){
    return r.map(function(c){ return '"' + c.replace(/"/g, '""') + '"'; }).join(',');
  }).join('\n');
  var blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
  var a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'TripRegister_' + (isReg ? 'Regular' : 'Agent') + '_<?= date('Ymd') ?>.csv';
  a.click();
}

window.addEventListener('offline', function(){ if(typeof SRV!=='undefined') SRV.toast.warning('Internet Disconnected!'); });
window.addEventListener('online',  function(){ if(typeof SRV!=='undefined') SRV.toast.success('Back Online!'); });
</script>
<?php require_once "../layout/footer.php"; ?>
