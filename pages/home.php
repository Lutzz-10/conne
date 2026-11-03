<?php

/**
 * =====================================================
 * HOME PAGE (TIMELINE) - CONNE
 * =====================================================
 * Halaman utama aplikasi yang menampilkan:
 * 1. Formulir pembuatan postingan baru (teks, foto, berkas dokumen).
 * 2. Pencarian dan filter linimasa berdasarkan tagar (#hashtag).
 * 3. Daftar linimasa seluruh postingan yang diurutkan secara kronologis (terbaru).
 * 4. Navigasi menuju halaman komentar atau pengeditan/penghapusan status.
 */

// Memanggil modul autentikasi dan fungsi bantuan.
require_once __DIR__ . '/../helpers/auth.php';

// Memastikan hanya pengguna yang telah login yang dapat mengakses halaman beranda.
require_login();

// Mengambil ID pengguna yang sedang aktif dari session.
$currentUser = current_user_id();

// -----------------------------------------------------
// 1. FILTER TAGAR / HASHTAG
// -----------------------------------------------------

// Mengambil nilai filter tagar dari URL query string (?hashtag=...).
$hashtag = trim($_GET['hashtag'] ?? '');
$filterError = null;

// Memvalidasi format tagar menggunakan regex (harus diawali dengan tanda #).
if (
    $hashtag !== '' &&
    !preg_match('/^#[\p{L}\p{N}_]+$/u', $hashtag)
) {
    $filterError = 'Format hashtag tidak valid. Contoh yang benar: #diskusi, #koding.';
    $hashtag = '';
}

// -----------------------------------------------------
// 2. MENGAMBIL DAFTAR POSTINGAN DARI DATABASE
// -----------------------------------------------------

try {

    // Query utama mengambil seluruh data postingan, username, foto profil pembuat,
    // serta menghitung jumlah komentar masing-masing postingan via subquery.
    $sql = "
        SELECT
            p.*,
            u.username,
            u.profile_photo,
            (
                SELECT COUNT(*)
                FROM comments c
                WHERE c.post_id = p.id
            ) AS comment_count
        FROM posts p
        INNER JOIN users u ON p.user_id = u.id
    ";

    $params = [];

    // Jika filter hashtag diisi, cari postingan yang teksnya memuat hashtag tersebut
    // ATAU postingan yang komentar di dalamnya memuat hashtag bersangkutan.
    if ($hashtag !== '') {
        $sql .= "
            WHERE (
                p.content LIKE :post_tag
                OR EXISTS (
                    SELECT 1
                    FROM comments fc
                    WHERE fc.post_id = p.id
                    AND fc.content LIKE :comment_tag
                )
            )
        ";

        $params[':post_tag'] = '%' . $hashtag . '%';
        $params[':comment_tag'] = '%' . $hashtag . '%';
    }

    // Mengurutkan postingan dari yang paling baru diunggah.
    $sql .= " ORDER BY p.created_at DESC, p.id DESC";

    $postQuery = $pdo->prepare($sql);
    $postQuery->execute($params);
    $posts = $postQuery->fetchAll();

} catch (PDOException $e) {

    // Catat pesan error ke log server dan beri pesan ramah kepada pengguna.
    error_log('Home timeline error: ' . $e->getMessage());

    $posts = [];
    $filterError = 'Gagal memuat data linimasa postingan.';

}

// -----------------------------------------------------
// 3. PESAN FLASH DARI SESSION
// -----------------------------------------------------

// Mengambil pesan notifikasi sementara (flash message) dari sesi jika tersedia.
$flash = get_flash();

// -----------------------------------------------------
// 4. FUNGSI TAMPILAN AVATAR & TANGGAL
// -----------------------------------------------------

/**
 * Menampilkan avatar pengguna dalam bentuk gambar profil
 * atau inisial huruf pertama jika foto profil belum diunggah.
 *
 * @param string|null $photo Lokasi relatif berkas foto profil.
 * @param string $username Nama pengguna untuk membuat inisial.
 * @param bool $small Apakah menggunakan ukuran avatar kecil.
 * @return void
 */
