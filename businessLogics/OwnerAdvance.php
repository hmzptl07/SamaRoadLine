<?php
require_once __DIR__ . '/../config/database.php';

class OwnerAdvance {

    /**
     * All advance records, optionally filtered by owner.
     * owneradvance.OwnerId → VehicleOwnerMaster.VehicleOwnerId
     */
    public static function getAll(?int $ownerId = null): array {
        global $pdo;
        $where  = $ownerId ? "WHERE oa.OwnerId = :oid" : "";
        $params = $ownerId ? [':oid' => $ownerId] : [];

        $stmt = $pdo->prepare("
            SELECT oa.*, vom.OwnerName, vom.MobileNo, vom.City
            FROM owneradvance oa
            JOIN VehicleOwnerMaster vom ON oa.OwnerId = vom.VehicleOwnerId
            $where
            ORDER BY oa.OwnerAdvanceId DESC
            LIMIT 500
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Insert a new advance record */
    public static function insert(array $data): array {
        global $pdo;
        $amount = floatval($data['Amount']);
        try {
            $pdo->prepare("
                INSERT INTO owneradvance
                    (OwnerId, AdvanceDate, Amount, PaymentMode, ReferenceNo, RemainingAmount, Remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                intval($data['OwnerId']),
                $data['AdvanceDate'],
                $amount,
                $data['PaymentMode']  ?? 'Cash',
                trim($data['ReferenceNo'] ?? ''),
                $amount,                          // RemainingAmount starts equal to Amount
                trim($data['Remarks'] ?? ''),
            ]);
            return ['status' => 'success'];
        } catch (Exception $e) {
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    /**
     * Trips that still have an outstanding owner payment balance.
     * Uses VehicleMaster.VehicleOwnerId to link vehicle → owner.
     */
    public static function getOwnerUnpaidTrips(int $ownerId): array {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT
                t.TripId, t.TripDate, t.FromLocation, t.ToLocation,
                t.FreightAmount,
                COALESCE(t.LabourCharge,  0) AS LabourCharge,
                COALESCE(t.HoldingCharge, 0) AS HoldingCharge,
                COALESCE(t.OtherCharge,   0) AS OtherCharge,
                COALESCE(t.TDS,           0) AS TDS,
                COALESCE(tc.CommissionAmount, 0) AS Commission,
                t.OwnerPaymentStatus,
                v.VehicleNumber,
                GREATEST(0,
                    t.FreightAmount
                    + COALESCE(t.LabourCharge,  0)
                    + COALESCE(t.HoldingCharge, 0)
                    + COALESCE(t.OtherCharge,   0)
                    + COALESCE(t.TDS,           0)
                    - COALESCE(tc.CommissionAmount, 0)
                ) AS NetPayable,
                COALESCE(SUM(op.Amount), 0) AS Paid
            FROM TripMaster t
            JOIN VehicleMaster      v   ON t.VehicleId      = v.VehicleId
            LEFT JOIN TripCommission tc  ON t.TripId         = tc.TripId
            LEFT JOIN ownerpayment  op  ON t.TripId         = op.TripId
            WHERE v.VehicleOwnerId = ?
              AND t.OwnerPaymentStatus != 'Paid'
              AND COALESCE(t.FreightPaymentToOwnerStatus, 'Pending') != 'PaidDirectly'
            GROUP BY t.TripId
            ORDER BY t.TripDate DESC
        ");
        $stmt->execute([$ownerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['Remaining'] = max(0, floatval($r['NetPayable']) - floatval($r['Paid']));
        }
        return $rows;
    }

    /**
     * Adjust an advance balance against a trip:
     *   1. Record in owneradvanceadjustment
     *   2. Deduct from owneradvance.RemainingAmount
     *   3. Insert matching ownerpayment row for the trip
     *   4. Recalc TripMaster.OwnerPaymentStatus
     */
    public static function adjustAgainstTrip(
        int    $advanceId,
        int    $tripId,
        int    $ownerId,           // VehicleOwnerMaster.VehicleOwnerId
        float  $amount,
        string $date,
        string $remarks = ''
    ): array {
        global $pdo;
        try {
            $pdo->beginTransaction();

            // Fetch & validate advance record
            $adv = $pdo->prepare(
                "SELECT * FROM owneradvance WHERE OwnerAdvanceId = ? AND OwnerId = ?"
            );
            $adv->execute([$advanceId, $ownerId]);
            $a = $adv->fetch(PDO::FETCH_ASSOC);

            if (!$a) throw new Exception("Advance record not found.");
            if ($amount <= 0) throw new Exception("Amount must be greater than 0.");
            if ($amount > floatval($a['RemainingAmount'])) {
                throw new Exception(
                    "Amount exceeds available balance (₹" .
                    number_format($a['RemainingAmount'], 2) . ")."
                );
            }

            // 1. Log the adjustment
            $pdo->prepare("
                INSERT INTO owneradvanceadjustment
                    (OwnerAdvanceId, TripId, AdjustedAmount, AdjustmentDate, Remarks)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$advanceId, $tripId, $amount, $date, $remarks]);

            // 2. Update advance balance
            $newAdj = floatval($a['AdjustedAmount']) + $amount;
            $newRem = max(0, floatval($a['Amount']) - $newAdj);
            $status = $newRem <= 0
                ? 'FullyAdjusted'
                : ($newAdj > 0 ? 'PartiallyAdjusted' : 'Open');

            $pdo->prepare("
                UPDATE owneradvance
                SET AdjustedAmount = ?, RemainingAmount = ?, Status = ?
                WHERE OwnerAdvanceId = ?
            ")->execute([$newAdj, $newRem, $status, $advanceId]);

            // 3. Record as a payment on the trip
            $pdo->prepare("
                INSERT INTO ownerpayment
                    (TripId, OwnerId, PaymentDate, Amount, PaymentMode, ReferenceNo, Remarks)
                VALUES (?, ?, ?, ?, 'Other', ?, ?)
            ")->execute([
                $tripId,
                $ownerId,
                $date,
                $amount,
                'ADV-' . str_pad($advanceId, 4, '0', STR_PAD_LEFT),
                'Advance adjusted' . ($remarks ? ' — ' . $remarks : ''),
            ]);

            // 4. Recalc trip payment status
            self::recalcTripStatus($tripId, $pdo);

            $pdo->commit();
            return ['status' => 'success', 'newRemaining' => $newRem, 'advStatus' => $status];
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    /** Adjustment history for one advance record */
    public static function getAdjustments(int $advanceId): array {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT
                adj.*,
                t.FromLocation, t.ToLocation, t.TripDate,
                v.VehicleNumber
            FROM owneradvanceadjustment adj
            LEFT JOIN TripMaster    t  ON adj.TripId   = t.TripId
            LEFT JOIN VehicleMaster v  ON t.VehicleId  = v.VehicleId
            WHERE adj.OwnerAdvanceId = ?
            ORDER BY adj.AdjustmentDate ASC
        ");
        $stmt->execute([$advanceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Overall summary stats for the advance listing page */
    public static function getSummary(): array {
        global $pdo;
        $r = $pdo->query("
            SELECT
                COUNT(*)                                              AS TotalEntries,
                COALESCE(SUM(Amount), 0)                            AS TotalAmount,
                COALESCE(SUM(AdjustedAmount), 0)                    AS TotalAdjusted,
                COALESCE(SUM(RemainingAmount), 0)                   AS TotalRemaining,
                SUM(CASE WHEN Status != 'FullyAdjusted' THEN 1 ELSE 0 END) AS OpenCount
            FROM owneradvance
        ")->fetch(PDO::FETCH_ASSOC);

        return $r ?: [
            'TotalEntries' => 0, 'TotalAmount' => 0,
            'TotalAdjusted' => 0, 'TotalRemaining' => 0, 'OpenCount' => 0,
        ];
    }

    /** Recalculate TripMaster.OwnerPaymentStatus after a change */
    private static function recalcTripStatus(int $tripId, PDO $pdo): void {
        $r = $pdo->prepare("
            SELECT
                GREATEST(0,
                    t.FreightAmount
                    + COALESCE(t.LabourCharge,  0)
                    + COALESCE(t.HoldingCharge, 0)
                    + COALESCE(t.OtherCharge,   0)
                    + COALESCE(t.TDS,           0)
                    - COALESCE(tc.CommissionAmount, 0)
                ) AS NetPayable,
                COALESCE(SUM(op.Amount), 0) AS Paid
            FROM TripMaster t
            LEFT JOIN TripCommission tc ON tc.TripId = t.TripId
            LEFT JOIN ownerpayment   op ON op.TripId = t.TripId
            WHERE t.TripId = ?
            GROUP BY t.TripId, tc.CommissionAmount
        ");
        $r->execute([$tripId]);
        $rv = $r->fetch(PDO::FETCH_ASSOC);

        $paid   = floatval($rv['Paid']       ?? 0);
        $net    = floatval($rv['NetPayable'] ?? 0);
        $status = ($net > 0 && $paid >= $net)
            ? 'Paid'
            : ($paid > 0 ? 'PartiallyPaid' : 'Unpaid');

        $pdo->prepare("UPDATE TripMaster SET OwnerPaymentStatus = ? WHERE TripId = ?")
            ->execute([$status, $tripId]);
    }
}
