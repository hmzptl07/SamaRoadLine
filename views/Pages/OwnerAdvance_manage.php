<?php
/* ================================================================
   OwnerAdvance_manage.php  —  Vehicle Owner Advance Management
   UI only: session, auth, thin AJAX wrappers, HTML/JS
================================================================ */
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

require_once "../../businessLogics/Admin.php";
require_once "../../businessLogics/OwnerAdvance.php";
require_once "../../businessLogics/OwnerPayment.php";
Admin::checkAuth();

/* ── AJAX: ADD ADVANCE ── */
if (isset($_POST['addAdvance'])) {
    header('Content-Type: application/json');
    echo json_encode(OwnerAdvance::insert($_POST));
    exit();
}

/* ── AJAX: GET UNPAID TRIPS FOR OWNER ── */
if (isset($_GET['getOwnerTrips'])) {
    header('Content-Type: application/json');
    echo json_encode(OwnerAdvance::getOwnerUnpaidTrips(intval($_GET['OwnerId'])));
    exit();
}

/* ── AJAX: ADJUST ADVANCE ── */
if (isset($_POST['adjustAdvance'])) {
    header('Content-Type: application/json');
    echo json_encode(OwnerAdvance::adjustAgainstTrip(
        intval($_POST['OwnerAdvanceId']),
        intval($_POST['TripId']),
        intval($_POST['OwnerId']),
        floatval($_POST['AdjustedAmount']),
        $_POST['AdjustmentDate'] ?? date('Y-m-d'),
        trim($_POST['Remarks'] ?? '')
    ));
    exit();
}

/* ── AJAX: GET ADJUSTMENTS ── */
if (isset($_GET['getAdjustments'])) {
    header('Content-Type: application/json');
    echo json_encode(OwnerAdvance::getAdjustments(intval($_GET['AdvanceId'])));
    exit();
}

/* ── PAGE DATA ── */
$filterOwner = !empty($_GET['ownerId']) ? intval($_GET['ownerId']) : null;
$advances    = OwnerAdvance::getAll($filterOwner);
$owners      = OwnerPayment::getOwners();
$summary     = OwnerAdvance::getSummary();

require_once "../layout/header.php";
require_once "../layout/sidebar.php";
?>

