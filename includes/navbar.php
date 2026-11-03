<?php

/**
 * =====================================================
 * NAVBAR - CONNE
 * Navbar utama yang digunakan di seluruh halaman.
 * =====================================================
 */

require_once __DIR__ . '/../helpers/auth.php';

require_login();

// Mengambil data pengguna yang sedang login.
$navbarQuery = $pdo->prepare("
    SELECT username, profile_photo
    FROM users
    WHERE id = :id
");

$navbarQuery->execute([
    ':id' => current_user_id()
]);

$navbarUser = $navbarQuery->fetch();

?>

<nav class="navbar">

    <a href="<?= BASE_URL ?>/pages/home.php" class="logo">
        CONNE
    </a>

    <div class="nav-menu">

        <a href="<?= BASE_URL ?>/pages/home.php">
            Home
        </a>

        <!-- Ikon profil -->
        <a
            href="<?= BASE_URL ?>/pages/profile.php"
            class="navbar-profile-link"
            title="Profil"
        >

            <?php if (!empty($navbarUser['profile_photo'])): ?>

                <img
                    src="<?= BASE_URL . '/' . e($navbarUser['profile_photo']); ?>"
                    alt="Foto profil"
                    class="navbar-profile-photo"
                >

            <?php else: ?>

                <span class="navbar-profile-initial">
                    <?= strtoupper(substr($navbarUser['username'] ?? 'U', 0, 1)); ?>
                </span>

            <?php endif; ?>

        </a>

        <!-- Logout -->
        <form
            action="<?= BASE_URL ?>/actions/logout.php"
            method="POST"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()); ?>"
            >

            <button
                type="submit"
                class="btn btn-danger btn-small"
            >
                Logout
            </button>

        </form>

    </div>

</nav>