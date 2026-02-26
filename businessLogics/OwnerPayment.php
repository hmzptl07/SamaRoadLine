<?php
require_once __DIR__ . '/../config/database.php';

class OwnerPayment {

    /**
     * All trips that have a vehicle owner assigned, with payment summary.
     *
     * Column mapping (important):
     *   VehicleMaster.VehicleOwnerId   → FK to VehicleOwnerMaster
     *   VehicleOwnerMaster.VehicleOwnerId → PK
     *   ownerpayment.OwnerId            → stores VehicleOwnerId value
     */
    public static function getAllTripsWithPaymentStatus(?int $ownerId = null): array {
        global $pdo;

        $where  = $ownerId ? "AND vom.VehicleOwnerId = :ownerId" : "";
        $params = $ownerId ? [':ownerId' => $ownerId] : [];

        $stmt = $pdo->prepare("
            SELECT
                t.TripId, t.TripDate, t.TripType,
                t.FromLocation, t.ToLocation,
                t.FreightAmount, t.AdvanceAmount, t.TDS,
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
                COALESCE(SUM(op.Amount), 0) AS TotalPaid,
                COUNT(op.OwnerPaymentId)    AS PaymentCount
            FROM TripMaster t
            LEFT JOIN VehicleMaster      v   ON t.VehicleId      = v.VehicleId
            LEFT JOIN VehicleOwnerMaster vom ON v.VehicleOwnerId = vom.VehicleOwnerId
            LEFT JOIN PartyMaster        p1  ON t.ConsignerId    = p1.PartyId
            LEFT JOIN PartyMaster        p2  ON t.ConsigneeId    = p2.PartyId
            LEFT JOIN PartyMaster        p3  ON t.AgentId        = p3.PartyId
            LEFT JOIN ownerpayment       op  ON t.TripId         = op.TripId
            WHERE vom.VehicleOwnerId IS NOT NULL
              AND (t.FreightPaymentToOwnerStatus IS NULL OR t.FreightPaymentToOwnerStatus = 'Pending')
              $where
            GROUP BY t.TripId
            ORDER BY t.TripDate DESC, t.TripId DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['NetPayable'] = max(0,
                floatval($r['FreightAmount'])
              - floatval($r['AdvanceAmount'])
              - floatval($r['TDS'])
            );
            $r['TotalPaid'] = floatval($r['TotalPaid']);
            $r['Remaining'] = max(0, $r['NetPayable'] - $r['TotalPaid']);
        }
        return $rows;
    }

    /** All payment entries for a trip */
    public static function getByTrip(int $tripId): array {
        global $pdo;
        $stmt = $pdo->prepare(
            "SELECT * FROM ownerpayment WHERE TripId = ? ORDER BY PaymentDate ASC"
        );
        $stmt->execute([$tripId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add a payment entry.
     * $ownerId = VehicleOwnerMaster.VehicleOwnerId, stored as OwnerId in ownerpayment
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
            $status = self::recalcStatus($tripId, $pdo);
            $pdo->commit();
            return ['status' => 'success', 'tripStatus' => $status];
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    /** Delete a payment entry and recalculate trip status */
    public static function deletePayment(int $paymentId): array {
        global $pdo;
        try {
            $pdo->beginTransaction();
            $r = $pdo->prepare("SELECT TripId FROM ownerpayment WHERE OwnerPaymentId = ?");
            $r->execute([$paymentId]);
            $tripId = (int) $r->fetchColumn();

            $pdo->prepare("DELETE FROM ownerpayment WHERE OwnerPaymentId = ?")->execute([$paymentId]);
            if ($tripId) self::recalcStatus($tripId, $pdo);

            $pdo->commit();
            return ['status' => 'success'];
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    /** Owner-wise total summary (for summary table on page) */
    public static function getOwnerSummary(): array {
        global $pdo;
        return $pdo->query("
            SELECT
                vom.VehicleOwnerId,
                vom.OwnerName, vom.MobileNo, vom.City,
                COUNT(DISTINCT t.TripId) AS TotalTrips,
                COALESCE(SUM(GREATEST(0, t.FreightAmount - t.AdvanceAmount - t.TDS)), 0) AS TotalPayable,
                COALESCE(SUM(op.Amount), 0) AS TotalPaid
            FROM VehicleOwnerMaster vom
            LEFT JOIN VehicleMaster  v   ON v.VehicleOwnerId = vom.VehicleOwnerId
            LEFT JOIN TripMaster     t   ON t.VehicleId      = v.VehicleId
            LEFT JOIN ownerpayment   op  ON op.TripId        = t.TripId
            WHERE vom.IsActive = 'Yes'
              AND (t.FreightPaymentToOwnerStatus IS NULL OR t.FreightPaymentToOwnerStatus = 'Pending'
                   OR t.TripId IS NULL)
            GROUP BY vom.VehicleOwnerId
            ORDER BY vom.OwnerName ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Active owners list for dropdowns/filters */
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
                GREATEST(0, t.FreightAmount - t.AdvanceAmount - t.TDS) AS NetPayable,
                COALESCE(SUM(op.Amount), 0) AS Paid
            FROM TripMaster t
            LEFT JOIN ownerpayment op ON t.TripId = op.TripId
            WHERE t.TripId = ?
            GROUP BY t.TripId
        ");
        $r->execute([$tripId]);
        $rv = $r->fetch(PDO::FETCH_ASSOC);

        $paid = floatval($rv['Paid']       ?? 0);
        $net  = floatval($rv['NetPayable'] ?? 0);

        $status = ($net > 0 && $paid >= $net)
            ? 'Paid'
            : ($paid > 0 ? 'PartiallyPaid' : 'Unpaid');

        $pdo->prepare("UPDATE TripMaster SET OwnerPaymentStatus = ? WHERE TripId = ?")
            ->execute([$status, $tripId]);

        return $status;
    }
}
