<?php
session_start();
require_once "../../businessLogics/Admin.php";
require_once "../../config/database.php";
Admin::checkAuth();

$billId=intval($_GET['BillId']??0);
if(!$billId)die("Invalid Bill");

$stmt=$pdo->prepare("SELECT b.*,p.PartyName,p.Address,p.City,p.State FROM Bill b LEFT JOIN PartyMaster p ON b.PartyId=p.PartyId WHERE b.BillId=?");
$stmt->execute([$billId]); $bill=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$bill)die("Bill not found");

$trips=$pdo->prepare("
    SELECT t.TripId,t.TripDate,t.InvoiceNo,t.FromLocation,t.ToLocation,
           t.FreightAmount, t.AdvanceAmount,
           (t.FreightAmount - t.AdvanceAmount) AS NetAmount,
           v.VehicleNumber
    FROM BillTrip bt
    JOIN TripMaster t ON bt.TripId=t.TripId
    LEFT JOIN VehicleMaster v ON t.VehicleId=v.VehicleId
    WHERE bt.BillId=? ORDER BY t.TripDate ASC");
$trips->execute([$billId]); $trips=$trips->fetchAll(PDO::FETCH_ASSOC);

function s($v){return htmlspecialchars($v??'',ENT_QUOTES);}
function rs($v){return number_format(floatval($v??0),2);}

function numToWords($n){
  $n=intval($n); if($n==0)return 'Zero';
  $ones=['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
  $tens=['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
  if($n<20)return $ones[$n];
  if($n<100)return $tens[intval($n/10)].($n%10?' '.$ones[$n%10]:'');
  if($n<1000)return $ones[intval($n/100)].' Hundred'.(($n%100)?' And '.numToWords($n%100):'');
  if($n<100000)return numToWords(intval($n/1000)).' Thousand'.(($n%1000)?' '.numToWords($n%1000):'');
  if($n<10000000)return numToWords(intval($n/100000)).' Lakh'.(($n%100000)?' '.numToWords($n%100000):'');
  return numToWords(intval($n/10000000)).' Crore'.(($n%10000000)?' '.numToWords($n%10000000):'');
}

$totalFr  = floatval($bill['TotalFreightAmount']);
$totalAdv = floatval($bill['TotalAdvanceAmount']);
$netTotal = $totalFr - $totalAdv;
$netWords = numToWords(intval($netTotal)).' Only';
$totalRows= max(count($trips),15);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bill <?=s($bill['BillNo'])?> - Shama Roadlines</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:Arial,sans-serif;font-size:10.5px;background:#fff;color:#000;}
.page{width:210mm;margin:0 auto;border:2.5px solid #000;}

.h-wrap{display:flex;align-items:center;padding:6px 10px 4px;border-bottom:1.5px solid #000;gap:10px;}
.truck{font-size:40px;line-height:1;}
.co-name{font-size:26px;font-weight:900;color:#1a237e;font-style:italic;letter-spacing:1px;}
.co-sub{font-size:10px;font-weight:bold;letter-spacing:1.5px;margin-top:2px;}
.co-addr{font-size:9px;color:#333;margin-top:2px;line-height:1.5;}
.pan-bar{text-align:right;padding:2px 10px;font-weight:bold;font-size:10px;border-bottom:1.5px solid #000;}

.info-grid{display:grid;grid-template-columns:1fr 240px;border-bottom:1.5px solid #000;}
.info-left{padding:5px 8px;border-right:1.5px solid #000;}
.info-right{padding:5px 8px;}
.field-row{display:flex;align-items:baseline;gap:6px;margin-bottom:5px;}
.field-row label{font-weight:bold;min-width:60px;white-space:nowrap;}
.field-val{border-bottom:1px solid #666;flex:1;padding:1px 3px;font-weight:bold;}
.bill-no{color:red;font-size:16px;font-weight:900;}

/* Table - same as image: Sr.No | Date | Truck | LR No | From | To | Weight | Rate | TDS | Advance | Amount */
.trips-table{width:100%;border-collapse:collapse;font-size:9.5px;}
.trips-table th,.trips-table td{border:1px solid #000;padding:2px 3px;text-align:center;white-space:nowrap;}
.trips-table th{background:#f0f0f0;font-weight:bold;}
.trips-table .td-left{text-align:left;}
.trips-table .td-right{text-align:right;}
.trips-table .total-row td{background:#e8e8e8;font-weight:bold;}

.bill-footer{display:grid;grid-template-columns:1fr 240px;border-top:1.5px solid #000;}
.bank-area{padding:6px 8px;border-right:1.5px solid #000;font-size:9.5px;line-height:1.8;}
.bank-title{font-weight:bold;font-size:10.5px;}
.totals-area table{width:100%;border-collapse:collapse;}
.totals-area td{border:1px solid #aaa;padding:3px 6px;font-size:10px;}
.totals-area td:last-child{text-align:right;font-weight:bold;}
.net-row td{background:#1a237e;color:#fff!important;font-weight:900!important;border:1.5px solid #000!important;font-size:11px!important;}
.sig-row td{text-align:center!important;font-size:9px;color:#555;height:30px;vertical-align:bottom;padding-bottom:3px;}

.words-row{display:flex;align-items:baseline;gap:6px;padding:4px 8px;border-top:1.5px solid #000;font-size:10px;}
.words-row b{white-space:nowrap;}
.words-row span{border-bottom:1px solid #666;flex:1;padding:1px 4px;font-style:italic;}

.print-bar{text-align:center;padding:10px;background:#f8f9fa;}
.print-bar button{margin:0 5px;padding:8px 22px;border:none;border-radius:5px;cursor:pointer;font-size:14px;font-weight:bold;}
.btn-p{background:#1a237e;color:#fff;} .btn-c{background:#6c757d;color:#fff;}
@media print{.print-bar{display:none!important;}@page{margin:5mm;size:A4 portrait;}}
</style>
</head>
<body>
<div class="print-bar">
  <button class="btn-p" onclick="window.print()">Print Bill</button>
  <button class="btn-c" onclick="window.close()">Close</button>
</div>

<div class="page">

  <!-- HEADER -->
  <div class="h-wrap">
    <div class="truck">&#128665;</div>
    <div>
      <div class="co-name">SHAMA ROADLINES</div>
      <div class="co-sub">TRANSPORT CONTRACTOR &amp; COMMISSION AGENT</div>
      <div class="co-addr">
        Shop No. 16, Gulistan Complex, N. H. No. 8, Narmada Chowkdi, Bharuch-392015<br>
        M: 99740 94467, 7778924467, 9824094467 &nbsp;|&nbsp; Ph: 02642-233830 &nbsp;|&nbsp; E-mail: shamaroadlines30@gmail.com
      </div>
    </div>
  </div>
  <div class="pan-bar">PAN : ANRPM8121A</div>

  <!-- NAME + BILL NO -->
  <div class="info-grid">
    <div class="info-left">
      <div class="field-row"><label>Name :</label><div class="field-val"><?=s($bill['PartyName'])?></div></div>
      <div class="field-row"><label>Address :</label><div class="field-val"><?=s($bill['City']??'')?><?=$bill['State']?' - '.s($bill['State']):''?></div></div>
    </div>
    <div class="info-right">
      <div class="field-row"><label>Bill No.</label><div class="field-val"><span class="bill-no"><?=s($bill['BillNo'])?></span></div></div>
      <div class="field-row"><label>Date :</label><div class="field-val"><?=date('d / m / Y',strtotime($bill['BillDate']))?></div></div>
    </div>
  </div>

  <!-- TRIPS TABLE - exactly as image -->
  <table class="trips-table">
    <thead>
      <tr>
        <th width="28">Sr.<br>No.</th>
        <th width="58">Date</th>
        <th width="70">Truck No.</th>
        <th width="50">L.R. No.</th>
        <th width="62">From</th>
        <th width="62">To</th>
        <th width="50">Weight</th>
        <th width="55">Rate</th>
        <th width="42">TDS</th>
        <th width="55">Advance</th>
        <th width="62">Amount</th>
      </tr>
    </thead>
    <tbody>
<?php
$gFr=0; $gAdv=0; $gNet=0;
for($i=0;$i<$totalRows;$i++):
  $tr=$trips[$i]??null;
  if($tr){
    $lr=str_pad($tr['TripId'],4,'0',STR_PAD_LEFT);
    $fr=floatval($tr['FreightAmount']); $adv=floatval($tr['AdvanceAmount']); $net=$fr-$adv;
    $gFr+=$fr; $gAdv+=$adv; $gNet+=$net;
  }
?>
      <tr style="height:18px">
        <td><?=$tr?($i+1):''?></td>
        <td><?=$tr?date('d-m-Y',strtotime($tr['TripDate'])):''?></td>
        <td><?=$tr?s($tr['VehicleNumber']):''?></td>
        <td><?=$tr?$lr:''?></td>
        <td class="td-left"><?=$tr?s($tr['FromLocation']):''?></td>
        <td class="td-left"><?=$tr?s($tr['ToLocation']):''?></td>
        <td></td>
        <td class="td-right"><?=$tr?rs($tr['FreightAmount']):''?></td>
        <td></td>
        <td class="td-right"><?=$tr&&floatval($tr['AdvanceAmount'])>0?rs($tr['AdvanceAmount']):''?></td>
        <td class="td-right"><?=$tr?rs($net):''?></td>
      </tr>
<?php endfor; ?>
      <tr class="total-row">
        <td colspan="7" style="text-align:right;padding-right:6px;">TOTAL</td>
        <td class="td-right"><?=rs($gFr)?></td>
        <td></td>
        <td class="td-right"><?=$gAdv>0?rs($gAdv):''?></td>
        <td class="td-right"><?=rs($gNet)?></td>
      </tr>
    </tbody>
  </table>

  <!-- FOOTER -->
  <div class="bill-footer">
    <div class="bank-area">
      <div class="bank-title">:: BANK DETAIL ::</div>
      <div>NAME : SHAMA ROADLINES</div>
      <div>A/C NO. : 59251786786786</div>
      <div>IFSC CODE : HDFC0000068</div>
      <div style="margin-top:6px;font-style:italic;font-size:9px;">Subject to Bharuch Jurisdiction</div>
    </div>
    <div class="totals-area">
      <table>
        <tr><td>TOTAL</td><td>Rs. <?=rs($totalFr)?></td></tr>
        <?php if($totalAdv>0): ?>
        <tr><td>(-) Advance</td><td>Rs. <?=rs($totalAdv)?></td></tr>
        <?php endif; ?>
        <tr class="net-row"><td>NET TOTAL</td><td>Rs. <?=rs($netTotal)?></td></tr>
        <tr class="sig-row"><td colspan="2">For, Shama Roadlines</td></tr>
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
