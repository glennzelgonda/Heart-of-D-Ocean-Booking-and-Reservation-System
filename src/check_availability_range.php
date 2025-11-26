<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cottage = $_POST['cottage'];
    $checkin_date = $_POST['checkin_date'];
    $checkout_date = $_POST['checkout_date'];
    
    try {
        $query = "SELECT COUNT(*) as count FROM cottage_availability 
                  WHERE cottage_name = :cottage 
                  AND booked_date BETWEEN :checkin_date AND DATE_SUB(:checkout_date, INTERVAL 1 DAY)
                  AND status = 'confirmed'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":cottage", $cottage);
        $stmt->bindParam(":checkin_date", $checkin_date);
        $stmt->bindParam(":checkout_date", $checkout_date);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'available' => $result['count'] == 0,
            'cottage' => $cottage,
            'checkin_date' => $checkin_date,
            'checkout_date' => $checkout_date
        ]);
    } catch(PDOException $e) {
        echo json_encode([
            'available' => false,
            'error' => 'Database error'
        ]);
    }
}
?>