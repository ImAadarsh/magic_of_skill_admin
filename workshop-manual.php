<?php
include 'include/session.php';
include 'include/connect.php';

if (!$connect) {
    die("Connection failed: " . mysqli_connect_error());
}

// Function to generate random order ID
function generateOrderId() {
    return substr(str_shuffle(str_repeat($x='0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil(15/strlen($x)))), 1, 15);
}

// Function to validate mobile number
function isValidMobile($mobile) {
    return preg_match('/^[0-9]{10}$/', $mobile);
}

// Function to validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Handle form submission for enrollments
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enroll'])) {
    $userId = $_POST['user_id'];
    $workshopIds = $_POST['workshop_ids'];
    $paymentId = "Dashboard Added";
    $orderId = generateOrderId();

    foreach ($workshopIds as $workshopId) {
        $sql = "INSERT INTO payments (user_id, workshop_id, payment_id, order_id, payment_status, amount) 
                VALUES (?, ?, ?, ?, 1, (SELECT price FROM workshops WHERE id = ?))";
        $stmt = $connect->prepare($sql);
        $stmt->bind_param("iissi", $userId, $workshopId, $paymentId, $orderId, $workshopId);
        $stmt->execute();
    }

    echo json_encode(['status' => 'success', 'message' => 'Enrollment successful!']);
    exit;
}

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = $_FILES['csv_file']['tmp_name'];
        $successCount = 0;
        $errorCount = 0;
        if (($handle = fopen($file, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $firstName = $data[0];
                $lastName = $data[1];
                $mobile = $data[2];
                $email = $data[3];
                $countryCode = $data[4];
                $city = $data[5];
                $school = $data[6];
                $grade = $data[7];

                if (isValidMobile($mobile) && isValidEmail($email)) {
                    // Check if email or mobile already exists
                    $checkSql = "SELECT id FROM users WHERE email = ? OR mobile = ?";
                    $checkStmt = $connect->prepare($checkSql);
                    $checkStmt->bind_param("ss", $email, $mobile);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();

                    if ($checkResult->num_rows == 0) {
                        $sql = "INSERT INTO users (first_name, last_name, mobile, email, country_code, city, school, grade, mobile_verified, email_verified, is_data) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1)";
                        $stmt = $connect->prepare($sql);
                        $stmt->bind_param("ssssssss", $firstName, $lastName, $mobile, $email, $countryCode, $city, $school, $grade);
                        if ($stmt->execute()) {
                            $successCount++;
                        } else {
                            $errorCount++;
                        }
                    } else {
                        $errorCount++;
                    }
                } else {
                    $errorCount++;
                }
            }
            fclose($handle);
            echo json_encode(['status' => 'success', 'message' => "CSV upload completed. Added: $successCount, Errors: $errorCount"]);
            exit;
        }
    }
    echo json_encode(['status' => 'error', 'message' => 'CSV upload failed.']);
    exit;
}

// Fetch all users
$usersSql = "SELECT id, CONCAT(first_name, ' ', last_name) AS name, email, mobile, icon FROM users ORDER BY first_name";
$usersResult = $connect->query($usersSql);

// Fetch all workshops
$workshopsSql = "SELECT id, name, price, start_time FROM workshops ORDER BY start_time";
$workshopsResult = $connect->query($workshopsSql);

?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enroll Users - Magic Of Skills Dashboard</title>
    <?php include "include/meta.php" ?>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .enrollment-form, .csv-upload-form {
            background-color: #ffffff;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.s ease;
        }
        .enrollment-form:hover, .csv-upload-form:hover {
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }
        .select2-container--default .select2-selection--multiple {
            border: 1px solid #ced4da;
            border-radius: 8px;
        }
        .form-control, .form-select {
            border-radius: 8px;
        }
        .btn {
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
        }
        .form-label {
            font-weight: 600;
            color: #333;
        }
        #workshopFilter {
            margin-bottom: 15px;
        }
        .user-select .select2-selection__rendered,
.workshop-select .select2-selection__rendered {
    padding: 6px 12px;
    line-height: 1.5;
}

.user-select .select2-selection__rendered img,
.workshop-select .select2-selection__rendered img {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    margin-right: 8px;
    vertical-align: middle;
}

.workshop-select .select2-selection__choice {
    padding: 5px 10px;
    margin: 3px;
    background-color: #e9ecef;
    border: none;
    border-radius: 20px;
}

.workshop-select .select2-selection__choice__remove {
    margin-right: 5px;
    color: #dc3545;
}

.workshop-info {
    display: flex;
    align-items: center;
    font-size: 0.9em;
}

