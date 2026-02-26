<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once "../../businessLogics/Admin.php";
require_once "../../businessLogics/Vehicle.php";
require_once "../../businessLogics/VehicleOwner.php";
require_once "../../config/database.php";
Admin::checkAuth();

/* ══ AJAX — Save / Update ══ */
if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $isEdit = !empty($_POST['VehicleId']);
    $errors = [];
    if (empty(trim($_POST['VehicleNumber'] ?? ''))) $errors[] = 'Vehicle Number is required.';
    if (empty($_POST['VehicleOwnerId']))             $errors[] = 'Vehicle Owner is required.';
    if ($errors) { echo json_encode(['status'=>'error','message'=>implode('<br>',$errors)]); exit(); }

    $res = $isEdit ? Vehicle::update($_POST['VehicleId'], $_POST) : Vehicle::insert($_POST);
    $row = null;
    if ($res) {
        $id = $isEdit ? $_POST['VehicleId'] : $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT v.*, o.OwnerName, o.MobileNo AS OwnerMobile
            FROM VehicleMaster v
            LEFT JOIN VehicleOwnerMaster o ON v.VehicleOwnerId = o.VehicleOwnerId
            WHERE v.VehicleId = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    echo json_encode(['status'=>$res?'success':'error','row'=>$row]);
    exit();
}

/* ══ AJAX — Toggle Status ══ */
if (isset($_POST['toggle'])) {
    header('Content-Type: application/json');
    $ns = ($_POST['status']==='Yes') ? 'No' : 'Yes';
    $ok = Vehicle::changeStatus($_POST['id'], $ns);
    echo json_encode(['status'=>$ok?'success':'error','newStatus'=>$ns]);
    exit();
}

