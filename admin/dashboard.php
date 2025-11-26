<?php
session_start();
include '../config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize all variables with defaults
$bookings = $recentBookingsData = $monthlyData = $popularRooms = $peakDates = [];
$monthlyRevenueData = $revenueByCottage = $revenueByPayment = $weeklyTrends = [];
$availabilityData = [];
$totalBookings = $pendingBookings = $confirmedBookings = $cancelledBookings = 0;
$recentBookings = $totalRevenue = $avgBookingValue = $totalConfirmedBookings = 0;
$gcashBookings = $onsiteBookings = $gcashRevenue = $onsiteRevenue = 0;

try {
    // Get bookings and calculate stats
    $query = "SELECT * FROM bookings WHERE deleted = 0 ORDER BY timestamp DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate basic stats
    $statsQueries = [
        'total' => "SELECT COUNT(*) as count FROM bookings WHERE deleted = 0",
        'pending' => "SELECT COUNT(*) as count FROM bookings WHERE status = 'pending' AND deleted = 0",
        'confirmed' => "SELECT COUNT(*) as count FROM bookings WHERE status = 'confirmed' AND deleted = 0",
        'cancelled' => "SELECT COUNT(*) as count FROM bookings WHERE status = 'cancelled' AND deleted = 0",
        'recent' => "SELECT COUNT(*) as count FROM bookings WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND deleted = 0"
    ];
    
    foreach ($statsQueries as $key => $sql) {
        $stmt = $db->prepare($sql);
        $stmt->execute();
        ${$key . 'Bookings'} = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }

    // Get recent bookings
    $recentQuery = "SELECT * FROM bookings WHERE deleted = 0 ORDER BY timestamp DESC LIMIT 5";
    $recentStmt = $db->prepare($recentQuery);
    $recentStmt->execute();
    $recentBookingsData = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

    // Monthly bookings data
    $monthlyQuery = "SELECT 
        MONTHNAME(timestamp) as month, 
        COUNT(*) as count,
        MONTH(timestamp) as month_num
        FROM bookings 
        WHERE YEAR(timestamp) = YEAR(CURDATE()) AND deleted = 0
        GROUP BY MONTH(timestamp), MONTHNAME(timestamp)
        ORDER BY month_num";
    $monthlyStmt = $db->prepare($monthlyQuery);
    $monthlyStmt->execute();
    $monthlyData = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Popular rooms
    $roomsQuery = "SELECT room, COUNT(*) as count FROM bookings WHERE deleted = 0 GROUP BY room ORDER BY count DESC LIMIT 5";
    $roomsStmt = $db->prepare($roomsQuery);
    $roomsStmt->execute();
    $popularRooms = $roomsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Peak dates
    $peakDatesQuery = "SELECT 
        date, 
        COUNT(*) as booking_count,
        SUM(total_price) as total_revenue
        FROM bookings 
        WHERE status = 'confirmed' AND deleted = 0
        GROUP BY date 
        ORDER BY booking_count DESC, total_revenue DESC 
        LIMIT 10";
    $peakDatesStmt = $db->prepare($peakDatesQuery);
    $peakDatesStmt->execute();
    $peakDates = $peakDatesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Revenue data
    $revenueQuery = "SELECT 
        COALESCE(SUM(total_price), 0) as total_revenue,
        COALESCE(AVG(total_price), 0) as avg_booking_value,
        COUNT(*) as total_confirmed_bookings,
        SUM(CASE WHEN payment_method = 'pay-now' THEN 1 ELSE 0 END) as gcash_bookings,
        SUM(CASE WHEN payment_method = 'face-to-face' THEN 1 ELSE 0 END) as onsite_bookings,
        COALESCE(SUM(CASE WHEN payment_method = 'pay-now' THEN total_price ELSE 0 END), 0) as gcash_revenue,
        COALESCE(SUM(CASE WHEN payment_method = 'face-to-face' THEN total_price ELSE 0 END), 0) as onsite_revenue
        FROM bookings 
        WHERE status = 'confirmed' AND deleted = 0";
    $revenueStmt = $db->prepare($revenueQuery);
    $revenueStmt->execute();
    $revenueData = $revenueStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($revenueData) {
        $totalRevenue = $revenueData['total_revenue'] ?? 0;
        $avgBookingValue = $revenueData['avg_booking_value'] ?? 0;
        $totalConfirmedBookings = $revenueData['total_confirmed_bookings'] ?? 0;
        $gcashBookings = $revenueData['gcash_bookings'] ?? 0;
        $onsiteBookings = $revenueData['onsite_bookings'] ?? 0;
        $gcashRevenue = $revenueData['gcash_revenue'] ?? 0;
        $onsiteRevenue = $revenueData['onsite_revenue'] ?? 0;
    }

    // Revenue by payment method
    $revenueByPaymentQuery = "SELECT 
        payment_method,
        COALESCE(SUM(total_price), 0) as total_revenue,
        COUNT(*) as bookings_count,
        COALESCE(AVG(total_price), 0) as avg_revenue
        FROM bookings 
        WHERE status = 'confirmed'
        AND total_price IS NOT NULL
        AND deleted = 0
        AND payment_method IS NOT NULL
        GROUP BY payment_method 
        ORDER BY total_revenue DESC";
    $revenueByPaymentStmt = $db->prepare($revenueByPaymentQuery);
    $revenueByPaymentStmt->execute();
    $revenueByPayment = $revenueByPaymentStmt->fetchAll(PDO::FETCH_ASSOC);

    // Monthly revenue
    $monthlyRevenueQuery = "SELECT 
        MONTHNAME(timestamp) as month,
        MONTH(timestamp) as month_num,
        COALESCE(SUM(total_price), 0) as revenue,
        COUNT(*) as bookings_count
        FROM bookings 
        WHERE status = 'confirmed' 
        AND total_price IS NOT NULL
        AND YEAR(timestamp) = YEAR(CURDATE())
        AND deleted = 0
        GROUP BY MONTH(timestamp), MONTHNAME(timestamp)
        ORDER BY month_num";
    $monthlyRevenueStmt = $db->prepare($monthlyRevenueQuery);
    $monthlyRevenueStmt->execute();
    $monthlyRevenueData = $monthlyRevenueStmt->fetchAll(PDO::FETCH_ASSOC);

    // Revenue by cottage
    $revenueByCottageQuery = "SELECT 
        room,
        COALESCE(SUM(total_price), 0) as total_revenue,
        COUNT(*) as bookings_count,
        COALESCE(AVG(total_price), 0) as avg_revenue
        FROM bookings 
        WHERE status = 'confirmed'
        AND total_price IS NOT NULL
        AND deleted = 0
        GROUP BY room 
        ORDER BY total_revenue DESC 
        LIMIT 10";
    $revenueByCottageStmt = $db->prepare($revenueByCottageQuery);
    $revenueByCottageStmt->execute();
    $revenueByCottage = $revenueByCottageStmt->fetchAll(PDO::FETCH_ASSOC);

    // Weekly trends for charts
    $weeklyTrendsQuery = "SELECT 
        DATE(timestamp) as date,
        COUNT(*) as daily_bookings
        FROM bookings 
        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND deleted = 0
        GROUP BY DATE(timestamp)
        ORDER BY date";
    $weeklyStmt = $db->prepare($weeklyTrendsQuery);
    $weeklyStmt->execute();
    $weeklyTrends = $weeklyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Availability data
    $availabilityQuery = "SELECT 
        DATE(date) as booking_date,
        room
        FROM bookings 
        WHERE status = 'confirmed' 
        AND date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 DAY)
        AND deleted = 0
        ORDER BY booking_date, room";
    $availabilityStmt = $db->prepare($availabilityQuery);
    $availabilityStmt->execute();
    $bookedData = $availabilityStmt->fetchAll(PDO::FETCH_ASSOC);

    // Define all available cottage types
    $allCottages = [
        "White House — ₱30,000",
        "Penthouse — ₱12,800",
        "Aqua Class — ₱11,800",
        "Heartsuite — ₱11,800",
        "Steph's Skylounge 842/844 — ₱11,800",
        "Steph's 848 — ₱10,800",
        "Steph's 846 — ₱10,000",
        "Concierge 817 — ₱9,800",
        "De Luxe — ₱8,800",
        "Concierge 815/819 — ₱8,800",
        "Premium 840 — ₱8,800",
        "Beatrice A — ₱7,800",
        "Premium 838 — ₱7,800",
        "Beatrice B — ₱6,800",
        "Giant Kubo — ₱6,800",
        "Seaside (Whole) — ₱6,800",
        "Seaside (Half) — ₱3,400",
        "Bamboo Kubo — ₱2,800"
    ];

    // Prepare availability data structure
    $availabilityData = [];
    $startDate = new DateTime();
    $endDate = new DateTime();
    $endDate->modify('+6 days');

    // Initialize all dates with all cottages available
    $currentDate = clone $startDate;
    while ($currentDate <= $endDate) {
        $dateStr = $currentDate->format('Y-m-d');
        $availabilityData[$dateStr] = [
            'date' => $dateStr,
            'available_cottages' => $allCottages,
            'booked_cottages' => []
        ];
        $currentDate->modify('+1 day');
    }

    // Mark booked cottages as unavailable
    foreach ($bookedData as $booking) {
        $dateStr = $booking['booking_date'];
        $bookedCottage = trim($booking['room']);
        
        if (isset($availabilityData[$dateStr])) {
            // Remove booked cottage from available list
            $availabilityData[$dateStr]['available_cottages'] = array_values(array_filter(
                $availabilityData[$dateStr]['available_cottages'],
                function($availableCottage) use ($bookedCottage) {
                    $availableCottageTrimmed = trim($availableCottage);
                    return ($availableCottageTrimmed !== $bookedCottage);
                }
            ));
            
            // Add to booked list if not already there
            if (!in_array($bookedCottage, $availabilityData[$dateStr]['booked_cottages'])) {
                $availabilityData[$dateStr]['booked_cottages'][] = $bookedCottage;
            }
        }
    }
    
} catch(PDOException $exception) {
    $error = "Database error: " . $exception->getMessage();
    error_log($error);
}

