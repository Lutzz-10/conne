<?php

/**
 * =====================================================
 * DELETE POST PROCESS - CONNE
 * =====================================================
 * Memproses penghapusan postingan beserta relasi datanya:
 * 1. Memeriksa otorisasi pemilik postingan.
 * 2. Mengumpulkan path lampiran berkas postingan & komentar.
 * 3. Menghapus seluruh komentar di bawah postingan (DB Transaction).
 * 4. Menghapus data postingan dari database.
 * 5. Menghapus seluruh berkas fisik (gambar/dokumen) dari disk.
 */

// Memanggil modul autentikasi dan fungsi bantuan.
require_once __DIR__ . '/../helpers/auth.php';

// Memastikan hanya pengguna yang telah login yang dapat melakukan aksi ini.
require_login();

// -----------------------------------------------------
// 1. VALIDASI METODE REQUEST & CSRF TOKEN
// -----------------------------------------------------

// Memastikan request dikirim melalui metode POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/home.php');
}

// Memverifikasi token CSRF untuk mencegah serangan Cross-Site Request Forgery.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Permintaan tidak valid atau token sesi telah berakhir.');
    redirect('/pages/home.php');
}

// -----------------------------------------------------
// 2. VALIDASI ID POSTINGAN
// -----------------------------------------------------

// Mengambil dan memvalidasi tipe integer dari post_id.
$postId = filter_var(
    $_POST['post_id'] ?? null,
    FILTER_VALIDATE_INT
);

// Jika ID tidak valid, alihkan ke beranda dengan pesan error.
if (!$postId || $postId < 1) {
    set_flash('error', 'Postingan tidak valid.');
    redirect('/pages/home.php');
}

// Mengambil ID pengguna yang sedang aktif dari session.
$currentUser = current_user_id();

try {

    // -------------------------------------------------
    // 3. MEMERIKSA HAK KEPEMILIKAN POSTINGAN
    // -------------------------------------------------

    // Query prepared statement untuk memastikan postingan ada dan milik pengguna login.
    $check = $pdo->prepare("
        SELECT *
        FROM posts
        WHERE id = :id AND user_id = :user_id
        LIMIT 1
    ");

    $check->execute([
        ':id'      => $postId,
        ':user_id' => $currentUser
    ]);

    $post = $check->fetch();

    // Jika postingan tidak ditemukan atau bukan milik pengguna aktif, tolak akses.
    if (!$post) {
        set_flash('error', 'Postingan tidak ditemukan atau bukan milik kamu.');
        redirect('/pages/home.php');
    }

    // -------------------------------------------------
    // 4. MENGUMPULKAN LOKASI BERKAS LAMPIRAN KOMENTAR
    // -------------------------------------------------

    // Mengambil path gambar dan file seluruh komentar sebelum baris datanya dihapus di DB.
    $commentQuery = $pdo->prepare("
        SELECT image, file
        FROM comments
        WHERE post_id = :post_id
    ");

    $commentQuery->execute([
        ':post_id' => $postId
    ]);

    $comments = $commentQuery->fetchAll();

    // -------------------------------------------------
    // 5. TRANSAKSI BASIS DATA (ATOMIC TRANSACTION)
    // -------------------------------------------------

    // Memulai transaksi agar penghapusan komentar dan postingan bersifat all-or-nothing.
    $pdo->beginTransaction();

    // 1. Hapus seluruh komentar yang terhubung ke postingan ini.
    $deleteComments = $pdo->prepare("
        DELETE FROM comments
        WHERE post_id = :post_id
    ");

    $deleteComments->execute([
        ':post_id' => $postId
    ]);

    // 2. Hapus data postingan utama.
    $deletePost = $pdo->prepare("
        DELETE FROM posts
        WHERE id = :id AND user_id = :user_id
    ");

    $deletePost->execute([
        ':id'      => $postId,
        ':user_id' => $currentUser
    ]);

    // Melakukan commit perubahan ke database.
    $pdo->commit();

    // -------------------------------------------------
    // 6. PENGHAPUSAN BERKAS FISIK DARI STORAGE SERVER
    // -------------------------------------------------

    $filesToDelete = [];

    // Kumpulkan berkas milik postingan utama
    if (!empty($post['image'])) {
        $filesToDelete[] = $post['image'];
    }

    if (!empty($post['file'])) {
        $filesToDelete[] = $post['file'];
    }

    // Kumpulkan berkas milik seluruh komentar yang terhapus
    foreach ($comments as $comment) {
        if (!empty($comment['image'])) {
            $filesToDelete[] = $comment['image'];
        }

        if (!empty($comment['file'])) {
            $filesToDelete[] = $comment['file'];
        }
    }

    // Eksekusi penghapusan berkas fisik di server menggunakan unlink()
    foreach ($filesToDelete as $file) {
        $path = BASE_PATH . '/' . $file;

        if (is_file($path)) {
            unlink($path);
        }
    }

    set_flash('success', 'Postingan dan seluruh komentarnya berhasil dihapus.');

} catch (Throwable $e) {

    // Jika terjadi kegagalan sistem, batalkan seluruh perubahan database (Rollback).
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Catat pesan error ke log server.
    error_log('Delete post error: ' . $e->getMessage());

    set_flash('error', 'Terjadi kesalahan sistem saat menghapus postingan.');

}

// Alihkan pengguna kembali ke beranda.
redirect('/pages/home.php');
