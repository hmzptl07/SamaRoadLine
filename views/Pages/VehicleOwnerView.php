<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once "../../businessLogics/Admin.php";
require_once "../../businessLogics/VehicleOwner.php";
require_once "../../businessLogics/OwnerAdvance.php";
require_once "../../config/database.php";
Admin::checkAuth();

/* ══ AJAX — Save / Update ══ */
if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $isEdit = !empty($_POST['VehicleOwnerId']);
    $errors = [];
    if (empty(trim($_POST['OwnerName'] ?? '')))
        $errors[] = 'Owner Name is required.';
    if (!empty($_POST['MobileNo']) && !preg_match('/^[6-9]\d{9}$/', trim($_POST['MobileNo'])))
        $errors[] = 'Invalid mobile number (10 digits, starts 6-9).';
    if (!empty($_POST['AlternateMobile']) && !preg_match('/^[6-9]\d{9}$/', trim($_POST['AlternateMobile'])))
        $errors[] = 'Invalid alternate mobile number.';
    if (!empty($_POST['IFSC']) && !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/i', trim($_POST['IFSC'])))
        $errors[] = 'Invalid IFSC code (e.g. SBIN0001234).';
    if ($errors) { echo json_encode(['status'=>'error','message'=>implode('<br>',$errors)]); exit(); }

    $res = $isEdit
        ? VehicleOwner::update($_POST['VehicleOwnerId'], $_POST)
        : VehicleOwner::insert($_POST);

    $row = null;
    if ($res) {
        $id  = $isEdit ? $_POST['VehicleOwnerId'] : $pdo->lastInsertId();
        $row = VehicleOwner::getById($id);
    }
    echo json_encode(['status'=>$res?'success':'error','row'=>$row]);
    exit();
}

/* ══ AJAX — Toggle Status ══ */
if (isset($_POST['toggle'])) {
    header('Content-Type: application/json');
    $ns = ($_POST['status']==='Yes') ? 'No' : 'Yes';
    $ok = VehicleOwner::changeStatus($_POST['id'], $ns);
    echo json_encode(['status'=>$ok?'success':'error','newStatus'=>$ns]);
    exit();
}

