<?php
session_start();
require_once "../../businessLogics/Admin.php";
require_once "../../config/database.php";
Admin::checkAuth();

$billId = intval($_GET['BillId'] ?? 0);
if (!$billId) die("Invalid Bill");

$stmt = $pdo->prepare("SELECT b.*,p.PartyName,p.Address,p.City,p.State FROM Bill b LEFT JOIN PartyMaster p ON b.PartyId=p.PartyId WHERE b.BillId=?");
$stmt->execute([$billId]);
$bill = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$bill) die("Bill not found");

$stmtT = $pdo->prepare("
    SELECT t.TripId,t.TripDate,t.FromLocation,t.ToLocation,
           t.FreightAmount,t.CashAdvance,t.OnlineAdvance,t.AdvanceAmount,t.TDS,
           t.LabourCharge,t.HoldingCharge,t.OtherCharge,
           v.VehicleNumber,
           COALESCE((SELECT SUM(m.Weight) FROM TripMaterial m WHERE m.TripId=t.TripId),0) AS TotalWeight,
           COALESCE((SELECT AVG(m.Rate)   FROM TripMaterial m WHERE m.TripId=t.TripId),0) AS AverageRate
    FROM BillTrip bt
    JOIN TripMaster t ON bt.TripId=t.TripId
    LEFT JOIN VehicleMaster v ON t.VehicleId=v.VehicleId
    WHERE bt.BillId=? ORDER BY t.TripDate ASC");
$stmtT->execute([$billId]);
$trips = $stmtT->fetchAll(PDO::FETCH_ASSOC);

function s($v){return htmlspecialchars($v??'',ENT_QUOTES);}
function rs($v){return number_format(floatval($v??0),2);}
function numToWords($n){
  $n=intval($n); if($n==0)return 'Zero';
  $o=['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
  $t=['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
  if($n<20)return $o[$n];
  if($n<100)return $t[intval($n/10)].($n%10?' '.$o[$n%10]:'');
  if($n<1000)return $o[intval($n/100)].' Hundred'.(($n%100)?' And '.numToWords($n%100):'');
  if($n<100000)return numToWords(intval($n/1000)).' Thousand'.(($n%1000)?' '.numToWords($n%1000):'');
  if($n<10000000)return numToWords(intval($n/100000)).' Lakh'.(($n%100000)?' '.numToWords($n%100000):'');
  return numToWords(intval($n/10000000)).' Crore'.(($n%10000000)?' '.numToWords($n%10000000):'');
}

// Pre-calc totals
$gFr=$gLab=$gHold=$gOth=$gother=0;
$gCash=$gOnline=$gTds=$gNet=$gWt=0;
$rows = [];
foreach($trips as $tr){
  $fr=floatval($tr['FreightAmount']); $lab=floatval($tr['LabourCharge']);
  $hold=floatval($tr['HoldingCharge']); $oth=floatval($tr['OtherCharge']);
  $cash=floatval($tr['CashAdvance']); $onl=floatval($tr['OnlineAdvance']);
  $adv=floatval($tr['AdvanceAmount']); $tds=floatval($tr['TDS']);
  $wt=floatval($tr['TotalWeight']); $rate=floatval($tr['AverageRate']);
  $otherC=$lab+$hold+$oth; $tot=$fr+$otherC; $net=$tot-$adv-$tds;
  $gFr+=$fr; $gother+=$otherC; $gLab+=$lab; $gHold+=$hold; $gOth+=$oth;
  $gCash+=$cash; $gOnline+=$onl; $gTds+=$tds; $gNet+=$net; $gWt+=$wt;
  $rows[]=compact('tr','fr','lab','hold','oth','cash','onl','adv','tds','wt','rate','otherC','tot','net');
}
$totalRows = max(count($rows), 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bill <?= s($bill['BillNo']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --navy:#0b1d3a; --gold:#d4a017; --gold2:#f0b429;
  --red:#b03020; --cream:#faf8f2; --stripe:#f5f3ea;
  --bdr:#c9a227; --grey:#5c5c5c; --dk:#1a1a1a;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{
  font-family:'Inter',Arial,sans-serif;
  background:#9e9a90;
  -webkit-print-color-adjust:exact;
  print-color-adjust:exact;
}

/* ── PRINT BUTTON BAR ── */
.no-print{display:flex;justify-content:center;gap:10px;padding:8px;background:var(--navy);}
.no-print button{padding:7px 26px;border:none;border-radius:4px;cursor:pointer;font-size:12px;font-weight:700;}
.btn-p{background:var(--gold2);color:var(--navy);}
.btn-c{background:#556;color:#fff;}

/* ══════════════════════════════════════
   PAGE — A4 landscape 297×210mm
   padding 10mm all sides → content 277×190mm
══════════════════════════════════════ */
.page{
  width:297mm;
  height:210mm;          /* HARD height — no overflow */
  margin:10px auto;
  padding:10mm;
  background:#fff;
  box-shadow:0 6px 30px rgba(0,0,0,.4);
  display:flex;
  flex-direction:column;
  overflow:hidden;
}

/* inner border box — fills 277×190mm exactly */
.wrap{
  width:100%;height:100%;
  border:2px solid var(--navy);
  display:flex;flex-direction:column;
  overflow:hidden;
}

/* ── accent lines ── */
.accent{height:4px;flex-shrink:0;
  background:linear-gradient(90deg,var(--navy),var(--gold2) 40%,var(--gold2) 60%,var(--navy));}

/* ── HEADER ── */
.hdr{
  background:linear-gradient(135deg,var(--navy),#122248);
  text-align:center;padding:5px 16px 4px;flex-shrink:0;
}
.co-name{
  font-family:'Playfair Display',serif;font-size:22px;font-weight:900;
  color:var(--gold2);letter-spacing:5px;line-height:1;
}
.co-div{height:1px;width:260px;margin:3px auto;
  background:linear-gradient(90deg,transparent,var(--gold2),transparent);}
.co-tag{font-size:6.5px;font-weight:600;letter-spacing:3.5px;
  color:rgba(255,255,255,.6);text-transform:uppercase;}

/* ── ADDRESS ── */
.addr{
  background:var(--cream);
  border-top:1.5px solid var(--bdr);border-bottom:1.5px solid var(--bdr);
  text-align:center;padding:3px 12px;flex-shrink:0;line-height:1.6;
}
.addr-main{font-size:9.5px;font-weight:700;color:var(--navy);}
.addr-contact{font-size:8.5px;font-weight:600;color:#222;}
.addr-contact .lbl{color:var(--gold);font-weight:800;}
.sep{color:#bbb;margin:0 5px;}

/* ── PAN ── */
.pan{
  display:flex;justify-content:flex-end;align-items:center;
  padding:2px 10px;border-bottom:2px solid var(--navy);flex-shrink:0;
  font-size:7.5px;font-weight:700;color:var(--grey);gap:6px;
}
.pan-pill{background:var(--navy);color:var(--gold2);
  padding:1px 9px;border-radius:3px;font-size:8px;letter-spacing:2px;font-weight:800;}

/* ── PARTY / BILL INFO ── */
.info{display:grid;grid-template-columns:1fr 240px;
  border-bottom:2px solid var(--navy);flex-shrink:0;}
.info-l{padding:4px 10px;border-right:1.5px solid var(--navy);}
.info-r{padding:4px 10px;background:var(--stripe);}
.frow{display:flex;align-items:baseline;gap:5px;margin-bottom:3px;}
.frow:last-child{margin-bottom:0;}
.flbl{font-size:7px;font-weight:700;color:var(--grey);
  text-transform:uppercase;letter-spacing:1px;min-width:48px;white-space:nowrap;}
.fval{flex:1;border-bottom:1px solid var(--bdr);
  padding:0 4px 1px;font-size:11px;font-weight:700;color:var(--navy);}
.billno{font-size:13px;font-weight:800;color:var(--navy);}

/* ── SECTION LABEL ── */
.sec{background:var(--navy);color:var(--gold2);
  font-size:7px;font-weight:800;letter-spacing:3px;text-transform:uppercase;
  padding:3px 10px;border-bottom:1px solid var(--gold);flex-shrink:0;}

/* ── TABLE ── */
.tbl{width:100%;border-collapse:collapse;font-size:9.5px;flex:1;}
.tbl thead tr{background:var(--navy);}
.tbl th{
  color:var(--gold2);font-weight:700;font-size:7.5px;
  letter-spacing:.5px;text-transform:uppercase;
  padding:4px 3px;text-align:center;white-space:nowrap;
  border:1px solid rgba(255,255,255,.08);
}
.tbl td{border:1px solid #ddd;padding:0 4px;text-align:center;
  white-space:nowrap;color:var(--dk);}
.tbl tbody tr:nth-child(even){background:var(--stripe);}
.tbl tbody tr.dr{height:20px;}
.tl{text-align:left!important;padding-left:5px!important;}
.tr{text-align:right!important;padding-right:4px!important;font-variant-numeric:tabular-nums;}
.tb{font-weight:700;}
.tn{font-weight:800;color:var(--navy)!important;}
.tbl tr.tot td{
  background:var(--navy)!important;color:var(--gold2)!important;
  font-weight:800;font-size:8.5px;
  border:1px solid rgba(255,255,255,.1)!important;
  padding:0 4px!important;height:auto!important;
}

/* ── FOOTER ── */
.ftr{display:grid;grid-template-columns:1fr 240px;
  border-top:2px solid var(--navy);flex-shrink:0;}
.bank{padding:5px 10px;border-right:1.5px solid var(--navy);background:var(--stripe);}
.bank-ttl{font-family:'Playfair Display',serif;font-size:9px;font-weight:700;
  color:var(--navy);border-bottom:1px solid var(--bdr);padding-bottom:2px;margin-bottom:3px;}
.brow{font-size:8.5px;color:var(--dk);line-height:1.9;}
.bk{display:inline-block;min-width:46px;color:var(--grey);font-weight:700;
  font-size:7px;text-transform:uppercase;letter-spacing:.4px;margin-right:3px;}
.bv{font-weight:800;color:var(--navy);font-size:9px;}
.juris{margin-top:4px;font-style:italic;font-size:7.5px;color:var(--grey);
  border-top:1px dashed var(--bdr);padding-top:3px;}

.amts{background:#fff;}
.amts table{width:100%;border-collapse:collapse;}
.amts td{padding:2.5px 9px;font-size:9px;border-bottom:1px solid #eee;color:var(--dk);}
.amts td:last-child{text-align:right;font-weight:700;color:var(--navy);font-variant-numeric:tabular-nums;}
.tsub td{font-weight:800!important;background:#e8ecf8!important;
  border-top:2px solid var(--navy)!important;border-bottom:2px solid var(--navy)!important;font-size:9.5px!important;}
.tded td{color:var(--red)!important;}
.tnet td{background:var(--navy)!important;color:var(--gold2)!important;
  font-weight:900!important;font-size:11px!important;
  border:none!important;padding:5px 9px!important;}
.tsig td{text-align:center!important;font-size:7.5px;color:var(--grey);
  height:24px;vertical-align:bottom;padding-bottom:4px;
  font-style:italic;font-weight:600;
  border-top:1px dashed var(--bdr)!important;border-bottom:none!important;}

/* ── WORDS ── */
.words{display:flex;align-items:center;gap:7px;
  padding:3px 10px;background:var(--cream);
  border-top:1.5px solid var(--bdr);flex-shrink:0;}
.wlbl{white-space:nowrap;font-size:7.5px;font-weight:800;
  text-transform:uppercase;letter-spacing:1px;color:var(--grey);}
.wval{flex:1;border-bottom:1.5px dashed var(--bdr);
  padding:0 5px 1px;font-style:italic;font-size:10px;font-weight:700;color:var(--navy);}

/* ══════════════════════════════════════
   PRINT
══════════════════════════════════════ */
@media print{
  body{background:#fff;}
  .no-print{display:none!important;}
  .page{
    margin:0;width:297mm;height:210mm;
    padding:10mm;box-shadow:none;
  }
  @page{size:A4 landscape;margin:0;}
}
</style>
</head>
<body>

<div class="no-print">
  <button class="btn-p" onclick="window.print()">🖨 &nbsp;Print</button>
  <button class="btn-c" onclick="window.close()">✕ &nbsp;Close</button>
</div>

<div class="page">
<div class="wrap">

  <div class="accent"></div>

  <!-- HEADER -->
  <div class="hdr">
    <div class="co-name">SHAMA ROADLINES</div>
    <div class="co-div"></div>
    <div class="co-tag">Transport Contractor &amp; Commission Agent</div>
  </div>

  <!-- ADDRESS -->
  <div class="addr">
    <div class="addr-main">Shop No. 16, Gulistan Complex, N.H. No. 8, Narmada Chowkdi, Bharuch – 392015</div>
    <div class="addr-contact">
      <span class="lbl">M:</span> 99740 94467 <span class="sep">|</span>
      77789 24467 <span class="sep">|</span> 98240 94467
      &nbsp;&nbsp;<span class="lbl">Ph:</span> 02642-233830
      &nbsp;&nbsp;<span class="lbl">Email:</span> shamaroadlines30@gmail.com
    </div>
  </div>

  <!-- PAN -->
  <div class="pan">PAN :&nbsp;<span class="pan-pill">ANRPM8121A</span></div>

  <!-- PARTY / BILL INFO -->
  <div class="info">
    <div class="info-l">
      <div class="frow"><span class="flbl">Name :</span><div class="fval"><?= s($bill['PartyName']) ?></div></div>
      <div class="frow"><span class="flbl">Address :</span><div class="fval"><?= s($bill['City']??'') ?><?= $bill['State']?' – '.s($bill['State']):'' ?></div></div>
    </div>
    <div class="info-r">
      <div class="frow"><span class="flbl">Bill No. :</span><div class="fval"><span class="billno"><?= s($bill['BillNo']) ?></span></div></div>
      <div class="frow"><span class="flbl">Date :</span><div class="fval"><?= date('d / m / Y', strtotime($bill['BillDate'])) ?></div></div>
    </div>
  </div>

  <!-- SECTION -->
  <div class="sec">◆ &nbsp;Trip Details</div>

  <!-- TABLE -->
  <table class="tbl">
    <thead>
      <tr>
        <th style="width:20px">Sr.</th>
        <th style="width:50px">Date</th>
        <th style="width:66px">Truck No.</th>
        <th style="width:36px">L.R.</th>
        <th style="width:58px">From</th>
        <th style="width:58px">To</th>
        <th style="width:38px">Wt.<br>(MT)</th>
        <th style="width:42px">Rate</th>
        <th style="width:52px">Freight</th>
        <th style="width:46px">Other<br>Chg.</th>
        <th style="width:54px">Total</th>
        <th style="width:44px">Cash<br>Adv.</th>
        <th style="width:48px">Online<br>Adv.</th>
        <th style="width:34px">TDS</th>
        <th style="width:60px">Net Amt.</th>
      </tr>
    </thead>
    <tbody>
<?php for($i=0;$i<$totalRows;$i++):
  $d = $rows[$i] ?? null;
  $tr = $d ? $d['tr'] : null;
?>
      <tr class="dr">
        <td><?= $tr?($i+1):'' ?></td>
        <td><?= $tr?date('d-m-y',strtotime($tr['TripDate'])):'' ?></td>
        <td class="tb"><?= $tr?s($tr['VehicleNumber']):'' ?></td>
        <td><?= $tr?str_pad($tr['TripId'],4,'0',STR_PAD_LEFT):'' ?></td>
        <td class="tl"><?= $tr?s($tr['FromLocation']):'' ?></td>
        <td class="tl"><?= $tr?s($tr['ToLocation']):'' ?></td>
        <td class="tr"><?= ($tr&&$d['wt']>0)?number_format($d['wt'],3):'' ?></td>
        <td class="tr"><?= ($tr&&$d['rate']>0)?rs($d['rate']):'' ?></td>
        <td class="tr"><?= $tr?rs($d['fr']):'' ?></td>
        <td class="tr"><?= $tr?rs($d['otherC']):'' ?></td>
        <td class="tr tb"><?= $tr?rs($d['tot']):'' ?></td>
        <td class="tr"><?= $tr?rs($d['cash']):'' ?></td>
        <td class="tr"><?= $tr?rs($d['onl']):'' ?></td>
        <td class="tr"><?= $tr?rs($d['tds']):'' ?></td>
        <td class="tr tn"><?= $tr?rs($d['net']):'' ?></td>
      </tr>
<?php endfor; ?>
      <tr class="tot">
        <td colspan="8" style="text-align:right;padding-right:10px;letter-spacing:1.5px;">◆ &nbsp;TOTAL</td>
        <td class="tr"><?= rs($gFr) ?></td>
        <td class="tr"><?= rs($gother) ?></td>
        <td class="tr"><?= rs($gFr+$gother) ?></td>
        <td class="tr"><?= rs($gCash) ?></td>
        <td class="tr"><?= rs($gOnline) ?></td>
        <td class="tr"><?= rs($gTds) ?></td>
        <td class="tr"><?= rs($gNet) ?></td>
      </tr>
    </tbody>
  </table>

  <!-- FOOTER -->
  <div class="ftr">
    <div class="bank">
      <div class="bank-ttl">:: Bank Details ::</div>
      <div class="brow"><span class="bk">Name</span><span class="bv">SHAMA ROADLINES</span></div>
      <div class="brow"><span class="bk">Bank</span><span class="bv">HDFC Bank</span></div>
      <div class="brow"><span class="bk">A/C No.</span><span class="bv">59251786786786</span></div>
      <div class="brow"><span class="bk">IFSC</span><span class="bv">HDFC0000068</span></div>
      <div class="juris">★ Subject to Bharuch Jurisdiction</div>
    </div>
    <div class="amts">
      <table>
        <tr><td>Freight</td><td>Rs. <?= rs($gFr) ?></td></tr>
        <?php if($gLab >0):?><tr><td>Labour</td><td>Rs. <?= rs($gLab) ?></td></tr><?php endif;?>
        <?php if($gHold>0):?><tr><td>Holding</td><td>Rs. <?= rs($gHold) ?></td></tr><?php endif;?>
        <?php if($gOth >0):?><tr><td>Other</td><td>Rs. <?= rs($gOth) ?></td></tr><?php endif;?>
        <tr class="tsub"><td>TOTAL</td><td>Rs. <?= rs($gFr+$gLab+$gHold+$gOth) ?></td></tr>
        <?php if($gCash  >0):?><tr class="tded"><td>(-) Cash Adv.</td><td>Rs. <?= rs($gCash) ?></td></tr><?php endif;?>
        <?php if($gOnline>0):?><tr class="tded"><td>(-) Online Adv.</td><td>Rs. <?= rs($gOnline) ?></td></tr><?php endif;?>
        <?php if($gTds   >0):?><tr class="tded"><td>(-) TDS</td><td>Rs. <?= rs($gTds) ?></td></tr><?php endif;?>
        <tr class="tnet"><td>NET TOTAL</td><td>Rs. <?= rs($gNet) ?></td></tr>
        <tr class="tsig"><td colspan="2">For, SHAMA ROADLINES</td></tr>
      </table>
    </div>
  </div>

  <!-- WORDS -->
  <div class="words">
    <span class="wlbl">Rs. In Words :</span>
    <span class="wval"><?= s(numToWords(intval($gNet)).' Only') ?></span>
  </div>

  <div class="accent"></div>

</div>
</div>
</body>
</html>