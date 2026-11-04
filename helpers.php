<?php
// helpers.php - Book cover image helpers (upload, delete, display)

const COVER_DIR      = 'uploads/books/';
const COVER_MAX_SIZE = 2 * 1024 * 1024; // 2 MB

/**
 * Validates and stores an uploaded cover image.
 * Returns the relative path (saved to books.image_path) or null.
 * Sets $error if a file was uploaded but rejected.
 */
function saveCoverImage(array $file, string &$error): ?string {
    // No file chosen: not an error, just nothing to save
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "The image could not be uploaded. Try a smaller file.";
        return null;
    }

    if ($file['size'] > COVER_MAX_SIZE) {
        $error = "The image is too large. The maximum size is 2 MB.";
        return null;
    }

    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF  => 'gif',
    ];
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || !isset($allowed[$info[2]])) {
        $error = "Please upload a JPG, PNG, WEBP or GIF image.";
        return null;
    }

    $dir = __DIR__ . '/' . COVER_DIR;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        $error = "The uploads folder could not be created.";
        return null;
    }

    // Random file name: never trust the original name
    $name = bin2hex(random_bytes(8)) . '.' . $allowed[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
        $error = "The image could not be saved. Check folder permissions.";
        return null;
    }

    return COVER_DIR . $name;
}

/** Deletes a stored cover image file (only inside uploads/books/). */
function deleteCoverImage(?string $path): void {
    if ($path && strpos($path, COVER_DIR) === 0) {
        $full = __DIR__ . '/' . COVER_DIR . basename($path);
        if (is_file($full)) {
            unlink($full);
        }
    }
}

/** Returns an <img> for the cover, or a book-icon placeholder when there is none. */
function coverImage(?string $path, string $alt = ''): string {
    if ($path && is_file(__DIR__ . '/' . $path)) {
        return '<img class="cover-thumb" src="' . htmlspecialchars($path) . '" alt="' . htmlspecialchars($alt) . '" loading="lazy">';
    }
    return '<span class="cover-thumb cover-empty" aria-hidden="true">📖</span>';
}

// ---------- Loans: returns and renewals ----------

const LOAN_DAYS    = 7; // length of a loan, and of each renewal
const MAX_RENEWALS = 2; // renewals allowed per loan

/** An approved loan whose due date has passed. */
function isOverdue(array $r): bool {
    return $r['status'] === 'Approved'
        && !empty($r['due_date'])
        && strtotime($r['due_date']) < time();
}

/** Status badge. Overdue loans show a red "Overdue" badge instead of "Approved". */
function statusBadge(array $r): string {
    if (isOverdue($r)) {
        return '<span class="badge badge-overdue">Overdue</span>';
    }
    return '<span class="badge badge-' . htmlspecialchars(strtolower($r['status'])) . '">' . htmlspecialchars($r['status']) . '</span>';
}

/** Date part only (YYYY-MM-DD), or a dash when empty. */
function shortDate($value): string {
    return $value ? htmlspecialchars(substr((string)$value, 0, 10)) : '-';
}