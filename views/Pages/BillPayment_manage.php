<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once "../../businessLogics/Admin.php";
require_once "../../config/database.php";
require_once "../../businessLogics/BillPayment.php";
Admin::checkAuth();

/* ═══════════ AJAX HANDLERS ═══════════ */
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
if (isset($_POST['markOwnerReceived'])) {
    header('Content-Type: application/json');
    $ids  = json_decode($_POST['commIds'], true);
    $date = $_POST['ReceivedDate'] ?? date('Y-m-d');
    echo json_encode(BillPayment::markOwnerCommissionReceived($pdo, $ids, $date));
    exit();
}

/* ═══════════ PAGE DATA ═══════════ */
$regBills   = BillPayment::getAllRegularBills($pdo);
$ownerTrips = BillPayment::getOwnerRecoveryTrips($pdo);

$regTotal   = array_sum(array_column($regBills,   'NetBillAmount'));
$regPaid    = array_sum(array_column($regBills,   'PaidAmount'));
$ownerPendComm = array_sum(array_map(
    fn($r) => $r['CommissionStatus'] !== 'Received' ? floatval($r['CommissionAmount'] ?? 0) : 0,
    $ownerTrips
));

require_once "../layout/header.php";
require_once "../layout/sidebar.php";
?>

<div class="main-content app-content">
<div class="container-fluid">

<!-- PAGE HEADER -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="fw-bold mb-0"><i class="ri-secure-payment-line me-2 text-success"></i>Bill Payment Management</h5>
    <p class="text-muted fs-12 mb-0">Multiple payments per bill &nbsp;|&nbsp; Commission auto-marked on full payment</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <div class="card border-0 shadow-sm mb-0" style="background:#fff5f5; min-width:140px">
      <div class="card-body p-2 text-center">
        <div class="fs-11 text-muted">Regular Pending</div>
        <div class="fw-bold text-danger fs-14">Rs.<?= number_format($regTotal - $regPaid, 0) ?></div>
      </div>
    </div>
    <?php if ($ownerPendComm > 0): ?>
    <div class="card border-0 shadow-sm mb-0" style="background:#f5f5f5; min-width:140px">
      <div class="card-body p-2 text-center">
        <div class="fs-11 text-muted">Owner Comm. Due</div>
        <div class="fw-bold fs-14">Rs.<?= number_format($ownerPendComm, 0) ?></div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- TABS -->
<ul class="nav nav-tabs mb-0" id="payTabs">
  <li class="nav-item">
    <a class="nav-link active fw-semibold" data-bs-toggle="tab" href="#tabReg">
      <i class="ri-file-list-3-line me-1 text-primary"></i>Regular Bills
      <span class="badge bg-primary ms-1"><?= count($regBills) ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link fw-semibold" data-bs-toggle="tab" href="#tabOwner">
      <i class="ri-truck-line me-1"></i>Owner Recovery
      <?php
        $pendCount = count(array_filter($ownerTrips, fn($r) => $r['CommissionStatus'] !== 'Received'));
        if ($pendCount > 0): ?>
      <span class="badge bg-danger ms-1"><?= $pendCount ?></span>
      <?php endif; ?>
    </a>
  </li>
</ul>

<div class="tab-content">

