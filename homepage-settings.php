<?php
include "include/session.php";
include "include/connect.php";

// Fetch current image from DB — build URL exactly like trainer/workshop images
$hero_query = mysqli_query($connect, "SELECT setting_value FROM Quest_settings WHERE setting_key = 'homepage_hero_image'");
$imageSrc   = "assets/img/hero-mos-default.png"; // generic fallback
if ($hero_query && mysqli_num_rows($hero_query) > 0) {
    $row = mysqli_fetch_assoc($hero_query);
    if (!empty($row['setting_value'])) {
        $val = $row['setting_value'];
        if (strpos($val, 'public/') === 0) {
            // Laravel storage path — prepend $uri (same as blog/trainer images)
            $imageSrc = $uri . $val;
        } elseif (strpos($val, 'data:') === 0) {
            $imageSrc = $val;
        } elseif (strpos($val, 'assets/img/') === 0) {
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

                            <!-- Exact same form structure as add-blog.php / add-trainer.php -->
                            <form id="homepageSettingsForm" enctype="multipart/form-data">
                                <!-- Token passed as hidden field — same as blog/trainer forms -->
                                <input value="<?php echo $_SESSION['token'] ?>" hidden name="token">

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
        // Real-time image preview on file select
        $('#hero_image').on('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#currentHeroImage').attr('src', e.target.result);
                };
                reader.readAsDataURL(file);
            }
        });

        // Submit — identical pattern to add-blog.php / add-trainer.php
        // Posts FormData directly to the API endpoint from the browser
        $('#homepageSettingsForm').on('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);

            $.ajax({
                url: '<?php echo $apiEndpoint; ?>uploadHeroImage',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(response) {
                    if (response.status) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Updated!',
                            text: response.message,
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Upload Failed',
                            text: response.message || 'Something went wrong!'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: 'An error occurred while uploading the image.'
                    });
                }
            });
        });
    </script>
</body>
</html>
