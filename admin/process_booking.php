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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['action_message'] = "Invalid form submission.";
        header("Location: dashboard.php");
        exit();
    }

    // Handle booking deletion - SOFT DELETE
    if (isset($_POST['delete_booking'])) {
        $bookingId = $_POST['booking_id'];
        
        try {
            // Get booking details before deletion for logging
            $query = "SELECT * FROM bookings WHERE booking_id = :booking_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":booking_id", $bookingId);
            $stmt->execute();
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // SOFT DELETE: Mark as deleted instead of removing
            $deleteQuery = "UPDATE bookings SET deleted = 1 WHERE booking_id = :booking_id";
            $stmt = $db->prepare($deleteQuery);
            $stmt->bindParam(":booking_id", $bookingId);
            
            if ($stmt->execute()) {
                // Also remove from cottage_availability
                $deleteAvailability = "DELETE FROM cottage_availability WHERE booking_id = :booking_id";
                $stmt = $db->prepare($deleteAvailability);
                $stmt->bindParam(":booking_id", $bookingId);
                $stmt->execute();
                
                // Log the deletion
                if ($booking) {
                    $deleteLog = "[" . date('Y-m-d H:i:s') . "] DELETED: Booking ID: " . $bookingId . 
                               " | Name: " . $booking['name'] . " | Email: " . $booking['email'] . 
                               " | Cottage: " . $booking['room'] . " | Date: " . $booking['date'] . "\n";
                    file_put_contents('../deletion_log.txt', $deleteLog, FILE_APPEND | LOCK_EX);
                }
                
                $_SESSION['action_message'] = "Booking deleted successfully!";
            }
        } catch(PDOException $exception) {
            $_SESSION['action_message'] = "Error deleting booking: " . $exception->getMessage();
        }
        
        header("Location: dashboard.php");
        exit();
    }

    // Handle status updates (existing code)
    $bookingId = $_POST['booking_id'];
    $newStatus = $_POST['status'];
    
    try {
        $query = "UPDATE bookings SET status = :status WHERE booking_id = :booking_id AND deleted = 0";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":status", $newStatus);
        $stmt->bindParam(":booking_id", $bookingId);
        
        if ($stmt->execute()) {
            // Get booking details for email
            $query = "SELECT * FROM bookings WHERE booking_id = :booking_id AND deleted = 0";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":booking_id", $bookingId);
            $stmt->execute();
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            // If status is confirmed, mark cottage as unavailable
            if ($newStatus === 'confirmed') {
                try {
                    if ($booking) {
                        // Insert into cottage_availability table
                        $availabilityQuery = "INSERT INTO cottage_availability 
                                             (cottage_name, booked_date, booking_id, status) 
                                             VALUES (:cottage, :date, :booking_id, 'confirmed')
                                             ON DUPLICATE KEY UPDATE status = 'confirmed'";
                        $availabilityStmt = $db->prepare($availabilityQuery);
                        $availabilityStmt->bindParam(":cottage", $booking['room']);
                        $availabilityStmt->bindParam(":date", $booking['date']);
                        $availabilityStmt->bindParam(":booking_id", $bookingId);
                        $availabilityStmt->execute();
                    }
                } catch(PDOException $e) {
                    error_log("Cottage availability update failed: " . $e->getMessage());
                }
            }
            
            // If status is cancelled or reverted to pending, make cottage available again
            if ($newStatus === 'cancelled' || $newStatus === 'pending') {
                try {
                    if ($booking) {
                        // Update cottage_availability status to cancelled
                        $availabilityQuery = "UPDATE cottage_availability 
                                             SET status = 'cancelled' 
                                             WHERE cottage_name = :cottage 
                                             AND booked_date = :date 
                                             AND booking_id = :booking_id";
                        $availabilityStmt = $db->prepare($availabilityQuery);
                        $availabilityStmt->bindParam(":cottage", $booking['room']);
                        $availabilityStmt->bindParam(":date", $booking['date']);
                        $availabilityStmt->bindParam(":booking_id", $bookingId);
                        $availabilityStmt->execute();
                    }
                } catch(PDOException $e) {
                    error_log("Cottage availability revert failed: " . $e->getMessage());
                }
            }
            
            if ($booking) {
                // Include email functions
                include '../email_functions.php';
                
                // Send email
                $to = $booking['email'];
                $subject = "";
                $message = "";
                
                if ($newStatus === 'confirmed') {
                    $subject = "Booking CONFIRMED - Heart Of D Ocean Beach Resort";
                    $message = "
                    <html>
                    <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
                        <h2>Booking Confirmed!</h2>
                        <p>Dear " . htmlspecialchars($booking['name']) . ",</p>
                        <p>Great news! Your booking has been <strong style='color: #28a745;'>CONFIRMED</strong>!</p>
                        
                        <div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0;'>
                            <h3>Booking Details:</h3>
                            <p><strong>Booking ID:</strong> " . $booking['booking_id'] . "</p>
                            <p><strong>Cottage:</strong> " . htmlspecialchars($booking['room']) . "</p>
                            <p><strong>Check-in Date:</strong> " . $booking['date'] . "</p>
                            <p><strong>Number of Guests:</strong> " . $booking['guests'] . "</p>
                            <p><strong>Status:</strong> <span style='color: #28a745; font-weight: bold;'>CONFIRMED</span></p>
                        </div>
                        
                        <p>We're excited to welcome you to Heart Of D Ocean Beach Resort!</p>
                        <p>If you have any questions, please contact us at heartofdocean2005@yahoo.com or call us.</p>
                        
                        <br>
                        <p>Best regards,<br>The Heart Of D Ocean Team</p>
                    </body>
                    </html>
                    ";
                } else {
                    $subject = "Booking Update - Heart Of D Ocean Beach Resort";
                    $message = "
                    <html>
                    <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
                        <h2>Booking Status Update</h2>
                        <p>Dear " . htmlspecialchars($booking['name']) . ",</p>
                        <p>Your booking status has been updated to: <strong>" . strtoupper($newStatus) . "</strong></p>
                        
                        <div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0;'>
                            <h3>Booking Details:</h3>
                            <p><strong>Booking ID:</strong> " . $booking['booking_id'] . "</p>
                            <p><strong>Cottage:</strong> " . htmlspecialchars($booking['room']) . "</p>
                            <p><strong>Check-in Date:</strong> " . $booking['date'] . "</p>
                            <p><strong>Status:</strong> " . strtoupper($newStatus) . "</p>
                        </div>
                        
                        <p>Please contact us at heartofdocean2005@yahoo.com if you have any questions.</p>
                        
                        <br>
                        <p>Best regards,<br>The Heart Of D Ocean Team</p>
                    </body>
                    </html>
                    ";
                }
                
                // Send the email
                $emailSent = sendResortEmail($to, $subject, $message);
                
                if ($emailSent) {
                    $_SESSION['action_message'] = "Booking {$newStatus} successfully! Email sent to customer.";
                } else {
                    $_SESSION['action_message'] = "Booking {$newStatus} successfully! (Email notification queued - customer will be notified)";
                }
                
                // Log the attempt
                $emailLog = "[" . date('Y-m-d H:i:s') . "] STATUS: $newStatus | To: $to | Sent: " . ($emailSent ? "YES" : "NO") . "\n";
                file_put_contents('../email_log.txt', $emailLog, FILE_APPEND | LOCK_EX);
            }
        }
    } catch(PDOException $exception) {
        $_SESSION['action_message'] = "Error: " . $exception->getMessage();
    }
    
    header("Location: dashboard.php");
    exit();
}
?>