<!-- ════ TAB: REGULAR BILLS ════ -->
<div class="tab-pane fade show active" id="tabReg">
<div class="card custom-card shadow-sm rounded-0 rounded-bottom border-top-0">
<div class="card-body p-0">
<div class="table-responsive">
<table id="regTable" class="table table-bordered table-hover mb-0 fs-13">
  <thead class="table-primary">
    <tr><th>#</th><th>Bill No.</th><th>Date</th><th>Party</th><th>Trips</th><th>Net Amt</th><th>Paid</th><th>Remaining</th><th>Status</th><th>Actions</th></tr>
  </thead>
  <tbody>
  <?php foreach ($regBills as $i => $b):
    $rem = $b['NetBillAmount'] - $b['PaidAmount'];
    $pct = $b['NetBillAmount'] > 0 ? min(100, round($b['PaidAmount'] / $b['NetBillAmount'] * 100)) : 0;
    $sc  = ['Generated' => 'bg-secondary', 'PartiallyPaid' => 'bg-warning text-dark', 'Paid' => 'bg-success'];
    $ss  = $sc[$b['BillStatus']] ?? 'bg-secondary';
  ?>
    <tr class="<?= $b['BillStatus'] === 'Paid' ? 'table-success' : '' ?>">
      <td class="text-muted"><?= $i + 1 ?></td>
      <td class="fw-bold text-primary"><?= htmlspecialchars($b['BillNo']) ?></td>
      <td style="white-space:nowrap"><?= date('d-m-Y', strtotime($b['BillDate'])) ?></td>
      <td>
        <div class="fw-semibold"><?= htmlspecialchars($b['PartyName']) ?></div>
        <small class="text-muted"><?= htmlspecialchars($b['City'] ?? '') ?></small>
      </td>
      <td class="text-center"><span class="badge bg-info"><?= $b['TripCount'] ?></span></td>
      <td class="fw-bold">Rs.<?= number_format($b['NetBillAmount'], 2) ?></td>
      <td>
        <div class="text-success fw-bold">Rs.<?= number_format($b['PaidAmount'], 2) ?></div>
        <div class="progress mt-1" style="height:5px;min-width:70px">
          <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
        </div>
        <div class="fs-11 text-muted"><?= $pct ?>%</div>
      </td>
      <td class="fw-bold <?= max(0, $rem) > 0 ? 'text-danger' : 'text-success' ?>">Rs.<?= number_format(max(0, $rem), 2) ?></td>
      <td><span class="badge <?= $ss ?>"><?= $b['BillStatus'] ?></span></td>
      <td style="white-space:nowrap">
        <?php if ($b['BillStatus'] !== 'Paid'): ?>
        <button class="btn btn-sm btn-success me-1"
          onclick="openPay('Regular',<?= $b['BillId'] ?>,<?= $b['NetBillAmount'] ?>,<?= $b['PaidAmount'] ?>,'<?= addslashes($b['BillNo']) ?>','<?= addslashes($b['PartyName']) ?>')">
          <i class="ri-add-circle-line"></i> Pay
        </button>
        <?php endif; ?>
        <button class="btn btn-sm btn-outline-info me-1"
          onclick="viewHistory('Regular',<?= $b['BillId'] ?>,'<?= addslashes($b['BillNo']) ?>','<?= addslashes($b['PartyName']) ?>')">
          <i class="ri-history-line"></i>
        </button>
        <button class="btn btn-sm btn-outline-dark"
          onclick="window.open('RegularBill_print.php?BillId=<?= $b['BillId'] ?>','_blank','width=950,height=720')">
          <i class="ri-printer-line"></i>
        </button>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div></div></div>
</div>