<style>
.adv-stat { border-radius: 12px; border: none; }
.adv-stat .icon-box { width: 46px; height: 46px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.adv-progress { height: 6px; border-radius: 3px; }
.status-open     { background: #fef9c3; border-left: 3px solid #eab308; }
.status-partial  { background: #e0f2fe; border-left: 3px solid #0ea5e9; }
.status-full     { background: #f0fdf4; border-left: 3px solid #22c55e; }
</style>

<div class="main-content app-content">
<div class="container-fluid">

<!-- ── PAGE HEADER ── -->
<div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h5 class="fw-bold mb-1">
            <i class="ri-hand-coin-line me-2" style="color:#7c3aed"></i>Owner Advance Management
        </h5>
        <p class="text-muted fs-12 mb-0">Advance given to vehicle owners — adjust against trip freight payments</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <select id="ownerFilter" class="form-select form-select-sm" style="min-width:200px" onchange="window.location.href = this.value ? '?ownerId='+this.value : '?'">
            <option value="">All Owners</option>
            <?php foreach ($owners as $o): ?>
            <option value="<?= $o['VehicleOwnerId'] ?>" <?= $filterOwner == $o['VehicleOwnerId'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($o['OwnerName']) ?> <?= $o['City'] ? "— {$o['City']}" : '' ?>
            </option>
            <?php endforeach; ?>
        </select>
        <button class="btn fw-bold text-white" style="background:#7c3aed" onclick="new bootstrap.Modal('#addAdvModal').show()">
            <i class="ri-add-circle-line me-1"></i>New Advance
        </button>
    </div>
</div>

<!-- ── STAT CARDS ── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card adv-stat shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="icon-box" style="background:#ede9fe">💰</div>
                    <div class="text-muted fs-12">Total Advances</div>
                </div>
                <div class="fw-bold fs-20" style="color:#7c3aed">₹<?= number_format($summary['TotalAmount'] ?? 0, 0) ?></div>
                <div class="fs-11 text-muted"><?= $summary['TotalEntries'] ?? 0 ?> entries</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card adv-stat shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="icon-box bg-success bg-opacity-10">✅</div>
                    <div class="text-muted fs-12">Adjusted (Used)</div>
                </div>
                <div class="fw-bold fs-20 text-success">₹<?= number_format($summary['TotalAdjusted'] ?? 0, 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card adv-stat shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="icon-box bg-danger bg-opacity-10">⏳</div>
                    <div class="text-muted fs-12">Remaining Balance</div>
                </div>
                <div class="fw-bold fs-20 text-danger">₹<?= number_format($summary['TotalRemaining'] ?? 0, 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card adv-stat shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="icon-box bg-warning bg-opacity-10">🔓</div>
                    <div class="text-muted fs-12">Open Advances</div>
                </div>
                <div class="fw-bold fs-20 text-warning"><?= $summary['OpenCount'] ?? 0 ?></div>
                <div class="fs-11 text-muted">not fully adjusted</div>
            </div>
        </div>
    </div>
</div>

<!-- ── ADVANCES TABLE ── -->
<div class="card shadow-sm">
    <div class="card-header py-2 d-flex align-items-center justify-content-between">
        <span class="fw-semibold fs-13"><i class="ri-list-check me-2" style="color:#7c3aed"></i>Advance Records</span>
        <div class="d-flex gap-1">
            <button class="btn btn-xs btn-outline-secondary py-1 px-2 fs-11" onclick="filterAdv('all')">All</button>
            <button class="btn btn-xs btn-outline-warning py-1 px-2 fs-11" onclick="filterAdv('Open')">Open</button>
            <button class="btn btn-xs btn-outline-info py-1 px-2 fs-11" onclick="filterAdv('Partial')">Partial</button>
            <button class="btn btn-xs btn-outline-success py-1 px-2 fs-11" onclick="filterAdv('Fully')">Full Used</button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="advTable" class="table table-bordered table-hover mb-0 fs-13">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Owner</th>
                        <th>Mode</th>
                        <th>Reference</th>
                        <th class="text-end">Advance</th>
                        <th>Used</th>
                        <th class="text-end">Remaining</th>
                        <th>Status</th>
                        <th>Remarks</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($advances as $i => $a):
                    $pct = $a['Amount'] > 0 ? min(100, round($a['AdjustedAmount'] / $a['Amount'] * 100)) : 0;
                    $rowCls = match($a['Status']) {
                        'FullyAdjusted'    => 'status-full',
                        'PartiallyAdjusted'=> 'status-partial',
                        default            => 'status-open'
                    };
                    $stBadge = match($a['Status']) {
                        'FullyAdjusted'    => '<span class="badge bg-success">Fully Used</span>',
                        'PartiallyAdjusted'=> '<span class="badge bg-info text-dark">Partial</span>',
                        default            => '<span class="badge bg-warning text-dark">Open</span>'
                    };
                ?>
                    <tr class="<?= $rowCls ?>">
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td style="white-space:nowrap"><?= date('d-m-Y', strtotime($a['AdvanceDate'])) ?></td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($a['OwnerName']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($a['City'] ?? '') ?><?= $a['MobileNo'] ? ' · ' . $a['MobileNo'] : '' ?></small>
                        </td>
                        <td><?= $a['PaymentMode'] ?></td>
                        <td><small class="text-muted"><?= htmlspecialchars($a['ReferenceNo'] ?? '—') ?></small></td>
                        <td class="text-end fw-bold" style="color:#7c3aed">₹<?= number_format($a['Amount'], 2) ?></td>
                        <td>
                            <div class="text-success fw-semibold fs-12">₹<?= number_format($a['AdjustedAmount'], 2) ?></div>
                            <div class="progress adv-progress mt-1" style="min-width:70px">
                                <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                            </div>
                            <div class="fs-11 text-muted"><?= $pct ?>%</div>
                        </td>
                        <td class="text-end fw-bold <?= floatval($a['RemainingAmount']) > 0 ? 'text-danger' : 'text-success' ?>">
                            ₹<?= number_format($a['RemainingAmount'], 2) ?>
                        </td>
                        <td><?= $stBadge ?></td>
                        <td><small class="text-muted"><?= htmlspecialchars($a['Remarks'] ?? '—') ?></small></td>
                        <td class="text-center" style="white-space:nowrap">
                            <?php if ($a['Status'] !== 'FullyAdjusted'): ?>
                            <button class="btn btn-sm btn-outline-primary me-1"
                                onclick="openAdjModal(<?= $a['OwnerAdvanceId'] ?>, <?= $a['OwnerId'] ?>, <?= $a['RemainingAmount'] ?>, '<?= addslashes($a['OwnerName']) ?>', '<?= date('d-m-Y', strtotime($a['AdvanceDate'])) ?>')">
                                <i class="ri-links-line"></i> Adjust
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-info"
                                onclick="viewAdj(<?= $a['OwnerAdvanceId'] ?>, '<?= addslashes($a['OwnerName']) ?>')">
                                <i class="ri-history-line"></i>
                            </button>
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

<!-- ════ ADD ADVANCE MODAL ════ -->
<div class="modal fade" id="addAdvModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2 text-white" style="background:#7c3aed">
                <h5 class="modal-title fs-14 fw-bold"><i class="ri-hand-coin-line me-2"></i>New Owner Advance Entry</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-medium">Vehicle Owner <span class="text-danger">*</span></label>
                        <select id="adv_Owner" class="form-select">
                            <option value="">-- Select Owner --</option>
                            <?php foreach ($owners as $o): ?>
                            <option value="<?= $o['VehicleOwnerId'] ?>">
                                <?= htmlspecialchars($o['OwnerName']) ?><?= $o['City'] ? " — {$o['City']}" : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-medium">Date <span class="text-danger">*</span></label>
                        <input type="date" id="adv_Date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-medium">Amount (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text fw-bold">₹</span>
                            <input type="number" id="adv_Amount" class="form-control fw-bold" step="0.01" min="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-medium">Payment Mode</label>
                        <select id="adv_Mode" class="form-select">
                            <option value="Cash">💵 Cash</option>
                            <option value="Cheque">📋 Cheque</option>
                            <option value="NEFT">🏦 NEFT</option>
                            <option value="RTGS">🏦 RTGS</option>
                            <option value="UPI">📱 UPI</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-medium">Reference No.</label>
                        <input type="text" id="adv_Ref" class="form-control" placeholder="Optional">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium">Remarks</label>
                        <input type="text" id="adv_Remarks" class="form-control" placeholder="Optional...">
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button class="btn fw-bold text-white" style="background:#7c3aed" onclick="saveAdvance()">
                    <i class="ri-save-3-line me-1"></i>Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ════ ADJUST MODAL ════ -->
<div class="modal fade" id="adjModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title fs-14"><i class="ri-links-line me-2"></i>Adjust Advance — Deduct from Trip Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="rounded border p-3 mb-3 bg-light">
                    <div class="row g-0 text-center">
                        <div class="col-4 border-end">
                            <div class="fs-11 text-muted">Owner</div>
                            <div class="fw-bold fs-12" id="adj_ownerName">—</div>
                        </div>
                        <div class="col-4 border-end">
                            <div class="fs-11 text-muted">Advance Date</div>
                            <div class="fw-bold fs-12" id="adj_advDate">—</div>
                        </div>
                        <div class="col-4">
                            <div class="fs-11 text-muted">Available</div>
                            <div class="fw-bold fs-16 text-success" id="adj_avail">₹0</div>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="adj_AdvId">
                <input type="hidden" id="adj_OwnerId">

                <div class="mb-3">
                    <label class="form-label fw-medium">Select Trip <span class="text-danger">*</span></label>
                    <select id="adj_Trip" class="form-select" onchange="tripSelected()">
                        <option value="">-- Loading... --</option>
                    </select>
                </div>

                <div id="adj_tripInfo" class="alert alert-info p-2 mb-3 fs-12" style="display:none">
                    Net Payable: <b id="adj_tripNet">₹0</b> &nbsp;|&nbsp;
                    Already Paid: <b id="adj_tripPaid">₹0</b> &nbsp;|&nbsp;
                    Due: <b class="text-danger" id="adj_tripDue">₹0</b>
                </div>

                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label fw-medium">Adjustment Date</label>
                        <input type="date" id="adj_Date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-medium">Amount (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text fw-bold">₹</span>
                            <input type="number" id="adj_Amount" class="form-control fw-bold" step="0.01" min="0.01">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium">Remarks</label>
                        <input type="text" id="adj_Remarks" class="form-control" placeholder="Optional...">
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary fw-bold" onclick="saveAdj()">
                    <i class="ri-save-3-line me-1"></i>Save Adjustment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ════ ADJUSTMENT HISTORY MODAL ════ -->
<div class="modal fade" id="adjHistModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white py-2">
                <h5 class="modal-title fs-14"><i class="ri-history-line me-2"></i>Adjustments — <span id="adjHist_label"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-bordered table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>#</th><th>Date</th><th>Trip</th><th>Route</th><th>Adjusted</th><th>Remarks</th></tr>
                    </thead>
                    <tbody id="adjHistBody"></tbody>
                    <tfoot>
                        <tr class="table-success">
                            <td colspan="4" class="text-end fw-bold">Total Adjusted:</td>
                            <td class="fw-bold" id="adjHistTotal">₹0</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="modal-footer py-2">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $('#advTable').DataTable({
        pageLength: 25, order: [[0, 'desc']],
        language: { search: '', searchPlaceholder: 'Search...' }
    });
    $('#adv_Owner').select2({ theme: 'bootstrap-5', placeholder: '-- Select Owner --', allowClear: true, dropdownParent: $('#addAdvModal'), width: '100%' });
    $('#adj_Trip').select2({ theme: 'bootstrap-5', placeholder: '-- Select Trip --', allowClear: true, dropdownParent: $('#adjModal'), width: '100%' });
});

function filterAdv(s) {
    var dt = $('#advTable').DataTable();
    dt.column(8).search(s === 'all' ? '' : s).draw();
}

function saveAdvance() {
    var ownerId = $('#adv_Owner').val();
    var amt = parseFloat($('#adv_Amount').val());
    if (!ownerId) { Swal.fire({ icon: 'warning', title: 'Select an owner!', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false }); return; }
    if (!amt || amt <= 0) { Swal.fire({ icon: 'warning', title: 'Enter valid amount!', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false }); return; }
    Swal.fire({ title: 'Saving...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    var fd = new FormData();
    fd.append('addAdvance', 1); fd.append('OwnerId', ownerId);
    fd.append('AdvanceDate', $('#adv_Date').val()); fd.append('Amount', amt);
    fd.append('PaymentMode', $('#adv_Mode').val()); fd.append('ReferenceNo', $('#adv_Ref').val());
    fd.append('Remarks', $('#adv_Remarks').val());
    fetch('OwnerAdvance_manage.php', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
        Swal.close();
        if (res.status === 'success') {
            bootstrap.Modal.getInstance(document.getElementById('addAdvModal')).hide();
            Swal.fire({ icon: 'success', title: 'Advance Saved!', toast: true, position: 'top-end', showConfirmButton: false, timer: 2500 });
            setTimeout(() => location.reload(), 1800);
        } else { Swal.fire({ icon: 'error', title: 'Error', text: res.msg }); }
    }).catch(() => Swal.fire({ icon: 'error', title: 'Server Error' }));
}

function openAdjModal(advId, ownerId, remaining, ownerName, advDate) {
    window._adjRemaining = remaining;
    $('#adj_AdvId').val(advId); $('#adj_OwnerId').val(ownerId);
    $('#adj_ownerName').text(ownerName); $('#adj_advDate').text(advDate);
    $('#adj_avail').text('₹' + parseFloat(remaining).toFixed(2));
    $('#adj_Amount').val(parseFloat(remaining).toFixed(2));
    $('#adj_tripInfo').hide(); $('#adj_Remarks').val('');
    $('#adj_Trip').html('<option value="">Loading trips...</option>');
    fetch('OwnerAdvance_manage.php?getOwnerTrips=1&OwnerId=' + ownerId)
        .then(r => r.json()).then(trips => {
            window._adjTrips = trips;
            var opts = '<option value="">-- Select Trip --</option>';
            if (!trips.length) opts += '<option disabled>No unpaid trips for this owner</option>';
            trips.forEach(function (t) {
                opts += '<option value="' + t.TripId + '" data-net="' + t.NetPayable + '" data-paid="' + t.Paid + '" data-rem="' + t.Remaining + '">'
                    + 'Trip #' + t.TripId + ' — ' + t.VehicleNumber + ' — ' + t.FromLocation + ' → ' + t.ToLocation
                    + ' (Due: ₹' + parseFloat(t.Remaining).toFixed(0) + ')</option>';
            });
            $('#adj_Trip').html(opts).trigger('change');
        });
    new bootstrap.Modal('#adjModal').show();
}

function tripSelected() {
    var opt = $('#adj_Trip option:selected');
    if ($('#adj_Trip').val()) {
        var rem = parseFloat(opt.data('rem') || 0);
        $('#adj_tripNet').text('₹' + parseFloat(opt.data('net') || 0).toFixed(2));
        $('#adj_tripPaid').text('₹' + parseFloat(opt.data('paid') || 0).toFixed(2));
        $('#adj_tripDue').text('₹' + rem.toFixed(2));
        $('#adj_tripInfo').show();
        $('#adj_Amount').val(Math.min(window._adjRemaining, rem).toFixed(2));
    } else { $('#adj_tripInfo').hide(); }
}

function saveAdj() {
    var tripId = $('#adj_Trip').val();
    var amt = parseFloat($('#adj_Amount').val());
    if (!tripId) { Swal.fire({ icon: 'warning', title: 'Select a trip!', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false }); return; }
    if (!amt || amt <= 0) { Swal.fire({ icon: 'warning', title: 'Enter valid amount!', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false }); return; }
    if (amt > window._adjRemaining) { Swal.fire({ icon: 'warning', title: 'Exceeds available balance!', text: 'Max: ₹' + window._adjRemaining.toFixed(2) }); return; }

    Swal.fire({ title: 'Adjusting...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    var fd = new FormData();
    fd.append('adjustAdvance', 1); fd.append('OwnerAdvanceId', $('#adj_AdvId').val());
    fd.append('TripId', tripId); fd.append('OwnerId', $('#adj_OwnerId').val());
    fd.append('AdjustedAmount', amt); fd.append('AdjustmentDate', $('#adj_Date').val());
    fd.append('Remarks', $('#adj_Remarks').val());

    fetch('OwnerAdvance_manage.php', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
        Swal.close();
        if (res.status === 'success') {
            bootstrap.Modal.getInstance(document.getElementById('adjModal')).hide();
            Swal.fire({ icon: 'success', title: 'Adjusted! Remaining: ₹' + parseFloat(res.newRemaining).toFixed(2), timer: 3000, showConfirmButton: false });
            setTimeout(() => location.reload(), 2200);
        } else { Swal.fire({ icon: 'error', title: 'Error', text: res.msg }); }
    }).catch(() => Swal.fire({ icon: 'error', title: 'Server Error' }));
}

function viewAdj(advId, ownerName) {
    $('#adjHist_label').text(ownerName);
    $('#adjHistBody').html('<tr><td colspan="6" class="text-center py-2"><div class="spinner-border spinner-border-sm"></div></td></tr>');
    new bootstrap.Modal('#adjHistModal').show();
    fetch('OwnerAdvance_manage.php?getAdjustments=1&AdvanceId=' + advId)
        .then(r => r.json()).then(rows => {
            var html = '', total = 0;
            if (!rows.length) { html = '<tr><td colspan="6" class="text-center text-muted py-3">No adjustments yet</td></tr>'; }
            rows.forEach(function (r, i) {
                total += parseFloat(r.AdjustedAmount || 0);
                html += '<tr><td>' + (i + 1) + '</td><td>' + r.AdjustmentDate + '</td>'
                    + '<td><b>Trip #' + r.TripId + '</b></td>'
                    + '<td style="font-size:11px">' + (r.VehicleNumber || '') + ' ' + (r.FromLocation || '') + ' → ' + (r.ToLocation || '') + '</td>'
                    + '<td class="fw-bold text-success">₹' + parseFloat(r.AdjustedAmount).toFixed(2) + '</td>'
                    + '<td><small class="text-muted">' + (r.Remarks || '—') + '</small></td></tr>';
            });
            $('#adjHistBody').html(html);
            $('#adjHistTotal').text('₹' + total.toFixed(2));
        });
}
</script>

<?php require_once "../layout/footer.php"; ?>
