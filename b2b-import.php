<?php
include "include/session.php";
include "include/connect.php";

$message = "";
$error = "";
$report = [];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["csv_file"])) {
    $file = $_FILES["csv_file"]["tmp_name"];
    $default_amount = mysqli_real_escape_string($connect, $_POST['amount'] ?? '0');
    $ref_prefix = mysqli_real_escape_string($connect, $_POST['ref_prefix'] ?? 'B2B_');

    if ($_FILES["csv_file"]["size"] > 0) {
        $handle = fopen($file, "r");
        $header = fgetcsv($handle, 1000, ","); // Skip header

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Name, Grade, Email ID, Contact No., School Name, workshop_id
            $full_name = trim($data[0] ?? '');
            $grade = mysqli_real_escape_string($connect, trim($data[1] ?? ''));
            $email = mysqli_real_escape_string($connect, trim($data[2] ?? ''));
            $mobile = mysqli_real_escape_string($connect, trim($data[3] ?? ''));
            $school = mysqli_real_escape_string($connect, trim($data[4] ?? ''));
            $workshop_id = mysqli_real_escape_string($connect, trim($data[5] ?? ''));

            // Split name
            $name_parts = explode(" ", $full_name, 2);
            $first_name = mysqli_real_escape_string($connect, $name_parts[0]);
            $last_name = mysqli_real_escape_string($connect, ($name_parts[1] ?? ''));

            if (empty($mobile)) {
                $report[] = ["status" => "error", "message" => "Contact No. is missing for $full_name"];
                continue;
            }

            // 1. Check if user exists
            $user_id = null;
            $checkUser = "SELECT id FROM users WHERE mobile = '$mobile' LIMIT 1";
            $userResult = mysqli_query($connect, $checkUser);

            if ($userResult && mysqli_num_rows($userResult) > 0) {
                $userData = mysqli_fetch_assoc($userResult);
                $user_id = $userData['id'];
                $userStatus = "Existing user found (ID: $user_id)";
            } else {
                // 2. Create user via API
                $regData = array(
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                    'mobile' => $mobile,
                    'school' => $school,
                    'grade' => $grade,
                    'password' => 'MOS@123', // Default password
                    'user_type' => 'user'
                );

                $apiResponse = callAPI('POST', 'register', json_encode($regData), '');
                $responseArr = json_decode($apiResponse, true);

                if (isset($responseArr['status']) && $responseArr['status'] == true) {
                    // Re-fetch user ID after creation
                    $checkUser = "SELECT id FROM users WHERE mobile = '$mobile' LIMIT 1";
                    $userResult = mysqli_query($connect, $checkUser);
                    if ($userResult && mysqli_num_rows($userResult) > 0) {
                        $userData = mysqli_fetch_assoc($userResult);
                        $user_id = $userData['id'];
                        $userStatus = "New user created (ID: $user_id)";
                    } else {
                        $report[] = ["status" => "error", "message" => "User created but could not retrieve ID for mobile: $mobile"];
                        continue;
                    }
                } else {
                    $errMsg = isset($responseArr['message']) ? $responseArr['message'] : "Unknown API error";
                    if (empty($responseArr) && !empty($apiResponse)) {
                        $errMsg = "Raw API Response: " . htmlspecialchars($apiResponse);
                    }
                    $report[] = ["status" => "error", "message" => "Failed to create user $mobile: $errMsg"];
                    continue;
                }
            }

            // 3. Check for duplicate payment for this workshop
            $checkPayment = "SELECT id FROM payments WHERE user_id = '$user_id' AND workshop_id = '$workshop_id' LIMIT 1";
            $paymentResult = mysqli_query($connect, $checkPayment);
            if ($paymentResult && mysqli_num_rows($paymentResult) > 0) {
                $report[] = ["status" => "warning", "message" => "Payment already exists for $mobile in workshop $workshop_id. Skipped."];
                continue;
            }

            // 4. Put entry into payments table
            $payment_id = $ref_prefix . uniqid();
            $order_id = "B2B_" . uniqid() . "2";
            $verify_token = bin2hex(random_bytes(8));
            $created_at = date('Y-m-d H:i:s');

            $insertPayment = "INSERT INTO payments (user_id, workshop_id, payment_id, amount, order_id, payment_status, verify_token, created_at, updated_at) 
                              VALUES ('$user_id', '$workshop_id', '$payment_id', '$default_amount', '$order_id', 1, '$verify_token', '$created_at', '$created_at')";

            if (mysqli_query($connect, $insertPayment)) {
                $report[] = ["status" => "success", "message" => "Payment added for $mobile ($userStatus)"];
            } else {
                $report[] = ["status" => "error", "message" => "Failed to add payment for user $user_id: " . mysqli_error($connect)];
            }
        }
        fclose($handle);
        $message = "CSV Processing Completed.";
    } else {
        $error = "Empty or invalid CSV file.";
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>B2B CSV Import - Magic Of Skills</title>
    <?php include "include/meta.php" ?>
</head>

<body>
    <?php include "include/aside.php" ?>

    <main class="dashboard-main">
        <?php include "include/header.php" ?>

        <div class="dashboard-main-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
                <h6 class="fw-semibold mb-0">B2B CSV Import</h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="dashboard.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Dashboard
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">B2B Import</li>
                </ul>
            </div>

            <div class="card h-100 p-0 radius-12">
                <div class="card-body p-24">
                    <div class="row justify-content-center">
                        <div class="col-xxl-6 col-xl-8 col-lg-10">
                            <div class="card border">
                                <div class="card-body">
                                    <h6 class="text-md text-primary-light mb-16">Upload B2B Payments CSV</h6>

                                    <?php if ($message): ?>
                                        <div class="alert alert-success"><?php echo $message; ?></div>
                                    <?php endif; ?>
                                    <?php if ($error): ?>
                                        <div class="alert alert-danger"><?php echo $error; ?></div>
                                    <?php endif; ?>

                                    <form action="" method="POST" enctype="multipart/form-data">
                                        <div class="mb-20">
                                            <label for="csv_file"
                                                class="form-label fw-semibold text-primary-light text-sm mb-8">Select
                                                CSV File <span class="text-danger-600">*</span></label>
                                            <input type="file" class="form-control radius-8" id="csv_file"
                                                name="csv_file" accept=".csv" required>
                                            <small class="text-secondary-light">Expected columns: Name, Grade, Email ID,
                                                Contact No., School Name, workshop_id</small>
                                        </div>
                                        <div class="row mb-20">
                                            <div class="col-sm-6">
                                                <label
                                                    class="form-label fw-semibold text-primary-light text-sm mb-8">Default
                                                    Amount</label>
                                                <input type="number" name="amount" class="form-control radius-8"
                                                    value="499" step="0.01">
                                            </div>
                                            <div class="col-sm-6">
                                                <label
                                                    class="form-label fw-semibold text-primary-light text-sm mb-8">Payment
                                                    Ref Prefix</label>
                                                <input type="text" name="ref_prefix" class="form-control radius-8"
                                                    value="B2B_">
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-center gap-3">
                                            <button type="submit"
                                                class="btn btn-primary border border-primary-600 text-md px-56 py-12 radius-8">
                                                Process CSV
                                            </button>
                                        </div>
                                    </form>

                                    <?php if (!empty($report)): ?>
                                        <div class="mt-24">
                                            <h6 class="text-sm fw-semibold mb-12">Import Report:</h6>
                                            <div class="table-responsive">
                                                <table class="table bordered-table sm-table mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Status</th>
                                                            <th>Message</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($report as $r): ?>
                                                            <tr>
                                                                <td>
                                                                    <?php if ($r['status'] == 'success'): ?>
                                                                        <span class="badge bg-success">Success</span>
                                                                    <?php elseif ($r['status'] == 'warning'): ?>
                                                                        <span class="badge bg-warning">Skipped</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-danger">Error</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td><?php echo $r['message']; ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include "include/footer.php" ?>
    </main>

    <?php include "include/script.php" ?>
</body>

</html>