<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once "../../businessLogics/Admin.php";
require_once "../../businessLogics/AgentTrip.php";
require_once "../../config/database.php";
Admin::checkAuth();

/* ── AJAX: Trip Detail with Materials ── */
if (isset($_GET['getTripDetail'])) {
    header('Content-Type: application/json');
    $tid  = intval($_GET['TripId'] ?? 0);
    $trip = AgentTrip::getById($tid);
    if (!$trip) {
        echo json_encode(['error' => 'Not found']);
        exit;
    }
    echo json_encode($trip);
    exit;
}

$allTrips     = AgentTrip::getAll();
$total        = count($allTrips);
$openCount    = count(array_filter($allTrips, fn($t) => $t['TripStatus'] === 'Open'));
$closedCount  = count(array_filter($allTrips, fn($t) => $t['TripStatus'] === 'Closed'));
$totalFreight = array_sum(array_column($allTrips, 'FreightAmount'));
$totalNet     = array_sum(array_column($allTrips, 'NetAmount'));

require_once "../layout/header.php";
require_once "../layout/sidebar.php";
?>
<style>
    .page-header-card {
        background: linear-gradient(135deg, #78350f 0%, #d97706 100%);
        border-radius: 14px;
        padding: 20px 26px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }

    .ph-title {
        font-size: 20px;
        font-weight: 800;
        color: #fff;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .ph-sub {
        font-size: 12px;
        color: rgba(255, 255, 255, .65);
        margin-top: 3px;
    }

    .stats-bar {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .stat-pill {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 10px 18px;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 1px 4px rgba(0, 0, 0, .05);
        flex: 1;
        min-width: 120px;
    }

    .sp-icon {
        width: 36px;
        height: 36px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .sp-val {
        font-size: 20px;
        font-weight: 800;
        color: #92400e;
        line-height: 1;
    }

    .sp-lbl {
        font-size: 11px;
        color: #64748b;
        margin-top: 2px;
    }

    .filter-bar {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 12px 12px 0 0;
        padding: 14px 20px;
    }

    .amber-head th {
        background: #92400e;
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        padding: 10px 12px;
        border: none;
        white-space: nowrap;
    }

    .card-table {
        border-radius: 0 0 12px 12px;
        border-top: none;
        overflow: hidden;
    }

    .status-open {
        background: #dbeafe;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
        padding: 3px 9px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 700;
    }

    .status-closed {
        background: #dcfce7;
        color: #15803d;
        border: 1px solid #bbf7d0;
        padding: 3px 9px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 700;
    }

    .owner-pending {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
        padding: 3px 8px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 700;
    }

    .owner-paid {
        background: #dcfce7;
        color: #16a34a;
        border: 1px solid #bbf7d0;
        padding: 3px 8px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 700;
    }

    .agent-badge {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fcd34d;
        padding: 3px 9px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
    }

    .btn-icon {
        width: 30px;
        height: 30px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 7px;
        font-size: 13px;
    }

    .action-group {
        display: flex;
        gap: 4px;
    }
</style>

<div class="main-content app-content">
    <div class="container-fluid">

        <!-- Header -->
        <div class="page-header-card">
            <div>
                <div class="ph-title"><i class="ri-user-star-line"></i> Agent Trips</div>
                <div class="ph-sub">All trips arranged through agents</div>
            </div>
            <a href="AgentTripForm.php" class="btn btn-warning fw-bold px-4"
                style="border-radius:9px;height:38px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="ri-add-circle-line"></i> New Agent Trip
            </a>
        </div>

        <!-- Stats -->
        <div class="stats-bar">
            <div class="stat-pill">
                <div class="sp-icon" style="background:#fef3c7;"><i class="ri-road-map-line" style="color:#92400e;"></i></div>
                <div>
                    <div class="sp-val"><?= $total ?></div>
                    <div class="sp-lbl">Total Trips</div>
                </div>
            </div>
            <div class="stat-pill">
                <div class="sp-icon" style="background:#dbeafe;"><i class="ri-time-line" style="color:#1d4ed8;"></i></div>
                <div>
                    <div class="sp-val" style="color:#1d4ed8;"><?= $openCount ?></div>
                    <div class="sp-lbl">Open</div>
                </div>
            </div>
            <div class="stat-pill">
                <div class="sp-icon" style="background:#dcfce7;"><i class="ri-checkbox-circle-line" style="color:#15803d;"></i></div>
                <div>
                    <div class="sp-val" style="color:#15803d;"><?= $closedCount ?></div>
                    <div class="sp-lbl">Closed</div>
                </div>
            </div>
            <div class="stat-pill">
                <div class="sp-icon" style="background:#f0fdf4;"><i class="ri-money-rupee-circle-line" style="color:#16a34a;"></i></div>
                <div>
                    <div class="sp-val" style="font-size:14px;color:#16a34a;">Rs.<?= number_format($totalFreight, 0) ?></div>
                    <div class="sp-lbl">Total Freight</div>
                </div>
            </div>
            <div class="stat-pill">
                <div class="sp-icon" style="background:#eff6ff;"><i class="ri-wallet-3-line" style="color:#1a237e;"></i></div>
                <div>
                    <div class="sp-val" style="font-size:14px;color:#1a237e;">Rs.<?= number_format($totalNet, 0) ?></div>
                    <div class="sp-lbl">Total Net</div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label fw-semibold fs-12 mb-1">Status</label>
                    <select id="fStatus" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        <option value="Open">Open</option>
                        <option value="Closed">Closed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold fs-12 mb-1">Owner Payment</label>
                    <select id="fOwner" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="Pending">Pending</option>
                        <option value="PaidDirectly">Paid Directly</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-secondary btn-sm w-100" onclick="clearFilters()">
                        <i class="ri-refresh-line me-1"></i>Clear
                    </button>
                </div>
                <div class="col-md-4 ms-auto">
                    <label class="form-label fw-semibold fs-12 mb-1">Search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0" style="border-radius:8px 0 0 8px;">
                            <i class="ri-search-line text-muted"></i>
                        </span>
                        <input type="text" id="srchBox" class="form-control border-start-0 ps-1"
                            placeholder="Vehicle, Agent, Location, LR No...">
                        <span id="filterInfo" class="input-group-text fw-bold text-white"
                            style="background:#d97706;border-radius:0 8px 8px 0;font-size:11px;min-width:52px;justify-content:center;"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="card custom-card shadow-sm card-table">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="dtTrips" class="table table-hover align-middle mb-0 w-100">
                        <thead>
                            <tr class="amber-head">
                                <th style="width:40px;">#</th>
                                <th>Date</th>
                                <th>Vehicle</th>
                                <th>Agent</th>
                                <th>Route</th>
                                <th>LR No.</th>
                                <th>Freight</th>
                                <th>Net Amt.</th>
                                <th>Owner Pay</th>
                                <th>Status</th>
                                <th style="width:80px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1;
                            foreach ($allTrips as $r): ?>
                                <tr>
                                    <td class="text-muted fw-medium fs-13"><?= $i++ ?></td>
                                    <td style="font-size:13px;white-space:nowrap;"><?= htmlspecialchars($r['TripDate'] ?? '') ?></td>
                                    <td>
                                        <div class="fw-bold" style="font-size:13px;"><?= htmlspecialchars($r['VehicleNumber'] ?? '—') ?></div>
                                        <?php if (!empty($r['VehicleName'])): ?>
                                            <div style="font-size:11px;color:#64748b;"><?= htmlspecialchars($r['VehicleName']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="agent-badge"><?= htmlspecialchars($r['AgentName'] ?? '—') ?></span></td>
                                    <td style="font-size:12px;">
                                        <?php if (!empty($r['FromLocation']) || !empty($r['ToLocation'])): ?>
                                            <span style="color:#d97706;font-weight:600;"><?= htmlspecialchars($r['FromLocation'] ?? '?') ?></span>
                                            <i class="ri-arrow-right-line" style="font-size:10px;color:#94a3b8;"></i>
                                            <span style="color:#dc2626;font-weight:600;"><?= htmlspecialchars($r['ToLocation'] ?? '?') ?></span>
                                        <?php else: echo '<span class="text-muted">—</span>';
                                        endif; ?>
                                    </td>
                                    <td style="font-size:12px;color:#64748b;"><?= htmlspecialchars($r['LRNo'] ?? '—') ?></td>
                                    <td style="font-size:13px;font-weight:700;color:#92400e;">Rs.<?= number_format($r['FreightAmount'] ?? 0, 0) ?></td>
                                    <td style="font-size:13px;font-weight:700;color:#1a237e;">Rs.<?= number_format($r['NetAmount'] ?? 0, 0) ?></td>
                                    <td>
                                        <?php if (($r['FreightPaymentToOwnerStatus'] ?? '') === 'PaidDirectly'): ?>
                                            <span class="owner-paid">⚡ Direct</span>
                                        <?php else: ?>
                                            <span class="owner-pending">⏳ Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($r['TripStatus'] ?? '') === 'Closed'): ?>
                                            <span class="status-closed">✓ Closed</span>
                                        <?php else: ?>
                                            <span class="status-open">● Open</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-group">
                                            <?php if (($r['TripStatus'] ?? '') === 'Open'): ?>
                                                <a href="AgentTripForm.php?TripId=<?= $r['TripId'] ?>"
                                                    class="btn btn-sm btn-warning btn-icon" title="Edit Trip">
                                                    <i class="ri-edit-line"></i>
                                                </a>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-outline-secondary btn-icon" title="View Details"
                                                onclick='showTrip(<?= $r['TripId'] ?>)'>
                                                <i class="ri-eye-line"></i>
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

<script>
    var dtTrips;
    $(document).ready(function() {
        dtTrips = $('#dtTrips').DataTable({
            scrollX: true,
            pageLength: 25,
            dom: 'rtip',
            columnDefs: [{
                orderable: false,
                targets: [0, 10]
            }],
            language: {
                paginate: {
                    previous: '‹',
                    next: '›'
                }
            },
            drawCallback: function() {
                var i = this.api().page.info();
                $('#filterInfo').text(i.recordsDisplay + '/' + i.recordsTotal);
            }
        });
        $('#srchBox').on('keyup input', function() {
            dtTrips.search($(this).val()).draw();
        });
        $('#fStatus').on('change', function() {
            dtTrips.column(9).search(this.value || '').draw();
        });
        $('#fOwner').on('change', function() {
            dtTrips.column(8).search(this.value || '').draw();
        });
    });

    function clearFilters() {
        $('#fStatus, #fOwner').val('').trigger('change');
        $('#srchBox').val('');
        dtTrips.search('').draw();
    }

    function rupee(n) {
        return 'Rs.' + parseFloat(n || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function switchDetailTab(name) {
        document.querySelectorAll('.dtab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.dtab-pane').forEach(p => p.style.display = 'none');
        document.getElementById('dtab-btn-' + name).classList.add('active');
        document.getElementById('dtab-' + name).style.display = 'block';
    }

    function showTrip(tripId) {
        // Show loading
        Swal.fire({
            title: 'Loading...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch('AgentTrips.php?getTripDetail=1&TripId=' + tripId)
            .then(r => r.json())
            .then(t => {
                Swal.close();
                var ownerClr = t.FreightPaymentToOwnerStatus === 'PaidDirectly' ? '#16a34a' : '#dc2626';
                var statusBg = t.TripStatus === 'Closed' ? '#dcfce7' : '#dbeafe';
                var statusCl = t.TripStatus === 'Closed' ? '#15803d' : '#1d4ed8';

                // ── Materials rows ──
                var mats = t.Materials || [];
                var matRows = '';
                var totalWt = 0,
                    totalAmt = 0;
                if (mats.length === 0) {
                    matRows = '<tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:20px;">No materials added</td></tr>';
                } else {
                    mats.forEach(function(m, i) {
                        totalWt += parseFloat(m.Weight || 0);
                        totalAmt += parseFloat(m.Amount || 0);
                        matRows += `<tr style="background:${i%2===0?'#fff':'#fafbfc'}">
                        <td style="padding:7px 10px;font-weight:600;font-size:13px;">${m.MaterialName||'—'}</td>
                        <td style="padding:7px 10px;text-align:center;font-size:13px;">${parseFloat(m.Weight||0).toFixed(3)} T</td>
                        <td style="padding:7px 10px;text-align:right;font-size:13px;">${rupee(m.Rate)}/T</td>
                        <td style="padding:7px 10px;text-align:right;font-size:13px;font-weight:700;color:#92400e;">${rupee(m.Amount)}</td>
                    </tr>`;
                    });
                    matRows += `<tr style="background:#fef3c7;font-weight:800;border-top:2px solid #fcd34d;">
                    <td style="padding:8px 10px;">Total</td>
                    <td style="padding:8px 10px;text-align:center;">${totalWt.toFixed(3)} T</td>
                    <td></td>
                    <td style="padding:8px 10px;text-align:right;color:#92400e;">${rupee(totalAmt)}</td>
                </tr>`;
                }

                var html = `
            <style>
              .dtab-nav{display:flex;gap:4px;border-bottom:2px solid #fde68a;margin-bottom:14px;}
              .dtab-btn{padding:7px 16px;font-size:12px;font-weight:700;cursor:pointer;border:none;background:transparent;color:#92400e;border-radius:8px 8px 0 0;border-bottom:3px solid transparent;margin-bottom:-2px;}
              .dtab-btn.active{background:#fef3c7;border-bottom-color:#d97706;color:#78350f;}
              .dtab-btn:hover:not(.active){background:#fffbeb;}
              .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
              .info-cell{background:#f8fafc;border-radius:9px;padding:10px 14px;}
              .info-lbl{font-size:10px;color:#94a3b8;font-weight:700;text-transform:uppercase;margin-bottom:4px;}
              .info-val{font-size:13px;font-weight:700;}
              .info-sub{font-size:11px;color:#64748b;margin-top:2px;}
            </style>
            <div style="font-family:inherit;">

              <!-- Status badges -->
              <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
                <span style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;">Trip #${t.TripId}</span>
                <span style="background:${statusBg};color:${statusCl};border:1px solid currentColor;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;">${t.TripStatus}</span>
                <span style="color:${ownerClr};border:1px solid currentColor;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;">
                  ${t.FreightPaymentToOwnerStatus==='PaidDirectly'?'⚡ Paid Directly':'⏳ Owner Pending'}
                </span>
              </div>

              <!-- Tab Nav -->
              <div class="dtab-nav">
                <button class="dtab-btn active" id="dtab-btn-info"     onclick="switchDetailTab('info')">    <i class="ri-information-line me-1"></i>Trip Info</button>
                <button class="dtab-btn"        id="dtab-btn-materials" onclick="switchDetailTab('materials')"><i class="ri-box-3-line me-1"></i>Materials (${mats.length})</button>
                <button class="dtab-btn"        id="dtab-btn-charges"   onclick="switchDetailTab('charges')"> <i class="ri-money-rupee-circle-line me-1"></i>Charges</button>
              </div>

              <!-- ── TAB 1: Trip Info ── -->
              <div class="dtab-pane" id="dtab-info" style="display:block;">
                <div class="info-grid">
                  <div class="info-cell">
                    <div class="info-lbl">Trip Date</div>
                    <div class="info-val" style="color:#92400e;">${t.TripDate||'—'}</div>
                  </div>
                  <div class="info-cell">
                    <div class="info-lbl">Vehicle</div>
                    <div class="info-val">${t.VehicleNumber||'—'}</div>
                    <div class="info-sub">${t.VehicleName||''}</div>
                  </div>
                  <div class="info-cell" style="background:#fef3c7;">
                    <div class="info-lbl" style="color:#92400e;">Agent</div>
                    <div class="info-val" style="color:#92400e;">${t.AgentName||'—'}</div>
                  </div>
                  <div class="info-cell">
                    <div class="info-lbl">Invoice No. / LR No.</div>
                    <div class="info-val">${t.InvoiceNo||'—'} &nbsp;<span style="color:#94a3b8;">/</span>&nbsp; ${t.LRNo||'—'}</div>
                  </div>
                  <div class="info-cell">
                    <div class="info-lbl">Driver</div>
                    <div class="info-val">${t.DriverName||'—'}</div>
                    <div class="info-sub">${t.DriverContactNo||''} ${t.DriverAadharNo?'| Aadhar: '+t.DriverAadharNo:''}</div>
                  </div>
                 
                </div>
                <!-- Route -->
                <div style="background:linear-gradient(135deg,#78350f,#d97706);border-radius:9px;padding:10px 16px;margin-top:12px;display:flex;align-items:center;gap:10px;color:#fff;">
                  <i class="ri-map-pin-2-line"></i>
                  <span style="font-weight:700;">${t.FromLocation||'?'}</span>
                  <i class="ri-arrow-right-line"></i>
                  <span style="font-weight:700;">${t.ToLocation||'?'}</span>
                  <span style="margin-left:auto;font-size:11px;opacity:.8;">Route</span>
                </div>
                ${t.Remarks?`<div style="background:#fefce8;border:1px solid #fde68a;border-radius:8px;padding:8px 12px;font-size:12px;color:#854d0e;margin-top:10px;"><i class="ri-chat-1-line me-1"></i><b>Remarks:</b> ${t.Remarks}</div>`:''}
              </div>

              <!-- ── TAB 2: Materials ── -->
              <div class="dtab-pane" id="dtab-materials" style="display:none;">
                <div style="overflow:hidden;border-radius:10px;border:1px solid #fde68a;">
                  <table style="width:100%;border-collapse:collapse;">
                    <thead>
                      <tr style="background:#92400e;color:#fff;">
                        <th style="padding:9px 12px;text-align:left;font-size:12px;">Material</th>
                        <th style="padding:9px 12px;text-align:center;font-size:12px;">Weight</th>
                        <th style="padding:9px 12px;text-align:right;font-size:12px;">Rate</th>
                        <th style="padding:9px 12px;text-align:right;font-size:12px;">Amount</th>
                      </tr>
                    </thead>
                    <tbody>${matRows}</tbody>
                  </table>
                </div>
              </div>

              <!-- ── TAB 3: Charges ── -->
              <div class="dtab-pane" id="dtab-charges" style="display:none;">
                <div style="overflow:hidden;border-radius:10px;border:1px solid #fde68a;">
                  <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <tr style="background:#fef3c7;">
                      <td style="padding:9px 14px;font-weight:700;font-size:13px;border-bottom:1px solid #fde68a;">🚛 Freight Amount</td>
                      <td style="padding:9px 14px;text-align:right;font-weight:800;color:#92400e;font-size:14px;border-bottom:1px solid #fde68a;">${rupee(t.FreightAmount)}</td>
                    </tr>
                    ${parseFloat(t.LabourCharge||0)>0?`
                    <tr>
                      <td style="padding:8px 14px;color:#475569;border-bottom:1px solid #f1f5f9;">👷 Labour Charge</td>
                      <td style="padding:8px 14px;text-align:right;border-bottom:1px solid #f1f5f9;">${rupee(t.LabourCharge)}</td>
                    </tr>`:''}
                    ${parseFloat(t.HoldingCharge||0)>0?`
                    <tr>
                      <td style="padding:8px 14px;color:#475569;border-bottom:1px solid #f1f5f9;">⏱️ Holding / Detention</td>
                      <td style="padding:8px 14px;text-align:right;border-bottom:1px solid #f1f5f9;">${rupee(t.HoldingCharge)}</td>
                    </tr>`:''}
                    ${parseFloat(t.OtherCharge||0)>0?`
                    <tr>
                      <td style="padding:8px 14px;color:#475569;border-bottom:1px solid #f1f5f9;">➕ Other ${t.OtherChargeNote?'<small style="color:#94a3b8;">('+t.OtherChargeNote+')</small>':''}</td>
                      <td style="padding:8px 14px;text-align:right;border-bottom:1px solid #f1f5f9;">${rupee(t.OtherCharge)}</td>
                    </tr>`:''}
                    <tr style="background:#fffbeb;border-top:2px solid #fcd34d;">
                      <td style="padding:9px 14px;font-weight:800;border-bottom:1px solid #fde68a;">📊 Total Amount</td>
                      <td style="padding:9px 14px;text-align:right;font-weight:800;border-bottom:1px solid #fde68a;">${rupee(t.TotalAmount)}</td>
                    </tr>
                    ${parseFloat(t.CashAdvance||0)>0?`
                    <tr>
                      <td style="padding:8px 14px;color:#dc2626;border-bottom:1px solid #f1f5f9;">💰 Cash Advance</td>
                      <td style="padding:8px 14px;text-align:right;color:#dc2626;border-bottom:1px solid #f1f5f9;">− ${rupee(t.CashAdvance)}</td>
                    </tr>`:''}
                    ${parseFloat(t.OnlineAdvance||0)>0?`
                    <tr>
                      <td style="padding:8px 14px;color:#dc2626;border-bottom:1px solid #f1f5f9;">💳 Online Advance</td>
                      <td style="padding:8px 14px;text-align:right;color:#dc2626;border-bottom:1px solid #f1f5f9;">− ${rupee(t.OnlineAdvance)}</td>
                    </tr>`:''}
                    ${parseFloat(t.TDS||0)>0?`
                    <tr>
                      <td style="padding:8px 14px;color:#dc2626;border-bottom:1px solid #f1f5f9;">🏛️ TDS</td>
                      <td style="padding:8px 14px;text-align:right;color:#dc2626;border-bottom:1px solid #f1f5f9;">− ${rupee(t.TDS)}</td>
                    </tr>`:''}
                    ${parseFloat(t.CommissionAmount||0)>0?`
                    <tr>
                      <td style="padding:8px 14px;color:#d97706;border-bottom:1px solid #f1f5f9;">🤝 Commission</td>
                      <td style="padding:8px 14px;text-align:right;color:#d97706;border-bottom:1px solid #f1f5f9;">${rupee(t.CommissionAmount)}</td>
                    </tr>`:''}
                    <tr style="background:#dcfce7;">
                      <td style="padding:10px 14px;font-weight:800;font-size:14px;color:#15803d;">✅ Net Payable</td>
                      <td style="padding:10px 14px;text-align:right;font-weight:900;font-size:16px;color:#15803d;">${rupee(t.NetAmount)}</td>
                    </tr>
                  </table>
                </div>
              </div>

            </div>`;

                Swal.fire({
                    title: '<span style="color:#92400e;font-size:16px;">Agent Trip Details</span>',
                    html: html,
                    width: 620,
                    showConfirmButton: false,
                    showCloseButton: true,
                    customClass: {
                        popup: 'text-start'
                    }
                });
            })
            .catch(() => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Could not load trip details.'
                });
            });
    }

    window.addEventListener('offline', () => {
        if (typeof SRV !== 'undefined') SRV.toast.warning('Internet Disconnected!');
    });
    window.addEventListener('online', () => {
        if (typeof SRV !== 'undefined') SRV.toast.success('Back Online!');
    });
</script>
<?php require_once "../layout/footer.php"; ?>