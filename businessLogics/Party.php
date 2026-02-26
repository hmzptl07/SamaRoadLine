<?php
require_once __DIR__ . "/../config/database.php";

class Party
{

    // 🔹 Get All Parties
    public static function getAll()
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM PartyMaster ORDER BY PartyId DESC");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 🔹 Get Single Party By ID
    public static function getById($id)
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM PartyMaster WHERE PartyId = ?");
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 🔹 Insert Party
    public static function insert($data)
    {
        global $pdo;

        $sql = "INSERT INTO PartyMaster 
            (PartyName, PartyType, MobileNo, Email, GSTNo, Address, City, State, StateCode, Remark, IsActive)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);

        return $stmt->execute([
            $data['PartyName'],
            $data['PartyType'],
            $data['MobileNo'],
            $data['Email'],
            $data['GSTNo'],
            $data['Address'],
            $data['City'],
            $data['State'],
            $data['StateCode'],
            $data['Remark'],
            $data['IsActive']
        ]);
    }

    // 🔹 Update Party
    public static function update($id, $data)
    {
        global $pdo;

        $sql = "UPDATE PartyMaster SET
            PartyName = ?,
            PartyType = ?,
            MobileNo = ?,
            Email = ?,
            GSTNo = ?,
            Address = ?,
            City = ?,
            State = ?,
            StateCode = ?,
            Remark = ?,
            IsActive = ?
            WHERE PartyId = ?";

        $stmt = $pdo->prepare($sql);

        return $stmt->execute([
            $data['PartyName'],
            $data['PartyType'],
            $data['MobileNo'],
            $data['Email'],
            $data['GSTNo'],
            $data['Address'],
            $data['City'],
            $data['State'],
            $data['StateCode'],
            $data['Remark'],
            $data['IsActive'],
            $id
        ]);
    }

    // 🔹 Soft Delete (Deactivate Instead of Delete)
    public static function deactivate($id)
    {
        global $pdo;

        $stmt = $pdo->prepare("UPDATE PartyMaster SET IsActive = 'No' WHERE PartyId = ?");
        return $stmt->execute([$id]);
    }

    // 🔹 Check Duplicate Party Name
    public static function existsByName($name)
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM PartyMaster WHERE PartyName = ?");
        $stmt->execute([$name]);

        return $stmt->fetchColumn() > 0;
    }

    public static function changeStatus($id, $status)
    {
        global $pdo;
        $stmt = $pdo->prepare("UPDATE PartyMaster SET IsActive=? WHERE PartyId=?");
        return $stmt->execute([$status, $id]);
    }
}
