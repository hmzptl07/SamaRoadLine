<?php
/**
 * Dashboard.php
 * Business logic for Dashboard stats, charts, and quick-view data.
 */
class Dashboard {

    public static function getStats(PDO $pdo): array {
        $month = date('Y-m');

        // Trips
        $s = $pdo->query("
            SELECT COUNT(*) AS total,
                   COUNT(CASE WHEN DATE_FORMAT(TripDate,'%Y-%m')='$month' THEN 1 END) AS this_month
            FROM TripMaster")->fetch(PDO::FETCH_ASSOC);

        $ownerPaid = $pdo->query("SELECT COUNT(*) FROM TripMaster WHERE FreightPaymentToOwnerStatus='PaidDirectly'")->fetchColumn();

        // Regular Bills
        $bs = $pdo->query("
            SELECT COUNT(*) AS total,
                   SUM(NetBillAmount) AS total_amt,
                   SUM(CASE WHEN BillStatus='Generated'     THEN NetBillAmount ELSE 0 END) AS pending_amt,
                   SUM(CASE WHEN BillStatus='PartiallyPaid' THEN NetBillAmount ELSE 0 END) AS partial_amt,
                   SUM(CASE WHEN BillStatus='Paid'          THEN NetBillAmount ELSE 0 END) AS paid_amt,
                   SUM(CASE WHEN DATE_FORMAT(BillDate,'%Y-%m')='$month' THEN NetBillAmount ELSE 0 END) AS month_amt
            FROM Bill")->fetch(PDO::FETCH_ASSOC);

        // Commission
        $cs = $pdo->query("
            SELECT SUM(CommissionAmount) AS total_comm,
                   SUM(CASE WHEN CommissionStatus='Pending'  THEN CommissionAmount ELSE 0 END) AS pending_comm,
                   SUM(CASE WHEN CommissionStatus='Received' THEN CommissionAmount ELSE 0 END) AS received_comm,
                   SUM(CASE WHEN RecoveryFrom='Owner' AND CommissionStatus='Pending' THEN CommissionAmount ELSE 0 END) AS owner_pending
            FROM TripCommission")->fetch(PDO::FETCH_ASSOC);

        return [
            'totalTrips'      => intval($s['total']),
            'monthTrips'      => intval($s['this_month']),
            'ownerPaidTrips'  => intval($ownerPaid),
            'totalBillAmt'    => floatval($bs['total_amt'] ?? 0),
            'pendingBillAmt'  => floatval($bs['pending_amt'] ?? 0) + floatval($bs['partial_amt'] ?? 0),
            'receivedBillAmt' => floatval($bs['paid_amt'] ?? 0),
            'regBillTotal'    => intval($bs['total']),
            'totalComm'       => floatval($cs['total_comm'] ?? 0),
            'pendingComm'     => floatval($cs['pending_comm'] ?? 0),
            'ownerComm'       => floatval($cs['owner_pending'] ?? 0),
        ];
    }

    public static function getMonthlyFreight(PDO $pdo): array {
        return $pdo->query("
            SELECT DATE_FORMAT(TripDate,'%b %Y') AS mon,
                   DATE_FORMAT(TripDate,'%Y-%m') AS mon_key,
                   SUM(FreightAmount) AS freight
            FROM TripMaster
            WHERE TripDate >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY mon_key, mon ORDER BY mon_key ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getRecentTrips(PDO $pdo, int $limit = 8): array {
        return $pdo->query("
            SELECT t.TripId, t.TripDate, t.TripType, t.FromLocation, t.ToLocation,
                   t.FreightAmount, t.TripStatus, t.FreightPaymentToOwnerStatus,
                   v.VehicleNumber,
                   p1.PartyName AS ConsignerName, p2.PartyName AS ConsigneeName, p3.PartyName AS AgentName
            FROM TripMaster t
            LEFT JOIN VehicleMaster v ON t.VehicleId   = v.VehicleId
            LEFT JOIN PartyMaster p1  ON t.ConsignerId  = p1.PartyId
            LEFT JOIN PartyMaster p2  ON t.ConsigneeId  = p2.PartyId
            LEFT JOIN PartyMaster p3  ON t.AgentId      = p3.PartyId
            ORDER BY t.TripId DESC LIMIT $limit
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getUnpaidBills(PDO $pdo, int $limit = 5): array {
        return $pdo->query("
            SELECT b.BillId, b.BillNo, b.BillDate, b.BillStatus, b.NetBillAmount,
                   p.PartyName,
                   COALESCE(SUM(pay.Amount),0) AS PaidAmt
            FROM Bill b
            LEFT JOIN PartyMaster p   ON b.PartyId = p.PartyId
            LEFT JOIN billpayment pay ON b.BillId   = pay.BillId
            WHERE b.BillStatus != 'Paid'
            GROUP BY b.BillId ORDER BY b.BillId DESC LIMIT $limit
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getCommissionPendingParty(PDO $pdo, int $limit = 5): array {
        return $pdo->query("
            SELECT tc.TripId, tc.CommissionAmount, t.FromLocation, t.ToLocation, t.TripDate,
                   p1.PartyName AS ConsignerName, p2.PartyName AS ConsigneeName
            FROM TripCommission tc
            JOIN TripMaster t ON tc.TripId = t.TripId
            LEFT JOIN PartyMaster p1 ON t.ConsignerId = p1.PartyId
            LEFT JOIN PartyMaster p2 ON t.ConsigneeId = p2.PartyId
            WHERE tc.CommissionStatus='Pending' AND tc.RecoveryFrom='Party'
            ORDER BY tc.CreatedDate DESC LIMIT $limit
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}
