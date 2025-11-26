<?php
session_start();
include 'config.php';

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Function to check cottage availability for date range
function checkCottageAvailability($db, $cottage, $checkin_date, $checkout_date) {
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
        
        return $result['count'] == 0; // True if available, False if booked
    } catch(PDOException $e) {
        error_log("Availability check error: " . $e->getMessage());
        return false; // If there's an error, assume not available
    }
}

// Function to calculate total price based on nights and cottage
function calculateTotalPrice($cottage, $nights) {
    $prices = [
        "White House — ₱30,000" => 30000,
        "Penthouse — ₱12,800" => 12800,
        "Aqua Class — ₱11,800" => 11800,
        "Heartsuite — ₱11,800" => 11800,
        "Steph's Skylounge 842/844 — ₱11,800" => 11800,
        "Steph's 848 — ₱10,800" => 10800,
        "Steph's 846 — ₱10,000" => 10000,
        "Concierge 817 — ₱9,800" => 9800,
        "De Luxe — ₱8,800" => 8800,
        "Concierge 815/819 — ₱8,800" => 8800,
        "Premium 840 — ₱8,800" => 8800,
        "Beatrice A — ₱7,800" => 7800,
        "Premium 838 — ₱7,800" => 7800,
        "Beatrice B — ₱6,800" => 6800,
        "Giant Kubo — ₱6,800" => 6800,
        "Seaside (Whole) — ₱6,800" => 6800,
        "Seaside (Half) — ₱3,400" => 3400,
        "Bamboo Kubo — ₱2,800" => 2800
    ];
    
    $pricePerNight = $prices[$cottage] ?? 0;
    return $pricePerNight * $nights;
}

