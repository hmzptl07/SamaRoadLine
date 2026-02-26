<?php
/* ================================================================
   OwnerPayment_manage.php  —  Vehicle Owner Trip-wise Payment
   UI only: session, auth, thin AJAX wrappers, HTML/JS
================================================================ */
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

require_once "../../businessLogics/Admin.php";
require_once "../../businessLogics/OwnerPayment.php";
Admin::checkAuth();

/* ── AJAX: GET PAYMENTS FOR A TRIP ── */
if (isset($_GET['getTripPayments'])) {
    header('Content-Type: application/json');
    echo json_encode(OwnerPayment::getByTrip(intval($_GET['TripId'])));
    exit();
}

/* ── AJAX: ADD PAYMENT ── */
if (isset($_POST['addPayment'])) {
    header('Content-Type: application/json');
    $tripId  = intval($_POST['TripId']);
    $ownerId = intval($_POST['OwnerId']);
    echo json_encode(OwnerPayment::addPayment($tripId, $ownerId, $_POST));
    exit();
}

/* ── AJAX: DELETE PAYMENT ── */
if (isset($_POST['deletePayment'])) {
    header('Content-Type: application/json');
    echo json_encode(OwnerPayment::deletePayment(intval($_POST['PaymentId'])));
    exit();
}

/* ── PAGE DATA ── */
$filterOwner = !empty($_GET['ownerId']) ? intval($_GET['ownerId']) : null;
$trips       = OwnerPayment::getAllTripsWithPaymentStatus($filterOwner);
$ownerSummary = OwnerPayment::getOwnerSummary();
$owners      = OwnerPayment::getOwners();

/* ── TOTALS ── */
$totalPayable  = array_sum(array_column($trips, 'NetPayable'));
$totalPaid     = array_sum(array_column($trips, 'TotalPaid'));
$totalRemaining = array_sum(array_column($trips, 'Remaining'));
$unpaidCount   = count(array_filter($trips, fn($t) => $t['OwnerPaymentStatus'] !== 'Paid'));

require_once "../layout/header.php";
require_once "../layout/sidebar.php";
?>

