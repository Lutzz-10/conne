<?php

require_once __DIR__ . '/../helpers/auth.php';

require_login();

$currentUser = current_user_id();

$postId = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$postId || $postId < 1) {
    set_flash('error', 'Postingan tidak valid.');
    redirect('/pages/home.php');
}

// Ambil postingan milik pengguna yang sedang login.
$query = $pdo->prepare("
    SELECT *
    FROM posts
    WHERE id = :id AND user_id = :user_id
    LIMIT 1
");

$query->execute([
    ':id' => $postId,
    ':user_id' => $currentUser
]);

$post = $query->fetch();

if (!$post) {
    set_flash('error', 'Postingan tidak ditemukan atau bukan milik kamu.');
    redirect('/pages/home.php');
}

$flash = get_flash();

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Postingan - CONNE</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<nav class="navbar">
    <a href="<?= BASE_URL ?>/pages/home.php" class="logo">CONNE</a>

    <div class="nav-menu">
        <a href="<?= BASE_URL ?>/pages/home.php">Home</a>
        <a href="<?= BASE_URL ?>/pages/profile.php">Profil</a>
    </div>
</nav>

<main class="container">

    <h2 class="page-title">Edit Postingan</h2>

    <p>
        <a href="<?= BASE_URL ?>/pages/home.php"
           class="btn btn-secondary btn-small">
            &larr; Kembali
        </a>
    </p>

    <?php if ($flash): ?>
        <div class="alert <?= $flash['type'] === 'success'
            ? 'alert-success'
            : 'alert-error' ?>">
            <?= e($flash['message']); ?>
        </div>
    <?php endif; ?>

    <section class="card">

        <form
            action="<?= BASE_URL ?>/actions/update-post.php"
            method="POST"
            enctype="multipart/form-data"
        >

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e(csrf_token()); ?>">

            <input type="hidden"
                   name="post_id"
                   value="<?= (int) $post['id']; ?>">

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

            <?php if (!empty($post['image'])): ?>

                <div class="form-group">
                    <label>Gambar Saat Ini</label>

                    <img
                        src="<?= BASE_URL . '/' . e($post['image']); ?>"
                        alt="Gambar postingan"
                        class="post-image"
                    >

                    <label>
                        <input type="checkbox" name="remove_image" value="1">
                        Hapus gambar saat ini
                    </label>
                </div>

            <?php endif; ?>

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
                    accept=".jpg,.jpeg,.png,.gif,.webp"
                >
            </div>

            <?php if (!empty($post['file'])): ?>

                <div class="form-group">
                    <label>File Saat Ini</label>

                    <p>
                        <a
                            href="<?= BASE_URL . '/' . e($post['file']); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            📎 <?= e($post['file_name'] ?? 'Lihat file'); ?>
                        </a>
                    </p>

                    <label>
                        <input type="checkbox" name="remove_file" value="1">
                        Hapus file saat ini
                    </label>
                </div>

            <?php endif; ?>

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
                    accept=".pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip"
                >
            </div>

            <button type="submit" class="btn btn-primary">
                Simpan Perubahan
            </button>

        </form>

    </section>

</main>

</body>
</html>