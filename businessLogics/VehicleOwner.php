<?php
require_once __DIR__ . "/../config/database.php";

class VehicleOwner {

    public static function getAll(){
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM VehicleOwnerMaster ORDER BY VehicleOwnerId DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getById($id){
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM VehicleOwnerMaster WHERE VehicleOwnerId=?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function insert($data){
        global $pdo;

        $sql = "INSERT INTO VehicleOwnerMaster
        (OwnerName,MobileNo,AlternateMobile,Address,City,State,BankName,AccountNo,IFSC,UPI,IsActive)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = $pdo->prepare($sql);

        return $stmt->execute([
            $data['OwnerName'],
            $data['MobileNo'],
            $data['AlternateMobile'],
            $data['Address'],
            $data['City'],
            $data['State'],
            $data['BankName'],
            $data['AccountNo'],
            $data['IFSC'],
            $data['UPI'],
            $data['IsActive']
        ]);
    }

    public static function update($id,$data){
        global $pdo;

        $sql = "UPDATE VehicleOwnerMaster SET
        OwnerName=?,
        MobileNo=?,
        AlternateMobile=?,
        Address=?,
        City=?,
        State=?,
        BankName=?,
        AccountNo=?,
        IFSC=?,
        UPI=?,
        IsActive=?
        WHERE VehicleOwnerId=?";

        $stmt = $pdo->prepare($sql);

        return $stmt->execute([
            $data['OwnerName'],
            $data['MobileNo'],
            $data['AlternateMobile'],
            $data['Address'],
            $data['City'],
            $data['State'],
            $data['BankName'],
            $data['AccountNo'],
            $data['IFSC'],
            $data['UPI'],
            $data['IsActive'],
            $id
        ]);
    }

    public static function changeStatus($id,$status){
        global $pdo;
        $stmt = $pdo->prepare("UPDATE VehicleOwnerMaster SET IsActive=? WHERE VehicleOwnerId=?");
        return $stmt->execute([$status,$id]);
    }
}
?>