.workshop-info .duration,
.workshop-info .start-time,
.workshop-info .price {
    margin-left: 10px;
    color: #000;
}

.workshop-category {
    font-size: 0.8em;
    padding: 2px 6px;
    background-color: #007bff;
    color: white;
    border-radius: 10px;
    margin-left: 5px;
}
.user-option, .workshop-option {
    display: flex;
    align-items: center;
    padding: 5px;
}

.user-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    margin-right: 10px;
}

.user-name, .workshop-name {
    font-weight: bold;
}

.user-info, .workshop-info {
    margin-left: 10px;
    color: #000;
}

.select2-container .select2-selection--single {
    height: 38px;
    line-height: 38px;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background-color: #007bff;
    border: none;
    color: white;
    padding: 5px 10px;
    margin-top: 5px;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    color: white;
    margin-right: 5px;
}

#workshopFilter {
    margin-bottom: 10px;
}

#workshopFilter .btn {
    margin-right: 5px;
    margin-bottom: 5px;
}
.user-select, .workshop-select {
    width: 100%;
}

.workshop-select {
    height: 200px !important; /* Increase the height */
}

.select2-container .select2-selection--multiple {
    min-height: 200px !important; /* Match the select height */
}

.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background-color: #007bff;
    border: none;
    color: white;
    padding: 8px 12px;
    margin-top: 5px;
    margin-bottom: 5px;
    border-radius: 20px;
    font-size: 14px;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    color: white;
    margin-right: 8px;
    font-size: 16px;
}

#workshopFilter {
    margin-bottom: 15px;
}

#workshopFilter .btn {
    margin-right: 5px;
    margin-bottom: 8px;
}

.enrollment-form {
    max-width: 600px;
    margin: 0 auto;
}

/* Improve the overall layout */
.dashboard-main-body {
    padding: 30px;
}

.form-label {
    font-weight: 600;
    margin-bottom: 10px;
}

/* Make the Enroll button more prominent */
button[name="enroll"] {
    margin-top: 20px;
    padding: 12px 30px;
    font-size: 16px;
}
    </style>
