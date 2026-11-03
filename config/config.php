<?php

/**
 * =====================================================
 * CONFIGURATION DATABASE - CONNE
 * =====================================================
 * File ini digunakan untuk:
 * 1. Mengatur konfigurasi database.
 * 2. Membuat koneksi PDO ke MySQL.
 * 3. Mengatur session aplikasi.
 */

// -----------------------------------------------------
// 1. KONFIGURASI DATABASE
// -----------------------------------------------------

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_conne');
define('DB_USER', 'root');
define('DB_PASS', '');

// Base URL aplikasi.
// Jika nama folder proyek bukan "conne", sesuaikan bagian ini.
define('BASE_URL', '/conne');

// Lokasi utama folder proyek.
define('BASE_PATH', dirname(__DIR__));


// -----------------------------------------------------
// 2. PENGATURAN TIMEZONE
// -----------------------------------------------------

date_default_timezone_set('Asia/Jakarta');


// -----------------------------------------------------
// 3. MEMBUAT KONEKSI DATABASE MENGGUNAKAN PDO
// -----------------------------------------------------

try {

    $dsn = "mysql:host=" . DB_HOST .
           ";dbname=" . DB_NAME .
           ";charset=utf8mb4";

    // Membuat objek PDO untuk menghubungkan PHP dengan MySQL.
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [

        // Menampilkan error database sebagai exception.
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

        // Hasil query dikembalikan sebagai associative array.
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        // Menggunakan prepared statement native MySQL.
        PDO::ATTR_EMULATE_PREPARES => false

    ]);

} catch (PDOException $e) {

    // Catat detail error ke log server, bukan ke halaman pengguna.
    error_log("Database connection error: " . $e->getMessage());

    // Tampilkan pesan umum agar detail database tidak bocor.
    exit("Koneksi database gagal. Silakan periksa konfigurasi aplikasi.");

}


// -----------------------------------------------------
// 4. MEMULAI SESSION
// -----------------------------------------------------

// Session digunakan untuk menyimpan status login pengguna.
if (session_status() === PHP_SESSION_NONE) {

    // Pengaturan keamanan cookie session.
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_URL . '/',
        'secure' => !empty($_SERVER['HTTPS'])
                    && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    // Memulai session.
    session_start();

}