<?php

/**
 * =====================================================
 * NAVBAR COMPONENT - CONNE
 * =====================================================
 * Komponen bilah navigasi atas (top navigation bar) yang
 * dimuat di seluruh halaman aplikasi setelah login.
 * Menyediakan:
 * 1. Logo brand yang mengarah ke beranda.
 * 2. Tautan cepat ke Home (linimasa) dan Profil saya.
 * 3. Indikator avatar kecil (foto profil atau inisial nama).
 * 4. Tombol Logout dengan keamanan CSRF token.
 */

// Memanggil modul pembantu autentikasi.
require_once __DIR__ . '/../helpers/auth.php';

// Menjamin navbar hanya dirender untuk pengguna yang memiliki sesi login aktif.
require_login();

// Mengambil data pengguna aktif (username dan foto profil) untuk ditampilkan pada navbar.
$navbarQuery = $pdo->prepare("
    SELECT username, profile_photo
    FROM users
    WHERE id = :id
    LIMIT 1
");

$navbarQuery->execute([
    ':id' => current_user_id()
]);

$navbarUser = $navbarQuery->fetch();

?>

<!-- Elemen Navigasi Atas Utama -->
<nav class="navbar">

    <!-- Logo Brand CONNE -->
    <a href="<?= BASE_URL ?>/pages/home.php" class="logo">
        CONNE
    </a>

    <!-- Menu Navigasi Pengguna -->
    <div class="nav-menu">

        <!-- Tautan ke Halaman Beranda -->
        <a href="<?= BASE_URL ?>/pages/home.php">
            Home
        </a>

        <!-- Ikon Profil Pengguna (Menampilkan Foto Asli atau Inisial Huruf) -->
        <a
            href="<?= BASE_URL ?>/pages/profile.php"
            class="navbar-profile-link"
            title="Lihat Profil Saya"
        >

            <?php if (!empty($navbarUser['profile_photo'])): ?>

                <!-- Foto profil bulat kecil -->
                <img
                    src="<?= BASE_URL . '/' . e($navbarUser['profile_photo']); ?>"
                    alt="Foto profil"
                    class="navbar-profile-photo"
                >

            <?php else: ?>

                <!-- Avatar inisial berlatar biru -->
                <span class="navbar-profile-initial">
                    <?= strtoupper(substr($navbarUser['username'] ?? 'U', 0, 1)); ?>
                </span>

            <?php endif; ?>

        </a>

        <!-- Formulir Tombol Logout (Menggunakan POST dan Proteksi CSRF) -->
        <form
            action="<?= BASE_URL ?>/actions/logout.php"
            method="POST"
        >

            <!-- Token CSRF mencegah serangan pemalsuan request logout -->
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
