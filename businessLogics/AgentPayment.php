<?php
require_once __DIR__ . '/../config/database.php';

class AgentPayment {

    /**
     * All Agent trips with payment summary.
     * OwnerPaidDirectly = 1 when FreightType = 'ToPay'
     */
    public static function getAllTripsWithPaymentStatus(?int $agentId = null, array $dateFilter = []): array {
        global $pdo;

        $where  = "WHERE t.TripType = 'Agent'";
        $params = [];

        if ($agentId) {
            $where .= " AND t.AgentId = :agentId";
            $params[':agentId'] = $agentId;
        }

        // Date filter
        if (!empty($dateFilter['from'])) {
            $where .= " AND t.TripDate >= :dateFrom";
            $params[':dateFrom'] = $dateFilter['from'];
        }
        if (!empty($dateFilter['to'])) {
            $where .= " AND t.TripDate <= :dateTo";
            $params[':dateTo'] = $dateFilter['to'];
        }

        $stmt = $pdo->prepare("
            SELECT
                t.TripId, t.TripDate,
                t.FromLocation, t.ToLocation,
                t.InvoiceNo, t.LRNo, t.TripStatus,
                t.FreightAmount,
                COALESCE(t.LabourCharge,  0) AS LabourCharge,
                COALESCE(t.HoldingCharge, 0) AS HoldingCharge,
                COALESCE(t.OtherCharge,   0) AS OtherCharge,
                COALESCE(t.TotalAmount,   0) AS TotalAmount,
                COALESCE(t.CashAdvance,   0) AS CashAdvance,
                COALESCE(t.OnlineAdvance, 0) AS OnlineAdvance,
                COALESCE(t.AdvanceAmount, 0) AS AdvanceAmount,
                COALESCE(t.TDS,           0) AS TDS,
                COALESCE(t.NetAmount,     0) AS NetAmount,
                t.FreightType,
                CASE WHEN t.FreightType = 'ToPay' THEN 1 ELSE 0 END AS OwnerPaidDirectly,
                v.VehicleNumber, v.VehicleName,
                p3.PartyName AS AgentName,
                p3.MobileNo  AS AgentMobile,
                t.AgentId,
                COALESCE(tc.CommissionAmount, 0)        AS CommissionAmount,
                COALESCE(SUM(ap.Amount), 0)             AS TotalPaid,
                COUNT(ap.AgentPaymentId)                AS PaymentCount
            FROM TripMaster t
            LEFT JOIN VehicleMaster  v   ON t.VehicleId = v.VehicleId
            LEFT JOIN PartyMaster    p3  ON t.AgentId   = p3.PartyId
            LEFT JOIN TripCommission tc  ON t.TripId    = tc.TripId
            LEFT JOIN agentpayment   ap  ON t.TripId    = ap.TripId
            $where
            GROUP BY t.TripId, tc.CommissionAmount
            ORDER BY t.TripDate DESC, t.TripId DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['ExtraCharges']      = floatval($r['LabourCharge'])
                                    + floatval($r['HoldingCharge'])
                                    + floatval($r['OtherCharge']);
            $r['TotalPaid']         = floatval($r['TotalPaid']);
            $r['OwnerPaidDirectly'] = intval($r['OwnerPaidDirectly']);
            $net  = floatval($r['NetAmount']);
            $paid = $r['TotalPaid'];
            $r['Balance'] = max(0, $net - $paid);

            if ($r['OwnerPaidDirectly']) {
                $r['PaymentStatus'] = 'OwnerPaid';
            } elseif ($net > 0 && $paid >= $net) {
                $r['PaymentStatus'] = 'Paid';
            } elseif ($paid > 0) {
                $r['PaymentStatus'] = 'PartiallyPaid';
            } else {
                $r['PaymentStatus'] = 'Unpaid';
            }
        }
        return $rows;
    }

    /** All payment entries for a trip */
    public static function getByTrip(int $tripId): array {
        global $pdo;
        $stmt = $pdo->prepare(
            "SELECT * FROM agentpayment WHERE TripId = ? ORDER BY PaymentDate ASC, AgentPaymentId ASC"
        );
        $stmt->execute([$tripId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Add a payment entry */
    public static function addPayment(int $tripId, array $data): array {
        global $pdo;
        try {
            $pdo->prepare("
                INSERT INTO agentpayment (TripId, PaymentDate, PaymentMode, Amount, Reference, Remarks)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([
                $tripId,
                $data['PaymentDate'],
                $data['PaymentMode'] ?? 'Cash',
                floatval($data['Amount']),
                trim($data['Reference'] ?? ''),
                trim($data['Remarks']   ?? ''),
            ]);
            return ['status' => 'success'];
        } catch (Exception $e) {
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    /** Delete a payment entry */
    public static function deletePayment(int $paymentId): array {
        global $pdo;
        try {
            $pdo->prepare("DELETE FROM agentpayment WHERE AgentPaymentId = ?")->execute([$paymentId]);
            return ['status' => 'success'];
        } catch (Exception $e) {
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    /** Agent-wise summary */
    public static function getAgentSummary(): array {
        global $pdo;
        return $pdo->query("
            SELECT
                p3.PartyId   AS AgentId,
                p3.PartyName AS AgentName,
                p3.MobileNo  AS AgentMobile,
                COUNT(DISTINCT t.TripId)          AS TotalTrips,
                COALESCE(SUM(t.NetAmount), 0)     AS TotalPayable,
                COALESCE(SUM(ap2.paid),    0)     AS TotalPaid
            FROM PartyMaster p3
            JOIN TripMaster t ON t.AgentId = p3.PartyId AND t.TripType = 'Agent'
            LEFT JOIN (
                SELECT TripId, SUM(Amount) AS paid FROM agentpayment GROUP BY TripId
            ) ap2 ON ap2.TripId = t.TripId
            GROUP BY p3.PartyId
            ORDER BY p3.PartyName ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Active agents list for filter dropdown */
    public static function getAgents(): array {
        global $pdo;
        return $pdo->query("
            SELECT DISTINCT p3.PartyId AS AgentId, p3.PartyName AS AgentName, p3.MobileNo
            FROM PartyMaster p3
            JOIN TripMaster t ON t.AgentId = p3.PartyId AND t.TripType = 'Agent'
            ORDER BY p3.PartyName ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Dashboard totals */
    public static function getDashboardTotals(): array {
        global $pdo;
        $row = $pdo->query("
            SELECT
                COUNT(DISTINCT t.TripId)                                         AS TotalTrips,
                COALESCE(SUM(t.NetAmount), 0)                                    AS TotalPayable,
                COALESCE(SUM(ap2.paid), 0)                                       AS TotalPaid,
                COALESCE(SUM(t.NetAmount) - COALESCE(SUM(ap2.paid), 0), 0)      AS TotalRemaining,
                COUNT(DISTINCT CASE WHEN (t.NetAmount - COALESCE(ap2.paid,0)) > 0.005
                                    THEN t.TripId END)                           AS PendingTrips
            FROM TripMaster t
            LEFT JOIN (
                SELECT TripId, SUM(Amount) AS paid FROM agentpayment GROUP BY TripId
            ) ap2 ON ap2.TripId = t.TripId
            WHERE t.TripType = 'Agent'
        ")->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }
}
