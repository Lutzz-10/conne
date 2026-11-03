<?php

/**
 * =====================================================
 * EDIT COMMENT - CONNE
 * Menampilkan form untuk mengedit komentar.
 * =====================================================
 */

require_once __DIR__ . '/../helpers/auth.php';

require_login();

$currentUser = current_user_id();

// Mengambil ID komentar.
$commentId = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$commentId || $commentId < 1) {
    set_flash('error', 'Komentar tidak valid.');
    redirect('/pages/home.php');
}

// Mengambil komentar milik pengguna yang sedang login.
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
    ':id' => $commentId,
    ':user_id' => $currentUser
]);

$comment = $query->fetch();

// Memastikan komentar ditemukan dan dimiliki pengguna.
if (!$comment) {
    set_flash('error', 'Komentar tidak ditemukan atau bukan milik kamu.');
    redirect('/pages/home.php');
}

$flash = get_flash();

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Komentar - CONNE</title>

    <link rel="stylesheet"
          href="<?= BASE_URL ?>/assets/css/style.css">
</head>

<body>

    <!-- NAVBAR -->

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

        <p>
            <a href="<?= BASE_URL ?>/pages/detail-post.php?id=<?= (int) $comment['post_id']; ?>"
               class="btn btn-secondary btn-small">
                &larr; Kembali ke Postingan
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


        <!-- FORM EDIT KOMENTAR -->

        <section class="card">

            <form
                action="<?= BASE_URL ?>/actions/update-comment.php"
                method="POST"
                enctype="multipart/form-data"
            >

                <input type="hidden"
                       name="csrf_token"
                       value="<?= e(csrf_token()); ?>">

                <input type="hidden"
                       name="comment_id"
                       value="<?= (int) $comment['id']; ?>">


                <!-- ISI KOMENTAR -->

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


                <!-- GAMBAR LAMA -->

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

                        <label>
                            <input
                                type="checkbox"
                                name="remove_image"
                                value="1"
                            >
                            Hapus gambar saat ini
                        </label>

                    </div>

                <?php endif; ?>


                <!-- UPLOAD GAMBAR BARU -->

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
                        accept=".jpg,.jpeg,.png,.gif,.webp"
                    >

                </div>


                <!-- FILE LAMA -->

                <?php if (!empty($comment['file'])): ?>

                    <div class="form-group">

                        <label>File Saat Ini</label>

                        <p>
                            <a
                                href="<?= BASE_URL . '/' . e($comment['file']); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                📎 <?= e($comment['file_name'] ?? 'Lihat file'); ?>
                            </a>
                        </p>

                        <label>
                            <input
                                type="checkbox"
                                name="remove_file"
                                value="1"
                            >
                            Hapus file saat ini
                        </label>

                    </div>

                <?php endif; ?>


                <!-- UPLOAD FILE BARU -->

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
                        accept=".pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip"
                    >

                </div>


                <!-- TOMBOL -->

                <button type="submit" class="btn btn-primary">
                    Simpan Perubahan
                </button>

            </form>

        </section>

    </main>

</body>
</html>