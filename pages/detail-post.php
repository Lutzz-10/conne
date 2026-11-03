<?php

/**
 * =====================================================
 * DETAIL POST - CONNE
 * Menampilkan satu postingan beserta komentarnya.
 * =====================================================
 */

require_once __DIR__ . '/../helpers/auth.php';

require_login();
$currentUser = current_user_id();

// -----------------------------------------------------
// 1. MENGAMBIL ID POSTINGAN
// -----------------------------------------------------

$postId = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$postId || $postId < 1) {
    set_flash('error', 'Postingan tidak valid.');
    redirect('/pages/home.php');
}
// Mengambil filter hashtag dari URL.
$hashtag = trim($_GET['hashtag'] ?? '');

// Memvalidasi format hashtag.
if (
    $hashtag !== '' &&
    !preg_match('/^#[\p{L}\p{N}_]+$/u', $hashtag)
) {
    set_flash('error', 'Format hashtag tidak valid.');
    redirect('/pages/detail-post.php?id=' . $postId);
}

// -----------------------------------------------------
// 2. FUNGSI AVATAR DAN TANGGAL
// -----------------------------------------------------

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

function detail_display_date($date)
{
    return date('d M Y, H:i', strtotime($date));
}

// -----------------------------------------------------
// 3. MENGAMBIL DATA POSTINGAN
// -----------------------------------------------------

try {

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

    // Jika postingan tidak ditemukan.
    if (!$post) {
        set_flash('error', 'Postingan tidak ditemukan.');
        redirect('/pages/home.php');
    }


    // -------------------------------------------------
    // 4. MENGAMBIL KOMENTAR
    // -------------------------------------------------

    // Query dasar untuk mengambil komentar.
$sql = "
    SELECT
        c.*,
        u.username,
        u.profile_photo
    FROM comments c
    INNER JOIN users u ON c.user_id = u.id
    WHERE c.post_id = :post_id
";

// Parameter query.
$params = [
    ':post_id' => $postId
];

// Jika hashtag diisi, tambahkan filter.
if ($hashtag !== '') {

    $sql .= "
        AND c.content LIKE :hashtag
    ";

    $params[':hashtag'] = '%' . $hashtag . '%';
}

// Urutan komentar tetap seperti sebelumnya.
// Urutan terbaru akan kita benahi pada nomor 11.
$sql .= "
    ORDER BY c.created_at DESC, c.id DESC
";

$commentQuery = $pdo->prepare($sql);

$commentQuery->execute($params);

$comments = $commentQuery->fetchAll();

} catch (PDOException $e) {

    error_log('Detail post error: ' . $e->getMessage());

    set_flash('error', 'Gagal mengambil detail postingan.');

    redirect('/pages/home.php');
}


// -----------------------------------------------------
// 5. PESAN FLASH
// -----------------------------------------------------

$flash = get_flash();

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Detail Postingan - CONNE</title>

    <link rel="stylesheet"
          href="<?= BASE_URL ?>/assets/css/style.css">

</head>

<body>

<!-- NAVBAR -->

<?php require_once __DIR__ . '/../includes/navbar.php'; ?>


<main class="container">

    <h2 class="page-title">Detail Postingan</h2>

    <!-- TOMBOL KEMBALI -->

    <p>
        <a href="<?= BASE_URL ?>/pages/home.php"
           class="btn btn-secondary btn-small">
            &larr; Kembali ke Home
        </a>
    </p>


    <!-- FLASH MESSAGE -->

    <?php if ($flash): ?>

        <div class="alert <?= $flash['type'] === 'success'
            ? 'alert-success'
            : 'alert-error' ?>">

            <?= e($flash['message']); ?>

        </div>

    <?php endif; ?>


    <!-- POSTINGAN YANG DIPILIH -->

    <article class="post-card">

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


        <!-- ISI POST -->

        <?php if ($post['content'] !== ''): ?>

            <div class="post-content">
                <?= nl2br(e($post['content'])); ?>
            </div>

        <?php endif; ?>


        <!-- GAMBAR POST -->

        <?php if (!empty($post['image'])): ?>

            <img
                src="<?= BASE_URL . '/' . e($post['image']); ?>"
                class="post-image"
                alt="Gambar postingan"
            >

        <?php endif; ?>


        <!-- FILE POST -->

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


    <!-- DAFTAR KOMENTAR -->

    <section class="card comment-section">

        <div class="comment-title">
            Komentar (<?= count($comments); ?>)
        </div>

        <!-- FILTER HASHTAG KOMENTAR -->

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

    <?php if ($hashtag !== ''): ?>

        <a
            href="<?= BASE_URL ?>/pages/detail-post.php?id=<?= (int) $post['id']; ?>"
            class="btn btn-secondary btn-small"
        >
            Reset
        </a>

    <?php endif; ?>

