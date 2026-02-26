<?php
require_once __DIR__ . "/../config/database.php";

class Vehicle {

    public static function getAll(){
        global $pdo;
        $stmt=$pdo->query("SELECT v.*, o.OwnerName 
        FROM VehicleMaster v
        LEFT JOIN VehicleOwnerMaster o 
        ON v.VehicleOwnerId=o.VehicleOwnerId
        ORDER BY VehicleId DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function insert($data){
        global $pdo;
        $stmt=$pdo->prepare("INSERT INTO VehicleMaster
        (VehicleNumber,VehicleName,VehicleType,Capacity,RCNo,VehicleOwnerId,IsActive)
        VALUES (?,?,?,?,?,?,?)");
        return $stmt->execute([
            $data['VehicleNumber'],
            $data['VehicleName'],
            $data['VehicleType'],
            $data['Capacity'],
            $data['RCNo'],
            $data['VehicleOwnerId'],
            $data['IsActive']
        ]);
    }

    public static function update($id,$data){
        global $pdo;
        $stmt=$pdo->prepare("UPDATE VehicleMaster SET
        VehicleNumber=?,VehicleName=?,VehicleType=?,Capacity=?,RCNo=?,VehicleOwnerId=?,IsActive=?
        WHERE VehicleId=?");
        return $stmt->execute([
            $data['VehicleNumber'],
            $data['VehicleName'],
            $data['VehicleType'],
            $data['Capacity'],
            $data['RCNo'],
            $data['VehicleOwnerId'],
            $data['IsActive'],
            $id
        ]);
    }

    public static function changeStatus($id,$status){
        global $pdo;
        $stmt=$pdo->prepare("UPDATE VehicleMaster SET IsActive=? WHERE VehicleId=?");
        return $stmt->execute([$status,$id]);
    }
}
?>