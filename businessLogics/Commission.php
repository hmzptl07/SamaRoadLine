<?php
/**
 * Commission.php
 * Business logic for Trip Commission tracking.
 * - Party commissions: auto-marked Received when bill fully paid
 * - Owner commissions: manual recovery from owner (PaidDirectly trips)
 */
class Commission {

    /* ══════════════════════════════════════════
       SAVE / UPDATE COMMISSION
    ══════════════════════════════════════════ */
    public static function save(PDO $pdo, int $tripId, float $amount, string $recFrom): array {
        $recFrom = in_array($recFrom, ['Party', 'Owner']) ? $recFrom : 'Party';
        try {
            $exists = $pdo->prepare("SELECT TripCommissionId FROM TripCommission WHERE TripId=?");
            $exists->execute([$tripId]);
            if ($exists->fetchColumn()) {
                $pdo->prepare("UPDATE TripCommission SET CommissionAmount=?, RecoveryFrom=? WHERE TripId=?")
                    ->execute([$amount, $recFrom, $tripId]);
            } else {
                $pdo->prepare("INSERT INTO TripCommission(TripId,CommissionAmount,RecoveryFrom) VALUES(?,?,?)")
                    ->execute([$tripId, $amount, $recFrom]);
            }
            return ['status' => 'success'];
        } catch (Exception $e) {
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    /* ══════════════════════════════════════════
       MARK SELECTED AS RECEIVED
    ══════════════════════════════════════════ */
    public static function markReceived(PDO $pdo, array $ids, string $date): array {
        if (empty($ids)) return ['status' => 'error', 'msg' => 'None selected'];
        try {
            $phs = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE TripCommission SET CommissionStatus='Received', ReceivedDate=?
                           WHERE TripCommissionId IN ($phs)")
                ->execute(array_merge([$date], $ids));
            return ['status' => 'success', 'count' => count($ids)];
        } catch (Exception $e) {
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    /* ══════════════════════════════════════════
       GET ALL TRIPS FOR COMMISSION ENTRY MODAL
    ══════════════════════════════════════════ */
    public static function getAllTripsForEntry(PDO $pdo): array {
        return $pdo->query("
            SELECT t.TripId, t.TripDate, t.FromLocation, t.ToLocation,
                   t.FreightAmount, t.TripType, t.FreightPaymentToOwnerStatus,
                   v.VehicleNumber,
                   p1.PartyName AS ConsignerName, p2.PartyName AS ConsigneeName, p3.PartyName AS AgentName,
                   b.BillNo, b.BillStatus AS RegBillStatus,
                   ab.AgentBillNo, ab.BillStatus AS AgentBillStatus,
                   tc.TripCommissionId, tc.CommissionAmount, tc.RecoveryFrom, tc.CommissionStatus
            FROM TripMaster t
            LEFT JOIN VehicleMaster v    ON t.VehicleId         = v.VehicleId
            LEFT JOIN PartyMaster p1     ON t.ConsignerId        = p1.PartyId
            LEFT JOIN PartyMaster p2     ON t.ConsigneeId        = p2.PartyId
            LEFT JOIN PartyMaster p3     ON t.AgentId            = p3.PartyId
            LEFT JOIN BillTrip bt        ON t.TripId             = bt.TripId
            LEFT JOIN Bill b             ON bt.BillId            = b.BillId
            LEFT JOIN AgentBillTrip abt  ON t.TripId             = abt.TripId
            LEFT JOIN AgentBill ab       ON abt.AgentBillId      = ab.AgentBillId
            LEFT JOIN TripCommission tc  ON t.TripId             = tc.TripId
            ORDER BY t.TripDate DESC LIMIT 500
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ══════════════════════════════════════════
       PAGE DATA — SUMMARY
    ══════════════════════════════════════════ */
    public static function getSummary(PDO $pdo): array {
        return $pdo->query("
            SELECT
                COUNT(*) AS total,
                SUM(CommissionAmount) AS total_amount,
                SUM(CASE WHEN CommissionStatus='Pending'  AND RecoveryFrom='Party' THEN CommissionAmount ELSE 0 END) AS party_pending,
                SUM(CASE WHEN CommissionStatus='Pending'  AND RecoveryFrom='Owner' THEN CommissionAmount ELSE 0 END) AS owner_pending,
                SUM(CASE WHEN CommissionStatus='Received' THEN CommissionAmount ELSE 0 END) AS received,
                SUM(CASE WHEN CommissionStatus='Pending'  THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN CommissionStatus='Received' THEN 1 ELSE 0 END) AS received_count
            FROM TripCommission
        ")->fetch(PDO::FETCH_ASSOC);
    }

    /* ══════════════════════════════════════════
       PAGE DATA — ALL COMMISSIONS WITH TRIP INFO
    ══════════════════════════════════════════ */
    public static function getAll(PDO $pdo): array {
        return $pdo->query("
            SELECT tc.*,
                   t.TripDate, t.FromLocation, t.ToLocation, t.FreightAmount, t.TripType, t.FreightPaymentToOwnerStatus,
                   v.VehicleNumber,
                   p1.PartyName AS ConsignerName, p2.PartyName AS ConsigneeName, p3.PartyName AS AgentName,
                   b.BillNo, b.BillStatus AS RegBillStatus,
                   ab.AgentBillNo, ab.BillStatus AS AgentBillStatus
            FROM TripCommission tc
            JOIN TripMaster t        ON tc.TripId            = t.TripId
            LEFT JOIN VehicleMaster v   ON t.VehicleId         = v.VehicleId
            LEFT JOIN PartyMaster p1    ON t.ConsignerId        = p1.PartyId
            LEFT JOIN PartyMaster p2    ON t.ConsigneeId        = p2.PartyId
            LEFT JOIN PartyMaster p3    ON t.AgentId            = p3.PartyId
            LEFT JOIN BillTrip bt       ON t.TripId             = bt.TripId
            LEFT JOIN Bill b            ON bt.BillId            = b.BillId
            LEFT JOIN AgentBillTrip abt ON t.TripId             = abt.TripId
            LEFT JOIN AgentBill ab      ON abt.AgentBillId      = ab.AgentBillId
            ORDER BY tc.CommissionStatus ASC, tc.CreatedDate DESC
            LIMIT 1000
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}
