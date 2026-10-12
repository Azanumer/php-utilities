<?php
/**
 * UploadedFile — secure file-upload handling without a framework.
 *
 * Validates the real MIME type with finfo (not the client-supplied one),
 * enforces an extension whitelist and a size limit, renames the file to a
 * random name, and refuses to write outside the configured destination dir.
 *
 *     $upload = new UploadedFile(array(
 *         'allowed_extensions' => array('jpg', 'jpeg', 'png', 'webp', 'pdf'),
 *         'max_bytes'          => 5 * 1024 * 1024,
 *     ));
 *
 *     try {
 *         $path = $upload->store($_FILES['avatar'], '/var/www/uploads');
 *         echo "Saved to: $path";
 *     } catch (UploadedFileException $e) {
 *         http_response_code(400);
 *         echo 'Upload failed: ' . $e->getMessage();
 *     }
 *
 * No dependencies, PHP 7.4+.
 */
class UploadedFileException extends RuntimeException {}

class UploadedFile {

	/** @var array */
	private $options;

	/** Extension -> list of MIME types finfo actually reports for them. */
	const MIME_MAP = array(
		'jpg'  => array('image/jpeg'),
		'jpeg' => array('image/jpeg'),
		'png'  => array('image/png'),
		'gif'  => array('image/gif'),
		'webp' => array('image/webp'),
		'pdf'  => array('application/pdf'),
		'svg'  => array('image/svg+xml', 'text/xml'),
		'mp4'  => array('video/mp4'),
		'mp3'  => array('audio/mpeg'),
		'zip'  => array('application/zip'),
	);

	/**
	 * @param array $options Optional:
	 *   'allowed_extensions' list like array('jpg','png','pdf') (default: jpg/jpeg/png/gif/webp/pdf)
	 *   'max_bytes'          maximum size, default 5 MB
	 *   'mime_map'           override/extend MIME_MAP (ext => array of mimes)
	 */
	public function __construct(array $options = array()) {
		$this->options = array_merge(array(
			'allowed_extensions' => array('jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'),
			'max_bytes'          => 5 * 1024 * 1024,
			'mime_map'           => self::MIME_MAP,
		), $options);
	}

	/**
	 * Validate and move an uploaded file into $destDir.
	 *
	 * @param array  $file     One $_FILES['x'] entry.
	 * @param string $destDir  Directory to store into (created if missing).
	 * @return string Absolute path of the stored file.
	 * @throws UploadedFileException on any validation/move failure.
	 */
	public function store(array $file, $destDir) {
		$this->assertValidEntry($file);
		$this->checkError($file);
		$this->checkSize($file);
		$ext = $this->extension($file['name']);
		$this->checkExtension($ext);
		$this->checkMime($file['tmp_name'], $ext);

		$destDir = rtrim($destDir, '/\\');
		if (!is_dir($destDir)) {
			if (!mkdir($destDir, 0755, true) && !is_dir($destDir)) {
				throw new UploadedFileException('Could not create destination directory.');
			}
		}
		// Path confinement: resolve and ensure we stay inside destDir.
		$realDest = realpath($destDir);
		if ($realDest === false) {
			throw new UploadedFileException('Destination directory is not resolvable.');
		}

		$name   = bin2hex(random_bytes(16)) . '.' . $ext;
		$target = $realDest . DIRECTORY_SEPARATOR . $name;

		if (!is_uploaded_file($file['tmp_name']) && !file_exists($file['tmp_name'])) {
			throw new UploadedFileException('Temp file is missing.');
		}
		$moved = is_uploaded_file($file['tmp_name'])
			? move_uploaded_file($file['tmp_name'], $target)
			: rename($file['tmp_name'], $target); // lets you unit-test without HTTP uploads
		if (!$moved) {
			throw new UploadedFileException('Failed to move the uploaded file.');
		}
		return $target;
	}

	/** Return the last error as a human message for the given $_FILES entry. */
	public static function errorMessage($code) {
		$messages = array(
			UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload limit.',
			UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form upload limit.',
			UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
			UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
			UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temporary folder.',
			UPLOAD_ERR_CANT_WRITE => 'Server failed to write the file.',
			UPLOAD_ERR_EXTENSION  => 'A server extension stopped the upload.',
		);
		return isset($messages[$code]) ? $messages[$code] : 'Unknown upload error.';
	}

	private function assertValidEntry(array $file) {
		foreach (array('name', 'tmp_name', 'size', 'error') as $key) {
			if (!array_key_exists($key, $file)) {
				throw new UploadedFileException('Not a valid upload entry (missing ' . $key . ').');
			}
		}
	}

	private function checkError(array $file) {
		if ($file['error'] !== UPLOAD_ERR_OK) {
			throw new UploadedFileException(self::errorMessage($file['error']));
		}
	}

	private function checkSize(array $file) {
		if ($file['size'] > $this->options['max_bytes']) {
			throw new UploadedFileException(sprintf(
				'File is too large (%s > %s).',
				$this->human($file['size']),
				$this->human($this->options['max_bytes'])
			));
		}
	}

	private function extension($name) {
		$ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
		return $ext;
	}

	private function checkExtension($ext) {
		if ($ext === '' || !in_array($ext, $this->options['allowed_extensions'], true)) {
			throw new UploadedFileException('File type not allowed: .' . $ext);
		}
	}

	/** Compare finfo's real MIME against the whitelist for this extension. */
	private function checkMime($tmpPath, $ext) {
		if (!function_exists('finfo_open')) {
			throw new UploadedFileException('PHP fileinfo extension is required.');
		}
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$real  = finfo_file($finfo, $tmpPath);
		finfo_close($finfo);
		$map     = $this->options['mime_map'];
		$allowed = isset($map[$ext]) ? $map[$ext] : array();
		if (!in_array($real, $allowed, true)) {
			throw new UploadedFileException(
				"File content does not match its extension (detected: $real, claimed: .$ext)."
			);
		}
	}

	private function human($bytes) {
		$units = array('B', 'KB', 'MB', 'GB');
		$i = 0;
		while ($bytes >= 1024 && $i < count($units) - 1) {
			$bytes /= 1024;
			$i++;
		}
		return round($bytes, 1) . ' ' . $units[$i];
	}
}
