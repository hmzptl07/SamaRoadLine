<?php
/**
 * PartyAdvanceLogic.php
 * Consigner advance  →  Regular Bill  (billpayment)
 * Agent advance      →  Agent Trip    (AgentPayment)
 */
class PartyAdvanceLogic {

    /* ══════════════════════════════════════════
       ADD ADVANCE
    ══════════════════════════════════════════ */
    public static function addAdvance(PDO $pdo, array $data): array {
        $partyId = intval($data['PartyId']);
        $amount  = floatval($data['Amount']);
        if ($partyId <= 0 || $amount <= 0)
            return ['status' => 'error', 'msg' => 'Invalid party or amount'];
        try {
            $pdo->prepare("
                INSERT INTO partyadvance
                    (PartyId, AdvanceDate, Amount, PaymentMode, ReferenceNo, RemainingAmount, Remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $partyId, $data['AdvanceDate'], $amount,
                $data['PaymentMode'] ?? 'Cash',
                trim($data['ReferenceNo'] ?? ''),
                $amount,
                trim($data['Remarks'] ?? ''),
            ]);
            return ['status' => 'success'];
        } catch (Exception $e) {
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    /* ══════════════════════════════════════════
       GET GROUPED BY PARTY  (one row per party)
    ══════════════════════════════════════════ */
    public static function getGroupedByParty(PDO $pdo): array {
        return $pdo->query("
            SELECT
                p.PartyId,
                p.PartyName,
                p.City,
                p.MobileNo,
                COALESCE(p.PartyType, 'Regular') AS PartyType,
                COUNT(pa.PartyAdvanceId)                                   AS TotalEntries,
                SUM(pa.Amount)                                              AS TotalReceived,
                SUM(pa.AdjustedAmount)                                      AS TotalAdjusted,
                SUM(pa.RemainingAmount)                                     AS TotalRemaining,
                SUM(CASE WHEN pa.Status = 'Open'               THEN 1 ELSE 0 END) AS OpenCount,
                SUM(CASE WHEN pa.Status = 'PartiallyAdjusted'  THEN 1 ELSE 0 END) AS PartialCount,
                SUM(CASE WHEN pa.Status = 'FullyAdjusted'      THEN 1 ELSE 0 END) AS FullCount,
                MAX(pa.AdvanceDate) AS LastDate
            FROM partyadvance pa
            JOIN PartyMaster p ON pa.PartyId = p.PartyId
            GROUP BY p.PartyId
            ORDER BY SUM(pa.RemainingAmount) DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ══════════════════════════════════════════
       PARTY LEDGER (all IN + OUT for one party)
    ══════════════════════════════════════════ */
    public static function getPartyLedger(PDO $pdo, int $partyId): array {
        /* All advance entries (IN) */
        $s1 = $pdo->prepare("
            SELECT
                pa.PartyAdvanceId AS id,
                pa.AdvanceDate    AS txn_date,
                'IN'              AS txn_type,
                pa.Amount         AS amount,
                pa.PaymentMode,
                pa.ReferenceNo,
                pa.Remarks,
                pa.Status,
                pa.AdjustedAmount,
                pa.RemainingAmount,
                NULL AS ref_label,
                NULL AS bill_type
            FROM partyadvance pa
            WHERE pa.PartyId = ?
            ORDER BY pa.AdvanceDate ASC, pa.PartyAdvanceId ASC
        ");
        $s1->execute([$partyId]);
        $advances = $s1->fetchAll(PDO::FETCH_ASSOC);

        /* All adjustments (OUT) */
        $s2 = $pdo->prepare("
            SELECT
                paa.AdjustmentId  AS id,
                paa.AdjustmentDate AS txn_date,
                'OUT'             AS txn_type,
                paa.AdjustedAmount AS amount,
                paa.AdjustmentType AS bill_type,
                paa.Remarks,
                paa.PartyAdvanceId,
                b.BillNo,
                t.TripDate,
                CONCAT(t.FromLocation, ' → ', t.ToLocation) AS route,
                v.VehicleNumber
            FROM partyadvanceadjustment paa
            JOIN partyadvance pa ON paa.PartyAdvanceId = pa.PartyAdvanceId
            LEFT JOIN bill          b ON paa.BillId      = b.BillId    AND paa.AdjustmentType = 'RegularBill'
            LEFT JOIN TripMaster    t ON paa.AgentTripId = t.TripId    AND paa.AdjustmentType = 'AgentTripPayment'
            LEFT JOIN VehicleMaster v ON t.VehicleId     = v.VehicleId
            WHERE pa.PartyId = ?
            ORDER BY paa.AdjustmentDate ASC, paa.AdjustmentId ASC
        ");
        $s2->execute([$partyId]);
        $adjustments = $s2->fetchAll(PDO::FETCH_ASSOC);

        /* Build reference label for OUT entries */
        foreach ($adjustments as &$adj) {
            if ($adj['bill_type'] === 'RegularBill')
                $adj['ref_label'] = $adj['BillNo'] ?? '—';
            else
                $adj['ref_label'] = ($adj['VehicleNumber'] ?? '') . ($adj['route'] ? ' | ' . $adj['route'] : '');
        }

        return ['advances' => $advances, 'adjustments' => $adjustments];
    }

    /* ══════════════════════════════════════════
       GET OPEN ADVANCES FOR ADJUST MODAL
    ══════════════════════════════════════════ */
    public static function getOpenAdvances(PDO $pdo, int $partyId): array {
        $s = $pdo->prepare("
            SELECT PartyAdvanceId, AdvanceDate, Amount, RemainingAmount, PaymentMode
            FROM partyadvance
            WHERE PartyId = ? AND Status != 'FullyAdjusted'
            ORDER BY AdvanceDate ASC
        ");
        $s->execute([$partyId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ══════════════════════════════════════════
       GET UNPAID REGULAR BILLS
    ══════════════════════════════════════════ */
    public static function getConsignerBills(PDO $pdo, int $partyId): array {
        $s = $pdo->prepare("
            SELECT b.BillId AS id, b.BillNo AS billno, b.BillDate AS billdate,
                   b.NetBillAmount AS netamt, COALESCE(SUM(pay.Amount),0) AS paid
            FROM Bill b
            LEFT JOIN billpayment pay ON b.BillId = pay.BillId
            WHERE b.PartyId = ? AND b.BillStatus != 'Paid'
            GROUP BY b.BillId ORDER BY b.BillDate DESC
        ");
        $s->execute([$partyId]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r)
            $r['remaining'] = max(0, floatval($r['netamt']) - floatval($r['paid']));
        return $rows;
    }

    /* ══════════════════════════════════════════
       GET UNPAID AGENT TRIPS
    ══════════════════════════════════════════ */
    public static function getAgentTrips(PDO $pdo, int $agentId): array {
        $s = $pdo->prepare("
            SELECT t.TripId AS id, t.TripDate, t.LRNo,
                   CONCAT(t.FromLocation,' → ',t.ToLocation) AS route,
                   v.VehicleNumber,
                   ROUND(
                       t.FreightAmount
                       + COALESCE(t.LabourCharge,0) + COALESCE(t.HoldingCharge,0) + COALESCE(t.OtherCharge,0)
                       - COALESCE(t.CashAdvance,0)  - COALESCE(t.OnlineAdvance,0)  - COALESCE(t.TDS,0)
                   ,2) AS netamt,
                   COALESCE(SUM(ap.Amount),0) AS paid
            FROM TripMaster t
            LEFT JOIN VehicleMaster v  ON t.VehicleId = v.VehicleId
            LEFT JOIN agentpayment  ap ON t.TripId    = ap.TripId
            WHERE t.AgentId = ? AND t.TripType = 'Agent'
            GROUP BY t.TripId
            HAVING (netamt - paid) > 0.01
            ORDER BY t.TripDate DESC
        ");
        $s->execute([$agentId]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r)
            $r['remaining'] = max(0, floatval($r['netamt']) - floatval($r['paid']));
        return $rows;
    }

    /* ══════════════════════════════════════════
       ADJUST CONSIGNER → Regular Bill
    ══════════════════════════════════════════ */
    public static function adjustConsigner(PDO $pdo, array $data): array {
        $partyId = intval($data['PartyId'] ?? 0);
        $billId  = intval($data['BillId'] ?? 0);
        $adjAmt  = floatval($data['AdjustedAmount'] ?? 0);
        $adjDate = $data['AdjustmentDate'] ?? date('Y-m-d');
        $note    = trim($data['Remarks'] ?? '');
        if ($adjAmt <= 0 || !$billId || !$partyId)
            return ['status'=>'error','msg'=>'Invalid amount or bill'];
        try {
            $pdo->beginTransaction();
            /* FIFO: deduct from oldest open advances first */
            $remaining = $adjAmt;
            $usedAdvances = self::_fifoDebit($pdo, $partyId, $adjAmt);
            foreach ($usedAdvances as [$advId, $usedAmt]) {
                $ref  = 'ADV-'.str_pad($advId,4,'0',STR_PAD_LEFT);
                $remk = 'Advance adjusted'.($note?' — '.$note:'');
                $pdo->prepare("INSERT INTO partyadvanceadjustment (PartyAdvanceId,BillId,AgentTripId,AdjustmentType,AdjustedAmount,AdjustmentDate,Remarks) VALUES (?,?,NULL,'RegularBill',?,?,?)")
                    ->execute([$advId,$billId,$usedAmt,$adjDate,$note]);
                $pdo->prepare("INSERT INTO billpayment (BillId,PaymentDate,Amount,PaymentMode,ReferenceNo,Remarks) VALUES (?,?,?,'Other',?,?)")
                    ->execute([$billId,$adjDate,$usedAmt,$ref,$remk]);
            }
            /* Update bill status */
            $r = $pdo->prepare("SELECT b.NetBillAmount, COALESCE(SUM(p.Amount),0) AS Paid FROM Bill b LEFT JOIN billpayment p ON b.BillId=p.BillId WHERE b.BillId=? GROUP BY b.BillId");
            $r->execute([$billId]); $rv=$r->fetch(PDO::FETCH_ASSOC);
            $paid=floatval($rv['Paid']); $net=floatval($rv['NetBillAmount']);
            $bs=$paid>=$net?'Paid':($paid>0?'PartiallyPaid':'Generated');
            $pdo->prepare("UPDATE Bill SET BillStatus=? WHERE BillId=?")->execute([$bs,$billId]);
            if ($bs==='Paid') $pdo->prepare("UPDATE TripMaster SET TripStatus='Closed' WHERE TripId IN (SELECT TripId FROM billtrip WHERE BillId=?)")->execute([$billId]);
            $totalRemaining = self::getPartyTotalBalance($pdo, $partyId);
            $pdo->commit();
            return ['status'=>'success','newRemaining'=>$totalRemaining];
        } catch (Exception $e) { $pdo->rollBack(); return ['status'=>'error','msg'=>$e->getMessage()]; }
    }

    /* ══════════════════════════════════════════
       ADJUST AGENT → Agent Trip
    ══════════════════════════════════════════ */
    public static function adjustAgent(PDO $pdo, array $data): array {
        $partyId = intval($data['PartyId'] ?? 0);
        $tripId  = intval($data['TripId'] ?? 0);
        $adjAmt  = floatval($data['AdjustedAmount'] ?? 0);
        $adjDate = $data['AdjustmentDate'] ?? date('Y-m-d');
        $note    = trim($data['Remarks'] ?? '');
        if ($adjAmt <= 0 || !$tripId || !$partyId)
            return ['status'=>'error','msg'=>'Invalid amount or trip'];
        try {
            $pdo->beginTransaction();
            /* FIFO: deduct from oldest open advances first */
            $usedAdvances = self::_fifoDebit($pdo, $partyId, $adjAmt);
            foreach ($usedAdvances as [$advId, $usedAmt]) {
                $ref  = 'ADV-'.str_pad($advId,4,'0',STR_PAD_LEFT);
                $remk = 'Advance adjusted'.($note?' — '.$note:'');
                $pdo->prepare("INSERT INTO partyadvanceadjustment (PartyAdvanceId,BillId,AgentTripId,AdjustmentType,AdjustedAmount,AdjustmentDate,Remarks) VALUES (?,NULL,?,'AgentTripPayment',?,?,?)")
                    ->execute([$advId,$tripId,$usedAmt,$adjDate,$note]);
                $pdo->prepare("INSERT INTO agentpayment (TripId,PaymentDate,PaymentMode,Amount,Reference,Remarks) VALUES (?,?,'Other',?,?,?)")
                    ->execute([$tripId,$adjDate,$usedAmt,$ref,$remk]);
            }
            $totalRemaining = self::getPartyTotalBalance($pdo, $partyId);
            $pdo->commit();
            return ['status'=>'success','newRemaining'=>$totalRemaining];
        } catch (Exception $e) { $pdo->rollBack(); return ['status'=>'error','msg'=>$e->getMessage()]; }
    }

    /* ══════════════════════════════════════════
       SUMMARY
    ══════════════════════════════════════════ */
    public static function getSummary(PDO $pdo): array {
        $rows  = self::getGroupedByParty($pdo);
        $cons  = array_filter($rows, fn($r) => strtolower($r['PartyType']) !== 'agent');
        $agent = array_filter($rows, fn($r) => strtolower($r['PartyType']) === 'agent');
        $s = fn($list, $col) => array_sum(array_column(array_values($list), $col));
        return [
            'parties'         => count($rows),
            'total'           => $s($rows,  'TotalReceived'),
            'remaining'       => $s($rows,  'TotalRemaining'),
            'cons_parties'    => count(array_values($cons)),
            'cons_total'      => $s($cons,  'TotalReceived'),
            'cons_remaining'  => $s($cons,  'TotalRemaining'),
            'agent_parties'   => count(array_values($agent)),
            'agent_total'     => $s($agent, 'TotalReceived'),
            'agent_remaining' => $s($agent, 'TotalRemaining'),
        ];
    }

    /**
     * FIFO: Deduct $totalAmt from party's oldest open advances first.
     * Returns array of [$advId, $usedAmt] pairs actually debited.
     */
    private static function _fifoDebit(PDO $pdo, int $partyId, float $totalAmt): array {
        /* Lock all open advances for this party, oldest first */
        $s = $pdo->prepare("
            SELECT * FROM partyadvance
            WHERE PartyId = ? AND Status != 'FullyAdjusted' AND RemainingAmount > 0
            ORDER BY AdvanceDate ASC, PartyAdvanceId ASC
            FOR UPDATE
        ");
        $s->execute([$partyId]);
        $advances = $s->fetchAll(PDO::FETCH_ASSOC);

        $totalAvail = array_sum(array_column($advances, 'RemainingAmount'));
        if ($totalAmt > $totalAvail)
            throw new Exception('Exceeds total available balance (Rs.'.number_format($totalAvail,2).')');

        $used = [];
        $leftToDeduct = $totalAmt;
        foreach ($advances as $a) {
            if ($leftToDeduct <= 0) break;
            $avail   = floatval($a['RemainingAmount']);
            $deduct  = min($avail, $leftToDeduct);
            $newAdj  = floatval($a['AdjustedAmount']) + $deduct;
            $newRem  = max(0, floatval($a['Amount']) - $newAdj);
            $status  = $newRem <= 0 ? 'FullyAdjusted' : 'PartiallyAdjusted';
            $pdo->prepare("UPDATE partyadvance SET AdjustedAmount=?,RemainingAmount=?,Status=? WHERE PartyAdvanceId=?")
                ->execute([$newAdj, $newRem, $status, $a['PartyAdvanceId']]);
            $used[] = [$a['PartyAdvanceId'], $deduct];
            $leftToDeduct -= $deduct;
        }
        return $used;
    }

    private static function getPartyTotalBalance(PDO $pdo, int $partyId): float {
        $s = $pdo->prepare("SELECT COALESCE(SUM(RemainingAmount),0) FROM partyadvance WHERE PartyId = ?");
        $s->execute([$partyId]);
        return floatval($s->fetchColumn());
    }
}
