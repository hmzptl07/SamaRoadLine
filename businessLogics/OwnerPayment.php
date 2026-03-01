<?php
require_once __DIR__ . '/../config/database.php';

class OwnerPayment {

    /**
     * Net Payable to Owner = FreightAmount + LabourCharge + HoldingCharge + OtherCharge + TDS - Commission
     * When fully paid -> CommissionStatus = 'Received' auto-marked
     */
    public static function getAllTripsWithPaymentStatus(?int $ownerId = null): array {
        global $pdo;

        $where  = $ownerId ? "AND vom.VehicleOwnerId = :ownerId" : "";
        $params = $ownerId ? [':ownerId' => $ownerId] : [];

        $stmt = $pdo->prepare("
            SELECT
                t.TripId, t.TripDate, t.TripType,
                t.FromLocation, t.ToLocation,
                t.FreightAmount,
                COALESCE(t.LabourCharge,  0) AS LabourCharge,
                COALESCE(t.HoldingCharge, 0) AS HoldingCharge,
                COALESCE(t.OtherCharge,   0) AS OtherCharge,
                COALESCE(t.TDS,           0) AS TDS,
                COALESCE(tc.CommissionAmount, 0)         AS Commission,
                COALESCE(tc.CommissionStatus, 'Pending') AS CommissionStatus,
                t.FreightPaymentToOwnerStatus,
                t.OwnerPaymentStatus,
                t.Remarks,
                v.VehicleNumber,
                vom.VehicleOwnerId,
                vom.OwnerName, vom.MobileNo,
                vom.BankName, vom.AccountNo, vom.IFSC, vom.UPI,
                p1.PartyName AS ConsignerName,
                p2.PartyName AS ConsigneeName,
                p3.PartyName AS AgentName,
                COALESCE(op.TotalPaid,    0) AS TotalPaid,
                COALESCE(op.PaymentCount, 0) AS PaymentCount
            FROM TripMaster t
            LEFT JOIN VehicleMaster      v   ON t.VehicleId      = v.VehicleId
            LEFT JOIN VehicleOwnerMaster vom ON v.VehicleOwnerId = vom.VehicleOwnerId
            LEFT JOIN PartyMaster        p1  ON t.ConsignerId    = p1.PartyId
            LEFT JOIN PartyMaster        p2  ON t.ConsigneeId    = p2.PartyId
            LEFT JOIN PartyMaster        p3  ON t.AgentId        = p3.PartyId
            LEFT JOIN TripCommission     tc  ON t.TripId         = tc.TripId
            LEFT JOIN (
                SELECT TripId,
                       COALESCE(SUM(Amount), 0) AS TotalPaid,
                       COUNT(*)                 AS PaymentCount
                FROM ownerpayment
                GROUP BY TripId
            ) op ON t.TripId = op.TripId
            WHERE vom.VehicleOwnerId IS NOT NULL
              AND (t.FreightPaymentToOwnerStatus IS NULL OR t.FreightPaymentToOwnerStatus = 'Pending')
              $where
            ORDER BY t.TripDate DESC, t.TripId DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['FreightAmount']  = floatval($r['FreightAmount']);
            $r['LabourCharge']   = floatval($r['LabourCharge']);
            $r['HoldingCharge']  = floatval($r['HoldingCharge']);
            $r['OtherCharge']    = floatval($r['OtherCharge']);
            $r['TDS']            = floatval($r['TDS']);
            $r['Commission']     = floatval($r['Commission']);
            $r['TotalCharges']   = $r['LabourCharge'] + $r['HoldingCharge'] + $r['OtherCharge'];

            /* Net Payable = Freight + Charges + TDS - Commission */
            $r['NetPayable'] = max(0,
                $r['FreightAmount']
              + $r['TotalCharges']
              + $r['TDS']
              - $r['Commission']
            );
            $r['TotalPaid']  = floatval($r['TotalPaid']);
            $r['Remaining']  = max(0, $r['NetPayable'] - $r['TotalPaid']);
        }
        unset($r);
        return $rows;
    }

