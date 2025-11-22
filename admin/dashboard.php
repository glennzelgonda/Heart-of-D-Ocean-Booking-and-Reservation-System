<?php
session_start();
include '../config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get bookings from database
try {
    $query = "SELECT * FROM bookings ORDER BY timestamp DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate stats
    $totalBookings = count($bookings);
    $pendingBookings = 0;
    $confirmedBookings = 0;
    $cancelledBookings = 0;
    
    foreach ($bookings as $booking) {
        switch ($booking['status']) {
            case 'pending': $pendingBookings++; break;
            case 'confirmed': $confirmedBookings++; break;
            case 'cancelled': $cancelledBookings++; break;
        }
    }
    
    // Analytics: Monthly bookings
    $monthlyQuery = "SELECT 
        MONTHNAME(timestamp) as month, 
        COUNT(*) as count,
        MONTH(timestamp) as month_num
        FROM bookings 
        WHERE YEAR(timestamp) = YEAR(CURDATE())
        GROUP BY MONTH(timestamp), MONTHNAME(timestamp)
        ORDER BY month_num";
    $monthlyStmt = $db->prepare($monthlyQuery);
    $monthlyStmt->execute();
    $monthlyData = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Popular rooms
    $roomsQuery = "SELECT room, COUNT(*) as count FROM bookings GROUP BY room ORDER BY count DESC LIMIT 5";
    $roomsStmt = $db->prepare($roomsQuery);
    $roomsStmt->execute();
    $popularRooms = $roomsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent activity (last 7 days)
    $recentQuery = "SELECT COUNT(*) as count FROM bookings WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $recentStmt = $db->prepare($recentQuery);
    $recentStmt->execute();
    $recentBookings = $recentStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
} catch(PDOException $exception) {
    $error = "Database error: " . $exception->getMessage();
}

// Set default values to prevent undefined variable errors
$totalBookings = $totalBookings ?? 0;
$pendingBookings = $pendingBookings ?? 0;
$confirmedBookings = $confirmedBookings ?? 0;
$cancelledBookings = $cancelledBookings ?? 0;
$recentBookings = $recentBookings ?? 0;
$monthlyData = $monthlyData ?? [];
$popularRooms = $popularRooms ?? [];
$bookings = $bookings ?? [];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: #f5f7fb; color: #333; }
        .admin-header { background: white; padding: 1rem 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .admin-header h1 { color: #667eea; }
        .header-info { display: flex; align-items: center; gap: 1rem; }
        .auto-refresh-info { color: #666; font-size: 0.9rem; background: #e3f2fd; padding: 0.5rem 1rem; border-radius: 20px; display: none; } /* Hidden */
        .logout-btn { background: #e74c3c; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer; text-decoration: none; }
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 20px; }
        
        /* Stats Grid */
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-number { font-size: 2rem; font-weight: bold; color: #667eea; }
        .stat-label { color: #666; font-size: 0.9rem; }
        
        /* Analytics Grid */
        .analytics-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem; }
        .analytics-card { background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .analytics-card h3 { margin-bottom: 1rem; color: #667eea; border-bottom: 2px solid #f0f0f0; padding-bottom: 0.5rem; }
        
        /* Charts */
        .chart-bar { background: #667eea; height: 20px; margin: 5px 0; border-radius: 10px; transition: width 0.3s; }
        .chart-label { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .chart-month { flex: 1; }
        .chart-count { font-weight: bold; color: #667eea; }
        
        /* Popular Rooms */
        .room-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
        .room-name { flex: 1; }
        .room-count { background: #667eea; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem; }
        
        table { width: 100%; background: white; border-collapse: collapse; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #667eea; color: white; font-weight: 600; }
        .status-pending { background: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 4px; font-size: 0.9rem; }
        .status-confirmed { background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 0.9rem; }
        .status-cancelled { background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 4px; font-size: 0.9rem; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; margin: 2px; font-size: 0.9rem; }
        .btn-confirm { background: #28a745; color: white; }
        .btn-cancel { background: #dc3545; color: white; }
        .btn-revert { background: #ffc107; color: black; }
        .btn:hover { opacity: 0.9; }
        .no-bookings { text-align: center; padding: 3rem; color: #666; background: white; border-radius: 10px; }
        .last-update { text-align: center; color: #666; margin-bottom: 1rem; font-size: 0.9rem; }
        .action-message { background: #d4edda; color: #155724; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        
        /* Tabs */
        .tabs { display: flex; margin-bottom: 2rem; background: white; border-radius: 10px; padding: 0.5rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .tab { padding: 1rem 2rem; cursor: pointer; border-radius: 8px; transition: all 0.3s; }
        .tab.active { background: #667eea; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Manual refresh button */
        .refresh-btn { background: #667eea; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer; margin-left: 1rem; }
        .refresh-btn:hover { background: #5a6fd8; }
        
        /* Action buttons container */
        .action-buttons { display: flex; gap: 5px; flex-wrap: wrap; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>Admin Dashboard - Analytics & Bookings</h1>
        <div class="header-info">
            <button class="refresh-btn" onclick="window.location.reload()">🔄 Refresh</button>
            <a href="logout.php" class="logout-btn">Logout</a>
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
        </div>

        <!-- Overview Tab -->
        <div id="overview" class="tab-content active">
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
                    <div class="stat-number"><?php echo $cancelledBookings; ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $recentBookings; ?></div>
                    <div class="stat-label">Last 7 Days</div>
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
                    <p><small>Click the refresh button to check for new bookings</small></p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Cottage</th>
                            <th>Date</th>
                            <th>Guests</th>
                            <th>Status</th>
                            <th>Booked On</th>
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
                                <span class="status-<?php echo $booking['status']; ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y g:i A', strtotime($booking['timestamp'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($booking['status'] === 'pending'): ?>
                                    <form method="POST" action="process_booking.php" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                        <input type="hidden" name="status" value="confirmed">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <button type="submit" class="btn btn-confirm" onclick="return confirm('Confirm this booking?')">Confirm</button>
                                    </form>
                                    <form method="POST" action="process_booking.php" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                        <input type="hidden" name="status" value="cancelled">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <button type="submit" class="btn btn-cancel" onclick="return confirm('Cancel this booking?')">Cancel</button>
                                    </form>
                                    <?php elseif ($booking['status'] === 'confirmed'): ?>
                                    <form method="POST" action="process_booking.php" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                        <input type="hidden" name="status" value="cancelled">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <button type="submit" class="btn btn-cancel" onclick="return confirm('Cancel this confirmed booking?')">Cancel</button>
                                    </form>
                                    <form method="POST" action="process_booking.php" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                        <input type="hidden" name="status" value="pending">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <button type="submit" class="btn btn-revert" onclick="return confirm('Revert to pending status?')">Revert to Pending</button>
                                    </form>
                                    <?php elseif ($booking['status'] === 'cancelled'): ?>
                                    <form method="POST" action="process_booking.php" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                        <input type="hidden" name="status" value="pending">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <button type="submit" class="btn btn-revert" onclick="return confirm('Reactivate this cancelled booking?')">Reactivate</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Analytics Tab -->
        <div id="analytics" class="tab-content">
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
    </div>

    <script>
    function showTab(tabName) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Remove active class from all tabs
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Show selected tab content
        document.getElementById(tabName).classList.add('active');
        
        // Add active class to clicked tab
        event.target.classList.add('active');
    }
    </script>
</body>
</html>