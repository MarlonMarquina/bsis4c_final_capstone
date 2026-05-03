<?php
// send_email_notification.php - Email notification handler for application status updates

require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendApplicationEmail($recipientEmail, $recipientName, $status, $details = []) {
    // Email Configuration (same as your handle_otp.php)
    $smtpHost = 'smtp.gmail.com';
    $smtpUsername = 'clearancebpc@gmail.com';
    $smtpPassword = 'powe wgem hlsv ybyq';
    $smtpPort = 587;
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUsername;
        $mail->Password = $smtpPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtpPort;
        
        // Recipients
        $mail->setFrom($smtpUsername, 'Smart Clearance System');
        $mail->addAddress($recipientEmail, $recipientName);
        
        // Content
        $mail->isHTML(true);
        
        if ($status === 'approved') {
            $mail->Subject = ' Clearance Application Approved - Smart Clearance';
            $mail->Body = generateApprovedEmailBody($recipientName, $details);
            $mail->AltBody = "Good news! Your clearance application has been approved by {$details['signatory']} for {$details['course']}. Requirement: {$details['requirement']}.";
            
        } elseif ($status === 'rejected') {
            $mail->Subject = ' Clearance Application Requires Revision - Smart Clearance';
            $mail->Body = generateRejectedEmailBody($recipientName, $details);
            $mail->AltBody = "Your clearance application requires revision. Signatory: {$details['signatory']}. Reason: {$details['reason']}. Please resubmit after making corrections.";
            
        } elseif ($status === 'totally_rejected') {
            $mail->Subject = ' Clearance Application Rejected - Smart Clearance';
            $mail->Body = generateTotallyRejectedEmailBody($recipientName, $details);
            $mail->AltBody = "Your clearance application has been rejected after {$details['rejection_count']} attempts. Please contact {$details['signatory']} for further assistance.";
        }
        
        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
        
    } catch (Exception $e) {
        error_log('Email Error: ' . $mail->ErrorInfo);
        return ['success' => false, 'message' => 'Email could not be sent: ' . $mail->ErrorInfo];
    }
}

function generateApprovedEmailBody($name, $details) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #0b4e12 0%, #0b5d27 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .success-box { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 5px; }
            .info-row { margin: 10px 0; }
            .info-label { font-weight: 600; color: #0b4e12; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin: 0; font-size: 28px;'>✅ Application Approved!</h1>
            </div>
            <div class='content'>
                <p>Dear <strong>{$name}</strong>,</p>
                
                <div class='success-box'>
                    <p style='margin: 0; font-size: 16px;'><strong>Great news!</strong> Your clearance application has been approved.</p>
                </div>
                
                <div class='info-row'>
                    <span class='info-label'>Signatory:</span> {$details['signatory']}
                </div>
                <div class='info-row'>
                    <span class='info-label'>Course:</span> {$details['course']}
                </div>
                <div class='info-row'>
                    <span class='info-label'>Requirement:</span> {$details['requirement']}
                </div>
                <div class='info-row'>
                    <span class='info-label'>Approved Date:</span> " . date('F j, Y g:i A') . "
                </div>
                
                <p style='margin-top: 25px;'>You can now proceed with the next steps in your clearance process.</p>
                
                <div class='footer'>
                    <p>This is an automated message from Smart Clearance System.<br>
                    Please do not reply to this email.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
}

function generateRejectedEmailBody($name, $details) {
    $requiresReupload = $details['requires_reupload'] ? 'Yes - Please upload a corrected document' : 'No - You can resubmit without uploading a new file';
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #856404 0%, #d39e00 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px; }
            .reason-box { background: white; border: 1px solid #ddd; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .info-row { margin: 10px 0; }
            .info-label { font-weight: 600; color: #856404; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin: 0; font-size: 28px;'>⚠️ Revision Required</h1>
            </div>
            <div class='content'>
                <p>Dear <strong>{$name}</strong>,</p>
                
                <div class='warning-box'>
                    <p style='margin: 0; font-size: 16px;'><strong>Your application requires revision.</strong></p>
                    <p style='margin: 5px 0 0 0; font-size: 14px;'>Attempt: {$details['rejection_count']} of 3</p>
                </div>
                
                <div class='info-row'>
                    <span class='info-label'>Signatory:</span> {$details['signatory']}
                </div>
                <div class='info-row'>
                    <span class='info-label'>Course:</span> {$details['course']}
                </div>
                <div class='info-row'>
                    <span class='info-label'>Requirement:</span> {$details['requirement']}
                </div>
                
                <div class='reason-box'>
                    <p style='margin: 0 0 10px 0; font-weight: 600; color: #dc3545;'>Reason for Revision:</p>
                    <p style='margin: 0; font-size: 15px;'>{$details['reason']}</p>
                    " . (!empty($details['additional_notes']) ? "<p style='margin: 10px 0 0 0; color: #666; font-size: 14px;'><em>Additional Notes:</em> {$details['additional_notes']}</p>" : "") . "
                </div>
                
                <div class='info-row'>
                    <span class='info-label'>File Re-upload Required:</span> {$requiresReupload}
                </div>
                
                <p style='margin-top: 25px; background: #e8f5e9; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745;'>
                    <strong>Next Steps:</strong><br>
                    Please log in to your account, make the necessary corrections, and resubmit your application.
                </p>
                
                <div class='footer'>
                    <p>This is an automated message from Smart Clearance System.<br>
                    Please do not reply to this email.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
}

function generateTotallyRejectedEmailBody($name, $details) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #721c24 0%, #c82333 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .danger-box { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; border-radius: 5px; }
            .info-row { margin: 10px 0; }
            .info-label { font-weight: 600; color: #721c24; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin: 0; font-size: 28px;'>⛔ Application Rejected</h1>
            </div>
            <div class='content'>
                <p>Dear <strong>{$name}</strong>,</p>
                
                <div class='danger-box'>
                    <p style='margin: 0; font-size: 16px;'><strong>Your application has been rejected after {$details['rejection_count']} attempts.</strong></p>
                </div>
                
                <div class='info-row'>
                    <span class='info-label'>Signatory:</span> {$details['signatory']}
                </div>
                <div class='info-row'>
                    <span class='info-label'>Course:</span> {$details['course']}
                </div>
                <div class='info-row'>
                    <span class='info-label'>Requirement:</span> {$details['requirement']}
                </div>
                
                <p style='margin-top: 25px; background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;'>
                    <strong>Next Steps:</strong><br>
                    Please contact <strong>{$details['signatory']}</strong> directly for further guidance on how to proceed with your clearance requirements.
                </p>
                
                <div class='footer'>
                    <p>This is an automated message from Smart Clearance System.<br>
                    Please do not reply to this email.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
}
?>