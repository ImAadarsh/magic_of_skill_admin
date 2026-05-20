<?php
include "include/session.php";
include "include/connect.php";

// Handle File Upload via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'upload') {
    header('Content-Type: application/json');
    try {
        if (!isset($_FILES['hero_image']) || $_FILES['hero_image']['error'] !== UPLOAD_ERR_OK) {
            $errorCode = $_FILES['hero_image']['error'] ?? UPLOAD_ERR_NO_FILE;
            throw new Exception("File upload failed with error code: " . $errorCode);
        }

        $fileTmpPath = $_FILES['hero_image']['tmp_name'];
        $fileSize = $_FILES['hero_image']['size'];

        // Validate file size (max 5MB)
        if ($fileSize > 5 * 1024 * 1024) {
            throw new Exception("File size exceeds limit of 5MB.");
        }

        // Validate file is a valid image
        $check = getimagesize($fileTmpPath);
        if ($check === false) {
            throw new Exception("Uploaded file is not a valid image.");
        }

        // Validate mime type
        $allowedMimeTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
        if (!in_array($check['mime'], $allowedMimeTypes)) {
            throw new Exception("Invalid image format. Allowed formats: PNG, JPEG, JPG, GIF, WEBP.");
        }

        $targetDir = "../mos_frontend/assets/img/";
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $targetFilePath = $targetDir . "hero-mos.png";
        $localSaveSuccess = false;

        // Try local file replacement if possible
        try {
            if (is_writable($targetDir) || (file_exists($targetFilePath) && is_writable($targetFilePath))) {
                if (move_uploaded_file($fileTmpPath, $targetFilePath)) {
                    $localSaveSuccess = true;
                }
            }
        } catch (Exception $e) {
            $localSaveSuccess = false;
        }

        if ($localSaveSuccess) {
            // Update database settings table with local path
            $dbPath = 'assets/img/hero-mos.png';
            $stmt = mysqli_prepare($connect, "INSERT INTO Quest_settings (setting_key, setting_value, description) VALUES ('homepage_hero_image', ?, 'Homepage Hero Image Path') ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            mysqli_stmt_bind_param($stmt, "ss", $dbPath, $dbPath);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            echo json_encode(['status' => true, 'message' => 'Homepage image updated locally and database reference saved!']);
        } else {
            // Fallback to storing image as Base64 in database
            $imgData = file_get_contents($fileTmpPath);
            $mimeType = $check['mime'];
            $base64Image = 'data:' . $mimeType . ';base64,' . base64_encode($imgData);

            // Ensure setting_value column type can hold large text
            @mysqli_query($connect, "ALTER TABLE Quest_settings MODIFY setting_value MEDIUMTEXT");

            $stmt = mysqli_prepare($connect, "INSERT INTO Quest_settings (setting_key, setting_value, description) VALUES ('homepage_hero_image', ?, 'Homepage Hero Image Base64') ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            mysqli_stmt_bind_param($stmt, "ss", $base64Image, $base64Image);
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['status' => true, 'message' => 'Homepage image uploaded directly to database settings successfully!']);
            } else {
                throw new Exception("Failed to save image to database: " . mysqli_error($connect));
            }
            mysqli_stmt_close($stmt);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Fetch current image from DB settings
$hero_query = mysqli_query($connect, "SELECT setting_value FROM Quest_settings WHERE setting_key = 'homepage_hero_image'");
$imageSrc = "../mos_frontend/assets/img/hero-mos.png";
if ($hero_query && mysqli_num_rows($hero_query) > 0) {
    $row = mysqli_fetch_assoc($hero_query);
    if (!empty($row['setting_value'])) {
        if (strpos($row['setting_value'], 'public/') === 0) {
            $imageSrc = $uri . $row['setting_value'];
        } elseif (strpos($row['setting_value'], 'data:') === 0) {
            $imageSrc = $row['setting_value'];
        } elseif (strpos($row['setting_value'], 'assets/img/') === 0) {
            $imageSrc = "../mos_frontend/" . $row['setting_value'] . "?v=" . time();
        } else {
            $imageSrc = $row['setting_value'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homepage Settings - Magic Of Skills Dashboard</title>
    <?php include "include/meta.php" ?>
    <style>
        .image-preview-container {
            max-width: 100%;
            height: 350px;
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background-color: #f9fafb;
            position: relative;
            margin-bottom: 20px;
            transition: border-color 0.3s;
        }
        .image-preview-container:hover {
            border-color: #4f46e5;
        }
        .image-preview-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .placeholder-text {
            color: #6b7280;
            text-align: center;
        }
        .placeholder-text iconify-icon {
            font-size: 48px;
            display: block;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <?php include "include/aside.php" ?>

    <main class="dashboard-main">
        <?php include "include/header.php" ?>

        <div class="dashboard-main-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
                <h6 class="fw-semibold mb-0">Homepage Settings</h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="dashboard.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Dashboard
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">Content Management - Homepage Settings</li>
                </ul>
            </div>

            <div class="row">
                <div class="col-lg-8 col-md-10 col-sm-12 mx-auto">
                    <div class="card basic-data-table radius-12 overflow-hidden">
                        <div class="card-body p-24">
                            <h4 class="mb-24">Modify Hero Banner Image</h4>
                            <p class="text-secondary-light mb-24">Upload a new image to replace the girl character image displayed on the frontend homepage. Recommended size: 600px x 600px with transparent background.</p>

                            <form id="homepageSettingsForm" enctype="multipart/form-data">
                                <div class="mb-24">
                                    <label class="form-label fw-semibold text-secondary-light">Current Image Preview</label>
                                    <div class="image-preview-container" id="previewContainer">
                                        <img src="<?php echo $imageSrc; ?>" id="currentHeroImage" alt="Current Hero Image">
                                    </div>
                                </div>

                                <div class="mb-24">
                                    <label for="hero_image" class="form-label fw-semibold text-secondary-light">Select New Image</label>
                                    <input class="form-control" type="file" id="hero_image" name="hero_image" accept="image/*" required>
                                </div>

                                <div class="d-flex align-items-center justify-content-end gap-3 mt-24">
                                    <button type="submit" class="btn btn-primary px-32 py-12 radius-8">Update Image</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "include/footer.php" ?>
    </main>

    <?php include "include/script.php" ?>

    <script>
        // Real-time image preview change
        $('#hero_image').on('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#currentHeroImage').attr('src', e.target.result);
                }
                reader.readAsDataURL(file);
            }
        });

        // Form submission via AJAX
        $('#homepageSettingsForm').on('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            Swal.fire({
                title: 'Uploading...',
                text: 'Please wait while we update the homepage image.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: 'homepage-settings.php?action=upload',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(response) {
                    Swal.close();
                    if (response.status) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Updated!',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            // Reload page to refresh filemtime cache buster and form state
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Upload Failed',
                            text: response.message || 'An error occurred during file upload.'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.close();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'A connection or server error occurred.'
                    });
                }
            });
        });
    </script>
</body>
</html>