// Prepare data for JavaScript charts
$statusLabels = ['Pending', 'Confirmed', 'Cancelled'];
$statusData = [$pendingBookings, $confirmedBookings, $cancelledBookings];
$statusColors = ['#ffc107', '#28a745', '#dc3545'];

// Monthly chart data
$monthlyLabels = [];
$monthlyCounts = [];
foreach ($monthlyData as $month) {
    $monthlyLabels[] = substr($month['month'], 0, 3);
    $monthlyCounts[] = (int)$month['count'];
}

// Weekly trends data
$weeklyDates = [];
$weeklyBookings = [];
foreach ($weeklyTrends as $trend) {
    $weeklyDates[] = $trend['date'];
    $weeklyBookings[] = (int)$trend['daily_bookings'];
}

// Ensure we have data for charts
if (empty($weeklyBookings)) {
    $weeklyDates = [date('Y-m-d', strtotime('-7 days')), date('Y-m-d')];
    $weeklyBookings = [2, 5];
}

if (empty($monthlyCounts)) {
    $monthlyLabels = ['Jan', 'Feb', 'Mar'];
    $monthlyCounts = [0, 0, 0];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .last-update { 
            color: #6c757d; 
            text-align: right; 
            margin-bottom: 1rem; 
            font-size: 0.9rem;
        }
        .action-message { 
            background: #d4edda; 
            color: #155724; 
            padding: 1rem; 
            border-radius: 5px; 
            margin-bottom: 1rem; 
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
            <h1>Admin Dashboard - Analytics & Bookings</h1>
            <div class="header-info">
                <button class="refresh-btn" onclick="window.location.reload()">🔄 Refresh</button>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['action_message'])): ?>
            <div class="action-message">
                <?php echo $_SESSION['action_message']; unset($_SESSION['action_message']); ?>
            </div>
        <?php endif; ?>

        <div class="last-update">
            Last updated: <?php echo date('Y-m-d H:i:s'); ?>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" onclick="showTab('overview')">📊 Overview</div>
            <div class="tab" onclick="showTab('bookings')">📋 Bookings</div>
            <div class="tab" onclick="showTab('analytics')">📈 Analytics</div>
            <div class="tab" onclick="showTab('revenue')">💰 Revenue</div>
            <div class="tab" onclick="showTab('payments')">💳 Payments</div>
            <div class="tab" onclick="showTab('availability')">🏠 Availability</div>
        </div>

        <!-- Overview Tab -->
        <div id="overview" class="tab-content active">
            <!-- Booking Statistics Section -->
            <div class="dashboard-card">
                <h3>📊 Booking Statistics</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <h4>Total Bookings</h4>
                        <span class="stat-number"><?php echo $totalBookings; ?></span>
                    </div>
                    <div class="stat-item">
                        <h4>Pending</h4>
                        <span class="stat-number pending"><?php echo $pendingBookings; ?></span>
                    </div>
                    <div class="stat-item">
                        <h4>Confirmed</h4>
                        <span class="stat-number confirmed"><?php echo $confirmedBookings; ?></span>
                    </div>
                    <div class="stat-item">
                        <h4>Cancelled</h4>
                        <span class="stat-number cancelled-stat"><?php echo $cancelledBookings; ?></span>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalBookings; ?></div>
                    <div class="stat-label">Total Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pendingBookings; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $confirmedBookings; ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number cancelled-stat"><?php echo $cancelledBookings; ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $recentBookings; ?></div>
                    <div class="stat-label">Last 7 Days</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number revenue-stat">₱<?php echo number_format($totalRevenue, 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>

            <div class="analytics-grid">
                <div class="analytics-card">
                    <h3>📈 Monthly Bookings</h3>
                    <?php if (!empty($monthlyData)): ?>
                        <?php 
                        $maxCount = max(array_column($monthlyData, 'count'));
                        foreach ($monthlyData as $month): 
                            $width = ($month['count'] / $maxCount) * 100;
                        ?>
                        <div class="chart-label">
                            <span class="chart-month"><?php echo $month['month']; ?></span>
                            <span class="chart-count"><?php echo $month['count']; ?></span>
                        </div>
                        <div class="chart-bar" style="width: <?php echo $width; ?>%"></div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #666; text-align: center;">No data available</p>
                    <?php endif; ?>
                </div>

                <div class="analytics-card">
                    <h3>🏆 Popular Cottages</h3>
                    <?php if (!empty($popularRooms)): ?>
                        <?php foreach ($popularRooms as $room): ?>
                        <div class="room-item">
                            <span class="room-name"><?php echo htmlspecialchars($room['room']); ?></span>
                            <span class="room-count"><?php echo $room['count']; ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #666; text-align: center;">No data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Bookings Tab -->
        <div id="bookings" class="tab-content">
            <?php if (empty($bookings)): ?>
                <div class="no-bookings">
                    <h3>No bookings yet</h3>
                    <p>When customers submit bookings, they will appear here automatically.</p>
                </div>
            <?php else: ?>
                <div style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
                    <h3>All Active Bookings (<?php echo count($bookings); ?> total)</h3>
                </div>
                
                <div class="bookings-table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Cottage</th>
                                <th>Date</th>
                                <th>Guests</th>
                                <th>Payment Method</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><strong><?php echo $booking['booking_id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($booking['name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['email']); ?></td>
                                <td><?php echo htmlspecialchars($booking['room']); ?></td>
                                <td><?php echo htmlspecialchars($booking['date']); ?></td>
                                <td><?php echo htmlspecialchars($booking['guests']); ?></td>
                                <td>
                                    <?php 
                                    $paymentMethod = $booking['payment_method'] ?? 'Not set';
                                    if ($paymentMethod === 'pay-now') {
                                        echo '<span class="payment-badge-gcash">GCash</span>';
                                    } elseif ($paymentMethod === 'face-to-face') {
                                        echo '<span class="payment-badge-onsite">On-site</span>';
                                    } else {
                                        echo '<span style="color: #6c757d;">Not set</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="status-<?php echo $booking['status']; ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($booking['status'] === 'pending'): ?>
                                        <form method="POST" action="process_booking.php" style="display: inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                            <input type="hidden" name="status" value="confirmed">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <button type="submit" class="btn btn-confirm">Confirm</button>
                                        </form>
                                        <form method="POST" action="process_booking.php" style="display: inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                            <input type="hidden" name="status" value="cancelled">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <button type="submit" class="btn btn-cancel">Cancel</button>
                                        </form>
                                        <?php elseif ($booking['status'] === 'confirmed'): ?>
                                        <form method="POST" action="process_booking.php" style="display: inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                            <input type="hidden" name="status" value="cancelled">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <button type="submit" class="btn btn-cancel">Cancel</button>
                                        </form>
                                        <?php elseif ($booking['status'] === 'cancelled'): ?>
                                        <form method="POST" action="process_booking.php" style="display: inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                            <input type="hidden" name="status" value="pending">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <button type="submit" class="btn btn-revert">Reactivate</button>
                                        </form>
                                        <?php endif; ?>
                                        
                                        <form method="POST" action="process_booking.php" style="display: inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="delete_booking" value="1">
                                            <button type="submit" class="btn btn-delete">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Analytics Tab -->
        <div id="analytics" class="tab-content">
            <!-- Graphs Section -->
            <div class="analytics-grid-3">
                <div class="analytics-card">
                    <h3>📊 Status Distribution</h3>
                    <div class="chart-container small-chart">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <h3>📈 Daily Bookings (30 Days)</h3>
                    <div class="chart-container small-chart">
                        <canvas id="trendsChart"></canvas>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <h3>🎯 Monthly Performance</h3>
                    <div class="chart-container small-chart">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="analytics-grid">
                <div class="analytics-card">
                    <h3>📊 Booking Statistics</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <h4>Status Distribution</h4>
                            <p>Pending: <?php echo $pendingBookings; ?> (<?php echo $totalBookings > 0 ? round(($pendingBookings/$totalBookings)*100, 1) : 0; ?>%)</p>
                            <p>Confirmed: <?php echo $confirmedBookings; ?> (<?php echo $totalBookings > 0 ? round(($confirmedBookings/$totalBookings)*100, 1) : 0; ?>%)</p>
                            <p>Cancelled: <?php echo $cancelledBookings; ?> (<?php echo $totalBookings > 0 ? round(($cancelledBookings/$totalBookings)*100, 1) : 0; ?>%)</p>
                        </div>
                        <div>
                            <h4>Recent Activity</h4>
                            <p>Last 7 Days: <?php echo $recentBookings; ?> bookings</p>
                            <p>This Month: <?php echo array_sum(array_column($monthlyData, 'count')); ?> bookings</p>
                            <p>Conversion Rate: <?php echo $totalBookings > 0 ? round(($confirmedBookings/$totalBookings)*100, 1) : 0; ?>%</p>
                        </div>
                    </div>
                </div>

                <div class="analytics-card">
                    <h3>📅 Monthly Overview</h3>
                    <?php if (!empty($monthlyData)): ?>
                        <?php foreach ($monthlyData as $month): ?>
                        <div class="chart-label">
                            <span class="chart-month"><?php echo $month['month']; ?></span>
                            <span class="chart-count"><?php echo $month['count']; ?> bookings</span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #666; text-align: center;">No monthly data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Revenue Tab -->
        <div id="revenue" class="tab-content">
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number revenue-stat">₱<?php echo number_format($totalRevenue, 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalConfirmedBookings; ?></div>
                    <div class="stat-label">Confirmed Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number revenue-stat">₱<?php echo number_format($avgBookingValue, 2); ?></div>
                    <div class="stat-label">Average Booking Value</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalBookings > 0 ? round(($totalConfirmedBookings/$totalBookings)*100, 1) : 0; ?>%</div>
                    <div class="stat-label">Conversion Rate</div>
                </div>
            </div>

            <div class="revenue-peak-grid">
                <div class="analytics-card">
                    <h3>📈 Monthly Revenue</h3>
                    <?php if (!empty($monthlyRevenueData)): ?>
                        <?php 
                        $maxRevenue = max(array_column($monthlyRevenueData, 'revenue'));
                        foreach ($monthlyRevenueData as $month): 
                            $width = $maxRevenue > 0 ? ($month['revenue'] / $maxRevenue) * 100 : 0;
                        ?>
                        <div class="chart-label">
                            <span class="chart-month"><?php echo $month['month']; ?></span>
                            <span class="chart-revenue">₱<?php echo number_format($month['revenue'], 2); ?></span>
                        </div>
                        <div class="revenue-bar" style="width: <?php echo $width; ?>%"></div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #666; text-align: center;">No revenue data available</p>
                    <?php endif; ?>
                </div>

                <div class="analytics-card">
                    <h3>💰 Revenue by Cottage Type</h3>
                    <?php if (!empty($revenueByCottage)): ?>
                        <?php foreach ($revenueByCottage as $cottage): ?>
                        <div class="revenue-item">
                            <div class="revenue-cottage"><?php echo htmlspecialchars($cottage['room']); ?></div>
                            <div class="revenue-bookings"><?php echo $cottage['bookings_count']; ?> bookings</div>
                            <div class="revenue-amount">₱<?php echo number_format($cottage['total_revenue'], 2); ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #666; text-align: center;">No revenue by cottage data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Payments Tab -->
        <div id="payments" class="tab-content">
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number revenue-stat">₱<?php echo number_format($totalRevenue, 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $gcashBookings; ?></div>
                    <div class="stat-label">GCash Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $onsiteBookings; ?></div>
                    <div class="stat-label">On-site Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number revenue-stat">₱<?php echo number_format($gcashRevenue, 2); ?></div>
                    <div class="stat-label">GCash Revenue</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number revenue-stat">₱<?php echo number_format($onsiteRevenue, 2); ?></div>
                    <div class="stat-label">On-site Revenue</div>
                </div>
            </div>

            <div class="analytics-grid">
                <div class="analytics-card">
                    <h3>💳 Payment Method Analytics</h3>
                    <?php if (!empty($revenueByPayment)): ?>
                        <?php foreach ($revenueByPayment as $payment): ?>
                        <div class="payment-item">
                            <div class="payment-method">
                                <?php 
                                if ($payment['payment_method'] === 'pay-now') {
                                    echo '💳 GCash Payments';
                                } elseif ($payment['payment_method'] === 'face-to-face') {
                                    echo '💵 On-site Payments';
                                } else {
                                    echo '❓ Unknown';
                                }
                                ?>
                            </div>
                            <div class="payment-bookings"><?php echo $payment['bookings_count']; ?> bookings</div>
                            <div class="payment-amount">₱<?php echo number_format($payment['total_revenue'], 2); ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #666; text-align: center;">No payment data available</p>
                    <?php endif; ?>
                </div>

                <div class="analytics-card">
                    <h3>📊 Payment Distribution</h3>
                    <div style="padding: 1rem 0;">
                        <div style="margin-bottom: 1rem;">
                            <h4>Booking Distribution</h4>
                            <p>GCash: <strong><?php echo $gcashBookings; ?></strong> (<?php echo $totalConfirmedBookings > 0 ? round(($gcashBookings/$totalConfirmedBookings)*100, 1) : 0; ?>%)</p>
                            <p>On-site: <strong><?php echo $onsiteBookings; ?></strong> (<?php echo $totalConfirmedBookings > 0 ? round(($onsiteBookings/$totalConfirmedBookings)*100, 1) : 0; ?>%)</p>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <h4>Revenue Distribution</h4>
                            <p>GCash: <strong>₱<?php echo number_format($gcashRevenue, 2); ?></strong> (<?php echo $totalRevenue > 0 ? round(($gcashRevenue/$totalRevenue)*100, 1) : 0; ?>%)</p>
                            <p>On-site: <strong>₱<?php echo number_format($onsiteRevenue, 2); ?></strong> (<?php echo $totalRevenue > 0 ? round(($onsiteRevenue/$totalRevenue)*100, 1) : 0; ?>%)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Availability Tab -->
        <div id="availability" class="tab-content">
            <div class="availability-grid">
                <div class="availability-card">
                    <h3>📅 Booked Cottages (Next 7 Days)</h3>
                    <div id="bookedCottagesList">
                        <div class="loading">Loading booked cottages...</div>
                    </div>
                </div>

                <div class="availability-card">
                    <h3>✅ Available Cottages (Next 7 Days)</h3>
                    <div id="availableCottagesList">
                        <div class="loading">Loading available cottages...</div>
                    </div>
                </div>
            </div>

            <div class="analytics-card">
                <h3>📊 Availability Summary</h3>
                <div id="availabilitySummary">
                    <div class="loading">Loading summary...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Dashboard Configuration
        const dashboardConfig = {
            status: {
                labels: <?php echo json_encode($statusLabels); ?>,
                data: <?php echo json_encode($statusData); ?>,
                colors: <?php echo json_encode($statusColors); ?>
            },
            monthly: {
                labels: <?php echo json_encode($monthlyLabels); ?>,
                data: <?php echo json_encode($monthlyCounts); ?>
            },
            trends: {
                dates: <?php echo json_encode($weeklyDates); ?>,
                bookings: <?php echo json_encode($weeklyBookings); ?>
            },
            availability: <?php echo json_encode($availabilityData); ?>
        };

        // Chart instances storage
        let chartInstances = {
            statusChart: null,
            monthlyChart: null, 
            trendsChart: null
        };

        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initializeTabs();
            initializeCharts();
            initializeAvailability();
        });

        // Tab management
        function initializeTabs() {
            // Set up tab click handlers
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabName = this.getAttribute('onclick').match(/showTab\('([^']+)'\)/)[1];
                    showTab(tabName);
                });
            });
        }

        function showTab(tabName) {
            console.log('Switching to tab:', tabName);
            
            // Hide all tab contents and remove active class from tabs
            document.querySelectorAll('.tab-content, .tab').forEach(element => {
                element.classList.remove('active');
            });
            
            // Show selected tab and mark as active
            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
            
            // Handle tab-specific initializations
            setTimeout(() => {
                if (tabName === 'analytics') {
                    initializeCharts();
                } else if (tabName === 'availability') {
                    initializeAvailability();
                }
            }, 100);
        }

        // Chart initialization
        function initializeCharts() {
            console.log('Initializing charts...');
            
            // Destroy existing charts to prevent duplicates
            Object.values(chartInstances).forEach(chart => {
                if (chart) {
                    chart.destroy();
                }
            });
            
            // Initialize Status Distribution Chart
            initializeStatusChart();
            
            // Initialize Monthly Performance Chart
            initializeMonthlyChart();
            
            // Initialize Trends Chart
            initializeTrendsChart();
        }

        function initializeStatusChart() {
            const ctx = document.getElementById('statusChart');
            if (!ctx) {
                console.warn('Status chart canvas not found');
                return;
            }
            
            try {
                chartInstances.statusChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: dashboardConfig.status.labels,
                        datasets: [{
                            data: dashboardConfig.status.data,
                            backgroundColor: dashboardConfig.status.colors,
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true
                                }
                            }
                        }
                    }
                });
                console.log('Status chart initialized successfully');
            } catch (error) {
                console.error('Error initializing status chart:', error);
                showChartError(ctx, 'Status Chart');
            }
        }

        function initializeMonthlyChart() {
            const ctx = document.getElementById('monthlyChart');
            if (!ctx) {
                console.warn('Monthly chart canvas not found');
                return;
            }
            
            try {
                chartInstances.monthlyChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: dashboardConfig.monthly.labels,
                        datasets: [{
                            label: 'Monthly Bookings',
                            data: dashboardConfig.monthly.data,
                            backgroundColor: 'rgba(102, 126, 234, 0.8)',
                            borderColor: 'rgba(102, 126, 234, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
                console.log('Monthly chart initialized successfully');
            } catch (error) {
                console.error('Error initializing monthly chart:', error);
                showChartError(ctx, 'Monthly Chart');
            }
        }

        function initializeTrendsChart() {
            const ctx = document.getElementById('trendsChart');
            if (!ctx) {
                console.warn('Trends chart canvas not found');
                return;
            }
            
            try {
                // Format dates for display
                const formattedDates = dashboardConfig.trends.dates.map(date => {
                    const d = new Date(date);
                    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                });

                chartInstances.trendsChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: formattedDates,
                        datasets: [{
                            label: 'Daily Bookings',
                            data: dashboardConfig.trends.bookings,
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
                console.log('Trends chart initialized successfully');
            } catch (error) {
                console.error('Error initializing trends chart:', error);
                showChartError(ctx, 'Trends Chart');
            }
        }

        function showChartError(canvasElement, chartName) {
            const container = canvasElement.parentElement;
            container.innerHTML = `
                <div class="chart-placeholder">
                    <h4>${chartName} Error</h4>
                    <p>Unable to load chart data</p>
                    <button onclick="initializeCharts()" class="btn btn-confirm" style="margin-top: 1rem;">
                        Retry Loading Charts
                    </button>
                </div>
            `;
        }

        // Availability functions
        function initializeAvailability() {
            displayAvailabilitySummary();
            displayBookedCottages();
            displayAvailableCottages();
        }

        function displayAvailabilitySummary() {
            const summaryElement = document.getElementById('availabilitySummary');
            if (!summaryElement || !dashboardConfig.availability) return;

            const availabilityData = dashboardConfig.availability;
            let totalBooked = 0;
            let totalAvailable = 0;
            const today = new Date().toISOString().split('T')[0];

            Object.values(availabilityData).forEach(day => {
                totalBooked += day.booked_cottages.length;
                totalAvailable += day.available_cottages.length;
            });

            const totalDays = Object.keys(availabilityData).length;
            const avgBookedPerDay = (totalBooked / totalDays).toFixed(1);
            const avgAvailablePerDay = (totalAvailable / totalDays).toFixed(1);

            // Get today's availability
            const todayData = availabilityData[today] || { booked_cottages: [], available_cottages: [] };
            const todayBooked = todayData.booked_cottages.length;
            const todayAvailable = todayData.available_cottages.length;

            summaryElement.innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                        <h4 style="margin: 0 0 0.5rem 0; color: #dc3545;">📅 Today</h4>
                        <div style="font-size: 1.5rem; font-weight: bold; color: #dc3545;">${todayBooked} Booked</div>
                        <div style="color: #666; font-size: 0.9rem;">${todayAvailable} Available</div>
                    </div>
                    <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                        <h4 style="margin: 0 0 0.5rem 0; color: #28a745;">📊 7-Day Avg</h4>
                        <div style="font-size: 1.5rem; font-weight: bold; color: #28a745;">${avgBookedPerDay} Booked</div>
                        <div style="color: #666; font-size: 0.9rem;">${avgAvailablePerDay} Available/day</div>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <h4>📈 Total (Next 7 Days)</h4>
                        <p>Booked Cottages: <strong>${totalBooked}</strong></p>
                        <p>Available Cottages: <strong>${totalAvailable}</strong></p>
                        <p>Occupancy Rate: <strong>${totalBooked + totalAvailable > 0 ? Math.round((totalBooked / (totalBooked + totalAvailable)) * 100) : 0}%</strong></p>
                    </div>
                    <div>
                        <h4>📋 Quick Stats</h4>
                        <p>Total Days: <strong>${totalDays}</strong></p>
                        <p>Avg Booked/Day: <strong>${avgBookedPerDay}</strong></p>
                        <p>Avg Available/Day: <strong>${avgAvailablePerDay}</strong></p>
                    </div>
                </div>
            `;
        }

        function displayBookedCottages() {
            const bookedElement = document.getElementById('bookedCottagesList');
            if (!bookedElement || !dashboardConfig.availability) return;

            let bookedHTML = '';
            const today = new Date();

            Object.entries(dashboardConfig.availability)
                .sort(([dateA], [dateB]) => new Date(dateA) - new Date(dateB))
                .forEach(([date, data]) => {
                    if (data.booked_cottages.length > 0) {
                        const dateObj = new Date(date);
                        const isToday = date === today.toISOString().split('T')[0];

                        let dateLabel = dateObj.toLocaleDateString('en-US', { 
                            weekday: 'short', 
                            month: 'short', 
                            day: 'numeric' 
                        });

                        if (isToday) dateLabel = '🎯 Today';

                        bookedHTML += `
                            <div class="availability-day">
                                <div class="availability-date ${isToday ? 'today' : ''}">
                                    <strong>${dateLabel}</strong>
                                    <span class="booking-count">${data.booked_cottages.length} booked</span>
                                </div>
                                <div class="cottage-list">
                                    ${data.booked_cottages.map(cottage => `
                                        <div class="cottage-item booked">
                                            <span class="cottage-name">${cottage}</span>
                                            <span class="status-badge">❌ Booked</span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                    }
                });

            if (bookedHTML === '') {
                bookedHTML = '<p style="color: #28a745; text-align: center; padding: 2rem;">✅ No booked cottages for the next 7 days!</p>';
            }

            bookedElement.innerHTML = bookedHTML;
        }

        function displayAvailableCottages() {
            const availableElement = document.getElementById('availableCottagesList');
            if (!availableElement || !dashboardConfig.availability) return;

            let availableHTML = '';
            const today = new Date();

            Object.entries(dashboardConfig.availability)
                .sort(([dateA], [dateB]) => new Date(dateA) - new Date(dateB))
                .forEach(([date, data]) => {
                    if (data.available_cottages.length > 0) {
                        const dateObj = new Date(date);
                        const isToday = date === today.toISOString().split('T')[0];

                        let dateLabel = dateObj.toLocaleDateString('en-US', { 
                            weekday: 'short', 
                            month: 'short', 
                            day: 'numeric' 
                        });

                        if (isToday) dateLabel = '🎯 Today';

                        availableHTML += `
                            <div class="availability-day">
                                <div class="availability-date ${isToday ? 'today' : ''}">
                                    <strong>${dateLabel}</strong>
                                    <span class="available-count">${data.available_cottages.length} available</span>
                                </div>
                                <div class="cottage-list">
                                    ${data.available_cottages.map(cottage => `
                                        <div class="cottage-item available">
                                            <span class="cottage-name">${cottage}</span>
                                            <span class="status-badge">✅ Available</span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                    }
                });

            if (availableHTML === '') {
                availableHTML = '<p style="color: #dc3545; text-align: center; padding: 2rem;">❌ No available cottages for the next 7 days!</p>';
            }

            availableElement.innerHTML = availableHTML;
        }

        // Utility function for delete confirmation
        function confirmDelete(bookingId, customerName) {
            return confirm(`🚨 ARE YOU SURE YOU WANT TO DELETE THIS BOOKING?\n\nBooking ID: ${bookingId}\nCustomer: ${customerName}\n\nThis action cannot be undone!`);
        }

        // Handle window resize for charts
        window.addEventListener('resize', function() {
            if (document.querySelector('#analytics.tab-content.active')) {
                setTimeout(initializeCharts, 250);
            }
        });
    </script>
</body>
</html>