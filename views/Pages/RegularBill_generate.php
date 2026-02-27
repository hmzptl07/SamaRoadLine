<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once "../../businessLogics/Admin.php";
require_once "../../config/database.php";
require_once "../../businessLogics/Party.php";
require_once "../../businessLogics/RegularBill.php";
Admin::checkAuth();

/* ══ AJAX — Get Unbilled Trips ══ */
if (isset($_GET['getTrips'])) {
    header('Content-Type: application/json');
    echo json_encode(RegularBill::getUnbilledTrips($pdo, intval($_GET['partyId']), $_GET['from'] ?? '', $_GET['to'] ?? ''));
    exit();
}

/* ══ AJAX — Generate Bill ══ */
if (isset($_POST['generateBill'])) {
    header('Content-Type: application/json');
    echo json_encode(RegularBill::generate($pdo, intval($_POST['PartyId']), json_decode($_POST['tripIds'], true), $_POST['BillDate'], $_POST['Remarks'] ?? ''));
    exit();
}

/* ══ Page Data ══ */
$bills   = RegularBill::getAllBills($pdo);
$parties = array_filter(Party::getAll(), fn($p) => $p['PartyType'] === 'Consigner' && $p['IsActive'] === 'Yes');

$total      = count($bills);
$paid       = count(array_filter($bills, fn($b) => $b['BillStatus']==='Paid'));
$partial    = count(array_filter($bills, fn($b) => $b['BillStatus']==='PartiallyPaid'));
$generated  = count(array_filter($bills, fn($b) => $b['BillStatus']==='Generated'));
$totalAmt   = array_sum(array_column($bills, 'NetBillAmount'));

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
.badge-generated{background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.badge-partial{background:#fef9c3;color:#92400e;border:1px solid #fcd34d;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.badge-paid{background:#dcfce7;color:#16a34a;border:1px solid #bbf7d0;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
/* Modal */
.modal-head-blue{background:linear-gradient(135deg,#1a237e,#1d4ed8);border-radius:16px 16px 0 0;padding:16px 24px;}
.step-badge{display:inline-flex;align-items:center;gap:6px;background:#1a237e;color:#fff;font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;margin-bottom:10px;}
.trip-select-table thead th{background:#1a237e;color:#fff;font-size:11.5px;padding:7px 10px;border:none;}
.net-summary-box{background:linear-gradient(135deg,#f0f4ff,#e8efff);border:2px solid #c7d7fc;border-radius:12px;padding:14px 16px;text-align:center;}
.net-summary-val{font-size:26px;font-weight:900;color:#1a237e;}
.net-summary-lbl{font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;}
</style>

<div class="main-content app-content">
<div class="container-fluid">

<!-- ══ Page Header ══ -->
<div class="page-header-card">
    <div>
        <div class="ph-title"><i class="ri-bill-line"></i>Regular Bills</div>
        <div class="ph-sub">Select party → Choose trips → Generate bill</div>
    </div>
    <button class="btn btn-warning fw-bold px-4" style="border-radius:9px;height:38px;font-size:13px;"
        onclick="openGenerateModal()">
        <i class="ri-add-circle-line me-1"></i>New Regular Bill
    </button>
</div>

<!-- ══ Stats ══ -->
<div class="stats-bar">
    <div class="stat-pill">
        <div class="sp-icon" style="background:#e0e7ff;"><i class="ri-file-list-3-line" style="color:#1a237e;"></i></div>
        <div><div class="sp-val"><?= $total ?></div><div class="sp-lbl">Total Bills</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-icon" style="background:#f1f5f9;"><i class="ri-file-text-line" style="color:#64748b;"></i></div>
        <div><div class="sp-val" style="color:#64748b;"><?= $generated ?></div><div class="sp-lbl">Generated</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-icon" style="background:#fef9c3;"><i class="ri-time-line" style="color:#ca8a04;"></i></div>
        <div><div class="sp-val" style="color:#ca8a04;"><?= $partial ?></div><div class="sp-lbl">Partial Paid</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-icon" style="background:#dcfce7;"><i class="ri-checkbox-circle-line" style="color:#16a34a;"></i></div>
        <div><div class="sp-val" style="color:#16a34a;"><?= $paid ?></div><div class="sp-lbl">✅ Paid</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-icon" style="background:#f0fdf4;"><i class="ri-money-rupee-circle-line" style="color:#16a34a;"></i></div>
        <div><div class="sp-val" style="font-size:15px;color:#16a34a;">Rs.<?= number_format($totalAmt, 0) ?></div><div class="sp-lbl">Total Amount</div></div>
    </div>
</div>

<!-- ══ Filter + Search ══ -->
<div class="filter-card">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label fw-semibold fs-12 mb-1"><i class="ri-group-line me-1"></i>Party</label>
            <select id="filterParty" class="form-select form-select-sm">
                <option value="">-- All Parties --</option>
                <?php foreach ($parties as $p): ?>
                <option value="<?= htmlspecialchars($p['PartyName']) ?>"><?= htmlspecialchars($p['PartyName']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold fs-12 mb-1"><i class="ri-toggle-line me-1"></i>Status</label>
            <select id="filterStatus" class="form-select form-select-sm">
                <option value="">All Status</option>
                <option value="Generated">Generated</option>
                <option value="PartiallyPaid">Partially Paid</option>
                <option value="Paid">Paid</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold fs-12 mb-1"><i class="ri-calendar-line me-1"></i>From Date</label>
            <input type="date" id="filterFrom" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold fs-12 mb-1"><i class="ri-calendar-line me-1"></i>To Date</label>
            <input type="date" id="filterTo" class="form-control form-control-sm">
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button class="btn btn-primary btn-sm w-100" onclick="applyDateFilter()"><i class="ri-search-line"></i></button>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-outline-secondary btn-sm w-100" onclick="clearFilters()"><i class="ri-refresh-line me-1"></i>Clear</button>
        </div>
    </div>
    <div class="row mt-2">
        <div class="col-md-6 ms-auto">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0" style="border-radius:8px 0 0 8px;"><i class="ri-search-line text-muted"></i></span>
                <input type="text" id="customSearch" class="form-control border-start-0 ps-1"
                    placeholder="Bill No, Party name, Amount..." style="box-shadow:none;">
                <span id="filterInfo" class="input-group-text bg-primary text-white fw-bold"
                    style="border-radius:0 8px 8px 0;font-size:11px;min-width:52px;justify-content:center;"></span>
            </div>
        </div>
    </div>
</div>

<!-- ══ Bills Table ══ -->
<div class="card custom-card shadow-sm">
<div class="card-body p-0">
<div class="table-responsive">
<table id="billTable" class="table table-hover align-middle mb-0 w-100">
    <thead style="background:#f8fafc;">
        <tr>
            <th style="width:50px;">#</th>
            <th>Bill No.</th>
            <th>Bill Date</th>
            <th>Party</th>
            <th>Trips</th>
            <th>Freight</th>
            <th>Net Amount</th>
            <th>Status</th>
            <th style="width:90px;">Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php $i=1; foreach ($bills as $b): ?>
    <tr>
        <td class="text-muted fw-medium fs-13"><?= $i++ ?></td>
        <td>
            <div class="fw-bold text-primary" style="font-size:13px;"><?= htmlspecialchars($b['BillNo']) ?></div>
        </td>
        <td style="font-size:13px;white-space:nowrap;">
            <?= date('d-m-Y', strtotime($b['BillDate'])) ?>
        </td>
        <td>
            <div class="fw-semibold" style="font-size:13px;"><?= htmlspecialchars($b['PartyName']) ?></div>
            <?php if (!empty($b['City'])): ?>
            <div style="font-size:11px;color:#64748b;"><?= htmlspecialchars($b['City']) ?></div>
            <?php endif; ?>
        </td>
        <td class="text-center">
            <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill">
                <?= $b['TripCount'] ?> Trips
            </span>
        </td>
        <td style="font-size:13px;font-weight:600;color:#16a34a;">
            Rs.<?= number_format($b['TotalFreightAmount'], 2) ?>
        </td>
        <td style="font-size:14px;font-weight:800;color:#1a237e;">
            Rs.<?= number_format($b['NetBillAmount'], 2) ?>
        </td>
        <td>
            <?php if ($b['BillStatus']==='Paid'): ?>
                <span class="badge-paid"><i class="ri-checkbox-circle-line me-1"></i>Paid</span>
            <?php elseif ($b['BillStatus']==='PartiallyPaid'): ?>
                <span class="badge-partial"><i class="ri-time-line me-1"></i>Partial</span>
            <?php else: ?>
                <span class="badge-generated"><i class="ri-file-text-line me-1"></i>Generated</span>
            <?php endif; ?>
        </td>
        <td>
            <div class="action-btn-group">
                            <a href="RegularBill_print.php?BillId=<?= $b['BillId'] ?>" target="_blank" class="btn btn-sm btn-outline-dark btn-icon" title="Print Bill"><i class="ri-printer-line"></i></a>

               
                <a href="BillPayment_manage.php" class="btn btn-sm btn-outline-success btn-icon" title="Manage Payment">
                    <i class="ri-secure-payment-line"></i>
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

<!-- ══════════════════════════════════
     GENERATE BILL MODAL
══════════════════════════════════ -->
<div class="modal fade" id="billModal" tabindex="-1" data-bs-backdrop="static">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 20px 60px rgba(0,0,0,.15);">
    <div class="modal-head-blue">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <div style="font-size:16px;font-weight:800;color:#fff;display:flex;align-items:center;gap:8px;">
                <i class="ri-bill-line"></i> New Regular Bill
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div style="font-size:12px;color:rgba(255,255,255,0.65);margin-top:4px;">
            Select party → Choose trips → Generate bill
        </div>
    </div>
    <div class="modal-body p-4">

        <!-- Step 1: Party + Bill Date -->
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin-bottom:16px;">
            <div class="step-badge"><span style="background:rgba(255,255,255,0.3);border-radius:50%;width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;font-size:10px;">1</span>Select Party & Bill Date</div>
            <div class="row g-3 align-items-end">
                <div class="col-md-7">
                    <label class="form-label fw-semibold fs-13">Party <span class="text-danger">*</span></label>
                    <select id="selParty" class="form-select">
                        <option value="">-- Search party --</option>
                        <?php foreach ($parties as $p): ?>
                        <option value="<?= $p['PartyId'] ?>"><?= htmlspecialchars($p['PartyName']) ?><?= $p['City'] ? ' — '.$p['City'] : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-13">Bill Date</label>
                    <input type="date" id="billDate" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" onclick="loadTrips()">
                        <i class="ri-search-line me-1"></i>Load Trips
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 2: Select Trips -->
        <div id="tripsCard" style="display:none;background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:16px;">
            <div style="padding:12px 16px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;background:#f8fafc;">
                <div class="step-badge" style="margin-bottom:0;"><span style="background:rgba(255,255,255,0.3);border-radius:50%;width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;font-size:10px;">2</span>Select Trips</div>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-outline-primary" onclick="selectAll()">Select All</button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="clearAll()">Clear</button>
                    <span id="selCount" class="badge bg-primary ms-1">0 selected</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 trip-select-table">
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="chkAll" onchange="toggleAll(this)"></th>
                            <th>Date</th><th>LR No.</th><th>Vehicle</th><th>Invoice</th>
                            <th>From</th><th>To</th><th class="text-end">Weight</th>
                            <th class="text-end">Freight</th>
                            <th class="text-end">Labour</th>
                            <th class="text-end">Holding</th>
                            <th class="text-end">Other</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Cash Adv.</th>
                            <th class="text-end">Online Adv.</th>
                            <th class="text-end">TDS</th>
                            <th class="text-end">Net</th>
                        </tr>
                    </thead>
                    <tbody id="tripsBody"></tbody>
                    <tfoot style="background:#f0f4ff;">
                        <tr class="fw-bold">
                            <td colspan="7" class="text-end" style="color:#1a237e;">TOTAL:</td>
                            <td class="text-end" id="tWt">0.000</td>
                            <td class="text-end" id="tFr">0.00</td>
                            <td class="text-end" id="tLab">0.00</td>
                            <td class="text-end" id="tHold">0.00</td>
                            <td class="text-end" id="tOth">0.00</td>
                            <td class="text-end fw-bold" id="tTotal">0.00</td>
                            <td class="text-end" id="tCash">0.00</td>
                            <td class="text-end" id="tOnline">0.00</td>
                            <td class="text-end" id="tTds">0.00</td>
                            <td class="text-end text-primary fw-bold" id="tNet">0.00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Step 3: Remarks + Summary -->
        <div id="step3" style="display:none;">
            <div class="step-badge"><span style="background:rgba(255,255,255,0.3);border-radius:50%;width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;font-size:10px;">3</span>Confirm & Generate</div>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold fs-13">Remarks / Note</label>
                    <input type="text" id="remarks" class="form-control" placeholder="Optional note or remark...">
                </div>
                <div class="col-md-4">
                    <div class="net-summary-box">
                        <div class="net-summary-lbl">Net Bill Amount</div>
                        <div class="net-summary-val" id="finalNet">Rs. 0.00</div>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <div class="modal-footer" style="border-top:1px solid #f1f5f9;padding:14px 24px;">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="ri-close-line me-1"></i>Cancel</button>
        <button class="btn btn-primary fw-bold px-4" id="genBtn" onclick="generateBill()" style="display:none;border-radius:9px;">
            <i class="ri-bill-line me-1"></i>Generate Bill
        </button>
    </div>
</div>
</div>
</div>

<script>
var trips = [];
var billDT;

$(document).ready(function(){
    billDT = $('#billTable').DataTable({
        scrollX:true, pageLength:25, dom:'rtip',
        columnDefs:[{orderable:false,targets:[0,8]}],
        language:{paginate:{previous:'‹',next:'›'},emptyTable:'No bills found.'},
        drawCallback:function(){ var i=this.api().page.info(); $('#filterInfo').text(i.recordsDisplay+'/'+i.recordsTotal); }
    });

    $('#selParty').select2({theme:'bootstrap-5',placeholder:'-- Search party --',allowClear:true,dropdownParent:$('#billModal'),width:'100%'});
    $('#filterParty').select2({theme:'bootstrap-5',placeholder:'-- All Parties --',allowClear:true,width:'100%'});
    $('#filterStatus').select2({theme:'bootstrap-5',placeholder:'All Status',allowClear:true,width:'100%'});

    // Party column filter
    $('#filterParty').on('change',function(){ billDT.column(3).search(this.value||'').draw(); });
    // Status column filter
    $('#filterStatus').on('change',function(){ billDT.column(7).search(this.value||'').draw(); });
    // Custom search
    $('#customSearch').on('keyup input',function(){ billDT.search($(this).val()).draw(); });
});

function openGenerateModal(){
    // Reset modal
    $('#selParty').val(null).trigger('change');
    $('#billDate').val('<?= date('Y-m-d') ?>');
    $('#tripsBody').html(''); $('#tripsCard,#step3,#genBtn').hide();
    $('#remarks').val(''); $('#finalNet').text('Rs. 0.00');
    new bootstrap.Modal('#billModal').show();
}

function applyDateFilter(){
    var from=$('#filterFrom').val(), to=$('#filterTo').val();
    if(!from && !to){ billDT.search('').draw(); return; }
    // Custom date range filter using DataTables search
    $.fn.dataTable.ext.search = [];
    if(from || to){
        $.fn.dataTable.ext.search.push(function(settings, data){
            if(settings.nTable.id !== 'billTable') return true;
            // data[2] = Bill Date column (index 2) format dd-mm-yyyy
            var parts = data[2].split('-');
            if(parts.length !== 3) return true;
            var cellDate = new Date(parts[2]+'-'+parts[1]+'-'+parts[0]);
            var fromDate = from ? new Date(from) : null;
            var toDate   = to   ? new Date(to)   : null;
            if(fromDate && cellDate < fromDate) return false;
            if(toDate   && cellDate > toDate)   return false;
            return true;
        });
    }
    billDT.draw();
}

function clearFilters(){
    $('#filterParty,#filterStatus').val(null).trigger('change');
    $('#filterFrom,#filterTo').val('');
    $('#customSearch').val('');
    $.fn.dataTable.ext.search = [];
    billDT.search('').columns().search('').draw();
}

window.addEventListener('offline',()=>SRV.toast.warning('Internet Disconnected!'));
window.addEventListener('online', ()=>SRV.toast.success('Back Online!'));

function loadTrips(){
    var partyId=$('#selParty').val();
    if(!partyId){ Swal.fire({icon:'warning',title:'Please select a party!',confirmButtonColor:'#1a237e'}); return; }
    Swal.fire({title:'Loading trips...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
    fetch('RegularBill_generate.php?getTrips=1&partyId='+partyId)
    .then(r=>r.json()).then(data=>{
        Swal.close(); trips=data;
        if(!data.length){ Swal.fire({icon:'info',title:'No unbilled trips found',text:'No pending trips found for this party.',confirmButtonColor:'#1a237e'}); return; }
        var html='';
        data.forEach(function(t,i){
            var lr=String(t.TripId).padStart(4,'0');
            var wt=parseFloat(t.TotalWeight||0).toFixed(3);
            var otherNote = t.OtherChargeNote ? ' <small class="text-muted">('+t.OtherChargeNote+')</small>' : '';
            html+=`<tr>
                <td class="text-center"><input type="checkbox" class="tripChk" data-idx="${i}" onchange="updateTotals()"></td>
                <td style="font-size:12px;white-space:nowrap;">${t.TripDate}</td>
                <td><code style="font-size:11px;">${lr}</code></td>
                <td style="font-size:12px;">${t.VehicleNumber||'—'}</td>
                <td style="font-size:12px;">${t.InvoiceNo||'—'}</td>
                <td style="font-size:12px;">${t.FromLocation||'—'}</td>
                <td style="font-size:12px;">${t.ToLocation||'—'}</td>
                <td class="text-end" style="font-size:12px;">${wt} T</td>
                <td class="text-end fw-medium" style="font-size:12px;">${parseFloat(t.FreightAmount||0).toFixed(2)}</td>
                <td class="text-end" style="font-size:12px;">${parseFloat(t.LabourCharge||0).toFixed(2)}</td>
                <td class="text-end" style="font-size:12px;">${parseFloat(t.HoldingCharge||0).toFixed(2)}</td>
                <td class="text-end" style="font-size:12px;">${parseFloat(t.OtherCharge||0).toFixed(2)}${otherNote}</td>
                <td class="text-end fw-bold" style="font-size:12px;color:#1a237e;">${parseFloat(t.TotalAmount||0).toFixed(2)}</td>
                <td class="text-end" style="font-size:12px;color:#dc2626;">${parseFloat(t.CashAdvance||0).toFixed(2)}</td>
                <td class="text-end" style="font-size:12px;color:#dc2626;">${parseFloat(t.OnlineAdvance||0).toFixed(2)}</td>
                <td class="text-end" style="font-size:12px;">${parseFloat(t.TDS||0).toFixed(2)}</td>
                <td class="text-end fw-bold text-primary" style="font-size:12px;">${parseFloat(t.NetAmount||0).toFixed(2)}</td>
            </tr>`;
        });
        $('#tripsBody').html(html);
        $('#tripsCard,#step3,#genBtn').show();
        updateTotals();
    }).catch(()=>Swal.fire({icon:'error',title:'Load Failed',text:'Could not fetch data from server.'}));
}

function toggleAll(cb){ $('.tripChk').prop('checked',cb.checked); updateTotals(); }
function selectAll(){ $('.tripChk').prop('checked',true); updateTotals(); }
function clearAll(){ $('.tripChk').prop('checked',false); $('#chkAll').prop('checked',false); updateTotals(); }

function updateTotals(){
    var fr=0,lab=0,hold=0,oth=0,total=0,cash=0,online=0,adv=0,tds=0,net=0,wt=0,cnt=0;
    $('.tripChk:checked').each(function(){
        var t=trips[$(this).data('idx')];
        fr+=parseFloat(t.FreightAmount||0);
        lab+=parseFloat(t.LabourCharge||0);
        hold+=parseFloat(t.HoldingCharge||0);
        oth+=parseFloat(t.OtherCharge||0);
        total+=parseFloat(t.TotalAmount||0);
        cash+=parseFloat(t.CashAdvance||0);
        online+=parseFloat(t.OnlineAdvance||0);
        adv+=parseFloat(t.AdvanceAmount||0);
        tds+=parseFloat(t.TDS||0);
        net+=parseFloat(t.NetAmount||0);
        wt+=parseFloat(t.TotalWeight||0);
        cnt++;
    });
    $('#tWt').text(wt.toFixed(3)+' T');
    $('#tFr').text(fr.toFixed(2));
    $('#tLab').text(lab.toFixed(2));
    $('#tHold').text(hold.toFixed(2));
    $('#tOth').text(oth.toFixed(2));
    $('#tTotal').text(total.toFixed(2));
    $('#tCash').text(cash.toFixed(2));
    $('#tOnline').text(online.toFixed(2));
    $('#tTds').text(tds.toFixed(2));
    $('#tNet').text(net.toFixed(2));
    $('#selCount').text(cnt+' selected');
    $('#finalNet').text('Rs. '+net.toLocaleString('en-IN',{minimumFractionDigits:2}));
    $('#finalNet').css('color', net<0 ? '#dc2626' : '#1a237e');
    $('#genBtn').toggle(cnt>0);
}

function generateBill(){
    var partyId=$('#selParty').val(), tripIds=[];
    $('.tripChk:checked').each(function(){ tripIds.push(trips[$(this).data('idx')].TripId); });
    if(!tripIds.length) return;
    var net=$('#finalNet').text();
    Swal.fire({
        title:'Generate Bill?',icon:'question',
        html:`<strong>${tripIds.length}</strong> trips selected.<br>Net Amount: <strong class='text-primary'>${net}</strong>`,
        showCancelButton:true,confirmButtonText:'Yes, Generate',
        confirmButtonColor:'#1a237e',cancelButtonColor:'#64748b'
    }).then(r=>{
        if(!r.isConfirmed) return;
        Swal.fire({title:'Generating bill...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
        var fd=new FormData();
        fd.append('generateBill',1); fd.append('PartyId',partyId);
        fd.append('tripIds',JSON.stringify(tripIds)); fd.append('BillDate',$('#billDate').val());
        fd.append('Remarks',$('#remarks').val());
        fetch('RegularBill_generate.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
            Swal.close();
            if(res.status==='success'){
                Swal.fire({icon:'success',title:'Bill Generated!',
                    html:`Bill No: <strong class='text-primary'>${res.billNo}</strong>`,
                    showCancelButton:true,confirmButtonText:'🖨️ Print',cancelButtonText:'Close',
                    confirmButtonColor:'#1a237e'
                }).then(r2=>{ if(r2.isConfirmed) window.open('RegularBill_print.php?BillId='+res.billId,'_blank','width=950,height=720'); location.reload(); });
            } else { Swal.fire({icon:'error',title:'Error',text:res.msg,confirmButtonColor:'#1a237e'}); }
        });
    });
}
</script>
<?php require_once "../layout/footer.php"; ?>
