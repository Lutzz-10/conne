<?php

/**
 * =====================================================
 * EDIT POST PAGE - CONNE
 * =====================================================
 * Halaman antarmuka untuk mengubah konten postingan
 * yang sudah dibuat oleh pengguna yang sedang login.
 * Memvalidasi kepemilikan postingan sebelum menampilkan form.
 */

// Memanggil modul autentikasi dan konfigurasi sistem.
require_once __DIR__ . '/../helpers/auth.php';

// Memastikan hanya pengguna yang sudah login yang dapat mengakses halaman ini.
require_login();

// Mengambil ID pengguna aktif dari session.
$currentUser = current_user_id();

// -----------------------------------------------------
// 1. VALIDASI PARAMETER ID POSTINGAN
// -----------------------------------------------------

// Mengambil dan memvalidasi ID postingan dari query string URL (?id=...).
$postId = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT
);

// Jika ID tidak valid atau kurang dari 1, alihkan ke beranda dengan pesan error.
if (!$postId || $postId < 1) {
    set_flash('error', 'Postingan tidak valid.');
    redirect('/pages/home.php');
}

// -----------------------------------------------------
// 2. MENGAMBIL DATA POSTINGAN DARI DATABASE
// -----------------------------------------------------

// Query prepared statement untuk mengambil postingan berdasarkan ID dan memastikan
// postingan tersebut benar-benar milik pengguna yang sedang login (kontrol akses).
$query = $pdo->prepare("
    SELECT *
    FROM posts
    WHERE id = :id AND user_id = :user_id
    LIMIT 1
");

$query->execute([
    ':id'      => $postId,
    ':user_id' => $currentUser
]);

$post = $query->fetch();

// Jika postingan tidak ditemukan atau bukan milik pengguna aktif, tolak akses.
if (!$post) {
    set_flash('error', 'Postingan tidak ditemukan atau bukan milik kamu.');
    redirect('/pages/home.php');
}

// Mengambil pesan flash notifikasi jika ada.
$flash = get_flash();

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Postingan - CONNE</title>
    <!-- Memuat berkas CSS utama aplikasi -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<!-- Navigasi Utama Aplikasi -->
<nav class="navbar">
    <a href="<?= BASE_URL ?>/pages/home.php" class="logo">CONNE</a>

    <div class="nav-menu">
        <a href="<?= BASE_URL ?>/pages/home.php">Home</a>
        <a href="<?= BASE_URL ?>/pages/profile.php">Profil</a>
    </div>
</nav>

<main class="container">

    <h2 class="page-title">Edit Postingan</h2>

    <!-- Tombol kembali ke halaman beranda -->
    <p>
        <a href="<?= BASE_URL ?>/pages/home.php"
           class="btn btn-secondary btn-small">
            &larr; Kembali
        </a>
    </p>

    <!-- Menampilkan pesan flash (sukses / error) jika tersedia -->
    <?php if ($flash): ?>
        <div class="alert <?= $flash['type'] === 'success'
            ? 'alert-success'
            : 'alert-error' ?>">
            <?= e($flash['message']); ?>
        </div>
    <?php endif; ?>

    <!-- Kartu Formulir Pengeditan Postingan -->
    <section class="card">

        <form
            action="<?= BASE_URL ?>/actions/update-post.php"
            method="POST"
            enctype="multipart/form-data"
        >

            <!-- Token CSRF untuk melindungi request formulir dari serangan CSRF -->
            <input type="hidden"
                   name="csrf_token"
                   value="<?= e(csrf_token()); ?>">

            <!-- ID postingan yang akan diperbarui -->
            <input type="hidden"
                   name="post_id"
                   value="<?= (int) $post['id']; ?>">

            <!-- Kolom Pengubahan Isi Teks Postingan -->
            <div class="form-group">
                <label for="content">Isi Postingan</label>

                <textarea
                    name="content"
                    id="content"
                    maxlength="250"
                    rows="5"
                    class="form-control"
                    placeholder="Apa yang sedang terjadi?"
                ><?= e($post['content']); ?></textarea>
            </div>

            <!-- Bagian Pratinjau & Opsi Hapus Gambar Lama (Jika Ada) -->
            <?php if (!empty($post['image'])): ?>

                <div class="form-group">
                    <label>Gambar Saat Ini</label>

                    <img
                        src="<?= BASE_URL . '/' . e($post['image']); ?>"
                        alt="Gambar postingan"
                        class="post-image"
                    >

                    <!-- Opsi checkbox jika pengguna ingin menghapus gambar yang ada -->
                    <label style="margin-top:8px; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                        <input type="checkbox" name="remove_image" value="1">
                        Hapus gambar saat ini
                    </label>
                </div>

            <?php endif; ?>

            <!-- Kolom Unggah Gambar Baru / Pengganti -->
            <div class="form-group">
                <label for="image">
                    <?= !empty($post['image'])
                        ? 'Ganti Gambar'
                        : 'Tambah Gambar'; ?>
                </label>

                <input
                    type="file"
                    name="image"
                    id="image"
                    class="form-control"
                    accept=".jpg,.jpeg,.png,.gif,.webp"
                >
            </div>

            <!-- Bagian Pratinjau & Opsi Hapus Berkas Dokumen Lama (Jika Ada) -->
            <?php if (!empty($post['file'])): ?>

                <div class="form-group">
                    <label>File Saat Ini</label>

                    <p>
                        <a
                            href="<?= BASE_URL . '/' . e($post['file']); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="file-link"
                        >
                            📎 <?= e($post['file_name'] ?? 'Lihat file'); ?>
                        </a>
                    </p>

                    <!-- Opsi checkbox jika pengguna ingin menghapus berkas dokumen yang ada -->
                    <label style="margin-top:8px; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                        <input type="checkbox" name="remove_file" value="1">
                        Hapus file saat ini
                    </label>
                </div>

            <?php endif; ?>

            <!-- Kolom Unggah Berkas Dokumen Baru / Pengganti -->
            <div class="form-group">
                <label for="file">
                    <?= !empty($post['file'])
                        ? 'Ganti File'
                        : 'Tambah File'; ?>
                </label>

                <input
                    type="file"
                    name="file"
                    id="file"
                    class="form-control"
                    accept=".pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip"
                >
            </div>

            <!-- Tombol Submit untuk Menyimpan Perubahan -->
            <button type="submit" class="btn btn-primary" style="margin-top:10px;">
                Simpan Perubahan
            </button>

        </form>

    </section>

</main>

</body>
</html>
