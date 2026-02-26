<?php
/**
 * PartyAdvanceLogic.php
 * Business logic for Party Advance management:
 * recording advances received from parties and adjusting them against bills.
 */
class PartyAdvanceLogic {

    /* ══════════════════════════════════════════
       ADD ADVANCE
    ══════════════════════════════════════════ */
    public static function addAdvance(PDO $pdo, array $data): array {
        $partyId = intval($data['PartyId']);
        $date    = $data['AdvanceDate'];
        $amount  = floatval($data['Amount']);
        $mode    = $data['PaymentMode'] ?? 'Cash';
        $ref     = trim($data['ReferenceNo'] ?? '');
        $rem     = trim($data['Remarks'] ?? '');

        if ($partyId <= 0 || $amount <= 0) {
            return ['status' => 'error', 'msg' => 'Invalid party or amount'];
        }
        try {
            $pdo->prepare("INSERT INTO partyadvance(PartyId,AdvanceDate,Amount,PaymentMode,ReferenceNo,RemainingAmount,Remarks)
                           VALUES(?,?,?,?,?,?,?)")
                ->execute([$partyId, $date, $amount, $mode, $ref, $amount, $rem]);
            return ['status' => 'success'];
        } catch (Exception $e) {
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    /* ══════════════════════════════════════════
       GET UNPAID BILLS FOR PARTY
    ══════════════════════════════════════════ */
    public static function getPartyBills(PDO $pdo, int $partyId): array {
        $r1 = $pdo->prepare("
            SELECT b.BillId AS id, b.BillNo AS billno, b.BillDate AS billdate, b.NetBillAmount AS netamt,
                   COALESCE(SUM(pay.Amount),0) AS paid, 'Regular' AS billtype
            FROM Bill b LEFT JOIN billpayment pay ON b.BillId=pay.BillId
            WHERE b.PartyId=? AND b.BillStatus!='Paid' GROUP BY b.BillId");
        $r1->execute([$partyId]);
        $bills = $r1->fetchAll(PDO::FETCH_ASSOC);

        $r2 = $pdo->prepare("
            SELECT b.AgentBillId AS id, b.AgentBillNo AS billno, b.AgentBillDate AS billdate, b.NetBillAmount AS netamt,
                   COALESCE(SUM(pay.Amount),0) AS paid, 'Agent' AS billtype
            FROM AgentBill b LEFT JOIN agentbillpayment pay ON b.AgentBillId=pay.AgentBillId
            WHERE b.AgentPartyId=? AND b.BillStatus!='Paid' GROUP BY b.AgentBillId");
        $r2->execute([$partyId]);
        $bills = array_merge($bills, $r2->fetchAll(PDO::FETCH_ASSOC));

        foreach ($bills as &$b) {
            $b['remaining'] = max(0, floatval($b['netamt']) - floatval($b['paid']));
        }
        return $bills;
    }

    /* ══════════════════════════════════════════
       ADJUST ADVANCE AGAINST BILL
    ══════════════════════════════════════════ */
    public static function adjustAdvance(PDO $pdo, array $data): array {
        $advId   = intval($data['PartyAdvanceId']);
        $billId  = !empty($data['BillId'])      ? intval($data['BillId'])      : null;
        $abillId = !empty($data['AgentBillId']) ? intval($data['AgentBillId']) : null;
        $btype   = $data['BillType'] ?? 'Regular';
        $adjAmt  = floatval($data['AdjustedAmount']);
        $adjDate = $data['AdjustmentDate'] ?? date('Y-m-d');
        $adjRem  = trim($data['Remarks'] ?? '');

        if ($adjAmt <= 0) return ['status' => 'error', 'msg' => 'Amount must be > 0'];

        try {
            $pdo->beginTransaction();

            $adv = $pdo->prepare("SELECT * FROM partyadvance WHERE PartyAdvanceId=?");
            $adv->execute([$advId]);
            $a = $adv->fetch(PDO::FETCH_ASSOC);
            if (!$a) throw new Exception('Advance not found');
            if ($adjAmt > floatval($a['RemainingAmount'])) {
                throw new Exception('Amount exceeds available balance (Rs.' . number_format($a['RemainingAmount'], 2) . ')');
            }

            $pdo->prepare("INSERT INTO partyadvanceadjustment(PartyAdvanceId,BillId,AgentBillId,BillType,AdjustedAmount,AdjustmentDate,Remarks)
                           VALUES(?,?,?,?,?,?,?)")
                ->execute([$advId, $billId, $abillId, $btype, $adjAmt, $adjDate, $adjRem]);

            $newAdj = floatval($a['AdjustedAmount']) + $adjAmt;
            $newRem = floatval($a['Amount']) - $newAdj;
            $nstat  = $newRem <= 0 ? 'FullyAdjusted' : ($newAdj > 0 ? 'PartiallyAdjusted' : 'Open');
            $pdo->prepare("UPDATE partyadvance SET AdjustedAmount=?,RemainingAmount=?,Status=? WHERE PartyAdvanceId=?")
                ->execute([$newAdj, max(0, $newRem), $nstat, $advId]);

            $ref  = 'ADV-' . str_pad($advId, 4, '0', STR_PAD_LEFT);
            $remk = 'Advance adjusted' . ($adjRem ? ' — ' . $adjRem : '');

            if ($btype === 'Regular' && $billId) {
                $pdo->prepare("INSERT INTO billpayment(BillId,PaymentDate,Amount,PaymentMode,ReferenceNo,Remarks)
                               VALUES(?,?,?,'Other',?,?)")
                    ->execute([$billId, $adjDate, $adjAmt, $ref, $remk]);

                $r = $pdo->prepare("SELECT b.NetBillAmount, COALESCE(SUM(p.Amount),0) AS Paid
                                    FROM Bill b LEFT JOIN billpayment p ON b.BillId=p.BillId
                                    WHERE b.BillId=? GROUP BY b.BillId");
                $r->execute([$billId]); $rv = $r->fetch(PDO::FETCH_ASSOC);
                $paid = floatval($rv['Paid']); $net = floatval($rv['NetBillAmount']);
                $bstat = $paid >= $net ? 'Paid' : ($paid > 0 ? 'PartiallyPaid' : 'Generated');
                $pdo->prepare("UPDATE Bill SET BillStatus=? WHERE BillId=?")->execute([$bstat, $billId]);

                if ($bstat === 'Paid') {
                    $pdo->prepare("UPDATE TripCommission tc JOIN BillTrip bt ON tc.TripId=bt.TripId
                                   SET tc.CommissionStatus='Received', tc.ReceivedDate=?
                                   WHERE bt.BillId=? AND tc.CommissionStatus='Pending' AND tc.RecoveryFrom='Party'")
                        ->execute([$adjDate, $billId]);
                }

            } elseif ($btype === 'Agent' && $abillId) {
                $pdo->prepare("INSERT INTO agentbillpayment(AgentBillId,PaymentDate,Amount,PaymentMode,ReferenceNo,Remarks)
                               VALUES(?,?,?,'Other',?,?)")
                    ->execute([$abillId, $adjDate, $adjAmt, $ref, $remk]);

                $r = $pdo->prepare("SELECT b.NetBillAmount, COALESCE(SUM(p.Amount),0) AS Paid
                                    FROM AgentBill b LEFT JOIN agentbillpayment p ON b.AgentBillId=p.AgentBillId
                                    WHERE b.AgentBillId=? GROUP BY b.AgentBillId");
                $r->execute([$abillId]); $rv = $r->fetch(PDO::FETCH_ASSOC);
                $paid = floatval($rv['Paid']); $net = floatval($rv['NetBillAmount']);
                $bstat = $paid >= $net ? 'Paid' : ($paid > 0 ? 'PartiallyPaid' : 'Generated');
                $pdo->prepare("UPDATE AgentBill SET BillStatus=? WHERE AgentBillId=?")->execute([$bstat, $abillId]);
            }

            $pdo->commit();
            return ['status' => 'success', 'newRemaining' => max(0, $newRem), 'advStatus' => $nstat];

        } catch (Exception $e) {
            $pdo->rollBack();
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    /* ══════════════════════════════════════════
       GET ADJUSTMENTS FOR AN ADVANCE
    ══════════════════════════════════════════ */
    public static function getAdjustments(PDO $pdo, int $advId): array {
        $stmt = $pdo->prepare("
            SELECT paa.*, b.BillNo, ab.AgentBillNo
            FROM partyadvanceadjustment paa
            LEFT JOIN Bill b       ON paa.BillId      = b.BillId
            LEFT JOIN AgentBill ab ON paa.AgentBillId = ab.AgentBillId
            WHERE paa.PartyAdvanceId=? ORDER BY paa.AdjustmentDate ASC");
        $stmt->execute([$advId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ══════════════════════════════════════════
       PAGE DATA
    ══════════════════════════════════════════ */
    public static function getAll(PDO $pdo): array {
        return $pdo->query("
            SELECT pa.*, p.PartyName, p.City, p.MobileNo
            FROM partyadvance pa
            JOIN PartyMaster p ON pa.PartyId = p.PartyId
            ORDER BY pa.PartyAdvanceId DESC LIMIT 300
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getSummary(PDO $pdo): array {
        $rows = self::getAll($pdo);
        return [
            'total'    => array_sum(array_column($rows, 'Amount')),
            'adjusted' => array_sum(array_column($rows, 'AdjustedAmount')),
            'remaining'=> array_sum(array_column($rows, 'RemainingAmount')),
            'open'     => count(array_filter($rows, fn($a) => $a['Status'] !== 'FullyAdjusted')),
            'count'    => count($rows),
        ];
    }
}
