<?php
/**
 * BillPayment.php
 * Business logic for Regular Bill payments, Agent Bill payments,
 * and Owner Commission recovery (PaidDirectly trips).
 */
class BillPayment {

    /* ══════════════════════════════════════════
       GET PAYMENT LIST
    ══════════════════════════════════════════ */
    public static function getPayments(PDO $pdo, string $type, int $id): array {
        if ($type === 'Regular') {
            $stmt = $pdo->prepare("SELECT * FROM billpayment WHERE BillId=? ORDER BY PaymentDate ASC");
        } else {
            $stmt = $pdo->prepare("SELECT * FROM agentbillpayment WHERE AgentBillId=? ORDER BY PaymentDate ASC");
        }
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ══════════════════════════════════════════
       ADD PAYMENT
    ══════════════════════════════════════════ */
    public static function addPayment(PDO $pdo, string $type, int $id, array $data): array {
        $date   = $data['PaymentDate'];
        $amount = floatval($data['Amount']);
        $mode   = $data['PaymentMode'] ?? 'Cash';
        $ref    = trim($data['ReferenceNo'] ?? '');
        $rem    = trim($data['Remarks'] ?? '');

        if ($amount <= 0) {
            return ['status' => 'error', 'msg' => 'Amount must be > 0'];
        }

        try {
            $pdo->beginTransaction();

            if ($type === 'Regular') {
                $pdo->prepare("INSERT INTO billpayment(BillId,PaymentDate,Amount,PaymentMode,ReferenceNo,Remarks) VALUES(?,?,?,?,?,?)")
                    ->execute([$id, $date, $amount, $mode, $ref, $rem]);

                $r = $pdo->prepare("SELECT b.NetBillAmount, COALESCE(SUM(p.Amount),0) AS Paid
                                    FROM Bill b LEFT JOIN billpayment p ON b.BillId=p.BillId
                                    WHERE b.BillId=? GROUP BY b.BillId");
                $r->execute([$id]);
                $rv = $r->fetch(PDO::FETCH_ASSOC);

                $paid = floatval($rv['Paid']); $net = floatval($rv['NetBillAmount']);
                $status = $paid >= $net ? 'Paid' : ($paid > 0 ? 'PartiallyPaid' : 'Generated');
                $pdo->prepare("UPDATE Bill SET BillStatus=? WHERE BillId=?")->execute([$status, $id]);

                if ($status === 'Paid') {
                    // ══ TripStatus → 'Closed' ══
                    $pdo->prepare("UPDATE TripMaster SET TripStatus='Closed'
                                   WHERE TripId IN (SELECT TripId FROM BillTrip WHERE BillId=?)")
                        ->execute([$id]);

                    // ══ Commission auto-mark ══
                    $pdo->prepare("UPDATE TripCommission tc JOIN BillTrip bt ON tc.TripId=bt.TripId
                                   SET tc.CommissionStatus='Received', tc.ReceivedDate=?
                                   WHERE bt.BillId=? AND tc.CommissionStatus='Pending' AND tc.RecoveryFrom='Party'")
                        ->execute([$date, $id]);
                }

            } else {
                $pdo->prepare("INSERT INTO agentbillpayment(AgentBillId,PaymentDate,Amount,PaymentMode,ReferenceNo,Remarks) VALUES(?,?,?,?,?,?)")
                    ->execute([$id, $date, $amount, $mode, $ref, $rem]);

                $r = $pdo->prepare("SELECT b.NetBillAmount, COALESCE(SUM(p.Amount),0) AS Paid
                                    FROM AgentBill b LEFT JOIN agentbillpayment p ON b.AgentBillId=p.AgentBillId
                                    WHERE b.AgentBillId=? GROUP BY b.AgentBillId");
                $r->execute([$id]);
                $rv = $r->fetch(PDO::FETCH_ASSOC);

                $paid = floatval($rv['Paid']); $net = floatval($rv['NetBillAmount']);
                $status = $paid >= $net ? 'Paid' : ($paid > 0 ? 'PartiallyPaid' : 'Generated');
                $pdo->prepare("UPDATE AgentBill SET BillStatus=? WHERE AgentBillId=?")->execute([$status, $id]);

                if ($status === 'Paid') {
                    // ══ TripStatus → 'Closed' ══
                    $pdo->prepare("UPDATE TripMaster SET TripStatus='Closed'
                                   WHERE TripId IN (SELECT TripId FROM AgentBillTrip WHERE AgentBillId=?)")
                        ->execute([$id]);

                    // ══ Commission auto-mark ══
                    $pdo->prepare("UPDATE TripCommission tc JOIN AgentBillTrip abt ON tc.TripId=abt.TripId
                                   SET tc.CommissionStatus='Received', tc.ReceivedDate=?
                                   WHERE abt.AgentBillId=? AND tc.CommissionStatus='Pending' AND tc.RecoveryFrom='Party'")
                        ->execute([$date, $id]);
                }
            }

            $pdo->commit();
            return [
                'status'     => 'success',
                'billStatus' => $status,
                'paid'       => $paid,
                'net'        => $net,
                'remaining'  => max(0, $net - $paid),
                'commAuto'   => ($status === 'Paid'),
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    /* ══════════════════════════════════════════
       DELETE PAYMENT
    ══════════════════════════════════════════ */
    public static function deletePayment(PDO $pdo, string $type, int $paymentId): array {
        try {
            $pdo->beginTransaction();

            if ($type === 'Regular') {
                $bid = $pdo->prepare("SELECT BillId FROM billpayment WHERE BillPaymentId=?");
                $bid->execute([$paymentId]); $bid = $bid->fetchColumn();
                $pdo->prepare("DELETE FROM billpayment WHERE BillPaymentId=?")->execute([$paymentId]);

                $r = $pdo->prepare("SELECT b.NetBillAmount, COALESCE(SUM(p.Amount),0) AS Paid
                                    FROM Bill b LEFT JOIN billpayment p ON b.BillId=p.BillId
                                    WHERE b.BillId=? GROUP BY b.BillId");
                $r->execute([$bid]); $rv = $r->fetch(PDO::FETCH_ASSOC);
                $status = floatval($rv['Paid']) >= floatval($rv['NetBillAmount']) ? 'Paid'
                        : (floatval($rv['Paid']) > 0 ? 'PartiallyPaid' : 'Generated');
                $pdo->prepare("UPDATE Bill SET BillStatus=? WHERE BillId=?")->execute([$status, $bid]);

            } else {
                $bid = $pdo->prepare("SELECT AgentBillId FROM agentbillpayment WHERE AgentBillPaymentId=?");
                $bid->execute([$paymentId]); $bid = $bid->fetchColumn();
                $pdo->prepare("DELETE FROM agentbillpayment WHERE AgentBillPaymentId=?")->execute([$paymentId]);

                $r = $pdo->prepare("SELECT b.NetBillAmount, COALESCE(SUM(p.Amount),0) AS Paid
                                    FROM AgentBill b LEFT JOIN agentbillpayment p ON b.AgentBillId=p.AgentBillId
                                    WHERE b.AgentBillId=? GROUP BY b.AgentBillId");
                $r->execute([$bid]); $rv = $r->fetch(PDO::FETCH_ASSOC);
                $status = floatval($rv['Paid']) >= floatval($rv['NetBillAmount']) ? 'Paid'
                        : (floatval($rv['Paid']) > 0 ? 'PartiallyPaid' : 'Generated');
                $pdo->prepare("UPDATE AgentBill SET BillStatus=? WHERE AgentBillId=?")->execute([$status, $bid]);
            }

            $pdo->commit();
            return ['status' => 'success'];
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    /* ══════════════════════════════════════════
       MARK OWNER COMMISSION RECEIVED
    ══════════════════════════════════════════ */
    public static function markOwnerCommissionReceived(PDO $pdo, array $ids, string $date): array {
        if (empty($ids)) return ['status' => 'error', 'msg' => 'None selected'];
        try {
            $phs = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE TripCommission SET CommissionStatus='Received', ReceivedDate=?
                           WHERE TripCommissionId IN ($phs) AND RecoveryFrom='Owner'")
                ->execute(array_merge([$date], $ids));
            return ['status' => 'success', 'count' => count($ids)];
        } catch (Exception $e) {
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    /* ══════════════════════════════════════════
       PAGE DATA — ALL BILLS (for manage page)
    ══════════════════════════════════════════ */
   public static function getAllRegularBills(PDO $pdo): array {
    return $pdo->query("
        SELECT 
            b.BillId, 
            b.BillNo, 
            b.BillDate, 
            b.NetBillAmount, 
            b.BillStatus,
            p.PartyName, 
            p.City,

            -- Trip Count
            (
                SELECT COUNT(DISTINCT bt.TripId)
                FROM BillTrip bt
                WHERE bt.BillId = b.BillId
            ) AS TripCount,

            -- Paid Amount (NO DUPLICATION)
            (
                SELECT COALESCE(SUM(pay.Amount),0)
                FROM billpayment pay
                WHERE pay.BillId = b.BillId
            ) AS PaidAmount

        FROM Bill b
        LEFT JOIN PartyMaster p ON b.PartyId = p.PartyId

        ORDER BY b.BillId DESC
        LIMIT 200
    ")->fetchAll(PDO::FETCH_ASSOC);
}

  
    public static function getOwnerRecoveryTrips(PDO $pdo): array {
        return $pdo->query("
            SELECT t.TripId, t.TripDate, t.FromLocation, t.ToLocation, t.FreightAmount, t.TripType,
                   v.VehicleNumber,
                   p1.PartyName AS ConsignerName, p2.PartyName AS ConsigneeName, p3.PartyName AS AgentName,
                   tc.TripCommissionId, tc.CommissionAmount, tc.CommissionStatus, tc.ReceivedDate
            FROM TripMaster t
            LEFT JOIN VehicleMaster v   ON t.VehicleId   = v.VehicleId
            LEFT JOIN PartyMaster p1    ON t.ConsignerId  = p1.PartyId
            LEFT JOIN PartyMaster p2    ON t.ConsigneeId  = p2.PartyId
            LEFT JOIN PartyMaster p3    ON t.AgentId      = p3.PartyId
            LEFT JOIN TripCommission tc ON t.TripId       = tc.TripId AND tc.RecoveryFrom='Owner'
            WHERE t.FreightPaymentToOwnerStatus = 'PaidDirectly'
            ORDER BY t.TripDate DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}
