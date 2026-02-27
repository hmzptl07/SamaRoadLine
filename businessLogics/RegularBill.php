<?php
/**
 * RegularBill.php
 * Business logic for Regular Bill generation and retrieval.
 */
class RegularBill {

    /* ══════════════════════════════════════════
       GET UNBILLED TRIPS FOR A PARTY
    ══════════════════════════════════════════ */
    public static function getUnbilledTrips(PDO $pdo, int $partyId, string $from = '', string $to = ''): array {
        $dateFrom = $from ? "AND t.TripDate >= '$from'" : '';
        $dateTo   = $to   ? "AND t.TripDate <= '$to'"  : '';

        $stmt = $pdo->prepare("
            SELECT t.TripId, t.TripDate, t.InvoiceNo, t.FromLocation, t.ToLocation,
                   t.FreightAmount, t.LabourCharge, t.HoldingCharge, t.OtherCharge, t.OtherChargeNote, t.TotalAmount,
                   t.CashAdvance, t.OnlineAdvance, t.AdvanceAmount, t.TDS,
                   (t.TotalAmount - t.AdvanceAmount - t.TDS) AS NetAmount,
                   v.VehicleNumber,
                   COALESCE((SELECT SUM(m.Weight) FROM TripMaterial m WHERE m.TripId = t.TripId), 0) AS TotalWeight
            FROM TripMaster t
            LEFT JOIN VehicleMaster v ON t.VehicleId = v.VehicleId
            LEFT JOIN BillTrip bt    ON t.TripId     = bt.TripId
            WHERE bt.TripId IS NULL
              AND t.ConsignerId = ?
              AND t.TripType = 'Regular'
              AND (t.FreightPaymentToOwnerStatus IS NULL OR t.FreightPaymentToOwnerStatus != 'PaidDirectly')
              $dateFrom $dateTo
            ORDER BY t.TripDate ASC");
        $stmt->execute([$partyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ══════════════════════════════════════════
       GENERATE BILL
    ══════════════════════════════════════════ */
    public static function generate(PDO $pdo, int $partyId, array $tripIds, string $billDate, string $remarks = ''): array {
        if (empty($tripIds)) return ['status' => 'error', 'msg' => 'Koi trip select nahi'];
        try {
            $pdo->beginTransaction();
            $prefix = 'RBILL-' . date('Ym') . '-';
            $last   = $pdo->query("SELECT BillNo FROM Bill WHERE BillNo LIKE '$prefix%' ORDER BY BillId DESC LIMIT 1")->fetch();
            $seq    = $last ? intval(substr($last['BillNo'], -3)) + 1 : 1;
            $billNo = $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);

            $phs = implode(',', array_fill(0, count($tripIds), '?'));
            $sum = $pdo->prepare("SELECT SUM(FreightAmount) AS fr, SUM(TotalAmount) AS total, SUM(AdvanceAmount) AS adv, SUM(TDS) AS tds FROM TripMaster WHERE TripId IN ($phs)");
            $sum->execute($tripIds); $s = $sum->fetch(PDO::FETCH_ASSOC);
            $net = floatval($s['total']) - floatval($s['adv']) - floatval($s['tds']);

            $pdo->prepare("INSERT INTO Bill(BillNo,BillDate,PartyId,TotalFreightAmount,TotalAdvanceAmount,NetBillAmount,BillStatus,Remarks)
                           VALUES(?,?,?,?,?,?,'Generated',?)")
                ->execute([$billNo, $billDate, $partyId, $s['total'], $s['adv'], $net, $remarks]);
            $billId = $pdo->lastInsertId();

            $lnk = $pdo->prepare("INSERT INTO BillTrip(BillId,TripId) VALUES(?,?)");
            foreach ($tripIds as $tid) $lnk->execute([$billId, $tid]);

            // ══ TripStatus → 'Billed' ══
            $phs2 = implode(',', array_fill(0, count($tripIds), '?'));
            $pdo->prepare("UPDATE TripMaster SET TripStatus='Billed' WHERE TripId IN ($phs2)")
                ->execute($tripIds);

            $pdo->commit();
            return ['status' => 'success', 'billId' => $billId, 'billNo' => $billNo];
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    /* ══════════════════════════════════════════
       GET ALL BILLS (for list page)
    ══════════════════════════════════════════ */
    public static function getAllBills(PDO $pdo): array {
        return $pdo->query("
            SELECT b.*, p.PartyName, p.City, COUNT(bt.TripId) AS TripCount
            FROM Bill b
            LEFT JOIN PartyMaster p ON b.PartyId  = p.PartyId
            LEFT JOIN BillTrip bt   ON b.BillId   = bt.BillId
            GROUP BY b.BillId ORDER BY b.BillId DESC LIMIT 100
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ══════════════════════════════════════════
       GET BILL WITH PARTY (for print)
    ══════════════════════════════════════════ */
    public static function getBillWithParty(PDO $pdo, int $billId): ?array {
        $stmt = $pdo->prepare("
            SELECT b.*, p.PartyName, p.Address, p.City, p.State
            FROM Bill b LEFT JOIN PartyMaster p ON b.PartyId=p.PartyId
            WHERE b.BillId=?");
        $stmt->execute([$billId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* ══════════════════════════════════════════
       GET TRIPS OF A BILL (for print)
    ══════════════════════════════════════════ */
    public static function getBillTrips(PDO $pdo, int $billId): array {
        $stmt = $pdo->prepare("
            SELECT t.TripId, t.TripDate, t.InvoiceNo, t.FromLocation, t.ToLocation,
                   t.FreightAmount, t.LabourCharge, t.HoldingCharge, t.OtherCharge, t.OtherChargeNote, t.TotalAmount,
                   t.CashAdvance, t.OnlineAdvance, t.AdvanceAmount, t.TDS,
                   (t.TotalAmount - t.AdvanceAmount - t.TDS) AS NetAmount,
                   v.VehicleNumber,
                   COALESCE((SELECT SUM(m.Weight) FROM TripMaterial m WHERE m.TripId = t.TripId), 0) AS TotalWeight
            FROM BillTrip bt
            JOIN TripMaster t         ON bt.TripId   = t.TripId
            LEFT JOIN VehicleMaster v ON t.VehicleId = v.VehicleId
            WHERE bt.BillId = ? ORDER BY t.TripDate ASC");
        $stmt->execute([$billId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ══════════════════════════════════════════
       GET PARTIES THAT HAVE UNBILLED REGULAR TRIPS
    ══════════════════════════════════════════ */
    public static function getPartiesWithUnbilledTrips(PDO $pdo): array {
        return $pdo->query("
            SELECT DISTINCT p.PartyId, p.PartyName, p.City
            FROM TripMaster t
            JOIN PartyMaster p ON t.ConsignerId = p.PartyId
            LEFT JOIN BillTrip bt ON t.TripId = bt.TripId
            WHERE bt.TripId IS NULL
              AND t.TripType = 'Regular'
              AND (t.FreightPaymentToOwnerStatus IS NULL OR t.FreightPaymentToOwnerStatus != 'PaidDirectly')
            ORDER BY p.PartyName ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}
