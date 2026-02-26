<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once "../../businessLogics/Admin.php";
require_once "../../config/database.php";
require_once "../../businessLogics/Party.php";
require_once "../../businessLogics/PartyAdvanceLogic.php";
Admin::checkAuth();

/* ═══════════ AJAX HANDLERS ═══════════ */
if (isset($_POST['addAdvance'])) {
    header('Content-Type: application/json');
    echo json_encode(PartyAdvanceLogic::addAdvance($pdo, $_POST));
    exit();
}
if (isset($_GET['getPartyBills'])) {
    header('Content-Type: application/json');
    echo json_encode(PartyAdvanceLogic::getPartyBills($pdo, intval($_GET['PartyId'])));
    exit();
}
if (isset($_POST['adjustAdvance'])) {
    header('Content-Type: application/json');
    echo json_encode(PartyAdvanceLogic::adjustAdvance($pdo, $_POST));
    exit();
}
if (isset($_GET['getAdjustments'])) {
    header('Content-Type: application/json');
    echo json_encode(PartyAdvanceLogic::getAdjustments($pdo, intval($_GET['AdvanceId'])));
    exit();
}

/* ═══════════ PAGE DATA ═══════════ */
$advances = PartyAdvanceLogic::getAll($pdo);
$summary  = PartyAdvanceLogic::getSummary($pdo);
$parties  = array_filter(Party::getAll(), fn($p) => $p['IsActive'] === 'Yes');

require_once "../layout/header.php";
require_once "../layout/sidebar.php";
?>

<div class="main-content app-content">
<div class="container-fluid">

<!-- PAGE HEADER -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="fw-bold mb-0"><i class="ri-hand-coin-line me-2" style="color:#7c3aed"></i>Party Advance Management</h5>
    <p class="text-muted fs-12 mb-0">Advance received from party — adjust against bills automatically</p>
  </div>
  <button class="btn fw-bold text-white" style="background:#7c3aed" onclick="new bootstrap.Modal('#addAdvModal').show()">
    <i class="ri-add-circle-line me-1"></i>New Advance Entry
  </button>
</div>

<!-- SUMMARY CARDS -->
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center">
      <div class="card-body py-2">
        <div class="fs-11 text-muted">Total Advances</div>
        <div class="fw-bold fs-16" style="color:#7c3aed">Rs.<?= number_format($summary['total'], 0) ?></div>
        <div class="fs-11 text-muted"><?= $summary['count'] ?> entries</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center">
      <div class="card-body py-2">
        <div class="fs-11 text-muted">Adjusted (Used)</div>
        <div class="fw-bold fs-16 text-success">Rs.<?= number_format($summary['adjusted'], 0) ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center">
      <div class="card-body py-2">
        <div class="fs-11 text-muted">Remaining Balance</div>
        <div class="fw-bold fs-16 text-danger">Rs.<?= number_format($summary['remaining'], 0) ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center">
      <div class="card-body py-2">
        <div class="fs-11 text-muted">Open Advances</div>
        <div class="fw-bold fs-16 text-warning"><?= $summary['open'] ?></div>
        <div class="fs-11 text-muted">not fully used</div>
      </div>
    </div>
  </div>
</div>

