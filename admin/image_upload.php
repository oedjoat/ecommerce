<?php
// admin/image_upload.php - Safe image upload helper.
// All upload-handling endpoints should require this and call save_uploaded_image().

/**
 * Validate and store an uploaded image. Returns the stored basename on success.
 * Throws RuntimeException on failure (caller should catch and surface a message).
 *
 * Security:
 *   - Verifies upload via is_uploaded_file()
 *   - Caps file size
 *   - Validates real MIME type via finfo (not the client-supplied type)
 *   - Generates a random server-side filename so user input cannot influence the path
 *   - Writes only inside the configured upload dir
 */
function save_uploaded_image(array $file, string $uploadDir, int $maxBytes = 4_000_000): string {
    if (!isset($file['tmp_name'], $file['error']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('No valid file uploaded.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (error ' . $file['error'] . ').');
    }
    if (!isset($file['size']) || $file['size'] <= 0 || $file['size'] > $maxBytes) {
        throw new RuntimeException('File size is invalid or too large (max ' . $maxBytes . ' bytes).');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Unsupported image type: ' . $mime);
    }
    $ext = $allowed[$mime];

    // Defensive: ensure it's actually decodable as an image
    if (@getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('File is not a valid image.');
    }

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Upload directory does not exist.');
        }
    }

    // Random filename - never derived from user input
    $basename = bin2hex(random_bytes(16)) . '.' . $ext;
    $target   = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $basename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Failed to move uploaded file.');
    }

    return $basename;
}

/** Best-effort delete of a stored image. */
function delete_stored_image(string $uploadDir, ?string $basename): void {
    if (!$basename) return;
    // Defensive against any path traversal in stored names
    $basename = basename($basename);
    $path = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $basename;
    if (is_file($path)) {
        @unlink($path);
    }
}
