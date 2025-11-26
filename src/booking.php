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

// Function to check cottage availability
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
        
        return $result['count'] == 0;
    } catch(PDOException $e) {
        error_log("Availability check error: " . $e->getMessage());
        return false;
    }
}

// Function to calculate total price
function calculateTotalPrice($cottage, $nights) {
    $prices = [
        "white-house" => 30000,
        "penthouse" => 12800,
        "aqua-class" => 11800,
        "heartsuite" => 11800,
        "stephs-skylounge" => 11800,
        "stephs-848" => 10800,
        "stephs-846" => 10000,
        "concierge-817" => 9800,
        "de-luxe" => 8800,
        "concierge-815-819" => 8800,
        "premium-840" => 8800,
        "beatrice-a" => 7800,
        "premium-838" => 7800,
        "giant-kubo" => 6800,
        "seaside-whole" => 6800,
        "beatrice-b" => 6800,
        "seaside-half" => 3400,
        "bamboo-kubo" => 2800
    ];
    
    $pricePerNight = $prices[$cottage] ?? 0;
    return $pricePerNight * $nights;
}

// Email function
function sendResortEmail($to, $subject, $message) {
    $subject = preg_replace('/[^\x20-\x7E]/', '', $subject);
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Heart Of D Ocean Resort <noreply@heartofdocean.com>" . "\r\n";
    $headers .= "Reply-To: heartofdocean2005@yahoo.com" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    try {
        $result = mail($to, $subject, $message, $headers);
        return $result;
    } catch (Exception $e) {
        error_log("Email error: " . $e->getMessage());
        return false;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid form submission. Please refresh the page and try again.";
    } else {
        // Sanitize input
        $name = trim($_POST['name']);
        $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
        $phone = trim($_POST['phone']);
        $accommodation = $_POST['accommodation'];
        $checkin_date = $_POST['checkin'];
        $checkout_date = $_POST['checkout'];
        $adults = intval($_POST['adults']);
        $children = intval($_POST['children'] ?? 0);
        $paymentMethod = $_POST['paymentMethod'] ?? '';
        $gcashName = trim($_POST['gcashName'] ?? '');
        $gcashNumber = trim($_POST['gcashNumber'] ?? '');
        $paymentReference = trim($_POST['paymentReference'] ?? '');
        $paymentDate = $_POST['paymentDate'] ?? '';
        
        $booking_id = 'RESORT' . time() . rand(100, 999);

        // Validate fields
        if (empty($name) || !$email || empty($phone) || empty($accommodation) || empty($checkin_date) || empty($checkout_date)) {
            $error = "Please fill in all required fields.";
        } else {
            // Calculate nights
            $checkin = new DateTime($checkin_date);
            $checkout = new DateTime($checkout_date);
            $nights = $checkin->diff($checkout)->days;
            
            if ($nights < 1) {
                $error = "Check-out date must be after check-in date.";
            } else {
                // Check availability
                if (!checkCottageAvailability($db, $accommodation, $checkin_date, $checkout_date)) {
                    $error = "Sorry, the selected cottage is not available for the selected dates. Please choose different dates or cottage.";
                } else {
                    try {
                        // Calculate total price
                        $total_price = calculateTotalPrice($accommodation, $nights);
                        
                        // Insert into database
                        $query = "INSERT INTO bookings (booking_id, name, email, phone, room, date, checkout_date, guests, children, nights, total_price, payment_method, gcash_name, gcash_number, payment_reference, payment_date, status) 
                                  VALUES (:booking_id, :name, :email, :phone, :room, :date, :checkout_date, :guests, :children, :nights, :total_price, :payment_method, :gcash_name, :gcash_number, :payment_reference, :payment_date, 'pending')";
                        
                        $stmt = $db->prepare($query);
                        $stmt->execute([
                            ':booking_id' => $booking_id,
                            ':name' => $name,
                            ':email' => $email,
                            ':phone' => $phone,
                            ':room' => $accommodation,
                            ':date' => $checkin_date,
                            ':checkout_date' => $checkout_date,
                            ':guests' => $adults,
                            ':children' => $children,
                            ':nights' => $nights,
                            ':total_price' => $total_price,
                            ':payment_method' => $paymentMethod,
                            ':gcash_name' => $gcashName,
                            ':gcash_number' => $gcashNumber,
                            ':payment_reference' => $paymentReference,
                            ':payment_date' => $paymentDate
                        ]);

                        if ($stmt->rowCount() > 0) {
                            // Send confirmation emails
                            $subject = "Booking Received - Heart Of D Ocean Beach Resort";
                            $message = "
                            <html>
                            <head>
                                <style>
                                    body { font-family: Arial, sans-serif; }
                                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                    .header { background: #3498db; color: white; padding: 20px; text-align: center; }
                                    .content { background: #f8f9fa; padding: 20px; }
                                    .booking-details { background: white; padding: 15px; margin: 15px 0; }
                                </style>
                            </head>
                            <body>
                                <div class='container'>
                                    <div class='header'>
                                        <h1>Booking Received!</h1>
                                    </div>
                                    <div class='content'>
                                        <p>Dear $name,</p>
                                        <p>Thank you for choosing Heart Of D Ocean Beach Resort!</p>
                                        <div class='booking-details'>
                                            <h3>Booking Details:</h3>
                                            <p><strong>Booking ID:</strong> $booking_id</p>
                                            <p><strong>Check-in Date:</strong> $checkin_date</p>
                                            <p><strong>Check-out Date:</strong> $checkout_date</p>
                                            <p><strong>Nights:</strong> $nights</p>
                                            <p><strong>Total Price:</strong> ₱" . number_format($total_price, 2) . "</p>
                                            <p><strong>Status:</strong> PENDING CONFIRMATION</p>
                                        </div>
                                    </div>
                                </div>
                            </body>
                            </html>
                            ";
                            
                            sendResortEmail($email, $subject, $message);
                            
                            $_SESSION['booking_id'] = $booking_id;
                            $_SESSION['nights'] = $nights;
                            $_SESSION['total_price'] = $total_price;
                            $_SESSION['booking_name'] = $name;
                            $_SESSION['booking_email'] = $email;
                            
                            header("Location: booking.php?success=1");
                            exit();
                        }
                    } catch(PDOException $exception) {
                        $error = "We're experiencing technical difficulties. Please try again later.";
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
      <a class="logo" href="index.php">Heart Of D' Ocean Beach Resort</a>
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

    <?php if ($successMessage): ?>
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
          <input type="date" id="checkin" name="checkin" value="<?php echo isset($_POST['checkin']) ? $_POST['checkin'] : ''; ?>" required>
        </div>
        <div class="form-group">
          <label for="checkout">Check-out Date *</label>
          <input type="date" id="checkout" name="checkout" value="<?php echo isset($_POST['checkout']) ? $_POST['checkout'] : ''; ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label for="accommodation">Accommodation Type *</label>
        <select id="accommodation" name="accommodation" required>
          <option value="">Select accommodation...</option>
          <optgroup label="Premium Cottages">
            <option value="white-house" data-price="30000" <?php echo (isset($_POST['accommodation']) && $_POST['accommodation'] == 'white-house') ? 'selected' : ''; ?>>White House - ₱30,000/night</option>
            <option value="penthouse" data-price="12800" <?php echo (isset($_POST['accommodation']) && $_POST['accommodation'] == 'penthouse') ? 'selected' : ''; ?>>Penthouse - ₱12,800/night</option>
            <option value="aqua-class" data-price="11800" <?php echo (isset($_POST['accommodation']) && $_POST['accommodation'] == 'aqua-class') ? 'selected' : ''; ?>>Aqua Class - ₱11,800/night</option>
            <option value="heartsuite" data-price="11800" <?php echo (isset($_POST['accommodation']) && $_POST['accommodation'] == 'heartsuite') ? 'selected' : ''; ?>>Heartsuite - ₱11,800/night</option>
            <option value="stephs-skylounge" data-price="11800" <?php echo (isset($_POST['accommodation']) && $_POST['accommodation'] == 'stephs-skylounge') ? 'selected' : ''; ?>>Steph's Skylounge - ₱11,800/night</option>
          </optgroup>
          <optgroup label="Standard Cottages">
            <option value="stephs-848" data-price="10800" <?php echo (isset($_POST['accommodation']) && $_POST['accommodation'] == 'stephs-848') ? 'selected' : ''; ?>>Steph's 848 - ₱10,800/night</option>
            <option value="stephs-846" data-price="10000" <?php echo (isset($_POST['accommodation']) && $_POST['accommodation'] == 'stephs-846') ? 'selected' : ''; ?>>Steph's 846 - ₱10,000/night</option>
            <option value="concierge-817" data-price="9800" <?php echo (isset($_POST['accommodation']) && $_POST['accommodation'] == 'concierge-817') ? 'selected' : ''; ?>>Concierge 817 - ₱9,800/night</option>
            <option value="de-luxe" data-price="8800" <?php echo (isset($_POST['accommodation']) && $_POST['accommodation'] == 'de-luxe') ? 'selected' : ''; ?>>De Luxe - ₱8,800/night</option>
            <option value="concierge-815-819" data-price="8800" <?php echo (isset($_POST['accommodation']) && $_POST['accommodation'] == 'concierge-815-819') ? 'selected' : ''; ?>>Concierge 815/819 - ₱8,800/night</option>
            <option value="premium-840" data-price="8800" <?php echo (isset($_POST['accommodation']) && $_POST['accommodation'] == 'premium-840') ? 'selected' : ''; ?>>Premium 840 - ₱8,800/night</option>
            <option value="beatrice-a" data-price="7800" <?php echo (isset($_POST['accommodation']) && $_POST['accommodation'] == 'beatrice-a') ? 'selected' : ''; ?>>Beatrice A - ₱7,800/night</option>
            <option value="premium-838" data-price="7800" <?php echo (isset($_POST['accommodation']) && $_POST['accommodation'] == 'premium-838') ? 'selected' : ''; ?>>Premium 838 - ₱7,800/night</option>
            <option value="giant-kubo" data-price="6800" <?php echo (isset($_POST['accommodation']) && $_POST['accommodation'] == 'giant-kubo') ? 'selected' : ''; ?>>Giant Kubo - ₱6,800/night</option>
            <option value="seaside-whole" data-price="6800" <?php echo (isset($_POST['accommodation']) && $_POST['accommodation'] == 'seaside-whole') ? 'selected' : ''; ?>>Seaside (Whole) - ₱6,800/night</option>
            <option value="beatrice-b" data-price="6800" <?php echo (isset($_POST['accommodation']) && $_POST['accommodation'] == 'beatrice-b') ? 'selected' : ''; ?>>Beatrice B - ₱6,800/night</option>
          </optgroup>
          <optgroup label="Budget Cottages">
            <option value="seaside-half" data-price="3400" <?php echo (isset($_POST['accommodation']) && $_POST['accommodation'] == 'seaside-half') ? 'selected' : ''; ?>>Seaside (Half) - ₱3,400/night</option>
            <option value="bamboo-kubo" data-price="2800" <?php echo (isset($_POST['accommodation']) && $_POST['accommodation'] == 'bamboo-kubo') ? 'selected' : ''; ?>>Bamboo Kubo - ₱2,800/night</option>
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
          <input type="number" id="adults" name="adults" min="1" max="50" value="<?php echo isset($_POST['adults']) ? $_POST['adults'] : '2'; ?>" required>
        </div>
        <div class="form-group">
          <label for="children">Children</label>
          <input type="number" id="children" name="children" min="0" max="20" value="<?php echo isset($_POST['children']) ? $_POST['children'] : '0'; ?>">
        </div>
      </div>

      <!-- PAYMENT METHOD SELECTION -->
      <div class="form-group">
        <label for="paymentMethod">Payment Method *</label>
        <select id="paymentMethod" name="paymentMethod" required onchange="togglePaymentOption()">
          <option value="">Select payment method...</option>
          <option value="pay-now" <?php echo (isset($_POST['paymentMethod']) && $_POST['paymentMethod'] == 'pay-now') ? 'selected' : ''; ?>>Pay Now (GCash)</option>
          <option value="face-to-face" <?php echo (isset($_POST['paymentMethod']) && $_POST['paymentMethod'] == 'face-to-face') ? 'selected' : ''; ?>>Pay Face to Face</option>
        </select>
      </div>

      <!-- QR Code Section (initially hidden) - KEPT FROM HTML -->
      <div id="qrSection" class="qr-section" style="display: none;">
        <h4>GCash QR Code</h4>
        <div class="qr-code">
          <img src="images&vids/qrcode.jpg" alt="GCash QR Code">
        </div>
        <p><strong>Amount:</strong> <span id="qrAmount">₱0</span></p>
        <p><strong>Reference No:</strong> <span id="qrReference">RESORT000000</span></p>
        
        <!-- FILE UPLOAD SECTION - KEPT FROM HTML -->
        <div class="upload-section">
          <label for="receiptUpload">Upload Payment Receipt (Screenshot) *</label>
          <input type="file" id="receiptUpload" accept="image/*" capture="environment">
          <small>Take a screenshot of your GCash payment and upload it here</small>
        </div>

        <!-- PAYMENT DETAILS FORM -->
        <div class="payment-details-form">
          <h5>Payment Details</h5>
          
          <div class="form-group">
            <label for="gcashName">Name as it appears in GCash *</label>
            <input type="text" id="gcashName" name="gcashName" value="<?php echo isset($_POST['gcashName']) ? htmlspecialchars($_POST['gcashName']) : ''; ?>" placeholder="Enter your name as shown in GCash">
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

      <div class="form-actions">
        <button type="submit" class="btn primary">Book Now</button>
        <!-- RESET BUTTON - KEPT FROM HTML -->
        <button type="button" id="resetForm" class="btn ghost">Reset Form</button>
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

  <script>
    // Set minimum dates to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('checkin').min = today;
    document.getElementById('checkout').min = today;

    // Toggle payment options
    function togglePaymentOption() {
        const paymentMethod = document.getElementById('paymentMethod').value;
        const qrSection = document.getElementById('qrSection');
        
        if (paymentMethod === 'pay-now') {
            qrSection.style.display = 'block';
            updateQRAmount();
        } else {
            qrSection.style.display = 'none';
        }
    }

    // Update QR amount
    function updateQRAmount() {
        const totalAmount = document.getElementById('totalAmount').textContent;
        document.getElementById('qrAmount').textContent = totalAmount;
        
        const refNumber = 'RESORT' + Date.now().toString().slice(-6);
        document.getElementById('qrReference').textContent = refNumber;
    }

    // Calculate nights and price
    function calculateStay() {
        const checkin = document.getElementById('checkin').value;
        const checkout = document.getElementById('checkout').value;
        const accommodationSelect = document.getElementById('accommodation');
        const selectedOption = accommodationSelect.options[accommodationSelect.selectedIndex];
        const pricePerNight = selectedOption ? parseInt(selectedOption.getAttribute('data-price')) : 0;

        if (checkin && checkout) {
            const checkinDate = new Date(checkin);
            const checkoutDate = new Date(checkout);
            const timeDiff = checkoutDate.getTime() - checkinDate.getTime();
            const nights = Math.ceil(timeDiff / (1000 * 3600 * 24));
            
            if (nights > 0 && pricePerNight > 0) {
                const totalPrice = pricePerNight * nights;
                document.getElementById('accommodationPrice').textContent = '₱' + pricePerNight.toLocaleString();
                document.getElementById('nightsCount').textContent = nights;
                document.getElementById('totalAmount').textContent = '₱' + totalPrice.toLocaleString();
                document.getElementById('priceBreakdown').style.display = 'block';
                
                // Update QR amount if section is visible
                if (document.getElementById('paymentMethod').value === 'pay-now') {
                    updateQRAmount();
                }
            } else {
                document.getElementById('priceBreakdown').style.display = 'none';
            }
        } else {
            document.getElementById('priceBreakdown').style.display = 'none';
        }
    }

    // Reset form function
    document.getElementById('resetForm').addEventListener('click', function() {
        if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
            document.getElementById('bookingForm').reset();
            document.getElementById('priceBreakdown').style.display = 'none';
            document.getElementById('qrSection').style.display = 'none';
        }
    });

    // Add event listeners
    document.getElementById('checkin').addEventListener('change', calculateStay);
    document.getElementById('checkout').addEventListener('change', calculateStay);
    document.getElementById('accommodation').addEventListener('change', calculateStay);

    // Initialize calculation on page load
    document.addEventListener('DOMContentLoaded', function() {
        calculateStay();
        
        // Set checkout date to tomorrow by default
        if (!document.getElementById('checkin').value) {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            document.getElementById('checkout').value = tomorrow.toISOString().split('T')[0];
        }
        
        // Initialize payment method section
        togglePaymentOption();
    });
  </script>
  <script src="main.js"></script>
</body>
</html>