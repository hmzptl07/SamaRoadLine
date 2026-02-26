<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once "../../businessLogics/Admin.php";
require_once "../../businessLogics/Party.php";
Admin::checkAuth();

/* ══ AJAX — Save / Update ══ */
if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $isEdit = !empty($_POST['PartyId']);
    $errors = [];
    if (empty(trim($_POST['PartyName'] ?? '')))  $errors[] = 'Party Name is required.';
    if (empty(trim($_POST['PartyType'] ?? '')))  $errors[] = 'Party Type is required.';
    if (!empty($_POST['MobileNo']) && !preg_match('/^[6-9]\d{9}$/', trim($_POST['MobileNo'])))
        $errors[] = 'Invalid mobile number (10 digits starting 6-9).';
    if (!empty($_POST['GSTNo']) && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i', trim($_POST['GSTNo'])))
        $errors[] = 'Invalid GSTIN format.';
    if (!empty($_POST['Email']) && !filter_var($_POST['Email'], FILTER_VALIDATE_EMAIL))
        $errors[] = 'Invalid email address.';
    if ($errors) { echo json_encode(['status'=>'error','message'=>implode('<br>',$errors)]); exit(); }
    $res = $isEdit ? Party::update($_POST['PartyId'], $_POST) : Party::insert($_POST);
    $row = null;
    if ($res) { global $pdo; $id = $isEdit ? $_POST['PartyId'] : $pdo->lastInsertId(); $row = Party::getById($id); }
    echo json_encode(['status'=>$res?'success':'error','row'=>$row]);
    exit();
}

/* ══ AJAX — Toggle Status ══ */
if (isset($_POST['toggle'])) {
    header('Content-Type: application/json');
    $ns = ($_POST['status']==='Yes') ? 'No' : 'Yes';
    $ok = Party::changeStatus($_POST['id'], $ns);
    echo json_encode(['status'=>$ok?'success':'error','newStatus'=>$ns]);
    exit();
}

