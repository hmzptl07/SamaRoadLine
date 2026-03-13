<?php
require_once __DIR__ . '/../config/database.php';

class TripRegister {

    /* ══════════════════════════════════════════
       DATE FILTER HELPER
    ══════════════════════════════════════════ */
    public static function resolveDateRange(string $preset, string $from, string $to): array {
        $today = date('Y-m-d');
        switch ($preset) {
            case 'today':
                return [$today, $today];
            case 'yesterday':
                $y = date('Y-m-d', strtotime('-1 day'));
                return [$y, $y];
            case 'thisweek':
                return [
                    date('Y-m-d', strtotime('monday this week')),
                    date('Y-m-d', strtotime('sunday this week')),
                ];
            case 'thismonth':
                return [date('Y-m-01'), date('Y-m-t')];
            case 'custom':
                return [$from, $to];
            default:
                return ['', ''];
        }
    }

    /* ══════════════════════════════════════════
       FETCH REGULAR TRIPS
    ══════════════════════════════════════════ */
    public static function getRegularTrips(string $dateFrom = '', string $dateTo = ''): array {
        global $pdo;

        $dateWhere = ($dateFrom && $dateTo) ? "AND t.TripDate BETWEEN :dfrom AND :dto" : '';

        $sql = "
            SELECT
                t.TripId, t.TripDate, t.TripStatus,
                t.FromLocation, t.ToLocation,
                t.FreightAmount,
                COALESCE(t.LabourCharge,0)  AS LabourCharge,
                COALESCE(t.HoldingCharge,0) AS HoldingCharge,
                COALESCE(t.OtherCharge,0)   AS OtherCharge,
                COALESCE(t.TDS,0)           AS TDS,
                COALESCE(t.AdvanceAmount,0)  AS AdvanceAmount,
                COALESCE(t.OnlineAdvance,0)  AS OnlineAdvance,
                COALESCE(t.CashAdvance,0)    AS CashAdvance,
                t.OwnerPaymentStatus,
                t.FreightPaymentToOwnerStatus,
                v.VehicleNumber,
                vom.VehicleOwnerId,
                vom.OwnerName,
                COALESCE(vom.MobileNo,'')   AS OwnerMobile,
                p1.PartyName                AS ConsignerName,
                t.ConsigneeName,
                b.BillNo, b.BillDate, b.BillStatus, b.BillId,
                COALESCE(b.NetBillAmount,0) AS NetBillAmount,
                COALESCE(SUM(bp.Amount),0)  AS PaidAmt,
                tc.CommissionAmount, tc.CommissionStatus, tc.RecoveryFrom
            FROM TripMaster t
            LEFT JOIN VehicleMaster      v   ON t.VehicleId     = v.VehicleId
            LEFT JOIN VehicleOwnerMaster vom ON v.VehicleOwnerId = vom.VehicleOwnerId
            LEFT JOIN PartyMaster        p1  ON t.ConsignerId    = p1.PartyId
            LEFT JOIN BillTrip           bt  ON t.TripId         = bt.TripId
            LEFT JOIN Bill               b   ON bt.BillId        = b.BillId
            LEFT JOIN billpayment        bp  ON b.BillId         = bp.BillId
            LEFT JOIN TripCommission     tc  ON t.TripId         = tc.TripId
            WHERE t.TripType = 'Regular' $dateWhere
            GROUP BY t.TripId
            ORDER BY t.TripDate DESC, t.TripId DESC
        ";

        $stmt = $pdo->prepare($sql);
        if ($dateFrom && $dateTo) {
            $stmt->bindValue(':dfrom', $dateFrom);
            $stmt->bindValue(':dto',   $dateTo);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ══════════════════════════════════════════
       FETCH AGENT TRIPS
    ══════════════════════════════════════════ */
    public static function getAgentTrips(string $dateFrom = '', string $dateTo = ''): array {
        global $pdo;

        $dateWhere = ($dateFrom && $dateTo) ? "AND t.TripDate BETWEEN :dfrom AND :dto" : '';

        $sql = "
            SELECT
                t.TripId, t.TripDate, t.TripStatus,
                t.FromLocation, t.ToLocation,
                t.FreightAmount,
                COALESCE(t.LabourCharge,0)  AS LabourCharge,
                COALESCE(t.HoldingCharge,0) AS HoldingCharge,
                COALESCE(t.OtherCharge,0)   AS OtherCharge,
                COALESCE(t.TDS,0)           AS TDS,
                COALESCE(t.AdvanceAmount,0)  AS AdvanceAmount,
                COALESCE(t.OnlineAdvance,0)  AS OnlineAdvance,
                COALESCE(t.CashAdvance,0)    AS CashAdvance,
                t.OwnerPaymentStatus,
                t.FreightPaymentToOwnerStatus,
                v.VehicleNumber,
                vom.VehicleOwnerId,
                vom.OwnerName,
                COALESCE(vom.MobileNo,'')   AS OwnerMobile,
                p1.PartyName                AS ConsignerName,
                p2.PartyName                AS ConsigneeName,
                p3.PartyName                AS AgentName,
                tc.CommissionAmount, tc.CommissionStatus, tc.RecoveryFrom
            FROM TripMaster t
            LEFT JOIN VehicleMaster      v   ON t.VehicleId     = v.VehicleId
            LEFT JOIN VehicleOwnerMaster vom ON v.VehicleOwnerId = vom.VehicleOwnerId
            LEFT JOIN PartyMaster        p1  ON t.ConsignerId    = p1.PartyId
            LEFT JOIN PartyMaster        p3  ON t.AgentId        = p3.PartyId
            LEFT JOIN TripCommission     tc  ON t.TripId         = tc.TripId
            WHERE t.TripType = 'Agent' $dateWhere
            GROUP BY t.TripId
            ORDER BY t.TripDate DESC, t.TripId DESC
        ";

        $stmt = $pdo->prepare($sql);
        if ($dateFrom && $dateTo) {
            $stmt->bindValue(':dfrom', $dateFrom);
            $stmt->bindValue(':dto',   $dateTo);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ══════════════════════════════════════════
       CALCULATE TOTALS
    ══════════════════════════════════════════ */
    public static function totals(array $trips): array {
        return [
            'cnt'     => count($trips),
            'freight' => array_sum(array_column($trips, 'FreightAmount')),
            'labour'  => array_sum(array_column($trips, 'LabourCharge')),
            'holding' => array_sum(array_column($trips, 'HoldingCharge')),
            'other'   => array_sum(array_column($trips, 'OtherCharge')),
            'tds'     => array_sum(array_column($trips, 'TDS')),
            'advance' => array_sum(array_column($trips, 'AdvanceAmount')),
            'comm'    => array_sum(array_column($trips, 'CommissionAmount')),
            'billed'  => array_sum(array_column($trips, 'NetBillAmount')),
            'paid'    => array_sum(array_column($trips, 'PaidAmt')),
        ];
    }
}
