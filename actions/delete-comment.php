<?php

/**
 * =====================================================
 * DELETE COMMENT PROCESS - CONNE
 * =====================================================
 * Memproses penghapusan komentar dari database:
 * 1. Memeriksa metode request POST dan validasi CSRF token.
 * 2. Memvalidasi kepemilikan komentar (otorisasi pengguna).
 * 3. Menghapus baris data komentar dari tabel `comments`.
 * 4. Menghapus berkas fisik lampiran (foto/dokumen) dari disk.
 * 5. Mengarahkan kembali ke halaman detail postingan.
 */

// Memanggil modul autentikasi dan fungsi bantuan.
require_once __DIR__ . '/../helpers/auth.php';

// Memastikan hanya pengguna yang telah login yang dapat melakukan penghapusan.
require_login();

// -----------------------------------------------------
// 1. MEMERIKSA REQUEST & CSRF TOKEN
// -----------------------------------------------------

// Memastikan hanya menerima metode POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/home.php');
}

// Memeriksa keabsahan token CSRF.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Permintaan tidak valid atau token sesi telah berakhir.');
    redirect('/pages/home.php');
}

// -----------------------------------------------------
// 2. MENGAMBIL & MEMVALIDASI ID KOMENTAR
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
// 3. MEMERIKSA KEPEMILIKAN KOMENTAR (OTORISASI)
// -----------------------------------------------------

$currentUser = current_user_id();

// Query untuk memastikan komentar ada dan benar-benar milik pengguna yang sedang login.
$check = $pdo->prepare("
    SELECT *
    FROM comments
    WHERE id = :id
    AND user_id = :user_id
    LIMIT 1
");

$check->execute([
    ':id'      => $commentId,
    ':user_id' => $currentUser
]);

$comment = $check->fetch();

// Jika komentar tidak ditemukan atau bukan milik pengguna aktif, tolak request.
if (!$comment) {
    set_flash(
        'error',
        'Komentar tidak ditemukan atau bukan milik kamu.'
    );
    redirect('/pages/home.php');
}

// URL untuk kembali ke halaman detail postingan asal komentar.
$detailUrl = '/pages/detail-post.php?id=' . (int) $comment['post_id'];

// -----------------------------------------------------
// 4. MENGHAPUS KOMENTAR DARI BASIS DATA
// -----------------------------------------------------

try {

    $delete = $pdo->prepare("
        DELETE FROM comments
        WHERE id = :id
        AND user_id = :user_id
    ");

    $delete->execute([
        ':id'      => $commentId,
        ':user_id' => $currentUser
    ]);

    // Memastikan baris data benar-benar terhapus.
    if ($delete->rowCount() < 1) {
        set_flash('error', 'Komentar gagal dihapus dari basis data.');
        redirect($detailUrl);
    }

    // -------------------------------------------------
    // 5. MENGHAPUS BERKAS LAMPIRAN FISIK DARI SERVER
    // -------------------------------------------------

    // Hapus lampiran gambar komentar jika ada.
    if (!empty($comment['image'])) {
        $imagePath = BASE_PATH . '/' . $comment['image'];

        if (is_file($imagePath)) {
            unlink($imagePath);
        }
    }

    // Hapus lampiran berkas dokumen komentar jika ada.
    if (!empty($comment['file'])) {
        $filePath = BASE_PATH . '/' . $comment['file'];

        if (is_file($filePath)) {
            unlink($filePath);
        }
    }

    set_flash('success', 'Komentar berhasil dihapus.');

} catch (PDOException $e) {

    // Mencatat detail error ke log server.
    error_log('Delete comment error: ' . $e->getMessage());

    set_flash('error', 'Terjadi kesalahan sistem saat menghapus komentar.');

}

// -----------------------------------------------------
// 6. KEMBALI KE DETAIL POSTINGAN
// -----------------------------------------------------

redirect($detailUrl);
