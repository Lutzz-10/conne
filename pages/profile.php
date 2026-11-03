<?php

/**
 * =====================================================
 * PROFILE PAGE - CONNE
 * Menampilkan profil dan postingan milik pengguna.
 * =====================================================
 */

require_once __DIR__ . '/../helpers/auth.php';


require_login();

// -----------------------------------------------------
// 1. MENGAMBIL DATA PENGGUNA
// -----------------------------------------------------

$userId = current_user_id();

$userQuery = $pdo->prepare("
    SELECT
        id,
        username,
        email,
        bio,
        profile_photo
    FROM users
    WHERE id = :id
");

$userQuery->execute([
    ':id' => $userId
]);

$user = $userQuery->fetch();

if (!$user) {
    set_flash('error', 'Pengguna tidak ditemukan.');
    redirect('/pages/home.php');
}

// -----------------------------------------------------
// 2. MENGAMBIL POSTINGAN MILIK PENGGUNA
// -----------------------------------------------------

$postQuery = $pdo->prepare("
    SELECT
        p.*,
        (
            SELECT COUNT(*)
            FROM comments c
            WHERE c.post_id = p.id
        ) AS comment_count
    FROM posts p
    WHERE p.user_id = :user_id
    ORDER BY p.created_at DESC, p.id DESC
");

$postQuery->execute([
    ':user_id' => $userId
]);

$posts = $postQuery->fetchAll();

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Profil - CONNE</title>

    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

    <style>
        .profile-container {
            max-width: 700px;
            margin: 30px auto;
            padding: 20px;
        }

        .profile-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .profile-photo {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            background: #e5e7eb;
        }

        .profile-initial {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: #2563eb;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 35px;
            font-weight: bold;
        }

        .profile-info h2 {
            margin: 0 0 5px;
        }

        .profile-info p {
            margin: 5px 0;
            color: #666;
        }

        .profile-bio {
            border-top: 1px solid #eee;
            padding-top: 15px;
            color: #444;
            line-height: 1.6;
        }

        .profile-post {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
        }

        .profile-post-content {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            margin-bottom: 15px;
        }

        .profile-post img {
            max-width: 100%;
            border-radius: 10px;
            margin-top: 10px;
        }

        .profile-post-footer {
            border-top: 1px solid #eee;
            padding-top: 12px;
            color: #777;
            font-size: 13px;
        }

        .empty-post {
            background: #fff;
            padding: 30px;
            text-align: center;
            border-radius: 12px;
            color: #777;
            border: 1px solid #e5e7eb;
        }
    </style>
</head>

<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="profile-container">
        <?php if ($flash = get_flash()): ?>

    <div class="alert alert-<?= e($flash['type']); ?>">
        <?= e($flash['message']); ?>
    </div>

<?php endif; ?>

        <!-- PROFIL PENGGUNA -->
         
        <section class="profile-card">

            <div class="profile-header">

                <?php if (!empty($user['profile_photo'])): ?>

                    <img
                        src="<?= BASE_URL . '/' . e($user['profile_photo']); ?>"
                        alt="Foto profil"
                        class="profile-photo"
                    >

                <?php else: ?>

                    <div class="profile-initial">
                        <?= strtoupper(substr($user['username'], 0, 1)); ?>
                    </div>

                <?php endif; ?>

                <div class="profile-info">

                    <h2><?= e($user['username']); ?></h2>

                    <p><?= e($user['email']); ?></p>

                </div>

            </div>

            <div class="profile-bio">

                <strong>Bio</strong>

                <p>
                    <?= !empty($user['bio'])
                        ? nl2br(e($user['bio']))
                        : 'Pengguna belum menambahkan bio.'; ?>
                </p>

            </div>

            <form
    action="<?= BASE_URL ?>/actions/update-profile-photo.php"
    method="POST"
    enctype="multipart/form-data"
    class="profile-photo-form"
>

    <input
        type="hidden"
        name="csrf_token"
        value="<?= e(csrf_token()); ?>"
    >

    <label for="profile_photo">
        Ganti Foto Profil
    </label>

    <input
        type="file"
        name="profile_photo"
        id="profile_photo"
        accept="image/jpeg,image/png,image/gif,image/webp"
        required
    >

    <button type="submit" class="btn btn-primary">
        Simpan Foto
    </button>

    <small>
        Format JPG, PNG, GIF, atau WEBP. Maksimal 5 MB.
    </small>

</form>

        </section>


        <!-- DAFTAR POSTINGAN -->
        <section>

            <h3>Postingan Saya</h3>

            <?php if (count($posts) > 0): ?>

                <?php foreach ($posts as $post): ?>

                    <article class="profile-post">

                        <div class="profile-post-content">
                            <?= nl2br(e($post['content'])); ?>
                        </div>

                        <?php if (!empty($post['image'])): ?>

                            <img
                                src="<?= BASE_URL . '/' . e($post['image']); ?>"
                                alt="Gambar postingan"
                            >

                        <?php endif; ?>

                        <?php if (!empty($post['file'])): ?>

                            <p>
                                <a
                                    href="<?= BASE_URL . '/' . e($post['file']); ?>"
                                    target="_blank"
                                >
                                    📎 <?= e($post['file_name']); ?>
                                </a>
                            </p>

                        <?php endif; ?>

                        <div class="profile-post-footer">

                            <?= (int) $post['comment_count']; ?> komentar

                            &nbsp;·&nbsp;

                            <?= date('d M Y, H:i', strtotime($post['created_at'])); ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty-post">
                    Kamu belum memiliki postingan.
                </div>

            <?php endif; ?>

        </section>

    </main>

</body>
</html>