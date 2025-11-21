<?php
session_start();
include '../config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId = $_POST['booking_id'];
    $newStatus = $_POST['status'];
    
    try {
        $query = "UPDATE bookings SET status = :status WHERE booking_id = :booking_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":status", $newStatus);
        $stmt->bindParam(":booking_id", $bookingId);
        
        if ($stmt->execute()) {
            // Get booking details for email
            $query = "SELECT * FROM bookings WHERE booking_id = :booking_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":booking_id", $bookingId);
            $stmt->execute();
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Send REAL email
            $to = $booking['email'];
            $subject = "";
            $message = "";
            
            if ($newStatus === 'confirmed') {
                $subject = "🎉 Booking CONFIRMED - Heart Of D' Ocean Beach Resort";
                $message = "
                <html>
                <body>
                    <h2>Booking Confirmed! 🏝️</h2>
                    <p>Dear <strong>" . $booking['name'] . "</strong>,</p>
                    <p>Great news! Your booking has been <strong style='color: #28a745;'>CONFIRMED</strong>!</p>
                    
                    <div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0;'>
                        <h3>Booking Details:</h3>
                        <p><strong>Booking ID:</strong> " . $booking['booking_id'] . "</p>
                        <p><strong>Cottage:</strong> " . $booking['room'] . "</p>
                        <p><strong>Check-in Date:</strong> " . $booking['date'] . "</p>
                        <p><strong>Number of Guests:</strong> " . $booking['guests'] . "</p>
                        <p><strong>Status:</strong> <span style='color: #28a745; font-weight: bold;'>CONFIRMED ✅</span></p>
                    </div>
                    
                    <p>We're excited to welcome you to Heart Of D' Ocean Beach Resort!</p>
                    <p>If you have any questions, please reply to this email.</p>
                    
                    <br>
                    <p>Best regards,<br>The Heart Of D' Ocean Team</p>
                </body>
                </html>
                ";
            } else {
                $subject = "Booking Update - Heart Of D' Ocean Beach Resort";
                $message = "
                <html>
                <body>
                    <h2>Booking Status Update</h2>
                    <p>Dear <strong>" . $booking['name'] . "</strong>,</p>
                    <p>Your booking status has been updated to: <strong>" . strtoupper($newStatus) . "</strong></p>
                    
                    <div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0;'>
                        <h3>Booking Details:</h3>
                        <p><strong>Booking ID:</strong> " . $booking['booking_id'] . "</p>
                        <p><strong>Cottage:</strong> " . $booking['room'] . "</p>
                        <p><strong>Check-in Date:</strong> " . $booking['date'] . "</p>
                        <p><strong>Status:</strong> " . strtoupper($newStatus) . "</p>
                    </div>
                    
                    <p>Please contact us if you have any questions.</p>
                    
                    <br>
                    <p>Best regards,<br>The Heart Of D' Ocean Team</p>
                </body>
                </html>
                ";
            }
            
            // Email headers for HTML email
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Heart Of D' Ocean Beach Resort <noreply@heart-ocean-resort.com>" . "\r\n";
            $headers .= "Reply-To: info@heart-ocean-resort.com" . "\r\n";
            
            // Send email
            if (mail($to, $subject, $message, $headers)) {
                $_SESSION['action_message'] = "Booking {$newStatus} successfully! Email sent to customer.";
            } else {
                $_SESSION['action_message'] = "Booking {$newStatus} but email failed to send.";
            }
            
            // Log email attempt
            $emailLog = "[" . date('Y-m-d H:i:s') . "] STATUS: $newStatus\nTo: $to\nSubject: $subject\nSent: " . (mail($to, $subject, $message, $headers) ? "YES" : "NO") . "\n" . str_repeat("-", 50) . "\n\n";
            file_put_contents('../email_log.txt', $emailLog, FILE_APPEND | LOCK_EX);
            
        }
    } catch(PDOException $exception) {
        $_SESSION['action_message'] = "Error: " . $exception->getMessage();
    }
    
    header("Location: dashboard.php");
    exit();
}
?>