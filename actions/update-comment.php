<?php

/**
 * =====================================================
 * UPDATE COMMENT - CONNE
 * Memproses perubahan komentar.
 * =====================================================
 */

require_once __DIR__ . '/../helpers/auth.php';

require_login();

// Memastikan request POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/home.php');
}

// Memeriksa CSRF.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Permintaan tidak valid.');
    redirect('/pages/home.php');
}

// Mengambil ID komentar.
$commentId = filter_var(
    $_POST['comment_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$commentId || $commentId < 1) {
    set_flash('error', 'Komentar tidak valid.');
    redirect('/pages/home.php');
}

$currentUser = current_user_id();

// Mengambil komentar yang dimiliki pengguna.
$check = $pdo->prepare("
    SELECT *
    FROM comments
    WHERE id = :id
    AND user_id = :user_id
    LIMIT 1
");

$check->execute([
    ':id' => $commentId,
    ':user_id' => $currentUser
]);

$comment = $check->fetch();

if (!$comment) {
    set_flash('error', 'Komentar tidak ditemukan atau bukan milik kamu.');
    redirect('/pages/home.php');
}

// URL untuk kembali ke halaman edit.
$editUrl = '/pages/edit-comment.php?id=' . $commentId;

// URL halaman detail postingan.
$detailUrl = '/pages/detail-post.php?id=' . (int) $comment['post_id'];


// -----------------------------------------------------
// 1. MEMERIKSA ISI KOMENTAR
// -----------------------------------------------------

$content = trim($_POST['content'] ?? '');

$length = function_exists('mb_strlen')
    ? mb_strlen($content, 'UTF-8')
    : strlen($content);

if ($length > 250) {
    set_flash('error', 'Komentar maksimal 250 karakter.');
    redirect($editUrl);
}


// -----------------------------------------------------
// 2. FORMAT FILE YANG DIPERBOLEHKAN
// -----------------------------------------------------

$imageMimes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp'
];

$fileMimes = [
    'application/pdf' => 'pdf',
    'text/plain' => 'txt',
    'text/csv' => 'csv',
    'application/csv' => 'csv',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-excel' => 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    'application/vnd.ms-powerpoint' => 'ppt',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    'application/zip' => 'zip',
    'application/x-zip-compressed' => 'zip'
];

$newImage = null;
$newFile = null;


// -----------------------------------------------------
// 3. PROSES UPDATE KOMENTAR
// -----------------------------------------------------

try {

    // Upload gambar baru jika dipilih.
    $newImage = upload_file(
        'image',
        'uploads/comments',
        $imageMimes
    );

    // Upload file baru jika dipilih.
    $newFile = upload_file(
        'file',
        'uploads/comments',
        $fileMimes
    );


    // Menggunakan lampiran lama sebagai nilai awal.
    $imagePath = $comment['image'];
    $filePath = $comment['file'];
    $fileName = $comment['file_name'];

    $oldImageToDelete = null;
    $oldFileToDelete = null;


    // Mengganti atau menghapus gambar.
    if ($newImage) {

        $imagePath = $newImage;
        $oldImageToDelete = $comment['image'];

    } elseif (!empty($_POST['remove_image'])) {

        $imagePath = null;
        $oldImageToDelete = $comment['image'];

    }


    // Mengganti atau menghapus file.
    if ($newFile) {

        $filePath = $newFile;
        $fileName = basename($_FILES['file']['name']);
        $oldFileToDelete = $comment['file'];

    } elseif (!empty($_POST['remove_file'])) {

        $filePath = null;
        $fileName = null;
        $oldFileToDelete = $comment['file'];

    }


    // Memastikan komentar tidak kosong seluruhnya.
    if ($content === '' && !$imagePath && !$filePath) {
        throw new RuntimeException(
            'Komentar harus memiliki teks, gambar, atau file.'
        );
    }


    // Update database.
    $update = $pdo->prepare("
        UPDATE comments
        SET
            content = :content,
            image = :image,
            file = :file,
            file_name = :file_name
        WHERE id = :id
        AND user_id = :user_id
    ");

    $update->execute([

        ':content' => $content,

        ':image' => $imagePath,

        ':file' => $filePath,

        ':file_name' => $fileName,

        ':id' => $commentId,

        ':user_id' => $currentUser

    ]);


    // Hapus lampiran lama setelah database berhasil diperbarui.
    if ($oldImageToDelete) {

        $oldImageFullPath = BASE_PATH . '/' . $oldImageToDelete;

        if (is_file($oldImageFullPath)) {
            unlink($oldImageFullPath);
        }

    }

    if ($oldFileToDelete) {

        $oldFileFullPath = BASE_PATH . '/' . $oldFileToDelete;

        if (is_file($oldFileFullPath)) {
            unlink($oldFileFullPath);
        }

    }


    set_flash('success', 'Komentar berhasil diperbarui.');

} catch (RuntimeException $e) {

    // Hapus upload baru jika proses gagal.
    if ($newImage && is_file(BASE_PATH . '/' . $newImage)) {
        unlink(BASE_PATH . '/' . $newImage);
    }

    if ($newFile && is_file(BASE_PATH . '/' . $newFile)) {
        unlink(BASE_PATH . '/' . $newFile);
    }

    set_flash('error', $e->getMessage());

    redirect($editUrl);

} catch (PDOException $e) {

    error_log('Update comment error: ' . $e->getMessage());

    // Hapus upload baru jika proses gagal.
    if ($newImage && is_file(BASE_PATH . '/' . $newImage)) {
        unlink(BASE_PATH . '/' . $newImage);
    }

    if ($newFile && is_file(BASE_PATH . '/' . $newFile)) {
        unlink(BASE_PATH . '/' . $newFile);
    }

    set_flash('error', 'Komentar gagal diperbarui.');

    redirect($editUrl);

}


// Kembali ke detail postingan.
redirect($detailUrl);