<!-- ════ TAB: OWNER RECOVERY ════ -->
<div class="tab-pane fade" id="tabOwner">
<div class="card custom-card shadow-sm rounded-0 rounded-bottom border-top-0">
  <div class="card-header bg-dark text-white d-flex align-items-center justify-content-between py-2">
    <div>
      <i class="ri-alert-line me-2"></i><strong>Owner Commission Recovery</strong>
      <small class="ms-2 opacity-75">Freight paid directly to owner — commission must be recovered from owner</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <span class="badge bg-warning text-dark" id="ownerSelCount" style="display:none">0 selected</span>
      <button class="btn btn-success btn-sm fw-bold" id="ownerMarkBtn" onclick="openOwnerMarkModal()" style="display:none">
        <i class="ri-check-double-line me-1"></i>Mark Received
      </button>
    </div>
  </div>
  <div class="card-body p-0">
  <div class="table-responsive">
  <table id="ownerTable" class="table table-bordered table-hover mb-0 fs-13">
    <thead class="table-dark">
      <tr>
        <th width="36"><input type="checkbox" id="ownerChkAll" onchange="toggleOwnerAll(this)"></th>
        <th>#</th><th>Date</th><th>Vehicle</th><th>Route / Party</th><th>Type</th>
        <th>Freight</th><th>Commission Due</th><th>Status</th><th>Received On</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($ownerTrips as $i => $r):
      $isPend = ($r['CommissionStatus'] ?? 'Pending') !== 'Received';
      $party  = $r['TripType'] === 'Agent' ? ($r['AgentName'] ?? '—') : ($r['ConsignerName'] ?? '—');
    ?>
      <tr class="<?= !$isPend ? 'table-success' : '' ?>">
        <td class="text-center">
          <?php if ($isPend && ($r['CommissionAmount'] ?? 0) > 0): ?>
          <input type="checkbox" class="owner-chk" data-id="<?= $r['TripCommissionId'] ?>">
          <?php endif; ?>
        </td>
        <td class="text-muted"><?= $i + 1 ?></td>
        <td style="white-space:nowrap"><?= date('d-m-Y', strtotime($r['TripDate'])) ?></td>
        <td><span class="badge bg-secondary"><?= htmlspecialchars($r['VehicleNumber'] ?? '—') ?></span></td>
        <td style="font-size:11px">
          <?= htmlspecialchars($r['FromLocation']) ?> → <?= htmlspecialchars($r['ToLocation']) ?><br>
          <small class="text-muted"><?= htmlspecialchars($party) ?></small>
        </td>
        <td><span class="badge <?= $r['TripType'] === 'Agent' ? 'bg-warning text-dark' : 'bg-primary' ?>"><?= $r['TripType'] ?></span></td>
        <td class="fw-bold">Rs.<?= number_format($r['FreightAmount'], 0) ?></td>
        <td class="fw-bold <?= $isPend ? 'text-danger' : 'text-success' ?>">
          <?= ($r['CommissionAmount'] ?? 0) > 0 ? 'Rs.' . number_format($r['CommissionAmount'], 2) : '<span class="text-muted fs-11">Not set</span>' ?>
        </td>
        <td>
          <?php if (!$isPend): ?>
            <span class="badge bg-success"><i class="ri-check-line me-1"></i>Received</span>
          <?php elseif (($r['CommissionAmount'] ?? 0) > 0): ?>
            <span class="badge bg-danger">Pending</span>
          <?php else: ?>
            <span class="badge bg-secondary">No Commission</span>
          <?php endif; ?>
        </td>
        <td style="white-space:nowrap"><?= $r['ReceivedDate'] ? date('d-m-Y', strtotime($r['ReceivedDate'])) : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div></div>
</div>
</div>

</div><!-- /tab-content -->
</div>
</div>

<!-- ════ MODALS ════ -->

<!-- Pay Modal -->
<div class="modal fade" id="payModal" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
  <div class="modal-header bg-success text-white py-2">
    <h5 class="modal-title fs-14 fw-bold"><i class="ri-money-dollar-circle-line me-2"></i>Add Payment</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body">
    <div class="border rounded p-2 mb-3 bg-light">
      <div class="row g-0 text-center mb-2">
        <div class="col-4 border-end"><div class="fs-11 text-muted">Bill No.</div><div class="fw-bold fs-13" id="pm_billno">—</div></div>
        <div class="col-4 border-end"><div class="fs-11 text-muted">Party</div><div class="fw-bold fs-12" id="pm_party">—</div></div>
        <div class="col-4"><div class="fs-11 text-muted">Remaining</div><div class="fw-bold text-danger fs-15" id="pm_rem">Rs. 0</div></div>
      </div>
      <div class="progress" style="height:8px">
        <div class="progress-bar bg-success" id="pm_prog" style="width:0%"></div>
      </div>
      <div class="d-flex justify-content-between mt-1">
        <small class="text-muted">Paid: <b id="pm_paid">Rs. 0</b></small>
        <small class="text-muted">Total: <b id="pm_total">Rs. 0</b></small>
      </div>
    </div>
    <input type="hidden" id="pay_Type"><input type="hidden" id="pay_BillId"><input type="hidden" id="pay_Net">
    <div class="row g-3">
      <div class="col-6">
        <label class="form-label fw-medium fs-13">Payment Date <span class="text-danger">*</span></label>
        <input type="date" id="pay_Date" class="form-control" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="col-6">
        <label class="form-label fw-medium fs-13">Amount (Rs.) <span class="text-danger">*</span></label>
        <div class="input-group"><span class="input-group-text">Rs.</span>
          <input type="number" id="pay_Amount" class="form-control fw-bold" step="0.01" min="0.01" placeholder="0.00">
        </div>
      </div>
      <div class="col-6">
        <label class="form-label fw-medium fs-13">Mode <span class="text-danger">*</span></label>
        <select id="pay_Mode" class="form-select">
          <option value="Cash">💵 Cash</option><option value="Cheque">📋 Cheque</option>
          <option value="NEFT">🏦 NEFT</option><option value="RTGS">🏦 RTGS</option>
          <option value="UPI">📱 UPI</option><option value="Other">Other</option>
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
    <button class="btn btn-success fw-bold" onclick="submitPay()"><i class="ri-save-3-line me-1"></i>Save Payment</button>
  </div>
