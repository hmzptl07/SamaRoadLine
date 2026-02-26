<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once "../../businessLogics/Admin.php";
require_once "../../config/database.php";
require_once "../../businessLogics/Commission.php";
Admin::checkAuth();

/* ═══════════ AJAX HANDLERS ═══════════ */
if (isset($_POST['saveCommission'])) {
    header('Content-Type: application/json');
    echo json_encode(Commission::save($pdo, intval($_POST['TripId']), floatval($_POST['CommissionAmount']), $_POST['RecoveryFrom'] ?? 'Party'));
    exit();
}
if (isset($_POST['markReceived'])) {
    header('Content-Type: application/json');
    echo json_encode(Commission::markReceived($pdo, json_decode($_POST['commIds'], true), $_POST['ReceivedDate'] ?? date('Y-m-d')));
    exit();
}
if (isset($_GET['getBilledTrips'])) {
    header('Content-Type: application/json');
    echo json_encode(Commission::getAllTripsForEntry($pdo));
    exit();
}

/* ═══════════ PAGE DATA ═══════════ */
$summary     = Commission::getSummary($pdo);
$commissions = Commission::getAll($pdo);

require_once "../layout/header.php";
require_once "../layout/sidebar.php";
?>
<style>
.rec-badge-party { background:#dc3545; }
.rec-badge-owner { background:#6f42c1; }
</style>

<div class="main-content app-content">
<div class="container-fluid">

<!-- PAGE HEADER -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="fw-bold mb-0"><i class="ri-percent-line me-2 text-info"></i>Commission Tracking</h5>
    <p class="text-muted fs-12 mb-0">
      <span class="badge bg-danger me-1">Party</span> Bill payment se auto-recover &nbsp;|&nbsp;
      <span class="badge me-1" style="background:#6f42c1">Owner</span> Owner se manually recover karo
    </p>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-primary btn-sm" onclick="openSetModal()">
      <i class="ri-edit-box-line me-1"></i>Set / Update Commission
    </button>
    <button class="btn btn-success btn-sm fw-bold" id="markRecBtn" onclick="openMarkModal()" style="display:none">
      <i class="ri-check-double-line me-1"></i>Mark Selected Received
    </button>
  </div>
</div>

<!-- SUMMARY CARDS -->
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center">
      <div class="card-body py-2">
        <div class="fs-11 text-muted">Total Commission</div>
        <div class="fw-bold fs-16 text-primary">Rs.<?= number_format($summary['total_amount'] ?? 0, 0) ?></div>
        <div class="fs-11 text-muted"><?= $summary['total'] ?> entries</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center">
      <div class="card-body py-2">
        <div class="fs-11 text-muted"><span class="badge bg-danger">Party</span> Pending</div>
        <div class="fw-bold fs-16 text-danger">Rs.<?= number_format($summary['party_pending'] ?? 0, 0) ?></div>
        <div class="fs-11 text-muted">Auto on Bill Paid</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center">
      <div class="card-body py-2">
        <div class="fs-11 text-muted"><span class="badge" style="background:#6f42c1">Owner</span> Pending</div>
        <div class="fw-bold fs-16" style="color:#6f42c1">Rs.<?= number_format($summary['owner_pending'] ?? 0, 0) ?></div>
        <div class="fs-11 text-danger fw-bold">Manual Recovery</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center">
      <div class="card-body py-2">
        <div class="fs-11 text-muted">Received</div>
        <div class="fw-bold fs-16 text-success">Rs.<?= number_format($summary['received'] ?? 0, 0) ?></div>
        <div class="fs-11 text-muted"><?= $summary['received_count'] ?> trips</div>
      </div>
    </div>
  </div>
</div>

<!-- FILTER PILLS -->
<div class="d-flex gap-2 mb-2 flex-wrap align-items-center">
  <button class="btn btn-sm btn-secondary" onclick="filterComm('')">All</button>
  <button class="btn btn-sm btn-outline-danger" onclick="filterComm('Pending')">Pending</button>
  <button class="btn btn-sm btn-outline-success" onclick="filterComm('Received')">Received</button>
  <div class="border-start ps-2 ms-1 d-flex gap-2">
    <button class="btn btn-sm btn-outline-danger" onclick="filterRecFrom('Party')"><span class="badge bg-danger">Party</span> Pending</button>
    <button class="btn btn-sm btn-outline-secondary" onclick="filterRecFrom('Owner')" style="border-color:#6f42c1;color:#6f42c1">
      <span class="badge" style="background:#6f42c1">Owner</span> Recovery
    </button>
  </div>
  <span class="ms-auto fs-12 text-muted" id="selInfo"></span>
</div>

<!-- TABLE -->
<div class="card custom-card shadow-sm">
<div class="card-body p-0">
<div class="table-responsive">
<table id="commTable" class="table table-bordered table-hover mb-0 fs-13">
  <thead class="table-light">
    <tr>
      <th width="36"><input type="checkbox" id="chkAll" onchange="toggleAll(this)"></th>
      <th>#</th><th>Trip Date</th><th>Vehicle</th><th>Route</th><th>Party / Agent</th>
      <th>Bill No.</th><th>Bill Status</th><th>Freight</th><th>Commission</th>
      <th>Recover From</th><th>Status</th><th>Received On</th><th>Edit</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($commissions as $i => $c):
    $isPend  = ($c['CommissionStatus'] === 'Pending');
    $billno  = $c['BillNo'] ?? $c['AgentBillNo'] ?? '—';
    $bstat   = ($c['TripType'] === 'Agent') ? ($c['AgentBillStatus'] ?? '—') : ($c['RegBillStatus'] ?? '—');
    $bsc     = ['Generated'=>'bg-secondary','PartiallyPaid'=>'bg-warning text-dark','Paid'=>'bg-success'];
    $bss     = $bsc[$bstat] ?? 'bg-secondary';
    $party   = $c['TripType'] === 'Agent' ? htmlspecialchars($c['AgentName'] ?? '—') : htmlspecialchars($c['ConsignerName'] ?? '—');
    $rfColor = $c['RecoveryFrom'] === 'Owner' ? '#6f42c1' : '#dc3545';
    $rfLabel = $c['RecoveryFrom'] === 'Owner' ? '🏠 Owner' : '🏢 Party';
  ?>
    <tr class="comm-row" data-status="<?= $c['CommissionStatus'] ?>" data-recfrom="<?= $c['RecoveryFrom'] ?>">
      <td class="text-center">
        <?php if ($isPend): ?>
        <input type="checkbox" class="comm-chk" data-id="<?= $c['TripCommissionId'] ?>">
        <?php endif; ?>
      </td>
      <td class="text-muted"><?= $i + 1 ?></td>
      <td style="white-space:nowrap"><?= date('d-m-Y', strtotime($c['TripDate'])) ?></td>
      <td><span class="badge bg-secondary"><?= htmlspecialchars($c['VehicleNumber'] ?? '—') ?></span></td>
      <td style="white-space:nowrap;font-size:11px"><?= htmlspecialchars($c['FromLocation']) ?> → <?= htmlspecialchars($c['ToLocation']) ?></td>
      <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= $party ?>"><?= $party ?></td>
      <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($billno) ?></span></td>
      <td><span class="badge <?= $bss ?>"><?= $bstat ?></span></td>
      <td class="fw-bold text-end">Rs.<?= number_format($c['FreightAmount'], 0) ?></td>
      <td class="fw-bold text-end text-info">Rs.<?= number_format($c['CommissionAmount'], 2) ?></td>
      <td><span class="badge fw-normal" style="background:<?= $rfColor ?>"><?= $rfLabel ?></span></td>
      <td>
        <?php if (!$isPend): ?>
          <span class="badge bg-success"><i class="ri-check-line me-1"></i>Received</span>
        <?php else: ?>
          <span class="badge bg-danger"><i class="ri-time-line me-1"></i>Pending</span>
        <?php endif; ?>
      </td>
      <td style="white-space:nowrap"><?= $c['ReceivedDate'] ? date('d-m-Y', strtotime($c['ReceivedDate'])) : '—' ?></td>
      <td>
        <button class="btn btn-sm btn-outline-warning" title="Edit"
          onclick="openEdit(<?= $c['TripCommissionId'] ?>,<?= $c['TripId'] ?>,<?= $c['CommissionAmount'] ?>,'<?= $c['RecoveryFrom'] ?>')">
          <i class="ri-edit-line"></i>
        </button>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div></div></div>

</div></div>

<!-- ════ MODALS ════ -->

<!-- Set Commission Modal -->
<div class="modal fade" id="setCommModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header bg-info text-white py-2">
    <h5 class="modal-title fs-14"><i class="ri-percent-line me-2"></i>Set / Update Commission — All Trips</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body p-0">
    <div id="setCommLoading" class="text-center py-4">
      <div class="spinner-border text-info"></div><div class="mt-2 text-muted">Loading trips...</div>
    </div>
    <table class="table table-bordered table-sm mb-0 fs-12" id="setCommTable" style="display:none">
      <thead class="table-dark">
        <tr><th>#</th><th>Date</th><th>Vehicle</th><th>Route</th><th>Type</th><th>Bill</th>
          <th>Freight</th><th style="min-width:120px">Commission Rs.</th><th>Recover From</th><th>Status</th><th>Save</th></tr>
      </thead>
      <tbody id="setCommBody"></tbody>
    </table>
  </div>
  <div class="modal-footer py-2">
    <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
  </div>
</div></div>
</div>

<!-- Edit Commission Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
  <div class="modal-header bg-warning py-2">
    <h5 class="modal-title fs-14 fw-bold"><i class="ri-edit-line me-2"></i>Edit Commission</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body">
    <input type="hidden" id="ec_commId"><input type="hidden" id="ec_tripId">
    <div class="mb-3">
      <label class="form-label fw-medium">Commission Amount (Rs.)</label>
      <div class="input-group"><span class="input-group-text">Rs.</span>
        <input type="number" id="ec_amount" class="form-control fw-bold" step="0.01" min="0">
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label fw-medium">Recover Commission From <span class="text-danger">*</span></label>
      <select id="ec_recFrom" class="form-select">
        <option value="Party">🏢 Party (Bill payment pe auto-recover)</option>
        <option value="Owner">🏠 Owner (Manual recovery needed)</option>
      </select>
      <div class="form-text text-warning"><i class="ri-alert-line"></i> Owner = freight already paid to owner directly</div>
    </div>
  </div>
  <div class="modal-footer py-2">
    <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
    <button class="btn btn-warning fw-bold" onclick="saveEdit()"><i class="ri-save-3-line me-1"></i>Update</button>
  </div>
</div></div>
</div>

<!-- Mark Received Modal -->
<div class="modal fade" id="markRecModal" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
  <div class="modal-header bg-success text-white py-2">
    <h5 class="modal-title fs-14"><i class="ri-check-double-line me-2"></i>Mark Commission as Received</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body">
    <div class="alert alert-success"><strong id="mr_count">0</strong> commission(s) will be marked as Received.</div>
    <label class="form-label fw-medium">Date Received</label>
    <input type="date" id="mr_date" class="form-control" value="<?= date('Y-m-d') ?>">
  </div>
  <div class="modal-footer py-2">
    <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
    <button class="btn btn-success fw-bold" onclick="confirmMarkRec()"><i class="ri-check-line me-1"></i>Confirm</button>
  </div>
</div></div>
</div>

<script>
var commDT;
$(document).ready(function(){
  commDT = $('#commTable').DataTable({
    pageLength:25, order:[[11,'asc'],[0,'desc']],
    columnDefs:[{orderable:false,targets:[0,13]}],
    language:{search:'',searchPlaceholder:'Search commissions...'}
  });
  updateSelInfo();
});

function filterComm(s){ commDT.column(11).search(s).draw(); }
function filterRecFrom(s){ commDT.column(10).search(s).draw(); }

function toggleAll(cb){ $('.comm-chk:visible').prop('checked',cb.checked); updateSelInfo(); }
$(document).on('change','.comm-chk',updateSelInfo);
function updateSelInfo(){
  var n=$('.comm-chk:checked').length;
  $('#selInfo').text(n?n+' selected':'');
  $('#markRecBtn').toggle(n>0);
}

function openMarkModal(){
  var ids=[]; $('.comm-chk:checked').each(function(){ids.push($(this).data('id'));});
  if(!ids.length)return;
  window._pendIds=ids; $('#mr_count').text(ids.length);
  new bootstrap.Modal('#markRecModal').show();
}

function confirmMarkRec(){
  var ids=window._pendIds||[]; var date=$('#mr_date').val();
  if(!ids.length||!date)return;
  Swal.fire({title:'Saving...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
  var fd=new FormData();
  fd.append('markReceived',1); fd.append('commIds',JSON.stringify(ids)); fd.append('ReceivedDate',date);
  fetch('CommissionTrack.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
    Swal.close();
    if(res.status==='success'){
      bootstrap.Modal.getInstance(document.getElementById('markRecModal')).hide();
      Swal.fire({icon:'success',title:res.count+' Commissions Received!',toast:true,position:'top-end',showConfirmButton:false,timer:2500});
      setTimeout(()=>location.reload(),2000);
    } else Swal.fire({icon:'error',title:'Error',text:res.msg});
  });
}

function openEdit(commId,tripId,amt,recFrom){
  $('#ec_commId').val(commId); $('#ec_tripId').val(tripId);
  $('#ec_amount').val(parseFloat(amt).toFixed(2));
  $('#ec_recFrom').val(recFrom);
  new bootstrap.Modal('#editModal').show();
}

function saveEdit(){
  var fd=new FormData();
  fd.append('saveCommission',1);
  fd.append('TripId',$('#ec_tripId').val());
  fd.append('CommissionAmount',$('#ec_amount').val());
  fd.append('RecoveryFrom',$('#ec_recFrom').val());
  Swal.fire({title:'Saving...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
  fetch('CommissionTrack.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
    Swal.close();
    if(res.status==='success'){
      bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
      Swal.fire({icon:'success',title:'Commission Updated!',toast:true,position:'top-end',showConfirmButton:false,timer:2000});
      setTimeout(()=>location.reload(),1500);
    } else Swal.fire({icon:'error',title:'Error',text:res.msg});
  });
}

function openSetModal(){
  $('#setCommLoading').show(); $('#setCommTable').hide();
  new bootstrap.Modal('#setCommModal').show();
  fetch('CommissionTrack.php?getBilledTrips=1')
  .then(r=>r.json()).then(rows=>{
    $('#setCommLoading').hide();
    var html='';
    var bsc={'Generated':'bg-secondary','PartiallyPaid':'bg-warning text-dark','Paid':'bg-success'};
    var csc={'Pending':'bg-danger','Received':'bg-success'};
    rows.forEach(function(r,i){
      var billno = r.BillNo||r.AgentBillNo||'—';
      var bstat  = r.TripType==='Agent'?(r.AgentBillStatus||'—'):(r.RegBillStatus||'—');
      var bBadge = '<span class="badge '+(bsc[bstat]||'bg-secondary')+'">'+bstat+'</span>';
      var cBadge = r.TripCommissionId ?
        '<span class="badge '+(csc[r.CommissionStatus]||'bg-secondary')+'">'+r.CommissionStatus+'</span>' :
        '<span class="badge bg-light text-dark border">New</span>';
      var tBadge = '<span class="badge '+(r.TripType==='Agent'?'bg-warning text-dark':'bg-primary')+'">'+r.TripType+'</span>';
      var curRec = r.RecoveryFrom||'Party';
      html+='<tr><td>'+(i+1)+'</td>'
        +'<td style="white-space:nowrap">'+r.TripDate+'</td>'
        +'<td><span class="badge bg-secondary">'+(r.VehicleNumber||'—')+'</span></td>'
        +'<td style="white-space:nowrap;font-size:11px">'+r.FromLocation+' → '+r.ToLocation+'</td>'
        +'<td>'+tBadge+' '+cBadge+'</td>'
        +'<td><small>'+billno+'</small> '+bBadge+'</td>'
        +'<td class="fw-bold text-end">Rs.'+parseFloat(r.FreightAmount||0).toFixed(0)+'</td>'
        +'<td><div class="input-group input-group-sm"><span class="input-group-text">Rs.</span>'
          +'<input type="number" class="form-control comm-inp" data-tid="'+r.TripId+'" step="0.01" min="0" value="'+parseFloat(r.CommissionAmount||0).toFixed(2)+'"></div></td>'
        +'<td><select class="form-select form-select-sm rec-sel" data-tid="'+r.TripId+'" style="min-width:100px">'
          +'<option value="Party"'+(curRec==='Party'?' selected':'')+'>🏢 Party</option>'
          +'<option value="Owner"'+(curRec==='Owner'?' selected':'')+'>🏠 Owner</option>'
        +'</select></td>'
        +'<td>'+cBadge+'</td>'
        +'<td><button class="btn btn-sm btn-info" onclick="saveRowComm('+r.TripId+',this)"><i class="ri-save-3-line"></i></button></td>'
        +'</tr>';
    });
    $('#setCommBody').html(html||'<tr><td colspan="11" class="text-center py-3 text-muted">No trips found</td></tr>');
    $('#setCommTable').show();
  }).catch(function(){ $('#setCommLoading').html('<div class="text-danger text-center py-3">Failed to load</div>'); });
}

function saveRowComm(tripId, btn){
  var row=$(btn).closest('tr');
  var amt  = row.find('.comm-inp').val();
  var recF = row.find('.rec-sel').val();
  var fd=new FormData();
  fd.append('saveCommission',1); fd.append('TripId',tripId);
  fd.append('CommissionAmount',amt); fd.append('RecoveryFrom',recF);
  $(btn).html('<i class="ri-loader-line"></i>').prop('disabled',true);
  fetch('CommissionTrack.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
    if(res.status==='success'){
      $(btn).html('<i class="ri-check-line"></i>').removeClass('btn-info').addClass('btn-success').prop('disabled',false);
      setTimeout(()=>$(btn).html('<i class="ri-save-3-line"></i>').removeClass('btn-success').addClass('btn-info'),2500);
    } else { $(btn).html('<i class="ri-close-line"></i>').addClass('btn-danger').prop('disabled',false); }
  });
}

window.addEventListener('offline',()=>Swal.fire({icon:'warning',title:'Internet Disconnected!',toast:true,position:'top-end',showConfirmButton:false,timer:3000}));
window.addEventListener('online', ()=>Swal.fire({icon:'success',title:'Back Online!',toast:true,position:'top-end',showConfirmButton:false,timer:2000}));
</script>

<?php require_once "../layout/footer.php"; ?>