function show_avatar($photo, $username, $small = false)
{
    $class = $small ? 'avatar avatar-small' : 'avatar';

    if (!empty($photo)) {
        echo '<img class="' . $class . '" src="' .
            BASE_URL . '/' . e($photo) .
            '" alt="Foto profil">';
    } else {
        $initial = strtoupper(
            function_exists('mb_substr')
                ? mb_substr($username, 0, 1, 'UTF-8')
                : substr($username, 0, 1)
        );

        echo '<div class="' . $class . '">';
        echo e($initial);
        echo '</div>';
    }
}

/**
 * Memformat string timestamp database ke format tanggal yang informatif.
 *
 * @param string $date String tanggal dan waktu dari database.
 * @return string Tanggal terformat (contoh: 02 Nov 2026, 20:30).
 */
function display_date($date)
{
    return date('d M Y, H:i', strtotime($date));
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Home - CONNE</title>

    <!-- Memuat berkas CSS utama aplikasi -->
    <link rel="stylesheet"
          href="<?= BASE_URL ?>/assets/css/style.css">
</head>

<body>

<!-- Komponen Navigasi Atas -->
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>


<main class="container">

    <h2 class="page-title">Beranda</h2>

    <!-- Menampilkan pesan notifikasi flash (sukses / error) -->
    <?php if ($flash): ?>

        <div class="alert <?= $flash['type'] === 'success'
            ? 'alert-success'
            : 'alert-error' ?>">

            <?= e($flash['message']); ?>

        </div>

    <?php endif; ?>


    <!-- Menampilkan pesan error filter jika ada -->
    <?php if ($filterError): ?>

        <div class="alert alert-error">
            <?= e($filterError); ?>
        </div>

    <?php endif; ?>


    <!-- ================= FORM PEMBUATAN POSTINGAN ================= -->
    <section class="card">

        <form action="<?= BASE_URL ?>/actions/store-post.php"
              method="POST"
              enctype="multipart/form-data">

            <!-- Token CSRF untuk melindungi pengiriman form -->
            <input type="hidden"
                   name="csrf_token"
                   value="<?= e(csrf_token()); ?>">

            <div class="composer">

                <!-- Avatar Akun Pengguna Aktif -->
                <?php
                show_avatar(
                    null,
                    $_SESSION['username'] ?? 'User'
                );
                ?>

                <div class="composer-main">

                    <!-- Textarea Konten Postingan (Maks. 250 Karakter) -->
                    <textarea
                        name="content"
                        maxlength="250"
                        placeholder="Apa yang sedang terjadi?"
                    ></textarea>

                    <div class="composer-bottom">

                        <!-- Tombol Unggah Lampiran (Foto dan Dokumen) -->
                        <div class="upload-actions">

                            <!-- Input Foto -->
                            <label for="post-image"
                                   class="upload-label">
                                <span>▧</span> Foto
                            </label>

                            <input
                                type="file"
                                id="post-image"
                                name="image"
                                class="file-input"
                                accept=".jpg,.jpeg,.png,.gif,.webp"
                                onchange="showFileName(this)"
                            >

                            <!-- Input Berkas Dokumen -->
                            <label for="post-file"
                                   class="upload-label">
                                <span>📎</span> File
                            </label>

                            <input
                                type="file"
                                id="post-file"
                                name="file"
                                class="file-input"
                                accept=".pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip"
                                onchange="showFileName(this)"
                            >

                        </div>

                        <!-- Tombol Submit Publikasi Postingan -->
                        <button type="submit"
                                class="btn btn-primary">
                            Post
                        </button>

                    </div>

                    <!-- Elemen Penampil Nama Berkas Terpilih -->
                    <div class="file-name"></div>

                </div>

            </div>

        </form>

    </section>


    <!-- ================= FORM FILTER TAGAR (HASHTAG) ================= -->
    <section class="card">

        <form action="<?= BASE_URL ?>/pages/home.php"
              method="GET"
              class="filter-form">

            <!-- Input Pencarian Tagar -->
            <input
                type="text"
                name="hashtag"
                class="form-control"
                placeholder="Cari hashtag, contoh: #diskusi"
                value="<?= e($hashtag); ?>"
            >

            <button type="submit"
                    class="btn btn-primary">
                Cari
            </button>

            <!-- Tombol Reset Filter Tagar -->
            <a href="<?= BASE_URL ?>/pages/home.php"
               class="btn btn-secondary">
                Reset
            </a>

        </form>

    </section>


    <!-- ================= LINIMASA POSTINGAN ================= -->
    <h3 class="page-title">Postingan Terbaru</h3>

    <!-- Pesan jika belum ada postingan -->
    <?php if (empty($posts)): ?>

        <div class="card" style="text-align:center;">

            <p style="color:#64748b;">
                Belum ada postingan yang ditemukan.
            </p>

        </div>

    <?php endif; ?>


    <!-- Looping Menampilkan Seluruh Postingan -->
    <?php foreach ($posts as $post): ?>

        <article class="post-card">

            <!-- Header Postingan: Avatar, Username, dan Waktu Publikasi -->
            <div class="post-header">

                <?php
                show_avatar(
                    $post['profile_photo'],
                    $post['username']
                );
                ?>

                <div class="post-user">

                    <strong>
                        <?= e($post['username']); ?>
                    </strong>

                    <div class="post-meta">
                        <?= e(display_date($post['created_at'])); ?>
                    </div>

                </div>

            </div>


            <!-- Isi Teks Postingan -->
            <?php if ($post['content'] !== ''): ?>

                <div class="post-content">
                    <?= nl2br(e($post['content'])); ?>
                </div>

            <?php endif; ?>


            <!-- Gambar Lampiran Jika Ada -->
            <?php if (!empty($post['image'])): ?>

                <img
                    src="<?= BASE_URL . '/' . e($post['image']); ?>"
                    class="post-image"
                    alt="Gambar postingan"
                >

            <?php endif; ?>


            <!-- Berkas Dokumen Lampiran Jika Ada -->
            <?php if (!empty($post['file'])): ?>

                <a
                    href="<?= BASE_URL . '/' . e($post['file']); ?>"
                    class="file-link"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    📎 <?= e($post['file_name'] ?? 'Lihat file'); ?>
                </a>

            <?php endif; ?>


            <!-- Footer Postingan: Tombol Komentar, Serta Opsi Edit/Hapus Pemilik -->
            <div class="post-footer">

                <!-- Tautan Menuju Halaman Detail Postingan & Komentar -->
                <a
                    href="<?= BASE_URL ?>/pages/detail-post.php?id=<?= (int) $post['id']; ?>"
                    class="comment-link"
                >
                    💬 Komentar (<?= (int) $post['comment_count']; ?>)
                </a>

                <!-- Opsi Khusus Pemilik Postingan -->
                <?php if ((int) $post['user_id'] === (int) $currentUser): ?>

                    <div class="post-actions">

                        <!-- Tombol Menuju Form Pengeditan Postingan -->
                        <a
                            href="<?= BASE_URL ?>/pages/edit-post.php?id=<?= (int) $post['id']; ?>"
                            class="btn btn-secondary btn-small"
                        >
                            Edit
                        </a>

                        <!-- Formulir Penghapusan Postingan -->
                        <form
                            action="<?= BASE_URL ?>/actions/delete-post.php"
                            method="POST"
                            onsubmit="return confirm('Yakin ingin menghapus postingan ini? Seluruh komentar di dalamnya juga akan terhapus.');"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e(csrf_token()); ?>"
                            >

                            <input
                                type="hidden"
                                name="post_id"
                                value="<?= (int) $post['id']; ?>"
                            >

                            <button type="submit" class="btn btn-danger btn-small">
                                Hapus
                            </button>

                        </form>

                    </div>

                <?php endif; ?>

            </div>

        </article>

    <?php endforeach; ?>

</main>


<!-- ================= JAVASCRIPT BANTUAN ================= -->
<script>
/**
 * Menampilkan nama berkas yang dipilih pada form unggah.
 *
 * @param {HTMLInputElement} input Elemen input file.
 */
function showFileName(input) {
    const form = input.closest('form');
    const display = form.querySelector('.file-name');

    if (!display) {
        return;
    }

    const selectedFiles = [];

    // Mengambil nama setiap berkas yang dipilih pada input file
    form.querySelectorAll('input[type="file"]').forEach(function(fileInput) {
        if (fileInput.files.length > 0) {
            selectedFiles.push(fileInput.files[0].name);
        }
    });

    display.textContent = selectedFiles.join(' | ');
}
</script>

</body>
</html>
