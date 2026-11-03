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

try {

    // Ambil postingan dan pastikan pemiliknya benar.
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

    // Ambil lokasi lampiran komentar sebelum datanya dihapus.
    $commentQuery = $pdo->prepare("
        SELECT image, file
        FROM comments
        WHERE post_id = :post_id
    ");

    $commentQuery->execute([
        ':post_id' => $postId
    ]);

    $comments = $commentQuery->fetchAll();

    // Mulai transaksi database.
    $pdo->beginTransaction();

    // Hapus seluruh komentar pada postingan.
    $deleteComments = $pdo->prepare("
        DELETE FROM comments
        WHERE post_id = :post_id
    ");

    $deleteComments->execute([
        ':post_id' => $postId
    ]);

    // Hapus postingan.
    $deletePost = $pdo->prepare("
        DELETE FROM posts
        WHERE id = :id AND user_id = :user_id
    ");

    $deletePost->execute([
        ':id' => $postId,
        ':user_id' => $currentUser
    ]);

    $pdo->commit();

    // Setelah database berhasil, hapus file fisiknya.
    $filesToDelete = [];

    if (!empty($post['image'])) {
        $filesToDelete[] = $post['image'];
    }

    if (!empty($post['file'])) {
        $filesToDelete[] = $post['file'];
    }

    foreach ($comments as $comment) {

        if (!empty($comment['image'])) {
            $filesToDelete[] = $comment['image'];
        }

        if (!empty($comment['file'])) {
            $filesToDelete[] = $comment['file'];
        }
    }

    foreach ($filesToDelete as $file) {

        $path = BASE_PATH . '/' . $file;

        if (is_file($path)) {
            unlink($path);
        }
    }

    set_flash('success', 'Postingan berhasil dihapus.');

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Delete post error: ' . $e->getMessage());

    set_flash('error', 'Postingan gagal dihapus.');
}

redirect('/pages/home.php');