<?php
include 'include/session.php';
include 'include/connect.php';

// Determine user type and fetch user data
if(isset($_GET['userid'])){
    $userId = $_GET['userid'];
    $userType = "user";
} else {
    $userId = $_SESSION['userid'];
    $userType = "admin";
}

$userData = [];
if ($userId) {
    $stmt = $connect->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
    $stmt->close();
}

$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $firstName = $_POST['first_name'];
        $lastName = $_POST['last_name'];
        $email = $_POST['email'];
        $mobile = $_POST['mobile'];
        $school = $_POST['school'];
        $city = $_POST['city'];
        $about = $_POST['about'];

        // Check if email is unique (excluding current user)
        $stmt = $connect->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $message = "error:Email already exists";
        } else {
            // Check if mobile is unique (excluding current user)
            $stmt = $connect->prepare("SELECT id FROM users WHERE mobile = ? AND id != ?");
            $stmt->bind_param("si", $mobile, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $message = "error:Phone number already exists";
            } else {
                // Update the database
                $stmt = $connect->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, mobile = ?, school = ?, city = ?, about = ? WHERE id = ?");
                $stmt->bind_param("sssssssi", $firstName, $lastName, $email, $mobile, $school, $city, $about, $userId);
                if ($stmt->execute()) {
                    $message = "success:Profile updated successfully";
                } else {
                    $message = "error:Error updating profile";
                }
            }
        }
        $stmt->close();
    }

    if (isset($_POST['change_password']) && $userType === 'admin') {
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        if ($newPassword === $confirmPassword) {
            $hashedPassword = md5($newPassword); // Using MD5 for password hashing
            $stmt = $connect->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashedPassword, $userId);
            if ($stmt->execute()) {
                $message = "success:Password changed successfully";
            } else {
                $message = "error:Error changing password";
            }
            $stmt->close();
        } else {
            $message = "error:Passwords do not match";
        }
    }

    // Redirect to avoid form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . ($userType === 'user' ? "?userid=$userId" : "") . "&message=" . urlencode($message));
    exit();
}