/* ══ AJAX — Get Owner Detail (advance history + payment summary) ══ */
if (isset($_POST['get_detail'])) {
    header('Content-Type: application/json');
    $oid = intval($_POST['owner_id']);

    // Last 5 advances
    $advStmt = $pdo->prepare("
        SELECT OwnerAdvanceId, AdvanceDate, Amount, RemainingAmount, AdjustedAmount, Status, PaymentMode
        FROM owneradvance WHERE OwnerId=? ORDER BY OwnerAdvanceId DESC LIMIT 5");
    $advStmt->execute([$oid]);
    $advances = $advStmt->fetchAll(PDO::FETCH_ASSOC);

    // Payment summary — total trips, paid, pending
    $payStmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT t.TripId) AS TotalTrips,
            COALESCE(SUM(GREATEST(0, t.FreightAmount - t.AdvanceAmount - t.TDS)), 0) AS TotalPayable,
            COALESCE(SUM(op.Amount), 0) AS TotalPaid,
            SUM(CASE WHEN t.OwnerPaymentStatus='Paid' THEN 1 ELSE 0 END) AS PaidTrips,
            SUM(CASE WHEN t.OwnerPaymentStatus!='Paid' THEN 1 ELSE 0 END) AS PendingTrips
        FROM TripMaster t
        JOIN VehicleMaster v ON t.VehicleId = v.VehicleId
        LEFT JOIN ownerpayment op ON t.TripId = op.TripId
        WHERE v.VehicleOwnerId = ?
          AND (t.FreightPaymentToOwnerStatus IS NULL OR t.FreightPaymentToOwnerStatus='Pending')
        GROUP BY v.VehicleOwnerId");
    $payStmt->execute([$oid]);
    $pay = $payStmt->fetch(PDO::FETCH_ASSOC) ?: ['TotalTrips'=>0,'TotalPayable'=>0,'TotalPaid'=>0,'PaidTrips'=>0,'PendingTrips'=>0];

    // Advance summary
    $advSumStmt = $pdo->prepare("
        SELECT COALESCE(SUM(Amount),0) AS Total, COALESCE(SUM(RemainingAmount),0) AS Remaining
        FROM owneradvance WHERE OwnerId=?");
    $advSumStmt->execute([$oid]);
    $advSum = $advSumStmt->fetch(PDO::FETCH_ASSOC);

    // Recoverable Commission from Owner (PaidDirectly trips)
    $commStmt = $pdo->prepare("
        SELECT
            tc.TripCommissionId, tc.CommissionAmount, tc.CommissionStatus,
            t.TripDate, t.FromLocation, t.ToLocation, t.FreightAmount,
            v.VehicleNumber
        FROM TripCommission tc
        JOIN TripMaster t        ON tc.TripId   = t.TripId
        JOIN VehicleMaster v     ON t.VehicleId = v.VehicleId
        WHERE v.VehicleOwnerId   = ?
          AND tc.RecoveryFrom    = 'Owner'
        ORDER BY tc.CommissionStatus ASC, t.TripDate DESC
        LIMIT 10");
    $commStmt->execute([$oid]);
    $commissions = $commStmt->fetchAll(PDO::FETCH_ASSOC);

    // Commission summary
    $commSumStmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(tc.CommissionAmount), 0) AS Total,
            COALESCE(SUM(CASE WHEN tc.CommissionStatus='Pending'  THEN tc.CommissionAmount ELSE 0 END), 0) AS Pending,
            COALESCE(SUM(CASE WHEN tc.CommissionStatus='Received' THEN tc.CommissionAmount ELSE 0 END), 0) AS Received,
            SUM(CASE WHEN tc.CommissionStatus='Pending'  THEN 1 ELSE 0 END) AS PendingCount,
            SUM(CASE WHEN tc.CommissionStatus='Received' THEN 1 ELSE 0 END) AS ReceivedCount
        FROM TripCommission tc
        JOIN TripMaster t    ON tc.TripId   = t.TripId
        JOIN VehicleMaster v ON t.VehicleId = v.VehicleId
        WHERE v.VehicleOwnerId = ?
          AND tc.RecoveryFrom  = 'Owner'");
    $commSumStmt->execute([$oid]);
    $commSum = $commSumStmt->fetch(PDO::FETCH_ASSOC) ?: ['Total'=>0,'Pending'=>0,'Received'=>0,'PendingCount'=>0,'ReceivedCount'=>0];

    echo json_encode([
        'advances'        => $advances,
        'payment'         => $pay,
        'advanceSummary'  => $advSum,
        'commissions'     => $commissions,
        'commSummary'     => $commSum,
    ]);
    exit();
}

$owners = VehicleOwner::getAll();
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
.action-btn-group{display:flex;gap:4px;flex-wrap:nowrap;}
.btn-icon{width:30px;height:30px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:7px;font-size:14px;}
.req{color:#ef4444;margin-left:2px;}
.form-section-head{font-size:11px;font-weight:700;color:#1a237e;text-transform:uppercase;letter-spacing:1px;border-left:3px solid #1a237e;padding-left:8px;margin:6px 0 14px;}

/* Detail Modal */
.detail-tab-btn{border:1.5px solid #e2e8f0;border-radius:8px;padding:6px 14px;font-size:12px;font-weight:600;cursor:pointer;background:#f8fafc;color:#64748b;transition:.15s;}
.detail-tab-btn.active{background:#1a237e;color:#fff;border-color:#1a237e;}
.detail-tab-content{display:none;}
.detail-tab-content.active{display:block;}
.detail-row{display:flex;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:13px;}
.detail-row:last-child{border-bottom:none;}
.detail-label{width:130px;flex-shrink:0;color:#64748b;font-weight:600;font-size:11.5px;text-transform:uppercase;}
.detail-value{color:#1e293b;font-weight:500;flex:1;}
.adv-row{display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:#f8fafc;border-radius:8px;margin-bottom:6px;font-size:12.5px;}
.pay-mini-card{border-radius:10px;padding:12px;text-align:center;flex:1;}
.pay-mini-val{font-size:18px;font-weight:800;line-height:1;}
.pay-mini-lbl{font-size:10px;color:#64748b;margin-top:3px;}
</style>

<div class="main-content app-content">
<div class="container-fluid">

<!-- ══ Page Header ══ -->
<div class="page-header-card">
    <div>
        <div class="ph-title"><i class="ri-user-star-line"></i> Vehicle Owner Master</div>
        <div class="ph-sub">Manage vehicle owners, bank details and payment tracking</div>
    </div>
    <button class="btn btn-warning fw-bold px-4" onclick="openAddModal()" style="border-radius:9px;height:38px;font-size:13px;">
        <i class="ri-add-circle-line me-1"></i> Add New Owner
    </button>
</div>

<!-- ══ Stats ══ -->
<?php
$total    = count($owners);
$active   = count(array_filter($owners, fn($o) => $o['IsActive']==='Yes'));
$inactive = $total - $active;
$withBank = count(array_filter($owners, fn($o) => !empty($o['AccountNo'])));
?>
<div class="stats-bar">
    <div class="stat-pill"><div class="sp-icon" style="background:#e0e7ff;"><i class="ri-user-star-line" style="color:#1a237e;"></i></div><div><div class="sp-val"><?=$total?></div><div class="sp-lbl">Total Owners</div></div></div>
    <div class="stat-pill"><div class="sp-icon" style="background:#dcfce7;"><i class="ri-checkbox-circle-line" style="color:#16a34a;"></i></div><div><div class="sp-val"><?=$active?></div><div class="sp-lbl">Active</div></div></div>
    <div class="stat-pill"><div class="sp-icon" style="background:#fee2e2;"><i class="ri-close-circle-line" style="color:#dc2626;"></i></div><div><div class="sp-val"><?=$inactive?></div><div class="sp-lbl">Inactive</div></div></div>
    <div class="stat-pill"><div class="sp-icon" style="background:#fef9c3;"><i class="ri-bank-line" style="color:#ca8a04;"></i></div><div><div class="sp-val"><?=$withBank?></div><div class="sp-lbl">With Bank</div></div></div>
</div>

<!-- ══ Filter ══ -->
<div class="filter-card">
    <div class="row g-3 align-items-end">
        <div class="col-md-2">
            <label class="form-label fw-semibold fs-12 mb-1"><i class="ri-toggle-line me-1"></i>Status</label>
            <select id="filterStatus" class="form-select form-select-sm">
                <option value="">-- All Status --</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button class="btn btn-outline-secondary btn-sm w-100" onclick="clearFilters()" title="Clear filters"><i class="ri-refresh-line"></i></button>
        </div>
        <div class="col-md-4 ms-auto">
            <label class="form-label fw-semibold fs-12 mb-1"><i class="ri-search-line me-1"></i>Search</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0" style="border-radius:8px 0 0 8px;"><i class="ri-search-line text-muted"></i></span>
                <input type="text" id="customSearch" class="form-control border-start-0 ps-1"
                    placeholder="Owner, Bank, City..."
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
            <table id="ownerTable" class="table table-hover align-middle mb-0 w-100">
                <thead style="background:#f8fafc;">
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>Owner Name</th>
                        <th>Mobile</th>
                        <th>City / State</th>
                        <th>Bank Details</th>
                        <th>UPI</th>
                        <th>Status</th>
                        <th style="width:110px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i=1; foreach($owners as $r): $a=$r['IsActive']==='Yes'; $rj=htmlspecialchars(json_encode($r),ENT_QUOTES); ?>
                <tr id="orow-<?=$r['VehicleOwnerId']?>">
                    <td class="text-muted fw-medium fs-13"><?=$i++?></td>
                    <td>
                        <div class="fw-bold" style="font-size:13.5px;"><?=htmlspecialchars($r['OwnerName'])?></div>
                        <?php if(!empty($r['AlternateMobile'])): ?>
                        <div class="text-muted" style="font-size:11px;"><i class="ri-phone-line"></i> Alt: <?=htmlspecialchars($r['AlternateMobile'])?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if(!empty($r['MobileNo'])): ?>
                        <a href="tel:<?=$r['MobileNo']?>" class="text-dark text-decoration-none" style="font-size:13px;">
                            <i class="ri-phone-line text-success me-1" style="font-size:12px;"></i><?=htmlspecialchars($r['MobileNo'])?>
                        </a>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td>
                        <div style="font-size:13px;"><?=htmlspecialchars($r['City']??'—')?></div>
                        <?php if(!empty($r['State'])): ?><div class="text-muted" style="font-size:11px;"><?=htmlspecialchars($r['State'])?></div><?php endif; ?>
                    </td>
                    <td>
                        <?php if(!empty($r['AccountNo'])): ?>
                        <div style="font-size:12px;"><i class="ri-bank-line me-1 text-primary"></i><strong><?=htmlspecialchars($r['BankName']??'')?></strong></div>
                        <div class="text-muted" style="font-size:11px;">A/C: <?=htmlspecialchars($r['AccountNo'])?> &nbsp;|&nbsp; <?=htmlspecialchars($r['IFSC']??'')?></div>
                        <?php else: ?><span class="badge bg-light text-muted border">Not Added</span><?php endif; ?>
                    </td>
                    <td style="font-size:12px;"><?=!empty($r['UPI'])?htmlspecialchars($r['UPI']):'<span class="text-muted">—</span>'?></td>
                    <td>
                        <span class="badge rounded-pill <?=$a?'bg-success':'bg-danger'?>">
                            <i class="<?=$a?'ri-checkbox-circle-line':'ri-close-circle-line'?> me-1"></i>
                            <?=$a?'Active':'Inactive'?>
                        </span>
                    </td>
                    <td>
                        <div class="action-btn-group">
                            <button class="btn btn-sm btn-outline-info btn-icon"
                                onclick='viewDetail(<?=$rj?>)'
                                title="View Details"><i class="ri-eye-line"></i></button>
                            <button class="btn btn-sm btn-warning btn-icon"
                                onclick='editOwner(<?=$rj?>)'
                                title="Edit"><i class="ri-edit-line"></i></button>
                            <button class="btn btn-sm <?=$a?'btn-danger':'btn-success'?> btn-icon"
                                onclick="toggleStatus(this)"
                                data-id="<?=$r['VehicleOwnerId']?>"
                                data-status="<?=$r['IsActive']?>"
                                data-name="<?=htmlspecialchars($r['OwnerName'])?>"
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
<div class="modal fade" id="ownerModal" tabindex="-1" data-bs-backdrop="static">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 20px 60px rgba(0,0,0,.15);">
    <div class="modal-header" style="background:linear-gradient(135deg,#1a237e,#1d4ed8);border-radius:16px 16px 0 0;padding:18px 24px;">
        <h5 class="modal-title text-white fw-bold" id="ownerModalTitle"><i class="ri-user-star-line me-2"></i>Add Vehicle Owner</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <form id="ownerForm" novalidate>
        <input type="hidden" name="ajax" value="1">
        <input type="hidden" name="VehicleOwnerId" id="VehicleOwnerId">
        <div class="modal-body p-4">

            <!-- Basic Info -->
            <div class="form-section-head"><i class="ri-user-line me-1"></i>Owner Information</div>
            <div class="row g-3 mb-1">
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-13">Owner Name <span class="req">*</span></label>
                    <input type="text" name="OwnerName" id="f_OwnerName" class="form-control" placeholder="Full owner name" data-uppercase>
                    <div class="srv-feedback invalid-feedback"></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-13">Mobile No</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="ri-phone-line"></i></span>
                        <input type="text" name="MobileNo" id="f_MobileNo" class="form-control" placeholder="10 digits mobile" maxlength="10" data-mobile>
                    </div>
                    <div class="srv-feedback invalid-feedback"></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-13">Alternate Mobile</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="ri-phone-line"></i></span>
                        <input type="text" name="AlternateMobile" id="f_AlternateMobile" class="form-control" placeholder="Alternate number" maxlength="10" data-mobile>
                    </div>
                    <div class="srv-feedback invalid-feedback"></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-13">City</label>
                    <input type="text" name="City" id="f_City" class="form-control" placeholder="City">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-13">State</label>
                    <input type="text" name="State" id="f_State" class="form-control" placeholder="State">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-13">Status</label>
                    <select name="IsActive" id="f_IsActive" class="form-select">
                        <option value="Yes">✅ Active</option>
                        <option value="No">❌ Inactive</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-semibold fs-13">Address</label>
                    <textarea name="Address" id="f_Address" class="form-control" rows="2" placeholder="Full address..."></textarea>
                </div>
            </div>

            <!-- Bank Details -->
            <div class="form-section-head mt-3"><i class="ri-bank-line me-1"></i>Bank & Payment Details</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-13">Bank Name</label>
                    <input type="text" name="BankName" id="f_BankName" class="form-control" placeholder="e.g. SBI, HDFC">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-13">Account No</label>
                    <input type="text" name="AccountNo" id="f_AccountNo" class="form-control" placeholder="Account number" data-numeric>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-13">IFSC Code</label>
                    <input type="text" name="IFSC" id="f_IFSC" class="form-control" placeholder="e.g. SBIN0001234" maxlength="11" data-uppercase>
                    <div class="srv-feedback invalid-feedback"></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-13">UPI ID</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light" style="font-size:12px;">UPI</span>
                        <input type="text" name="UPI" id="f_UPI" class="form-control" placeholder="mobile@upi or name@bank">
                    </div>
                </div>
            </div>

        </div>
        <div class="modal-footer" style="border-top:1px solid #e2e8f0;padding:14px 24px;">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="ri-close-line me-1"></i>Cancel</button>
            <button type="submit" class="btn btn-primary px-4" id="ownerSaveBtn"><i class="ri-save-line me-1"></i>Save Owner</button>
        </div>
    </form>
</div>
</div>
</div>

<!-- ══════════════════════════════════
     DETAIL MODAL — with tabs
══════════════════════════════════ -->
<div class="modal fade" id="detailModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered" style="max-width:540px;">
<div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 20px 60px rgba(0,0,0,.15);">
    <div class="modal-header" style="background:linear-gradient(135deg,#1a237e,#1d4ed8);border-radius:16px 16px 0 0;padding:18px 24px;">
        <h5 class="modal-title text-white fw-bold"><i class="ri-user-star-line me-2"></i>Owner Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body p-4">

        <!-- Owner name + status header -->
        <div id="detailHeader" style="text-align:center;margin-bottom:16px;"></div>

        <!-- Tabs -->
        <div class="d-flex gap-2 mb-3 flex-wrap">
            <button class="detail-tab-btn active" onclick="switchTab('info',this)">👤 Info</button>
            <button class="detail-tab-btn" onclick="switchTab('bank',this)">🏦 Bank</button>
            <button class="detail-tab-btn" onclick="switchTab('payment',this)">💰 Payments</button>
            <button class="detail-tab-btn" onclick="switchTab('advance',this)">📋 Advances</button>
            <button class="detail-tab-btn" onclick="switchTab('commission',this)" id="commTabBtn">🔄 Commission</button>
        </div>

        <!-- Tab: Info -->
        <div class="detail-tab-content active" id="tab-info">
            <div id="infoContent" style="background:#f8fafc;border-radius:12px;padding:4px 16px;"></div>
        </div>

        <!-- Tab: Bank -->
        <div class="detail-tab-content" id="tab-bank">
            <div id="bankContent" style="background:#f8fafc;border-radius:12px;padding:4px 16px;"></div>
        </div>

        <!-- Tab: Payment Summary -->
        <div class="detail-tab-content" id="tab-payment">
            <div id="paymentContent">
                <div class="text-center py-3"><span class="spinner-border spinner-border-sm text-primary"></span> Loading...</div>
            </div>
        </div>

        <!-- Tab: Advance History -->
        <div class="detail-tab-content" id="tab-advance">
            <div id="advanceContent">
                <div class="text-center py-3"><span class="spinner-border spinner-border-sm text-primary"></span> Loading...</div>
            </div>
        </div>

        <!-- Tab: Commission (Recoverable from Owner) -->
        <div class="detail-tab-content" id="tab-commission">
            <div id="commissionContent">
                <div class="text-center py-3"><span class="spinner-border spinner-border-sm text-primary"></span> Loading...</div>
            </div>
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
var ownerDT;
let _currentOwner = null;
let _detailLoaded = false;

$(document).ready(function(){
    ownerDT = $('#ownerTable').DataTable({
        scrollX:true, pageLength:25,
        columnDefs:[{orderable:false,targets:[0,7]}],
        dom: 'rtip',  // hide default search — using custom
        language:{
            paginate:{previous:'‹',next:'›'},
            info:'Showing _START_–_END_ of _TOTAL_',
            emptyTable:'No owners found.'
        },
        drawCallback:function(){var i=this.api().page.info();$('#filterInfo').text(i.recordsDisplay+'/'+i.recordsTotal);}
    });
    $('#filterStatus').select2({theme:'bootstrap-5',allowClear:true,placeholder:'-- All Status --',width:'100%'});
    $('#filterStatus').on('change',function(){ownerDT.column(6).search($(this).val()||'').draw();});
    $('#customSearch').on('keyup input',function(){ownerDT.search($(this).val()).draw();});
});

function clearFilters(){
    $('#filterStatus').val(null).trigger('change');
    $('#customSearch').val('');
    ownerDT.search('').columns().search('').draw();
}

window.addEventListener('offline',()=>SRV.toast.warning('No internet!'));
window.addEventListener('online', ()=>SRV.toast.success('Back online!'));

function doAjax(body,cb){
    if(!navigator.onLine){Swal.fire({icon:'warning',title:'No Internet',text:'Check your connection.'});return;}
    Swal.fire({title:'Please wait...',allowOutsideClick:false,allowEscapeKey:false,didOpen:()=>Swal.showLoading()});
    fetch('',{method:'POST',body}).then(r=>r.json()).then(res=>{Swal.close();cb(res);})
    .catch(()=>Swal.fire({icon:'error',title:'Error',text:'Server error. Try again.'}));
}

function clearFormVal(){
    document.querySelectorAll('#ownerForm .is-invalid,#ownerForm .is-valid').forEach(el=>el.classList.remove('is-invalid','is-valid'));
    document.querySelectorAll('#ownerForm .srv-feedback').forEach(el=>{el.textContent='';el.style.display='none';});
}

/* ── Add Modal ── */
function openAddModal(){
    document.getElementById('ownerForm').reset();
    document.getElementById('VehicleOwnerId').value='';
    clearFormVal();
    document.getElementById('ownerModalTitle').innerHTML='<i class="ri-add-circle-line me-2"></i>Add New Owner';
    document.getElementById('ownerSaveBtn').innerHTML='<i class="ri-save-line me-1"></i>Save Owner';
    new bootstrap.Modal('#ownerModal').show();
    setTimeout(()=>document.getElementById('f_OwnerName').focus(),400);
}

/* ── Edit Modal ── */
function editOwner(d){
    document.getElementById('ownerForm').reset();
    clearFormVal();
    ['VehicleOwnerId','OwnerName','MobileNo','AlternateMobile','City','State','Address','IsActive','BankName','AccountNo','IFSC','UPI']
        .forEach(k=>{const el=document.getElementById('f_'+k)||document.querySelector(`#ownerForm [name="${k}"]`);if(el)el.value=d[k]??'';});
    document.getElementById('ownerModalTitle').innerHTML='<i class="ri-edit-line me-2"></i>Edit Owner';
    document.getElementById('ownerSaveBtn').innerHTML='<i class="ri-save-line me-1"></i>Update Owner';
    const dm=bootstrap.Modal.getInstance(document.getElementById('detailModal'));
    if(dm)dm.hide();
    setTimeout(()=>new bootstrap.Modal('#ownerModal').show(),200);
}

/* ── Form Submit ── */
document.getElementById('ownerForm').addEventListener('submit',function(e){
    e.preventDefault();
    const valid=SRV.validate('#ownerForm',{
        'f_OwnerName':       {required:[true,'Owner Name is required.'],minLength:[2,'Min 2 characters.']},
        'f_MobileNo':        {mobile:[true,'Enter valid 10 digits mobile.']},
        'f_AlternateMobile': {mobile:[true,'Enter valid alternate mobile.']},
        'f_IFSC':            {regex:['^[A-Z]{4}0[A-Z0-9]{6}$','Invalid IFSC code.']}
    });
    if(!valid)return;

    const isEdit=!!document.getElementById('VehicleOwnerId').value;
    const btn=document.getElementById('ownerSaveBtn');
    btn.disabled=true;
    btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

    doAjax(new FormData(this),function(res){
        btn.disabled=false;
        btn.innerHTML=isEdit?'<i class="ri-save-line me-1"></i>Update Owner':'<i class="ri-save-line me-1"></i>Save Owner';
        if(res.status==='success'){
            bootstrap.Modal.getInstance(document.getElementById('ownerModal')).hide();
            const r=res.row;
            const nameHtml=`<div class='fw-bold' style='font-size:13.5px;'>${r.OwnerName}</div>${r.AlternateMobile?`<div class='text-muted' style='font-size:11px;'>Alt: ${r.AlternateMobile}</div>`:''}`;
            const mobHtml=r.MobileNo?`<a href='tel:${r.MobileNo}' class='text-dark text-decoration-none'><i class='ri-phone-line text-success me-1'></i>${r.MobileNo}</a>`:'<span class="text-muted">—</span>';
            const cityHtml=(r.City||'—')+(r.State?`<br><small class='text-muted'>${r.State}</small>`:'');
            const bankHtml=r.AccountNo?`<div style='font-size:12px;'><i class='ri-bank-line me-1 text-primary'></i><strong>${r.BankName||''}</strong></div><div class='text-muted' style='font-size:11px;'>A/C: ${r.AccountNo} | ${r.IFSC||''}</div>`:'<span class="badge bg-light text-muted border">Not Added</span>';
            const upiHtml=r.UPI||'<span class="text-muted">—</span>';
            const statHtml=r.IsActive==='Yes'?"<span class='badge rounded-pill bg-success'><i class='ri-checkbox-circle-line me-1'></i>Active</span>":"<span class='badge rounded-pill bg-danger'><i class='ri-close-circle-line me-1'></i>Inactive</span>";
            const actHtml=buildActionBtns(r);

            if(isEdit){
                const tr=document.getElementById('orow-'+r.VehicleOwnerId);
                if(tr){tr.cells[1].innerHTML=nameHtml;tr.cells[2].innerHTML=mobHtml;tr.cells[3].innerHTML=cityHtml;
                    tr.cells[4].innerHTML=bankHtml;tr.cells[5].innerHTML=upiHtml;tr.cells[6].innerHTML=statHtml;tr.cells[7].innerHTML=actHtml;
                    ownerDT.row(tr).invalidate().draw(false);}
                SRV.toast.success('Owner updated successfully!');
            } else {
                const nn=ownerDT.row.add([ownerDT.data().count()+1,nameHtml,mobHtml,cityHtml,bankHtml,upiHtml,statHtml,actHtml]).draw(false).node();
                $(nn).attr('id','orow-'+r.VehicleOwnerId);
                SRV.toast.success('Owner added successfully!');
            }
        } else {
            Swal.fire({icon:'error',title:'Error',html:res.message||'Could not save.',confirmButtonColor:'#1a237e'});
        }
    });
});

function buildActionBtns(r){
    const a=r.IsActive==='Yes', rj=JSON.stringify(r).replace(/'/g,"&#39;");
    return `<div class='action-btn-group'>
        <button class='btn btn-sm btn-outline-info btn-icon' onclick='viewDetail(${rj})' title='View'><i class='ri-eye-line'></i></button>
        <button class='btn btn-sm btn-warning btn-icon' onclick='editOwner(${rj})' title='Edit'><i class='ri-edit-line'></i></button>
        <button class='btn btn-sm ${a?'btn-danger':'btn-success'} btn-icon' onclick='toggleStatus(this)'
            data-id='${r.VehicleOwnerId}' data-status='${r.IsActive}' data-name='${r.OwnerName}'
            title='${a?'Deactivate':'Activate'}'><i class='${a?'ri-toggle-line':'ri-toggle-fill'}'></i></button>
    </div>`;
}

/* ── Toggle Status ── */
function toggleStatus(btn){
    if(!navigator.onLine){SRV.toast.warning('No internet!');return;}
    const id=btn.dataset.id,st=btn.dataset.status,name=btn.dataset.name||'this owner',isDe=st==='Yes';
    Swal.fire({title:isDe?'⚠️ Deactivate?':'✅ Activate?',html:`<b>${name}</b> will be ${isDe?'deactivated':'activated'}.`,
        icon:'warning',showCancelButton:true,confirmButtonText:isDe?'Yes, Deactivate':'Yes, Activate',
        cancelButtonText:'Cancel',confirmButtonColor:isDe?'#dc2626':'#16a34a',cancelButtonColor:'#64748b'})
    .then(result=>{
        if(!result.isConfirmed)return;
        doAjax(new URLSearchParams({toggle:1,id,status:st}),function(res){
            if(res.status==='success'){
                const ns=res.newStatus,a=ns==='Yes';
                btn.dataset.status=ns;
                btn.className=`btn btn-sm ${a?'btn-danger':'btn-success'} btn-icon`;
                btn.innerHTML=`<i class='${a?'ri-toggle-line':'ri-toggle-fill'}'></i>`;
                btn.title=a?'Deactivate':'Activate';
                const statHtml=a?"<span class='badge rounded-pill bg-success'><i class='ri-checkbox-circle-line me-1'></i>Active</span>":"<span class='badge rounded-pill bg-danger'><i class='ri-close-circle-line me-1'></i>Inactive</span>";
                btn.closest('tr').cells[6].innerHTML=statHtml;
                ownerDT.row(btn.closest('tr')).invalidate().draw(false);
                SRV.toast.success(`Owner ${a?'activated':'deactivated'}!`);
            } else { SRV.toast.error('Status update failed!'); }
        });
    });
}

/* ── View Detail ── */
function viewDetail(d){
    _currentOwner=d;
    _detailLoaded=false;

    const a=d.IsActive==='Yes';
    // Header
    document.getElementById('detailHeader').innerHTML=`
        <div style="width:56px;height:56px;background:linear-gradient(135deg,#1a237e,#1d4ed8);border-radius:14px;display:inline-flex;align-items:center;justify-content:center;font-size:28px;margin-bottom:10px;">🚛</div>
        <div style="font-size:18px;font-weight:800;color:#0f172a;">${d.OwnerName}</div>
        <div style="margin-top:6px;">
            <span class='badge rounded-pill ${a?'bg-success':'bg-danger'}'><i class='${a?'ri-checkbox-circle-line':'ri-close-circle-line'} me-1'></i>${a?'Active':'Inactive'}</span>
        </div>`;

    // Info tab
    const row=(l,v)=>`<div class="detail-row"><div class="detail-label">${l}</div><div class="detail-value">${v||'<span class="text-muted">—</span>'}</div></div>`;
    document.getElementById('infoContent').innerHTML=`
        ${row('📞 Mobile',   d.MobileNo?`<a href="tel:${d.MobileNo}" class="text-primary fw-medium">${d.MobileNo}</a>`:'')}
        ${row('📱 Alternate', d.AlternateMobile?`<a href="tel:${d.AlternateMobile}" class="text-primary">${d.AlternateMobile}</a>`:'')}
        ${row('🏙️ City',     d.City)}
        ${row('📍 State',    d.State)}
        ${row('🏠 Address',  d.Address)}`;

    // Bank tab
    document.getElementById('bankContent').innerHTML=`
        ${row('🏦 Bank',    d.BankName)}
        ${row('💳 Account', d.AccountNo?`<code>${d.AccountNo}</code>`:'')}
        ${row('🔢 IFSC',    d.IFSC?`<code>${d.IFSC}</code>`:'')}
        ${row('📲 UPI',     d.UPI)}`;

    // Reset dynamic tabs
    document.getElementById('paymentContent').innerHTML='<div class="text-center py-3"><span class="spinner-border spinner-border-sm text-primary"></span> Loading...</div>';
    document.getElementById('advanceContent').innerHTML='<div class="text-center py-3"><span class="spinner-border spinner-border-sm text-primary"></span> Loading...</div>';
    document.getElementById('commissionContent').innerHTML='<div class="text-center py-3"><span class="spinner-border spinner-border-sm text-primary"></span> Loading...</div>';
    document.getElementById('commTabBtn').innerHTML='🔄 Commission';

    // Reset to info tab
    document.querySelectorAll('.detail-tab-btn').forEach(b=>b.classList.remove('active'));
    document.querySelectorAll('.detail-tab-content').forEach(c=>c.classList.remove('active'));
    document.querySelector('.detail-tab-btn').classList.add('active');
    document.getElementById('tab-info').classList.add('active');

    new bootstrap.Modal('#detailModal').show();

    // Load dynamic data
    loadDetailData(d.VehicleOwnerId);
}

function loadDetailData(ownerId){
    fetch('',{method:'POST',body:new URLSearchParams({get_detail:1,owner_id:ownerId})})
    .then(r=>r.json()).then(data=>{
        // ── Payment Summary ──
        const p=data.payment;
        const remaining=Math.max(0,(parseFloat(p.TotalPayable)||0)-(parseFloat(p.TotalPaid)||0));
        document.getElementById('paymentContent').innerHTML=`
            <div class="d-flex gap-2 mb-3">
                <div class="pay-mini-card" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                    <div class="pay-mini-val" style="color:#16a34a;">Rs.${Number(p.TotalPaid||0).toLocaleString('en-IN')}</div>
                    <div class="pay-mini-lbl">Total Paid</div>
                </div>
                <div class="pay-mini-card" style="background:#fef2f2;border:1px solid #fecaca;">
                    <div class="pay-mini-val" style="color:#dc2626;">Rs.${Number(remaining).toLocaleString('en-IN')}</div>
                    <div class="pay-mini-lbl">Remaining</div>
                </div>
                <div class="pay-mini-card" style="background:#f0f4ff;border:1px solid #c7d7fc;">
                    <div class="pay-mini-val" style="color:#1a237e;">Rs.${Number(p.TotalPayable||0).toLocaleString('en-IN')}</div>
                    <div class="pay-mini-lbl">Total Payable</div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <div class="pay-mini-card" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                    <div class="pay-mini-val" style="color:#16a34a;">${p.PaidTrips||0}</div>
                    <div class="pay-mini-lbl">✅ Paid Trips</div>
                </div>
                <div class="pay-mini-card" style="background:#fef2f2;border:1px solid #fecaca;">
                    <div class="pay-mini-val" style="color:#dc2626;">${p.PendingTrips||0}</div>
                    <div class="pay-mini-lbl">⏳ Pending Trips</div>
                </div>
                <div class="pay-mini-card" style="background:#f8fafc;border:1px solid #e2e8f0;">
                    <div class="pay-mini-val" style="color:#1a237e;">${p.TotalTrips||0}</div>
                    <div class="pay-mini-lbl">🚛 Total Trips</div>
                </div>
            </div>`;

        // ── Advance History ──
        const advances=data.advances;
        const advSum=data.advanceSummary;
        let advHtml=`
            <div class="d-flex gap-2 mb-3">
                <div class="pay-mini-card" style="background:#f0f4ff;border:1px solid #c7d7fc;">
                    <div class="pay-mini-val" style="color:#1a237e;">Rs.${Number(advSum.Total||0).toLocaleString('en-IN')}</div>
                    <div class="pay-mini-lbl">Total Advance</div>
                </div>
                <div class="pay-mini-card" style="background:#fef9c3;border:1px solid #fde047;">
                    <div class="pay-mini-val" style="color:#92400e;">Rs.${Number(advSum.Remaining||0).toLocaleString('en-IN')}</div>
                    <div class="pay-mini-lbl">Balance Left</div>
                </div>
            </div>`;

        if(advances.length===0){
            advHtml+='<div class="text-center text-muted py-2" style="font-size:13px;">No advance records found.</div>';
        } else {
            const statusColors={Open:'bg-primary',PartiallyAdjusted:'bg-warning text-dark',FullyAdjusted:'bg-success'};
            advances.forEach(a=>{
                const sc=statusColors[a.Status]||'bg-secondary';
                advHtml+=`<div class="adv-row">
                    <div>
                        <div style="font-weight:700;font-size:13px;">Rs.${Number(a.Amount).toLocaleString('en-IN')}</div>
                        <div style="font-size:11px;color:#64748b;">${a.AdvanceDate} &nbsp;|&nbsp; ${a.PaymentMode}</div>
                    </div>
                    <div style="text-align:right;">
                        <span class="badge ${sc}" style="font-size:10px;">${a.Status}</span>
                        <div style="font-size:11px;color:#64748b;margin-top:3px;">Bal: Rs.${Number(a.RemainingAmount).toLocaleString('en-IN')}</div>
                    </div>
                </div>`;
            });
            if(advances.length===5) advHtml+=`<div class="text-center mt-2"><a href="/Sama_Roadlines/views/pages/OwnerAdvance_manage.php" class="btn btn-outline-primary btn-sm" style="font-size:12px;">View All Advances</a></div>`;
        }
        document.getElementById('advanceContent').innerHTML=advHtml;

        // ── Commission (Recoverable from Owner) ──
        const cs=data.commSummary;
        const pendingComm=parseFloat(cs.Pending||0);

        // Tab badge — show pending count if > 0
        const commTabBtn=document.getElementById('commTabBtn');
        if(parseInt(cs.PendingCount||0)>0){
            commTabBtn.innerHTML=`🔄 Commission <span class="badge bg-danger ms-1" style="font-size:9px;">${cs.PendingCount}</span>`;
        }

        let commHtml=`
            <div class="d-flex gap-2 mb-3">
                <div class="pay-mini-card" style="background:#fef2f2;border:1px solid #fecaca;">
                    <div class="pay-mini-val" style="color:#dc2626;">Rs.${Number(pendingComm).toLocaleString('en-IN')}</div>
                    <div class="pay-mini-lbl">⚠️ To Recover</div>
                </div>
                <div class="pay-mini-card" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                    <div class="pay-mini-val" style="color:#16a34a;">Rs.${Number(cs.Received||0).toLocaleString('en-IN')}</div>
                    <div class="pay-mini-lbl">✅ Recovered</div>
                </div>
                <div class="pay-mini-card" style="background:#f0f4ff;border:1px solid #c7d7fc;">
                    <div class="pay-mini-val" style="color:#1a237e;">Rs.${Number(cs.Total||0).toLocaleString('en-IN')}</div>
                    <div class="pay-mini-lbl">Total Commission</div>
                </div>
            </div>`;

        if(data.commissions.length===0){
            commHtml+=`<div class="text-center text-muted py-3" style="font-size:13px;">
                <i class="ri-percent-line" style="font-size:28px;color:#e2e8f0;display:block;margin-bottom:6px;"></i>
                No PaidDirectly trips with commission.
            </div>`;
        } else {
            commHtml+=`<div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Trip-wise Commission</div>`;
            data.commissions.forEach(c=>{
                const isPending=c.CommissionStatus==='Pending';
                commHtml+=`<div class="adv-row" style="${isPending?'border-left:3px solid #dc2626;':'border-left:3px solid #16a34a;'}">
                    <div style="flex:1;">
                        <div style="font-weight:700;font-size:13px;">
                            Rs.${Number(c.CommissionAmount).toLocaleString('en-IN')}
                            <span class="badge ${isPending?'bg-danger':'bg-success'} ms-1" style="font-size:9px;">${isPending?'Pending':'Received'}</span>
                        </div>
                        <div style="font-size:11px;color:#64748b;">${c.VehicleNumber} &nbsp;·&nbsp; ${c.TripDate}</div>
                        <div style="font-size:11px;color:#94a3b8;">${c.FromLocation} → ${c.ToLocation}</div>
                    </div>
                    <div style="text-align:right;font-size:11px;color:#64748b;">
                        Freight<br><strong style="color:#1a237e;">Rs.${Number(c.FreightAmount).toLocaleString('en-IN')}</strong>
                    </div>
                </div>`;
            });
            if(data.commissions.length>=10) commHtml+=`<div class="text-center mt-2"><a href="/Sama_Roadlines/views/pages/CommissionTrack.php" class="btn btn-outline-primary btn-sm" style="font-size:12px;">View All Commissions</a></div>`;
        }
        document.getElementById('commissionContent').innerHTML=commHtml;
        _detailLoaded=true;
    }).catch(()=>{
        ['paymentContent','advanceContent','commissionContent'].forEach(id=>{
            document.getElementById(id).innerHTML='<div class="text-danger text-center py-2">Failed to load data.</div>';
        });
    });
}

function switchTab(name,btn){
    document.querySelectorAll('.detail-tab-btn').forEach(b=>b.classList.remove('active'));
    document.querySelectorAll('.detail-tab-content').forEach(c=>c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-'+name).classList.add('active');
}

document.getElementById('detailEditBtn').addEventListener('click',function(){if(_currentOwner)editOwner(_currentOwner);});
</script>

<?php require_once "../layout/footer.php"; ?>
