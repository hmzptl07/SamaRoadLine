<?php
/**
 * Dashboard.php — Complete stats for all SAMA ROADLINES modules
 */
class Dashboard {

    public static function getStats(PDO $pdo): array {
        $month = date('Y-m');

        /* ── Trips ── */
        $s = $pdo->query("
            SELECT
                COUNT(*) AS total,
                COUNT(CASE WHEN DATE_FORMAT(TripDate,'%Y-%m')='$month' THEN 1 END) AS this_month,
                COUNT(CASE WHEN TripType='Regular' THEN 1 END) AS regular_cnt,
                COUNT(CASE WHEN TripType='Agent'   THEN 1 END) AS agent_cnt,
                COUNT(CASE WHEN FreightType='ToPay' THEN 1 END) AS owner_direct,
                COUNT(CASE WHEN TripStatus='Open'       THEN 1 END) AS open_cnt,
                COUNT(CASE WHEN TripStatus='Billed'     THEN 1 END) AS billed_cnt,
                COUNT(CASE WHEN TripStatus='Completed'  THEN 1 END) AS completed_cnt,
                COALESCE(SUM(FreightAmount),0) AS total_freight
            FROM TripMaster
        ")->fetch(PDO::FETCH_ASSOC);

        /* ── Regular Bills ── */
        $bs = $pdo->query("
            SELECT
                COUNT(*) AS total,
                COALESCE(SUM(NetBillAmount),0) AS total_amt,
                COALESCE(SUM(CASE WHEN BillStatus='Generated'     THEN NetBillAmount ELSE 0 END),0) AS gen_amt,
                COALESCE(SUM(CASE WHEN BillStatus='PartiallyPaid' THEN NetBillAmount ELSE 0 END),0) AS part_amt,
                COALESCE(SUM(CASE WHEN BillStatus='Paid'          THEN NetBillAmount ELSE 0 END),0) AS paid_amt,
                COUNT(CASE WHEN BillStatus='Generated'     THEN 1 END) AS gen_cnt,
                COUNT(CASE WHEN BillStatus='PartiallyPaid' THEN 1 END) AS part_cnt,
                COUNT(CASE WHEN BillStatus='Paid'          THEN 1 END) AS paid_cnt
            FROM Bill
        ")->fetch(PDO::FETCH_ASSOC);

        /* ── Commission ── */
        $cs = $pdo->query("
            SELECT
                COALESCE(SUM(CommissionAmount),0) AS total_comm,
                COALESCE(SUM(CASE WHEN CommissionStatus='Pending'  THEN CommissionAmount ELSE 0 END),0) AS pending_comm,
                COALESCE(SUM(CASE WHEN CommissionStatus='Received' THEN CommissionAmount ELSE 0 END),0) AS received_comm,
                COALESCE(SUM(CASE WHEN RecoveryFrom='Owner' AND CommissionStatus='Pending' THEN CommissionAmount ELSE 0 END),0) AS owner_pending,
                COUNT(CASE WHEN CommissionStatus='Pending' THEN 1 END) AS pending_cnt,
                COUNT(CASE WHEN CommissionStatus='Pending' AND RecoveryFrom='Owner' THEN 1 END) AS owner_pend_cnt
            FROM TripCommission
        ")->fetch(PDO::FETCH_ASSOC);

        /* ── Owner Payments ── */
        $op = $pdo->query("
            SELECT
                COUNT(DISTINCT t.TripId) AS total_trips,
                COUNT(DISTINCT CASE WHEN t.OwnerPaymentStatus='Unpaid'        THEN t.TripId END) AS unpaid_cnt,
                COUNT(DISTINCT CASE WHEN t.OwnerPaymentStatus='PartiallyPaid' THEN t.TripId END) AS partial_cnt,
                COUNT(DISTINCT CASE WHEN t.OwnerPaymentStatus='Paid'          THEN t.TripId END) AS paid_cnt,
                COALESCE(SUM(
                    GREATEST(0,
                        t.FreightAmount
                        + COALESCE(t.LabourCharge,0)
                        + COALESCE(t.HoldingCharge,0)
                        + COALESCE(t.OtherCharge,0)
                        + COALESCE(t.TDS,0)
                        - COALESCE(tc.CommissionAmount,0)
                    )
                ),0) AS total_payable,
                COALESCE(SUM(COALESCE(op2.paid,0)),0) AS total_paid
            FROM TripMaster t
            LEFT JOIN VehicleMaster v        ON t.VehicleId = v.VehicleId
            LEFT JOIN VehicleOwnerMaster vom  ON v.VehicleOwnerId = vom.VehicleOwnerId
            LEFT JOIN TripCommission tc       ON t.TripId = tc.TripId
            LEFT JOIN (
                SELECT TripId, COALESCE(SUM(Amount),0) AS paid
                FROM ownerpayment GROUP BY TripId
            ) op2 ON t.TripId = op2.TripId
            WHERE vom.VehicleOwnerId IS NOT NULL
              AND COALESCE(t.FreightType,'Pending') != 'ToPay'
        ")->fetch(PDO::FETCH_ASSOC);

        /* ── Owner Advance ── */
        $oa = $pdo->query("
            SELECT
                COALESCE(SUM(Amount),0)          AS total,
                COALESCE(SUM(AdjustedAmount),0)  AS adjusted,
                COALESCE(SUM(RemainingAmount),0)  AS remaining,
                COUNT(CASE WHEN Status != 'FullyAdjusted' THEN 1 END) AS open_cnt
            FROM owneradvance
        ")->fetch(PDO::FETCH_ASSOC);

        /* ── Party Advance ── */
        $pa = $pdo->query("
            SELECT
                COALESCE(SUM(Amount),0)          AS total,
                COALESCE(SUM(AdjustedAmount),0)  AS adjusted,
                COALESCE(SUM(RemainingAmount),0)  AS remaining,
                COUNT(CASE WHEN Status != 'FullyAdjusted' THEN 1 END) AS open_cnt
            FROM partyadvance
        ")->fetch(PDO::FETCH_ASSOC);

        return [
            /* trips */
            'totalTrips'      => intval($s['total']),
            'monthTrips'      => intval($s['this_month']),
            'regularCnt'      => intval($s['regular_cnt']),
            'agentCnt'        => intval($s['agent_cnt']),
            'ownerPaidTrips'  => intval($s['owner_direct']),
            'tripOpen'        => intval($s['open_cnt']),
            'tripBilled'      => intval($s['billed_cnt']),
            'tripCompleted'   => intval($s['completed_cnt']),
            'totalFreight'    => floatval($s['total_freight']),
            /* bills */
            'totalBillAmt'    => floatval($bs['total_amt']),
            'pendingBillAmt'  => floatval($bs['gen_amt']) + floatval($bs['part_amt']),
            'receivedBillAmt' => floatval($bs['paid_amt']),
            'billGenAmt'      => floatval($bs['gen_amt']),
            'billPartAmt'     => floatval($bs['part_amt']),
            'billGenCnt'      => intval($bs['gen_cnt']),
            'billPartCnt'     => intval($bs['part_cnt']),
            'billPaidCnt'     => intval($bs['paid_cnt']),
            'regBillTotal'    => intval($bs['total']),
            /* commission */
            'totalComm'       => floatval($cs['total_comm']),
            'pendingComm'     => floatval($cs['pending_comm']),
            'receivedComm'    => floatval($cs['received_comm']),
            'ownerComm'       => floatval($cs['owner_pending']),
            'commPendCnt'     => intval($cs['pending_cnt']),
            'ownerCommCnt'    => intval($cs['owner_pend_cnt']),
            /* owner payment */
            'opUnpaid'        => intval($op['unpaid_cnt']),
            'opPartial'       => intval($op['partial_cnt']),
            'opPaid'          => intval($op['paid_cnt']),
            'opPayable'       => floatval($op['total_payable']),
            'opTotalPaid'     => floatval($op['total_paid']),
            'opRemaining'     => max(0, floatval($op['total_payable']) - floatval($op['total_paid'])),
            /* owner advance */
            'oaTotal'         => floatval($oa['total']),
            'oaAdjusted'      => floatval($oa['adjusted']),
            'oaRemaining'     => floatval($oa['remaining']),
            'oaOpenCnt'       => intval($oa['open_cnt']),
            /* party advance */
            'paTotal'         => floatval($pa['total']),
            'paAdjusted'      => floatval($pa['adjusted']),
            'paRemaining'     => floatval($pa['remaining']),
            'paOpenCnt'       => intval($pa['open_cnt']),
        ];
    }

    public static function getMonthlyFreight(PDO $pdo): array {
        return $pdo->query("
            SELECT DATE_FORMAT(TripDate,'%b %Y') AS mon,
                   DATE_FORMAT(TripDate,'%Y-%m') AS mon_key,
                   COALESCE(SUM(FreightAmount),0) AS freight,
                   COUNT(*) AS trips
            FROM TripMaster
            WHERE TripDate >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY mon_key, mon ORDER BY mon_key ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getRecentTrips(PDO $pdo, int $limit = 8): array {
        return $pdo->query("
            SELECT t.TripId, t.TripDate, t.TripType, t.FromLocation, t.ToLocation,
                   t.FreightAmount, t.TripStatus, t.FreightType,
                   v.VehicleNumber,
                   p1.PartyName AS ConsignerName,
                   p3.PartyName AS AgentName
            FROM TripMaster t
            LEFT JOIN VehicleMaster v ON t.VehicleId  = v.VehicleId
            LEFT JOIN PartyMaster p1  ON t.ConsignerId = p1.PartyId
            LEFT JOIN PartyMaster p3  ON t.AgentId     = p3.PartyId
            ORDER BY t.TripId DESC LIMIT $limit
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getUnpaidBills(PDO $pdo, int $limit = 6): array {
        return $pdo->query("
            SELECT b.BillId, b.BillNo, b.BillDate, b.BillStatus, b.NetBillAmount,
                   p.PartyName,
                   COALESCE(SUM(pay.Amount),0) AS PaidAmt
            FROM Bill b
            LEFT JOIN PartyMaster p   ON b.PartyId = p.PartyId
            LEFT JOIN billpayment pay  ON b.BillId  = pay.BillId
            WHERE b.BillStatus != 'Paid'
            GROUP BY b.BillId ORDER BY b.BillDate DESC LIMIT $limit
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getCommissionPendingParty(PDO $pdo, int $limit = 5): array {
        return $pdo->query("
            SELECT tc.TripId, tc.CommissionAmount, t.FromLocation, t.ToLocation, t.TripDate,
                   p1.PartyName AS ConsignerName
            FROM TripCommission tc
            JOIN TripMaster t    ON tc.TripId    = t.TripId
            LEFT JOIN PartyMaster p1 ON t.ConsignerId = p1.PartyId
            WHERE tc.CommissionStatus='Pending' AND tc.RecoveryFrom='Party'
            ORDER BY tc.CreatedDate DESC LIMIT $limit
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getOwnerPendingTrips(PDO $pdo, int $limit = 5): array {
        return $pdo->query("
            SELECT t.TripId, t.TripDate, t.FromLocation, t.ToLocation,
                   t.OwnerPaymentStatus, v.VehicleNumber, vom.OwnerName,
                   GREATEST(0,
                       t.FreightAmount
                       + COALESCE(t.LabourCharge,0) + COALESCE(t.HoldingCharge,0)
                       + COALESCE(t.OtherCharge,0)  + COALESCE(t.TDS,0)
                       - COALESCE(tc.CommissionAmount,0)
                   ) AS NetPayable,
                   COALESCE(op.paid,0) AS TotalPaid
            FROM TripMaster t
            LEFT JOIN VehicleMaster v       ON t.VehicleId     = v.VehicleId
            LEFT JOIN VehicleOwnerMaster vom ON v.VehicleOwnerId= vom.VehicleOwnerId
            LEFT JOIN TripCommission tc      ON t.TripId        = tc.TripId
            LEFT JOIN (
                SELECT TripId, SUM(Amount) AS paid FROM ownerpayment GROUP BY TripId
            ) op ON t.TripId = op.TripId
            WHERE t.OwnerPaymentStatus != 'Paid'
              AND vom.VehicleOwnerId IS NOT NULL
              AND COALESCE(t.FreightType,'Pending') != 'ToPay'
            ORDER BY t.TripDate DESC LIMIT $limit
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}