</form>


        <?php if (empty($comments)): ?>

    <p style="font-size:13px;color:#94a3b8;">

        <?php if ($hashtag !== ''): ?>

            Tidak ada komentar dengan hashtag
            <strong><?= e($hashtag); ?></strong>.

        <?php else: ?>

            Belum ada komentar. Jadilah yang pertama berkomentar!

        <?php endif; ?>

    </p>

        <?php else: ?>

            <?php foreach ($comments as $comment): ?>

                <div class="comment-item">

                    <?php
                    show_detail_avatar(
                        $comment['profile_photo'],
                        $comment['username'],
                        true
                    );
                    ?>

                    <div style="flex:1;min-width:0;">

                        <div class="comment-bubble">

                            <strong>
                                <?= e($comment['username']); ?>
                            </strong>


                            <!-- ISI KOMENTAR -->

                            <?php if ($comment['content'] !== ''): ?>

                                <div class="comment-content">
                                    <?= nl2br(e($comment['content'])); ?>
                                </div>

                            <?php endif; ?>


                            <!-- GAMBAR KOMENTAR -->

                            <?php if (!empty($comment['image'])): ?>

                                <img
                                    src="<?= BASE_URL . '/' . e($comment['image']); ?>"
                                    class="post-image"
                                    alt="Gambar komentar"
                                >

                            <?php endif; ?>


                            <!-- FILE KOMENTAR -->

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

                        <div class="comment-time">
                            <?= e(detail_display_date($comment['created_at'])); ?>
                        </div>
                        <?php if ((int) $comment['user_id'] === (int) $currentUser): ?>

    <div class="comment-actions">

        <!-- TOMBOL EDIT -->
        <a
            href="<?= BASE_URL ?>/pages/edit-comment.php?id=<?= (int) $comment['id']; ?>"
            class="btn btn-secondary btn-small"
        >
            Edit
        </a>

        <!-- FORM HAPUS -->
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


    <!-- FORM MENAMBAHKAN KOMENTAR -->

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

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e(csrf_token()); ?>">

            <input type="hidden"
                   name="post_id"
                   value="<?= (int) $post['id']; ?>">

            <?php
            show_detail_avatar(
                null,
                $_SESSION['username'] ?? 'User',
                true
            );
            ?>

            <div class="comment-main">

                <textarea
                    name="content"
                    class="comment-input"
                    rows="2"
                    maxlength="250"
                    placeholder="Tulis komentar..."
                ></textarea>


                <div class="comment-tools">

                    <div class="upload-actions">

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


                    <button type="submit"
                            class="btn btn-primary btn-small">
                        Kirim
                    </button>

                </div>

                <div class="file-name"></div>

            </div>

        </form>

    </section>

</main>


<!-- JAVASCRIPT -->

<script>

function showFileName(input) {

    const form = input.closest('form');

    const display = form.querySelector('.file-name');

    if (!display) {
        return;
    }

    const selectedFiles = [];

    form.querySelectorAll('input[type="file"]').forEach(
        function(fileInput) {

            if (fileInput.files.length > 0) {
                selectedFiles.push(fileInput.files[0].name);
            }

        }
    );

    display.textContent = selectedFiles.join(' | ');
}

</script>

</body>
</html>