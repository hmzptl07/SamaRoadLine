<?php
session_start();
require_once "../../businessLogics/Admin.php";
require_once "../../config/database.php";
Admin::checkAuth();

$tripId = intval($_GET['TripId'] ?? 0);
if ($tripId <= 0) die("Invalid Trip");

$stmt = $pdo->prepare("
    SELECT t.*,
           p1.PartyName AS ConsignerName, p1.Address AS ConsignerAddress, p1.City AS ConsignerCity, p1.MobileNo AS ConsignerMobile,
           p3.PartyName AS AgentName,
           v.VehicleNumber
    FROM TripMaster t
    LEFT JOIN PartyMaster p1 ON t.ConsignerId=p1.PartyId
    LEFT JOIN PartyMaster p3 ON t.AgentId=p3.PartyId
    LEFT JOIN VehicleMaster v ON t.VehicleId=v.VehicleId
    WHERE t.TripId=?");
$stmt->execute([$tripId]);
$t = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$t) die("Trip not found");

$matStmt = $pdo->prepare("SELECT * FROM TripMaterial WHERE TripId=?");
$matStmt->execute([$tripId]);
$materials = $matStmt->fetchAll(PDO::FETCH_ASSOC);

function s($v)
{
  return htmlspecialchars($v ?? '', ENT_QUOTES);
}
function m($v)
{
  return number_format(floatval($v ?? 0), 2);
}

