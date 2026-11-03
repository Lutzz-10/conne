<?php

require_once __DIR__ . '/../helpers/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/home.php');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Permintaan tidak valid.');
    redirect('/pages/home.php');
}

$postId = filter_var(
    $_POST['post_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$postId || $postId < 1) {
    set_flash('error', 'Postingan tidak valid.');
    redirect('/pages/home.php');
}

$currentUser = current_user_id();

// Pastikan postingan memang milik pengguna.
$check = $pdo->prepare("
    SELECT *
    FROM posts
    WHERE id = :id AND user_id = :user_id
    LIMIT 1
");

$check->execute([
    ':id' => $postId,
    ':user_id' => $currentUser
]);

$post = $check->fetch();

if (!$post) {
    set_flash('error', 'Postingan tidak ditemukan atau bukan milik kamu.');
    redirect('/pages/home.php');
}

$detailUrl = '/pages/edit-post.php?id=' . $postId;

$content = trim($_POST['content'] ?? '');

$length = function_exists('mb_strlen')
    ? mb_strlen($content, 'UTF-8')
    : strlen($content);

if ($length > 250) {
    set_flash('error', 'Postingan maksimal 250 karakter.');
    redirect($detailUrl);
}

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

$oldImageToDelete = null;
$oldFileToDelete = null;

try {

    // Upload gambar baru jika pengguna memilihnya.
    $newImage = upload_file(
        'image',
        'uploads/posts',
        $imageMimes
    );

    // Upload file baru jika pengguna memilihnya.
    $newFile = upload_file(
        'file',
        'uploads/posts',
        $fileMimes
    );

    // Tentukan gambar yang akan disimpan.
    $imagePath = $post['image'];

    if ($newImage) {
        $imagePath = $newImage;
        $oldImageToDelete = $post['image'];
    } elseif (!empty($_POST['remove_image'])) {
        $imagePath = null;
        $oldImageToDelete = $post['image'];
    }

    // Tentukan file yang akan disimpan.
    $filePath = $post['file'];
    $fileName = $post['file_name'];

    if ($newFile) {
        $filePath = $newFile;
        $fileName = basename($_FILES['file']['name']);
        $oldFileToDelete = $post['file'];
    } elseif (!empty($_POST['remove_file'])) {
        $filePath = null;
        $fileName = null;
        $oldFileToDelete = $post['file'];
    }

    // Postingan tidak boleh kosong.
    if ($content === '' && !$imagePath && !$filePath) {
        throw new RuntimeException(
            'Postingan harus memiliki teks, gambar, atau file.'
        );
    }

    $update = $pdo->prepare("
        UPDATE posts
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
        ':id' => $postId,
        ':user_id' => $currentUser
    ]);

    // Hapus lampiran lama setelah database berhasil diperbarui.
    if ($oldImageToDelete) {
        $path = BASE_PATH . '/' . $oldImageToDelete;

        if (is_file($path)) {
            unlink($path);
        }
    }

    if ($oldFileToDelete) {
        $path = BASE_PATH . '/' . $oldFileToDelete;

        if (is_file($path)) {
            unlink($path);
        }
    }

    set_flash('success', 'Postingan berhasil diperbarui.');

} catch (RuntimeException $e) {

    // Bersihkan file baru jika proses gagal.
    if ($newImage && is_file(BASE_PATH . '/' . $newImage)) {
        unlink(BASE_PATH . '/' . $newImage);
    }

    if ($newFile && is_file(BASE_PATH . '/' . $newFile)) {
        unlink(BASE_PATH . '/' . $newFile);
    }

    set_flash('error', $e->getMessage());
    redirect($detailUrl);

} catch (PDOException $e) {

    error_log('Update post error: ' . $e->getMessage());

    if ($newImage && is_file(BASE_PATH . '/' . $newImage)) {
        unlink(BASE_PATH . '/' . $newImage);
    }

    if ($newFile && is_file(BASE_PATH . '/' . $newFile)) {
        unlink(BASE_PATH . '/' . $newFile);
    }

    set_flash('error', 'Postingan gagal diperbarui.');
    redirect($detailUrl);
}

redirect('/pages/home.php');