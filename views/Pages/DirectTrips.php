<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once "../../businessLogics/Admin.php";
require_once "../../businessLogics/Trip.php";
require_once "../../config/database.php";
Admin::checkAuth();

$allTrips     = Trip::getDirectPaymentTrips();
$total        = count($allTrips);
$regular      = count(array_filter($allTrips, fn($t) => $t['TripType'] === 'Regular'));
$agent        = count(array_filter($allTrips, fn($t) => $t['TripType'] === 'Agent'));
$totalFreight = array_sum(array_column($allTrips, 'FreightAmount'));

require_once "../layout/header.php";
require_once "../layout/sidebar.php";
?>
<style>
.page-header-card{background:linear-gradient(135deg,#1a237e 0%,#1d4ed8 100%);border-radius:14px;padding:20px 24px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
.ph-title{font-size:20px;font-weight:800;color:#fff;margin:0;display:flex;align-items:center;gap:10px;}
.ph-sub{font-size:12px;color:rgba(255,255,255,0.65);margin-top:3px;}
.stats-bar{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;}
.stat-pill{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:10px 18px;display:flex;align-items:center;gap:10px;box-shadow:0 1px 4px rgba(0,0,0,.05);flex:1;min-width:130px;}
.sp-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.sp-val{font-size:20px;font-weight:800;color:#1a237e;line-height:1;}
.sp-lbl{font-size:11px;color:#64748b;margin-top:2px;}
.filter-card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px 20px;margin-bottom:16px;}
.action-btn-group{display:flex;gap:4px;}
.btn-icon{width:30px;height:30px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:7px;font-size:13px;}
.badge-regular{background:#e0e7ff;color:#1a237e;border:1px solid #c7d7fc;padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700;}
.badge-agent  {background:#dbeafe;color:#1d4ed8;border:1px solid #93c5fd;padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700;}
.s-open  {background:#dbeafe;color:#1d4ed8;border:1px solid #93c5fd;  padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;}
.s-billed{background:#fef9c3;color:#854d0e;border:1px solid #fde047;  padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;}
.s-closed{background:#dcfce7;color:#15803d;border:1px solid #86efac;  padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;}
.info-banner{background:linear-gradient(135deg,#eff6ff,#dbeafe);border:1.5px solid #93c5fd;border-radius:10px;padding:10px 16px;margin-bottom:16px;font-size:12.5px;color:#1e40af;display:flex;align-items:center;gap:8px;}
</style>

<div class="main-content app-content">
<div class="container-fluid">

<!-- ══ Page Header ══ -->
<div class="page-header-card">
    <div>
        <div class="ph-title"><i class="ri-arrow-right-circle-line"></i>Direct Payment Trips</div>
        <div class="ph-sub">Trips where the owner received freight directly — commission tracking only</div>
    </div>
    <div class="d-flex gap-2">
        <a href="RegularTripForm.php" class="btn btn-light fw-bold"
            style="border-radius:9px;height:38px;font-size:13px;color:#1a237e;">
            <i class="ri-add-line me-1"></i>New Regular
        </a>
        <a href="AgentTripForm.php" class="btn btn-warning fw-bold"
            style="border-radius:9px;height:38px;font-size:13px;">
            <i class="ri-add-line me-1"></i>New Agent
        </a>
    </div>
</div>

<!-- Info Banner -->
<div class="info-banner">
    <i class="ri-information-line" style="font-size:18px;flex-shrink:0;"></i>
    <span>
        <strong>Direct Pay Trips</strong> — Party ne owner ko directly freight pay kiya.
        Owner payment system se process nahi hoga — sirf <strong>commission track</strong> hogi.
    </span>
</div>

<!-- ══ Stats ══ -->
<div class="stats-bar">
    <div class="stat-pill">
        <div class="sp-icon" style="background:#e0e7ff;"><i class="ri-arrow-right-circle-line" style="color:#1a237e;"></i></div>
        <div><div class="sp-val"><?= $total ?></div><div class="sp-lbl">Total Direct</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-icon" style="background:#e0e7ff;"><i class="ri-file-list-line" style="color:#1a237e;"></i></div>
        <div><div class="sp-val" style="color:#1a237e;"><?= $regular ?></div><div class="sp-lbl">Regular</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-icon" style="background:#dbeafe;"><i class="ri-user-star-line" style="color:#1d4ed8;"></i></div>
        <div><div class="sp-val" style="color:#1d4ed8;"><?= $agent ?></div><div class="sp-lbl">Agent</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-icon" style="background:#f0fdf4;"><i class="ri-money-rupee-circle-line" style="color:#16a34a;"></i></div>
        <div><div class="sp-val" style="font-size:14px;color:#16a34a;">Rs.<?= number_format($totalFreight,0) ?></div><div class="sp-lbl">Total Freight</div></div>
    </div>
</div>

<!-- ══ Filter + Search ══ -->
<div class="filter-card">
    <div class="row g-3 align-items-end">
        <div class="col-md-2">
            <label class="form-label fw-semibold fs-12 mb-1">Trip Type</label>
            <select id="filterType" class="form-select form-select-sm">
                <option value="">All Types</option>
                <option value="Regular">Regular</option>
                <option value="Agent">Agent</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold fs-12 mb-1">Trip Status</label>
            <select id="filterStatus" class="form-select form-select-sm">
                <option value="">All Status</option>
                <option value="Open">Open</option>
                <option value="Billed">Billed</option>
                <option value="Closed">Closed</option>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-outline-secondary btn-sm w-100" onclick="clearFilters()">
                <i class="ri-refresh-line me-1"></i>Clear
            </button>
        </div>
        <div class="col-md-6 ms-auto">
            <label class="form-label fw-semibold fs-12 mb-1">Search</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0" style="border-radius:8px 0 0 8px;">
                    <i class="ri-search-line text-muted"></i>
                </span>
                <input type="text" id="customSearch" class="form-control border-start-0 ps-1"
                    placeholder="Vehicle, Party, Location..." style="box-shadow:none;">
                <span id="filterInfo" class="input-group-text bg-primary text-white fw-bold"
                    style="border-radius:0 8px 8px 0;font-size:11px;min-width:52px;justify-content:center;"></span>
            </div>
        </div>
    </div>
</div>

<!-- ══ Table ══ -->
<div class="card custom-card shadow-sm">
<div class="card-body p-0">
<div class="table-responsive">
<table id="tripTable" class="table table-hover align-middle mb-0 w-100">
    <thead style="background:#f0f4ff;">
        <tr>
            <th style="width:45px;">#</th>
            <th>Date</th>
            <th>Type</th>
            <th>Vehicle</th>
            <th>Party / Agent</th>
            <th>Route</th>
            <th>Freight</th>
            <th>Status</th>
            <th style="width:80px;">Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php $i=1; foreach ($allTrips as $r):
        $isAgent = $r['TripType'] === 'Agent';
        $editUrl = $isAgent ? "AgentTripForm.php?TripId={$r['TripId']}" : "RegularTripForm.php?TripId={$r['TripId']}";
        $st = $r['TripStatus'] ?? 'Open';
    ?>
    <tr>
        <td class="text-muted fw-medium fs-13"><?= $i++ ?></td>
        <td style="font-size:13px;white-space:nowrap;"><?= htmlspecialchars($r['TripDate'] ?? '') ?></td>
        <td>
            <?php if ($isAgent): ?>
            <span class="badge-agent">⭐ Agent</span>
            <?php else: ?>
            <span class="badge-regular">📋 Regular</span>
            <?php endif; ?>
        </td>
        <td>
            <div class="fw-bold" style="font-size:13px;"><?= htmlspecialchars($r['VehicleNumber'] ?? '—') ?></div>
            <?php if (!empty($r['VehicleName'])): ?>
            <div style="font-size:11px;color:#64748b;"><?= htmlspecialchars($r['VehicleName']) ?></div>
            <?php endif; ?>
        </td>
        <td style="font-size:12.5px;">
            <?php if ($isAgent): ?>
            <span class="badge bg-primary" style="font-size:11px;"><?= htmlspecialchars($r['AgentName'] ?? '—') ?></span>
            <?php else: ?>
            <div style="font-weight:600;"><?= htmlspecialchars($r['ConsignerName'] ?? '—') ?></div>
            <div style="font-size:11px;color:#64748b;">→ <?= htmlspecialchars($r['ConsigneeName'] ?? '—') ?></div>
            <?php endif; ?>
        </td>
        <td style="font-size:12px;">
            <?php if (!empty($r['FromLocation']) || !empty($r['ToLocation'])): ?>
            <span style="color:#1d4ed8;"><?= htmlspecialchars($r['FromLocation'] ?? '?') ?></span>
            <i class="ri-arrow-right-line" style="font-size:10px;color:#94a3b8;"></i>
            <span style="color:#dc2626;"><?= htmlspecialchars($r['ToLocation'] ?? '?') ?></span>
            <?php else: echo '<span class="text-muted">—</span>'; endif; ?>
        </td>
        <td style="font-size:13px;font-weight:700;color:#1a237e;">
            Rs.<?= number_format($r['FreightAmount'] ?? 0, 0) ?>
        </td>
        <td>
            <?php
            if ($st === 'Open')        echo '<span class="s-open"><i class="ri-time-line me-1"></i>Open</span>';
            elseif ($st === 'Billed')  echo '<span class="s-billed"><i class="ri-bill-line me-1"></i>Billed</span>';
            elseif ($st === 'Closed')  echo '<span class="s-closed"><i class="ri-checkbox-circle-line me-1"></i>Closed</span>';
            else echo '<span class="s-open">'.$st.'</span>';
            ?>
        </td>
        <td>
            <div class="action-btn-group">
                <a href="<?= $editUrl ?>" class="btn btn-sm btn-primary btn-icon" title="Edit">
                    <i class="ri-edit-line"></i>
                </a>
                <a href="GCNote_print.php?TripId=<?= $r['TripId'] ?>" target="_blank"
                    class="btn btn-sm btn-outline-dark btn-icon" title="Print GC">
                    <i class="ri-printer-line"></i>
                </a>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>
</div>

</div>
</div>

<script>
var tripDT;
$(document).ready(function(){
    tripDT = $('#tripTable').DataTable({
        scrollX:true, pageLength:25, dom:'rtip',
        columnDefs:[{orderable:false,targets:[0,8]}],
        language:{paginate:{previous:'‹',next:'›'},emptyTable:'No direct pay trips found.'},
        drawCallback:function(){
            var i=this.api().page.info();
            $('#filterInfo').text(i.recordsDisplay+'/'+i.recordsTotal);
        }
    });
    $('#filterType').on('change',function(){ tripDT.column(2).search(this.value||'').draw(); });
    $('#filterStatus').on('change',function(){ tripDT.column(7).search(this.value||'').draw(); });
    $('#customSearch').on('keyup input',function(){ tripDT.search($(this).val()).draw(); });
});

function clearFilters(){
    $('#filterType,#filterStatus').val('').trigger('change');
    $('#customSearch').val(''); tripDT.search('').draw();
}
window.addEventListener('offline',()=>SRV.toast.warning('Internet Disconnected!'));
window.addEventListener('online', ()=>SRV.toast.success('Back Online!'));
</script>
<?php require_once "../layout/footer.php"; ?>
