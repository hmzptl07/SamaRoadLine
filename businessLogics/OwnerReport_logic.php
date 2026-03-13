<?php
require_once __DIR__ . '/../config/database.php';

class OwnerReport {

    /* ══════════════════════════════════
       DATE RANGE RESOLVER
    ══════════════════════════════════ */
    public static function resolveDateRange(string $preset, string $from, string $to): array {
        $today = date('Y-m-d');
        switch ($preset) {
            case 'today':     return [$today, $today];
            case 'yesterday': $y = date('Y-m-d', strtotime('-1 day')); return [$y, $y];
            case 'thisweek':  return [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))];
            case 'thismonth': return [date('Y-m-01'), date('Y-m-t')];
            case 'custom':    return [$from, $to];
            default:          return ['', ''];
        }
    }

    /* ══════════════════════════════════
       ALL OWNERS LIST (for dropdown)
       — only owners who have at least one trip
    ══════════════════════════════════ */
    public static function getOwnerList(): array {
        global $pdo;
        $stmt = $pdo->query("
            SELECT DISTINCT vom.VehicleOwnerId, vom.OwnerName
            FROM VehicleOwnerMaster vom
            INNER JOIN VehicleMaster vm  ON vm.VehicleOwnerId = vom.VehicleOwnerId
            INNER JOIN TripMaster    t   ON t.VehicleId       = vm.VehicleId
            ORDER BY vom.OwnerName ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ══════════════════════════════════
       ALL TRIPS FOR ONE OWNER
       (Regular + Agent combined)
       Owner Payable = Total - Commission
    ══════════════════════════════════ */
    public static function getOwnerTrips(int $ownerId, string $dateFrom = '', string $dateTo = ''): array {
        global $pdo;
        $dw = ($dateFrom && $dateTo) ? "AND t.TripDate BETWEEN :dfrom AND :dto" : '';

        $sql = "
            SELECT
                t.TripId,
                t.TripDate,
                t.TripType,
                t.TripStatus,
                t.FromLocation,
                t.ToLocation,
                t.FreightAmount,
                COALESCE(t.LabourCharge,0)              AS LabourCharge,
                COALESCE(t.HoldingCharge,0)             AS HoldingCharge,
                COALESCE(t.OtherCharge,0)               AS OtherCharge,
                COALESCE(t.TDS,0)                       AS TDS,
                COALESCE(t.AdvanceAmount,0)             AS AdvanceAmount,
                COALESCE(t.OnlineAdvance,0)             AS OnlineAdvance,
                COALESCE(t.CashAdvance,0)               AS CashAdvance,
                COALESCE(t.OwnerPaymentStatus,'Unpaid') AS OwnerPaymentStatus,
                COALESCE(t.FreightPaymentToOwnerStatus,'') AS DirectPayStatus,
                vm.VehicleNumber,
                vom.VehicleOwnerId,
                vom.OwnerName,
                COALESCE(tc.CommissionAmount,0)         AS CommissionAmount,
                COALESCE(op.TotalPaid,0)                AS TotalPaid
            FROM TripMaster t
            INNER JOIN VehicleMaster     vm  ON t.VehicleId       = vm.VehicleId
            INNER JOIN VehicleOwnerMaster vom ON vm.VehicleOwnerId = vom.VehicleOwnerId
            LEFT JOIN  TripCommission    tc  ON t.TripId           = tc.TripId
            LEFT JOIN (
                SELECT TripId, COALESCE(SUM(Amount),0) AS TotalPaid
                FROM ownerpayment
                GROUP BY TripId
            ) op ON op.TripId = t.TripId
            WHERE vom.VehicleOwnerId = :oid
              $dw
            ORDER BY t.TripDate DESC, t.TripId DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':oid', $ownerId, PDO::PARAM_INT);
        if ($dateFrom && $dateTo) {
            $stmt->bindValue(':dfrom', $dateFrom);
            $stmt->bindValue(':dto',   $dateTo);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ══════════════════════════════════
       TOTALS
       ownerPayable = total - commission
    ══════════════════════════════════ */
    public static function totals(array $trips): array {
        $freight = $labour = $holding = $other = $comm = $paid = 0;
        foreach ($trips as $t) {
            $freight += floatval($t['FreightAmount']);
            $labour  += floatval($t['LabourCharge']);
            $holding += floatval($t['HoldingCharge']);
            $other   += floatval($t['OtherCharge']);
            $comm    += floatval($t['CommissionAmount']);
            $paid    += floatval($t['TotalPaid']);
        }
        $total        = $freight + $labour + $holding + $other;
        $ownerPayable = $total - $comm;
        return [
            'cnt'          => count($trips),
            'freight'      => $freight,
            'labour'       => $labour,
            'holding'      => $holding,
            'other'        => $other,
            'total'        => $total,
            'comm'         => $comm,
            'ownerPayable' => $ownerPayable,
            'paid'         => $paid,
            'balance'      => max(0, $ownerPayable - $paid),
        ];
    }
}
