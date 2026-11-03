<?php

/**
 * =====================================================
 * REGISTER PROCESS - CONNE
 * =====================================================
 * Memproses pendaftaran akun pengguna baru.
 */

// Memanggil konfigurasi dan helper.
require_once __DIR__ . '/../helpers/auth.php';

// Pengguna yang sudah login tidak boleh mendaftar lagi.
if (is_logged_in()) {
    redirect('/pages/home.php');
}

// Proses hanya boleh melalui metode POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/login.php?tab=register');
}

// Memeriksa token CSRF.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Permintaan tidak valid atau token sesi telah kedaluwarsa.');
    redirect('/pages/login.php?tab=register');
}

// Mengambil data input dari form pendaftaran.
$username        = trim($_POST['username'] ?? '');
$email           = trim($_POST['email'] ?? '');
$password        = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Memeriksa kolom wajib.
if ($username === '' || $email === '' || $password === '' || $confirmPassword === '') {
    set_flash('error', 'Semua kolom formulir pendaftaran wajib diisi.');
    redirect('/pages/login.php?tab=register');
}

// Memeriksa format username: 3-50 karakter alfanumerik atau underscore.
if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
    set_flash('error', 'Username harus 3-50 karakter dan hanya boleh berisi huruf, angka, serta garis bawah (_).');
    redirect('/pages/login.php?tab=register');
}

// Memeriksa format alamat email.
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    set_flash('error', 'Format alamat email tidak valid.');
    redirect('/pages/login.php?tab=register');
}

// Memeriksa panjang password minimal 6 karakter.
$passwordLength = function_exists('mb_strlen')
    ? mb_strlen($password, 'UTF-8')
    : strlen($password);

if ($passwordLength < 6) {
    set_flash('error', 'Password minimal harus 6 karakter.');
    redirect('/pages/login.php?tab=register');
}

// Memeriksa kesesuaian konfirmasi password.
if ($password !== $confirmPassword) {
    set_flash('error', 'Konfirmasi password tidak cocok.');
    redirect('/pages/login.php?tab=register');
}

try {

    // Memeriksa apakah username atau email sudah digunakan.
    $checkQuery = $pdo->prepare(
        "SELECT id, username, email
         FROM users
         WHERE username = :username OR email = :email
         LIMIT 1"
    );

    $checkQuery->execute([
        ':username' => $username,
        ':email'    => $email
    ]);

    $existingUser = $checkQuery->fetch();

    if ($existingUser) {

        if (strcasecmp($existingUser['username'], $username) === 0) {
            set_flash('error', 'Username "' . $username . '" sudah digunakan. Silakan pilih username lain.');
        } else {
            set_flash('error', 'Alamat email "' . $email . '" sudah terdaftar. Silakan login atau gunakan email lain.');
        }

        redirect('/pages/login.php?tab=register');

    }

    // Hash password menggunakan algoritma default yang aman (bcrypt/argon2).
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Menyimpan pengguna baru ke dalam database.
    $insertQuery = $pdo->prepare(
        "INSERT INTO users (username, email, password)
         VALUES (:username, :email, :password)"
    );

    $insertQuery->execute([
        ':username' => $username,
        ':email'    => $email,
        ':password' => $hashedPassword
    ]);

    // Berikan pesan berhasil dan arahkan ke form login.
    set_flash('success', 'Pendaftaran akun berhasil! Silakan login dengan akun baru Anda.');

    redirect('/pages/login.php');

} catch (PDOException $e) {

    // Mencatat detail error ke log server.
    error_log('Register error: ' . $e->getMessage());

    set_flash('error', 'Terjadi kesalahan pada sistem saat memproses pendaftaran. Silakan coba lagi.');

    redirect('/pages/login.php?tab=register');

}