// Fetch updated user data after redirect
if ($userId) {
    $stmt = $connect->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <title>Magic Of Skills DashBoard</title>
  <?php include "include/meta.php" ?>
</head>
<body>
    <?php include "include/aside.php" ?>

    <main class="dashboard-main">
        <?php include "include/header.php" ?>
    
        <div class="dashboard-main-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
                <h6 class="fw-semibold mb-0">View Profile</h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="dashboard.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Dashboard
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">View Profile</li>
                </ul>
            </div>

            <div class="row gy-4">
                <div class="col-lg-4">
                    <div class="user-grid-card position-relative border radius-16 overflow-hidden bg-base h-100">
                        <img src="<?php echo isset($userData['banner'])?$uri.$userData['banner']:'assets/images/mos_banner.webp'; ?>" alt="" class="w-100 object-fit-cover">
                        <div class="pb-24 ms-16 mb-24 me-16  mt--100">
                            <div class="text-center border border-top-0 border-start-0 border-end-0">
                                <img src="<?php echo isset($userData['icon'])?$uri.$userData['icon']:"assets/images/mos_icon.png"; ?>" alt="" class="border br-white border-width-2-px w-200-px h-200-px rounded-circle object-fit-cover">
                                <h6 class="mb-0 mt-16"><?php echo htmlspecialchars($userData['first_name'] . ' ' . $userData['last_name']); ?></h6>
                                <span class="text-secondary-light mb-16"><?php echo htmlspecialchars($userData['email']); ?></span>
                            </div>
                            <div class="mt-24">
                                <h6 class="text-xl mb-16">Personal Info</h6>
                                <ul>
                                    <li class="d-flex align-items-center gap-1 mb-12">
                                        <span class="w-30 text-md fw-semibold text-primary-light">Full Name</span>
                                        <span class="w-70 text-secondary-light fw-medium">: <?php echo htmlspecialchars($userData['first_name'] . ' ' . $userData['last_name']); ?></span>
                                    </li>
                                    <li class="d-flex align-items-center gap-1 mb-12">
                                        <span class="w-30 text-md fw-semibold text-primary-light"> Email</span>
                                        <span class="w-70 text-secondary-light fw-medium">: <?php echo htmlspecialchars($userData['email']); ?></span>
                                    </li>
                                    <li class="d-flex align-items-center gap-1 mb-12">
                                        <span class="w-30 text-md fw-semibold text-primary-light"> Phone Number</span>
                                        <span class="w-70 text-secondary-light fw-medium">: <?php echo htmlspecialchars($userData['mobile']); ?></span>
                                    </li>
                                    <li class="d-flex align-items-center gap-1 mb-12">
                                        <span class="w-30 text-md fw-semibold text-primary-light"> School</span>
                                        <span class="w-70 text-secondary-light fw-medium">: <?php echo htmlspecialchars($userData['school']); ?></span>
                                    </li>
                                    <li class="d-flex align-items-center gap-1 mb-12">
                                        <span class="w-30 text-md fw-semibold text-primary-light"> City</span>
                                        <span class="w-70 text-secondary-light fw-medium">: <?php echo htmlspecialchars($userData['city']); ?></span>
                                    </li>
                                    <li class="d-flex align-items-center gap-1 mb-12">
                                        <span class="w-30 text-md fw-semibold text-primary-light"> Country</span>
                                        <span class="w-70 text-secondary-light fw-medium">: <?php echo htmlspecialchars($userData['country_code']); ?></span>
                                    </li>
                                    <li class="d-flex align-items-center gap-1">
                                        <span class="w-30 text-md fw-semibold text-primary-light"> About</span>
                                        <span class="w-70 text-secondary-light fw-medium">: <?php echo htmlspecialchars($userData['about'] ?? 'No information available'); ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card h-100">
                        <div class="card-body p-24">
                        <ul class="nav border-gradient-tab nav-pills mb-20 d-inline-flex" id="pills-tab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link d-flex align-items-center px-24 active" id="pills-edit-profile-tab" data-bs-toggle="pill" data-bs-target="#pills-edit-profile" type="button" role="tab" aria-controls="pills-edit-profile" aria-selected="true">
                                    Edit Profile 
                                </button>
                            </li>
                            <?php if ($userType === 'admin'): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link d-flex align-items-center px-24" id="pills-change-password-tab" data-bs-toggle="pill" data-bs-target="#pills-change-password" type="button" role="tab" aria-controls="pills-change-password" aria-selected="false">
                                    Change Password 
                                </button>
                            </li>
                            <?php endif; ?>
                        </ul>

                            <div class="tab-content" id="pills-tabContent">   
                                <div class="tab-pane fade show active" id="pills-edit-profile" role="tabpanel" aria-labelledby="pills-edit-profile-tab" tabindex="0">
                                    <h6 class="text-md text-primary-light mb-16">Profile Image (Can Change Only from User Panel)</h6>
                                    <!-- Upload Image Start -->
                                    <div class="mb-30 mt-16">
                                        <div class="avatar-upload">
                                            <div class="avatar-edit position-absolute bottom-0 end-0 me-24 mt-16 z-1 cursor-pointer">
                                                <!-- <input type='file' id="imageUpload" accept=".png, .jpg, .jpeg" hidden> -->
                                                <label for="imageUpload" class="w-32-px h-32-px d-flex justify-content-center align-items-center bg-primary-50 text-primary-600 border border-primary-600 bg-hover-primary-100 text-lg rounded-circle">
                                                    <iconify-icon icon="solar:camera-outline" class="icon"></iconify-icon>
                                                </label>
                                            </div>
                                            <div class="avatar-preview">
                                                <div id="imagePreview" style="background-image: url(<?php echo isset($userData['icon'])?$uri.$userData['icon']:"assets/images/mos_icon.png"; ?>);">
                                            </div>
                                        </div>
                                    </div>
                                    <br>
                                    <!-- Upload Image End -->
                                     
                                    <form action="#" method="post">
                                        <div class="row">
                                        <div class="col-sm-6">
                                            <div class="mb-20">
                                                <label for="first_name" class="form-label fw-semibold text-primary-light text-sm mb-8">First Name <span class="text-danger-600">*</span></label>
                                                <input type="text" class="form-control radius-8" id="first_name" name="first_name" value="<?php echo htmlspecialchars($userData['first_name']); ?>" placeholder="Enter First Name">
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="mb-20">
                                                <label for="last_name" class="form-label fw-semibold text-primary-light text-sm mb-8">Last Name <span class="text-danger-600">*</span></label>
                                                <input type="text" class="form-control radius-8" id="last_name" name="last_name" value="<?php echo htmlspecialchars($userData['last_name']); ?>" placeholder="Enter Last Name">
                                            </div>
                                        </div>
                                            <div class="col-sm-6">
                                                <div class="mb-20">
                                                    <label for="email" class="form-label fw-semibold text-primary-light text-sm mb-8">Email <span class="text-danger-600">*</span></label>
                                                    <input type="email" class="form-control radius-8" id="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" placeholder="Enter email address">
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="mb-20">
                                                    <label for="number" class="form-label fw-semibold text-primary-light text-sm mb-8">Phone</label>
                                                    <input type="text" class="form-control radius-8" id="number" name="mobile" value="<?php echo htmlspecialchars($userData['mobile']); ?>" placeholder="Enter phone number">
                                                </div>
                                            </div>
                                            <?php if ($userType != 'admin'): ?>
                                            <div class="col-sm-6">
                                                <div class="mb-20">
                                                    <label for="school" class="form-label fw-semibold text-primary-light text-sm mb-8">School <span class="text-danger-600">*</span></label>
                                                    <input type="text" class="form-control radius-8" id="school" name="school" value="<?php echo htmlspecialchars($userData['school']); ?>" placeholder="Enter school name">
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="mb-20">
                                                    <label for="city" class="form-label fw-semibold text-primary-light text-sm mb-8">City <span class="text-danger-600">*</span></label>
                                                    <input type="text" class="form-control radius-8" id="city" name="city" value="<?php echo htmlspecialchars($userData['city']); ?>" placeholder="Enter city">
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="mb-20">
                                                    <label for="country" class="form-label fw-semibold text-primary-light text-sm mb-8">Country (Not Editable) <span class="text-danger-600">*</span></label>
                                                    <input type="text" class="form-control radius-8" id="country" disabled name="country_code" value="<?php echo htmlspecialchars($userData['country_code']); ?>" placeholder="Enter country">
                                                </div>
                                            </div>
                                            <div class="col-sm-12">
                                                <div class="mb-20">
                                                    <label for="about" class="form-label fw-semibold text-primary-light text-sm mb-8">About</label>
                                                    <textarea name="about" class="form-control radius-8" id="about" placeholder="Write about yourself..."><?php echo htmlspecialchars($userData['about'] ?? ''); ?></textarea>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-center gap-3">
                                            <button type="submit" name="update_profile" class="btn btn-primary border border-primary-600 text-md px-56 py-12 radius-8"> 
                                                Save
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
        <?php
        if (isset($_GET['message'])) {
            $messageParts = explode(':', urldecode($_GET['message']));
            $messageType = $messageParts[0];
            $messageText = $messageParts[1];
            echo "<script>
                Swal.fire({
                    icon: '" . ($messageType === 'success' ? 'success' : 'error') . "',
                    text: '" . $messageText . "',
                    showConfirmButton: false,
                    timer: 2500,
                    customClass: {
                        popup: 'small-text-popup'
                    }
                });
            </script>";
        }
        ?>
        <?php include "include/footer.php" ?>
    </main>
    <?php include "include/script.php" ?>

    <script>
    $(document).ready(function() {
        $('#pills-tab button').on('click', function (e) {
            e.preventDefault();
            $(this).tab('show');
        });

        <?php if ($userType === 'admin'): ?>
        // Ensure the password tab is visible for admin users
        $('#pills-change-password-tab').show();
        <?php endif; ?>
    });
</script>

</body>
</html>