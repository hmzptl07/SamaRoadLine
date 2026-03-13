<?php
require_once __DIR__ . '/../config/database.php';

class RegularTrip {

    private static function baseSelect(): string {
        return "
            SELECT t.*,
                   p1.PartyName AS ConsignerName,
                   p1.MobileNo  AS ConsignerMobile,
                   p1.City      AS ConsignerCity,
                   p1.Address   AS ConsignerAddress,
                   v.VehicleNumber, v.VehicleName,
                   tc.CommissionAmount, tc.RecoveryFrom AS CommRecoveryFrom,
                   tc.CommissionStatus,
                   tv.VasuliAmount, tv.RecoverFrom AS VasuliRecoverFrom,
                   tv.VasuliStatus
            FROM TripMaster t
            LEFT JOIN PartyMaster p1     ON t.ConsignerId = p1.PartyId
            LEFT JOIN VehicleMaster v    ON t.VehicleId   = v.VehicleId
            LEFT JOIN tripcommission tc  ON t.TripId      = tc.TripId
            LEFT JOIN tripvasuli tv      ON t.TripId      = tv.TripId";
    }

    public static function getAll(): array {
        global $pdo;
        $stmt = $pdo->prepare(
            self::baseSelect() . " 
            WHERE t.TripType = 'Regular' 
            ORDER BY t.TripDate DESC, t.TripId DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getById(int $id): ?array {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT * FROM TripMaster 
            WHERE TripId = ? AND TripType = 'Regular'
        ");
        $stmt->execute([$id]);

        $trip = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$trip) return null;

        $trip['Materials'] = self::getMaterials($id);

        $comm = self::getCommission($id);
        $trip['CommissionAmount'] = $comm['CommissionAmount'] ?? 0;
        $trip['RecoveryFrom']     = $comm['RecoveryFrom']     ?? 'Party';

        $vasuli = self::getVasuli($id);
        $trip['VasuliAmount']  = $vasuli['VasuliAmount']  ?? '';
        $trip['RecoverFrom']   = $vasuli['RecoverFrom']   ?? 'Other';
        $trip['VasuliStatus']  = $vasuli['VasuliStatus']  ?? 'Pending';
        $trip['ReceivedDate']  = $vasuli['ReceivedDate']  ?? '';

        return $trip;
    }

    public static function getMaterials(int $tripId): array {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM TripMaterial WHERE TripId = ?");
        $stmt->execute([$tripId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getCommission(int $tripId) {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT CommissionAmount, RecoveryFrom 
            FROM TripCommission 
            WHERE TripId = ?
        ");
        $stmt->execute([$tripId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function getVasuli(int $tripId) {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT VasuliAmount, RecoverFrom, VasuliStatus, ReceivedDate
            FROM tripvasuli
            WHERE TripId = ?
        ");
        $stmt->execute([$tripId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ========================= INSERT =========================

    public static function insert(array $data) {
        global $pdo;

        try {
            $pdo->beginTransaction();

            $cashAdv   = floatval($data['CashAdvance']   ?? 0);
            $onlineAdv = floatval($data['OnlineAdvance'] ?? 0);
            $advance   = $cashAdv + $onlineAdv;

            $stmt = $pdo->prepare("
                INSERT INTO TripMaster (
                    TripDate, TripType, VehicleId, ConsignerId,
                    AppointedByPartyType, FromLocation, ToLocation, InvoiceNo, InvoiceDate,
                    ConsigneeName, ConsigneeContactNo, ConsigneeCity, ConsigneeAddress,
                    DriverName, DriverContactNo, DriverAadharNo, DriverAddress,
                    MaterialTotalValue, FreightAmount,
                    LabourCharge, HoldingCharge, OtherCharge, OtherChargeNote,
                    CashAdvance, OnlineAdvance, AdvanceAmount, TDS,
                    TotalAmount, NetAmount, FreightPaymentToOwnerStatus, Remarks, TripStatus
                ) VALUES (
                    :TripDate, 'Regular', :VehicleId, :ConsignerId,
                    'Consigner', :FromLocation, :ToLocation, :InvoiceNo, :InvoiceDate,
                    :ConsigneeName, :ConsigneeContactNo, :ConsigneeCity, :ConsigneeAddress,
                    :DriverName, :DriverContactNo, :DriverAadharNo, :DriverAddress,
                    :MaterialTotalValue, :FreightAmount,
                    :LabourCharge, :HoldingCharge, :OtherCharge, :OtherChargeNote,
                    :CashAdvance, :OnlineAdvance, :AdvanceAmount, :TDS,
                    :TotalAmount, :NetAmount, :FreightPaymentToOwnerStatus, :Remarks, 'Open'
                )
            ");

            $stmt->execute([
                ':TripDate' => $data['TripDate'],
                ':VehicleId' => $data['VehicleId'] ?: null,
                ':ConsignerId' => $data['ConsignerId'] ?: null,
                ':FromLocation' => $data['FromLocation'] ?? '',
                ':ToLocation' => $data['ToLocation'] ?? '',
                ':InvoiceNo' => $data['InvoiceNo'] ?? '',
                ':InvoiceDate' => !empty($data['InvoiceDate']) ? $data['InvoiceDate'] : null,
                ':ConsigneeName' => $data['ConsigneeName'] ?? '',
                ':ConsigneeContactNo' => $data['ConsigneeContactNo'] ?? '',
                ':ConsigneeCity' => $data['ConsigneeCity'] ?? '',
                ':ConsigneeAddress' => $data['ConsigneeAddress'] ?? '',
                ':DriverName' => $data['DriverName'] ?? '',
                ':DriverContactNo' => $data['DriverContactNo'] ?? '',
                ':DriverAadharNo' => $data['DriverAadharNo'] ?? '',
                ':DriverAddress' => $data['DriverAddress'] ?? '',
                ':MaterialTotalValue' => floatval($data['MaterialTotalValue'] ?? 0),
                ':FreightAmount' => floatval($data['FreightAmount'] ?? 0),
                ':LabourCharge' => floatval($data['LabourCharge'] ?? 0),
                ':HoldingCharge' => floatval($data['HoldingCharge'] ?? 0),
                ':OtherCharge' => floatval($data['OtherCharge'] ?? 0),
                ':OtherChargeNote' => $data['OtherChargeNote'] ?? '',
                ':CashAdvance' => $cashAdv,
                ':OnlineAdvance' => $onlineAdv,
                ':AdvanceAmount' => $advance,
                ':TDS' => floatval($data['TDS'] ?? 0),
                ':TotalAmount' => floatval($data['TotalAmount'] ?? 0),
                ':NetAmount' => floatval($data['NetAmount'] ?? 0),
                ':FreightPaymentToOwnerStatus' => $data['FreightPaymentToOwnerStatus'] ?? 'Pending',
                ':Remarks' => $data['Remarks'] ?? '',
            ]);

            $tripId = $pdo->lastInsertId();

            // Materials
            if (!empty($data['MaterialName'])) {
                $matStmt = $pdo->prepare("
                    INSERT INTO TripMaterial
                    (TripId, MaterialName, MaterialType, Weight, Quantity, UnitType, WeightPerUnit, TotalWeight, Rate, Amount)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($data['MaterialName'] as $k => $name) {
                    if (trim($name) === '') continue;

                    $type = $data['MaterialType'][$k] ?? 'Loose';
                    $r    = floatval($data['Rate'][$k] ?? 0);

                    if ($type === 'Units') {
                        $qty = floatval($data['Quantity'][$k] ?? 0);
                        $wpu = floatval($data['WeightPerUnit'][$k] ?? 0);
                        $tw  = $qty * $wpu;
                        $amt = $tw * $r;
                        $matStmt->execute([$tripId, trim($name), 'Units', 0, $qty, $data['UnitType'][$k] ?? '', $wpu, $tw, $r, $amt]);
                    } else {
                        $w   = floatval($data['Weight'][$k] ?? 0);
                        $matStmt->execute([$tripId, trim($name), 'Loose', $w, 0, null, 0, 0, $r, $w * $r]);
                    }
                }
            }

            // Commission (Always Create)
            $commission = floatval($data['CommissionAmount'] ?? 0);

            $rf = ($data['FreightPaymentToOwnerStatus'] ?? 'Pending') === 'PaidDirectly'
                ? 'Owner'
                : 'Party';

            $pdo->prepare("
                INSERT INTO TripCommission (TripId, CommissionAmount, RecoveryFrom)
                VALUES (?, ?, ?)
            ")->execute([$tripId, $commission, $rf]);

            // Vasuli (optional)
            $vasuliAmt = !empty($data['VasuliAmount']) ? floatval($data['VasuliAmount']) : null;
            if ($vasuliAmt !== null && $vasuliAmt > 0) {
                $vrf = ($data['VasuliRecoverFrom'] ?? '') === 'Owner' ? 'Owner' : 'Other';
                $pdo->prepare("
                    INSERT INTO tripvasuli (TripId, VasuliAmount, RecoverFrom)
                    VALUES (?, ?, ?)
                ")->execute([$tripId, $vasuliAmt, $vrf]);
            }

            $pdo->commit();
            return $tripId;

        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }

    // ========================= UPDATE =========================

    public static function update(int $id, array $data) {
        global $pdo;

        try {
            $pdo->beginTransaction();

            $cashAdv   = floatval($data['CashAdvance'] ?? 0);
            $onlineAdv = floatval($data['OnlineAdvance'] ?? 0);
            $advance   = $cashAdv + $onlineAdv;

            $pdo->prepare("
                UPDATE TripMaster SET
                    TripDate = :TripDate,
                    VehicleId = :VehicleId,
                    ConsignerId = :ConsignerId,
                    FromLocation = :FromLocation,
                    ToLocation = :ToLocation,
                    InvoiceNo = :InvoiceNo,
                    InvoiceDate = :InvoiceDate,
                    ConsigneeName = :ConsigneeName,
                    ConsigneeContactNo = :ConsigneeContactNo,
                    ConsigneeCity = :ConsigneeCity,
                    ConsigneeAddress = :ConsigneeAddress,
                    DriverName = :DriverName,
                    DriverContactNo = :DriverContactNo,
                    DriverAadharNo = :DriverAadharNo,
                    DriverAddress = :DriverAddress,
                    MaterialTotalValue = :MaterialTotalValue,
                    FreightAmount = :FreightAmount,
                    LabourCharge = :LabourCharge,
                    HoldingCharge = :HoldingCharge,
                    OtherCharge = :OtherCharge,
                    OtherChargeNote = :OtherChargeNote,
                    CashAdvance = :CashAdvance,
                    OnlineAdvance = :OnlineAdvance,
                    AdvanceAmount = :AdvanceAmount,
                    TDS = :TDS,
                    TotalAmount = :TotalAmount,
                    NetAmount = :NetAmount,
                    FreightPaymentToOwnerStatus = :FreightPaymentToOwnerStatus,
                    Remarks = :Remarks
                WHERE TripId = :TripId AND TripType = 'Regular'
            ")->execute([
                ':TripDate' => $data['TripDate'],
                ':VehicleId' => $data['VehicleId'] ?: null,
                ':ConsignerId' => $data['ConsignerId'] ?: null,
                ':FromLocation' => $data['FromLocation'] ?? '',
                ':ToLocation' => $data['ToLocation'] ?? '',
                ':InvoiceNo' => $data['InvoiceNo'] ?? '',
                ':InvoiceDate' => !empty($data['InvoiceDate']) ? $data['InvoiceDate'] : null,
                ':ConsigneeName' => $data['ConsigneeName'] ?? '',
                ':ConsigneeContactNo' => $data['ConsigneeContactNo'] ?? '',
                ':ConsigneeCity' => $data['ConsigneeCity'] ?? '',
                ':ConsigneeAddress' => $data['ConsigneeAddress'] ?? '',
                ':DriverName' => $data['DriverName'] ?? '',
                ':DriverContactNo' => $data['DriverContactNo'] ?? '',
                ':DriverAadharNo' => $data['DriverAadharNo'] ?? '',
                ':DriverAddress' => $data['DriverAddress'] ?? '',
                ':MaterialTotalValue' => floatval($data['MaterialTotalValue'] ?? 0),
                ':FreightAmount' => floatval($data['FreightAmount'] ?? 0),
                ':LabourCharge' => floatval($data['LabourCharge'] ?? 0),
                ':HoldingCharge' => floatval($data['HoldingCharge'] ?? 0),
                ':OtherCharge' => floatval($data['OtherCharge'] ?? 0),
                ':OtherChargeNote' => $data['OtherChargeNote'] ?? '',
                ':CashAdvance' => $cashAdv,
                ':OnlineAdvance' => $onlineAdv,
                ':AdvanceAmount' => $advance,
                ':TDS' => floatval($data['TDS'] ?? 0),
                ':TotalAmount' => floatval($data['TotalAmount'] ?? 0),
                ':NetAmount' => floatval($data['NetAmount'] ?? 0),
                ':FreightPaymentToOwnerStatus' => $data['FreightPaymentToOwnerStatus'] ?? 'Pending',
                ':Remarks' => $data['Remarks'] ?? '',
                ':TripId' => $id,
            ]);

            // Replace Materials
            $pdo->prepare("DELETE FROM TripMaterial WHERE TripId = ?")
                ->execute([$id]);

            if (!empty($data['MaterialName'])) {
                $matStmt = $pdo->prepare("
                    INSERT INTO TripMaterial
                    (TripId, MaterialName, MaterialType, Weight, Quantity, UnitType, WeightPerUnit, TotalWeight, Rate, Amount)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($data['MaterialName'] as $k => $name) {
                    if (trim($name) === '') continue;

                    $type = $data['MaterialType'][$k] ?? 'Loose';
                    $r    = floatval($data['Rate'][$k] ?? 0);

                    if ($type === 'Units') {
                        $qty = floatval($data['Quantity'][$k] ?? 0);
                        $wpu = floatval($data['WeightPerUnit'][$k] ?? 0);
                        $tw  = $qty * $wpu;
                        $amt = $tw * $r;
                        $matStmt->execute([$id, trim($name), 'Units', 0, $qty, $data['UnitType'][$k] ?? '', $wpu, $tw, $r, $amt]);
                    } else {
                        $w   = floatval($data['Weight'][$k] ?? 0);
                        $matStmt->execute([$id, trim($name), 'Loose', $w, 0, null, 0, 0, $r, $w * $r]);
                    }
                }
            }

            // Commission — pehle purana delete karo, phir naya insert
            $commission = floatval($data['CommissionAmount'] ?? 0);
            $rf = ($data['FreightPaymentToOwnerStatus'] ?? 'Pending') === 'PaidDirectly'
                ? 'Owner' : 'Party';

            $pdo->prepare("DELETE FROM TripCommission WHERE TripId = ?")
                ->execute([$id]);

            $pdo->prepare("
                INSERT INTO TripCommission (TripId, CommissionAmount, RecoveryFrom)
                VALUES (?, ?, ?)
            ")->execute([$id, $commission, $rf]);

            // Vasuli — delete + re-insert
            $pdo->prepare("DELETE FROM tripvasuli WHERE TripId = ?")
                ->execute([$id]);

            $vasuliAmt = !empty($data['VasuliAmount']) ? floatval($data['VasuliAmount']) : null;
            if ($vasuliAmt !== null && $vasuliAmt > 0) {
                $vrf = ($data['VasuliRecoverFrom'] ?? '') === 'Owner' ? 'Owner' : 'Other';
                $pdo->prepare("
                    INSERT INTO tripvasuli (TripId, VasuliAmount, RecoverFrom)
                    VALUES (?, ?, ?)
                ")->execute([$id, $vasuliAmt, $vrf]);
            }

            $pdo->commit();
            return $id;

        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }

    public static function changeStatus(int $id, string $status): bool {
        global $pdo;
        return $pdo->prepare("
            UPDATE TripMaster 
            SET TripStatus = ?, UpdatedDate = NOW() 
            WHERE TripId = ?
        ")->execute([$status, $id]);
    }
}
