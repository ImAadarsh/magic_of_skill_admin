<?php
include "include/session.php";
include "include/connect.php";


// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $last_name = $_POST['last_name'];
    $first_name = $_POST['first_name'];
    $email = $_POST['email'];
    $mobile = $_POST['mobile'];
    $password = $_POST['password'];

    // Prepare data for API call
    $data = array(
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'mobile' => $mobile,
        'password' => $password,
        'user_type' => 'admin'
    );

    // Make API call
    $result = callAPI('POST', 'register', json_encode($data), '');

    // Parse the JSON response
    $response = json_decode($result, true);

    // Check if registration was successful
    if (isset($response['status']) && $response['status']==true) {
        $message = "Admin added successfully!";
    } else {
        $error = "Failed to add admin. " . (isset($response['message']) ? $response['message'] : "");
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Admin - Magic Of Skills Dashboard</title>
    <?php include "include/meta.php" ?>
</head>
<body>
    <?php include "include/aside.php" ?>

    <main class="dashboard-main">
        <?php include "include/header.php" ?>
    
        <div class="dashboard-main-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
                <h6 class="fw-semibold mb-0">Add Admin</h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="dashboard.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Dashboard
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">Add Admin</li>
                </ul>
            </div>

            <div class="card h-100 p-0 radius-12">
                <div class="card-body p-24">
                    <div class="row justify-content-center">
                        <div class="col-xxl-6 col-xl-8 col-lg-10">
                            <div class="card border">
                                <div class="card-body">
                                    <h6 class="text-md text-primary-light mb-16">Add New Admin</h6>

                                    <?php
                                    if (isset($message)) {
                                        echo "<div class='alert alert-success'>$message</div>";
                                    }
                                    if (isset($error)) {
                                        echo "<div class='alert alert-danger'>$error</div>";
                                    }
                                    ?>
                                    
                                    <form action="" method="POST">
                                        <div class="mb-20">
                                            <label for="name" class="form-label fw-semibold text-primary-light text-sm mb-8">Full Name <span class="text-danger-600">*</span></label>
                                            <input type="text" class="form-control radius-8" id="name" name="first_name" placeholder="Enter First Name" required>
                                        </div>
                                        <div class="mb-20">
                                            <label for="name" class="form-label fw-semibold text-primary-light text-sm mb-8">Last Name <span class="text-danger-600">*</span></label>
                                            <input type="text" class="form-control radius-8" id="name" name="last_name" placeholder="Enter Last Name" required>
                                        </div>
                                        <div class="mb-20">
                                            <label for="email" class="form-label fw-semibold text-primary-light text-sm mb-8">Email <span class="text-danger-600">*</span></label>
                                            <input type="email" class="form-control radius-8" id="email" name="email" placeholder="Enter email address" required>
                                        </div>
                                        <div class="mb-20">
                                            <label for="mobile" class="form-label fw-semibold text-primary-light text-sm mb-8">Phone <span class="text-danger-600">*</span></label>
                                            <input type="tel" class="form-control radius-8" id="mobile" name="mobile" placeholder="Enter phone number" required>
                                        </div>
                                        <div class="mb-20">
                                            <label for="password" class="form-label fw-semibold text-primary-light text-sm mb-8">Password <span class="text-danger-600">*</span></label>
                                            <input type="password" class="form-control radius-8" id="password" name="password" placeholder="Enter password" required>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-center gap-3">
                                            <button type="button" class="border border-danger-600 bg-hover-danger-200 text-danger-600 text-md px-56 py-11 radius-8" onclick="window.history.back()"> 
                                                Cancel
                                            </button>
                                            <button type="submit" class="btn btn-primary border border-primary-600 text-md px-56 py-12 radius-8"> 
                                                Add Admin
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include "include/script.php" ?>
</body>
</html>