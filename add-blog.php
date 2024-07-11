<?php
include "include/session.php";
include "include/connect.php";
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Blog - Magic Of Skills Dashboard</title>
  <?php include "include/meta.php" ?>
</head>
<body>
  <?php include "include/aside.php" ?>

  <main class="dashboard-main">
    <?php include "include/header.php" ?>

    <div class="dashboard-main-body">
      <div class="card basic-data-table radius-12 overflow-hidden">
        <div class="card-body p-24">
          <h2 class="mb-4">Add New Blog</h2>
          
          <form id="addBlogForm" enctype="multipart/form-data">
            <div class="mb-3">
              <input value="<?php echo $_SESSION['token'] ?>" hidden name="token">
              <label for="title" class="form-label">Title</label>
              <input type="text" class="form-control" id="title" name="title" required>
            </div>
            
            <div class="mb-3">
              <label for="subtitle" class="form-label">Subtitle</label>
              <textarea class="form-control" id="subtitle" name="subtitle" rows="3" required></textarea>
            </div>

            <div class="mb-3">
              <label for="category" class="form-label">Category</label>
              <select class="form-select" id="category" name="category_id" required>
              <?php
          $sql="Select * from blog_categories";
          $results=$connect->query($sql);
          while($final=$results->fetch_assoc()){ ?>
                                            <option value="<?php echo $final['id'];?>"><?php echo $final['name'];?>
                                            </option>
                                            <?php }
                                        ?>
              </select>
            </div>

            <div class="mb-3">
              <label for="author" class="form-label">Author Name</label>
              <input type="text" class="form-control" id="author" name="author_name" required>
            </div>

            <div class="mb-3">
              <label for="icon" class="form-label">Icon</label>
              <input type="file" class="form-control" id="icon" name="icon" accept="image/*" required>
            </div>

            <div class="mb-3">
              <label for="banner" class="form-label">Banner Image</label>
              <input type="file" class="form-control" id="banner" name="banner" accept="image/*" required>
            </div>

            <div class="mb-3">
              <label for="quote" class="form-label">Quote</label>
              <textarea class="form-control" id="quote" name="quote" rows="2"></textarea>
            </div>

            <div class="mb-3">
              <label for="tags" class="form-label">Tags (comma separated)</label>
              <input type="text" class="form-control" id="tags" name="tags">
            </div>

            <div class="mb-3">
              <label for="content" class="form-label">Content</label>
              <div id="editor"></div>
              <input type="hidden" name="content" id="hiddenContent">
            </div>

            <button type="submit" class="btn btn-primary">Submit</button>
          </form>
        </div>
      </div>
    </div>

    <?php include "include/footer.php" ?>
  </main>

  <?php include "include/script.php" ?>
  <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
  <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

  <script>
var toolbarOptions = [
  ['bold', 'italic', 'underline', 'strike'],        // toggled buttons
  ['blockquote', 'code-block'],

  [{ 'header': 1 }, { 'header': 2 }],               // custom button values
  [{ 'list': 'ordered'}, { 'list': 'bullet' }],
  [{ 'script': 'sub'}, { 'script': 'super' }],      // superscript/subscript
  [{ 'indent': '-1'}, { 'indent': '+1' }],          // outdent/indent
  [{ 'direction': 'rtl' }],                         // text direction

  [{ 'size': ['small', false, 'large', 'huge'] }],  // custom dropdown
  [{ 'header': [1, 2, 3, 4, 5, 6, false] }],

  [{ 'color': [] }, { 'background': [] }],          // dropdown with defaults
  [{ 'font': [] }],
  [{ 'align': [] }],

  ['clean'],                                        // remove formatting button

  ['link', 'image', 'video']                        // link and image, video
];

var quill = new Quill('#editor', {
  modules: {
    toolbar: toolbarOptions
  },
  theme: 'snow'
});

  $('#addBlogForm').on('submit', function(e) {
    e.preventDefault();
    $('#hiddenContent').val(quill.root.innerHTML);
    
    var formData = new FormData(this);
    
    $.ajax({
      url: '<?php echo $apiEndpoint; ?>insertBlog',
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function(response) {
        if(response.status) {
          Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: response.message,
            showConfirmButton: false,
            timer: 1500
          }).then(() => {
            // Reset form
            $('#addBlogForm')[0].reset();
            quill.root.innerHTML = '';
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: response.message || 'Something went wrong!'
          });
        }
      },
      error: function(xhr, status, error) {
        Swal.fire({
          icon: 'error',
          title: 'Oops...',
          text: 'An error occurred while submitting the form.'
        });
      }
    });
  });
</script>
</body>
</html>