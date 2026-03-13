<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once "../../businessLogics/Admin.php";
require_once "../../businessLogics/RegularTrip.php";
require_once "../../businessLogics/Party.php";
require_once "../../businessLogics/Vehicle.php";
Admin::checkAuth();

$TRIP_TYPE = "Regular";
$BACK_URL  = "RegularTrips.php";

/* ══ AJAX — Save ══ */
if (isset($_POST["saveTrip"])) {
    header("Content-Type: application/json");
    try {
        $data = $_POST;
        $data["TripType"] = $TRIP_TYPE;

        // Recalculate Amount[] server-side from Weight x Rate (don't trust readonly field)
        if (!empty($data["Weight"]) && is_array($data["Weight"])) {
            foreach ($data["Weight"] as $k => $w) {
                $data["Amount"][$k] = floatval($w) * floatval($data["Rate"][$k] ?? 0);
            }
        }

        // FreightAmount = sum of material amounts (always auto)
        $matSum = 0;
        if (!empty($data["Weight"]) && is_array($data["Weight"])) {
            foreach ($data["Weight"] as $k => $w) {
                $matSum += floatval($w) * floatval($data["Rate"][$k] ?? 0);
            }
        }
        $data["FreightAmount"] = $matSum;

        // Recalculate TotalAmount and NetAmount server-side
        $fr  = $matSum;
        $la  = floatval($data["LabourCharge"] ?? 0);
        $ho  = floatval($data["HoldingCharge"] ?? 0);
        $ot  = floatval($data["OtherCharge"] ?? 0);
        $adv = floatval($data["CashAdvance"] ?? 0) + floatval($data["OnlineAdvance"] ?? 0);
        $tds = floatval($data["TDS"] ?? 0);
        $com = floatval($data["CommissionAmount"] ?? 0);
        $data["TotalAmount"] = $fr + $la + $ho + $ot;
        $data["NetAmount"]   = $data["TotalAmount"] - $adv - $tds;

        $tripId = !empty($data["TripId"]) ? intval($data["TripId"]) : 0;
        $result = $tripId > 0 ? RegularTrip::update($tripId, $data) : RegularTrip::insert($data);

        echo json_encode([
            "status"  => $result ? "success" : "error",
            "tripId"  => $result,
            "tripType" => $TRIP_TYPE,
            "msg"     => $result ? "Saved" : "DB insert failed"
        ]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "msg" => $e->getMessage()]);
    }
    exit();
}

$tripId    = intval($_GET["TripId"] ?? 0);
$editTrip  = null;
$materials = [];
if ($tripId > 0) {
    $editTrip  = RegularTrip::getById($tripId);
    // Ensure this is actually a Regular trip
    if ($editTrip && $editTrip["TripType"] !== "Regular") {
        header("Location: AgentTripForm.php?TripId=$tripId");
        exit();
    }
    $materials = RegularTrip::getMaterials($tripId);
    $comm      = RegularTrip::getCommission($tripId);
    if ($editTrip) $editTrip["CommissionAmount"] = $comm["CommissionAmount"] ?? "";
}
$isEdit    = $editTrip !== null;
$pageTitle = $isEdit ? "Edit Regular Trip #$tripId" : "New Regular Trip";

// ── Lock check ──
if ($isEdit) {
    $tripStatus = $editTrip['TripStatus'] ?? 'Open';
    $isBilledOrClosed = in_array($tripStatus, ['Billed', 'Closed']);
    $ownerPaid = false;
    if ($tripId > 0) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM ownerpayment WHERE TripId = ?");
        $chk->execute([$tripId]);
        $ownerPaid = $chk->fetchColumn() > 0;
    }
    // Commission received?
    $commLock = false;
    if ($tripId > 0) {
        $chk2 = $pdo->prepare("SELECT COUNT(*) FROM tripcommission WHERE TripId = ? AND CommissionStatus = 'Received'");
        $chk2->execute([$tripId]);
        $commLock = $chk2->fetchColumn() > 0;
    }
    // Vasuli received?
    $vasuliLock = false;
    if ($tripId > 0) {
        $chk3 = $pdo->prepare("SELECT COUNT(*) FROM tripvasuli WHERE TripId = ? AND VasuliStatus = 'Received'");
        $chk3->execute([$tripId]);
        $vasuliLock = $chk3->fetchColumn() > 0;
    }
    if ($isBilledOrClosed || $ownerPaid || $commLock || $vasuliLock) {
        if ($isBilledOrClosed) $reason = 'billed_closed';
        elseif ($ownerPaid)    $reason = 'owner_paid';
        elseif ($commLock)     $reason = 'commission_received';
        else                   $reason = 'vasuli_received';
        // Redirect to DirectTrips if came from there, else RegularTrips
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $back = (strpos($ref, 'DirectTrips') !== false) ? 'DirectTrips.php' : 'RegularTrips.php';
        header("Location: {$back}?locked=1&reason={$reason}");
        exit();
    }
}

$parties        = Party::getAll();
$vehicles       = Vehicle::getAll();
$consigners     = array_filter($parties,  fn($p) => $p["PartyType"] === "Consigner" && $p["IsActive"] === "Yes");
$activeVehicles = array_filter($vehicles, fn($v) => $v["IsActive"] === "Yes");

function fv($f, $e)
{
    return htmlspecialchars($e[$f] ?? '', ENT_QUOTES);
}
function fm($f, $e)
{
    return number_format(floatval($e[$f] ?? 0), 2, ".", "");
}

require_once "../layout/header.php";
require_once "../layout/sidebar.php";
?>