</div></div>
</div>

<!-- History Modal -->
<div class="modal fade" id="histModal" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content">
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
          <td class="fw-bold" id="histTotal">Rs. 0</td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <div class="modal-footer py-2">
    <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
  </div>
</div></div>
</div>

<!-- Owner Mark Received Modal -->
<div class="modal fade" id="ownerMarkModal" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
  <div class="modal-header bg-success text-white py-2">
    <h5 class="modal-title fs-14"><i class="ri-check-double-line me-2"></i>Mark Owner Commission Received</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body">
    <div class="alert alert-success"><strong id="ownerSelCnt">0</strong> commission(s) will be marked as Received (from Owner).</div>
    <label class="form-label fw-medium">Date Received</label>
    <input type="date" id="ownerRecDate" class="form-control" value="<?= date('Y-m-d') ?>">
  </div>
  <div class="modal-footer py-2">
    <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
    <button class="btn btn-success fw-bold" onclick="confirmOwnerMark()"><i class="ri-check-line me-1"></i>Confirm</button>
  </div>
</div></div>
</div>

<script>
$(document).ready(function(){
  $('#regTable').DataTable({pageLength:25,order:[[0,'desc']],language:{search:'',searchPlaceholder:'Search...'}});
  $('#ownerTable').DataTable({pageLength:25,order:[[1,'desc']],language:{search:'',searchPlaceholder:'Search...'}});
});

function openPay(type,id,net,paid,billno,party){
  var rem=Math.max(0,parseFloat(net)-parseFloat(paid));
  var pct=parseFloat(net)>0?Math.min(100,Math.round(parseFloat(paid)/parseFloat(net)*100)):0;
  $('#pay_Type').val(type); $('#pay_BillId').val(id); $('#pay_Net').val(net);
  $('#pm_billno').text(billno); $('#pm_party').text(party);
  $('#pm_rem').text('Rs. '+rem.toFixed(2));
  $('#pm_paid').text('Rs. '+parseFloat(paid).toFixed(2));
  $('#pm_total').text('Rs. '+parseFloat(net).toFixed(2));
  $('#pm_prog').css('width',pct+'%');
  $('#pay_Amount').val(rem>0?rem.toFixed(2):'');
  $('#pay_Date').val('<?= date('Y-m-d') ?>');
  $('#pay_Mode').val('Cash'); $('#pay_Ref,#pay_Remarks').val('');
  new bootstrap.Modal('#payModal').show();
}

