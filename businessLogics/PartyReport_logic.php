<?php
require_once __DIR__ . '/../config/database.php';

class PartyReport {

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
       ALL CONSIGNERS LIST (for dropdown)
    ══════════════════════════════════ */
    public static function getConsignerList(): array {
        global $pdo;
        $stmt = $pdo->query("
            SELECT DISTINCT p.PartyId, p.PartyName
            FROM PartyMaster p
            INNER JOIN TripMaster t ON t.ConsignerId = p.PartyId AND t.TripType = 'Regular'
            ORDER BY p.PartyName ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ══════════════════════════════════
       ALL AGENTS LIST (for dropdown)
    ══════════════════════════════════ */
    public static function getAgentList(): array {
        global $pdo;
        $stmt = $pdo->query("
            SELECT DISTINCT p.PartyId, p.PartyName
            FROM PartyMaster p
            INNER JOIN TripMaster t ON t.AgentId = p.PartyId AND t.TripType = 'Agent'
            ORDER BY p.PartyName ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ══════════════════════════════════
       TRIPS FOR ONE CONSIGNER
    ══════════════════════════════════ */
    public static function getConsignerTrips(int $partyId, string $dateFrom = '', string $dateTo = ''): array {
        global $pdo;
        $dw = ($dateFrom && $dateTo) ? "AND t.TripDate BETWEEN :dfrom AND :dto" : '';

        $sql = "
            SELECT
                t.TripId,
                t.TripDate,
                t.TripStatus,
                t.FromLocation,
                t.ToLocation,
                t.FreightAmount,
                COALESCE(t.LabourCharge,0)      AS LabourCharge,
                COALESCE(t.HoldingCharge,0)     AS HoldingCharge,
                COALESCE(t.OtherCharge,0)       AS OtherCharge,
                COALESCE(t.TDS,0)               AS TDS,
                COALESCE(t.AdvanceAmount,0)     AS AdvanceAmount,
                p1.PartyName                    AS ConsignerName,
                b.BillNo,
                b.BillStatus,
                b.BillId,
                COALESCE(b.NetBillAmount,0)     AS BillAmount,
                COALESCE((
                    SELECT SUM(pay.Amount) FROM billpayment pay WHERE pay.BillId = b.BillId
                ),0)                            AS ReceivedAmount,
                COALESCE(tc.CommissionAmount,0) AS CommissionAmount
            FROM TripMaster t
            LEFT JOIN PartyMaster    p1  ON t.ConsignerId = p1.PartyId
            LEFT JOIN BillTrip       bt  ON t.TripId      = bt.TripId
            LEFT JOIN Bill           b   ON bt.BillId     = b.BillId
            LEFT JOIN TripCommission tc  ON t.TripId      = tc.TripId
            WHERE t.TripType = 'Regular'
              AND t.ConsignerId = :pid
              $dw
            ORDER BY t.TripDate DESC, t.TripId DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':pid', $partyId, PDO::PARAM_INT);
        if ($dateFrom && $dateTo) { $stmt->bindValue(':dfrom', $dateFrom); $stmt->bindValue(':dto', $dateTo); }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ══════════════════════════════════
       TRIPS FOR ONE AGENT
    ══════════════════════════════════ */
    public static function getAgentTrips(int $agentId, string $dateFrom = '', string $dateTo = ''): array {
        global $pdo;
        $dw = ($dateFrom && $dateTo) ? "AND t.TripDate BETWEEN :dfrom AND :dto" : '';

        $sql = "
            SELECT
                t.TripId,
                t.TripDate,
                t.TripStatus,
                t.FromLocation,
                t.ToLocation,
                t.FreightAmount,
                COALESCE(t.LabourCharge,0)      AS LabourCharge,
                COALESCE(t.HoldingCharge,0)     AS HoldingCharge,
                COALESCE(t.OtherCharge,0)       AS OtherCharge,
                COALESCE(t.TDS,0)               AS TDS,
                COALESCE(t.AdvanceAmount,0)     AS AdvanceAmount,
                p3.PartyName                    AS AgentName,
                COALESCE(tc.CommissionAmount,0) AS CommissionAmount
            FROM TripMaster t
            LEFT JOIN PartyMaster    p3  ON t.AgentId = p3.PartyId
            LEFT JOIN TripCommission tc  ON t.TripId  = tc.TripId
            WHERE t.TripType = 'Agent'
              AND t.AgentId = :pid
              $dw
            ORDER BY t.TripDate DESC, t.TripId DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':pid', $agentId, PDO::PARAM_INT);
        if ($dateFrom && $dateTo) { $stmt->bindValue(':dfrom', $dateFrom); $stmt->bindValue(':dto', $dateTo); }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ══════════════════════════════════
       TOTALS FOR TRIPS ARRAY
    ══════════════════════════════════ */
    public static function totals(array $trips): array {
        $freight = $labour = $holding = $other = $tds = $adv = $comm = $bill = $received = 0;
        foreach ($trips as $t) {
            $freight  += floatval($t['FreightAmount']);
            $labour   += floatval($t['LabourCharge']);
            $holding  += floatval($t['HoldingCharge']);
            $other    += floatval($t['OtherCharge']);
            $tds      += floatval($t['TDS']);
            $adv      += floatval($t['AdvanceAmount']);
            $comm     += floatval($t['CommissionAmount']);
            $bill     += floatval($t['BillAmount'] ?? 0);
            $received += floatval($t['ReceivedAmount'] ?? 0);
        }
        $total = $freight + $labour + $holding + $other;
        return [
            'cnt'      => count($trips),
            'freight'  => $freight,
            'labour'   => $labour,
            'holding'  => $holding,
            'other'    => $other,
            'total'    => $total,
            'adv'      => $adv,
            'tds'      => $tds,
            'net'      => $total - $adv - $tds,
            'comm'     => $comm,
            'bill'     => $bill,
            'received' => $received,
            'pending'  => max(0, $bill - $received),  // billed but not yet received
        ];
    }
}
