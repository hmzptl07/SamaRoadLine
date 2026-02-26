<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once "../../businessLogics/Admin.php";
require_once "../../businessLogics/Trip.php";
require_once "../../businessLogics/Party.php";
require_once "../../businessLogics/Vehicle.php";
Admin::checkAuth();

$TRIP_TYPE = "Agent";
$BACK_URL  = "AgentTrips.php";

/* ══ AJAX — Save ══ */
if (isset($_POST["saveTrip"])) {
    header("Content-Type: application/json");
    try {
        $data = $_POST;
        $data["TripType"] = $TRIP_TYPE;

        // Recalculate Amount[] server-side from Weight x Rate
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
        $adv = floatval($data["AdvanceAmount"] ?? 0);
        $tds = floatval($data["TDS"] ?? 0);
        $data["TotalAmount"] = $fr + $la + $ho + $ot;
        $data["NetAmount"]   = $data["TotalAmount"] - $adv - $tds;

        $tripId = !empty($data["TripId"]) ? intval($data["TripId"]) : 0;
        $result = $tripId > 0 ? Trip::update($tripId, $data) : Trip::insert($data);

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
    $editTrip  = Trip::getById($tripId);
    // Ensure this is actually an Agent trip
    if ($editTrip && $editTrip["TripType"] !== "Agent") {
        header("Location: RegularTripForm.php?TripId=$tripId");
        exit();
    }
    $materials = Trip::getMaterials($tripId);
}
$isEdit    = $editTrip !== null;
$pageTitle = $isEdit ? "Edit Agent Trip #$tripId" : "New Agent Trip";

$parties        = Party::getAll();
$vehicles       = Vehicle::getAll();
$agents         = array_filter($parties,  fn($p) => $p["PartyType"] === "Agent" && $p["IsActive"] === "Yes");
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
    /* Agent theme — amber/gold instead of blue for header accent */
    .page-header-card {
        background: linear-gradient(135deg, #78350f 0%, #d97706 100%);
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
        color: rgba(255, 255, 255, 0.65);
        margin-top: 5px;
    }

    .ph-breadcrumb a {
        color: rgba(255, 255, 255, 0.8);
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
        color: #92400e;
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
        background: linear-gradient(135deg, #78350f, #b45309);
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
        color: #92400e;
        background: #fef3c7;
        border-radius: 6px;
        padding: 3px 10px;
        margin: 6px 0 10px;
    }

    .summary-box {
        background: linear-gradient(135deg, #fffbeb, #fef3c7);
        border: 2px solid #fcd34d;
        border-radius: 12px;
        padding: 14px 16px;
        margin-top: 14px;
    }

    .net-amount {
        font-size: 26px;
        font-weight: 900;
        color: #92400e;
        text-align: center;
        margin-top: 10px;
        letter-spacing: -0.5px;
    }

    .net-label {
        font-size: 11px;
        color: #78350f;
        text-align: center;
        text-transform: uppercase;
        letter-spacing: .5px;
    }

    .save-btn-card {
        background: linear-gradient(135deg, #fffbeb, #fef9c3);
        border: 2px solid #fcd34d;
        border-radius: 14px;
        padding: 20px;
        text-align: center;
    }

    #matTable thead th {
        background: #92400e;
        color: #fff;
        font-size: 11.5px;
        padding: 8px 10px;
        border: none;
    }

    #matTable tbody td {
        padding: 5px 6px;
        vertical-align: middle;
    }

    #matTable tfoot td {
        background: #fffbeb;
        font-weight: 700;
    }

    .charge-input {
        font-weight: 600;
    }
</style>

<div class="main-content app-content">
    <div class="container-fluid">

        <!-- ══ Page Header ══ -->
        <div class="page-header-card">
            <div>
                <div class="ph-title">
                    <i class="ri-map-pin-line"></i>
                    <?= $pageTitle ?>
                    <span class="badge bg-warning text-dark">Agent Trip</span>
                </div>
                <div class="ph-breadcrumb">
                    <a href="/Sama_Roadlines/index.php">Dashboard</a><span>›</span>
                    <a href="AgentTrips.php">Agent Trips</a><span>›</span>
                    <?= $pageTitle ?>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($isEdit): ?>
                    <button class="btn btn-light fw-semibold" style="border-radius:9px;height:38px;font-size:13px;"
                        onclick="window.open('GCNote_print.php?TripId=<?= $tripId ?>','_blank','width=950,height=720')">
                        <i class="ri-printer-line me-1"></i> Print GC
                    </button>
                <?php endif; ?>
                <a href="AgentTrips.php" class="btn btn-outline-light fw-semibold" style="border-radius:9px;height:38px;font-size:13px;">
                    <i class="ri-arrow-left-line me-1"></i> Back
                </a>
            </div>
        </div>

        <form id="tripForm">
            <input type="hidden" name="saveTrip" value="1">
            <input type="hidden" name="TripId" value="<?= $isEdit ? $tripId : '' ?>">
            <input type="hidden" name="TripType" value="Agent">

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
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold fs-13">Trip Date <span class="req">*</span></label>
                                    <input type="date" name="TripDate" class="form-control" value="<?= $isEdit ? fv("TripDate", $editTrip) : date("Y-m-d") ?>">
                                </div>
                                <div class="col-md-4">
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
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold fs-13">Party Invoice No.</label>
                                    <input type="text" name="InvoiceNo" class="form-control"
                                        value="<?= $isEdit ? fv("InvoiceNo", $editTrip) : "" ?>" placeholder="Invoice no.">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold fs-13">Owner Payment</label>
                                    <select name="FreightPaymentToOwnerStatus" class="form-select">
                                        <option value="Pending" <?= (!$isEdit || ($editTrip["FreightPaymentToOwnerStatus"] ?? "") === "Pending") ? "selected" : "" ?>>⏳ Pending</option>
                                        <option value="PaidDirectly" <?= (($editTrip["FreightPaymentToOwnerStatus"] ?? "") === "PaidDirectly") ? "selected" : "" ?>>✅ Paid Directly</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ② Agent Details -->
                    <div class="trip-card">
                        <div class="trip-card-head">
                            <div class="trip-card-title"><i class="ri-user-star-line" style="color:#d97706;"></i>Agent Details</div>
                            <span class="badge bg-warning text-dark" style="font-size:10px;">Agent Trip</span>
                        </div>
                        <div class="trip-card-body">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label fw-semibold fs-13">Agent <span class="req">*</span></label>
                                    <select name="AgentId" id="sel_Agent" class="form-select">
                                        <option value="">-- Search Agent --</option>
                                        <?php foreach ($agents as $p): ?>
                                            <option value="<?= $p["PartyId"] ?>" <?= (($editTrip["AgentId"] ?? "") == $p["PartyId"]) ? "selected" : "" ?>>
                                                <?= htmlspecialchars($p["PartyName"]) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold fs-13">From Location</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="ri-map-pin-2-line" style="color:#d97706;"></i></span>
                                        <input type="text" name="FromLocation" class="form-control"
                                            value="<?= $isEdit ? fv("FromLocation", $editTrip) : "" ?>" placeholder="e.g. Surat">
                                    </div>
                                </div>
                                <div class="col-md-4">
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
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold fs-13">Driver Name</label>
                                    <input type="text" name="DriverName" class="form-control" value="<?= $isEdit ? fv("DriverName", $editTrip) : "" ?>" placeholder="Full name">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold fs-13">Contact No</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="ri-phone-line"></i></span>
                                        <input type="text" name="DriverContactNo" class="form-control" maxlength="10" value="<?= $isEdit ? fv("DriverContactNo", $editTrip) : "" ?>" placeholder="10 digits">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold fs-13">Aadhar No</label>
                                    <input type="text" name="DriverAadharNo" class="form-control" maxlength="12" value="<?= $isEdit ? fv("DriverAadharNo", $editTrip) : "" ?>" placeholder="12 digits">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold fs-13">Driver Address</label>
                                    <input type="text" name="DriverAddress" class="form-control" value="<?= $isEdit ? fv("DriverAddress", $editTrip) : "" ?>" placeholder="Address">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ④ Materials -->
                    <div class="trip-card">
                        <div class="trip-card-head">
                            <div class="trip-card-title"><i class="ri-box-3-line" style="color:#64748b;"></i>Goods / Materials</div>
                            <button type="button" class="btn btn-sm" onclick="addRow()"
                                style="border-radius:7px;font-size:12px;padding:4px 12px;background:#92400e;color:#fff;border:none;">
                                <i class="ri-add-line me-1"></i>Add Row
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table mb-0" id="matTable">
                                <thead>
                                    <tr>
                                        <th class="ps-3">Material Name</th>
                                        <th style="width:110px;" class="text-center">Weight (Ton)</th>
                                        <th style="width:120px;" class="text-center">Rate (Rs./Ton)</th>
                                        <th style="width:130px;" class="text-center">Amount (Rs.)</th>
                                        <th style="width:44px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="matBody">
                                    <?php if (empty($materials)): ?>
                                        <tr>
                                            <td class="ps-3"><input type="text" name="MaterialName[]" class="form-control form-control-sm" placeholder="e.g. Wheat, Cement..."></td>
                                            <td><input type="number" step="0.01" name="Weight[]" class="form-control form-control-sm wt text-center" min="0" placeholder="0.00"></td>
                                            <td><input type="number" step="0.01" name="Rate[]" class="form-control form-control-sm rt text-center" min="0" placeholder="0.00"></td>
                                            <td><input type="number" step="0.01" name="Amount[]" class="form-control form-control-sm amt text-center" style="background:#fffbeb;" readonly></td>
                                            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="delRow(this)" style="width:30px;height:30px;padding:0;"><i class="ri-delete-bin-line" style="font-size:13px;"></i></button></td>
                                        </tr>
                                        <?php else: foreach ($materials as $m): ?>
                                            <tr>
                                                <td class="ps-3"><input type="text" name="MaterialName[]" class="form-control form-control-sm" value="<?= htmlspecialchars($m["MaterialName"]) ?>"></td>
                                                <td><input type="number" step="0.01" name="Weight[]" class="form-control form-control-sm wt text-center" value="<?= $m["Weight"] ?>"></td>
                                                <td><input type="number" step="0.01" name="Rate[]" class="form-control form-control-sm rt text-center" value="<?= $m["Rate"] ?>"></td>
                                                <td><input type="number" step="0.01" name="Amount[]" class="form-control form-control-sm amt text-center" value="<?= $m["Amount"] ?>" style="background:#fffbeb;" readonly></td>
                                                <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="delRow(this)" style="width:30px;height:30px;padding:0;"><i class="ri-delete-bin-line" style="font-size:13px;"></i></button></td>
                                            </tr>
                                    <?php endforeach;
                                    endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td class="ps-3 fw-bold text-end text-muted fs-12">Total:</td>
                                        <td><input id="totWt" class="form-control form-control-sm fw-bold text-center" style="background:#fef3c7;border-color:#fcd34d;" readonly></td>
                                        <td></td>
                                        <td><input id="matAmt" class="form-control form-control-sm fw-bold text-center" style="background:#fef3c7;border-color:#fcd34d;" readonly></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- ⑤ Remarks -->
                    <div class="trip-card">
                        <div class="trip-card-head">
                            <div class="trip-card-title"><i class="ri-chat-1-line" style="color:#94a3b8;"></i>Remarks</div>
                        </div>
                        <div class="trip-card-body">
                            <textarea name="Remarks" class="form-control" rows="2" placeholder="Any notes or remarks..."><?= $isEdit ? fv("Remarks", $editTrip) : "" ?></textarea>
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
                                <label class="form-label fw-semibold fs-13">Labour Charge (Rs.)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="ri-user-line text-muted"></i></span>
                                    <input type="number" step="0.01" name="LabourCharge" id="labourAmt" class="form-control charge-input" min="0"
                                        value="<?= $isEdit ? fm("LabourCharge", $editTrip) : "0.00" ?>" placeholder="0.00">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold fs-13">Holding / Detention (Rs.)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="ri-time-line text-muted"></i></span>
                                    <input type="number" step="0.01" name="HoldingCharge" id="holdingAmt" class="form-control charge-input" min="0"
                                        value="<?= $isEdit ? fm("HoldingCharge", $editTrip) : "0.00" ?>" placeholder="0.00">
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
                                <label class="form-label fw-semibold fs-13">Advance Paid (Rs.)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="ri-arrow-up-circle-line text-warning"></i></span>
                                    <input type="number" step="0.01" name="AdvanceAmount" id="advanceAmt" class="form-control charge-input" min="0"
                                        value="<?= $isEdit ? fm("AdvanceAmount", $editTrip) : "0.00" ?>" placeholder="0.00">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold fs-13">TDS (Rs.)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="ri-government-line text-muted"></i></span>
                                    <input type="number" step="0.01" name="TDS" id="tdsAmt" class="form-control charge-input" min="0"
                                        value="<?= $isEdit ? fm("TDS", $editTrip) : "0.00" ?>" placeholder="0.00">
                                </div>
                            </div>
                            <!-- NOTE: Agent trips have NO commission field -->

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
                                    <tr style="border-top:2px solid #fcd34d;">
                                        <td class="fw-bold pt-2">📊 Total</td>
                                        <td class="text-end fw-bold pt-2" id="sum_total">Rs.0.00</td>
                                    </tr>
                                    <tr>
                                        <td class="text-danger">➖ Advance</td>
                                        <td class="text-end text-danger" id="sum_advance">Rs.0.00</td>
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
                        <div style="font-size:13px;font-weight:700;color:#92400e;margin-bottom:14px;">
                            <i class="ri-save-line me-1"></i><?= $isEdit ? "Update Agent Trip" : "Save New Agent Trip" ?>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-warning fw-bold btn-lg text-dark" id="saveBtn" style="border-radius:10px;">
                                <i class="ri-save-line me-1"></i><?= $isEdit ? "Update Trip" : "Save Trip" ?>
                            </button>
                            <?php if ($isEdit): ?>
                                <button type="button" class="btn btn-outline-dark fw-semibold" style="border-radius:10px;"
                                    onclick="window.open('GCNote_print.php?TripId=<?= $tripId ?>','_blank','width=950,height=720')">
                                    <i class="ri-printer-line me-1"></i>Print GC Note
                                </button>
                            <?php endif; ?>
                            <a href="AgentTrips.php" class="btn btn-outline-secondary" style="border-radius:10px;">
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

    $(document).ready(function() {
        ["#sel_Vehicle", "#sel_Agent"].forEach(s => {
            if ($(s).length) $(s).select2({
                theme: "bootstrap-5",
                allowClear: true,
                placeholder: "Search & Select",
                width: "100%"
            });
        });
        calcAll();
    });

    // FreightAmount is always auto — no manual input

    function addRow() {
        $("#matBody").append(`<tr>
        <td class="ps-3"><input type="text" name="MaterialName[]" class="form-control form-control-sm" placeholder="Material name..."></td>
        <td><input type="number" step="0.01" name="Weight[]" class="form-control form-control-sm wt text-center" min="0" placeholder="0.00"></td>
        <td><input type="number" step="0.01" name="Rate[]" class="form-control form-control-sm rt text-center" min="0" placeholder="0.00"></td>
        <td><input type="number" step="0.01" name="Amount[]" class="form-control form-control-sm amt text-center" style="background:#fffbeb;" readonly></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="delRow(this)" style="width:30px;height:30px;padding:0;"><i class="ri-delete-bin-line" style="font-size:13px;"></i></button></td>
    </tr>`);
    }

    function delRow(btn) {
        $(btn).closest("tr").remove();
        calcAll();
    }

    $(document).on("input", ".wt,.rt", function() {
        var row = $(this).closest("tr");
        var w = parseFloat(row.find(".wt").val()) || 0,
            r = parseFloat(row.find(".rt").val()) || 0;
        row.find(".amt").val((w * r).toFixed(2));
        calcAll();
    });

    function calcAll() {
        var tw = 0,
            ta = 0;
        $(".wt").each(function() {
            tw += parseFloat($(this).val()) || 0;
        });
        $(".amt").each(function() {
            ta += parseFloat($(this).val()) || 0;
        });
        $("#totWt").val(tw.toFixed(2));
        $("#matAmt").val(ta.toFixed(2));
        // Always auto-sync freight = material total
        $("#freightAmt").val(ta.toFixed(2));
        calcTotal();
    }

    $(document).on("input", "#labourAmt,#holdingAmt,#otherAmt,#advanceAmt,#tdsAmt", calcTotal);

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
        var ad = f("#advanceAmt"),
            td = f("#tdsAmt");
        var total = fr + la + ho + ot;
        var net = total - ad - td;
        $("#sum_freight").text(rupee(fr));
        $("#sum_labour").text(rupee(la));
        $("#sum_holding").text(rupee(ho));
        $("#sum_other").text(rupee(ot));
        $("#sum_total").text(rupee(total));
        $("#sum_advance").text(rupee(ad));
        $("#sum_tds").text(rupee(td));
        $("#sum_net").text(rupee(net));
        document.getElementById("sum_net").style.color = net < 0 ? "#dc2626" : "#92400e";
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
                confirmButtonColor: "#92400e"
            });
            return;
        }
        if (!$("#sel_Agent").val()) {
            Swal.fire({
                icon: "warning",
                title: "Agent Required",
                text: "Please select an agent.",
                confirmButtonColor: "#92400e"
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
        fetch("AgentTripForm.php", {
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
                            window.location.href = "AgentTrips.php";
                        });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Could not save.",
                        confirmButtonColor: "#92400e"
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