<?php

/**
 * =====================================================
 * HOME PAGE - CONNE
 * Menampilkan timeline postingan.
 * =====================================================
 */

require_once __DIR__ . '/../helpers/auth.php';

require_login();

$currentUser = current_user_id();

// -----------------------------------------------------
// 1. FILTER HASHTAG
// -----------------------------------------------------

$hashtag = trim($_GET['hashtag'] ?? '');
$filterError = null;

if (
    $hashtag !== '' &&
    !preg_match('/^#[\p{L}\p{N}_]+$/u', $hashtag)
) {
    $filterError = 'Format hashtag tidak valid.';
    $hashtag = '';
}

// -----------------------------------------------------
// 2. MENGAMBIL POSTINGAN
// -----------------------------------------------------


    try {
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

    $sql .= " ORDER BY p.created_at DESC, p.id DESC";

    $postQuery = $pdo->prepare($sql);
    $postQuery->execute($params);
    $posts = $postQuery->fetchAll();

} catch (PDOException $e) {
    error_log('Home error: ' . $e->getMessage());

    $posts = [];
    $filterError = 'Gagal mengambil data postingan.';
}

// -----------------------------------------------------
// 3. PESAN FLASH
// -----------------------------------------------------

$flash = get_flash();

// -----------------------------------------------------
// 4. FUNGSI AVATAR
// -----------------------------------------------------

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

    <link rel="stylesheet"
          href="<?= BASE_URL ?>/assets/css/style.css">
</head>

<body>

<!-- NAVBAR -->

<?php require_once __DIR__ . '/../includes/navbar.php'; ?>


<main class="container">

    <h2 class="page-title">Beranda</h2>

    <!-- FLASH MESSAGE -->

    <?php if ($flash): ?>

        <div class="alert <?= $flash['type'] === 'success'
            ? 'alert-success'
            : 'alert-error' ?>">

            <?= e($flash['message']); ?>

        </div>

    <?php endif; ?>


    <?php if ($filterError): ?>

        <div class="alert alert-error">
            <?= e($filterError); ?>
        </div>

    <?php endif; ?>


    <!-- FORM BUAT POSTINGAN -->

    <section class="card">

        <form action="<?= BASE_URL ?>/actions/store-post.php"
              method="POST"
              enctype="multipart/form-data">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e(csrf_token()); ?>">

            <div class="composer">

                <?php
                show_avatar(
                    null,
                    $_SESSION['username'] ?? 'User'
                );
                ?>

                <div class="composer-main">

                    <textarea
                        name="content"
                        maxlength="250"
                        placeholder="Apa yang sedang terjadi?"
                    ></textarea>

                    <div class="composer-bottom">

                        <div class="upload-actions">

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

                        <button type="submit"
                                class="btn btn-primary">
                            Post
                        </button>

                    </div>

                    <div class="file-name"></div>

                </div>

            </div>

        </form>

    </section>


    <!-- FILTER HASHTAG -->

    <section class="card">

        <form action="<?= BASE_URL ?>/pages/home.php"
              method="GET"
              class="filter-form">

            <input
                type="text"
                name="hashtag"
                class="form-control"
                placeholder="Cari hashtag, contoh #RPL"
                value="<?= e($hashtag); ?>"
            >

            <button type="submit"
                    class="btn btn-primary">
                Cari
            </button>

            <a href="<?= BASE_URL ?>/pages/home.php"
               class="btn btn-secondary">
                Reset
            </a>

        </form>

    </section>


    <!-- TIMELINE POSTINGAN -->

    <h3 class="page-title">Postingan Terbaru</h3>

    <?php if (empty($posts)): ?>

        <div class="card" style="text-align:center;">

            <p style="color:#64748b;">
                Belum ada postingan.
            </p>

        </div>

    <?php endif; ?>


    <?php foreach ($posts as $post): ?>

        <article class="post-card">

            <!-- HEADER POST -->

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


            <!-- TOMBOL MENUJU DETAIL POSTINGAN -->

            <div class="post-footer">

    <a
    href="<?= BASE_URL ?>/pages/detail-post.php?id=<?= (int) $post['id']; ?>"
    class="comment-link"
>
    💬 Komentar (<?= (int) $post['comment_count']; ?>)
</a>

    <?php if ((int) $post['user_id'] === (int) $currentUser): ?>

        <div class="post-actions">

            <a
                href="<?= BASE_URL ?>/pages/edit-post.php?id=<?= (int) $post['id']; ?>"
                class="btn btn-secondary btn-small"
            >
                Edit
            </a>

            <form
                action="<?= BASE_URL ?>/actions/delete-post.php"
                method="POST"
                onsubmit="return confirm('Yakin ingin menghapus postingan ini?');"
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