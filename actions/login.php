<?php

/**
 * =====================================================
 * LOGIN PROCESS - CONNE
 * =====================================================
 * Memproses autentikasi pengguna menggunakan email
 * dan password yang dikirim dari halaman login.
 */

// Memanggil konfigurasi dan helper.
require_once __DIR__ . '/../helpers/auth.php';

// Login hanya boleh diproses menggunakan POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    redirect('/pages/login.php');

}

// Memeriksa token CSRF.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

    set_flash('error', 'Permintaan tidak valid.');

    redirect('/pages/login.php');

}

// Mengambil data dari form.
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Memastikan input tidak kosong.
if ($email === '' || $password === '') {

    set_flash('error', 'Email dan password wajib diisi.');

    redirect('/pages/login.php');

}

// Memastikan format email benar.
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    set_flash('error', 'Format email tidak valid.');

    redirect('/pages/login.php');

}

try {

    // Mengambil pengguna berdasarkan email.
    // Prepared statement mencegah SQL Injection.
    $query = $pdo->prepare(
        "SELECT id, username, email, password
         FROM users
         WHERE email = :email
         LIMIT 1"
    );

    $query->execute([
        ':email' => $email
    ]);

    $user = $query->fetch();

    // Memeriksa password dengan hash di database.
    if (!$user || !password_verify($password, $user['password'])) {

        set_flash('error', 'Email atau password salah.');

        redirect('/pages/login.php');

    }

    // Mengganti ID session untuk mencegah session fixation.
    session_regenerate_id(true);

    // Menyimpan informasi pengguna ke session.
    $_SESSION['user_id'] = (int) $user['id'];

    $_SESSION['username'] = $user['username'];

    $_SESSION['email'] = $user['email'];

    // Membuat token CSRF baru setelah login.
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // Memberikan pesan berhasil.
    set_flash('success', 'Login berhasil. Selamat datang!');

    // Mengarahkan pengguna ke halaman utama.
    redirect('/pages/home.php');

} catch (PDOException $e) {

    // Mencatat detail error ke log server.
    error_log('Login error: ' . $e->getMessage());

    set_flash('error', 'Terjadi kesalahan. Silakan coba lagi.');

    redirect('/pages/login.php');

}