$freight  = floatval($t['FreightAmount'] ?? 0);
$labour   = floatval($t['LabourCharge'] ?? 0);
$holding  = floatval($t['HoldingCharge'] ?? 0);
$other    = floatval($t['OtherCharge'] ?? 0);
$advance  = floatval($t['AdvanceAmount'] ?? 0);
$tds      = floatval($t['TDS'] ?? 0);
$total    = $freight + $labour + $holding + $other;
$net      = $total - $advance - $tds;
$totalWt  = array_sum(array_column($materials, 'Weight'));
$matValue = floatval($t['MaterialTotalValue'] ?? 0);
$lrNo     = str_pad($tripId, 4, '0', STR_PAD_LEFT);
$maxRows  = max(count($materials), 5);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>LR No. <?= $lrNo ?> - Shama Roadlines</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: Arial, sans-serif;
      font-size: 10.5px;
      background: #f0f0f0;
      color: #000;
    }

    .print-bar {
      text-align: center;
      padding: 8px;
      background: #343a40;
    }

    .print-bar button {
      margin: 0 4px;
      padding: 6px 20px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 13px;
      font-weight: bold;
    }

    .btn-p {
      background: #1a237e;
      color: #fff;
    }

    .btn-c {
      background: #6c757d;
      color: #fff;
    }

    .page {
      width: 210mm;
      margin: 6px auto;
      border: 2.5px solid #000;
      background: #fff;
    }

    /* TOP STRIP */
    .top-strip {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      border-bottom: 1.5px solid #000;
      font-size: 12px;
      line-height: 1.6;
    }

    .top-strip>div {
      padding: 3px 7px;
    }

    .top-strip>div:nth-child(2) {
      text-align: center;
    }

    .top-strip>div:nth-child(3) {
      text-align: right;
    }

    /* COMPANY */
    .co-header {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 4px 8px 2px;
      border-bottom: 1.5px solid #000;
    }

    .co-name {
      font-size: 30px;
      font-weight: 900;
      color: #1a237e;
      font-style: italic;
      letter-spacing: 1px;
      line-height: 1;
    }

    .co-sub {
      font-size: 10px;
      font-weight: bold;
      letter-spacing: 2px;
      color: #000;
      margin-top: 1px;
    }

    .co-addr {
      text-align: center;
      padding: 2px 8px;
      border-bottom: 1.5px solid #000;
      font-size: 11px;
    }

    /* TRUCK / LR / DATE — 3 column */
    .info-band {
      display: grid;
      grid-template-columns: 33.33% 33.33% 33.33%;
      border-bottom: 1.5px solid #000;
    }

    .ib-cell {
      padding: 3px 7px;
    }

    .ib-cell+.ib-cell {
      border-left: 1.5px solid #000;
    }

    .ib-label {
      font-weight: bold;
      font-size: 12px;
      color: #555;
      margin-bottom: 1px;
    }

    .ib-value {
      font-size: 12px;
      font-weight: bold;
    }

    .lr-num {
      color: #c62828;
      font-size: 20px;
      font-weight: 900;
      letter-spacing: 1px;
    }

    /* LR Centre sub-info: invoice + value */
    .lr-sub {
      margin-top: 3px;
      font-size: 9px;
      line-height: 1.6;
    }

    .lr-sub span {
      font-weight: bold;
      color: #333;
    }

    /* PARTY ROW */
    .party-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      border-bottom: 1.5px solid #000;
    }

    .party-box {
      padding: 4px 7px;
    }

    .party-box:first-child {
      border-right: 1.5px solid #000;
    }

    .party-title {
      font-weight: 900;
      font-size: 11px;
      letter-spacing: 0.5px;
      color: #1a237e;
      border-bottom: 1px dotted #aaa;
      padding-bottom: 1px;
      margin-bottom: 2px;
    }

    .party-name {
      font-size: 13px;
      font-weight: bold;
    }

    .party-detail {
      font-size: 10px;
      color: #333;
      margin-top: 1px;
      line-height: 1.4;
    }

    /* ROUTE */
    .route-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      border-bottom: 1.5px solid #000;
      padding: 3px 8px;
      gap: 8px;
    }

    .route-field {
      display: flex;
      align-items: center;
      gap: 4px;
      font-size: 20px;
    }

    .route-field b {
      min-width: 38px;
      color: #1a237e;
    }

    .route-val {
      border-bottom: 1.5px solid #000;
      flex: 1;
      padding: 1px 2px;
      font-weight: bold;
    }

    /* MATERIAL TABLE */
    .mat-wrap {
      border-bottom: 1.5px solid #000;
    }

    .mat-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 9.5px;
    }

    .mat-table th {
      border: 1px solid #000;
      padding: 2px 3px;
      text-align: center;
      background: #e8e8e8;
      font-weight: bold;
      font-size: 9px;
    }

    .mat-table td {
      border: 1px solid #000;
      padding: 0 3px;
      text-align: center;
      height: 19px;
    }

    .mat-table .desc {
      text-align: left;
    }

    .mat-table tfoot td {
      background: #f5f5f5;
      font-weight: bold;
      font-size: 9px;
    }

    /* INVOICE + VALUE bar below table */
    .inv-val-bar {
      display: grid;
      grid-template-columns: 1fr 1fr;
      border-bottom: 1.5px solid #000;
      font-size: 9.5px;
    }

    .inv-val-cell {
      padding: 3px 7px;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .inv-val-cell:first-child {
      border-right: 1px solid #ccc;
    }

    .inv-val-cell label {
      font-weight: bold;
      min-width: 90px;
      color: #333;
    }

    .inv-val-cell span {
      font-weight: bold;
      color: #1a237e;
    }

    /* CHARGES */
    .bottom-row {
      display: grid;
      grid-template-columns: 1fr 210px;
      border-bottom: 1.5px solid #000;
    }

    .terms-col {
      padding: 5px 7px;
      border-right: 1.5px solid #000;
      font-size: 9px;
      line-height: 1.7;
    }

    .terms-title {
      font-weight: bold;
      font-size: 9.5px;
      margin-bottom: 3px;
      color: #1a237e;
    }

    .terms-check {
      margin-bottom: 1px;
    }

    .charges-col table {
      width: 100%;
      border-collapse: collapse;
      height: 100%;
    }

    .charges-col td {
      border: 1px solid #bbb;
      padding: 2px 6px;
      font-size: 9.5px;
    }

    .charges-col td:last-child {
      text-align: right;
      font-weight: bold;
    }

    .c-total td {
      background: #e8e8e8;
      font-weight: 900 !important;
      border: 1.5px solid #000 !important;
    }

    .c-net td {
      background: #1a237e;
      color: #fff !important;
      font-weight: 900 !important;
      border: 1.5px solid #000 !important;
    }

    /* FOOTER */
    .footer-strip {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 4px 8px;
      font-size: 10px;
    }

    .delivery-blank {
      border-bottom: 1.5px solid #000;
      min-width: 150px;
      display: inline-block;
    }

    @media print {
      .print-bar {
        display: none !important;
      }

      body {
        background: #fff;
      }

      .page {
        margin: 0;
        border: 2px solid #000;
      }

      @page {
        margin: 4mm;
        size: A4 portrait;
      }
    }
  </style>
</head>

<body>

  <div class="print-bar">
    <button class="btn-p" onclick="window.print()">🖨️ Print GC / LR</button>
    <button class="btn-c" onclick="window.close()">✖ Close</button>
  </div>

  <div class="page">

    <!-- TOP STRIP -->
    <div class="top-strip">
      <div>
        Email: mahirahmedmarothi86@gmail.com<br>
        Email: shamaroadlines30@gmail.com
      </div>
      <div style="font-style:italic;">
        Subject to Bharuch Jurisdiction<br>
        <strong>At Owner's Risk</strong>
      </div>
      <div>
        Mo: 99740 94467, 7778924467<br>
        Ph: 02642-233830
      </div>
    </div>

    <!-- COMPANY -->
    <div class="co-header">
      <div style="font-size:50px;line-height:1;">🚛</div>
      <div>
        <div class="co-name">SHAMA ROADLINES</div>
        <div class="co-sub">TRANSPORT CONTRACTOR &amp; COMMISSION AGENT</div>
      </div>
    </div>
    <div class="co-addr">Shop No. 16, Gulistan Complex, N. H. No. 8, Narmada Chowkdi, Bharuch - 392015</div>

    <!-- TRUCK / LR / DATE -->
    <div class="info-band">
      <div class="ib-cell">
        
        <div class="ib-label" style="margin-top:3px;">DATE</div>
        <div class="ib-value" style="font-size:13px;"><?= date('d / m / Y', strtotime($t['TripDate'])) ?></div>
      </div>
      <div class="ib-cell" style="text-align:center;">
      <div class="ib-label">TRUCK NO.</div>
        <div class="ib-value" style="font-size:13px;"><?= s($t['VehicleNumber'] ?? '—') ?></div>
        <!-- Invoice No and Material Value shown here prominently -->
        
      </div>
      <div class="ib-cell" style="text-align:right;">
         <div style="display: inline;"  class="ib-label">G.C. NO. :</div>
        <div style="display: inline;" class="lr-num"><?= $lrNo ?></div>
      </div>
    </div>

    <!-- CONSIGNOR & CONSIGNEE -->
    <div class="party-row">
      <div class="party-box">
        <div class="party-title">CONSIGNOR (Sender)</div>
        <div class="party-name"><?= s($t['ConsignerName'] ?? '—') ?></div>
        <div class="party-detail">
          <?php if (!empty($t['ConsignerCity'])): ?>City: <?= s($t['ConsignerCity']) ?><?php endif; ?>
          <?php if (!empty($t['ConsignerMobile'])): ?> &nbsp;|&nbsp; Mo: <?= s($t['ConsignerMobile']) ?><?php endif; ?>
        </div>
        <?php if (!empty($t['ConsignerAddress'])): ?>
          <div class="party-detail"><?= s($t['ConsignerAddress']) ?></div>
        <?php endif; ?>
      </div>
      <div class="party-box">
        <div class="party-title">CONSIGNEE (Receiver)</div>
        <div class="party-name"><?= s($t['ConsigneeName'] ?? '—') ?></div>
        <div class="party-detail">
            <?php if (!empty($t['ConsigneeCity'])): ?>City: <?= s($t['ConsigneeCity']) ?><?php endif; ?>
          <?php if (!empty($t['ConsigneeContactNo'])): ?> &nbsp;|&nbsp; Mo: <?= s($t['ConsigneeContactNo']) ?><?php endif; ?>
        </div>
        <?php if (!empty($t['ConsigneeAddress'])): ?>
          <div class="party-detail"><?= s($t['ConsigneeAddress']) ?></div>
        <?php endif; ?>
        <?php if (!empty($t['AgentName'])): ?>
          <div class="party-detail" style="margin-top:2px;"><strong>Agent:</strong> <?= s($t['AgentName']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ROUTE -->
    <div class="route-row">
      <div class="route-field">
        <b>From :</b>
        <div class="route-val"><?= s($t['FromLocation']) ?></div>
      </div>
      <div class="route-field">
        <b>To :</b>
        <div class="route-val"><?= s($t['ToLocation']) ?></div>
      </div>
      
    </div>

    <!-- MATERIAL TABLE -->
    <div class="mat-wrap">
      <table class="mat-table">
        <thead>
          <tr>
            <th width="44">Qty.<br>(Ton)</th>
            <th class="desc">Said to Contain (Goods Description)</th>
            <th width="52">Rate<br>Rs.</th>
            <th width="42">Rate<br>Ps.</th>
            <th width="72">Actual Wt.<br>Kgs</th>
            <th width="60">Freight<br>Rs.</th>
            <th width="50">Freight<br>Ps.</th>
          </tr>
        </thead>
        <tbody>
          <?php
          for ($i = 0; $i < $maxRows; $i++):
            $mat = $materials[$i] ?? null;
            $wKg = $mat ? round(floatval($mat['Weight']) * 1000) : '';
            $rRs = $mat ? floor(floatval($mat['Rate'])) : '';
            $rPs = $mat ? round((floatval($mat['Rate']) - floor(floatval($mat['Rate']))) * 100) : '';
            $aRs = $mat ? floor(floatval($mat['Amount'])) : '';
            $aPs = $mat ? round((floatval($mat['Amount']) - floor(floatval($mat['Amount']))) * 100) : '';
          ?>
            <tr>
              <td><?= $mat ? s($mat['Weight']) . 'T' : '' ?></td>
              <td class="desc"><?= $mat ? s($mat['MaterialName']) : '' ?></td>
              <td><?= $mat ? $rRs : '' ?></td>
              <td><?= $mat ? $rPs : '' ?></td>
              <td><?= $mat ? $wKg : '' ?></td>
              <td><?= $mat ? $aRs : '' ?></td>
              <td><?= $mat ? $aPs : '' ?></td>
            </tr>
          <?php endfor; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3" style="text-align:right;padding-right:4px;">Total Weight :</td>
            <td colspan="2" style="font-weight:bold;"><?= number_format($totalWt * 1000, 0) ?> Kgs (<?= number_format($totalWt, 3) ?> Ton)</td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- INVOICE NO + MATERIAL VALUE bar (always visible) -->
    <div class="inv-val-bar">
      <div class="inv-val-cell">
        <label>Party Invoice No. :</label>
        <span><?= !empty($t['InvoiceNo']) ? s($t['InvoiceNo']) : '—' ?></span>
      </div>
      <div class="inv-val-cell">
        <label>Material Value :</label>
        <span><?= $matValue > 0 ? 'Rs.' . m($matValue) : '—' ?></span>
      </div>
    </div>

    <!-- CHARGES + TERMS -->
    <div class="bottom-row">
      <div class="terms-col">
        <div class="terms-title">SERVICE TAX / TERMS :</div>
        <div class="terms-check">&#9634; Service Tax Paid by CONSIGNOR</div>
        <div class="terms-check">&#9634; Service Tax Paid by CONSIGNEE</div>
        <div class="terms-check">&#9634; Service Tax Paid by TRANSPORTER</div>
        <div style="margin-top:6px;font-size:8.5px;font-style:italic;color:#555;">CONSUMER COPY &nbsp;|&nbsp; E.&amp;O.E.</div>
      </div>
      <div class="charges-col">
        <table>
          <tr>
            <td>Freight Charge</td>
            <td>Rs. <?= m($freight) ?></td>
          </tr>
          <tr>
            <td>Labour Charge</td>
            <td>Rs. <?= m($labour) ?></td>
          </tr>
          <tr>
            <td>Holding / Detention</td>
            <td>Rs. <?= m($holding) ?></td>
          </tr>
          <tr>
            <td><?= !empty($t['OtherChargeNote']) ? s($t['OtherChargeNote']) : 'Other Charge' ?></td>
            <td>Rs. <?= m($other) ?></td>
          </tr>
          <tr class="c-total">
            <td>TOTAL</td>
            <td>Rs. <?= m($total) ?></td>
          </tr>
          <?php
            $cash   = floatval($t['CashAdvance']??0);
            $online = floatval($t['OnlineAdvance']??0);
          ?>
          <?php if($cash > 0 || $online > 0): ?>
            <tr><td>(-) Cash Advance</td><td>Rs. <?= m($cash) ?></td></tr>
            <tr><td>(-) Online Advance</td><td>Rs. <?= m($online) ?></td></tr>
          <?php else: ?>
            <tr><td>(-) Advance</td><td>Rs. <?= m($advance) ?></td></tr>
          <?php endif; ?>
          <tr>
            <td>(-) TDS</td>
            <td>Rs. <?= m($tds) ?></td>
          </tr>
          <tr class="c-net">
            <td>NET AMOUNT</td>
            <td>Rs. <?= m($net) ?></td>
          </tr>
          <tr>
            <td colspan="2" style="height:26px;text-align:center;font-size:8.5px;color:#555;vertical-align:bottom;">Authorized Signature</td>
          </tr>
        </table>
      </div>
    </div>

    <!-- FOOTER -->
    <div class="footer-strip">
      <div>
        <strong>Delivery :</strong>
        <span class="delivery-blank">&nbsp;</span>
      </div>
      <div style="font-weight:bold;font-size:10.5px;">For, SHAMA ROADLINES &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
    </div>

  </div>

  <script>
    <?php if (!empty($_GET['autoprint'])): ?>
      window.onload = function() {
        window.print();
      };
    <?php endif; ?>
  </script>
</body>

</html>