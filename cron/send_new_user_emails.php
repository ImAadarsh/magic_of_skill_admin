<?php
include __DIR__ . '/../include/connect.php';

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Credentials
$mail_host = 'smtp.gmail.com';
$mail_port = 465;
$mail_username = 'ipnacademy2023@gmail.com';
// Use provided password or get from URL parameter
$mail_password = $_GET['password'];
$admin_email = 'digital.endeavour.in@gmail.com';

// Check for PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Try to load PHPMailer from common locations
$autoloadFound = false;
$locations = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../PHPMailer/src/Exception.php',
    __DIR__ . '/../include/PHPMailer/src/Exception.php'
];

foreach ($locations as $loc) {
    if (file_exists($loc)) {
        if (strpos($loc, 'autoload.php') !== false) {
            require $loc;
            $autoloadFound = true;
            break;
        } else {
            // Manual load
            $baseDir = dirname($loc);
            require $baseDir . '/Exception.php';
            require $baseDir . '/PHPMailer.php';
            require $baseDir . '/SMTP.php';
            $autoloadFound = true;
            break;
        }
    }
}

if (!$autoloadFound) {
    // If not found, check if classes are already defined (maybe included by connect.php or others?)
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        die("PHPMailer library not found. Please install PHPMailer via Composer or place it in a known directory.");
    }
}

// Check for new users
$sql = "SELECT * FROM users WHERE is_new = 0";
$result = $connect->query($sql);

if ($result->num_rows > 0) {
    $newUsers = [];
    while($row = $result->fetch_assoc()) {
        $newUsers[] = $row;
    }

    // Setup Email
    $mail = new PHPMailer(true);

    try {
        // Server settings
        //$mail->SMTPDebug = SMTP::DEBUG_SERVER; 
        $mail->isSMTP();
        $mail->Host       = $mail_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $mail_username;
        $mail->Password   = $mail_password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $mail_port;

        // Recipients
        $mail->setFrom($mail_username, 'SkillAdmin System');
        $mail->addAddress($admin_email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'New User Registrations Alert';
        
        $body = "<h3>New Users Registered</h3>";
        $body .= "<p>The following users have registered recently:</p>";
        $body .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
        $body .= "<thead><tr><th>ID</th><th>Name</th><th>Email</th><th>School</th><th>Mobile</th><th>Grade</th></tr></thead>";
        $body .= "<tbody>";
        
        $idsToUpdate = [];
        foreach ($newUsers as $user) {
            $body .= "<tr>";
            $body .= "<td>" . htmlspecialchars($user['id']) . "</td>";
            $body .= "<td>" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</td>";
            $body .= "<td>" . htmlspecialchars($user['email']) . "</td>";
            $body .= "<td>" . htmlspecialchars($user['school']) . "</td>";
            $body .= "<td>". htmlspecialchars($user["mobile"]) ."</td>";
            $body .= "<td>". htmlspecialchars($user["grade"]) ."</td>";
            $body .= "</tr>";
            $idsToUpdate[] = $user['id'];
        }
        
        $body .= "</tbody></table>";
        $mail->Body = $body;

        $mail->send();
        echo "Email sent successfully to $admin_email with " . count($newUsers) . " new users.\n";

        // Update is_new status
        if (!empty($idsToUpdate)) {
            $idsStr = implode(',', array_map('intval', $idsToUpdate));
            $updateSql = "UPDATE users SET is_new = 1 WHERE id IN ($idsStr)";
            if ($connect->query($updateSql) === TRUE) {
                echo "Updated is_new status for " . count($idsToUpdate) . " users.\n";
            } else {
                echo "Error updating records: " . $connect->error . "\n";
            }
        }

    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}\n";
    }

} else {
    echo "No new users found.\n";
}
?>
