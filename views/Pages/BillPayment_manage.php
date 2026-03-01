<?php
/* ================================================================
   BillPayment_manage.php  —  Regular Bill Payment Management
   Blue theme  |  Paid bill → delete locked  |  No commission auto-mark
================================================================ */
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once "../../businessLogics/Admin.php";
require_once "../../config/database.php";
require_once "../../businessLogics/BillPayment.php";
Admin::checkAuth();

/* ── AJAX ── */
if (isset($_GET['getPayments'])) {
    header('Content-Type: application/json');
    echo json_encode(BillPayment::getPayments($pdo, $_GET['type'] ?? 'Regular', intval($_GET['id'])));
    exit();
}
if (isset($_POST['addPayment'])) {
    header('Content-Type: application/json');
    echo json_encode(BillPayment::addPayment($pdo, $_POST['BillType'] ?? 'Regular', intval($_POST['BillId']), $_POST));
    exit();
}
if (isset($_POST['deletePayment'])) {
    header('Content-Type: application/json');
    echo json_encode(BillPayment::deletePayment($pdo, $_POST['BillType'] ?? 'Regular', intval($_POST['PaymentId'])));
    exit();
}

/* ── PAGE DATA ── */
$regBills = BillPayment::getAllRegularBills($pdo);

$totalNet     = array_sum(array_column($regBills, 'NetBillAmount'));
$totalPaid    = array_sum(array_column($regBills, 'PaidAmount'));
$totalPending = max(0, $totalNet - $totalPaid);
$cntAll       = count($regBills);
$cntGenerated = count(array_filter($regBills, fn($b) => $b['BillStatus'] === 'Generated'));
$cntPartial   = count(array_filter($regBills, fn($b) => $b['BillStatus'] === 'PartiallyPaid'));
$cntPaid      = count(array_filter($regBills, fn($b) => $b['BillStatus'] === 'Paid'));

