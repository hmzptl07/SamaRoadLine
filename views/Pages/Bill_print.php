<?php
session_start();
require_once "../../businessLogics/Admin.php";
require_once "../../config/database.php";
Admin::checkAuth();

// Called from a party's page - pass PartyId or show all trips for a period
$partyId  = intval($_GET['PartyId'] ?? 0);
$billNo   = $_GET['BillNo'] ?? '';
$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate   = $_GET['to']   ?? date('Y-m-d');

// Get party info
$party = null;
if($partyId > 0){
  $stmt = $pdo->prepare("SELECT * FROM PartyMaster WHERE PartyId=?");
  $stmt->execute([$partyId]); $party = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get trips for this party in date range
$sql = "SELECT t.*, v.VehicleNumber
        FROM TripMaster t
        LEFT JOIN VehicleMaster v ON t.VehicleId=v.VehicleId
        WHERE t.TripDate BETWEEN ? AND ?";
$params = [$fromDate, $toDate];
if($partyId > 0){
  $sql .= " AND (t.ConsignerId=? OR t.ConsigneeId=?)";
  $params[] = $partyId; $params[] = $partyId;
}
$sql .= " ORDER BY t.TripDate ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

function s($v){ return htmlspecialchars($v??'',ENT_QUOTES); }
function m($v){ return number_format(floatval($v??0),2); }

// Calculate totals
$grandTotal = array_sum(array_column($trips,'FreightAmount'));
$grandNet   = array_sum(array_column($trips,'NetAmount'));
$grandAdv   = array_sum(array_column($trips,'AdvanceAmount'));
$grandTDS   = array_sum(array_column($trips,'TDS'));

// Number to words (simple)
function numToWords($n){
  $n = intval($n);
  $ones=['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
  $tens=['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
  if($n<20) return $ones[$n];
  if($n<100) return $tens[intval($n/10)].($n%10?' '.$ones[$n%10]:'');
  if($n<1000) return $ones[intval($n/100)].' Hundred'.(($n%100)?' '.numToWords($n%100):'');
  if($n<100000) return numToWords(intval($n/1000)).' Thousand'.(($n%1000)?' '.numToWords($n%1000):'');
  if($n<10000000) return numToWords(intval($n/100000)).' Lakh'.(($n%100000)?' '.numToWords($n%100000):'');
  return numToWords(intval($n/10000000)).' Crore'.(($n%10000000)?' '.numToWords($n%10000000):'');
}
$netWords = numToWords(intval($grandNet)).' Only';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bill - <?=s($billNo)?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:Arial,sans-serif;font-size:11px;background:#fff;color:#000;}
.page{width:210mm;margin:0 auto;border:2.5px solid #000;}

/* Header */
.h-top{display:flex;align-items:center;padding:6px 10px;border-bottom:1.5px solid #000;gap:10px;}
.co-name{font-size:28px;font-weight:900;color:#1a237e;font-style:italic;letter-spacing:1px;line-height:1;}
.co-sub{font-size:10px;font-weight:bold;letter-spacing:1px;margin-top:1px;}
.co-contact{font-size:9px;margin-top:2px;color:#333;}
.pan-row{text-align:right;padding:2px 10px 0;font-size:10px;font-weight:bold;border-bottom:1.5px solid #000;}

/* Name/Address/Bill rows */
.info-grid{display:grid;grid-template-columns:1fr 1fr;border-bottom:1.5px solid #000;}
.info-left{padding:5px 8px;border-right:1.5px solid #000;}
.info-right{padding:5px 8px;}
.info-field{display:flex;align-items:baseline;gap:5px;margin-bottom:4px;}
.info-field label{font-weight:bold;min-width:55px;font-size:10.5px;}
.info-field .val{border-bottom:1px solid #888;flex:1;padding:1px 2px;font-size:10.5px;font-weight:bold;}
.bill-no{color:red;font-size:14px;font-weight:900;}

/* Table */
.bill-table{width:100%;border-collapse:collapse;font-size:10px;}
.bill-table th,.bill-table td{border:1px solid #000;padding:2px 3px;text-align:center;}
.bill-table th{background:#f0f0f0;font-weight:bold;}
.bill-table .td-left{text-align:left;}
.bill-table tr.total-row{background:#f0f0f0;font-weight:bold;}

/* Footer */
.bill-footer{display:grid;grid-template-columns:1fr auto;border-top:1.5px solid #000;}
.bank-col{padding:6px 8px;border-right:1.5px solid #000;font-size:9.5px;}
.bank-col .bank-title{font-weight:bold;font-size:11px;margin-bottom:3px;}
.totals-col{padding:4px 6px;min-width:180px;}
.totals-col table{width:100%;border-collapse:collapse;}
.totals-col td{border:1px solid #999;padding:2px 5px;font-size:10px;}
.totals-col td:last-child{text-align:right;font-weight:bold;}
.net-total-row td{background:#1a237e;color:#fff!important;font-weight:900!important;border:1.5px solid #000!important;}
.words-row{padding:4px 8px;border-top:1.5px solid #000;font-size:10px;display:flex;align-items:baseline;gap:5px;}
.words-row b{min-width:90px;}
.words-row span{border-bottom:1px solid #888;flex:1;padding:1px 4px;font-style:italic;}

.print-bar{text-align:center;padding:10px;background:#f8f9fa;}
.print-bar button{margin:0 5px;padding:8px 22px;border:none;border-radius:5px;cursor:pointer;font-size:14px;font-weight:bold;}
.btn-p{background:#1a237e;color:#fff;}
.btn-c{background:#6c757d;color:#fff;}
@media print{
  .print-bar{display:none!important;}
  @page{margin:5mm;size:A4 portrait;}
}
</style>
</head>
<body>
<div class="print-bar">
  <button class="btn-p" onclick="window.print()">🖨️ Print Bill</button>
  <button class="btn-c" onclick="window.close()">✖ Close</button>
</div>

<div class="page">

  <!-- HEADER -->
  <div class="h-top">
    <div style="font-size:38px;">🚛</div>
    <div>
      <div class="co-name">SHAMA ROADLINES</div>
      <div class="co-sub">TRANSPORT CONTRACTOR &amp; COMMISSION AGENT</div>
      <div class="co-contact">
        Shop No. 16, Gulistan Complex, N. H. No. 8, Narmada Chowkdi, Bharuch-392015<br>
        M: 99740 94467, 7778924467, 9824094467, Ph: 02642-233830 &nbsp;|&nbsp; E-mail: shamaroadlines30@gmail.com
      </div>
    </div>
  </div>
  <div class="pan-row">PAN : ANRPM8121A</div>

  <!-- NAME / BILL NO -->
  <div class="info-grid">
    <div class="info-left">
      <div class="info-field">
        <label>Name :</label>
        <div class="val"><?=s($party['PartyName']??'')?></div>
      </div>
      <div class="info-field">
        <label>Address :</label>
        <div class="val"><?=s($party['City']??'')?><?=$party['State']?' - '.s($party['State']):''?></div>
      </div>
    </div>
    <div class="info-right">
      <div class="info-field">
        <label>Bill No.</label>
        <div class="val"><span class="bill-no"><?=s($billNo)?></span></div>
      </div>
      <div class="info-field">
        <label>Date :</label>
        <div class="val"><?=date('d / m / Y')?></div>
      </div>
    </div>
  </div>

  <!-- TRIPS TABLE -->
  <table class="bill-table">
    <thead>
      <tr>
        <th width="30">Sr.<br>No.</th>
        <th width="58">Date</th>
        <th width="70">Truck No.</th>
        <th width="58">L.R. No.</th>
        <th width="60">From</th>
        <th width="60">To</th>
        <th width="45">Weight</th>
        <th width="45">Rate</th>
        <th width="40">TDS</th>
        <th width="55">Advance</th>
        <th width="60">Amount</th>
      </tr>
    </thead>
    <tbody>
<?php foreach($trips as $i=>$tr): ?>
      <tr>
        <td><?=$i+1?></td>
        <td><?=date('d-m-Y',strtotime($tr['TripDate']))?></td>
        <td><?=s($tr['VehicleNumber'])?></td>
        <td><?=s($tr['InvoiceNo'])?></td>
        <td class="td-left"><?=s($tr['FromLocation'])?></td>
        <td class="td-left"><?=s($tr['ToLocation'])?></td>
        <td><?=$tr['MaterialTotalValue']?m($tr['MaterialTotalValue']):''?></td>
        <td><?=m($tr['FreightAmount'])?></td>
        <td><?=$tr['TDS']>0?m($tr['TDS']):''?></td>
        <td><?=$tr['AdvanceAmount']>0?m($tr['AdvanceAmount']):''?></td>
        <td><?=m($tr['NetAmount']??$tr['FreightAmount'])?></td>
      </tr>
<?php endforeach; ?>
<?php for($x=count($trips);$x<15;$x++): ?>
      <tr style="height:18px"><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
<?php endfor; ?>
      <tr class="total-row">
        <td colspan="7" style="text-align:right;">TOTAL</td>
        <td><?=m($grandTotal)?></td>
        <td><?=$grandTDS>0?m($grandTDS):''?></td>
        <td><?=$grandAdv>0?m($grandAdv):''?></td>
        <td><?=m($grandNet)?></td>
      </tr>
    </tbody>
  </table>

  <!-- FOOTER: BANK + TOTALS -->
  <div class="bill-footer">
    <div class="bank-col">
      <div class="bank-title">:: BANK DETAIL ::</div>
      <div><b>Name :</b> SHAMA ROADLINES</div>
      <div><b>A/C No. :</b> 59251786786786</div>
      <div><b>IFSC Code :</b> HDFC0000068</div>
      <div style="margin-top:8px;font-style:italic;font-size:9px;">Subject to Bharuch Jurisdiction</div>
    </div>
    <div class="totals-col">
      <table>
        <tr><td>Total Freight</td><td>Rs.<?=m($grandTotal)?></td></tr>
        <?php if($grandAdv>0): ?><tr><td>(-) Advance</td><td>Rs.<?=m($grandAdv)?></td></tr><?php endif; ?>
        <?php if($grandTDS>0): ?><tr><td>(-) TDS</td><td>Rs.<?=m($grandTDS)?></td></tr><?php endif; ?>
        <tr class="net-total-row"><td>NET TOTAL</td><td>Rs.<?=m($grandNet)?></td></tr>
        <tr><td colspan="2" style="text-align:center;font-size:9px;padding-top:4px;">For, Shama Roadlines</td></tr>
        <tr><td colspan="2" style="height:30px;text-align:center;font-size:9px;color:#999;">Authorized Signature</td></tr>
      </table>
    </div>
  </div>

  <!-- RS IN WORDS -->
  <div class="words-row">
    <b>Rs. In Word :</b>
    <span><?=s($netWords)?></span>
  </div>

</div>
</body>
</html>