function submitPay(){
  var amt=parseFloat($('#pay_Amount').val());
  if(!amt||amt<=0){Swal.fire({icon:'warning',title:'Enter valid amount!',toast:true,position:'top-end',timer:2000,showConfirmButton:false});return;}
  Swal.fire({title:'Saving...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
  var fd=new FormData();
  fd.append('addPayment',1); fd.append('BillType',$('#pay_Type').val()); fd.append('BillId',$('#pay_BillId').val());
  fd.append('PaymentDate',$('#pay_Date').val()); fd.append('Amount',amt);
  fd.append('PaymentMode',$('#pay_Mode').val()); fd.append('ReferenceNo',$('#pay_Ref').val());
  fd.append('Remarks',$('#pay_Remarks').val());
  fetch('BillPayment_manage.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
    Swal.close();
    if(res.status==='success'){
      bootstrap.Modal.getInstance(document.getElementById('payModal')).hide();
      var msg='Payment saved!'; if(res.commAuto) msg+=' Commission auto-marked Received.';
      Swal.fire({icon:'success',title:msg,toast:true,position:'top-end',showConfirmButton:false,timer:3000});
      setTimeout(()=>location.reload(),2000);
    } else Swal.fire({icon:'error',title:'Error',text:res.msg});
  }).catch(()=>Swal.fire({icon:'error',title:'Server Error'}));
}

function viewHistory(type,id,billno,party){
  $('#hist_label').text(billno+' — '+party);
  $('#histBody').html('<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>');
  new bootstrap.Modal('#histModal').show();
  fetch('BillPayment_manage.php?getPayments=1&type='+type+'&id='+id)
  .then(r=>r.json()).then(rows=>{
    var html='',total=0;
    var icons={Cash:'💵',Cheque:'📋',NEFT:'🏦',RTGS:'🏦',UPI:'📱',Other:'💳'};
    if(!rows.length){html='<tr><td colspan="7" class="text-center text-muted py-3">No payments yet</td></tr>';}
    rows.forEach(function(p,i){
      var pid=type==='Regular'?p.BillPaymentId:p.AgentBillPaymentId;
      total+=parseFloat(p.Amount||0);
      html+='<tr id="pr-'+pid+'"><td>'+(i+1)+'</td><td>'+p.PaymentDate+'</td>'
        +'<td>'+(icons[p.PaymentMode]||'')+' '+p.PaymentMode+'</td>'
        +'<td><small>'+(p.ReferenceNo||'—')+'</small></td>'
        +'<td class="fw-bold text-success">Rs.'+parseFloat(p.Amount).toFixed(2)+'</td>'
        +'<td><small>'+(p.Remarks||'—')+'</small></td>'
        +'<td><button class="btn btn-sm btn-outline-danger" onclick="delPayment(\''+type+'\','+pid+')">'
        +'<i class="ri-delete-bin-line"></i></button></td></tr>';
    });
    $('#histBody').html(html); $('#histTotal').text('Rs.'+total.toFixed(2));
  });
}

function delPayment(type,pid){
  Swal.fire({title:'Delete payment?',icon:'warning',showCancelButton:true,confirmButtonText:'Delete',confirmButtonColor:'#dc3545'})
  .then(r=>{
    if(!r.isConfirmed)return;
    var fd=new FormData(); fd.append('deletePayment',1); fd.append('BillType',type); fd.append('PaymentId',pid);
    fetch('BillPayment_manage.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
      if(res.status==='success'){$('#pr-'+pid).fadeOut(300,function(){$(this).remove();});setTimeout(()=>location.reload(),1500);}
      else Swal.fire({icon:'error',title:'Error',text:res.msg});
    });
  });
}

function toggleOwnerAll(cb){ $('.owner-chk:visible').prop('checked',cb.checked); updateOwnerSel(); }
$(document).on('change','.owner-chk',updateOwnerSel);
function updateOwnerSel(){
  var n=$('.owner-chk:checked').length;
  $('#ownerSelCount').text(n+' selected').toggle(n>0);
  $('#ownerMarkBtn').toggle(n>0);
}
function openOwnerMarkModal(){
  var ids=[]; $('.owner-chk:checked').each(function(){ids.push($(this).data('id'));});
  if(!ids.length)return;
  window._ownerIds=ids; $('#ownerSelCnt').text(ids.length);
  new bootstrap.Modal('#ownerMarkModal').show();
}
function confirmOwnerMark(){
  var ids=window._ownerIds||[]; var date=$('#ownerRecDate').val();
  if(!ids.length||!date)return;
  Swal.fire({title:'Saving...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
  var fd=new FormData();
  fd.append('markOwnerReceived',1); fd.append('commIds',JSON.stringify(ids)); fd.append('ReceivedDate',date);
  fetch('BillPayment_manage.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
    Swal.close();
    if(res.status==='success'){
      bootstrap.Modal.getInstance(document.getElementById('ownerMarkModal')).hide();
      Swal.fire({icon:'success',title:res.count+' Commissions marked Received!',toast:true,position:'top-end',showConfirmButton:false,timer:2500});
      setTimeout(()=>location.reload(),2000);
    } else Swal.fire({icon:'error',title:'Error',text:res.msg});
  });
}

window.addEventListener('offline',()=>Swal.fire({icon:'warning',title:'Internet Disconnected!',toast:true,position:'top-end',showConfirmButton:false,timer:3000}));
window.addEventListener('online', ()=>Swal.fire({icon:'success',title:'Back Online!',toast:true,position:'top-end',showConfirmButton:false,timer:2000}));
</script>

<?php require_once "../layout/footer.php"; ?>