require_once "../layout/header.php";
require_once "../layout/sidebar.php";
?>
<style>
/* ── Page Header ── */
.page-hdr {
    background: linear-gradient(135deg,#1a237e 0%,#283593 60%,#1d4ed8 100%);
    border-radius:16px; padding:22px 28px; margin-bottom:22px;
    display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;
}
.ph-left h4 { color:#fff; font-weight:800; font-size:20px; margin:0; display:flex; align-items:center; gap:10px; }
.ph-left p  { color:rgba(255,255,255,.6); font-size:12px; margin:4px 0 0; }

/* ── Stats ── */
.stat-row { display:flex; gap:14px; flex-wrap:wrap; margin-bottom:22px; }
.stat-pill {
    background:#fff; border:1px solid #e2e8f0; border-radius:12px;
    padding:14px 20px; display:flex; align-items:center; gap:14px;
    flex:1; min-width:130px; box-shadow:0 1px 4px rgba(0,0,0,.05);
}
.sp-ico { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:19px; flex-shrink:0; }
.sp-num { font-size:20px; font-weight:900; color:#1a237e; line-height:1; }
.sp-lbl { font-size:11px; color:#64748b; margin-top:3px; }

/* ── Tabs ── */
.tab-nav { display:flex; border-bottom:2px solid #c7d2fe; margin-bottom:0; gap:2px; }
.tnav {
    padding:11px 24px; font-size:13px; font-weight:700; cursor:pointer;
    border:none; background:transparent; border-bottom:3px solid transparent;
    margin-bottom:-2px; display:flex; align-items:center; gap:8px;
    color:#64748b; border-radius:8px 8px 0 0; transition:all .15s;
}
.tnav:hover { background:#f0f4ff; color:#1a237e; }
.tnav.t-all  { color:#1d4ed8; border-bottom-color:#1d4ed8; background:#eff6ff; }
.tnav.t-gen  { color:#475569; border-bottom-color:#94a3b8; background:#f8fafc; }
.tnav.t-part { color:#b45309; border-bottom-color:#f59e0b; background:#fffbeb; }
.tnav.t-paid { color:#15803d; border-bottom-color:#16a34a; background:#f0fdf4; }
.tbadge { display:inline-flex; align-items:center; justify-content:center; min-width:22px; height:20px; border-radius:10px; font-size:10px; font-weight:800; padding:0 5px; }
.b-a { background:#dbeafe; color:#1d4ed8; }
.b-g { background:#f1f5f9; color:#475569; }
.b-p { background:#fef9c3; color:#b45309; }
.b-d { background:#dcfce7; color:#15803d; }

/* ── Filter bar ── */
.fbar { padding:14px 20px; border-bottom:1px solid #c7d2fe; }
.fbar-all  { background:#f0f4ff; }
.fbar-gen  { background:#f8fafc; }
.fbar-part { background:#fffbeb; }
.fbar-paid { background:#f0fdf4; }

/* ── Table ── */
.bp-head th {
    background:#1a237e; color:#fff; font-size:12px; font-weight:700;
    padding:10px 12px; white-space:nowrap; border-bottom:2px solid #283593;
}
.tab-card { border-radius:0 0 14px 14px; overflow:hidden; border:1px solid #c7d2fe; border-top:none; }

/* Row tints */
.r-gen  { background:#fff !important; }
.r-part { background:#fffbeb !important; }
.r-paid { background:#f0fdf4 !important; }

/* Status badges */
.bs-gen  { background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.bs-part { background:#fef9c3; color:#b45309; border:1px solid #fde68a; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.bs-paid { background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.prog-wrap { height:5px; background:#e2e8f0; border-radius:3px; overflow:hidden; margin-top:4px; }
.prog-bar  { height:100%; border-radius:3px; }
.btn-ic { width:30px; height:30px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:7px; font-size:13px; }
</style>

<div class="main-content app-content">
<div class="container-fluid">

<!-- ── HEADER ── -->
<div class="page-hdr">
    <div class="ph-left">
        <h4><i class="ri-secure-payment-line"></i> Bill Payment Management</h4>
        <p>Track payments received against generated bills</p>
    </div>
    <a href="RegularTrips.php" class="btn btn-outline-light fw-semibold"
        style="border-radius:10px;height:40px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
        <i class="ri-arrow-left-line"></i> Regular Trips
    </a>
</div>

<!-- ── STATS ── -->
<div class="stat-row">
    <div class="stat-pill">
        <div class="sp-ico" style="background:#e0e7ff;"><i class="ri-file-list-3-line" style="color:#1a237e;"></i></div>
        <div><div class="sp-num"><?= $cntAll ?></div><div class="sp-lbl">Total Bills</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-ico" style="background:#f1f5f9;"><i class="ri-file-text-line" style="color:#475569;"></i></div>
        <div><div class="sp-num" style="color:#475569;"><?= $cntGenerated ?></div><div class="sp-lbl">Generated</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-ico" style="background:#fef9c3;"><i class="ri-loader-line" style="color:#b45309;"></i></div>
        <div><div class="sp-num" style="color:#b45309;"><?= $cntPartial ?></div><div class="sp-lbl">Partial</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-ico" style="background:#dcfce7;"><i class="ri-checkbox-circle-line" style="color:#15803d;"></i></div>
        <div><div class="sp-num" style="color:#15803d;"><?= $cntPaid ?></div><div class="sp-lbl">Paid</div></div>
    </div>
    <div class="stat-pill">
        <div class="sp-ico" style="background:#dbeafe;"><i class="ri-funds-line" style="color:#1d4ed8;"></i></div>
        <div>
            <div class="sp-num" style="font-size:14px;">Rs.<?= number_format($totalNet,0) ?></div>
            <div class="sp-lbl">Total Bill Amount</div>
        </div>
    </div>
    <div class="stat-pill">
        <div class="sp-ico" style="background:#dcfce7;"><i class="ri-arrow-down-circle-line" style="color:#15803d;"></i></div>
        <div>
            <div class="sp-num" style="font-size:14px;color:#15803d;">Rs.<?= number_format($totalPaid,0) ?></div>
            <div class="sp-lbl">Total Received</div>
        </div>
    </div>
    <div class="stat-pill">
        <div class="sp-ico" style="background:#fee2e2;"><i class="ri-error-warning-line" style="color:#dc2626;"></i></div>
        <div>
            <div class="sp-num" style="font-size:14px;color:#dc2626;">Rs.<?= number_format($totalPending,0) ?></div>
            <div class="sp-lbl">Still Pending</div>
        </div>
    </div>
</div>

<!-- ── TABS ── -->
<div class="tab-nav">
    <button class="tnav t-all" id="nav-all" onclick="switchTab('all')">
        <i class="ri-list-check"></i> All <span class="tbadge b-a"><?= $cntAll ?></span>
    </button>
    <button class="tnav" id="nav-gen" onclick="switchTab('gen')">
        <i class="ri-file-text-line"></i> Generated <span class="tbadge b-g"><?= $cntGenerated ?></span>
    </button>
    <button class="tnav" id="nav-part" onclick="switchTab('part')">
        <i class="ri-loader-line"></i> Partial <span class="tbadge b-p"><?= $cntPartial ?></span>
    </button>
    <button class="tnav" id="nav-paid" onclick="switchTab('paid')">
        <i class="ri-checkbox-circle-line"></i> Paid <span class="tbadge b-d"><?= $cntPaid ?></span>
    </button>
</div>

<?php
$thead = '<thead><tr class="bp-head">
    <th>#</th>
    <th>Bill No.</th>
    <th>Date</th>
    <th>Party</th>
    <th class="text-center">Trips</th>
    <th class="text-end">Net Amount</th>
    <th>Received</th>
    <th class="text-end">Remaining</th>
    <th>Status</th>
    <th class="text-center">Actions</th>
</tr></thead>';

/* Filter bar */
function fbar($id, $cls, $color) {
    global $regBills;
    $parties = array_unique(array_filter(array_column($regBills, 'PartyName')));
    sort($parties);
    ?>
<div class="fbar <?= $cls ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label fw-semibold fs-12 mb-1">Party</label>
            <select id="fp_<?= $id ?>" class="form-select form-select-sm">
                <option value="">All Parties</option>
                <?php foreach($parties as $p): ?>
                <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary btn-sm" onclick="clearF('<?= $id ?>')">
                <i class="ri-refresh-line me-1"></i>Clear
            </button>
        </div>
        <div class="col ms-auto" style="max-width:420px;">
            <label class="form-label fw-semibold fs-12 mb-1">Search</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0">
                    <i class="ri-search-line text-muted"></i>
                </span>
                <input type="text" id="sr_<?= $id ?>" class="form-control border-start-0"
                    placeholder="Bill No., Party, City...">
                <span id="fi_<?= $id ?>" class="input-group-text fw-bold text-white"
                    style="background:<?= $color ?>;min-width:52px;justify-content:center;font-size:11px;"></span>
            </div>
        </div>
    </div>
</div>
<?php }

/* Table row */
function billRow($b, $i) {
    $rem = max(0, floatval($b['NetBillAmount']) - floatval($b['PaidAmount']));
    $pct = floatval($b['NetBillAmount']) > 0
        ? min(100, round(floatval($b['PaidAmount']) / floatval($b['NetBillAmount']) * 100))
        : 0;
    $rc = ['Generated'=>'r-gen','PartiallyPaid'=>'r-part','Paid'=>'r-paid'][$b['BillStatus']] ?? 'r-gen';
    ?>
    <tr class="<?= $rc ?>">
        <td class="text-muted fw-medium" style="font-size:13px;"><?= $i ?></td>
        <td>
            <span class="fw-bold" style="color:#1a237e;font-size:13px;">
                <?= htmlspecialchars($b['BillNo']) ?>
            </span>
        </td>
        <td style="font-size:12px;white-space:nowrap;">
            <?= date('d-m-Y', strtotime($b['BillDate'])) ?>
        </td>
        <td>
            <div class="fw-semibold" style="font-size:13px;"><?= htmlspecialchars($b['PartyName']) ?></div>
            <?php if(!empty($b['City'])): ?>
            <div style="font-size:11px;color:#64748b;"><?= htmlspecialchars($b['City']) ?></div>
            <?php endif; ?>
        </td>
        <td class="text-center">
            <span class="badge" style="background:#e0e7ff;color:#3730a3;font-size:11px;">
                <?= $b['TripCount'] ?>
            </span>
        </td>
        <td class="text-end" style="font-size:13px;font-weight:800;color:#1a237e;">
            Rs.<?= number_format($b['NetBillAmount'], 0) ?>
        </td>
        <td>
            <div style="font-size:13px;font-weight:700;color:#15803d;">
                Rs.<?= number_format($b['PaidAmount'], 0) ?>
            </div>
            <div class="prog-wrap">
                <div class="prog-bar"
                    style="width:<?= $pct ?>%;background:<?= $pct>=100?'#16a34a':'#f59e0b' ?>;"></div>
            </div>
            <div style="font-size:10px;color:#94a3b8;"><?= $pct ?>%</div>
        </td>
        <td class="text-end" style="font-size:13px;font-weight:800;color:<?= $rem>0?'#dc2626':'#15803d' ?>;">
            Rs.<?= number_format($rem, 0) ?>
        </td>
        <td>
            <?php
            if($b['BillStatus']==='Generated')        echo '<span class="bs-gen">Generated</span>';
            elseif($b['BillStatus']==='PartiallyPaid') echo '<span class="bs-part">Partial</span>';
            else                                       echo '<span class="bs-paid">✓ Paid</span>';
            ?>
        </td>
        <td>
            <div class="d-flex gap-1 justify-content-center">
                <?php if($b['BillStatus'] !== 'Paid'): ?>
                <button class="btn btn-success btn-ic" title="Add Payment"
                    onclick="openPay(
                        'Regular',
                        <?= $b['BillId'] ?>,
                        <?= floatval($b['NetBillAmount']) ?>,
                        <?= floatval($b['PaidAmount']) ?>,
                        '<?= addslashes($b['BillNo']) ?>',
                        '<?= addslashes($b['PartyName']) ?>',
                        '<?= $b['BillStatus'] ?>'
                    )">
                    <i class="ri-add-circle-line"></i>
                </button>
                <?php endif; ?>
                <button class="btn btn-outline-info btn-ic" title="Payment History"
                    onclick="viewHistory(
                        'Regular',
                        <?= $b['BillId'] ?>,
                        '<?= addslashes($b['BillNo']) ?>',
                        '<?= addslashes($b['PartyName']) ?>',
                        '<?= $b['BillStatus'] ?>'
                    )">
                    <i class="ri-history-line"></i>
                </button>
                <button class="btn btn-outline-secondary btn-ic" title="Print Bill"
                    onclick="window.open('RegularBill_print.php?BillId=<?= $b['BillId'] ?>','_blank','width=950,height=720')">
                    <i class="ri-printer-line"></i>
                </button>
            </div>
        </td>
    </tr>
    <?php
}

/* Render 4 tab panes */
$tabData = [
    'all'  => ['bills' => $regBills,                                                                                                  'cls' => 'fbar-all',  'color' => '#1d4ed8'],
    'gen'  => ['bills' => array_values(array_filter($regBills, fn($b) => $b['BillStatus'] === 'Generated')),                          'cls' => 'fbar-gen',  'color' => '#475569'],
    'part' => ['bills' => array_values(array_filter($regBills, fn($b) => $b['BillStatus'] === 'PartiallyPaid')),                      'cls' => 'fbar-part', 'color' => '#f59e0b'],
    'paid' => ['bills' => array_values(array_filter($regBills, fn($b) => $b['BillStatus'] === 'Paid')),                               'cls' => 'fbar-paid', 'color' => '#16a34a'],
];

foreach($tabData as $tabId => $td):
    $display = $tabId==='all' ? 'block' : 'none';
?>
<div id="tab-<?= $tabId ?>" style="display:<?= $display ?>;">
    <?php fbar($tabId, $td['cls'], $td['color']); ?>
    <div class="tab-card">
        <div class="table-responsive">
            <table id="dt_<?= $tabId ?>" class="table table-hover align-middle mb-0 w-100">
                <?= $thead ?>
                <tbody>
                <?php $i=1; foreach($td['bills'] as $b) { billRow($b, $i++); } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endforeach; ?>

</div>
</div>

<!-- ════ PAY MODAL ════ -->
<div class="modal fade" id="payModal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;">
    <div class="modal-header text-white py-3"
        style="background:linear-gradient(135deg,#15803d,#16a34a);">
        <h5 class="modal-title fw-bold">
            <i class="ri-wallet-3-line me-2"></i>Add Payment
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <!-- Summary strip -->
        <div class="rounded p-3 mb-3" style="background:#f0fdf4;border:1px solid #bbf7d0;">
            <div class="row g-0 text-center">
                <div class="col-4" style="border-right:1px solid #bbf7d0;">
                    <div style="font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;">Bill No.</div>
                    <div class="fw-bold fs-12 mt-1" id="pm_billno">—</div>
                </div>
                <div class="col-4" style="border-right:1px solid #bbf7d0;">
                    <div style="font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;">Party</div>
                    <div class="fw-bold fs-12 mt-1" style="color:#3730a3;" id="pm_party">—</div>
                </div>
                <div class="col-4">
                    <div style="font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;">Remaining</div>
                    <div class="fw-bold mt-1" style="font-size:20px;color:#dc2626;" id="pm_rem">Rs.0</div>
                </div>
            </div>
            <div class="prog-wrap mt-3">
                <div class="prog-bar" id="pm_prog" style="width:0%;"></div>
            </div>
            <div class="d-flex justify-content-between mt-1">
                <small class="text-muted">Paid: <b id="pm_paid">Rs.0</b></small>
                <small class="text-muted">Total: <b id="pm_total">Rs.0</b></small>
            </div>
        </div>

        <input type="hidden" id="pay_Type">
        <input type="hidden" id="pay_BillId">

        <div class="row g-3">
            <div class="col-6">
                <label class="form-label fw-semibold fs-13">Date <span class="text-danger">*</span></label>
                <input type="date" id="pay_Date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6">
                <label class="form-label fw-semibold fs-13">Amount (Rs.) <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text bg-light fw-bold">Rs.</span>
                    <input type="number" id="pay_Amount" class="form-control fw-bold"
                        step="0.01" min="0.01" placeholder="0.00">
                </div>
            </div>
            <div class="col-6">
                <label class="form-label fw-semibold fs-13">Mode</label>
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
                <label class="form-label fw-semibold fs-13">Reference / Cheque No.</label>
                <input type="text" id="pay_Ref" class="form-control" placeholder="Optional">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold fs-13">Remarks</label>
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
</div></div></div>

<!-- ════ HISTORY MODAL ════ -->
<div class="modal fade" id="histModal" tabindex="-1">
<div class="modal-dialog modal-lg">
<div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;">
    <div class="modal-header text-white py-3"
        style="background:linear-gradient(135deg,#0369a1,#0284c7);">
        <h5 class="modal-title fw-bold">
            <i class="ri-history-line me-2"></i>Payment History —
            <span id="hist_label"></span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body p-0">
        <table class="table table-bordered table-sm mb-0 fs-13">
            <thead class="table-light">
                <tr>
                    <th>#</th><th>Date</th><th>Mode</th>
                    <th>Reference</th><th class="text-end">Amount</th>
                    <th>Remarks</th><th></th>
                </tr>
            </thead>
            <tbody id="histBody"></tbody>
            <tfoot>
                <tr class="table-success">
                    <td colspan="4" class="text-end fw-bold">Total Paid:</td>
                    <td class="fw-bold" id="histTotal">Rs.0</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="modal-footer py-2 d-flex align-items-center justify-content-between">
        <div id="hist_locked_note" style="display:none;">
            <span style="font-size:12px;color:#64748b;">
                <i class="ri-lock-line me-1"></i>
                Bill fully paid — payments cannot be deleted.
            </span>
        </div>
        <button class="btn btn-secondary btn-sm ms-auto" data-bs-dismiss="modal">Close</button>
    </div>
</div></div></div>

<script>
var dts = {};

$(document).ready(function(){
    var cfg = {
        scrollX: true, pageLength: 25, dom: 'rtip',
        columnDefs: [{ orderable: false, targets: [0, 9] }],
        language: { paginate: { previous: '‹', next: '›' } }
    };
    ['all','gen','part','paid'].forEach(function(id){
        dts[id] = $('#dt_'+id).DataTable({
            ...cfg,
            drawCallback: function(){
                var info = this.api().page.info();
                $('#fi_'+id).text(info.recordsDisplay + '/' + info.recordsTotal);
            }
        });
        $('#sr_'+id).on('keyup input', function(){ dts[id].search($(this).val()).draw(); });
        // Party filter on col index 3
        $('#fp_'+id).on('change', function(){ dts[id].column(3).search(this.value || '').draw(); });
    });
});

function switchTab(name){
    var clsMap = { all:'t-all', gen:'t-gen', part:'t-part', paid:'t-paid' };
    ['all','gen','part','paid'].forEach(function(t){
        document.getElementById('nav-'+t).className = 'tnav';
        document.getElementById('tab-'+t).style.display = 'none';
    });
    document.getElementById('nav-'+name).classList.add(clsMap[name]);
    document.getElementById('tab-'+name).style.display = 'block';
    if(dts[name]) dts[name].columns.adjust();
}

function clearF(id){
    $('#fp_'+id).val('').trigger('change');
    $('#sr_'+id).val('');
    if(dts[id]) dts[id].search('').draw();
}

function openPay(type, id, net, paid, billno, party, status){
    var rem = Math.max(0, net - paid);
    var pct = net > 0 ? Math.min(100, Math.round(paid/net*100)) : 0;
    $('#pay_Type').val(type);
    $('#pay_BillId').val(id);
    $('#pm_billno').text(billno);
    $('#pm_party').text(party);
    $('#pm_rem').text('Rs.' + rem.toFixed(2));
    $('#pm_paid').text('Rs.' + parseFloat(paid).toFixed(2));
    $('#pm_total').text('Rs.' + parseFloat(net).toFixed(2));
    $('#pm_prog').css('width', pct + '%');
    $('#pay_Amount').val(rem > 0 ? rem.toFixed(2) : '');
    $('#pay_Date').val('<?= date('Y-m-d') ?>');
    $('#pay_Mode').val('Cash');
    $('#pay_Ref, #pay_Remarks').val('');
    new bootstrap.Modal('#payModal').show();
}

function submitPay(){
    var amt = parseFloat($('#pay_Amount').val());
    if(!amt || amt <= 0){
        Swal.fire({ icon:'warning', title:'Enter valid amount!', toast:true, position:'top-end', timer:2000, showConfirmButton:false });
        return;
    }
    Swal.fire({ title:'Saving...', allowOutsideClick:false, didOpen:()=>Swal.showLoading() });
    var fd = new FormData();
    fd.append('addPayment', 1);
    fd.append('BillType',    $('#pay_Type').val());
    fd.append('BillId',      $('#pay_BillId').val());
    fd.append('PaymentDate', $('#pay_Date').val());
    fd.append('Amount',      amt);
    fd.append('PaymentMode', $('#pay_Mode').val());
    fd.append('ReferenceNo', $('#pay_Ref').val());
    fd.append('Remarks',     $('#pay_Remarks').val());
    fetch('BillPayment_manage.php', { method:'POST', body:fd })
        .then(r => r.json()).then(res => {
            Swal.close();
            if(res.status === 'success'){
                bootstrap.Modal.getInstance(document.getElementById('payModal')).hide();
                Swal.fire({ icon:'success', title:'Payment Saved!', toast:true, position:'top-end', showConfirmButton:false, timer:2500 });
                setTimeout(() => location.reload(), 1800);
            } else {
                Swal.fire({ icon:'error', title:'Error', text:res.msg });
            }
        }).catch(() => Swal.fire({ icon:'error', title:'Server Error' }));
}

function viewHistory(type, id, billno, party, billStatus){
    var isPaid = (billStatus === 'Paid');
    $('#hist_label').text(billno + ' — ' + party);
    $('#hist_locked_note').toggle(isPaid);
    $('#histBody').html('<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>');
    new bootstrap.Modal('#histModal').show();

    fetch('BillPayment_manage.php?getPayments=1&type=' + type + '&id=' + id)
        .then(r => r.json()).then(rows => {
            var html = '', total = 0;
            var icons = { Cash:'💵', Cheque:'📋', NEFT:'🏦', RTGS:'🏦', UPI:'📱', Other:'💳' };
            if(!rows.length){
                html = '<tr><td colspan="7" class="text-center text-muted py-3">No payments yet</td></tr>';
            }
            rows.forEach(function(p, i){
                var pid = type === 'Regular' ? p.BillPaymentId : p.AgentBillPaymentId;
                total += parseFloat(p.Amount || 0);
                /* Delete only if NOT fully Paid */
                var actionCell = isPaid
                    ? '<span title="Bill fully paid — cannot delete" style="color:#94a3b8;cursor:default;"><i class="ri-lock-line"></i></span>'
                    : '<button class="btn btn-sm btn-outline-danger btn-ic" onclick="delPayment(\''+type+'\','+pid+')">'
                      + '<i class="ri-delete-bin-line"></i></button>';
                html += '<tr id="pr-'+pid+'">'
                    + '<td>' + (i+1) + '</td>'
                    + '<td style="white-space:nowrap">' + p.PaymentDate + '</td>'
                    + '<td>' + (icons[p.PaymentMode]||'') + ' ' + p.PaymentMode + '</td>'
                    + '<td><small>' + (p.ReferenceNo||'—') + '</small></td>'
                    + '<td class="text-end fw-bold text-success">Rs.' + parseFloat(p.Amount).toFixed(2) + '</td>'
                    + '<td><small>' + (p.Remarks||'—') + '</small></td>'
                    + '<td>' + actionCell + '</td></tr>';
            });
            $('#histBody').html(html);
            $('#histTotal').text('Rs.' + total.toFixed(2));
        });
}

function delPayment(type, pid){
    Swal.fire({
        title:'Delete payment?', icon:'warning',
        showCancelButton:true, confirmButtonText:'Delete', confirmButtonColor:'#dc3545'
    }).then(r => {
        if(!r.isConfirmed) return;
        var fd = new FormData();
        fd.append('deletePayment', 1);
        fd.append('BillType', type);
        fd.append('PaymentId', pid);
        fetch('BillPayment_manage.php', { method:'POST', body:fd })
            .then(r => r.json()).then(res => {
                if(res.status === 'success'){
                    $('#pr-'+pid).fadeOut(300, function(){ $(this).remove(); });
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Swal.fire({ icon:'error', title:'Error', text:res.msg });
                }
            });
    });
}

window.addEventListener('offline', () => Swal.fire({ icon:'warning', title:'Disconnected!',   toast:true, position:'top-end', showConfirmButton:false, timer:3000 }));
window.addEventListener('online',  () => Swal.fire({ icon:'success', title:'Back Online!',    toast:true, position:'top-end', showConfirmButton:false, timer:2000 }));
</script>
<?php require_once "../layout/footer.php"; ?>
