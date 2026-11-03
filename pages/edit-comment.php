<?php

/**
 * =====================================================
 * EDIT COMMENT PAGE - CONNE
 * =====================================================
 * Menampilkan antarmuka untuk mengubah isi komentar,
 * mengganti lampiran foto, atau mengganti dokumen berkas.
 * Hanya pemilik komentar yang diizinkan mengakses halaman ini.
 */

// Memanggil modul autentikasi dan fungsi bantuan.
require_once __DIR__ . '/../helpers/auth.php';

// Memastikan hanya pengguna yang telah login yang dapat mengakses form edit.
require_login();

// Mengambil ID akun pengguna yang sedang aktif dari session.
$currentUser = current_user_id();

// -----------------------------------------------------
// 1. VALIDASI PARAMETER ID KOMENTAR
// -----------------------------------------------------

// Mengambil dan memvalidasi integer ID komentar dari URL parameter (?id=...).
$commentId = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT
);

// Jika ID tidak valid, alihkan kembali ke beranda dengan notifikasi error.
if (!$commentId || $commentId < 1) {
    set_flash('error', 'Komentar tidak valid.');
    redirect('/pages/home.php');
}

// -----------------------------------------------------
// 2. MENGAMBIL DATA KOMENTAR MILIK PENGGUNA
// -----------------------------------------------------

// Query prepared statement untuk mengambil data komentar serta memastikan
// komentar tersebut adalah milik pengguna yang sedang aktif (hak akses/otorisasi).
$query = $pdo->prepare("
    SELECT
        c.*,
        p.id AS post_id
    FROM comments c
    INNER JOIN posts p ON c.post_id = p.id
    WHERE c.id = :id
    AND c.user_id = :user_id
    LIMIT 1
");

$query->execute([
    ':id'      => $commentId,
    ':user_id' => $currentUser
]);

$comment = $query->fetch();

// Jika komentar tidak ditemukan atau bukan milik akun aktif, tolak akses.
if (!$comment) {
    set_flash('error', 'Komentar tidak ditemukan atau bukan milik kamu.');
    redirect('/pages/home.php');
}

// Mengambil pesan kilat (flash message) dari sesi jika tersedia.
$flash = get_flash();

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Komentar - CONNE</title>
    <!-- Memuat lembar gaya CSS utama -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>

<body>

    <!-- Navigasi Atas Aplikasi -->
    <nav class="navbar">

        <a href="<?= BASE_URL ?>/pages/home.php" class="logo">
            CONNE
        </a>

        <div class="nav-menu">

            <a href="<?= BASE_URL ?>/pages/home.php">
                Home
            </a>

            <a href="<?= BASE_URL ?>/pages/profile.php">
                Profil
            </a>

            <!-- Form Tombol Logout -->
            <form action="<?= BASE_URL ?>/actions/logout.php"
                  method="POST">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= e(csrf_token()); ?>">

                <button type="submit" class="btn btn-danger btn-small">
                    Logout
                </button>

            </form>

        </div>

    </nav>


    <main class="container">

        <h2 class="page-title">Edit Komentar</h2>

        <!-- Tautan Navigasi untuk Kembali ke Halaman Detail Postingan -->
        <p>
            <a href="<?= BASE_URL ?>/pages/detail-post.php?id=<?= (int) $comment['post_id']; ?>"
               class="btn btn-secondary btn-small">
                &larr; Kembali ke Postingan
            </a>
        </p>


        <!-- Blok Notifikasi Pesan Flash (Sukses atau Error) -->
        <?php if ($flash): ?>

            <div class="alert <?= $flash['type'] === 'success'
                ? 'alert-success'
                : 'alert-error' ?>">

                <?= e($flash['message']); ?>

            </div>

        <?php endif; ?>


        <!-- Kartu Formulir Pengeditan Komentar -->
        <section class="card">

            <form
                action="<?= BASE_URL ?>/actions/update-comment.php"
                method="POST"
                enctype="multipart/form-data"
            >

                <!-- Token CSRF untuk memvalidasi keamanan request formulir -->
                <input type="hidden"
                       name="csrf_token"
                       value="<?= e(csrf_token()); ?>">

                <!-- ID komentar yang akan diperbarui -->
                <input type="hidden"
                       name="comment_id"
                       value="<?= (int) $comment['id']; ?>">


                <!-- Input Isi Teks Komentar -->
                <div class="form-group">

                    <label for="content">
                        Isi Komentar
                    </label>

                    <textarea
                        name="content"
                        id="content"
                        class="form-control"
                        rows="4"
                        maxlength="250"
                        placeholder="Tulis komentar..."
                    ><?= e($comment['content']); ?></textarea>

                    <small>
                        Maksimal 250 karakter.
                    </small>

                </div>


                <!-- Bagian Gambar Lama & Opsi Menghapusnya -->
                <?php if (!empty($comment['image'])): ?>

                    <div class="form-group">

                        <label>Gambar Saat Ini</label>

                        <div>
                            <img
                                src="<?= BASE_URL . '/' . e($comment['image']); ?>"
                                alt="Gambar komentar"
                                style="max-width:200px;border-radius:10px;"
                            >
                        </div>

                        <!-- Checkbox untuk menandai penghapusan gambar lama -->
                        <label style="margin-top:8px; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                            <input
                                type="checkbox"
                                name="remove_image"
                                value="1"
                            >
                            Hapus gambar saat ini
                        </label>

                    </div>

                <?php endif; ?>


                <!-- Input Unggah Gambar Baru / Pengganti -->
                <div class="form-group">

                    <label for="image">
                        <?= !empty($comment['image'])
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


                <!-- Bagian Berkas Dokumen Lama & Opsi Menghapusnya -->
                <?php if (!empty($comment['file'])): ?>

                    <div class="form-group">

                        <label>File Saat Ini</label>

                        <p>
                            <a
                                href="<?= BASE_URL . '/' . e($comment['file']); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="file-link"
                            >
                                📎 <?= e($comment['file_name'] ?? 'Lihat file'); ?>
                            </a>
                        </p>

                        <!-- Checkbox untuk menandai penghapusan file lama -->
                        <label style="margin-top:8px; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                            <input
                                type="checkbox"
                                name="remove_file"
                                value="1"
                            >
                            Hapus file saat ini
                        </label>

                    </div>

                <?php endif; ?>


                <!-- Input Unggah Berkas Dokumen Baru / Pengganti -->
                <div class="form-group">

                    <label for="file">
                        <?= !empty($comment['file'])
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


                <!-- Tombol Submit untuk Menyimpan Pembaruan Komentar -->
                <button type="submit" class="btn btn-primary" style="margin-top:10px;">
                    Simpan Perubahan
                </button>

            </form>

        </section>

    </main>

</body>
</html>
