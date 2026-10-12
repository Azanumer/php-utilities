<?php
/**
 * upload-example.php — demo of UploadedFile.
 *
 * Run from a browser: this script shows an upload form and stores the file.
 * For CLI testing it builds a fake $_FILES entry from a local file:
 *   php examples/upload-example.php /path/to/picture.jpg
 */
require __DIR__ . '/../src/UploadedFile.php';

$upload = new UploadedFile(array(
	'allowed_extensions' => array('jpg', 'jpeg', 'png', 'webp', 'pdf'),
	'max_bytes'          => 5 * 1024 * 1024,
));

if (PHP_SAPI === 'cli') {
	$src = isset($argv[1]) ? $argv[1] : '';
	if ($src === '' || !is_file($src)) {
		fwrite(STDERR, "Usage: php examples/upload-example.php /path/to/file\n");
		exit(1);
	}
	$fake = array(
		'name'     => basename($src),
		'type'     => 'application/octet-stream',
		'tmp_name' => $src,
		'error'    => UPLOAD_ERR_OK,
		'size'     => filesize($src),
	);
	try {
		$path = $upload->store($fake, sys_get_temp_dir() . '/uploads-demo');
		echo "Stored: $path\n";
	} catch (UploadedFileException $e) {
		fwrite(STDERR, 'Upload failed: ' . $e->getMessage() . "\n");
		exit(1);
	}
	exit(0);
}
?>
<!doctype html>
<meta charset="utf-8">
<title>Upload demo</title>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	try {
		$path = $upload->store($_FILES['file'], __DIR__ . '/uploads');
		echo '<p>Stored: ' . htmlspecialchars(basename($path)) . '</p>';
	} catch (UploadedFileException $e) {
		echo '<p style="color:red">Upload failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
	}
}
?>
<form method="post" enctype="multipart/form-data">
	<input type="file" name="file" required>
	<button>Upload</button>
</form>