    /** All payment entries for a trip */
    public static function getByTrip(int $tripId): array {
        global $pdo;
        $s = $pdo->prepare("SELECT * FROM ownerpayment WHERE TripId = ? ORDER BY PaymentDate ASC");
        $s->execute([$tripId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add a payment.
     * When trip is fully paid → TripCommission.CommissionStatus = 'Received'
     */
    public static function addPayment(int $tripId, int $ownerId, array $data): array {
        global $pdo;
        try {
            $pdo->beginTransaction();

            $pdo->prepare("
                INSERT INTO ownerpayment
                    (TripId, OwnerId, PaymentDate, Amount, PaymentMode, ReferenceNo, Remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $tripId,
                $ownerId,
                $data['PaymentDate'],
                floatval($data['Amount']),
                $data['PaymentMode']  ?? 'Cash',
                trim($data['ReferenceNo'] ?? ''),
                trim($data['Remarks']     ?? ''),
            ]);

            $status             = self::recalcStatus($tripId, $pdo);
            $commissionReceived = false;

            /* Auto-mark commission Received when fully paid */
            if ($status === 'Paid') {
                $u = $pdo->prepare("
                    UPDATE TripCommission
                    SET CommissionStatus = 'Received',
                        ReceivedDate     = CURDATE()
                    WHERE TripId = ?
                ");
                $u->execute([$tripId]);
                $commissionReceived = $u->rowCount() > 0;
            }

            $pdo->commit();
            return ['status' => 'success', 'tripStatus' => $status, 'commissionReceived' => $commissionReceived];
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    /** Delete a payment and recalculate. Reverts commission if no longer fully paid. */
    public static function deletePayment(int $paymentId): array {
        global $pdo;
        try {
            $pdo->beginTransaction();

            $r = $pdo->prepare("SELECT TripId FROM ownerpayment WHERE OwnerPaymentId = ?");
            $r->execute([$paymentId]);
            $tripId = (int) $r->fetchColumn();

            $pdo->prepare("DELETE FROM ownerpayment WHERE OwnerPaymentId = ?")->execute([$paymentId]);

            if ($tripId) {
                $status = self::recalcStatus($tripId, $pdo);
                if ($status !== 'Paid') {
                    $pdo->prepare("
                        UPDATE TripCommission
                        SET CommissionStatus = 'Pending',
                            ReceivedDate     = NULL
                        WHERE TripId = ?
                    ")->execute([$tripId]);
                }
            }

            $pdo->commit();
            return ['status' => 'success'];
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    /** Owner-wise summary */
    public static function getOwnerSummary(): array {
        global $pdo;
        return $pdo->query("
            SELECT
                vom.VehicleOwnerId,
                vom.OwnerName, vom.MobileNo, vom.City,
                COUNT(DISTINCT t.TripId) AS TotalTrips,
                COALESCE(SUM(
                    GREATEST(0,
                        t.FreightAmount
                        + COALESCE(t.LabourCharge,  0)
                        + COALESCE(t.HoldingCharge, 0)
                        + COALESCE(t.OtherCharge,   0)
                        + COALESCE(t.TDS, 0)
                        - COALESCE(tc.CommissionAmount, 0)
                    )
                ), 0) AS TotalPayable,
                COALESCE(SUM(COALESCE(op.TotalPaid, 0)), 0) AS TotalPaid,
                COALESCE(SUM(
                    CASE WHEN COALESCE(tc.CommissionStatus, 'Pending') = 'Received'
                         THEN COALESCE(tc.CommissionAmount, 0)
                         ELSE 0
                    END
                ), 0) AS CommissionReceived
            FROM VehicleOwnerMaster vom
            LEFT JOIN VehicleMaster  v   ON v.VehicleOwnerId  = vom.VehicleOwnerId
            LEFT JOIN TripMaster     t   ON t.VehicleId       = v.VehicleId
            LEFT JOIN TripCommission tc  ON tc.TripId         = t.TripId
            LEFT JOIN (
                SELECT TripId, COALESCE(SUM(Amount), 0) AS TotalPaid
                FROM ownerpayment
                GROUP BY TripId
            ) op ON op.TripId = t.TripId
            WHERE vom.IsActive = 'Yes'
              AND (t.FreightPaymentToOwnerStatus IS NULL
                   OR t.FreightPaymentToOwnerStatus = 'Pending'
                   OR t.TripId IS NULL)
            GROUP BY vom.VehicleOwnerId, vom.OwnerName, vom.MobileNo, vom.City
            ORDER BY vom.OwnerName ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Active owners for dropdown */
    public static function getOwners(): array {
        global $pdo;
        return $pdo->query("
            SELECT VehicleOwnerId, OwnerName, MobileNo, City
            FROM VehicleOwnerMaster
            WHERE IsActive = 'Yes'
            ORDER BY OwnerName ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Recalculate and update TripMaster.OwnerPaymentStatus */
    private static function recalcStatus(int $tripId, PDO $pdo): string {
        $r = $pdo->prepare("
            SELECT
                GREATEST(0,
                    t.FreightAmount
                    + COALESCE(t.LabourCharge,  0)
                    + COALESCE(t.HoldingCharge, 0)
                    + COALESCE(t.OtherCharge,   0)
                    + COALESCE(t.TDS, 0)
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
        return $status;
    }
}
