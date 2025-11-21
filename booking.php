<?php
session_start();
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $room = $_POST['room'];
    $date = $_POST['date'];
    $guests = $_POST['guests'];
    $booking_id = 'RESORT' . time() . rand(100, 999);

    try {
        $query = "INSERT INTO bookings (booking_id, name, email, phone, room, date, guests, status) 
                  VALUES (:booking_id, :name, :email, :phone, :room, :date, :guests, 'pending')";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":booking_id", $booking_id);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":phone", $phone);
        $stmt->bindParam(":room", $room);
        $stmt->bindParam(":date", $date);
        $stmt->bindParam(":guests", $guests);
        
        if ($stmt->execute()) {
            // Send REAL confirmation email
            $subject = "📋 Booking Received - Heart Of D' Ocean Beach Resort";
            $message = "
            <html>
            <body>
                <h2>Booking Received! 🏝️</h2>
                <p>Dear <strong>$name</strong>,</p>
                <p>Thank you for your booking request! We have received your reservation and will process it shortly.</p>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0;'>
                    <h3>Booking Details:</h3>
                    <p><strong>Booking ID:</strong> $booking_id</p>
                    <p><strong>Cottage:</strong> $room</p>
                    <p><strong>Check-in Date:</strong> $date</p>
                    <p><strong>Number of Guests:</strong> $guests</p>
                    <p><strong>Contact:</strong> $phone</p>
                    <p><strong>Status:</strong> <span style='color: #ffc107; font-weight: bold;'>PENDING ⏳</span></p>
                </div>
                
                <p><strong>What happens next?</strong></p>
                <ul>
                    <li>We will review your booking within 24 hours</li>
                    <li>You'll receive a confirmation email once approved</li>
                    <li>Payment instructions will be provided upon confirmation</li>
                </ul>
                
                <p>If you have any questions, please reply to this email.</p>
                
                <br>
                <p>Best regards,<br>The Heart Of D' Ocean Team</p>
            </body>
            </html>
            ";
            
            // Email headers for HTML email
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Heart Of D' Ocean Beach Resort <noreply@heart-ocean-resort.com>" . "\r\n";
            $headers .= "Reply-To: info@heart-ocean-resort.com" . "\r\n";
            
            // Send email
            mail($email, $subject, $message, $headers);
            
            $_SESSION['booking_success'] = true;
            $_SESSION['booking_id'] = $booking_id;
            header("Location: booking.php?success=1");
            exit();
        }
    } catch(PDOException $exception) {
        $error = "Database error: " . $exception->getMessage();
    }
}

$successMessage = '';
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $successMessage = '🎉 Booking submitted successfully! Please check your email for confirmation details.';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Booking — Heart Of D' Ocean Beach Resort</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
  <link rel="stylesheet" href="styles.css" />
</head> 
<body>
  <header class="site-header">
    <div class="container header-inner">
      <a class="logo" href="index.php">Heart Of D' Ocean Beach Resort</a>
      <nav class="nav" id="mainNav">
        <a href="index.php">Home</a>
        <a href="rooms.php">Cottages</a>
        <a href="gallery.php">Gallery</a>
        <a href="booking.php" class="cta">Book Now</a>
        <button id="darkToggle" class="icon-btn" aria-label="Toggle dark">🌙</button>
      </nav>
      <button id="menuBtn" class="hamburger" aria-label="menu">☰</button>
    </div>
  </header>

  <main class="container booking-page">
    <h1>Make a Reservation</h1>

    <?php if ($successMessage): ?>
    <div class="success-message">
        <?php echo $successMessage; ?>
    </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
    <div class="error-message">
        <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <form id="bookingForm" method="POST" action="booking.php" class="form-card">
      <label>Full name<input type="text" name="name" id="name" required></label>
      <label>Email<input type="email" name="email" id="email" required></label>
      <label>Contact No<input type="text" name="phone" id="phone" required></label>
      <label>Choose cottage / package
        <select name="room" id="room" required>
          <option value="">Select a cottage</option>
          <option value="White House — ₱30,000">White House — ₱30,000</option>
          <option value="Penthouse — ₱12,800">Penthouse — ₱12,800</option>
          <option value="Aqua Class — ₱11,800">Aqua Class — ₱11,800</option>
          <option value="Heartsuite — ₱11,800">Heartsuite — ₱11,800</option>
          <option value="Steph's Skylounge 842/844 — ₱11,800">Steph's Skylounge 842/844 — ₱11,800</option>
          <option value="Steph's 848 — ₱10,800">Steph's 848 — ₱10,800</option>
          <option value="Steph's 846 — ₱10,000">Steph's 846 — ₱10,000</option>
          <option value="Concierge 817 — ₱9,800">Concierge 817 — ₱9,800</option>
          <option value="De Luxe — ₱8,800">De Luxe — ₱8,800</option>
          <option value="Concierge 815/819 — ₱8,800">Concierge 815/819 — ₱8,800</option>
          <option value="Premium 840 — ₱8,800">Premium 840 — ₱8,800</option>
          <option value="Beatrice A — ₱7,800">Beatrice A — ₱7,800</option>
          <option value="Premium 838 — ₱7,800">Premium 838 — ₱7,800</option>
          <option value="Beatrice B — ₱6,800">Beatrice B — ₱6,800</option>
          <option value="Giant Kubo — ₱6,800">Giant Kubo — ₱6,800</option>
          <option value="Seaside (Whole) — ₱6,800">Seaside (Whole) — ₱6,800</option>
          <option value="Seaside (Half) — ₱3,400">Seaside (Half) — ₱3,400</option>
          <option value="Bamboo Kubo — ₱2,800">Bamboo Kubo — ₱2,800</option>
        </select>
      </label>

      <label>Check-in date<input type="date" name="date" id="date" required></label>
      <label>Guests<input type="number" name="guests" id="guests" value="2" min="1" max="10" required></label>

      <div class="form-actions">
        <button type="submit" class="btn">Reserve & Pay</button>
        <button type="button" id="saveDraft" class="btn ghost">Save Draft</button>
      </div>

      <div id="formMessage" class="muted" aria-live="polite"></div>
    </form>

    <section>
      <h2>How payment works</h2>
      <p>After you submit, the booking is saved to our database and you'll receive a confirmation email. You can proceed with payment through GCash after booking confirmation.</p>
    </section>
  </main>

  <hr>

  <footer class="footer">
      <div class="container">
          <div class="row">
              <div class="footer-col">
                  <h4>Company</h4>
                    <ul>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Services</a></li>
                        <li><a href="#">Portfolio</a></li>
                        <li><a href="#">Contact</a></li>
                    </ul>
              </div>
              <div class="footer-col">
                  <h4>Help</h4>
                    <ul>
                      <li><a href="#">FAQ</a></li>
                      <li><a href="#">Payment</a></li>
                    </ul>
              </div>
              <div class="footer-col">
                <h4>Reach Us</h4>
                  <div class="social-links">
                    <a href="https://www.facebook.com/messages/t/233219370026088"><i class="fab fa-facebook-messenger"></i></a>
                    <a href="https://www.facebook.com/Heartofdoceanbeachresort/#"><i class="fab fa-facebook"></i></a>
                    <a href="mailto:heartofdocean2005@yahoo.com"><i class="fas fa-envelope"></i></a>
                    <a href="https://maps.app.goo.gl/q67iwWwZYtNH51rN8"><i class="fas fa-location-dot"></i></a>
              </div>
            </div>
        </div>
      </div>
    </footer>

  <script src="main.js"></script>
</body>
</html>