<?php

/**
 * =====================================================
 * DETAIL POST PAGE - CONNE
 * =====================================================
 * Menampilkan satu postingan secara utuh beserta seluruh
 * komentar yang terhubung. Menyediakan fitur:
 * 1. Filter komentar berdasarkan tagar (#hashtag).
 * 2. Form penambahan komentar baru (teks + foto/file).
 * 3. Aksi edit dan hapus komentar bagi pemiliknya.
 */

// Memanggil modul autentikasi dan fungsi bantuan.
require_once __DIR__ . '/../helpers/auth.php';

// Memastikan hanya pengguna yang telah login yang dapat melihat halaman ini.
require_login();

// Mengambil ID akun pengguna yang sedang login.
$currentUser = current_user_id();

// -----------------------------------------------------
// 1. MENGAMBIL DAN MEMVALIDASI ID POSTINGAN
// -----------------------------------------------------

// Memvalidasi ID postingan dari parameter GET (?id=...).
$postId = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT
);

// Jika ID tidak valid atau kosong, redirect ke beranda dengan pesan error.
if (!$postId || $postId < 1) {
    set_flash('error', 'Postingan tidak valid.');
    redirect('/pages/home.php');
}

// -----------------------------------------------------
// 2. FILTER TAGAR / HASHTAG PADA KOMENTAR
// -----------------------------------------------------

// Mengambil kata kunci hashtag dari parameter URL (?hashtag=...).
$hashtag = trim($_GET['hashtag'] ?? '');

// Memvalidasi format hashtag menggunakan regex Unicode (wajib diawali tanda #).
if (
    $hashtag !== '' &&
    !preg_match('/^#[\p{L}\p{N}_]+$/u', $hashtag)
) {
    set_flash('error', 'Format hashtag tidak valid.');
    redirect('/pages/detail-post.php?id=' . $postId);
}

// -----------------------------------------------------
// 3. FUNGSI BANTUAN TAMPILAN (AVATAR & TANGGAL)
// -----------------------------------------------------

/**
 * Menampilkan foto profil atau inisial huruf pertama username jika foto belum ada.
 *
 * @param string|null $photo Lokasi berkas foto profil.
 * @param string $username Nama pengguna untuk membuat inisial.
 * @param bool $small Apakah ukuran avatar diperkecil (misal untuk komentar).
 * @return void
 */
function show_detail_avatar($photo, $username, $small = false)
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
 * Mengubah format timestamp database menjadi format tanggal yang mudah dibaca.
 *
 * @param string $date String tanggal dari database.
 * @return string Tanggal terformat (contoh: 02 Nov 2026, 20:30).
 */
function detail_display_date($date)
{
    return date('d M Y, H:i', strtotime($date));
}

// -----------------------------------------------------
// 4. MENGAMBIL DATA POSTINGAN DARI DATABASE
// -----------------------------------------------------