</head>
<body>
    <?php include "include/aside.php" ?>

    <main class="dashboard-main">
        <?php include "include/header.php" ?>
    
        <div class="dashboard-main-body">
            <div class="container-fluid">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                    <h4 class="fw-bold mb-0">Enroll Users</h4>
                    <ul class="d-flex align-items-center gap-2">
                        <li class="fw-medium">
                            <a href="dashboard.php" class="d-flex align-items-center gap-1 hover-text-primary">
                                <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                                Dashboard
                            </a>
                        </li>
                        <li>-</li>
                        <li class="fw-medium">MOS | Enroll Users</li>
                    </ul>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                    <div class="enrollment-form">
    <h5 class="mb-4">Enroll Users to Workshops</h5>
    <form id="enrollmentForm" method="POST">
        <div class="mb-3">
            <label for="user_id" class="form-label">Select User</label>
            <select name="user_id" id="user_id" class="form-select user-select" required>
                <option value="">Select a user</option>
                <?php while ($user = $usersResult->fetch_assoc()): ?>
                    <option value="<?php echo $user['id']; ?>" 
                            data-avatar="<?php echo $user['icon'] ? $uri.$user['icon'] : 'assets/images/mos_icon.png'; ?>" 
                            data-info="<?php echo htmlspecialchars($user['email'] . ' | ' . $user['mobile']); ?>">
                        <?php echo htmlspecialchars($user['name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="mb-3">
            <label for="workshop_ids" class="form-label">Select Workshops</label>
            <div id="workshopFilter" class="mb-2">
                <button type="button" class="btn btn-sm btn-outline-primary filter-btn" data-filter="all">All</button>
                <button type="button" class="btn btn-sm btn-outline-primary filter-btn" data-filter="upcoming">Upcoming</button>
                <button type="button" class="btn btn-sm btn-outline-primary filter-btn" data-filter="past">Past</button>
                <button type="button" class="btn btn-sm btn-outline-primary filter-btn" data-filter="today">Today</button>
                <button type="button" class="btn btn-sm btn-outline-primary filter-btn" data-filter="this-week">This Week</button>
                <button type="button" class="btn btn-sm btn-outline-primary filter-btn" data-filter="this-month">This Month</button>
            </div>
            <select name="workshop_ids[]" id="workshop_ids" class="form-select workshop-select" multiple required>
                <?php while ($workshop = $workshopsResult->fetch_assoc()): ?>
                    <option value="<?php echo $workshop['id']; ?>" 
                            data-start-time="<?php echo $workshop['start_time']; ?>"
                            data-price="<?php echo $workshop['price']; ?>">
                        <?php echo htmlspecialchars($workshop['name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <button type="submit" name="enroll" class="btn btn-primary">Enroll</button>
    </form>
</div>
                    </div>
                    <div class="col-lg-6">
                        <div class="csv-upload-form">
                            <h5 class="mb-4">Upload Users via CSV</h5>
                            <form id="csvUploadForm" method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="csv_file" class="form-label">Upload CSV File</label>
                                    <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
                                </div>
                                <button type="submit" name="upload_csv" class="btn btn-success">Upload CSV</button>
                            </form>
                            <small class="text-muted mt-3 d-block">
                                CSV Format: First Name, Last Name, Mobile, Email, Country Code, City, School, Grade
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
  
        <?php include "include/footer.php" ?>
    </main>

    <script src="assets/js/lib/jquery-3.7.1.min.js"></script>
    <script src="assets/js/lib/bootstrap.bundle.min.js"></script>
    <script src="assets/js/lib/iconify-icon.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="assets/js/app.js"></script>

    <script>
        $(document).ready(function() {
            $('#user_id').select2({
        placeholder: "Select a user",
        allowClear: true,
        templateResult: formatUser,
        templateSelection: formatUser
    });

    $('#workshop_ids').select2({
        placeholder: "Select workshops",
        allowClear: true,
        templateResult: formatWorkshop,
        templateSelection: formatWorkshop
    });

    function formatUser(user) {
        if (!user.id) return user.text;
        var $user = $(
            '<div class="user-option">' +
            '<img src="' + $(user.element).data('avatar') + '" class="user-avatar" onerror="this.src=\'assets/images/default-avatar.png\'"/>' +
            '<span class="user-name">' + user.text + '</span>' +
            '<small class="user-info">' + $(user.element).data('info') + '</small>' +
            '</div>'
        );
        return $user;
    }

    function formatWorkshop(workshop) {
        if (!workshop.id) return workshop.text;
        var startTime = new Date($(workshop.element).data('start-time'));
        var $workshop = $(
            '<div class="workshop-option">' +
            '<span class="workshop-name">' + workshop.text + '</span>' +
            '<small class="workshop-info">' + 
            startTime.toLocaleDateString() + ' | ₹' + $(workshop.element).data('price') +
            '</small>' +
            '</div>'
        );
        return $workshop;
    }

    // Workshop filter functionality
    $('.filter-btn').on('click', function() {
        let filter = $(this).data('filter');
        let now = new Date();
        let options = $('#workshop_ids option');

        options.each(function() {
            let workshopDate = new Date($(this).data('start-time'));
            let show = false;

            switch(filter) {
                case 'all':
                    show = true;
                    break;
                case 'upcoming':
                    show = workshopDate > now;
                    break;
                case 'past':
                    show = workshopDate < now;
                    break;
                case 'today':
                    show = workshopDate.toDateString() === now.toDateString();
                    break;
                case 'this-week':
                    let weekStart = new Date(now.getFullYear(), now.getMonth(), now.getDate() - now.getDay());
                    let weekEnd = new Date(now.getFullYear(), now.getMonth(), now.getDate() - now.getDay() + 6);
                    show = workshopDate >= weekStart && workshopDate <= weekEnd;
                    break;
                case 'this-month':
                    show = workshopDate.getMonth() === now.getMonth() && workshopDate.getFullYear() === now.getFullYear();
                    break;
            }

            $(this).toggle(show);
        });

        $('#workshop_ids').select2('destroy').select2({
            templateResult: formatWorkshop,
            templateSelection: formatWorkshop
        });
    });

            // Enrollment form submission
            $('#enrollmentForm').on('submit', function(e) {
                e.preventDefault();
                let formData = new FormData(this);

                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        let res = JSON.parse(response);
                        Swal.fire({
                            title: res.status === 'success' ? 'Success!' : 'Error!',
                            text: res.message,
                            icon: res.status,
                            confirmButtonText: 'OK'
                        }).then(() => {
                            if (res.status === 'success') {
                                location.reload();
                            }
                        });
                    },
                    cache: false,
                    contentType: false,
                    processData: false
                });
            });

            // CSV upload form submission
            $('#csvUploadForm').on('submit', function(e) {
                e.preventDefault();
                let formData = new FormData(this);

                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        let res = JSON.parse(response);
                        Swal.fire({
                            title: res.status === 'success' ? 'Success!' : 'Error!',
                            text: res.message,
                            icon: res.status,
                            confirmButtonText: 'OK'
                        }).then(() => {
                            if (res.status === 'success') {
                                location.reload();
                            }
                        });
                    },
                    cache: false,
                    contentType: false,
                    processData: false
                });
            });
        });
    </script>
</body>
</html>