<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/Exception.php';
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';

function sendResortEmail($to, $subject, $message) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings for Gmail
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'mcpebrinehq@gmail.com';
        $mail->Password   = 'dtdxxxhcmauo eebt'; // Make sure NO spaces: dtdxxxhcmauoeebt
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Enable detailed debugging
        $mail->SMTPDebug = 4; // Full debug output
        $mail->Debugoutput = function($str, $level) {
            file_put_contents('smtp_debug.log', date('Y-m-d H:i:s') . " - $level: $str\n", FILE_APPEND | LOCK_EX);
        };
        
        // Recipients
        $mail->setFrom('mcpebrinehq@gmail.com', 'Heart Of D Ocean Resort');
        $mail->addAddress($to);
        $mail->addReplyTo('heartofdocean2005@yahoo.com', 'Heart Of D Ocean Resort');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message);
        
        $mail->send();
        
        // Log success
        file_put_contents('email_log.txt', "[" . date('Y-m-d H:i:s') . "] SUCCESS: Email sent to $to\n", FILE_APPEND | LOCK_EX);
        return true;
        
    } catch (Exception $e) {
        // Log detailed error
        $error = "[" . date('Y-m-d H:i:s') . "] FAILED: " . $e->getMessage() . " | To: $to\n";
        file_put_contents('email_log.txt', $error, FILE_APPEND | LOCK_EX);
        return false;
    }
}
?>