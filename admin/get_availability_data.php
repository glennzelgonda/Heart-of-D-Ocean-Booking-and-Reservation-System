<?php
session_start();
include '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    // Get booked cottages for next 7 days - ONLY FROM NON-DELETED BOOKINGS
    $bookedQuery = "SELECT 
        ca.cottage_name,
        ca.booked_date,
        ca.status,
        b.name,
        b.booking_id
        FROM cottage_availability ca
        LEFT JOIN bookings b ON ca.booking_id = b.booking_id
        WHERE ca.booked_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND ca.status = 'confirmed'
        AND (b.deleted = 0 OR b.deleted IS NULL)
        ORDER BY ca.booked_date, ca.cottage_name";
    
    $bookedStmt = $db->prepare($bookedQuery);
    $bookedStmt->execute();
    $bookedData = $bookedStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get available cottages for next 7 days
    $availableQuery = "SELECT 
        cottage_name,
        booked_date,
        'available' as status
        FROM (
            SELECT 
                c.cottage_name,
                d.booked_date
            FROM (
                SELECT DISTINCT cottage_name 
                FROM cottage_availability 
                UNION 
                SELECT 'White House — ₱30,000' as cottage_name
                UNION SELECT 'Penthouse — ₱12,800'
                UNION SELECT 'Aqua Class — ₱11,800'
                UNION SELECT 'Heartsuite — ₱11,800'
                UNION SELECT 'Steph''s Skylounge 842/844 — ₱11,800'
                UNION SELECT 'Steph''s 848 — ₱10,800'
                UNION SELECT 'Steph''s 846 — ₱10,000'
                UNION SELECT 'Concierge 817 — ₱9,800'
                UNION SELECT 'De Luxe — ₱8,800'
                UNION SELECT 'Concierge 815/819 — ₱8,800'
                UNION SELECT 'Premium 840 — ₱8,800'
                UNION SELECT 'Beatrice A — ₱7,800'
                UNION SELECT 'Premium 838 — ₱7,800'
                UNION SELECT 'Beatrice B — ₱6,800'
                UNION SELECT 'Giant Kubo — ₱6,800'
                UNION SELECT 'Seaside (Whole) — ₱6,800'
                UNION SELECT 'Seaside (Half) — ₱3,400'
                UNION SELECT 'Bamboo Kubo — ₱2,800'
            ) c
            CROSS JOIN (
                SELECT DATE_ADD(CURDATE(), INTERVAL n DAY) as booked_date
                FROM (
                    SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 
                    UNION SELECT 4 UNION SELECT 5 UNION SELECT 6
                ) days
            ) d
        ) all_combinations
        WHERE NOT EXISTS (
            SELECT 1 FROM cottage_availability ca 
            WHERE ca.cottage_name = all_combinations.cottage_name 
            AND ca.booked_date = all_combinations.booked_date 
            AND ca.status = 'confirmed'
        )
        ORDER BY booked_date, cottage_name";
    
    $availableStmt = $db->prepare($availableQuery);
    $availableStmt->execute();
    $availableData = $availableStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate summary
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    $todayBooked = array_filter($bookedData, function($item) use ($today) {
        return $item['booked_date'] == $today;
    });
    
    $tomorrowBooked = array_filter($bookedData, function($item) use ($tomorrow) {
        return $item['booked_date'] == $tomorrow;
    });
    
    $todayAvailable = array_filter($availableData, function($item) use ($today) {
        return $item['booked_date'] == $today;
    });
    
    $tomorrowAvailable = array_filter($availableData, function($item) use ($tomorrow) {
        return $item['booked_date'] == $tomorrow;
    });

    $summary = [
        'booked_dates' => count($bookedData),
        'available_dates' => count($availableData),
        'total_dates' => count($bookedData) + count($availableData),
        'today_booked' => count($todayBooked),
        'today_available' => count($todayAvailable),
        'tomorrow_booked' => count($tomorrowBooked),
        'tomorrow_available' => count($tomorrowAvailable)
    ];

    echo json_encode([
        'booked' => $bookedData,
        'available' => $availableData,
        'summary' => $summary
    ]);

} catch(PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>