try {

    // Query untuk mengambil detail postingan beserta username dan foto pengunggah.
    $postQuery = $pdo->prepare("
        SELECT
            p.*,
            u.username,
            u.profile_photo
        FROM posts p
        INNER JOIN users u ON p.user_id = u.id
        WHERE p.id = :id
        LIMIT 1
    ");

    $postQuery->execute([
        ':id' => $postId
    ]);

    $post = $postQuery->fetch();

    // Jika postingan tidak ditemukan di database.
    if (!$post) {
        set_flash('error', 'Postingan tidak ditemukan.');
        redirect('/pages/home.php');
    }


    // -------------------------------------------------
    // 5. MENGAMBIL DAFTAR KOMENTAR POSTINGAN
    // -------------------------------------------------

    // Query dasar untuk mengambil komentar berelasi dengan post_id.
    $sql = "
        SELECT
            c.*,
            u.username,
            u.profile_photo
        FROM comments c
        INNER JOIN users u ON c.user_id = u.id
        WHERE c.post_id = :post_id
    ";

    $params = [
        ':post_id' => $postId
    ];

    // Jika filter hashtag diisi, saring hanya komentar yang memuat teks hashtag.
    if ($hashtag !== '') {
        $sql .= " AND c.content LIKE :hashtag ";
        $params[':hashtag'] = '%' . $hashtag . '%';
    }

    // Mengurutkan komentar dari yang terbaru.
    $sql .= " ORDER BY c.created_at DESC, c.id DESC ";

    $commentQuery = $pdo->prepare($sql);
    $commentQuery->execute($params);
    $comments = $commentQuery->fetchAll();

} catch (PDOException $e) {

    // Mencatat detail error ke log server dan mengalihkan ke halaman beranda.
    error_log('Detail post error: ' . $e->getMessage());
    set_flash('error', 'Gagal mengambil detail postingan.');
    redirect('/pages/home.php');

}

// Mengambil pesan notifikasi flash jika tersedia.
$flash = get_flash();

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Detail Postingan - CONNE</title>

    <!-- Memuat berkas CSS utama -->
    <link rel="stylesheet"
          href="<?= BASE_URL ?>/assets/css/style.css">

</head>

<body>

<!-- Komponen Navigasi Atas -->
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>


<main class="container">

    <h2 class="page-title">Detail Postingan</h2>

    <!-- Tombol Kembali ke Halaman Utama -->
    <p>
        <a href="<?= BASE_URL ?>/pages/home.php"
           class="btn btn-secondary btn-small">
            &larr; Kembali ke Home
        </a>
    </p>


    <!-- Notifikasi Flash Message (Sukses atau Error) -->
    <?php if ($flash): ?>

        <div class="alert <?= $flash['type'] === 'success'
            ? 'alert-success'
            : 'alert-error' ?>">

            <?= e($flash['message']); ?>

        </div>

    <?php endif; ?>


    <!-- ================= KARTU POSTINGAN UTAMA ================= -->
    <article class="post-card">

        <!-- Header Postingan: Avatar, Username, dan Tanggal -->
        <div class="post-header">

            <?php
            show_detail_avatar(
                $post['profile_photo'],
                $post['username']
            );
            ?>

            <div class="post-user">

                <strong>
                    <?= e($post['username']); ?>
                </strong>

                <div class="post-meta">
                    <?= e(detail_display_date($post['created_at'])); ?>
                </div>

            </div>

        </div>


        <!-- Isi Teks Postingan -->
        <?php if ($post['content'] !== ''): ?>

            <div class="post-content">
                <?= nl2br(e($post['content'])); ?>
            </div>

        <?php endif; ?>


        <!-- Gambar Postingan Jika Ada -->
        <?php if (!empty($post['image'])): ?>

            <img
                src="<?= BASE_URL . '/' . e($post['image']); ?>"
                class="post-image"
                alt="Gambar postingan"
            >

        <?php endif; ?>


        <!-- Dokumen Lampiran Postingan Jika Ada -->
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

    </article>


    <!-- ================= DAFTAR SELURUH KOMENTAR ================= -->
    <section class="card comment-section">

        <div class="comment-title">
            Komentar (<?= count($comments); ?>)
        </div>

        <!-- Formulir Filter Tagar / Hashtag Pada Komentar -->
        <form method="GET"
              action="<?= BASE_URL ?>/pages/detail-post.php"
              class="hashtag-filter">

            <input
                type="hidden"
                name="id"
                value="<?= (int) $post['id']; ?>"
            >

            <input
                type="text"
                name="hashtag"
                value="<?= e($hashtag); ?>"
                placeholder="Cari komentar berdasarkan hashtag..."
                maxlength="50"
            >

            <button type="submit" class="btn btn-primary btn-small">
                Cari
            </button>

            <!-- Tombol reset filter tagar jika filter sedang aktif -->
            <?php if ($hashtag !== ''): ?>

                <a
                    href="<?= BASE_URL ?>/pages/detail-post.php?id=<?= (int) $post['id']; ?>"
                    class="btn btn-secondary btn-small"
                >
                    Reset
                </a>

            <?php endif; ?>

        </form>


        <!-- Kondisi Jika Belum Ada Komentar -->
        <?php if (empty($comments)): ?>

            <p style="font-size:13px;color:#94a3b8; margin-top:10px;">

                <?php if ($hashtag !== ''): ?>

                    Tidak ada komentar dengan hashtag
                    <strong><?= e($hashtag); ?></strong>.

                <?php else: ?>

                    Belum ada komentar. Jadilah yang pertama berkomentar!

                <?php endif; ?>

            </p>

        <?php else: ?>

            <!-- Looping Setiap Komentar -->
            <?php foreach ($comments as $comment): ?>

                <div class="comment-item">

                    <!-- Avatar Pengirim Komentar -->
                    <?php
                    show_detail_avatar(
                        $comment['profile_photo'],
                        $comment['username'],
                        true
                    );
                    ?>

                    <div style="flex:1;min-width:0;">

                        <!-- Gelembung Konten Komentar -->
                        <div class="comment-bubble">

                            <strong>
                                <?= e($comment['username']); ?>
                            </strong>

                            <!-- Isi Teks Komentar -->
                            <?php if ($comment['content'] !== ''): ?>

                                <div class="comment-content">
                                    <?= nl2br(e($comment['content'])); ?>
                                </div>

                            <?php endif; ?>

                            <!-- Gambar Lampiran Komentar Jika Ada -->
                            <?php if (!empty($comment['image'])): ?>

                                <img
                                    src="<?= BASE_URL . '/' . e($comment['image']); ?>"
                                    class="post-image"
                                    alt="Gambar komentar"
                                >

                            <?php endif; ?>

                            <!-- Dokumen Lampiran Komentar Jika Ada -->
                            <?php if (!empty($comment['file'])): ?>

                                <a
                                    href="<?= BASE_URL . '/' . e($comment['file']); ?>"
                                    class="file-link"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    📎 <?= e($comment['file_name'] ?? 'Lihat file'); ?>
                                </a>

                            <?php endif; ?>

                        </div>

                        <!-- Waktu Pengiriman Komentar -->
                        <div class="comment-time">
                            <?= e(detail_display_date($comment['created_at'])); ?>
                        </div>

                        <!-- Opsi Edit & Hapus Hanya Untuk Pemilik Komentar -->
                        <?php if ((int) $comment['user_id'] === (int) $currentUser): ?>

                            <div class="comment-actions">

                                <!-- Tombol Menuju Halaman Edit Komentar -->
                                <a
                                    href="<?= BASE_URL ?>/pages/edit-comment.php?id=<?= (int) $comment['id']; ?>"
                                    class="btn btn-secondary btn-small"
                                >
                                    Edit
                                </a>

                                <!-- Formulir Hapus Komentar -->
                                <form
                                    action="<?= BASE_URL ?>/actions/delete-comment.php"
                                    method="POST"
                                    onsubmit="return confirm('Yakin ingin menghapus komentar ini?');"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e(csrf_token()); ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="comment_id"
                                        value="<?= (int) $comment['id']; ?>"
                                    >

                                    <button type="submit" class="btn btn-danger btn-small">
                                        Hapus
                                    </button>

                                </form>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>


    <!-- ================= FORM PENULISAN KOMENTAR BARU ================= -->
    <section class="card">

        <div class="comment-title">
            Tulis Komentar
        </div>

        <form
            action="<?= BASE_URL ?>/actions/store-comment.php"
            method="POST"
            enctype="multipart/form-data"
            class="comment-form"
        >

            <!-- Token Proteksi CSRF -->
            <input type="hidden"
                   name="csrf_token"
                   value="<?= e(csrf_token()); ?>">

            <!-- ID Postingan Tujuan -->
            <input type="hidden"
                   name="post_id"
                   value="<?= (int) $post['id']; ?>">

            <!-- Avatar Pengguna Aktif -->
            <?php
            show_detail_avatar(
                null,
                $_SESSION['username'] ?? 'User',
                true
            );
            ?>

            <div class="comment-main">

                <!-- Textarea Isi Komentar -->
                <textarea
                    name="content"
                    class="comment-input"
                    rows="2"
                    maxlength="250"
                    placeholder="Tulis komentar..."
                ></textarea>

                <div class="comment-tools">

                    <!-- Opsi Lampiran Media / File -->
                    <div class="upload-actions">

                        <!-- Tombol Unggah Foto Komentar -->
                        <label
                            for="comment-image"
                            class="upload-label"
                        >
                            ▧ Foto
                        </label>

                        <input
                            type="file"
                            id="comment-image"
                            name="image"
                            class="file-input"
                            accept=".jpg,.jpeg,.png,.gif,.webp"
                            onchange="showFileName(this)"
                        >

                        <!-- Tombol Unggah Dokumen Komentar -->
                        <label
                            for="comment-file"
                            class="upload-label"
                        >
                            📎 File
                        </label>

                        <input
                            type="file"
                            id="comment-file"
                            name="file"
                            class="file-input"
                            accept=".pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip"
                            onchange="showFileName(this)"
                        >

                    </div>

                    <!-- Tombol Kirim Komentar -->
                    <button type="submit"
                            class="btn btn-primary btn-small">
                        Kirim
                    </button>

                </div>

                <!-- Area Penampil Nama File Terpilih -->
                <div class="file-name"></div>

            </div>

        </form>

    </section>

</main>


<!-- ================= JAVASCRIPT BANTUAN ================= -->
<script>
/**
 * Menampilkan nama berkas yang dipilih pada elemen pratinjau form.
 *
 * @param {HTMLInputElement} input Elemen input file yang berubah.
 */
function showFileName(input) {
    const form = input.closest('form');
    const display = form.querySelector('.file-name');

    if (!display) {
        return;
    }

    const selectedFiles = [];

    // Mengumpulkan nama-nama file yang dipilih
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