<style>
    .page-header-card {
        background: linear-gradient(135deg, #1a237e 0%, #1d4ed8 100%);
        border-radius: 14px;
        padding: 18px 24px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }

    .ph-title {
        font-size: 18px;
        font-weight: 800;
        color: #fff;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .ph-breadcrumb {
        font-size: 11.5px;
        color: rgba(255, 255, 255, 0.6);
        margin-top: 5px;
    }

    .ph-breadcrumb a {
        color: rgba(255, 255, 255, 0.75);
        text-decoration: none;
    }

    .ph-breadcrumb a:hover {
        color: #fff;
    }

    .ph-breadcrumb span {
        margin: 0 5px;
    }

    .trip-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        margin-bottom: 16px;
        overflow: hidden;
        box-shadow: 0 1px 4px rgba(0, 0, 0, .05);
    }

    .trip-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 18px;
        border-bottom: 1px solid #f1f5f9;
    }

    .trip-card-title {
        font-size: 13px;
        font-weight: 700;
        color: #1a237e;
        display: flex;
        align-items: center;
        gap: 7px;
    }

    .trip-card-body {
        padding: 16px 18px;
    }

    .req {
        color: #ef4444;
        margin-left: 2px;
    }

    .charge-card-head {
        background: linear-gradient(135deg, #1a237e, #283593);
        border-radius: 13px 13px 0 0;
        padding: 13px 18px;
    }

    .charge-card-head .title {
        font-size: 13px;
        font-weight: 700;
        color: #fff;
        display: flex;
        align-items: center;
        gap: 7px;
    }

    .charge-card-body {
        padding: 16px 18px;
    }

    .sec-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .8px;
        text-transform: uppercase;
        color: #1a237e;
        background: #e0e7ff;
        border-radius: 6px;
        padding: 3px 10px;
        margin: 6px 0 10px;
    }

    .summary-box {
        background: linear-gradient(135deg, #f0f4ff, #e8efff);
        border: 2px solid #c7d7fc;
        border-radius: 12px;
        padding: 14px 16px;
        margin-top: 14px;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        font-size: 13px;
    }

    .total-row {
        border-top: 2px solid #c7d7fc;
        margin-top: 6px;
        padding-top: 10px;
    }

    .net-amount {
        font-size: 26px;
        font-weight: 900;
        color: #1a237e;
        text-align: center;
        margin-top: 10px;
        letter-spacing: -0.5px;
    }

    .net-label {
        font-size: 11px;
        color: #64748b;
        text-align: center;
        text-transform: uppercase;
        letter-spacing: .5px;
    }

    .save-btn-card {
        background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
        border: 2px solid #bae6fd;
        border-radius: 14px;
        padding: 20px;
        text-align: center;
    }

    /* ══ Material Table ══ */
    .mat-wrap {
        background: #fff;
        border: 1px solid #dde4f0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(30,58,95,.08);
        margin-bottom: 0;
    }
    .mat-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 13px 20px;
        background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%);
    }
    .mat-header-title { display:flex;align-items:center;gap:10px;color:#fff;font-size:15px;font-weight:700; }
    .mat-header-title i { font-size:18px;opacity:.9; }
    .mat-header-stats { display:flex;gap:18px;align-items:center; }
    .mat-stat { text-align:center;color:rgba(255,255,255,.75);font-size:11px; }
    .mat-stat strong { display:block;color:#fff;font-size:14px;font-weight:700; }
    .mat-stat-sep { width:1px;height:30px;background:rgba(255,255,255,.2); }
    .btn-add-mat {
        display:flex;align-items:center;gap:6px;padding:7px 16px;
        background:rgba(255,255,255,.15);border:1.5px solid rgba(255,255,255,.35);
        border-radius:10px;color:#fff;font-size:12.5px;font-weight:600;
        cursor:pointer;transition:all .18s;
    }
    .btn-add-mat:hover { background:rgba(255,255,255,.28);transform:translateY(-1px); }

    /* Table */
    #matTable { width:100%;border-collapse:collapse; }
    #matTable thead tr { background:#f7f9fc;border-bottom:2px solid #e2e9f5; }
    #matTable thead th {
        font-size:10.5px;font-weight:700;color:#64748b;
        text-transform:uppercase;letter-spacing:.6px;
        padding:9px 12px;white-space:nowrap;
    }
    #matTable tbody tr { border-bottom:1px solid #f0f4fb;transition:background .12s; }
    #matTable tbody tr:last-child { border-bottom:none; }
    #matTable tbody tr:hover { background:#f8faff; }
    #matTable tbody td { padding:6px 10px;vertical-align:middle; }

    /* Left accent */
    #matTable tbody tr { position:relative; }
    #matTable tbody tr td:first-child { padding-left:18px; }
    #matTable tbody tr::before {
        content:'';position:absolute;left:0;top:8px;bottom:8px;
        width:3px;border-radius:0 3px 3px 0;background:#93c5fd;
    }
    #matTable tbody tr.is-units::before { background:#6ee7b7; }

    /* Row number badge */
    .mat-num {
        width:22px;height:22px;border-radius:50%;
        background:#f1f5f9;color:#64748b;
        font-size:11px;font-weight:700;
        display:inline-flex;align-items:center;justify-content:center;
    }

    /* Detail cells */
    .det-loose {
        display:inline-block;
        background:linear-gradient(135deg,#dbeafe,#bfdbfe);
        color:#1d4ed8;font-weight:700;font-size:14px;
        padding:4px 12px;border-radius:8px;
        border:1px solid #93c5fd;letter-spacing:.3px;
        min-width:80px;text-align:center;
    }
    .det-units {
        display:inline-block;font-size:12.5px;font-weight:500;color:#374151;
        background:#f8faff;border:1px solid #e2e8f0;
        padding:4px 10px;border-radius:8px;white-space:nowrap;
    }
    .det-units .du-qty  { font-weight:700;color:#1d4ed8; }
    .det-units .du-unit { font-weight:600;color:#6366f1;margin:0 2px; }
    .det-units .du-op   { color:#94a3b8;margin:0 4px;font-size:12px; }
    .det-units .du-kg   { color:#374151; }
    .det-units .du-tot  { font-weight:700;color:#15803d;margin-left:4px; }

    /* Amount cell */
    .amt-td {
        background:linear-gradient(135deg,#eef3ff,#e0e9ff);
        border-radius:8px;
        font-weight:700;color:#1e3a5f;font-size:13px;
        text-align:right;padding:7px 10px !important;
        border:1px solid #c7d5fa;white-space:nowrap;
    }

    /* Action buttons */
    .mat-edit-btn, .mat-del-btn {
        width:28px;height:28px;border-radius:7px;
        display:inline-flex;align-items:center;justify-content:center;
        border:1.5px solid;cursor:pointer;transition:all .15s;
    }
    .mat-edit-btn { background:#eff6ff;border-color:#bfdbfe;color:#2563eb; }
    .mat-edit-btn:hover { background:#dbeafe;transform:scale(1.1); }
    .mat-del-btn  { background:#fff0f0;border-color:#fecaca;color:#ef4444;margin-left:4px; }
    .mat-del-btn:hover  { background:#fee2e2;transform:scale(1.1); }

    /* Totals bar */
    .mat-totals-bar {
        display:flex;align-items:center;justify-content:flex-end;
        gap:8px;padding:10px 16px;
        background:linear-gradient(135deg,#eef3ff,#e8f0ff);
        border-top:1.5px solid #c7d5fa;
    }
    .mat-total-chip {
        display:flex;align-items:center;gap:8px;
        background:#fff;border:1.5px solid #c7d5fa;
        border-radius:10px;padding:5px 14px;
    }
    .mat-total-chip label {
        font-size:10.5px;font-weight:700;color:#64748b;
        text-transform:uppercase;letter-spacing:.5px;margin:0;white-space:nowrap;
    }
    .mat-total-chip input {
        width:120px;border:none !important;background:transparent !important;
        font-weight:800;color:#1e3a5f;font-size:14px;
        text-align:right;padding:0;box-shadow:none !important;
    }

    /* ══ Material Modal ══ */
    #matModal .modal-content {
        border:none;border-radius:18px;overflow:hidden;
        box-shadow:0 20px 60px rgba(30,58,95,.25);
    }
    #matModal .modal-header {
        background:linear-gradient(135deg,#1e3a5f,#2563eb);
        border:none;padding:16px 24px;
    }
    #matModal .modal-title { color:#fff;font-weight:700;font-size:15px; }
    #matModal .btn-close { filter:invert(1);opacity:.8; }
    #matModal .modal-body { padding:24px; }
    #matModal .modal-footer {
        border-top:1px solid #e8edf5;padding:14px 24px;
        background:#f8faff;
    }
    .modal-type-seg {
        display:inline-flex;border-radius:10px;
        border:2px solid #e2e8f0;overflow:hidden;width:100%;
    }
    .modal-type-btn {
        flex:1;padding:9px 0;font-size:13px;font-weight:700;
        border:none;background:#f8fafc;cursor:pointer;
        transition:all .18s;color:#64748b;
    }
    .modal-type-btn.active-loose { background:linear-gradient(135deg,#dbeafe,#bfdbfe);color:#1d4ed8; }
    .modal-type-btn.active-units { background:linear-gradient(135deg,#dcfce7,#bbf7d0);color:#15803d; }
    .modal-field-label {
        font-size:11px;font-weight:700;color:#64748b;
        text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;
    }
    #modalPreview {
        background:linear-gradient(135deg,#eef3ff,#e8f0ff);
        border:1.5px solid #c7d5fa;border-radius:12px;
        padding:12px 16px;margin-top:8px;
    }
    #modalPreview .prev-formula { font-size:13px;color:#374151;margin-bottom:4px; }
    #modalPreview .prev-amount  { font-size:18px;font-weight:800;color:#1e3a5f; }

    .charge-input { font-weight:600; }
</style>

<div class="main-content app-content">
    <div class="container-fluid">

        <!-- ══ Page Header ══ -->
        <div class="page-header-card">
            <div>
                <div class="ph-title">
                    <i class="ri-map-pin-line"></i>
                    <?= $pageTitle ?>
                    <span class="badge" style="background:rgba(255,255,255,0.25);color:#fff;font-size:11px;">Regular Trip</span>
                </div>
                <div class="ph-breadcrumb">
                    <a href="/Sama_Roadlines/index.php">Dashboard</a><span>›</span>
                    <a href="RegularTrips.php">Regular Trips</a><span>›</span>
                    <?= $pageTitle ?>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">

                <a href="RegularTrips.php" class="btn btn-outline-light fw-semibold" style="border-radius:9px;height:38px;font-size:13px;">
                    <i class="ri-arrow-left-line me-1"></i> Back
                </a>
            </div>
        </div>

        <form id="tripForm">
            <input type="hidden" name="saveTrip" value="1">
            <input type="hidden" name="TripId" value="<?= $isEdit ? $tripId : '' ?>">
            <input type="hidden" name="TripType" value="Regular">

            <div class="row g-3">

                <!-- ══ LEFT ══ -->
                <div class="col-xl-8">

                    <!-- ① Basic Details -->
                    <div class="trip-card">
                        <div class="trip-card-head">
                            <div class="trip-card-title"><i class="ri-file-list-3-line"></i>Basic Details</div>
                        </div>
                        <div class="trip-card-body">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label fw-semibold fs-13">Trip Date <span class="req">*</span></label>
                                    <input type="date" name="TripDate" class="form-control" value="<?= $isEdit ? fv("TripDate", $editTrip) : date("Y-m-d") ?>">
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label fw-semibold fs-13">Vehicle <span class="req">*</span></label>
                                    <select name="VehicleId" id="sel_Vehicle" class="form-select">
                                        <option value="">-- Search Vehicle --</option>
                                        <?php foreach ($activeVehicles as $v): ?>
                                            <option value="<?= $v["VehicleId"] ?>" <?= (($editTrip["VehicleId"] ?? "") == $v["VehicleId"]) ? "selected" : "" ?>>
                                                <?= htmlspecialchars($v["VehicleNumber"]) ?><?= $v["VehicleName"] ? " – " . htmlspecialchars($v["VehicleName"]) : "" ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold fs-13">Party Invoice No.</label>
                                    <input type="text" name="InvoiceNo" class="form-control"
                                        value="<?= $isEdit ? fv("InvoiceNo", $editTrip) : "" ?>" placeholder="Party's invoice/bill no.">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold fs-13">Invoice Date</label>
                                    <input type="date" name="InvoiceDate" class="form-control"
                                        value="<?= $isEdit ? fv("InvoiceDate", $editTrip) : "" ?>">
                                </div>
                                <div class="col-md-4">
                                    <?php $fps = $isEdit ? ($editTrip['FreightType'] ?? 'Regular') : 'Regular'; ?>
                                    <label class="form-label fw-semibold fs-13">Bhadu Type</label>
                                    <select name="FreightType" id="freightTypeSelect" class="form-select fw-bold" onchange="toggleVasuli(this.value)">
                                        <option value="Regular" <?= $fps==='Regular' ? 'selected' : '' ?> style="color:#1d4ed8;">🔵 Regular</option>
                                        <option value="ToPay"   <?= $fps==='ToPay'   ? 'selected' : '' ?> style="color:#15803d;">🟡 ToPay</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ② Party Details -->
                    <div class="trip-card">
                        <div class="trip-card-head">
                            <div class="trip-card-title"><i class="ri-group-line" style="color:#0891b2;"></i>Party Details</div>
                            <span class="badge bg-primary" style="font-size:10px;">Regular Trip</span>
                        </div>
                        <div class="trip-card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold fs-13">Consigner <small class="text-muted fw-normal">(Sender)</small></label>
                                    <select name="ConsignerId" id="sel_Consigner" class="form-select">
                                        <option value="">-- Search Consigner --</option>
                                        <?php foreach ($consigners as $p): ?>
                                            <option value="<?= $p["PartyId"] ?>" <?= (($editTrip["ConsignerId"] ?? "") == $p["PartyId"]) ? "selected" : "" ?>>
                                                <?= htmlspecialchars($p["PartyName"]) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold fs-13">Consignee Name <small class="text-muted fw-normal">(Receiver)</small></label>
                                    <input type="text" name="ConsigneeName" class="form-control"
                                        value="<?= $isEdit ? fv("ConsigneeName", $editTrip) : "" ?>" placeholder="Full Name">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold fs-13">Consignee Contact No.</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="ri-phone-line"></i></span>
                                        <input type="text" name="ConsigneeContactNo" class="form-control" maxlength="10"
                                            value="<?= $isEdit ? fv("ConsigneeContactNo", $editTrip) : "" ?>" placeholder="10 digits">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold fs-13">Consignee City</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="ri-map-pin-line"></i></span>
                                        <input type="text" name="ConsigneeCity" class="form-control" maxlength="10"
                                            value="<?= $isEdit ? fv("ConsigneeCity", $editTrip) : "" ?>" placeholder="City name">
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold fs-13">Consignee Address</label>
                                    <input type="text" name="ConsigneeAddress" class="form-control"
                                        value="<?= $isEdit ? fv("ConsigneeAddress", $editTrip) : "" ?>" placeholder="Full Address">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold fs-13">From Location</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="ri-map-pin-2-line text-primary"></i></span>
                                        <input type="text" name="FromLocation" class="form-control"
                                            value="<?= $isEdit ? fv("FromLocation", $editTrip) : "" ?>" placeholder="e.g. Surat">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold fs-13">To Location</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="ri-map-pin-line text-danger"></i></span>
                                        <input type="text" name="ToLocation" class="form-control"
                                            value="<?= $isEdit ? fv("ToLocation", $editTrip) : "" ?>" placeholder="e.g. Mumbai">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ③ Driver Details -->
                    <div class="trip-card">
                        <div class="trip-card-head">
                            <div class="trip-card-title"><i class="ri-steering-line" style="color:#16a34a;"></i>Driver Details</div>
                            <span style="font-size:11px;color:#94a3b8;">Optional</span>
                        </div>
                        <div class="trip-card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold fs-13">Driver Name</label>
                                    <input type="text" name="DriverName" class="form-control" value="<?= $isEdit ? fv("DriverName", $editTrip) : "" ?>" placeholder="Full name">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold fs-13">Contact No</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="ri-phone-line"></i></span>
                                        <input type="text" name="DriverContactNo" class="form-control" maxlength="10" value="<?= $isEdit ? fv("DriverContactNo", $editTrip) : "" ?>" placeholder="10 digits">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold fs-13">Aadhar No</label>
                                    <input type="text" name="DriverAadharNo" class="form-control" maxlength="12" value="<?= $isEdit ? fv("DriverAadharNo", $editTrip) : "" ?>" placeholder="12 digits">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold fs-13">Driver Address</label>
                                    <input type="text" name="DriverAddress" class="form-control" value="<?= $isEdit ? fv("DriverAddress", $editTrip) : "" ?>" placeholder="Address">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ④ Remarks -->
                    <div class="trip-card">
                        <div class="trip-card-head">
                            <div class="trip-card-title"><i class="ri-chat-1-line" style="color:#94a3b8;"></i>Remarks</div>
                        </div>
                        <div class="trip-card-body">
                            <textarea name="Remarks" class="form-control" rows="2" placeholder="Any notes or remarks..."><?= $isEdit ? fv("Remarks", $editTrip) : "" ?></textarea>
                        </div>
                    </div>

                    <div class="mat-wrap">

                        <!-- Header -->
                        <div class="mat-header">
                            <div class="mat-header-title">
                                <i class="ri-stack-line"></i>
                                Goods / Materials
                            </div>
                            <div class="mat-header-stats">
                                <div class="mat-stat"><strong id="hdr-rows">–</strong>Items</div>
                                <div class="mat-stat-sep"></div>
                                <div class="mat-stat"><strong id="hdr-wt">–</strong>Total Wt (T)</div>
                                <div class="mat-stat-sep"></div>
                                <div class="mat-stat"><strong id="hdr-amt">–</strong>Total Amt</div>
                                <div class="mat-stat-sep"></div>
                                <button type="button" class="btn-add-mat" onclick="openMatModal()">
                                    <i class="ri-add-circle-line" style="font-size:15px;"></i>Add Material
                                </button>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="table-responsive">
                            <table id="matTable">
                                <thead>
                                    <tr>
                                        <th style="width:36px;">#</th>
                                        <th style="width:130px;">Material Name</th>
                                        <th>Weight / Details</th>
                                        <th style="width:105px;" class="text-end">Rate (Rs.)</th>
                                        <th style="width:120px;" class="text-end">Amount (Rs.)</th>
                                        <th style="width:68px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="matBody">
                                <?php
                                $matList = !empty($materials) ? $materials : [];
                                $rn = 1;
                                foreach ($matList as $m):
                                    $isUnits  = ($m['MaterialType'] ?? 'Loose') === 'Units';
                                    $mName    = htmlspecialchars($m['MaterialName'] ?? '');
                                    $mWt      = floatval($m['Weight'] ?? 0);
                                    $mQty     = intval($m['Quantity'] ?? 0);
                                    $mUnit    = htmlspecialchars($m['UnitType'] ?? '');
                                    $mWpu     = floatval($m['WeightPerUnit'] ?? 0);
                                    $mTw      = floatval($m['TotalWeight'] ?? 0);
                                    $mRate    = floatval($m['Rate'] ?? 0);
                                    $mAmt     = floatval($m['Amount'] ?? 0);
                                    $mWpuKg   = round($mWpu * 1000, 3);
                                    $mTwKg    = round($mTw * 1000, 1);
                                    $mTwT     = number_format($mTw, 3);
                                ?>
                                <tr class="<?= $isUnits ? 'is-units' : '' ?>"
                                    data-name="<?= $mName ?>" data-type="<?= $isUnits?'Units':'Loose' ?>"
                                    data-wt="<?= $mWt ?>" data-qty="<?= $mQty ?>" data-unit="<?= $mUnit ?>"
                                    data-wpu="<?= $mWpu ?>" data-tw="<?= $mTw ?>"
                                    data-rate="<?= $mRate ?>" data-amt="<?= $mAmt ?>">
                                    <td><span class="mat-num"><?= $rn++ ?></span></td>
                                    <td style="font-weight:600;color:#1e293b;"><?= $mName ?></td>
                                    <td>
                                        <?php if ($isUnits): ?>
                                        <span class="det-units">
                                            <span class="du-qty"><?= $mQty ?></span>
                                            <span class="du-unit"><?= $mUnit ?: 'unit' ?></span>
                                            <span class="du-op">×</span>
                                            <span class="du-kg"><?= $mWpuKg ?> kg</span>
                                            <span class="du-op">=</span>
                                            <span class="du-kg"><?= number_format($mTwKg, 0) ?> kg</span>
                                            <span class="du-tot">(<?= $mTwT ?> T)</span>
                                        </span>
                                        <?php else: ?>
                                        <span class="det-loose"><?= number_format($mWt,3) ?> T</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end" style="color:#374151;"><?= number_format($mRate,2) ?></td>
                                    <td class="amt-td">₹&nbsp;<?= number_format($mAmt,2) ?></td>
                                    <td>
                                        <input type="hidden" name="MaterialName[]"    value="<?= $mName ?>">
                                        <input type="hidden" name="MaterialType[]"    value="<?= $isUnits?'Units':'Loose' ?>">
                                        <input type="hidden" name="Weight[]"          value="<?= $isUnits ? $mTw : $mWt ?>">
                                        <input type="hidden" name="Quantity[]"        value="<?= $mQty ?>">
                                        <input type="hidden" name="UnitType[]"        value="<?= $mUnit ?>">
                                        <input type="hidden" name="WeightPerUnit[]"   value="<?= $mWpu ?>">
                                        <input type="hidden" name="TotalWeight[]"     value="<?= $mTw ?>">
                                        <input type="hidden" name="Rate[]"            value="<?= $mRate ?>">
                                        <input type="hidden" name="Amount[]"          value="<?= $mAmt ?>">
                                        <button type="button" class="mat-edit-btn" onclick="editRow(this)" title="Edit">
                                            <i class="ri-pencil-line" style="font-size:12px;"></i>
                                        </button>
                                        <button type="button" class="mat-del-btn" onclick="delRow(this)" title="Delete">
                                            <i class="ri-delete-bin-line" style="font-size:12px;"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Totals Bar -->
                        <div class="mat-totals-bar">
                            <div class="mat-total-chip">
                                <label>Total Weight (T)</label>
                                <input id="totWt" class="form-control" readonly placeholder="0.000">
                            </div>
                            <div class="mat-total-chip" style="border-color:#a5b4fc;background:linear-gradient(135deg,#eef3ff,#e0e9ff);">
                                <label style="color:#4338ca;">Total Amount</label>
                                <input id="matAmt" class="form-control" readonly placeholder="₹ 0.00" style="color:#1e3a5f;font-size:15px;">
                            </div>
                        </div>

                    </div><!-- /.mat-wrap -->

                    <!-- ══ ADD/EDIT MATERIAL MODAL ══ -->
                    <div class="modal fade" id="matModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="matModalTitle">
                                        <i class="ri-add-circle-line me-2"></i>Add Material
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" id="matEditIdx" value="-1">

                                    <!-- Material Name -->
                                    <div class="mb-3">
                                        <div class="modal-field-label">Material Name</div>
                                        <input type="text" id="m_name" class="form-control"
                                            placeholder="e.g. Wheat, Rice, Cement, Iron Rods...">
                                    </div>

                                    <!-- Type -->
                                    <div class="mb-3">
                                        <div class="modal-field-label">Material Type</div>
                                        <div class="modal-type-seg">
                                            <button type="button" class="modal-type-btn active-loose"
                                                id="typeBtnLoose" onclick="modalSetType('Loose')">
                                                ⚖ Loose (by weight)
                                            </button>
                                            <button type="button" class="modal-type-btn"
                                                id="typeBtnUnits" onclick="modalSetType('Units')">
                                                📦 Units (bags/boxes)
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Loose Fields -->
                                    <div id="looseFields">
                                        <div class="mb-3">
                                            <div class="modal-field-label">Weight (Tonnes)</div>
                                            <div class="input-group">
                                                <input type="number" id="m_wt" class="form-control form-control-lg"
                                                    step="0.001" min="0" placeholder="0.000"
                                                    style="font-size:20px;font-weight:700;text-align:center;"
                                                    oninput="modalCalc()">
                                                <span class="input-group-text" style="font-weight:700;color:#1d4ed8;background:#dbeafe;border-color:#93c5fd;">T</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Units Fields -->
                                    <div id="unitsFields" style="display:none;">
                                        <div class="row g-2 mb-3">
                                            <div class="col-4">
                                                <div class="modal-field-label">Quantity</div>
                                                <input type="number" id="m_qty" class="form-control text-center"
                                                    step="1" min="0" placeholder="0" oninput="modalCalc()">
                                            </div>
                                            <div class="col-4">
                                                <div class="modal-field-label">Unit (bag/box...)</div>
                                                <input type="text" id="m_unit" class="form-control text-center"
                                                    placeholder="bag" oninput="modalCalc()">
                                            </div>
                                            <div class="col-4">
                                                <div class="modal-field-label">Weight / Unit (kg)</div>
                                                <input type="number" id="m_wpuKg" class="form-control text-center"
                                                    step="0.001" min="0" placeholder="0.000" oninput="modalCalc()">
                                            </div>
                                        </div>
                                        <!-- Formula preview -->
                                        <div id="modalPreview" class="mb-3" style="display:none;">
                                            <div class="prev-formula" id="prevFormula"></div>
                                            <div style="font-size:11px;color:#64748b;">Total Weight</div>
                                            <div style="font-size:18px;font-weight:800;color:#15803d;" id="prevTw"></div>
                                        </div>
                                    </div>

                                    <!-- Rate + Amount -->
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <div class="modal-field-label">Rate (Rs. per Tonne)</div>
                                            <div class="input-group">
                                                <span class="input-group-text">₹</span>
                                                <input type="number" id="m_rate" class="form-control"
                                                    step="0.01" min="0" placeholder="0.00" oninput="modalCalc()">
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="modal-field-label">Amount (Auto)</div>
                                            <div class="input-group">
                                                <span class="input-group-text" style="background:#eef3ff;color:#1e3a5f;font-weight:700;">₹</span>
                                                <input type="text" id="m_amt" class="form-control"
                                                    readonly placeholder="0.00"
                                                    style="background:linear-gradient(135deg,#eef3ff,#e0e9ff);font-weight:800;color:#1e3a5f;font-size:15px;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary px-4 fw-bold" onclick="saveMatModal()"
                                        style="background:linear-gradient(135deg,#2563eb,#1d4ed8);border:none;">
                                        <i class="ri-save-line me-1"></i>
                                        <span id="matModalSaveLabel">Add to List</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /col-xl-8 -->

                <!-- ══ RIGHT ══ -->
                <div class="col-xl-4">

                    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05);margin-bottom:16px;">
                        <div class="charge-card-head">
                            <div class="title"><i class="ri-money-rupee-circle-line"></i>Charges &amp; Amounts</div>
                        </div>
                        <div class="charge-card-body">

                            <div class="sec-badge"><i class="ri-truck-line"></i>Freight</div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold fs-13">Material Total Value (Rs.)</label>
                                <input type="number" step="0.01" name="MaterialTotalValue" id="matVal" class="form-control" min="0"
                                    value="<?= $isEdit ? fm("MaterialTotalValue", $editTrip) : "0.00" ?>" placeholder="0.00">
                                <div class="form-text fs-11">Actual material value (for GST purposes)</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold fs-13">Freight Amount (Rs.) <span class="badge bg-success ms-1" style="font-size:9px;">Auto from Materials</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light fs-12">Rs.</span>
                                    <input type="number" step="0.01" name="FreightAmount" id="freightAmt" class="form-control fw-bold" min="0"
                                        value="<?= $isEdit ? fm("FreightAmount", $editTrip) : "0.00" ?>"
                                        readonly style="background:#f0f9ff;color:#1a237e;cursor:not-allowed;">
                                </div>
                                <div class="form-text fs-11"><i class="ri-lock-line"></i> Automatically calculated from Material Weight × Rate</div>
                            </div>

                            <div class="sec-badge mt-1"><i class="ri-add-circle-line"></i>Extra Charges</div>
                            <div class="mb-3">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold fs-13">Labour Charge (Rs.)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="ri-user-line text-muted"></i></span>
                                            <input type="number" step="0.01" name="LabourCharge" id="labourAmt" class="form-control charge-input" min="0"
                                                value="<?= $isEdit ? fm("LabourCharge", $editTrip) : "0.00" ?>" placeholder="0.00">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold fs-13">Holding / Detention (Rs.)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="ri-time-line text-muted"></i></span>
                                            <input type="number" step="0.01" name="HoldingCharge" id="holdingAmt" class="form-control charge-input" min="0"
                                                value="<?= $isEdit ? fm("HoldingCharge", $editTrip) : "0.00" ?>" placeholder="0.00">
                                        </div>
                                    </div>
                                </div>


                            </div>


                            <div class="mb-3">
                                <label class="form-label fw-semibold fs-13">Other Charge (Rs.)</label>
                                <div class="row g-2">
                                    <div class="col-7">
                                        <input type="number" step="0.01" name="OtherCharge" id="otherAmt" class="form-control charge-input" min="0"
                                            value="<?= $isEdit ? fm("OtherCharge", $editTrip) : "0.00" ?>" placeholder="0.00">
                                    </div>
                                    <div class="col-5">
                                        <input type="text" name="OtherChargeNote" class="form-control" placeholder="e.g. Toll"
                                            value="<?= $isEdit ? fv("OtherChargeNote", $editTrip) : "" ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="sec-badge mt-1"><i class="ri-subtract-line"></i>Deductions</div>
                            <div class="mb-3">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold fs-13">Cash Advance (Rs.)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="ri-money-rupee-circle-line text-warning"></i></span>
                                            <input type="number" step="0.01" name="CashAdvance" id="cashAdvAmt" class="form-control charge-input" min="0"
                                                value="<?= $isEdit ? fm("CashAdvance", $editTrip) : "0.00" ?>" placeholder="0.00">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold fs-13">Online Advance (Rs.)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="ri-bank-card-line text-info"></i></span>
                                            <input type="number" step="0.01" name="OnlineAdvance" id="onlineAdvAmt" class="form-control charge-input" min="0"
                                                value="<?= $isEdit ? fm("OnlineAdvance", $editTrip) : "0.00" ?>" placeholder="0.00">
                                        </div>
                                    </div>
                                </div>


                            </div>
                            <div class="mb-3">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold fs-13">TDS (Rs.)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="ri-government-line text-muted"></i></span>
                                            <input type="number" step="0.01" name="TDS" id="tdsAmt" class="form-control charge-input" min="0"
                                                value="<?= $isEdit ? fm("TDS", $editTrip) : "0.00" ?>" placeholder="0.00">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold fs-13">Commission Amount (Rs.)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="ri-percent-line text-primary"></i></span>
                                            <input type="number" step="0.01" name="CommissionAmount" id="commAmt" class="form-control charge-input" min="0"
                                                value="<?= $isEdit ? fm("CommissionAmount", $editTrip) : "0.00" ?>" placeholder="0.00">
                                        </div>
                                    </div>
                                </div>
                                <!-- Vasuli -->
                                <div class="row g-2 mt-1">
                                    <div class="col-7">
                                        <label class="form-label fw-semibold fs-13">Vasuli Amount (Rs.)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="ri-hand-coin-line text-success"></i></span>
                                            <input type="number" step="0.01" name="VasuliAmount" id="vasuliAmt" class="form-control charge-input" min="0"
                                                value="<?= $isEdit ? fm("VasuliAmount", $editTrip) : "" ?>" placeholder="0.00">
                                        </div>
                                    </div>
                                    <div class="col-5">
                                        <label class="form-label fw-semibold fs-13">Recover From</label>
                                        <select name="VasuliRecoverFrom" id="vasuliRecoverFrom" class="form-select">
                                            <option value="Other" <?= ($isEdit && ($editTrip['RecoverFrom']??'')=='Other') ? 'selected' : '' ?>>Other</option>
                                            <option value="Owner" <?= ($isEdit && ($editTrip['RecoverFrom']??'')=='Owner') ? 'selected' : '' ?>>Owner</option>
                                        </select>
                                    </div>
                                </div>


                            </div>


                            <!-- Summary -->
                            <div class="summary-box">
                                <table class="table table-sm mb-0" style="font-size:13px;">
                                    <tr>
                                        <td class="text-muted">🚛 Freight</td>
                                        <td class="text-end fw-medium" id="sum_freight">Rs.0.00</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">👷 Labour</td>
                                        <td class="text-end fw-medium" id="sum_labour">Rs.0.00</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">⏱️ Holding</td>
                                        <td class="text-end fw-medium" id="sum_holding">Rs.0.00</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">➕ Other</td>
                                        <td class="text-end fw-medium" id="sum_other">Rs.0.00</td>
                                    </tr>
                                    <tr style="border-top:2px solid #c7d7fc;">
                                        <td class="fw-bold pt-2">📊 Total</td>
                                        <td class="text-end fw-bold pt-2" id="sum_total">Rs.0.00</td>
                                    </tr>
                                    <tr>
                                        <td class="text-danger">➖ Cash Advance</td>
                                        <td class="text-end text-danger" id="sum_cash_adv">Rs.0.00</td>
                                    </tr>
                                    <tr>
                                        <td class="text-danger">➖ Online Advance</td>
                                        <td class="text-end text-danger" id="sum_online_adv">Rs.0.00</td>
                                    </tr>
                                    <tr>
                                        <td class="text-danger">➖ TDS</td>
                                        <td class="text-end text-danger" id="sum_tds">Rs.0.00</td>
                                    </tr>
                                </table>
                                <div class="net-label mt-2">Net Payable Amount</div>
                                <div class="net-amount" id="sum_net">Rs.0.00</div>
                                <input type="hidden" name="TotalAmount" id="hidTotal">
                                <input type="hidden" name="NetAmount" id="hidNet">
                            </div>
                        </div>
                    </div>

                    <!-- Save -->
                    <div class="save-btn-card">
                        <div style="font-size:13px;font-weight:700;color:#1a237e;margin-bottom:14px;">
                            <i class="ri-save-line me-1"></i><?= $isEdit ? "Update Trip Details" : "Save New Regular Trip" ?>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold" id="saveBtn" style="border-radius:10px;">
                                <i class="ri-save-line me-1"></i><?= $isEdit ? "Update Trip" : "Save Trip" ?>
                            </button>
                            <?php if ($isEdit): ?>
                                <button type="button" class="btn btn-outline-dark fw-semibold" style="border-radius:10px;"
                                    onclick="window.open('GCNote_print.php?TripId=<?= $tripId ?>','_blank','width=950,height=720')">
                                    <i class="ri-printer-line me-1"></i>Print GC Note
                                </button>
                            <?php endif; ?>
                            <a href="RegularTrips.php" class="btn btn-outline-secondary" style="border-radius:10px;">
                                <i class="ri-close-line me-1"></i>Cancel
                            </a>
                        </div>
                    </div>

                </div><!-- /col-xl-4 -->
            </div><!-- /row -->

        </form>
    </div>
</div>

<script>
    window.addEventListener("offline", () => {
        if (typeof SRV !== "undefined") SRV.toast.warning("Internet Disconnected!");
    });
    window.addEventListener("online", () => {
        if (typeof SRV !== "undefined") SRV.toast.success("Back Online!");
    });

    var freightManual = false; // always auto — kept for compatibility

    $(document).ready(function() {
        ["#sel_Vehicle", "#sel_Consigner"].forEach(s => {
            if ($(s).length) $(s).select2({
                theme: "bootstrap-5",
                allowClear: true,
                placeholder: "Search & Select",
                width: "100%"
            });
        });
        calcAll();
    });

    // ─── Material Modal Logic ───────────────────────────────────────
    var matModalBS;
    /* ── Vasuli enable/disable based on FreightType (Bhadu Type) ── */
    var _freightType = '<?= $fps ?>';

    function toggleVasuli(type) {
        var isRegular = (type !== 'ToPay');
        $('#vasuliAmt').prop('disabled', isRegular);
        $('#vasuliRecoverFrom').prop('disabled', isRegular);
        $('#vasuliAmt').closest('.input-group').css('opacity', isRegular ? '0.45' : '1');
        $('#vasuliRecoverFrom').css('opacity', isRegular ? '0.45' : '1');
        if (isRegular) {
            $('#vasuliAmt').val('');
            updateSummary();
        }
    }

    $(function() {
        matModalBS = new bootstrap.Modal(document.getElementById("matModal"));
        /* Apply on page load */
        toggleVasuli(_freightType);
    });

    var mCurType = "Loose"; // modal current type

    function openMatModal() {
        $("#matModalTitle").html('<i class="ri-add-circle-line me-2"></i>Add Material');
        $("#matModalSaveLabel").text("Add to List");
        $("#matEditIdx").val("-1");
        // clear
        $("#m_name").val("");
        $("#m_wt").val("");
        $("#m_qty").val("");
        $("#m_unit").val("");
        $("#m_wpuKg").val("");
        $("#m_rate").val("");
        $("#m_amt").val("");
        $("#modalPreview").hide();
        modalSetType("Loose");
        matModalBS.show();
        setTimeout(function(){ $("#m_name").focus(); }, 400);
    }

    function editRow(btn) {
        var tr  = $(btn).closest("tr");
        var idx = $("#matBody tr").index(tr);
        var d   = tr.data();
        $("#matModalTitle").html('<i class="ri-pencil-line me-2"></i>Edit Material');
        $("#matModalSaveLabel").text("Update");
        $("#matEditIdx").val(idx);
        $("#m_name").val(d.name || "");
        $("#m_rate").val(d.rate || "");
        modalSetType(d.type || "Loose");
        if (d.type === "Units") {
            $("#m_qty").val(d.qty || "");
            $("#m_unit").val(d.unit || "");
            // convert T to kg for display
            var wpuKg = parseFloat(d.wpu || 0) * 1000;
            $("#m_wpuKg").val(wpuKg > 0 ? wpuKg.toFixed(3) : "");
        } else {
            $("#m_wt").val(d.wt || "");
        }
        modalCalc();
        matModalBS.show();
    }

    function modalSetType(type) {
        mCurType = type;
        if (type === "Loose") {
            $("#typeBtnLoose").addClass("active-loose").removeClass("active-units");
            $("#typeBtnUnits").removeClass("active-loose active-units");
            $("#looseFields").show();
            $("#unitsFields").hide();
        } else {
            $("#typeBtnUnits").addClass("active-units").removeClass("active-loose");
            $("#typeBtnLoose").removeClass("active-loose active-units");
            $("#looseFields").hide();
            $("#unitsFields").show();
        }
        modalCalc();
    }

    function fmt(n, d) { return n.toLocaleString("en-IN", {minimumFractionDigits:d, maximumFractionDigits:d}); }

    function modalCalc() {
        var rate = parseFloat($("#m_rate").val()) || 0;
        var amt = 0, tw = 0;
        if (mCurType === "Units") {
            var qty = parseFloat($("#m_qty").val()) || 0;
            var unit = $("#m_unit").val().trim() || "unit";
            var wpuKg = parseFloat($("#m_wpuKg").val()) || 0;
            var wpuT  = wpuKg / 1000;
            tw = qty * wpuT;
            var twKg = qty * wpuKg;
            amt = tw * rate;
            if (qty > 0 && wpuKg > 0) {
                $("#prevFormula").html(
                    "<b>" + fmt(qty,0) + "</b> " + unit + " × " +
                    "<b>" + fmt(wpuKg,3) + " kg</b> = " +
                    "<b>" + fmt(twKg,1) + " kg</b>"
                );
                $("#prevTw").text(fmt(tw,3) + " T");
                $("#modalPreview").show();
            } else {
                $("#modalPreview").hide();
            }
        } else {
            tw = parseFloat($("#m_wt").val()) || 0;
            amt = tw * rate;
        }
        $("#m_amt").val(amt > 0 ? "₹ " + fmt(amt,2) : "");
    }

    function saveMatModal() {
        var name = $("#m_name").val().trim();
        if (!name) { $("#m_name").focus(); return; }
        var rate = parseFloat($("#m_rate").val()) || 0;
        var wt=0, qty=0, unit="", wpuT=0, tw=0, amt=0, wpuKg=0;

        if (mCurType === "Units") {
            qty   = parseFloat($("#m_qty").val()) || 0;
            unit  = $("#m_unit").val().trim();
            wpuKg = parseFloat($("#m_wpuKg").val()) || 0;
            wpuT  = wpuKg / 1000;
            tw    = qty * wpuT;
            amt   = tw * rate;
        } else {
            wt    = parseFloat($("#m_wt").val()) || 0;
            amt   = wt * rate;
            tw    = 0;
        }

        var idx = parseInt($("#matEditIdx").val());

        // Build display HTML
        var detHtml;
        if (mCurType === "Units") {
            var twKg = qty * wpuKg;
            detHtml = '<span class="det-units">'
                + '<span class="du-qty">' + fmt(qty,0) + '</span> '
                + '<span class="du-unit">' + (unit||"unit") + '</span>'
                + '<span class="du-op">×</span>'
                + '<span class="du-kg">' + fmt(wpuKg,3) + ' kg</span>'
                + '<span class="du-op">=</span>'
                + '<span class="du-kg">' + fmt(twKg,1) + ' kg</span>'
                + '<span class="du-tot">(' + fmt(tw,3) + ' T)</span>'
                + '</span>';
        } else {
            detHtml = '<span class="det-loose">' + fmt(wt,3) + ' T</span>';
        }

        var amtHtml = '₹&nbsp;' + fmt(amt,2);
        var typeStr = mCurType;
        var wtSubmit = mCurType === "Units" ? tw : wt;

        var hiddens = ''
            + '<input type="hidden" name="MaterialName[]"  value="' + name + '">'
            + '<input type="hidden" name="MaterialType[]"  value="' + typeStr + '">'
            + '<input type="hidden" name="Weight[]"        value="' + wtSubmit + '">'
            + '<input type="hidden" name="Quantity[]"      value="' + qty + '">'
            + '<input type="hidden" name="UnitType[]"      value="' + unit + '">'
            + '<input type="hidden" name="WeightPerUnit[]" value="' + wpuT + '">'
            + '<input type="hidden" name="TotalWeight[]"   value="' + tw + '">'
            + '<input type="hidden" name="Rate[]"          value="' + rate + '">'
            + '<input type="hidden" name="Amount[]"        value="' + amt + '">';

        var actions = ''
            + '<button type="button" class="mat-edit-btn" onclick="editRow(this)" title="Edit">'
            + '  <i class="ri-pencil-line" style="font-size:12px;"></i></button>'
            + '<button type="button" class="mat-del-btn" onclick="delRow(this)" title="Delete">'
            + '  <i class="ri-delete-bin-line" style="font-size:12px;"></i></button>';

        var newRow = $('<tr class="' + (mCurType==="Units"?"is-units":"") + '"'
            + ' data-name="' + name + '" data-type="' + typeStr + '"'
            + ' data-wt="' + wt + '" data-qty="' + qty + '" data-unit="' + unit + '"'
            + ' data-wpu="' + wpuT + '" data-tw="' + tw + '"'
            + ' data-rate="' + rate + '" data-amt="' + amt + '">'
            + '<td><span class="mat-num">?</span></td>'
            + '<td style="font-weight:600;color:#1e293b;">' + name + '</td>'
            + '<td>' + detHtml + '</td>'
            + '<td class="text-end" style="color:#374151;">' + fmt(rate,2) + '</td>'
            + '<td class="amt-td">' + amtHtml + '</td>'
            + '<td>' + hiddens + actions + '</td>'
            + '</tr>');

        if (idx >= 0) {
            $("#matBody tr").eq(idx).replaceWith(newRow);
        } else {
            $("#matBody").append(newRow);
        }
        reNumber();
        calcAll();
        matModalBS.hide();
    }

    function reNumber() {
        $("#matBody tr").each(function(i) {
            $(this).find(".mat-num").text(i + 1);
        });
    }

    function delRow(btn) {
        $(btn).closest("tr").remove();
        reNumber();
        calcAll();
    }

    function fmtNum(n) { return n.toLocaleString("en-IN", {minimumFractionDigits:2, maximumFractionDigits:2}); }

    function calcAll() {
        var tw = 0, ta = 0, rows = 0;
        $("#matBody tr").each(function() {
            var d = $(this).data();
            var t = parseFloat(d.type === "Units" ? d.tw : d.wt) || 0;
            tw += t;
            ta += parseFloat(d.amt) || 0;
            if ((d.name||"").trim()) rows++;
        });
        $("#totWt").val(tw > 0 ? tw.toFixed(3) : "");
        $("#matAmt").val(ta > 0 ? "₹ " + fmtNum(ta) : "");
        $("#hdr-rows").text(rows || "–");
        $("#hdr-wt").text(tw > 0 ? tw.toFixed(2) + " T" : "–");
        $("#hdr-amt").text(ta > 0 ? "₹ " + fmtNum(ta) : "–");
        $("#freightAmt").val(ta.toFixed(2));
        calcTotal();
    }

            $(document).on("input", "#labourAmt,#holdingAmt,#otherAmt,#cashAdvAmt,#onlineAdvAmt,#tdsAmt,#commAmt", calcTotal);

    function f(id) {
        return parseFloat($(id).val()) || 0;
    }

    function rupee(n) {
        return "Rs." + n.toLocaleString("en-IN", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function calcTotal() {
        var fr = f("#freightAmt"),
            la = f("#labourAmt"),
            ho = f("#holdingAmt"),
            ot = f("#otherAmt");
        var ca = f("#cashAdvAmt"),
            oa = f("#onlineAdvAmt"),
            td = f("#tdsAmt"),
            cm = f("#commAmt");
        var ad = ca + oa;
        var total = fr + la + ho + ot;
        var net = total - ad - td;
        $("#sum_freight").text(rupee(fr));
        $("#sum_labour").text(rupee(la));
        $("#sum_holding").text(rupee(ho));
        $("#sum_other").text(rupee(ot));
        $("#sum_total").text(rupee(total));
        $("#sum_cash_adv").text(rupee(ca));
        $("#sum_online_adv").text(rupee(oa));
        $("#sum_tds").text(rupee(td));
        // Commission deduction in summary
        var $cmRow = $("#sum_comm_row");
        if (cm > 0) {
            if (!$cmRow.length) {
                $("#sum_tds").closest("tr").after(`<tr id="sum_comm_row"><td class="text-danger">➖ Commission</td><td class="text-end text-danger" id="sum_comm">Rs.0.00</td></tr>`);
            }
            $("#sum_comm").text(rupee(cm));
        } else {
            $cmRow.remove();
        }
        $("#sum_net").text(rupee(net));
        document.getElementById("sum_net").style.color = net < 0 ? "#dc2626" : "#1a237e";
        $("#hidTotal").val(total.toFixed(2));
        $("#hidNet").val(net.toFixed(2));
    }
    $("#tripForm").on("submit", function(e) {
        e.preventDefault();
        if (!navigator.onLine) {
            Swal.fire({
                icon: "warning",
                title: "No Internet"
            });
            return;
        }
        if (!$("#sel_Vehicle").val()) {
            Swal.fire({
                icon: "warning",
                title: "Vehicle Required",
                text: "Please select a vehicle.",
                confirmButtonColor: "#1a237e"
            });
            return;
        }
        const btn = document.getElementById("saveBtn"),
            orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
        Swal.fire({
            title: "Saving, please wait...",
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
        fetch("RegularTripForm.php", {
                method: "POST",
                body: new FormData(this)
            })
            .then(async r => {
                const t = await r.text();
                try {
                    return JSON.parse(t);
                } catch (e) {
                    throw new Error(t);
                }
            })
            .then(res => {
                Swal.close();
                btn.disabled = false;
                btn.innerHTML = orig;
                if (res.status === "success") {
                    Swal.fire({
                            icon: "success",
                            title: res.tripId ? "Trip Saved!" : "Trip Updated!",
                            timer: 1200,
                            showConfirmButton: false
                        })
                        .then(() => {
                            window.location.href = "RegularTrips.php";
                        });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Could not save.",
                        confirmButtonColor: "#1a237e"
                    });
                }
            })
            .catch(err => {
                Swal.close();
                btn.disabled = false;
                btn.innerHTML = orig;
                Swal.fire({
                    icon: "error",
                    title: "Server Error",
                    text: err.message
                });
            });
    });
</script>
<?php require_once "../layout/footer.php"; ?>