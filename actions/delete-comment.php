<?php

/**
 * =====================================================
 * DELETE COMMENT - CONNE
 * Memproses penghapusan komentar.
 * =====================================================
 */

require_once __DIR__ . '/../helpers/auth.php';

require_login();

// -----------------------------------------------------
// 1. MEMERIKSA REQUEST
// -----------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/home.php');
}

// Memeriksa token CSRF.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

    set_flash('error', 'Permintaan tidak valid.');

    redirect('/pages/home.php');
}


// -----------------------------------------------------
// 2. MENGAMBIL ID KOMENTAR
// -----------------------------------------------------

$commentId = filter_var(
    $_POST['comment_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$commentId || $commentId < 1) {

    set_flash('error', 'Komentar tidak valid.');

    redirect('/pages/home.php');
}


// -----------------------------------------------------
// 3. MEMERIKSA KEPEMILIKAN KOMENTAR
// -----------------------------------------------------

$currentUser = current_user_id();

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

// Memastikan komentar ditemukan dan milik pengguna.
if (!$comment) {

    set_flash(
        'error',
        'Komentar tidak ditemukan atau bukan milik kamu.'
    );

    redirect('/pages/home.php');
}


// URL untuk kembali ke halaman detail postingan.
$detailUrl = '/pages/detail-post.php?id=' . (int) $comment['post_id'];


// -----------------------------------------------------
// 4. MENGHAPUS KOMENTAR
// -----------------------------------------------------

try {

    $delete = $pdo->prepare("
        DELETE FROM comments
        WHERE id = :id
        AND user_id = :user_id
    ");

    $delete->execute([
        ':id' => $commentId,
        ':user_id' => $currentUser
    ]);

    // Memastikan komentar benar-benar terhapus.
    if ($delete->rowCount() < 1) {

        set_flash('error', 'Komentar gagal dihapus.');

        redirect($detailUrl);
    }


    // -------------------------------------------------
    // 5. MENGHAPUS FILE LAMPIRAN
    // -------------------------------------------------

    if (!empty($comment['image'])) {

        $imagePath = BASE_PATH . '/' . $comment['image'];

        if (is_file($imagePath)) {
            unlink($imagePath);
        }
    }


    if (!empty($comment['file'])) {

        $filePath = BASE_PATH . '/' . $comment['file'];

        if (is_file($filePath)) {
            unlink($filePath);
        }
    }


    set_flash('success', 'Komentar berhasil dihapus.');

} catch (PDOException $e) {

    error_log('Delete comment error: ' . $e->getMessage());

    set_flash('error', 'Komentar gagal dihapus.');

}


// -----------------------------------------------------
// 6. KEMBALI KE DETAIL POSTINGAN
// -----------------------------------------------------

redirect($detailUrl);