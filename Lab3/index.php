<!DOCTYPE html>
<html>
<body>
<h2>Upload file to S3</h2>

<form action="upload.php" method="post" enctype="multipart/form-data">
  Select file to upload:<br><br>
  <input type="file" name="fileToUpload" required>
  <br><br>
  <input type="submit" value="Upload File">
</form>

</body>
</html>