<!-- TABLE -->
<div class="card custom-card shadow-sm">
<div class="card-body p-0">
<div class="table-responsive">
<table id="advTable" class="table table-bordered table-hover mb-0 fs-13">
  <thead class="table-light">
    <tr><th>#</th><th>Date</th><th>Party</th><th>Mode</th><th>Reference</th><th>Advance</th><th>Adjusted</th><th>Remaining</th><th>Status</th><th>Remarks</th><th>Actions</th></tr>
  </thead>
  <tbody>
  <?php foreach ($advances as $i => $a):
    $sc = ['Open'=>'bg-warning text-dark','PartiallyAdjusted'=>'bg-info text-dark','FullyAdjusted'=>'bg-success'];
    $ss = $sc[$a['Status']] ?? 'bg-secondary';
    $pct = $a['Amount'] > 0 ? min(100, round($a['AdjustedAmount'] / $a['Amount'] * 100)) : 0;
  ?>
    <tr>
      <td class="text-muted"><?= $i + 1 ?></td>
      <td style="white-space:nowrap"><?= date('d-m-Y', strtotime($a['AdvanceDate'])) ?></td>
      <td>
        <div class="fw-bold"><?= htmlspecialchars($a['PartyName']) ?></div>
        <small class="text-muted"><?= htmlspecialchars($a['City'] ?? '') ?><?= $a['MobileNo'] ? ' | ' . $a['MobileNo'] : '' ?></small>
      </td>
      <td><?= $a['PaymentMode'] ?></td>
      <td><small class="text-muted"><?= htmlspecialchars($a['ReferenceNo'] ?? '—') ?></small></td>
      <td class="fw-bold" style="color:#7c3aed">Rs.<?= number_format($a['Amount'], 2) ?></td>
      <td>
        <div class="text-success fw-bold">Rs.<?= number_format($a['AdjustedAmount'], 2) ?></div>
        <div class="progress mt-1" style="height:5px;min-width:60px">
          <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
        </div>
      </td>
      <td class="fw-bold <?= floatval($a['RemainingAmount']) > 0 ? 'text-danger' : 'text-success' ?>">Rs.<?= number_format($a['RemainingAmount'], 2) ?></td>
      <td><span class="badge <?= $ss ?>"><?= $a['Status'] ?></span></td>
      <td><small class="text-muted"><?= htmlspecialchars($a['Remarks'] ?? '—') ?></small></td>
      <td style="white-space:nowrap">
        <?php if ($a['Status'] !== 'FullyAdjusted'): ?>
        <button class="btn btn-sm btn-outline-primary me-1" title="Adjust against bill"
          onclick="openAdjModal(<?= $a['PartyAdvanceId'] ?>,<?= $a['PartyId'] ?>,<?= $a['RemainingAmount'] ?>,'<?= addslashes($a['PartyName']) ?>','<?= date('d-m-Y', strtotime($a['AdvanceDate'])) ?>')">
          <i class="ri-links-line"></i> Adjust
        </button>
        <?php endif; ?>
        <button class="btn btn-sm btn-outline-info" onclick="viewAdj(<?= $a['PartyAdvanceId'] ?>,'<?= addslashes($a['PartyName']) ?>')">
          <i class="ri-history-line"></i>
        </button>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div></div></div>

</div></div>

<!-- ════ MODALS ════ -->

