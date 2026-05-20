<?php
include "include/session.php";
include "include/connect.php";

// Handle File Upload via AJAX — sends to backend API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'upload') {
    header('Content-Type: application/json');
    try {
        if (!isset($_FILES['hero_image']) || $_FILES['hero_image']['error'] !== UPLOAD_ERR_OK) {
            $errorCode = $_FILES['hero_image']['error'] ?? UPLOAD_ERR_NO_FILE;
            throw new Exception("File upload failed with error code: " . $errorCode);
        }

        $fileTmpPath  = $_FILES['hero_image']['tmp_name'];
        $fileOrigName = $_FILES['hero_image']['name'];
        $fileSize     = $_FILES['hero_image']['size'];
        $fileMime     = mime_content_type($fileTmpPath);

        // Validate size (max 5MB)
        if ($fileSize > 5 * 1024 * 1024) {
            throw new Exception("File size exceeds limit of 5MB.");
        }

        // Validate image
        $check = getimagesize($fileTmpPath);
        if ($check === false) {
            throw new Exception("Uploaded file is not a valid image.");
        }

        $allowedMimeTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
        if (!in_array($check['mime'], $allowedMimeTypes)) {
            throw new Exception("Invalid format. Allowed: PNG, JPEG, JPG, GIF, WEBP.");
        }

        // Build multipart POST to backend API
        $token = $_SESSION['token'] ?? '';
        $curlFile = new CURLFile($fileTmpPath, $fileMime, $fileOrigName);

        $postData = [
            'token'      => $token,
            'hero_image' => $curlFile,
        ];

        $ch = curl_init($apiEndpoint . 'uploadHeroImage');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $apiResponse = curl_exec($ch);
        $curlError   = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("cURL error: " . $curlError);
        }

        $decoded = json_decode($apiResponse, true);
        if (!$decoded) {
            throw new Exception("Invalid response from server: " . substr($apiResponse, 0, 200));
        }

        if (!empty($decoded['status']) && $decoded['status'] === true) {
            echo json_encode(['status' => true, 'message' => $decoded['message'] ?? 'Hero image updated successfully!']);
        } else {
            throw new Exception($decoded['message'] ?? 'Upload failed on the server.');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Fetch current image from DB — build URL exactly like trainer/workshop images
$hero_query = mysqli_query($connect, "SELECT setting_value FROM Quest_settings WHERE setting_key = 'homepage_hero_image'");
$imageSrc   = $uri . 'public/homepage/hero-mos.png'; // fallback
if ($hero_query && mysqli_num_rows($hero_query) > 0) {
    $row = mysqli_fetch_assoc($hero_query);
    if (!empty($row['setting_value'])) {
        $val = $row['setting_value'];
        if (strpos($val, 'public/') === 0) {
            // Laravel storage path — prepend $uri
            $imageSrc = $uri . $val;
        } elseif (strpos($val, 'data:') === 0) {
            // Legacy Base64
            $imageSrc = $val;
        } elseif (strpos($val, 'assets/img/') === 0) {
            // Legacy local path
            $imageSrc = "../mos_frontend/" . $val . "?v=" . time();
        } else {
            $imageSrc = $val;
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