<style>
.ow-stat { border-radius: 12px; border: none; }
.ow-stat .icon-box { width: 46px; height: 46px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.pay-progress { height: 6px; border-radius: 3px; }
.trip-row-paid { background: #f0fdf4 !important; }
.trip-row-partial { background: #fffbeb !important; }
.trip-row-unpaid { background: #fff5f5 !important; }
.owner-chip { font-size: 11px; background: #ede9fe; color: #5b21b6; border-radius: 20px; padding: 2px 10px; }
.badge-mode { font-size: 10px; font-weight: 600; }
</style>

<div class="main-content app-content">
<div class="container-fluid">

<!-- ── PAGE HEADER ── -->
<div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h5 class="fw-bold mb-1">
            <i class="ri-truck-line me-2 text-primary"></i>Owner Freight Payment
        </h5>
        <p class="text-muted fs-12 mb-0">Trip-wise freight payment management for vehicle owners</p>
        <div class="alert alert-info border-0 py-2 px-3 mt-2 mb-0 fs-12" style="background:#eff6ff;border-left:3px solid #3b82f6!important;border-radius:6px">
            <i class="ri-information-line me-1"></i>
            <strong>Note:</strong> Jis trip mein <code>Owner Payment = Paid Directly</code> hai wo yahan nahi dikhta — 
            us trip ka sirf <strong>Commission Recovery</strong> karna hai 
            (<a href="CommissionTrack.php" class="fw-bold">Commission Tracker</a> mein dekho).
        </div>
    </div>
    <!-- Owner Filter -->
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <select id="ownerFilter" class="form-select form-select-sm" style="min-width:200px" onchange="filterByOwner(this.value)">
            <option value="">All Owners</option>
            <?php foreach ($owners as $o): ?>
            <option value="<?= $o['VehicleOwnerId'] ?>" <?= $filterOwner == $o['VehicleOwnerId'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($o['OwnerName']) ?> <?= $o['City'] ? "— {$o['City']}" : '' ?>
            </option>
            <?php endforeach; ?>
        </select>
        <a href="OwnerPayment_manage.php" class="btn btn-sm btn-outline-secondary"><i class="ri-refresh-line"></i></a>
    </div>
</div>

<!-- ── STAT CARDS ── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card ow-stat shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="icon-box bg-primary bg-opacity-10">🚛</div>
                    <div class="text-muted fs-12">Total Trips</div>
                </div>
                <div class="fw-bold fs-22"><?= count($trips) ?></div>
                <div class="fs-11 text-danger mt-1"><?= $unpaidCount ?> pending payment</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card ow-stat shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="icon-box bg-info bg-opacity-10">💼</div>
                    <div class="text-muted fs-12">Total Payable</div>
                </div>
                <div class="fw-bold fs-18 text-info">₹<?= number_format($totalPayable, 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card ow-stat shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="icon-box bg-success bg-opacity-10">✅</div>
                    <div class="text-muted fs-12">Total Paid</div>
                </div>
                <div class="fw-bold fs-18 text-success">₹<?= number_format($totalPaid, 0) ?></div>
                <div class="progress pay-progress mt-2">
                    <div class="progress-bar bg-success" style="width:<?= $totalPayable > 0 ? min(100, round($totalPaid / $totalPayable * 100)) : 0 ?>%"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card ow-stat shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="icon-box bg-danger bg-opacity-10">⏳</div>
                    <div class="text-muted fs-12">Still Due</div>
                </div>
                <div class="fw-bold fs-18 text-danger">₹<?= number_format($totalRemaining, 0) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- ── OWNER SUMMARY CARDS (collapsible) ── -->
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex align-items-center justify-content-between py-2" role="button" data-bs-toggle="collapse" data-bs-target="#ownerSumCollapse">
        <span class="fw-semibold fs-13"><i class="ri-user-star-line me-2 text-purple"></i>Owner-wise Summary</span>
        <i class="ri-arrow-down-s-line"></i>
    </div>
    <div class="collapse show" id="ownerSumCollapse">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 fs-12">
                    <thead class="table-light">
                        <tr>
                            <th>Owner</th><th>Mobile</th><th>Trips</th>
                            <th class="text-end">Payable</th><th class="text-end">Paid</th><th class="text-end">Remaining</th>
                            <th>Filter</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ownerSummary as $os):
                        $rem = max(0, floatval($os['TotalPayable']) - floatval($os['TotalPaid']));
                        $pct = floatval($os['TotalPayable']) > 0 ? min(100, round(floatval($os['TotalPaid']) / floatval($os['TotalPayable']) * 100)) : 0;
                    ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($os['OwnerName']) ?>
                                <?php if ($os['City']): ?><br><small class="text-muted"><?= htmlspecialchars($os['City']) ?></small><?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($os['MobileNo'] ?? '—') ?></td>
                            <td><span class="badge bg-secondary"><?= $os['TotalTrips'] ?? 0 ?></span></td>
                            <td class="text-end fw-semibold">₹<?= number_format($os['TotalPayable'] ?? 0, 0) ?></td>
                            <td class="text-end text-success fw-semibold">₹<?= number_format($os['TotalPaid'] ?? 0, 0) ?></td>
                            <td class="text-end fw-bold <?= $rem > 0 ? 'text-danger' : 'text-success' ?>">₹<?= number_format($rem, 0) ?></td>
                            <td>
                                <a href="?ownerId=<?= $os['VehicleOwnerId'] ?>" class="btn btn-xs btn-outline-primary py-0 px-2 fs-11">
                                    <i class="ri-filter-line"></i> Filter
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ── TRIPS TABLE ── -->
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between py-2">
        <span class="fw-semibold fs-13"><i class="ri-list-check me-2 text-primary"></i>Trip-wise Payment Details</span>
        <div class="d-flex gap-1">
            <button class="btn btn-xs btn-outline-secondary py-1 px-2 fs-11" onclick="filterTable('all')">All</button>
            <button class="btn btn-xs btn-outline-danger py-1 px-2 fs-11" onclick="filterTable('Unpaid')">Unpaid</button>
            <button class="btn btn-xs btn-outline-warning py-1 px-2 fs-11" onclick="filterTable('PartiallyPaid')">Partial</button>
            <button class="btn btn-xs btn-outline-success py-1 px-2 fs-11" onclick="filterTable('Paid')">Paid</button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tripPayTable" class="table table-bordered table-hover mb-0 fs-13">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Vehicle / Owner</th>
                        <th>Route</th>
                        <th>Type</th>
                        <th class="text-end">Freight</th>
                        <th class="text-end">Net Payable</th>
                        <th>Paid</th>
                        <th class="text-end">Remaining</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($trips as $i => $t):
                    $rowCls = match($t['OwnerPaymentStatus']) {
                        'Paid'          => 'trip-row-paid',
                        'PartiallyPaid' => 'trip-row-partial',
                        default         => 'trip-row-unpaid'
                    };
                    $stBadge = match($t['OwnerPaymentStatus']) {
                        'Paid'          => '<span class="badge bg-success">Paid</span>',
                        'PartiallyPaid' => '<span class="badge bg-warning text-dark">Partial</span>',
                        default         => '<span class="badge bg-danger">Unpaid</span>'
                    };
                    $pct = $t['NetPayable'] > 0 ? min(100, round($t['TotalPaid'] / $t['NetPayable'] * 100)) : 0;
                    $party = $t['TripType'] === 'Agent' ? ($t['AgentName'] ?? '—') : ($t['ConsignerName'] ?? '—');
                ?>
                    <tr class="<?= $rowCls ?>" data-status="<?= $t['OwnerPaymentStatus'] ?>">
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td style="white-space:nowrap"><?= date('d-m-Y', strtotime($t['TripDate'])) ?></td>
                        <td>
                            <span class="badge bg-secondary"><?= htmlspecialchars($t['VehicleNumber'] ?? '—') ?></span><br>
                            <span class="owner-chip mt-1 d-inline-block"><?= htmlspecialchars($t['OwnerName'] ?? '—') ?></span>
                        </td>
                        <td style="font-size:11px; max-width:140px">
                            <?= htmlspecialchars($t['FromLocation']) ?> → <?= htmlspecialchars($t['ToLocation']) ?>
                            <br><small class="text-muted"><?= htmlspecialchars($party) ?></small>
                        </td>
                        <td>
                            <span class="badge <?= $t['TripType'] === 'Agent' ? 'bg-warning text-dark' : 'bg-primary' ?>">
                                <?= $t['TripType'] ?>
                            </span>
                            <?php if ($t['FreightPaymentToOwnerStatus'] === 'PaidDirectly'): ?>
                            <br><span class="badge bg-info text-dark mt-1" style="font-size:9px">Direct</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-semibold">₹<?= number_format($t['FreightAmount'], 0) ?></td>
                        <td class="text-end fw-bold text-primary">₹<?= number_format($t['NetPayable'], 0) ?></td>
                        <td>
                            <div class="text-success fw-semibold fs-12">₹<?= number_format($t['TotalPaid'], 0) ?></div>
                            <div class="progress pay-progress mt-1" style="min-width:60px">
                                <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                            </div>
                            <div class="fs-11 text-muted"><?= $pct ?>% (<?= $t['PaymentCount'] ?> entries)</div>
                        </td>
                        <td class="text-end fw-bold <?= $t['Remaining'] > 0 ? 'text-danger' : 'text-success' ?>">
                            ₹<?= number_format($t['Remaining'], 0) ?>
                        </td>
                        <td><?= $stBadge ?></td>
                        <td class="text-center" style="white-space:nowrap">
                            <?php if ($t['OwnerPaymentStatus'] !== 'Paid'): ?>
                            <button class="btn btn-sm btn-success mb-1"
                                onclick="openPayModal(
                                    <?= $t['TripId'] ?>,
                                    <?= $t['VehicleOwnerId'] ?>,
                                    '<?= addslashes($t['VehicleNumber'] ?? '—') ?>',
                                    '<?= addslashes($t['OwnerName'] ?? '—') ?>',
                                    <?= $t['NetPayable'] ?>,
                                    <?= $t['TotalPaid'] ?>
                                )">
                                <i class="ri-add-circle-line me-1"></i>Pay
                            </button><br>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-info"
                                onclick="viewHistory(<?= $t['TripId'] ?>, '<?= addslashes($t['VehicleNumber'] ?? '—') ?>', '<?= addslashes($t['OwnerName'] ?? '') ?>')">
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

<!-- ════ PAY MODAL ════ -->
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white py-2">
                <h5 class="modal-title fs-14 fw-bold"><i class="ri-money-dollar-circle-line me-2"></i>Add Owner Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Summary -->
                <div class="rounded border p-3 mb-3 bg-light">
                    <div class="row g-0 text-center">
                        <div class="col-4 border-end">
                            <div class="fs-11 text-muted">Trip</div>
                            <div class="fw-bold fs-12" id="pm_trip">—</div>
                        </div>
                        <div class="col-4 border-end">
                            <div class="fs-11 text-muted">Owner</div>
                            <div class="fw-bold fs-12" id="pm_owner">—</div>
                        </div>
                        <div class="col-4">
                            <div class="fs-11 text-muted">Due</div>
                            <div class="fw-bold fs-16 text-danger" id="pm_due">₹0</div>
                        </div>
                    </div>
                    <div class="progress pay-progress mt-2">
                        <div class="progress-bar bg-success" id="pm_prog" style="width:0%"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <small class="text-muted">Paid: <b id="pm_paid">₹0</b></small>
                        <small class="text-muted">Total: <b id="pm_total">₹0</b></small>
                    </div>
                </div>

                <input type="hidden" id="pay_TripId">
                <input type="hidden" id="pay_OwnerId">
                <input type="hidden" id="pay_Net">

                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label fw-medium fs-13">Date <span class="text-danger">*</span></label>
                        <input type="date" id="pay_Date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-medium fs-13">Amount (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text fw-bold">₹</span>
                            <input type="number" id="pay_Amount" class="form-control fw-bold" step="0.01" min="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-medium fs-13">Mode</label>
                        <select id="pay_Mode" class="form-select">
                            <option value="Cash">💵 Cash</option>
                            <option value="Cheque">📋 Cheque</option>
                            <option value="NEFT">🏦 NEFT</option>
                            <option value="RTGS">🏦 RTGS</option>
                            <option value="UPI">📱 UPI</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-medium fs-13">Reference / Cheque No.</label>
                        <input type="text" id="pay_Ref" class="form-control" placeholder="Optional">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium fs-13">Remarks</label>
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
        </div>
    </div>
</div>

<!-- ════ HISTORY MODAL ════ -->
<div class="modal fade" id="histModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white py-2">
                <h5 class="modal-title fs-14"><i class="ri-history-line me-2"></i>Payment History — <span id="hist_label"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-bordered table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>#</th><th>Date</th><th>Mode</th><th>Reference</th><th>Amount</th><th>Remarks</th><th>Del</th></tr>
                    </thead>
                    <tbody id="histBody"></tbody>
                    <tfoot>
                        <tr class="table-success">
                            <td colspan="4" class="text-end fw-bold">Total Paid:</td>
                            <td class="fw-bold" id="histTotal">₹0</td>
                            <td colspan="2"></td>
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
    $('#tripPayTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        language: { search: '', searchPlaceholder: 'Search trips...' }
    });
});

function filterByOwner(val) {
    window.location.href = val ? '?ownerId=' + val : '?';
}

function filterTable(status) {
    var dt = $('#tripPayTable').DataTable();
    if (status === 'all') { dt.column(9).search('').draw(); }
    else { dt.column(9).search(status).draw(); }
}

function openPayModal(tripId, ownerId, vehicle, owner, net, paid) {
    var rem = Math.max(0, net - paid);
    var pct = net > 0 ? Math.min(100, Math.round(paid / net * 100)) : 0;
    $('#pay_TripId').val(tripId);
    $('#pay_OwnerId').val(ownerId);
    $('#pay_Net').val(net);
    $('#pm_trip').text('Trip #' + tripId + ' — ' + vehicle);
    $('#pm_owner').text(owner);
    $('#pm_due').text('₹' + rem.toFixed(2));
    $('#pm_paid').text('₹' + paid.toFixed(2));
    $('#pm_total').text('₹' + net.toFixed(2));
    $('#pm_prog').css('width', pct + '%');
    $('#pay_Amount').val(rem > 0 ? rem.toFixed(2) : '');
    $('#pay_Date').val('<?= date('Y-m-d') ?>');
    $('#pay_Mode').val('Cash');
    $('#pay_Ref, #pay_Remarks').val('');
    new bootstrap.Modal('#payModal').show();
}

function submitPay() {
    var amt = parseFloat($('#pay_Amount').val());
    if (!amt || amt <= 0) {
        Swal.fire({ icon: 'warning', title: 'Enter valid amount!', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false });
        return;
    }
    Swal.fire({ title: 'Saving...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    var fd = new FormData();
    fd.append('addPayment', 1);
    fd.append('TripId', $('#pay_TripId').val());
    fd.append('OwnerId', $('#pay_OwnerId').val());
    fd.append('PaymentDate', $('#pay_Date').val());
    fd.append('Amount', amt);
    fd.append('PaymentMode', $('#pay_Mode').val());
    fd.append('ReferenceNo', $('#pay_Ref').val());
    fd.append('Remarks', $('#pay_Remarks').val());

    fetch('OwnerPayment_manage.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            Swal.close();
            if (res.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('payModal')).hide();
                Swal.fire({ icon: 'success', title: 'Payment Saved!', toast: true, position: 'top-end', showConfirmButton: false, timer: 2500 });
                setTimeout(() => location.reload(), 1800);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.msg });
            }
        }).catch(() => Swal.fire({ icon: 'error', title: 'Server Error' }));
}

function viewHistory(tripId, vehicle, owner) {
    $('#hist_label').text('Trip #' + tripId + ' — ' + vehicle + ' (' + owner + ')');
    $('#histBody').html('<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>');
    new bootstrap.Modal('#histModal').show();

    fetch('OwnerPayment_manage.php?getTripPayments=1&TripId=' + tripId)
        .then(r => r.json()).then(rows => {
            var html = '', total = 0;
            var icons = { Cash: '💵', Cheque: '📋', NEFT: '🏦', RTGS: '🏦', UPI: '📱', Other: '💳' };
            if (!rows.length) {
                html = '<tr><td colspan="7" class="text-center text-muted py-3">No payments yet</td></tr>';
            }
            rows.forEach(function (p, i) {
                total += parseFloat(p.Amount || 0);
                html += '<tr id="pr-' + p.OwnerPaymentId + '">'
                    + '<td>' + (i + 1) + '</td>'
                    + '<td>' + p.PaymentDate + '</td>'
                    + '<td>' + (icons[p.PaymentMode] || '') + ' ' + p.PaymentMode + '</td>'
                    + '<td><small>' + (p.ReferenceNo || '—') + '</small></td>'
                    + '<td class="fw-bold text-success">₹' + parseFloat(p.Amount).toFixed(2) + '</td>'
                    + '<td><small>' + (p.Remarks || '—') + '</small></td>'
                    + '<td><button class="btn btn-sm btn-outline-danger" onclick="delPayment(' + p.OwnerPaymentId + ')">'
                    + '<i class="ri-delete-bin-line"></i></button></td></tr>';
            });
            $('#histBody').html(html);
            $('#histTotal').text('₹' + total.toFixed(2));
        });
}

function delPayment(pid) {
    Swal.fire({ title: 'Delete payment?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#dc3545' })
        .then(r => {
            if (!r.isConfirmed) return;
            var fd = new FormData();
            fd.append('deletePayment', 1);
            fd.append('PaymentId', pid);
            fetch('OwnerPayment_manage.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(res => {
                    if (res.status === 'success') {
                        $('#pr-' + pid).fadeOut(300, function () { $(this).remove(); });
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.msg });
                    }
                });
        });
}
</script>

<?php require_once "../layout/footer.php"; ?>