<!-- Add Advance Modal -->
<div class="modal fade" id="addAdvModal" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
  <div class="modal-header py-2 text-white" style="background:#7c3aed">
    <h5 class="modal-title fs-14 fw-bold"><i class="ri-hand-coin-line me-2"></i>New Party Advance Entry</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body">
    <div class="row g-3">
      <div class="col-12">
        <label class="form-label fw-medium">Party <span class="text-danger">*</span></label>
        <select id="adv_Party" class="form-select">
          <option value="">-- Select Party --</option>
          <?php foreach ($parties as $p): ?>
          <option value="<?= $p['PartyId'] ?>"><?= htmlspecialchars($p['PartyName']) ?><?= $p['City'] ? ' — ' . htmlspecialchars($p['City']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6">
        <label class="form-label fw-medium">Date <span class="text-danger">*</span></label>
        <input type="date" id="adv_Date" class="form-control" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="col-6">
        <label class="form-label fw-medium">Amount (Rs.) <span class="text-danger">*</span></label>
        <div class="input-group"><span class="input-group-text">Rs.</span>
          <input type="number" id="adv_Amount" class="form-control fw-bold" step="0.01" min="0.01" placeholder="0.00">
        </div>
      </div>
      <div class="col-6">
        <label class="form-label fw-medium">Payment Mode</label>
        <select id="adv_Mode" class="form-select">
          <option value="Cash">💵 Cash</option><option value="Cheque">📋 Cheque</option>
          <option value="NEFT">🏦 NEFT</option><option value="RTGS">🏦 RTGS</option>
          <option value="UPI">📱 UPI</option><option value="Other">Other</option>
        </select>
      </div>
      <div class="col-6">
        <label class="form-label fw-medium">Reference / Cheque No.</label>
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
      <i class="ri-save-3-line me-1"></i>Save Advance
    </button>
  </div>
</div></div>
</div>

<!-- Adjust Modal -->
<div class="modal fade" id="adjModal" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header bg-primary text-white py-2">
    <h5 class="modal-title fs-14"><i class="ri-links-line me-2"></i>Adjust Advance Against Bill</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body">
    <div class="alert alert-light border p-2 mb-3">
      <div class="row text-center g-0">
        <div class="col-4 border-end"><div class="fs-11 text-muted">Party</div><div class="fw-bold" id="adj_partyName">—</div></div>
        <div class="col-4 border-end"><div class="fs-11 text-muted">Advance Date</div><div class="fw-bold" id="adj_advDate">—</div></div>
        <div class="col-4"><div class="fs-11 text-muted">Available Balance</div><div class="fw-bold text-danger fs-15" id="adj_rem">Rs. 0</div></div>
      </div>
    </div>
    <input type="hidden" id="adj_AdvId"><input type="hidden" id="adj_PartyId">
    <div class="mb-3">
      <label class="form-label fw-medium">Select Unpaid Bill <span class="text-danger">*</span></label>
      <select id="adj_Bill" class="form-select" onchange="billSel()">
        <option value="">-- Select bill --</option>
      </select>
    </div>
    <div id="adj_billInfo" class="alert alert-info p-2 mb-3 fs-13" style="display:none">
      Bill Net: <b id="adj_billNet">Rs. 0</b> &nbsp;|&nbsp; Paid: <b id="adj_billPaid">Rs. 0</b> &nbsp;|&nbsp; Due: <b class="text-danger" id="adj_billDue">Rs. 0</b>
    </div>
    <div class="row g-3">
      <div class="col-6">
        <label class="form-label fw-medium">Adjustment Date</label>
        <input type="date" id="adj_Date" class="form-control" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="col-6">
        <label class="form-label fw-medium">Amount to Adjust <span class="text-danger">*</span></label>
        <div class="input-group"><span class="input-group-text">Rs.</span>
          <input type="number" id="adj_Amount" class="form-control fw-bold" step="0.01" min="0.01" placeholder="0.00">
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
    <button class="btn btn-primary fw-bold" onclick="saveAdj()"><i class="ri-save-3-line me-1"></i>Save Adjustment</button>
  </div>
</div></div>
</div>

<!-- Adjustment History Modal -->
<div class="modal fade" id="adjHistModal" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header bg-info text-white py-2">
    <h5 class="modal-title fs-14"><i class="ri-history-line me-2"></i>Adjustments — <span id="adjHist_label"></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body p-0">
    <table class="table table-bordered table-sm mb-0">
      <thead class="table-light">
        <tr><th>#</th><th>Date</th><th>Bill No.</th><th>Type</th><th>Adjusted</th><th>Remarks</th></tr>
      </thead>
      <tbody id="adjHistBody"></tbody>
      <tfoot>
        <tr class="table-success">
          <td colspan="4" class="text-end fw-bold">Total Adjusted:</td>
          <td class="fw-bold" id="adjHistTotal">Rs. 0</td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <div class="modal-footer py-2"><button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button></div>
</div></div>
</div>

<script>
$(document).ready(function(){
  $('#advTable').DataTable({pageLength:25,order:[[0,'desc']],language:{search:'',searchPlaceholder:'Search...'}});
  $('#adv_Party').select2({theme:'bootstrap-5',placeholder:'-- Select Party --',allowClear:true,dropdownParent:$('#addAdvModal'),width:'100%'});
  $('#adj_Bill').select2({theme:'bootstrap-5',placeholder:'-- Select Bill --',allowClear:true,dropdownParent:$('#adjModal'),width:'100%'});
});

function saveAdvance(){
  var pid=$('#adv_Party').val();
  var amt=parseFloat($('#adv_Amount').val());
  if(!pid){Swal.fire({icon:'warning',title:'Select a party!',toast:true,position:'top-end',timer:2000,showConfirmButton:false});return;}
  if(!amt||amt<=0){Swal.fire({icon:'warning',title:'Enter valid amount!',toast:true,position:'top-end',timer:2000,showConfirmButton:false});return;}
  Swal.fire({title:'Saving...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
  var fd=new FormData();
  fd.append('addAdvance',1); fd.append('PartyId',pid); fd.append('AdvanceDate',$('#adv_Date').val());
  fd.append('Amount',amt); fd.append('PaymentMode',$('#adv_Mode').val());
  fd.append('ReferenceNo',$('#adv_Ref').val()); fd.append('Remarks',$('#adv_Remarks').val());
  fetch('PartyAdvance.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
    Swal.close();
    if(res.status==='success'){
      bootstrap.Modal.getInstance(document.getElementById('addAdvModal')).hide();
      Swal.fire({icon:'success',title:'Advance Saved!',toast:true,position:'top-end',showConfirmButton:false,timer:2500});
      setTimeout(()=>location.reload(),2000);
    } else Swal.fire({icon:'error',title:'Error',text:res.msg});
  }).catch(()=>Swal.fire({icon:'error',title:'Server Error'}));
}

function openAdjModal(advId,partyId,remaining,partyName,advDate){
  $('#adj_AdvId').val(advId); $('#adj_PartyId').val(partyId);
  $('#adj_partyName').text(partyName); $('#adj_advDate').text(advDate);
  $('#adj_rem').text('Rs. '+parseFloat(remaining).toFixed(2));
  $('#adj_Amount').val(parseFloat(remaining).toFixed(2));
  $('#adj_billInfo').hide(); $('#adj_Remarks').val('');
  $('#adj_Date').val('<?= date('Y-m-d') ?>');
  $('#adj_Bill').html('<option value="">Loading...</option>');
  fetch('PartyAdvance.php?getPartyBills=1&PartyId='+partyId)
  .then(r=>r.json()).then(bills=>{
    window._adjBills=bills;
    var opts='<option value="">-- Select Bill --</option>';
    if(!bills.length) opts+='<option disabled>No unpaid bills for this party</option>';
    bills.forEach(function(b){
      opts+='<option value="'+b.id+'" data-type="'+b.billtype+'" data-net="'+b.netamt+'" data-paid="'+b.paid+'" data-rem="'+b.remaining+'">'
           +b.billno+' ('+b.billdate+') — Due: Rs.'+parseFloat(b.remaining).toFixed(0)+'</option>';
    });
    $('#adj_Bill').html(opts).trigger('change');
  });
  new bootstrap.Modal('#adjModal').show();
}

function billSel(){
  var opt=$('#adj_Bill option:selected');
  if($('#adj_Bill').val()){
    var rem=parseFloat(opt.data('rem')||0);
    $('#adj_billNet').text('Rs.'+parseFloat(opt.data('net')||0).toFixed(2));
    $('#adj_billPaid').text('Rs.'+parseFloat(opt.data('paid')||0).toFixed(2));
    $('#adj_billDue').text('Rs.'+rem.toFixed(2));
    $('#adj_billInfo').show();
    var advRem=parseFloat($('#adj_rem').text().replace('Rs. ',''));
    $('#adj_Amount').val(Math.min(advRem,rem).toFixed(2));
  } else { $('#adj_billInfo').hide(); }
}

function saveAdj(){
  var advId=$('#adj_AdvId').val();
  var opt=$('#adj_Bill option:selected');
  var billId=$('#adj_Bill').val();
  var btype=opt.data('type');
  var adjAmt=parseFloat($('#adj_Amount').val());
  var adjDate=$('#adj_Date').val();
  if(!billId){Swal.fire({icon:'warning',title:'Select a bill!',toast:true,position:'top-end',timer:2000,showConfirmButton:false});return;}
  if(!adjAmt||adjAmt<=0){Swal.fire({icon:'warning',title:'Enter valid amount!',toast:true,position:'top-end',timer:2000,showConfirmButton:false});return;}
  var advRem=parseFloat($('#adj_rem').text().replace('Rs. ',''));
  if(adjAmt>advRem){Swal.fire({icon:'warning',title:'Amount exceeds available balance!',text:'Available: Rs.'+advRem.toFixed(2)});return;}
  Swal.fire({title:'Adjusting...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
  var fd=new FormData();
  fd.append('adjustAdvance',1); fd.append('PartyAdvanceId',advId);
  if(btype==='Regular') fd.append('BillId',billId);
  else fd.append('AgentBillId',billId);
  fd.append('BillType',btype); fd.append('AdjustedAmount',adjAmt);
  fd.append('AdjustmentDate',adjDate); fd.append('Remarks',$('#adj_Remarks').val());
  fetch('PartyAdvance.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
    Swal.close();
    if(res.status==='success'){
      bootstrap.Modal.getInstance(document.getElementById('adjModal')).hide();
      Swal.fire({icon:'success',title:'Advance Adjusted!',text:'Remaining: Rs.'+parseFloat(res.newRemaining).toFixed(2),timer:3000,showConfirmButton:false});
      setTimeout(()=>location.reload(),2500);
    } else Swal.fire({icon:'error',title:'Error',text:res.msg});
  }).catch(()=>Swal.fire({icon:'error',title:'Server Error'}));
}

function viewAdj(advId,partyName){
  $('#adjHist_label').text(partyName);
  $('#adjHistBody').html('<tr><td colspan="6" class="text-center py-2"><div class="spinner-border spinner-border-sm"></div></td></tr>');
  new bootstrap.Modal('#adjHistModal').show();
  fetch('PartyAdvance.php?getAdjustments=1&AdvanceId='+advId)
  .then(r=>r.json()).then(rows=>{
    var html='',total=0;
    if(!rows.length){html='<tr><td colspan="6" class="text-center text-muted py-3">No adjustments yet</td></tr>';}
    rows.forEach(function(r,i){
      var bn=r.BillNo||r.AgentBillNo||'—'; total+=parseFloat(r.AdjustedAmount||0);
      html+='<tr><td>'+(i+1)+'</td><td>'+r.AdjustmentDate+'</td><td><b>'+bn+'</b></td>'
        +'<td><span class="badge '+(r.BillType==='Regular'?'bg-primary':'bg-warning text-dark')+'">'+r.BillType+'</span></td>'
        +'<td class="fw-bold text-success">Rs.'+parseFloat(r.AdjustedAmount).toFixed(2)+'</td>'
        +'<td><small class="text-muted">'+(r.Remarks||'—')+'</small></td></tr>';
    });
    $('#adjHistBody').html(html); $('#adjHistTotal').text('Rs.'+total.toFixed(2));
  });
}

window.addEventListener('offline',()=>Swal.fire({icon:'warning',title:'Internet Disconnected!',toast:true,position:'top-end',showConfirmButton:false,timer:3000}));
window.addEventListener('online', ()=>Swal.fire({icon:'success',title:'Back Online!',toast:true,position:'top-end',showConfirmButton:false,timer:2000}));
</script>

<?php require_once "../layout/footer.php"; ?>