/* ══ AJAX — Detail (owner info + last trips) ══ */
if (isset($_POST['get_detail'])) {
    header('Content-Type: application/json');
    $vid = intval($_POST['vehicle_id']);

    // Owner full info
    $ownerStmt = $pdo->prepare("
        SELECT vom.*
        FROM VehicleMaster v
        JOIN VehicleOwnerMaster vom ON v.VehicleOwnerId = vom.VehicleOwnerId
        WHERE v.VehicleId = ?");
    $ownerStmt->execute([$vid]);
    $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Last 8 trips
    $tripStmt = $pdo->prepare("
        SELECT t.TripId, t.TripDate, t.TripType, t.FromLocation, t.ToLocation,
               t.FreightAmount, t.TripStatus, t.FreightPaymentToOwnerStatus, t.OwnerPaymentStatus,
               p1.PartyName AS ConsignerName, p2.PartyName AS ConsigneeName
        FROM TripMaster t
        LEFT JOIN PartyMaster p1 ON t.ConsignerId = p1.PartyId
        LEFT JOIN PartyMaster p2 ON t.ConsigneeId = p2.PartyId
        WHERE t.VehicleId = ?
        ORDER BY t.TripDate DESC, t.TripId DESC LIMIT 8");
    $tripStmt->execute([$vid]);
    $trips = $tripStmt->fetchAll(PDO::FETCH_ASSOC);

    // Trip stats
    $statStmt = $pdo->prepare("
        SELECT COUNT(*) AS TotalTrips,
               COALESCE(SUM(FreightAmount),0) AS TotalFreight,
               SUM(CASE WHEN TripStatus='Open'      THEN 1 ELSE 0 END) AS OpenTrips,
               SUM(CASE WHEN TripStatus='Completed' THEN 1 ELSE 0 END) AS CompletedTrips,
               SUM(CASE WHEN FreightPaymentToOwnerStatus='PaidDirectly' THEN 1 ELSE 0 END) AS DirectTrips
        FROM TripMaster WHERE VehicleId = ?");
    $statStmt->execute([$vid]);
    $stats = $statStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    echo json_encode(['owner'=>$owner,'trips'=>$trips,'stats'=>$stats]);
    exit();
}

/* ══ AJAX — Filter by Owner ══ */
if (isset($_GET['filterOwner'])) {
    header('Content-Type: application/json');
    $ownerId = intval($_GET['ownerId'] ?? 0);
    if ($ownerId > 0) {
        $stmt = $pdo->prepare("SELECT v.*, o.OwnerName, o.MobileNo AS OwnerMobile
            FROM VehicleMaster v
            LEFT JOIN VehicleOwnerMaster o ON v.VehicleOwnerId = o.VehicleOwnerId
            WHERE v.VehicleOwnerId = ? ORDER BY v.VehicleId DESC");
        $stmt->execute([$ownerId]);
    } else {
        $stmt = $pdo->query("SELECT v.*, o.OwnerName, o.MobileNo AS OwnerMobile
            FROM VehicleMaster v
            LEFT JOIN VehicleOwnerMaster o ON v.VehicleOwnerId = o.VehicleOwnerId
            ORDER BY v.VehicleId DESC");
    }
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit();
}

$vehicles     = Vehicle::getAll();
$owners       = VehicleOwner::getAll();
$activeOwners = array_filter($owners, fn($o) => $o['IsActive']==='Yes');

$total      = count($vehicles);
$active     = count(array_filter($vehicles, fn($v) => $v['IsActive']==='Yes'));
$inactive   = $total - $active;
$typeCounts = array_count_values(array_column($vehicles,'VehicleType'));

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
.filter-card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px 20px;margin-bottom:16px;}
.action-btn-group{display:flex;gap:4px;}
.btn-icon{width:30px;height:30px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:7px;font-size:14px;}
.req{color:#ef4444;margin-left:2px;}
.form-section-head{font-size:11px;font-weight:700;color:#1a237e;text-transform:uppercase;letter-spacing:1px;border-left:3px solid #1a237e;padding-left:8px;margin:6px 0 14px;}
/* Detail tabs */
.detail-tab-btn{border:1.5px solid #e2e8f0;border-radius:8px;padding:6px 14px;font-size:12px;font-weight:600;cursor:pointer;background:#f8fafc;color:#64748b;transition:.15s;}
.detail-tab-btn.active{background:#1a237e;color:#fff;border-color:#1a237e;}
.detail-tab-content{display:none;}
.detail-tab-content.active{display:block;}
.detail-row{display:flex;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:13px;}
.detail-row:last-child{border-bottom:none;}
.detail-label{width:120px;flex-shrink:0;color:#64748b;font-weight:600;font-size:11.5px;text-transform:uppercase;}
.detail-value{color:#1e293b;font-weight:500;flex:1;}
.trip-row{padding:9px 12px;background:#f8fafc;border-radius:8px;margin-bottom:6px;border-left:3px solid #e2e8f0;}
.trip-row.open{border-left-color:#1d4ed8;}
.trip-row.completed{border-left-color:#16a34a;}
.pay-mini-card{border-radius:10px;padding:10px;text-align:center;flex:1;}
.pay-mini-val{font-size:16px;font-weight:800;line-height:1;}
.pay-mini-lbl{font-size:10px;color:#64748b;margin-top:3px;}
</style>

<div class="main-content app-content">
<div class="container-fluid">

<!-- ══ Page Header ══ -->
<div class="page-header-card">
    <div>
        <div class="ph-title"><i class="ri-truck-line"></i> Vehicle Master</div>
        <div class="ph-sub">Manage all vehicles, types, RC details and owner assignments</div>
    </div>
    <button class="btn btn-warning fw-bold px-4" onclick="openAddModal()" style="border-radius:9px;height:38px;font-size:13px;">
        <i class="ri-add-circle-line me-1"></i> Add New Vehicle
    </button>
</div>

<!-- ══ Stats ══ -->
<div class="stats-bar">
    <div class="stat-pill"><div class="sp-icon" style="background:#e0e7ff;"><i class="ri-truck-line" style="color:#1a237e;"></i></div><div><div class="sp-val"><?=$total?></div><div class="sp-lbl">Total</div></div></div>
    <div class="stat-pill"><div class="sp-icon" style="background:#dcfce7;"><i class="ri-checkbox-circle-line" style="color:#16a34a;"></i></div><div><div class="sp-val"><?=$active?></div><div class="sp-lbl">Active</div></div></div>
    <div class="stat-pill"><div class="sp-icon" style="background:#fee2e2;"><i class="ri-close-circle-line" style="color:#dc2626;"></i></div><div><div class="sp-val"><?=$inactive?></div><div class="sp-lbl">Inactive</div></div></div>
    <div class="stat-pill"><div class="sp-icon" style="background:#dbeafe;"><i class="ri-truck-line" style="color:#1d4ed8;"></i></div><div><div class="sp-val"><?=$typeCounts['Truck']??0?></div><div class="sp-lbl">Trucks</div></div></div>
    <div class="stat-pill"><div class="sp-icon" style="background:#fef9c3;"><i class="ri-car-line" style="color:#ca8a04;"></i></div><div><div class="sp-val"><?=$typeCounts['Trailer']??0?></div><div class="sp-lbl">Trailers</div></div></div>
    <div class="stat-pill"><div class="sp-icon" style="background:#f0fdf4;"><i class="ri-car-line" style="color:#16a34a;"></i></div><div><div class="sp-val"><?=($typeCounts['Tempo']??0)+($typeCounts['Container']??0)+($typeCounts['Other']??0)?></div><div class="sp-lbl">Others</div></div></div>
</div>

<!-- ══ Filter Card ══ -->
<div class="filter-card">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label fw-semibold fs-12 mb-1"><i class="ri-user-star-line me-1"></i>Filter by Owner</label>
            <select id="filterOwner" class="form-select form-select-sm">
                <option value="">-- All Owners --</option>
                <?php foreach($activeOwners as $o): ?>
                <option value="<?=$o['VehicleOwnerId']?>"><?=htmlspecialchars($o['OwnerName'])?> | <?=htmlspecialchars($o['MobileNo'])?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold fs-12 mb-1"><i class="ri-filter-3-line me-1"></i>Vehicle Type</label>
            <select id="filterType" class="form-select form-select-sm">
                <option value="">-- All Types --</option>
                <option value="Truck">Truck</option><option value="Trailer">Trailer</option>
                <option value="Tempo">Tempo</option><option value="Container">Container</option><option value="Other">Other</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold fs-12 mb-1"><i class="ri-toggle-line me-1"></i>Status</label>
            <select id="filterStatus" class="form-select form-select-sm">
                <option value="">-- All Status --</option>
                <option value="Active">Active</option><option value="Inactive">Inactive</option>
            </select>
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button class="btn btn-outline-secondary btn-sm w-100" onclick="clearFilters()" title="Clear"><i class="ri-refresh-line"></i></button>
        </div>
        <!-- Right side search -->
        <div class="col-md-4 ms-auto">
            <label class="form-label fw-semibold fs-12 mb-1"><i class="ri-search-line me-1"></i>Search</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0" style="border-radius:8px 0 0 8px;"><i class="ri-search-line text-muted"></i></span>
                <input type="text" id="customSearch" class="form-control border-start-0 ps-1"
                    placeholder="Vehicle No, Owner, RC No..."
                    style="border-radius:0;box-shadow:none;">
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
            <table id="vehicleTable" class="table table-hover align-middle mb-0 w-100">
                <thead style="background:#f8fafc;">
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>Vehicle No</th>
                        <th>Name / Type</th>
                        <th>Capacity</th>
                        <th>RC No</th>
                        <th>Owner</th>
                        <th>Status</th>
                        <th style="width:110px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i=1; foreach($vehicles as $r): $a=$r['IsActive']==='Yes'; $rj=htmlspecialchars(json_encode($r),ENT_QUOTES); ?>
                <tr id="vrow-<?=$r['VehicleId']?>">
                    <td class="text-muted fw-medium fs-13"><?=$i++?></td>
                    <td>
                        <div class="fw-bold" style="font-size:14px;letter-spacing:.3px;"><?=htmlspecialchars($r['VehicleNumber'])?></div>
                    </td>
                    <td>
                        <div style="font-size:13px;"><?=htmlspecialchars($r['VehicleName']??'—')?></div>
                        <?php if(!empty($r['VehicleType'])): ?>
                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill" style="font-size:10px;"><?=htmlspecialchars($r['VehicleType'])?></span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:13px;"><?=!empty($r['Capacity'])?htmlspecialchars($r['Capacity']).' Ton':'<span class="text-muted">—</span>'?></td>
                    <td>
                        <?=!empty($r['RCNo'])?"<code style='font-size:11px;'>".htmlspecialchars($r['RCNo'])."</code>":"<span class='text-muted'>—</span>"?>
                    </td>
                    <td>
                        <?php if(!empty($r['OwnerName'])): ?>
                        <div class="fw-medium" style="font-size:13px;"><?=htmlspecialchars($r['OwnerName'])?></div>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td>
                        <span class="badge rounded-pill <?=$a?'bg-success':'bg-danger'?>">
                            <i class="<?=$a?'ri-checkbox-circle-line':'ri-close-circle-line'?> me-1"></i>
                            <?=$a?'Active':'Inactive'?>
                        </span>
                    </td>
                    <td>
                        <div class="action-btn-group">
                            <button class="btn btn-sm btn-outline-info btn-icon" onclick='viewDetail(<?=$rj?>)' title="View Details"><i class="ri-eye-line"></i></button>
                            <button class="btn btn-sm btn-warning btn-icon" onclick='editVehicle(<?=$rj?>)' title="Edit"><i class="ri-edit-line"></i></button>
                            <button class="btn btn-sm <?=$a?'btn-danger':'btn-success'?> btn-icon"
                                onclick="toggleStatus(this)"
                                data-id="<?=$r['VehicleId']?>"
                                data-status="<?=$r['IsActive']?>"
                                data-name="<?=htmlspecialchars($r['VehicleNumber'])?>"
                                title="<?=$a?'Deactivate':'Activate'?>">
                                <i class="<?=$a?'ri-toggle-line':'ri-toggle-fill'?>"></i>
                            </button>
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

<!-- ══════════════════════════════════
     ADD / EDIT MODAL
══════════════════════════════════ -->
<div class="modal fade" id="vehicleModal" tabindex="-1" data-bs-backdrop="static">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 20px 60px rgba(0,0,0,.15);">
    <div class="modal-header" style="background:linear-gradient(135deg,#1a237e,#1d4ed8);border-radius:16px 16px 0 0;padding:18px 24px;">
        <h5 class="modal-title text-white fw-bold" id="vehicleModalTitle"><i class="ri-truck-line me-2"></i>Add Vehicle</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <form id="vehicleForm" novalidate>
        <input type="hidden" name="ajax" value="1">
        <input type="hidden" name="VehicleId" id="VehicleId">
        <div class="modal-body p-4">

            <div class="form-section-head"><i class="ri-truck-line me-1"></i>Vehicle Information</div>
            <div class="row g-3 mb-1">
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-13">Vehicle Number <span class="req">*</span></label>
                    <input type="text" name="VehicleNumber" id="f_VehicleNumber" class="form-control" placeholder="e.g. GJ01AB1234" data-uppercase>
                    <div class="srv-feedback invalid-feedback"></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-13">Vehicle Name</label>
                    <input type="text" name="VehicleName" id="f_VehicleName" class="form-control" placeholder="e.g. Tata Ace">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-13">Vehicle Type</label>
                    <select name="VehicleType" id="f_VehicleType" class="form-select">
                        <option value="">-- Select Type --</option>
                        <option value="Truck">🚛 Truck</option>
                        <option value="Trailer">🚚 Trailer</option>
                        <option value="Tempo">🛻 Tempo</option>
                        <option value="Container">📦 Container</option>
                        <option value="Other">🔧 Other</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-13">Capacity (Ton)</label>
                    <div class="input-group">
                        <input type="number" step="0.01" name="Capacity" id="f_Capacity" class="form-control" placeholder="e.g. 10.5" min="0">
                        <span class="input-group-text bg-light">Ton</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-13">RC Number</label>
                    <input type="text" name="RCNo" id="f_RCNo" class="form-control" placeholder="Registration cert. no." data-uppercase>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-13">Status</label>
                    <select name="IsActive" id="f_IsActive" class="form-select">
                        <option value="Yes">✅ Active</option>
                        <option value="No">❌ Inactive</option>
                    </select>
                </div>
            </div>

            <div class="form-section-head mt-3"><i class="ri-user-star-line me-1"></i>Owner Assignment</div>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold fs-13">Vehicle Owner <span class="req">*</span></label>
                    <select name="VehicleOwnerId" id="sel_Owner" class="form-select">
                        <option value="">-- Search & Select Owner --</option>
                        <?php foreach($activeOwners as $o): ?>
                        <option value="<?=$o['VehicleOwnerId']?>"><?=htmlspecialchars($o['OwnerName'])?> | <?=htmlspecialchars($o['MobileNo'])?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="srv-feedback invalid-feedback" id="err_Owner"></div>
                </div>
            </div>

        </div>
        <div class="modal-footer" style="border-top:1px solid #e2e8f0;padding:14px 24px;">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="ri-close-line me-1"></i>Cancel</button>
            <button type="submit" class="btn btn-primary px-4" id="vehicleSaveBtn"><i class="ri-save-line me-1"></i>Save Vehicle</button>
        </div>
    </form>
</div>
</div>
</div>

<!-- ══════════════════════════════════
     DETAIL MODAL — Tabbed
══════════════════════════════════ -->
<div class="modal fade" id="detailModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered" style="max-width:540px;">
<div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 20px 60px rgba(0,0,0,.15);">
    <div class="modal-header" style="background:linear-gradient(135deg,#1a237e,#1d4ed8);border-radius:16px 16px 0 0;padding:18px 24px;">
        <h5 class="modal-title text-white fw-bold"><i class="ri-truck-line me-2"></i>Vehicle Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body p-4">
        <!-- Header -->
        <div id="detailHeader" style="text-align:center;margin-bottom:16px;"></div>
        <!-- Tabs -->
        <div class="d-flex gap-2 mb-3 flex-wrap">
            <button class="detail-tab-btn active" onclick="switchTab('vinfo',this)">🚛 Vehicle</button>
            <button class="detail-tab-btn" onclick="switchTab('owner',this)">👤 Owner</button>
            <button class="detail-tab-btn" onclick="switchTab('trips',this)">📋 Last Trips</button>
        </div>
        <!-- Tab: Vehicle Info -->
        <div class="detail-tab-content active" id="tab-vinfo">
            <div id="vinfoContent" style="background:#f8fafc;border-radius:12px;padding:4px 16px;"></div>
        </div>
        <!-- Tab: Owner Info -->
        <div class="detail-tab-content" id="tab-owner">
            <div id="ownerContent"><div class="text-center py-3"><span class="spinner-border spinner-border-sm text-primary"></span> Loading...</div></div>
        </div>
        <!-- Tab: Last Trips -->
        <div class="detail-tab-content" id="tab-trips">
            <div id="tripsContent"><div class="text-center py-3"><span class="spinner-border spinner-border-sm text-primary"></span> Loading...</div></div>
        </div>
    </div>
    <div class="modal-footer" style="border-top:1px solid #f1f5f9;padding:12px 20px;">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-warning btn-sm" id="detailEditBtn"><i class="ri-edit-line me-1"></i>Edit</button>
    </div>
</div>
</div>
</div>

<!-- Scripts -->
<script src="/Sama_Roadlines/assets/js/validation.js"></script>
<script>
var vehicleDT;
let _currentVehicle = null;

$(document).ready(function(){
    vehicleDT = $('#vehicleTable').DataTable({
        scrollX:true, pageLength:25,
        columnDefs:[{orderable:false,targets:[0,7]}],
        dom:'rtip',
        language:{paginate:{previous:'‹',next:'›'},info:'Showing _START_–_END_ of _TOTAL_',emptyTable:'No vehicles found.'},
        drawCallback:function(){var i=this.api().page.info();$('#filterInfo').text(i.recordsDisplay+'/'+i.recordsTotal);}
    });

    $('#filterOwner').select2({theme:'bootstrap-5',allowClear:true,placeholder:'-- All Owners --',width:'100%'});
    $('#filterType').select2({theme:'bootstrap-5',allowClear:true,placeholder:'-- All Types --',width:'100%'});
    $('#filterStatus').select2({theme:'bootstrap-5',allowClear:true,placeholder:'-- All Status --',width:'100%'});
    $('#sel_Owner').select2({theme:'bootstrap-5',dropdownParent:$('#vehicleModal'),allowClear:true,placeholder:'Search owner...',width:'100%'});

    // Owner filter — AJAX reload
    $('#filterOwner').on('change',function(){
        const ownerId=this.value;
        if(!ownerId){clearFilters();return;}
        Swal.fire({title:'Loading...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
        fetch('VehicleView.php?filterOwner=1&ownerId='+ownerId)
        .then(r=>r.json()).then(rows=>{
            Swal.close();
            vehicleDT.clear();
            let i=1;
            rows.forEach(r=>vehicleDT.row.add([i++,buildVehicleNoHtml(r),buildTypeHtml(r),
                (r.Capacity||'0')+' Ton',r.RCNo?`<code style='font-size:11px;'>${r.RCNo}</code>`:'—',
                r.OwnerName||'—',buildStatusBadge(r.IsActive),buildActionBtns(r)]));
            vehicleDT.draw(false);
            $('#filterInfo').text(rows.length+' vehicles');
        }).catch(()=>Swal.fire({icon:'error',title:'Filter failed'}));
    });

    $('#filterType').on('change',function(){vehicleDT.column(2).search(this.value||'').draw();});
    $('#filterStatus').on('change',function(){vehicleDT.column(6).search(this.value||'').draw();});
    $('#customSearch').on('keyup input',function(){vehicleDT.search($(this).val()).draw();});
});

function clearFilters(){
    $('#filterOwner,#filterType,#filterStatus').val(null).trigger('change');
    $('#customSearch').val('');
    vehicleDT.search('').columns().search('').draw();
}

/* ── Helpers ── */
function buildVehicleNoHtml(r){return `<div class='fw-bold' style='font-size:14px;letter-spacing:.3px;'>${r.VehicleNumber}</div>`;}
function buildTypeHtml(r){
    const icons={Truck:'🚛',Trailer:'🚚',Tempo:'🛻',Container:'📦',Other:'🔧'};
    const name=r.VehicleName||'—';
    const type=r.VehicleType?`<span class='badge bg-info-subtle text-info border border-info-subtle rounded-pill' style='font-size:10px;'>${icons[r.VehicleType]||''} ${r.VehicleType}</span>`:'';
    return `<div style='font-size:13px;'>${name}</div>${type}`;
}
function buildStatusBadge(s){
    return s==='Yes'
        ? "<span class='badge rounded-pill bg-success'><i class='ri-checkbox-circle-line me-1'></i>Active</span>"
        : "<span class='badge rounded-pill bg-danger'><i class='ri-close-circle-line me-1'></i>Inactive</span>";
}
function buildActionBtns(r){
    const a=r.IsActive==='Yes', rj=JSON.stringify(r).replace(/'/g,"&#39;");
    return `<div class='action-btn-group'>
        <button class='btn btn-sm btn-outline-info btn-icon' onclick='viewDetail(${rj})' title='View'><i class='ri-eye-line'></i></button>
        <button class='btn btn-sm btn-warning btn-icon' onclick='editVehicle(${rj})' title='Edit'><i class='ri-edit-line'></i></button>
        <button class='btn btn-sm ${a?'btn-danger':'btn-success'} btn-icon' onclick='toggleStatus(this)'
            data-id='${r.VehicleId}' data-status='${r.IsActive}' data-name='${r.VehicleNumber}'
            title='${a?'Deactivate':'Activate'}'><i class='${a?'ri-toggle-line':'ri-toggle-fill'}'></i></button>
    </div>`;
}

window.addEventListener('offline',()=>SRV.toast.warning('No internet!'));
window.addEventListener('online', ()=>SRV.toast.success('Back online!'));

function doAjax(body,cb){
    if(!navigator.onLine){Swal.fire({icon:'warning',title:'No Internet',text:'Check your connection.'});return;}
    Swal.fire({title:'Please wait...',allowOutsideClick:false,allowEscapeKey:false,didOpen:()=>Swal.showLoading()});
    fetch('',{method:'POST',body}).then(r=>r.json()).then(res=>{Swal.close();cb(res);})
    .catch(()=>Swal.fire({icon:'error',title:'Error',text:'Server error.'}));
}

function clearFormVal(){
    document.querySelectorAll('#vehicleForm .is-invalid,#vehicleForm .is-valid').forEach(el=>el.classList.remove('is-invalid','is-valid'));
    document.querySelectorAll('#vehicleForm .srv-feedback').forEach(el=>{el.textContent='';el.style.display='none';});
}

/* ── Open Add ── */
function openAddModal(){
    document.getElementById('vehicleForm').reset();
    document.getElementById('VehicleId').value='';
    clearFormVal();
    $('#sel_Owner').val(null).trigger('change');
    document.getElementById('vehicleModalTitle').innerHTML='<i class="ri-add-circle-line me-2"></i>Add New Vehicle';
    document.getElementById('vehicleSaveBtn').innerHTML='<i class="ri-save-line me-1"></i>Save Vehicle';
    new bootstrap.Modal('#vehicleModal').show();
    setTimeout(()=>document.getElementById('f_VehicleNumber').focus(),400);
}

/* ── Open Edit ── */
function editVehicle(d){
    document.getElementById('vehicleForm').reset();
    clearFormVal();
    ['VehicleId','VehicleNumber','VehicleName','VehicleType','Capacity','RCNo','IsActive']
        .forEach(k=>{const el=document.getElementById('f_'+k)||document.querySelector(`#vehicleForm [name="${k}"]`);if(el)el.value=d[k]??'';});
    $('#sel_Owner').val(d.VehicleOwnerId||'').trigger('change');
    document.getElementById('vehicleModalTitle').innerHTML='<i class="ri-edit-line me-2"></i>Edit Vehicle';
    document.getElementById('vehicleSaveBtn').innerHTML='<i class="ri-save-line me-1"></i>Update Vehicle';
    const dm=bootstrap.Modal.getInstance(document.getElementById('detailModal'));
    if(dm)dm.hide();
    setTimeout(()=>new bootstrap.Modal('#vehicleModal').show(),200);
}

/* ── Form Submit ── */
document.getElementById('vehicleForm').addEventListener('submit',function(e){
    e.preventDefault();
    const valid=SRV.validate('#vehicleForm',{
        'f_VehicleNumber': {required:[true,'Vehicle Number is required.'],minLength:[4,'Min 4 characters.']},
        'sel_Owner':       {selectRequired:[true,'Please select Vehicle Owner.']}
    });
    if(!valid)return;

    const isEdit=!!document.getElementById('VehicleId').value;
    const btn=document.getElementById('vehicleSaveBtn');
    btn.disabled=true;
    btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

    doAjax(new FormData(this),function(res){
        btn.disabled=false;
        btn.innerHTML=isEdit?'<i class="ri-save-line me-1"></i>Update Vehicle':'<i class="ri-save-line me-1"></i>Save Vehicle';
        if(res.status==='success'){
            bootstrap.Modal.getInstance(document.getElementById('vehicleModal')).hide();
            const r=res.row;
            const icons={Truck:'🚛',Trailer:'🚚',Tempo:'🛻',Container:'📦',Other:'🔧'};
            const typeHtml=r.VehicleType?`<span class='badge bg-info-subtle text-info border border-info-subtle rounded-pill' style='font-size:10px;'>${icons[r.VehicleType]||''} ${r.VehicleType}</span>`:'';
            const nameTypeHtml=`<div style='font-size:13px;'>${r.VehicleName||'—'}</div>${typeHtml}`;
            const rcHtml=r.RCNo?`<code style='font-size:11px;'>${r.RCNo}</code>`:'<span class="text-muted">—</span>';

            if(isEdit){
                const tr=document.getElementById('vrow-'+r.VehicleId);
                if(tr){
                    tr.cells[1].innerHTML=buildVehicleNoHtml(r);
                    tr.cells[2].innerHTML=nameTypeHtml;
                    tr.cells[3].textContent=(r.Capacity||'0')+' Ton';
                    tr.cells[4].innerHTML=rcHtml;
                    tr.cells[5].textContent=r.OwnerName||'—';
                    tr.cells[6].innerHTML=buildStatusBadge(r.IsActive);
                    tr.cells[7].innerHTML=buildActionBtns(r);
                    vehicleDT.row(tr).invalidate().draw(false);
                }
                SRV.toast.success('Vehicle updated!');
            } else {
                const nn=vehicleDT.row.add([
                    vehicleDT.data().count()+1,
                    buildVehicleNoHtml(r),nameTypeHtml,
                    (r.Capacity||'0')+' Ton',rcHtml,
                    r.OwnerName||'—',buildStatusBadge(r.IsActive),buildActionBtns(r)
                ]).draw(false).node();
                $(nn).attr('id','vrow-'+r.VehicleId);
                SRV.toast.success('Vehicle added!');
            }
        } else {
            Swal.fire({icon:'error',title:'Error',html:res.message||'Could not save.',confirmButtonColor:'#1a237e'});
        }
    });
});

/* ── Toggle Status ── */
function toggleStatus(btn){
    if(!navigator.onLine){SRV.toast.warning('No internet!');return;}
    const id=btn.dataset.id,st=btn.dataset.status,name=btn.dataset.name||'this vehicle',isDe=st==='Yes';
    Swal.fire({
        title:isDe?'⚠️ Deactivate?':'✅ Activate?',
        html:`Vehicle <b>${name}</b> will be ${isDe?'deactivated':'activated'}.`,
        icon:'warning',showCancelButton:true,
        confirmButtonText:isDe?'Yes, Deactivate':'Yes, Activate',cancelButtonText:'Cancel',
        confirmButtonColor:isDe?'#dc2626':'#16a34a',cancelButtonColor:'#64748b'
    }).then(result=>{
        if(!result.isConfirmed)return;
        doAjax(new URLSearchParams({toggle:1,id,status:st}),function(res){
            if(res.status==='success'){
                const ns=res.newStatus,a=ns==='Yes';
                btn.dataset.status=ns;
                btn.className=`btn btn-sm ${a?'btn-danger':'btn-success'} btn-icon`;
                btn.innerHTML=`<i class='${a?'ri-toggle-line':'ri-toggle-fill'}'></i>`;
                btn.title=a?'Deactivate':'Activate';
                btn.closest('tr').cells[6].innerHTML=buildStatusBadge(ns);
                vehicleDT.row(btn.closest('tr')).invalidate().draw(false);
                SRV.toast.success(`Vehicle ${a?'activated':'deactivated'}!`);
            } else { SRV.toast.error('Status update failed!'); }
        });
    });
}

/* ── View Detail ── */
function viewDetail(d){
    _currentVehicle=d;
    const a=d.IsActive==='Yes';
    const icons={Truck:'🚛',Trailer:'🚚',Tempo:'🛻',Container:'📦',Other:'🔧'};
    const icon=icons[d.VehicleType]||'🚗';

    // Header
    document.getElementById('detailHeader').innerHTML=`
        <div style="width:56px;height:56px;background:linear-gradient(135deg,#1a237e,#1d4ed8);border-radius:14px;display:inline-flex;align-items:center;justify-content:center;font-size:28px;margin-bottom:10px;">${icon}</div>
        <div style="font-size:18px;font-weight:800;color:#0f172a;letter-spacing:.5px;">${d.VehicleNumber}</div>
        <div style="margin-top:6px;">
            ${d.VehicleType?`<span class='badge bg-info-subtle text-info border rounded-pill me-1'>${d.VehicleType}</span>`:''}
            <span class='badge rounded-pill ${a?'bg-success':'bg-danger'}'><i class='${a?'ri-checkbox-circle-line':'ri-close-circle-line'} me-1'></i>${a?'Active':'Inactive'}</span>
        </div>`;

    // Vehicle info tab
    const row=(l,v)=>`<div class="detail-row"><div class="detail-label">${l}</div><div class="detail-value">${v||'<span class="text-muted">—</span>'}</div></div>`;
    document.getElementById('vinfoContent').innerHTML=`
        ${row('🚛 Veh. No',  `<code style="font-size:13px;font-weight:700;">${d.VehicleNumber}</code>`)}
        ${row('📛 Name',     d.VehicleName)}
        ${row('🔖 Type',     d.VehicleType)}
        ${row('⚖️ Capacity', d.Capacity ? d.Capacity+' Ton' : '')}
        ${row('📄 RC No',    d.RCNo ? `<code>${d.RCNo}</code>` : '')}`;

    // Reset dynamic tabs
    document.getElementById('ownerContent').innerHTML='<div class="text-center py-3"><span class="spinner-border spinner-border-sm text-primary"></span> Loading...</div>';
    document.getElementById('tripsContent').innerHTML='<div class="text-center py-3"><span class="spinner-border spinner-border-sm text-primary"></span> Loading...</div>';

    // Reset to first tab
    document.querySelectorAll('.detail-tab-btn').forEach(b=>b.classList.remove('active'));
    document.querySelectorAll('.detail-tab-content').forEach(c=>c.classList.remove('active'));
    document.querySelector('.detail-tab-btn').classList.add('active');
    document.getElementById('tab-vinfo').classList.add('active');

    new bootstrap.Modal('#detailModal').show();

    // AJAX load owner + trips
    fetch('',{method:'POST',body:new URLSearchParams({get_detail:1,vehicle_id:d.VehicleId})})
    .then(r=>r.json()).then(data=>{

        // ── Owner Tab ──
        const o=data.owner;
        if(!o || !o.VehicleOwnerId){
            document.getElementById('ownerContent').innerHTML='<div class="text-center text-muted py-3">No owner assigned.</div>';
        } else {
            document.getElementById('ownerContent').innerHTML=`
                <div style="text-align:center;margin-bottom:14px;">
                    <div style="width:44px;height:44px;background:linear-gradient(135deg,#166534,#16a34a);border-radius:12px;display:inline-flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:8px;">👤</div>
                    <div style="font-size:16px;font-weight:800;color:#0f172a;">${o.OwnerName}</div>
                </div>
                <div style="background:#f8fafc;border-radius:12px;padding:4px 16px;">
                    ${row('📞 Mobile',  o.MobileNo?`<a href="tel:${o.MobileNo}" class="text-primary fw-medium">${o.MobileNo}</a>`:'')}
                    ${row('📱 Alt No',  o.AlternateMobile?`<a href="tel:${o.AlternateMobile}" class="text-primary">${o.AlternateMobile}</a>`:'')}
                    ${row('🏙️ City',    o.City)}
                    ${row('📍 State',   o.State)}
                    ${row('🏦 Bank',    o.BankName)}
                    ${row('💳 Account', o.AccountNo?`<code>${o.AccountNo}</code>`:'')}
                    ${row('🔢 IFSC',    o.IFSC?`<code>${o.IFSC}</code>`:'')}
                    ${row('📲 UPI',     o.UPI)}
                </div>`;
        }

        // ── Trips Tab ──
        const st=data.stats;
        let tripHtml=`
            <div class="d-flex gap-2 mb-3">
                <div class="pay-mini-card" style="background:#f0f4ff;border:1px solid #c7d7fc;">
                    <div class="pay-mini-val" style="color:#1a237e;">${st.TotalTrips||0}</div>
                    <div class="pay-mini-lbl">Total Trips</div>
                </div>
                <div class="pay-mini-card" style="background:#dbeafe;border:1px solid #93c5fd;">
                    <div class="pay-mini-val" style="color:#1d4ed8;">${st.OpenTrips||0}</div>
                    <div class="pay-mini-lbl">🔵 Open</div>
                </div>
                <div class="pay-mini-card" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                    <div class="pay-mini-val" style="color:#16a34a;">${st.CompletedTrips||0}</div>
                    <div class="pay-mini-lbl">✅ Completed</div>
                </div>
                <div class="pay-mini-card" style="background:#f8fafc;border:1px solid #e2e8f0;">
                    <div class="pay-mini-val" style="color:#1a237e;">Rs.${Number(st.TotalFreight||0).toLocaleString('en-IN')}</div>
                    <div class="pay-mini-lbl">Total Freight</div>
                </div>
            </div>`;

        if(!data.trips || data.trips.length===0){
            tripHtml+='<div class="text-center text-muted py-2" style="font-size:13px;"><i class="ri-truck-line" style="font-size:28px;color:#e2e8f0;display:block;margin-bottom:6px;"></i>No trips yet.</div>';
        } else {
            tripHtml+='<div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Last Trips</div>';
            data.trips.forEach(t=>{
                const isOpen=t.TripStatus==='Open';
                const ownerPaid=t.OwnerPaymentStatus==='Paid';
                const tripTypeBadge=t.TripType==='Agent'
                    ? "<span class='badge bg-warning text-dark' style='font-size:9px;'>Agent</span>"
                    : "<span class='badge bg-primary' style='font-size:9px;'>Regular</span>";
                const ownerPayBadge=ownerPaid
                    ? "<span class='badge bg-success' style='font-size:9px;'>Paid</span>"
                    : "<span class='badge bg-danger' style='font-size:9px;'>Pending</span>";

                tripHtml+=`<div class="trip-row ${isOpen?'open':'completed'}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div style="flex:1;">
                            <div style="font-size:12.5px;font-weight:700;color:#0f172a;">
                                ${t.FromLocation||'—'} → ${t.ToLocation||'—'}
                            </div>
                            <div style="font-size:11px;color:#64748b;margin-top:2px;">
                                ${t.TripDate} &nbsp;·&nbsp; ${t.ConsignerName||'—'}
                            </div>
                        </div>
                        <div style="text-align:right;flex-shrink:0;margin-left:8px;">
                            <div style="font-size:13px;font-weight:700;color:#1a237e;">Rs.${Number(t.FreightAmount||0).toLocaleString('en-IN')}</div>
                            <div style="margin-top:3px;">${tripTypeBadge} ${ownerPayBadge}</div>
                        </div>
                    </div>
                </div>`;
            });
            if(data.trips.length>=8) tripHtml+=`<div class="text-center mt-2"><a href="/Sama_Roadlines/views/pages/RegularTrips.php" class="btn btn-outline-primary btn-sm" style="font-size:12px;">View All Trips</a></div>`;
        }
        document.getElementById('tripsContent').innerHTML=tripHtml;

    }).catch(()=>{
        document.getElementById('ownerContent').innerHTML='<div class="text-danger text-center py-2">Failed to load.</div>';
        document.getElementById('tripsContent').innerHTML='<div class="text-danger text-center py-2">Failed to load.</div>';
    });
}

function switchTab(name,btn){
    document.querySelectorAll('.detail-tab-btn').forEach(b=>b.classList.remove('active'));
    document.querySelectorAll('.detail-tab-content').forEach(c=>c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-'+name).classList.add('active');
}

document.getElementById('detailEditBtn').addEventListener('click',function(){if(_currentVehicle)editVehicle(_currentVehicle);});
</script>

<?php require_once "../layout/footer.php"; ?>