// Enhanced email function with better error handling
function sendResortEmail($to, $subject, $message) {
    // Remove any emojis or special characters from subject
    $subject = preg_replace('/[^\x20-\x7E]/', '', $subject);
    
    // Enhanced headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Heart Of D Ocean Resort <noreply@heartofdocean.com>" . "\r\n";
    $headers .= "Reply-To: heartofdocean2005@yahoo.com" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    $headers .= "X-Priority: 1 (Highest)" . "\r\n";
    $headers .= "X-MSMail-Priority: High" . "\r\n";
    
    // Try to send email
    try {
        $result = mail($to, $subject, $message, $headers);
        
        // Log email attempt
        $email_log = date('Y-m-d H:i:s') . " - Email to: $to - Subject: $subject - Sent: " . ($result ? 'YES' : 'NO') . "\n";
        file_put_contents('email_log.txt', $email_log, FILE_APPEND | LOCK_EX);
        
        return $result;
    } catch (Exception $e) {
        error_log("Email error: " . $e->getMessage());
        $email_log = date('Y-m-d H:i:s') . " - Email ERROR to: $to - Error: " . $e->getMessage() . "\n";
        file_put_contents('email_log.txt', $email_log, FILE_APPEND | LOCK_EX);
        return false;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid form submission. Please refresh the page and try again.";
    } else {
        // Sanitize and validate input
        $name = trim($_POST['name']);
        $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
        $phone = trim($_POST['phone']);
        $room = $_POST['room'];
        $checkin_date = $_POST['checkin_date'];
        $checkout_date = $_POST['checkout_date'];
        $guests = intval($_POST['guests']);
        $children = intval($_POST['children'] ?? 0);
        $paymentMethod = $_POST['paymentMethod'] ?? '';
        $gcashName = trim($_POST['gcashName'] ?? '');
        $gcashNumber = trim($_POST['gcashNumber'] ?? '');
        $paymentReference = trim($_POST['paymentReference'] ?? '');
        $paymentDate = $_POST['paymentDate'] ?? '';
        
        $booking_id = 'RESORT' . time() . rand(100, 999);

        // Validate required fields
        if (empty($name) || !$email || empty($phone) || empty($room) || empty($checkin_date) || empty($checkout_date)) {
            $error = "Please fill in all required fields.";
        } else {
            // Calculate nights
            $checkin = new DateTime($checkin_date);
            $checkout = new DateTime($checkout_date);
            $nights = $checkin->diff($checkout)->days;
            
            if ($nights < 1) {
                $error = "Check-out date must be after check-in date.";
            } elseif ($guests < 1 || $guests > 50) {
                $error = "Number of adults must be between 1 and 50.";
            } elseif ($children < 0 || $children > 20) {
                $error = "Number of children must be between 0 and 20.";
            } else {
                // Check if cottage is available for the selected date range
                if (!checkCottageAvailability($db, $room, $checkin_date, $checkout_date)) {
                    $error = "Sorry, the selected cottage '{$room}' is not available for the selected dates. Please choose different dates or cottage.";
                } else {
                    try {
                        // Calculate total price
                        $total_price = calculateTotalPrice($room, $nights);
                        
                        // DEBUG: Log the calculated values
                        error_log("Booking Calculation - Room: $room, Nights: $nights, Total Price: $total_price");
                        
                        // FIXED: Updated query with all required columns
                        $query = "INSERT INTO bookings (booking_id, name, email, phone, room, date, checkout_date, guests, children, nights, total_price, payment_method, gcash_name, gcash_number, payment_reference, payment_date, status) 
                                  VALUES (:booking_id, :name, :email, :phone, :room, :date, :checkout_date, :guests, :children, :nights, :total_price, :payment_method, :gcash_name, :gcash_number, :payment_reference, :payment_date, 'pending')";
                        
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(":booking_id", $booking_id);
                        $stmt->bindParam(":name", $name);
                        $stmt->bindParam(":email", $email);
                        $stmt->bindParam(":phone", $phone);
                        $stmt->bindParam(":room", $room);
                        $stmt->bindParam(":date", $checkin_date);
                        $stmt->bindParam(":checkout_date", $checkout_date);
                        $stmt->bindParam(":guests", $guests);
                        $stmt->bindParam(":children", $children);
                        $stmt->bindParam(":nights", $nights);
                        $stmt->bindParam(":total_price", $total_price);
                        $stmt->bindParam(":payment_method", $paymentMethod);
                        $stmt->bindParam(":gcash_name", $gcashName);
                        $stmt->bindParam(":gcash_number", $gcashNumber);
                        $stmt->bindParam(":payment_reference", $paymentReference);
                        $stmt->bindParam(":payment_date", $paymentDate);
                        
                        if ($stmt->execute()) {
                            // DEBUG: Log successful save
                            error_log("Booking saved successfully - ID: $booking_id, Price: $total_price");
                            
                            // Send confirmation email
                            $subject = "Booking Received - Heart Of D Ocean Beach Resort";
                            $message = "
                            <html>
                            <head>
                                <style>
                                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                    .header { background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                                    .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 10px 10px; }
                                    .booking-details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #3498db; }
                                    .status-pending { color: #f39c12; font-weight: bold; }
                                    .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
                                </style>
                            </head>
                            <body>
                                <div class='container'>
                                    <div class='header'>
                                        <h1>🎉 Booking Received!</h1>
                                    </div>
                                    <div class='content'>
                                        <p>Dear $name,</p>
                                        <p>Thank you for choosing Heart Of D Ocean Beach Resort! We have received your booking request and will process it shortly.</p>
                                        
                                        <div class='booking-details'>
                                            <h3>📋 Booking Details:</h3>
                                            <p><strong>Booking ID:</strong> $booking_id</p>
                                            <p><strong>Cottage:</strong> $room</p>
                                            <p><strong>Check-in Date:</strong> $checkin_date</p>
                                            <p><strong>Check-out Date:</strong> $checkout_date</p>
                                            <p><strong>Nights:</strong> $nights</p>
                                            <p><strong>Total Price:</strong> ₱" . number_format($total_price, 2) . "</p>
                                            <p><strong>Number of Adults:</strong> $guests</p>
                                            <p><strong>Number of Children:</strong> $children</p>
                                            <p><strong>Contact:</strong> $phone</p>
                                            <p><strong>Payment Method:</strong> " . ($paymentMethod === 'pay-now' ? 'GCash' : 'On-site Payment') . "</p>
                                            <p><strong>Status:</strong> <span class='status-pending'>PENDING CONFIRMATION</span></p>
                                        </div>
                                        
                                        <h4>📝 What happens next?</h4>
                                        <ul>
                                            <li>We will review your booking within 24 hours</li>
                                            <li>You'll receive a confirmation email once approved</li>
                                            <li>Payment instructions will be provided upon confirmation</li>
                                            <li>For questions, contact: heartofdocean2005@yahoo.com</li>
                                        </ul>
                                        
                                        <div class='footer'>
                                            <p>Best regards,<br><strong>The Heart Of D Ocean Team</strong></p>
                                            <p>📞 Contact: +63 XXX XXX XXXX<br>
                                            📍 Location: Beach Resort Location</p>
                                        </div>
                                    </div>
                                </div>
                            </body>
                            </html>
                            ";
                            
                            // Send email using our function
                            $emailSent = sendResortEmail($email, $subject, $message);
                            
                            // Also send notification to admin
                            $adminSubject = "New Booking - $booking_id";
                            $adminMessage = "New booking received:\n\nBooking ID: $booking_id\nName: $name\nEmail: $email\nCottage: $room\nCheck-in: $checkin_date\nCheck-out: $checkout_date\nNights: $nights\nTotal: ₱" . number_format($total_price, 2) . "\nAdults: $guests\nChildren: $children\nPayment Method: " . ($paymentMethod === 'pay-now' ? 'GCash' : 'On-site');
                            sendResortEmail('heartofdocean2005@yahoo.com', $adminSubject, $adminMessage);
                            
                            $_SESSION['booking_success'] = true;
                            $_SESSION['booking_id'] = $booking_id;
                            $_SESSION['nights'] = $nights;
                            $_SESSION['total_price'] = $total_price;
                            $_SESSION['booking_name'] = $name;
                            $_SESSION['booking_email'] = $email;
                            
                            // Log booking attempt
                            $logMessage = "[" . date('Y-m-d H:i:s') . "] NEW BOOKING | ID: $booking_id | Name: $name | Email: $email | Room: $room | Nights: $nights | Total: ₱$total_price | Payment: $paymentMethod | Email Sent: " . ($emailSent ? 'YES' : 'NO') . "\n";
                            file_put_contents('booking_log.txt', $logMessage, FILE_APPEND | LOCK_EX);
                            
                            // Regenerate CSRF token for security
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                            
                            header("Location: booking.php?success=1");
                            exit();
                        } else {
                            // DEBUG: Log failed save
                            error_log("Booking failed to save - ID: $booking_id");
                            $error = "We're experiencing technical difficulties. Please try again later.";
                        }
                    } catch(PDOException $exception) {
                        $error = "We're experiencing technical difficulties. Please try again later. Error: " . $exception->getMessage();
                        error_log("Booking database error: " . $exception->getMessage());
                    }
                }
            }
        }
    }
}

// Display success message
$successMessage = '';
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $booking_id = $_SESSION['booking_id'] ?? '';
    $nights = $_SESSION['nights'] ?? 1;
    $total_price = $_SESSION['total_price'] ?? 0;
    $name = $_SESSION['booking_name'] ?? '';
    $email = $_SESSION['booking_email'] ?? '';
    
    $successMessage = "
        <div class='success-container'>
            <div class='success-icon'>🎉</div>
            <h2>Booking Submitted Successfully!</h2>
            <div class='booking-summary'>
                <p><strong>Booking ID:</strong> <span>$booking_id</span></p>
                <p><strong>Name:</strong> $name</p>
                <p><strong>Email:</strong> $email</p>
                <p><strong>Stay Duration:</strong> $nights night" . ($nights > 1 ? 's' : '') . "</p>
                <p><strong>Total Amount:</strong> ₱" . number_format($total_price, 2) . "</p>
            </div>
            <p class='confirmation-note'>
                We've sent a confirmation email to <strong>$email</strong>. 
                Please check your inbox (and spam folder).
            </p>
            <div class='action-buttons'>
                <a href='index.php' class='btn'>Return to Home</a>
                <a href='booking.php' class='btn ghost'>Make Another Booking</a>
            </div>
        </div>
    ";
    
    // Clear session variables
    unset($_SESSION['booking_id'], $_SESSION['nights'], $_SESSION['total_price'], $_SESSION['booking_name'], $_SESSION['booking_email']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Booking - Heart Of D' Ocean Beach Resort</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <a class="logo" href="index.html">Heart Of D' Ocean Beach Resort</a>
      <nav class="nav" id="mainNav">
        <button class="close-menu" id="closeMenu">✕</button>
        <a href="index.php">Home</a>
        <a href="rooms.php">Cottages</a>
        <a href="gallery.php">Gallery</a>
        <a href="booking.php" class="cta">Book Now</a>
        <button id="darkToggle" class="icon-btn" aria-label="Toggle dark mode">🌙</button>
      </nav>
      <button id="menuBtn" class="hamburger" aria-label="Toggle menu">☰</button>
    </div>
  </header>

  <main class="container booking-page">
    <h1>Make a Reservation</h1>

    <<?php if ($successMessage): ?>
    <div class="success-container">
        <?php echo $successMessage; ?>
    </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
    <div class="error-message">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <form id="bookingForm" method="POST" action="booking.php" class="booking-form" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
      
      <div class="form-row">
        <div class="form-group">
          <label for="checkin">Check-in Date *</label>
          <input type="date" id="checkin" required>
        </div>
        <div class="form-group">
          <label for="checkout">Check-out Date *</label>
          <input type="date" id="checkout" required>
        </div>
      </div>

      <div class="form-group">
        <label for="accommodation">Accommodation Type *</label>
        <select id="accommodation" required>
          <option value="">Select accommodation...</option>
          <optgroup label="Premium Cottages">
            <option value="White House — ₱30,000" data-price="30000" <?php echo (isset($_POST['room']) && $_POST['room'] == 'White House — ₱30,000') ? 'selected' : ''; ?>>White House - ₱30,000/night</option>
            <option value="Penthouse — ₱12,800" data-price="12800" <?php echo (isset($_POST['room']) && $_POST['room'] == 'Penthouse — ₱12,800') ? 'selected' : ''; ?>>Penthouse - ₱12,800/night</option>
            <option value="Aqua Class — ₱11,800" data-price="11800" <?php echo (isset($_POST['room']) && $_POST['room'] == 'Aqua Class — ₱11,800') ? 'selected' : ''; ?>>Aqua Class - ₱11,800/night</option>
            <option value="Heartsuite — ₱11,800" data-price="11800" <?php echo (isset($_POST['room']) && $_POST['room'] == 'Heartsuite — ₱11,800') ? 'selected' : ''; ?>>Heartsuite - ₱11,800/night</option>
            <option value="Steph's Skylounge 842/844 — ₱11,800" data-price="11800" <?php echo (isset($_POST['room']) && $_POST['room'] == "Steph's Skylounge 842/844 — ₱11,800") ? 'selected' : ''; ?>>Steph's Skylounge - ₱11,800/night</option>
          </optgroup>
          <optgroup label="Standard Cottages">
            <option value="Steph's 848 — ₱10,800" data-price="10800" <?php echo (isset($_POST['room']) && $_POST['room'] == "Steph's 848 — ₱10,800") ? 'selected' : ''; ?>>Steph's 848 - ₱10,800/night</option>
            <option value="Steph's 846 — ₱10,000" data-price="10000" <?php echo (isset($_POST['room']) && $_POST['room'] == "Steph's 846 — ₱10,000") ? 'selected' : ''; ?>>Steph's 846 - ₱10,000/night</option>
            <option value="Concierge 817 — ₱9,800" data-price="9800" <?php echo (isset($_POST['room']) && $_POST['room'] == 'Concierge 817 — ₱9,800') ? 'selected' : ''; ?>>Concierge 817 - ₱9,800/night</option>
            <option value="De Luxe — ₱8,800" data-price="8800" <?php echo (isset($_POST['room']) && $_POST['room'] == 'De Luxe — ₱8,800') ? 'selected' : ''; ?>>De Luxe - ₱8,800/night</option>
            <option value="Concierge 815/819 — ₱8,800" data-price="8800" <?php echo (isset($_POST['room']) && $_POST['room'] == 'Concierge 815/819 — ₱8,800') ? 'selected' : ''; ?>>Concierge 815/819 - ₱8,800/night</option>
            <option value="Premium 840 — ₱8,800" data-price="8800" <?php echo (isset($_POST['room']) && $_POST['room'] == 'Premium 840 — ₱8,800') ? 'selected' : ''; ?>>Premium 840 - ₱8,800/night</option>
            <option value="Beatrice A — ₱7,800" data-price="7800" <?php echo (isset($_POST['room']) && $_POST['room'] == 'Beatrice A — ₱7,800') ? 'selected' : ''; ?>>Beatrice A - ₱7,800/night</option>
            <option value="Premium 838 — ₱7,800" data-price="7800" <?php echo (isset($_POST['room']) && $_POST['room'] == 'Premium 838 — ₱7,800') ? 'selected' : ''; ?>>Premium 838 - ₱7,800/night</option>
            <option value="Giant Kubo — ₱6,800" data-price="6800" <?php echo (isset($_POST['room']) && $_POST['room'] == 'Giant Kubo — ₱6,800') ? 'selected' : ''; ?>>Giant Kubo - ₱6,800/night</option>
            <option value="Seaside (Whole) — ₱6,800" data-price="6800" <?php echo (isset($_POST['room']) && $_POST['room'] == 'Seaside (Whole) — ₱6,800') ? 'selected' : ''; ?>>Seaside (Whole) - ₱6,800/night</option>
            <option value="Beatrice B — ₱6,800" data-price="6800" <?php echo (isset($_POST['room']) && $_POST['room'] == 'Beatrice B — ₱6,800') ? 'selected' : ''; ?>>Beatrice B - ₱6,800/night</option>
          </optgroup>
          <optgroup label="Budget Cottages">
            <option value="Seaside (Half) — ₱3,400" data-price="3400" <?php echo (isset($_POST['room']) && $_POST['room'] == 'Seaside (Half) — ₱3,400') ? 'selected' : ''; ?>>Seaside (Half) - ₱3,400/night</option>
            <option value="Bamboo Kubo — ₱2,800" data-price="2800" <?php echo (isset($_POST['room']) && $_POST['room'] == 'Bamboo Kubo — ₱2,800') ? 'selected' : ''; ?>>Bamboo Kubo - ₱2,800/night</option>
          </optgroup>
        </select>
      </div>

            <div class="form-row">
          <div class="form-group">
            <label for="name">Full Name *</label>
            <input type="text" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="email">Email *</label>
            <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
          </div>
          <div class="form-group">
            <label for="phone">Phone Number *</label>
            <input type="tel" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="adults">Adults *</label>
            <input type="number" id="guests" name="guests" min="1" max="50" value="<?php echo isset($_POST['guests']) ? $_POST['guests'] : '2'; ?>" required>
          </div>
          <div class="form-group">
            <label for="children">Children</label>
            <input type="number" id="children" name="children" min="0" max="20" value="<?php echo isset($_POST['children']) ? $_POST['children'] : '0'; ?>">
          </div>
        </div>

      <!-- NEW PAYMENT METHOD SELECTION -->
      <div class="form-group">
        <label for="paymentMethod">Payment Method *</label>
        <select id="paymentMethod" name="paymentMethod" required onchange="togglePaymentOption()">
          <option value="">Select payment method...</option>
          <option value="pay-now" <?php echo (isset($_POST['paymentMethod']) && $_POST['paymentMethod'] == 'pay-now') ? 'selected' : ''; ?>>Pay Now (GCash)</option>
          <option value="face-to-face" <?php echo (isset($_POST['paymentMethod']) && $_POST['paymentMethod'] == 'face-to-face') ? 'selected' : ''; ?>>Pay Face to Face</option>
        </select>
      </div>

      <!-- QR Code Section (initially hidden) -->
      <div id="qrSection" class="qr-section" style="display: none;">
        <h4>GCash QR Code</h4>
        <div class="qr-code">
          <!-- Replace with your actual GCash QR code -->
          <img src="images&vids/qrcode.jpg" alt="GCash QR Code">
        </div>
        <p><strong>Amount:</strong> <span id="qrAmount">₱0</span></p>
        <p><strong>Reference No:</strong> <span id="qrReference">RESORT000000</span></p>
        
        <div class="upload-section">
          <label for="receiptUpload">Upload Payment Receipt (Screenshot) *</label>
          <input type="file" id="receiptUpload" accept="image/*" capture="environment" required>
          <small>Take a screenshot of your GCash payment and upload it here</small>
        </div>

        <!-- PAYMENT DETAILS FORM -->
        <div class="payment-details-form">
          <h5>Payment Details</h5>
          
          <div class="form-group">
            <label for="gcashName">Name as it appears in GCash *</label>
            <input type="text" id="gcashName" name="gcashName" required placeholder="Enter your name as shown in GCash">
          </div>

          <div class="form-group">
            <label for="gcashNumber">GCash Mobile Number Used *</label>
            <input type="tel" id="gcashNumber" name="gcashNumber" value="<?php echo isset($_POST['gcashNumber']) ? htmlspecialchars($_POST['gcashNumber']) : ''; ?>" placeholder="0917XXXXXXX">
          </div>

          <div class="form-group">
            <label for="paymentReference">GCash Reference Number *</label>
            <input type="text" id="paymentReference" name="paymentReference" value="<?php echo isset($_POST['paymentReference']) ? htmlspecialchars($_POST['paymentReference']) : ''; ?>" placeholder="Enter reference number from GCash">
            <small>This is the transaction reference number from your GCash receipt</small>
          </div>

          <div class="form-group">
            <label for="paymentDate">Payment Date *</label>
            <input type="date" id="paymentDate" name="paymentDate" value="<?php echo isset($_POST['paymentDate']) ? $_POST['paymentDate'] : ''; ?>">
          </div>
        </div>
      </div>

      <!-- Price Breakdown -->
      <div class="price-breakdown" id="priceBreakdown">
        <h3>Price Breakdown</h3>
        <div class="breakdown-item">
          <span>Accommodation:</span>
          <span id="accommodationPrice">₱0</span>
        </div>
        <div class="breakdown-item">
          <span>Number of nights:</span>
          <span id="nightsCount">0</span>
        </div>
        <div class="breakdown-item total">
          <span>Total Amount:</span>
          <span id="totalAmount">₱0</span>
        </div>
      </div>

      <!-- Availability Message -->
      <div id="availabilityMessage" class="availability-message"></div>

      <!-- Loading Spinner -->
      <div class="loading-spinner" id="availabilityLoading">
        <div class="spinner"></div> Checking availability...
      </div>

      <div class="form-actions">
        <button type="submit" id="submitBtn" class="btn primary" disabled>Book Now</button>
        <button type="button" id="resetForm" class="btn primary">Reset Form</button>
      </div>
    </form>
  </main>

  <footer class="footer">
    <div class="container">
      <div class="row">
        <div class="footer-col">
          <h4>Company</h4>
          <ul>
            <li><a href="about.php" class="footer-link">About Us</a></li>
            <li><a href="contact.php" class="footer-link">Contact</a></li>
          </ul>
        </div>
        
        <div class="footer-col">
          <h4>Help</h4>
          <ul>
            <li><a href="faq.php" class="footer-link">FAQ</a></li>
            <li><a href="faq.php#payment" class="footer-link">Payment Options</a></li>
            <li><a href="faq.php#cancellation" class="footer-link">Cancellation Policy</a></li>
            <li><a href="faq.php#terms" class="footer-link">Terms & Conditions</a></li>
          </ul>
        </div>
        
        <div class="footer-col">
          <h4>Reach Us</h4>
          <div class="social-links">
            <a href="https://www.facebook.com/messages/t/233219370026088" target="_blank">
              <i class="fab fa-facebook-messenger"></i>
            </a>
            <a href="https://www.facebook.com/Heartofdoceanbeachresort/#" target="_blank">
              <i class="fab fa-facebook"></i>
            </a>
            <a href="mailto:heartofdocean2005@yahoo.com">
              <i class="fas fa-envelope"></i>
            </a>
            <a href="https://maps.app.goo.gl/q67iwWwZYtNH51rN8" target="_blank">
              <i class="fas fa-location-dot"></i>
            </a>
          </div>
          
          <!-- Contact Info -->
          <div class="contact-info">
            <p>📍 Nonong Casto, Lemery, Batangas, Philippines</p>
            <p>📞 0917 528 3832</p>
            <p>⏰ Open 24/7</p>
          </div>
        </div>
      </div>
      
      <!-- Copyright -->
      <div class="footer-bottom">
        <p>&copy; 2024 Heart Of D' Ocean Beach Resort. All rights reserved.</p>
      </div>
    </div>
  </footer>

  <script src="main.js"></script>
</body>
</html>