/* ══ AJAX — Get Party Detail (bills + payments + advance) ══ */
if (isset($_POST['get_detail'])) {
    header('Content-Type: application/json');
    $pid = intval($_POST['party_id']);

    // Last 5 bill payments (Regular)
    $billStmt = $pdo->prepare("
        SELECT b.BillNo, b.BillDate, b.NetBillAmount, b.BillStatus,
               COALESCE(SUM(bp.Amount),0) AS PaidAmt
        FROM Bill b
        LEFT JOIN billpayment bp ON b.BillId = bp.BillId
        WHERE b.PartyId = ?
        GROUP BY b.BillId
        ORDER BY b.BillId DESC LIMIT 5");
    $billStmt->execute([$pid]);
    $bills = $billStmt->fetchAll(PDO::FETCH_ASSOC);

    // Bill summary
    $billSumStmt = $pdo->prepare("
        SELECT
            COUNT(b.BillId) AS TotalBills,
            COALESCE(SUM(b.NetBillAmount),0) AS TotalAmt,
            COALESCE(SUM(bp.Amount),0) AS PaidAmt,
            SUM(CASE WHEN b.BillStatus='Paid' THEN 1 ELSE 0 END) AS PaidBills,
            SUM(CASE WHEN b.BillStatus!='Paid' THEN 1 ELSE 0 END) AS PendingBills
        FROM Bill b
        LEFT JOIN billpayment bp ON b.BillId = bp.BillId
        WHERE b.PartyId = ?
        GROUP BY b.PartyId");
    $billSumStmt->execute([$pid]);
    $billSum = $billSumStmt->fetch(PDO::FETCH_ASSOC) ?: ['TotalBills'=>0,'TotalAmt'=>0,'PaidAmt'=>0,'PaidBills'=>0,'PendingBills'=>0];

    // Last 5 advances
    $advStmt = $pdo->prepare("
        SELECT AdvanceDate, Amount, RemainingAmount, AdjustedAmount, Status, PaymentMode
        FROM partyadvance WHERE PartyId = ?
        ORDER BY PartyAdvanceId DESC LIMIT 5");
    $advStmt->execute([$pid]);
    $advances = $advStmt->fetchAll(PDO::FETCH_ASSOC);

    // Advance summary
    $advSumStmt = $pdo->prepare("
        SELECT COALESCE(SUM(Amount),0) AS Total, COALESCE(SUM(RemainingAmount),0) AS Remaining
        FROM partyadvance WHERE PartyId = ?");
    $advSumStmt->execute([$pid]);
    $advSum = $advSumStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['bills'=>$bills,'billSummary'=>$billSum,'advances'=>$advances,'advanceSummary'=>$advSum]);
    exit();
}

$parties = Party::getAll();
require_once "../layout/header.php";
require_once "../layout/sidebar.php";
?>

<style>
.page-header-card{background:linear-gradient(135deg,#1a237e 0%,#1d4ed8 100%);border-radius:14px;padding:20px 24px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
.page-header-card .ph-title{font-size:20px;font-weight:800;color:#fff;margin:0;display:flex;align-items:center;gap:10px;}
.page-header-card .ph-sub{font-size:12px;color:rgba(255,255,255,0.65);margin-top:3px;}
.stats-bar{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;}
.stat-pill{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:10px 18px;display:flex;align-items:center;gap:10px;box-shadow:0 1px 4px rgba(0,0,0,.05);flex:1;min-width:130px;}
.stat-pill .sp-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.stat-pill .sp-val{font-size:20px;font-weight:800;color:#1a237e;line-height:1;}
.stat-pill .sp-lbl{font-size:11px;color:#64748b;margin-top:2px;}
.filter-card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px 20px;margin-bottom:16px;}
.action-btn-group{display:flex;gap:4px;flex-wrap:nowrap;}
.btn-icon{width:30px;height:30px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:7px;font-size:14px;}
.detail-row{display:flex;padding:9px 0;border-bottom:1px solid #f1f5f9;font-size:13.5px;}
.detail-row:last-child{border-bottom:none;}
.detail-label{width:120px;flex-shrink:0;color:#64748b;font-weight:600;font-size:11.5px;text-transform:uppercase;letter-spacing:.3px;}
.detail-value{color:#1e293b;font-weight:500;flex:1;}
.req{color:#ef4444;margin-left:2px;}
.form-section-head{font-size:11px;font-weight:700;color:#1a237e;text-transform:uppercase;letter-spacing:1px;border-left:3px solid #1a237e;padding-left:8px;margin:6px 0 14px;}
/* Detail Tabs */
.detail-tab-btn{border:1.5px solid #e2e8f0;border-radius:8px;padding:6px 14px;font-size:12px;font-weight:600;cursor:pointer;background:#f8fafc;color:#64748b;transition:.15s;}
.detail-tab-btn.active{background:#1a237e;color:#fff;border-color:#1a237e;}
.detail-tab-content{display:none;}
.detail-tab-content.active{display:block;}
.detail-row{display:flex;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:13px;}
.detail-row:last-child{border-bottom:none;}
.detail-label{width:120px;flex-shrink:0;color:#64748b;font-weight:600;font-size:11.5px;text-transform:uppercase;letter-spacing:.3px;}
.detail-value{color:#1e293b;font-weight:500;flex:1;}
.adv-row{display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:#f8fafc;border-radius:8px;margin-bottom:6px;font-size:12.5px;}
.pay-mini-card{border-radius:10px;padding:12px;text-align:center;flex:1;}
.pay-mini-val{font-size:16px;font-weight:800;line-height:1;}
.pay-mini-lbl{font-size:10px;color:#64748b;margin-top:3px;}
</style>

<div class="main-content app-content">
<div class="container-fluid">

<!-- ══ Page Header ══ -->
<div class="page-header-card">
    <div>
        <div class="ph-title"><i class="ri-group-line"></i> Party Master</div>
        <div class="ph-sub">Manage consigners and agents</div>
    </div>
    <button class="btn btn-warning fw-bold px-4" onclick="openAddModal()" style="border-radius:9px;height:38px;font-size:13px;">
        <i class="ri-add-circle-line me-1"></i> Add New Party
    </button>
</div>

<!-- ══ Stats Bar ══ -->
<?php
$total    = count($parties);
$active   = count(array_filter($parties, fn($p) => $p['IsActive']==='Yes'));
$inactive = $total - $active;
$tyC      = array_count_values(array_column($parties,'PartyType'));
?>
<div class="stats-bar">
    <div class="stat-pill"><div class="sp-icon" style="background:#e0e7ff;"><i class="ri-group-line" style="color:#1a237e;"></i></div><div><div class="sp-val"><?=$total?></div><div class="sp-lbl">Total</div></div></div>
    <div class="stat-pill"><div class="sp-icon" style="background:#dcfce7;"><i class="ri-checkbox-circle-line" style="color:#16a34a;"></i></div><div><div class="sp-val"><?=$active?></div><div class="sp-lbl">Active</div></div></div>
    <div class="stat-pill"><div class="sp-icon" style="background:#fee2e2;"><i class="ri-close-circle-line" style="color:#dc2626;"></i></div><div><div class="sp-val"><?=$inactive?></div><div class="sp-lbl">Inactive</div></div></div>
    <div class="stat-pill"><div class="sp-icon" style="background:#dbeafe;"><i class="ri-truck-line" style="color:#1d4ed8;"></i></div><div><div class="sp-val"><?=$tyC['Consigner']??0?></div><div class="sp-lbl">Consigners</div></div></div>
    <div class="stat-pill"><div class="sp-icon" style="background:#fef9c3;"><i class="ri-user-star-line" style="color:#ca8a04;"></i></div><div><div class="sp-val"><?=$tyC['Agent']??0?></div><div class="sp-lbl">Agents</div></div></div>
</div>

<!-- ══ Filters ══ -->
<div class="filter-card">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label fw-semibold fs-12 mb-1"><i class="ri-filter-3-line me-1"></i>Party Type</label>
            <select id="filterType" class="form-select form-select-sm">
                <option value="">-- All Types --</option>
                <option value="Consigner">Consigner</option>
                <option value="Agent">Agent</option>
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
            <button class="btn btn-outline-secondary btn-sm w-100" onclick="clearFilters()" title="Clear filters"><i class="ri-refresh-line"></i></button>
        </div>
        <div class="col-md-4 ms-auto">
            <label class="form-label fw-semibold fs-12 mb-1"><i class="ri-search-line me-1"></i>Search</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0" style="border-radius:8px 0 0 8px;"><i class="ri-search-line text-muted"></i></span>
                <input type="text" id="customSearch" class="form-control border-start-0 ps-1"
                    placeholder="Name, Mobile, City, GST..."
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
            <table id="partyTable" class="table table-hover align-middle mb-0 w-100">
                <thead style="background:#f8fafc;">
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>Party Name</th>
                        <th>Type</th>
                        <th>Mobile</th>
                        <th>GST No</th>
                        <th>City / State</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th style="width:110px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $tc=['Consigner'=>'primary','Agent'=>'warning'];
                $i=1; foreach($parties as $r):
                    $c=$tc[$r['PartyType']]??'secondary'; $a=$r['IsActive']==='Yes';
                    $rj=htmlspecialchars(json_encode($r),ENT_QUOTES);
                ?>
                <tr id="prow-<?=$r['PartyId']?>">
                    <td class="text-muted fw-medium fs-13"><?=$i++?></td>
                    <td>
                        <div class="fw-bold" style="font-size:13.5px;"><?=htmlspecialchars($r['PartyName'])?></div>
                        <?php if(!empty($r['Address'])): ?>
                        <div class="text-muted" style="font-size:11px;"><?=htmlspecialchars(mb_strimwidth($r['Address'],0,40,'...'))?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-<?=$c?> rounded-pill"><?=$r['PartyType']?></span></td>
                    <td>
                        <?php if(!empty($r['MobileNo'])): ?>
                        <a href="tel:<?=$r['MobileNo']?>" class="text-dark text-decoration-none" style="font-size:13px;">
                            <i class="ri-phone-line text-success me-1" style="font-size:12px;"></i><?=htmlspecialchars($r['MobileNo'])?>
                        </a>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td><?=!empty($r['GSTNo'])?"<span class='badge bg-light text-dark border' style='font-size:11px;'>".htmlspecialchars($r['GSTNo'])."</span>":"<span class='text-muted'>—</span>"?></td>
                    <td>
                        <div style="font-size:13px;"><?=htmlspecialchars($r['City']??'—')?></div>
                        <?php if(!empty($r['State'])): ?>
                        <div class="text-muted" style="font-size:11px;"><?=htmlspecialchars($r['State'])?> <?=!empty($r['StateCode'])?'('.$r['StateCode'].')':''?></div>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;"><?=!empty($r['Email'])?"<a href='mailto:{$r['Email']}' class='text-primary'>".htmlspecialchars($r['Email'])."</a>":"<span class='text-muted'>—</span>"?></td>
                    <td>
                        <span class="badge rounded-pill <?=$a?'bg-success':'bg-danger'?>">
                            <i class="<?=$a?'ri-checkbox-circle-line':'ri-close-circle-line'?> me-1"></i>
                            <?=$a?'Active':'Inactive'?>
                        </span>
                    </td>
                    <td>
                        <div class="action-btn-group">
                            <button class="btn btn-sm btn-outline-info btn-icon" onclick='viewDetail(<?=$rj?>)' title="View Details"><i class="ri-eye-line"></i></button>
                            <button class="btn btn-sm btn-warning btn-icon" onclick='editParty(<?=$rj?>)' title="Edit"><i class="ri-edit-line"></i></button>
                            <button class="btn btn-sm <?=$a?'btn-danger':'btn-success'?> btn-icon"
                                onclick="toggleStatus(this)" data-id="<?=$r['PartyId']?>"
                                data-status="<?=$r['IsActive']?>" data-name="<?=htmlspecialchars($r['PartyName'])?>"
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

<!-- ══════════════════════════════
     ADD / EDIT MODAL
══════════════════════════════ -->
<div class="modal fade" id="partyModal" tabindex="-1" data-bs-backdrop="static">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 20px 60px rgba(0,0,0,.15);">
    <div class="modal-header" style="background:linear-gradient(135deg,#1a237e,#1d4ed8);border-radius:16px 16px 0 0;padding:18px 24px;">
        <h5 class="modal-title text-white fw-bold" id="partyModalTitle"><i class="ri-group-line me-2"></i>Add Party</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <form id="partyForm" novalidate>
        <input type="hidden" name="ajax" value="1">
        <input type="hidden" name="PartyId" id="PartyId">
        <div class="modal-body p-4">

            <!-- Basic Info -->
            <div class="form-section-head"><i class="ri-information-line me-1"></i>Basic Information</div>
            <div class="row g-3 mb-1">
                <div class="col-md-5">
                    <label class="form-label fw-semibold fs-13">Party Name <span class="req">*</span></label>
                    <input type="text" name="PartyName" id="f_PartyName" class="form-control" placeholder="Enter party name" data-uppercase>
                    <div class="srv-feedback invalid-feedback"></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-13">Party Type <span class="req">*</span></label>
                    <select name="PartyType" id="f_PartyType" class="form-select">
                        <option value="">-- Select Type --</option>
                        <option value="Consigner">🚛 Consigner</option>
                        <option value="Agent">⭐ Agent</option>
                    </select>
                    <div class="srv-feedback invalid-feedback"></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-13">Status</label>
                    <select name="IsActive" id="f_IsActive" class="form-select">
                        <option value="Yes">✅ Active</option>
                        <option value="No">❌ Inactive</option>
                    </select>
                </div>
            </div>

            <!-- Contact -->
            <div class="form-section-head mt-3"><i class="ri-phone-line me-1"></i>Contact Details</div>
            <div class="row g-3 mb-1">
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-13">Mobile No</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="ri-phone-line"></i></span>
                        <input type="text" name="MobileNo" id="f_MobileNo" class="form-control" placeholder="10 digits mobile" maxlength="10" data-mobile>
                    </div>
                    <div class="srv-feedback invalid-feedback" id="err_MobileNo"></div>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-semibold fs-13">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="ri-mail-line"></i></span>
                        <input type="email" name="Email" id="f_Email" class="form-control" placeholder="email@example.com">
                    </div>
                    <div class="srv-feedback invalid-feedback" id="err_Email"></div>
                </div>
            </div>

            <!-- GST -->
            <div class="form-section-head mt-3"><i class="ri-government-line me-1"></i>Tax / GST Details</div>
            <div class="row g-3 mb-1">
                <div class="col-md-5">
                    <label class="form-label fw-semibold fs-13">GST Number</label>
                    <input type="text" name="GSTNo" id="f_GSTNo" class="form-control" placeholder="e.g. 24ABCDE1234F1Z5" maxlength="15" data-uppercase>
                    <div class="srv-feedback invalid-feedback"></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-13">State Code</label>
                    <input type="text" name="StateCode" id="f_StateCode" class="form-control" placeholder="e.g. 24" maxlength="5" data-numeric>
                </div>
            </div>

            <!-- Address -->
            <div class="form-section-head mt-3"><i class="ri-map-pin-line me-1"></i>Address</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-13">City</label>
                    <input type="text" name="City" id="f_City" class="form-control" placeholder="City">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-13">State</label>
                    <input type="text" name="State" id="f_State" class="form-control" placeholder="State">
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-semibold fs-13">Full Address</label>
                    <textarea name="Address" id="f_Address" class="form-control" rows="2" placeholder="Street, area, pincode..."></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-13">Remark</label>
                    <textarea name="Remark" id="f_Remark" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid #e2e8f0;padding:14px 24px;">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="ri-close-line me-1"></i>Cancel</button>
            <button type="submit" class="btn btn-primary px-4" id="partySaveBtn"><i class="ri-save-line me-1"></i>Save Party</button>
        </div>
    </form>
</div>
</div>
</div>

<!-- ══════════════════════════════
     DETAIL MODAL — Tabbed
══════════════════════════════ -->
<div class="modal fade" id="detailModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
<div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 20px 60px rgba(0,0,0,.15);">
    <div class="modal-header" id="detailModalHeader" style="border-radius:16px 16px 0 0;padding:18px 24px;">
        <h5 class="modal-title text-white fw-bold"><i class="ri-eye-line me-2"></i>Party Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body p-4">
        <!-- Header -->
        <div id="detailHeader" style="text-align:center;margin-bottom:16px;"></div>
        <!-- Tabs -->
        <div class="d-flex gap-2 mb-3 flex-wrap">
            <button class="detail-tab-btn active" onclick="switchTab('info',this)">👤 Info</button>
            <button class="detail-tab-btn" onclick="switchTab('bills',this)">🧾 Bills</button>
            <button class="detail-tab-btn" onclick="switchTab('advance',this)">📋 Advances</button>
        </div>
        <!-- Tab: Info -->
        <div class="detail-tab-content active" id="tab-info">
            <div id="partyInfoContent" style="background:#f8fafc;border-radius:12px;padding:4px 16px;"></div>
        </div>
        <!-- Tab: Bills -->
        <div class="detail-tab-content" id="tab-bills">
            <div id="partyBillContent"><div class="text-center py-3"><span class="spinner-border spinner-border-sm text-primary"></span> Loading...</div></div>
        </div>
        <!-- Tab: Advance -->
        <div class="detail-tab-content" id="tab-advance">
            <div id="partyAdvContent"><div class="text-center py-3"><span class="spinner-border spinner-border-sm text-primary"></span> Loading...</div></div>
        </div>
    </div>
    <div class="modal-footer" style="border-top:1px solid #f1f5f9;padding:12px 20px;">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-warning btn-sm" id="detailEditBtn"><i class="ri-edit-line me-1"></i>Edit</button>
    </div>
</div>
</div>
</div>

<!-- ══════════════════════════════
     SCRIPTS
══════════════════════════════ -->
<script src="/Sama_Roadlines/assets/js/validation.js"></script>
<script>
const TC = {Consigner:'primary',Agent:'warning'};
const TI = {Consigner:'🚛',Agent:'⭐'};

function tBadge(t){ return `<span class='badge bg-${TC[t]||'secondary'} rounded-pill'>${TI[t]||''} ${t}</span>`; }
function sBadge(s){ return s==='Yes' ? "<span class='badge bg-success rounded-pill'><i class='ri-checkbox-circle-line me-1'></i>Active</span>" : "<span class='badge bg-danger rounded-pill'><i class='ri-close-circle-line me-1'></i>Inactive</span>"; }
function aBtns(r){
    const a=r.IsActive==='Yes', rj=JSON.stringify(r).replace(/'/g,"&#39;");
    return `<div class='action-btn-group'>
        <button class='btn btn-sm btn-outline-info btn-icon' onclick='viewDetail(${rj})' title='View'><i class='ri-eye-line'></i></button>
        <button class='btn btn-sm btn-warning btn-icon' onclick='editParty(${rj})' title='Edit'><i class='ri-edit-line'></i></button>
        <button class='btn btn-sm ${a?'btn-danger':'btn-success'} btn-icon' onclick='toggleStatus(this)'
            data-id='${r.PartyId}' data-status='${r.IsActive}' data-name='${r.PartyName}'
            title='${a?'Deactivate':'Activate'}'><i class='${a?'ri-toggle-line':'ri-toggle-fill'}'></i></button>
    </div>`;
}

/* ── DataTable ── */
var partyDT;
$(document).ready(function(){
    partyDT = $('#partyTable').DataTable({
        scrollX:true, pageLength:25,
        columnDefs:[{orderable:false,targets:[0,8]}],
        dom: 'rtip',   // hide default search box — we use our own
        language:{
            paginate:{previous:'‹',next:'›'},
            info:'Showing _START_–_END_ of _TOTAL_',
            emptyTable:'No parties found.'
        },
        drawCallback:function(){var i=this.api().page.info();$('#filterInfo').text(i.recordsDisplay+'/'+i.recordsTotal);}
    });

    $('#filterType').select2({theme:'bootstrap-5',allowClear:true,placeholder:'-- All Types --',width:'100%'});
    $('#filterStatus').select2({theme:'bootstrap-5',allowClear:true,placeholder:'-- All Status --',width:'100%'});
    $('#filterType').on('change',function(){partyDT.column(2).search($(this).val()||'').draw();});
    $('#filterStatus').on('change',function(){partyDT.column(7).search($(this).val()||'').draw();});

    // Custom search box
    $('#customSearch').on('keyup input',function(){partyDT.search($(this).val()).draw();});
});
function clearFilters(){
    $('#filterType,#filterStatus').val(null).trigger('change');
    $('#customSearch').val('');
    partyDT.search('').columns().search('').draw();
}

window.addEventListener('offline',()=>SRV.toast.warning('No internet connection!'));
window.addEventListener('online', ()=>SRV.toast.success('Back online!'));

function doAjax(body,cb){
    if(!navigator.onLine){ Swal.fire({icon:'warning',title:'No Internet',text:'Check your connection.'}); return; }
    Swal.fire({title:'Please wait...',allowOutsideClick:false,allowEscapeKey:false,didOpen:()=>Swal.showLoading()});
    fetch('',{method:'POST',body}).then(r=>r.json()).then(res=>{Swal.close();cb(res);})
    .catch(()=>Swal.fire({icon:'error',title:'Error',text:'Server error. Try again.'}));
}

/* ── Clear form validation ── */
function clearFormValidation(){
    document.querySelectorAll('#partyForm .is-invalid,#partyForm .is-valid').forEach(el=>el.classList.remove('is-invalid','is-valid'));
    document.querySelectorAll('#partyForm .srv-feedback').forEach(el=>{el.textContent='';el.style.display='none';});
}

/* ── Open Add Modal ── */
function openAddModal(){
    document.getElementById('partyForm').reset();
    document.getElementById('PartyId').value='';
    clearFormValidation();
    document.getElementById('partyModalTitle').innerHTML='<i class="ri-add-circle-line me-2"></i>Add New Party';
    document.getElementById('partySaveBtn').innerHTML='<i class="ri-save-line me-1"></i>Save Party';
    new bootstrap.Modal('#partyModal').show();
    setTimeout(()=>document.getElementById('f_PartyName').focus(),400);
}

/* ── Open Edit Modal ── */
function editParty(d){
    document.getElementById('partyForm').reset();
    clearFormValidation();
    ['PartyId','PartyName','PartyType','MobileNo','Email','GSTNo','StateCode','City','State','Address','Remark','IsActive']
        .forEach(k=>{ const el=document.getElementById('f_'+k)||document.querySelector(`#partyForm [name="${k}"]`); if(el) el.value=d[k]??''; });
    document.getElementById('partyModalTitle').innerHTML='<i class="ri-edit-line me-2"></i>Edit Party';
    document.getElementById('partySaveBtn').innerHTML='<i class="ri-save-line me-1"></i>Update Party';
    const dm=bootstrap.Modal.getInstance(document.getElementById('detailModal'));
    if(dm) dm.hide();
    setTimeout(()=>new bootstrap.Modal('#partyModal').show(),200);
}

/* ── Form Submit with SRV Validation ── */
document.getElementById('partyForm').addEventListener('submit',function(e){
    e.preventDefault();
    const valid = SRV.validate('#partyForm',{
        'f_PartyName':  {required:[true,'Party Name is required.'],minLength:[2,'Min 2 characters.']},
        'f_PartyType':  {selectRequired:[true,'Please select Party Type.']},
        'f_MobileNo':   {mobile:[true,'Enter valid 10 digits mobile number.']},
        'f_Email':      {email:[true,'Enter a valid email address.']},
        'f_GSTNo':      {gstin:[true,'Invalid GSTIN. e.g. 24ABCDE1234F1Z5']}
    });
    if(!valid) return;

    const isEdit=!!document.getElementById('PartyId').value;
    const btn=document.getElementById('partySaveBtn');
    btn.disabled=true;
    btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

    doAjax(new FormData(this),function(res){
        btn.disabled=false;
        btn.innerHTML=isEdit?'<i class="ri-save-line me-1"></i>Update Party':'<i class="ri-save-line me-1"></i>Save Party';
        if(res.status==='success'){
            bootstrap.Modal.getInstance(document.getElementById('partyModal')).hide();
            const r=res.row;
            const nameHtml=`<div class='fw-bold' style='font-size:13.5px;'>${r.PartyName}</div>${r.Address?`<div class='text-muted' style='font-size:11px;'>${r.Address.substring(0,40)}...</div>`:''}`;
            const mobHtml=r.MobileNo?`<a href='tel:${r.MobileNo}' class='text-dark text-decoration-none'><i class='ri-phone-line text-success me-1' style='font-size:12px;'></i>${r.MobileNo}</a>`:'<span class="text-muted">—</span>';
            const gstHtml=r.GSTNo?`<span class='badge bg-light text-dark border'>${r.GSTNo}</span>`:'<span class="text-muted">—</span>';
            const emailHtml=r.Email?`<a href='mailto:${r.Email}' class='text-primary' style='font-size:12px;'>${r.Email}</a>`:'<span class="text-muted">—</span>';
            const cityHtml=(r.City||'—')+(r.State?`<br><small class='text-muted'>${r.State}</small>`:'');

            if(isEdit){
                const tr=document.getElementById('prow-'+r.PartyId);
                if(tr){tr.cells[1].innerHTML=nameHtml;tr.cells[2].innerHTML=tBadge(r.PartyType);tr.cells[3].innerHTML=mobHtml;
                    tr.cells[4].innerHTML=gstHtml;tr.cells[5].innerHTML=cityHtml;tr.cells[6].innerHTML=emailHtml;
                    tr.cells[7].innerHTML=sBadge(r.IsActive);tr.cells[8].innerHTML=aBtns(r);partyDT.row(tr).invalidate().draw(false);}
                SRV.toast.success('Party updated successfully!');
            } else {
                const nn=partyDT.row.add([partyDT.data().count()+1,nameHtml,tBadge(r.PartyType),mobHtml,gstHtml,cityHtml,emailHtml,sBadge(r.IsActive),aBtns(r)]).draw(false).node();
                $(nn).attr('id','prow-'+r.PartyId);
                SRV.toast.success('Party added successfully!');
            }
        } else {
            Swal.fire({icon:'error',title:'Validation Error',html:res.message||'Could not save. Try again.',confirmButtonColor:'#1a237e'});
        }
    });
});

/* ── Toggle Status ── */
function toggleStatus(btn){
    if(!navigator.onLine){SRV.toast.warning('No internet!');return;}
    const id=btn.dataset.id, st=btn.dataset.status, name=btn.dataset.name||'this party';
    const isDe=st==='Yes';
    Swal.fire({
        title: isDe?'⚠️ Deactivate?':'✅ Activate?',
        html:`<b>${name}</b> will be ${isDe?'deactivated':'activated'}.`,
        icon:'warning', showCancelButton:true,
        confirmButtonText:isDe?'Yes, Deactivate':'Yes, Activate',
        cancelButtonText:'Cancel',
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
                btn.closest('tr').cells[7].innerHTML=sBadge(ns);
                partyDT.row(btn.closest('tr')).invalidate().draw(false);
                SRV.toast.success(`Party ${a?'activated':'deactivated'}!`);
            } else { SRV.toast.error('Status update failed!'); }
        });
    });
}

/* ── View Detail ── */
let _detailParty=null;
function viewDetail(d){
    _detailParty=d;
    const headerGrads={primary:'linear-gradient(135deg,#1a237e,#1d4ed8)',warning:'linear-gradient(135deg,#92400e,#d97706)',secondary:'linear-gradient(135deg,#374151,#6b7280)'};
    const grad=headerGrads[TC[d.PartyType]||'secondary'];
    document.getElementById('detailModalHeader').style.background=grad;

    // Header
    document.getElementById('detailHeader').innerHTML=`
        <div style="width:56px;height:56px;background:${grad};border-radius:14px;display:inline-flex;align-items:center;justify-content:center;font-size:28px;margin-bottom:10px;">${TI[d.PartyType]||'👤'}</div>
        <div style="font-size:18px;font-weight:800;color:#0f172a;">${d.PartyName}</div>
        <div style="margin-top:6px;">${tBadge(d.PartyType)} ${sBadge(d.IsActive)}</div>`;

    // Info tab
    const row=(lbl,val,ic='')=>`<div class="detail-row"><div class="detail-label">${ic} ${lbl}</div><div class="detail-value">${val||'<span class="text-muted">—</span>'}</div></div>`;
    document.getElementById('partyInfoContent').innerHTML=`
        ${row('Mobile',   d.MobileNo?`<a href="tel:${d.MobileNo}" class="text-primary fw-medium">${d.MobileNo}</a>`:'',' 📞')}
        ${row('Email',    d.Email?`<a href="mailto:${d.Email}" class="text-primary">${d.Email}</a>`:'',' 📧')}
        ${row('GST No',   d.GSTNo?`<code style="font-size:12px;">${d.GSTNo}</code>`:'',' 🏛️')}
        ${row('State Code', d.StateCode,' 🔢')}
        ${row('City',     d.City,' 🏙️')}
        ${row('State',    d.State,' 📍')}
        ${row('Address',  d.Address,' 🏠')}
        ${row('Remark',   d.Remark,' 📝')}`;

    // Reset dynamic tabs
    document.getElementById('partyBillContent').innerHTML='<div class="text-center py-3"><span class="spinner-border spinner-border-sm text-primary"></span> Loading...</div>';
    document.getElementById('partyAdvContent').innerHTML='<div class="text-center py-3"><span class="spinner-border spinner-border-sm text-primary"></span> Loading...</div>';

    // Reset to info tab
    document.querySelectorAll('.detail-tab-btn').forEach(b=>b.classList.remove('active'));
    document.querySelectorAll('.detail-tab-content').forEach(c=>c.classList.remove('active'));
    document.querySelector('.detail-tab-btn').classList.add('active');
    document.getElementById('tab-info').classList.add('active');

    new bootstrap.Modal('#detailModal').show();

    // Load dynamic data
    fetch('',{method:'POST',body:new URLSearchParams({get_detail:1,party_id:d.PartyId})})
    .then(r=>r.json()).then(data=>{
        // Bills tab
        const bs=data.billSummary;
        const remaining=Math.max(0,(parseFloat(bs.TotalAmt)||0)-(parseFloat(bs.PaidAmt)||0));
        let billHtml=`
            <div class="d-flex gap-2 mb-3">
                <div class="pay-mini-card" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                    <div class="pay-mini-val" style="color:#16a34a;">Rs.${Number(bs.PaidAmt||0).toLocaleString('en-IN')}</div>
                    <div class="pay-mini-lbl">Total Received</div>
                </div>
                <div class="pay-mini-card" style="background:#fef2f2;border:1px solid #fecaca;">
                    <div class="pay-mini-val" style="color:#dc2626;">Rs.${Number(remaining).toLocaleString('en-IN')}</div>
                    <div class="pay-mini-lbl">Outstanding</div>
                </div>
                <div class="pay-mini-card" style="background:#f0f4ff;border:1px solid #c7d7fc;">
                    <div class="pay-mini-val" style="color:#1a237e;">Rs.${Number(bs.TotalAmt||0).toLocaleString('en-IN')}</div>
                    <div class="pay-mini-lbl">Total Billed</div>
                </div>
            </div>
            <div class="d-flex gap-2 mb-3">
                <div class="pay-mini-card" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                    <div class="pay-mini-val" style="color:#16a34a;">${bs.PaidBills||0}</div>
                    <div class="pay-mini-lbl">✅ Paid Bills</div>
                </div>
                <div class="pay-mini-card" style="background:#fef2f2;border:1px solid #fecaca;">
                    <div class="pay-mini-val" style="color:#dc2626;">${bs.PendingBills||0}</div>
                    <div class="pay-mini-lbl">⏳ Pending Bills</div>
                </div>
                <div class="pay-mini-card" style="background:#f8fafc;border:1px solid #e2e8f0;">
                    <div class="pay-mini-val" style="color:#1a237e;">${bs.TotalBills||0}</div>
                    <div class="pay-mini-lbl">🧾 Total Bills</div>
                </div>
            </div>`;

        if(data.bills.length===0){
            billHtml+='<div class="text-center text-muted py-2" style="font-size:13px;">No bills generated yet.</div>';
        } else {
            billHtml+='<div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Recent Bills</div>';
            const statusStyle={Generated:{bg:'#fef2f2',color:'#dc2626',label:'Unpaid'},PartiallyPaid:{bg:'#fef9c3',color:'#92400e',label:'Partial'},Paid:{bg:'#f0fdf4',color:'#16a34a',label:'Paid'}};
            data.bills.forEach(b=>{
                const paid=parseFloat(b.PaidAmt||0);
                const net=parseFloat(b.NetBillAmount||0);
                const rem=Math.max(0,net-paid);
                const ss=statusStyle[b.BillStatus]||{bg:'#f8fafc',color:'#64748b',label:b.BillStatus};
                billHtml+=`<div class="adv-row">
                    <div>
                        <div style="font-weight:700;font-size:13px;">${b.BillNo}</div>
                        <div style="font-size:11px;color:#64748b;">${b.BillDate}</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:12px;font-weight:600;color:#1a237e;">Rs.${Number(net).toLocaleString('en-IN')}</div>
                        <div style="font-size:10px;color:#64748b;">Bal: Rs.${Number(rem).toLocaleString('en-IN')}</div>
                    </div>
                    <div><span class="badge" style="background:${ss.bg};color:${ss.color};border:1px solid ${ss.color}30;font-size:10px;">${ss.label}</span></div>
                </div>`;
            });
            if(data.bills.length===5) billHtml+=`<div class="text-center mt-2"><a href="/Sama_Roadlines/views/pages/BillPayment_manage.php" class="btn btn-outline-primary btn-sm" style="font-size:12px;">View All Bills</a></div>`;
        }
        document.getElementById('partyBillContent').innerHTML=billHtml;

        // Advance tab
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

        if(data.advances.length===0){
            advHtml+='<div class="text-center text-muted py-2" style="font-size:13px;">No advance records found.</div>';
        } else {
            advHtml+='<div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Recent Advances</div>';
            const statusColors={Open:'bg-primary',PartiallyAdjusted:'bg-warning text-dark',FullyAdjusted:'bg-success'};
            data.advances.forEach(a=>{
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
            if(data.advances.length===5) advHtml+=`<div class="text-center mt-2"><a href="/Sama_Roadlines/views/pages/PartyAdvance.php" class="btn btn-outline-primary btn-sm" style="font-size:12px;">View All Advances</a></div>`;
        }
        document.getElementById('partyAdvContent').innerHTML=advHtml;
    }).catch(()=>{
        document.getElementById('partyBillContent').innerHTML='<div class="text-danger text-center py-2">Failed to load.</div>';
        document.getElementById('partyAdvContent').innerHTML='<div class="text-danger text-center py-2">Failed to load.</div>';
    });
}

function switchTab(name,btn){
    document.querySelectorAll('.detail-tab-btn').forEach(b=>b.classList.remove('active'));
    document.querySelectorAll('.detail-tab-content').forEach(c=>c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-'+name).classList.add('active');
}

document.getElementById('detailEditBtn').addEventListener('click',function(){if(_detailParty)editParty(_detailParty);});
</script>

<?php require_once "../layout/footer.php"; ?>
