<?php

/**
 * =====================================================
 * LOGOUT PROCESS - CONNE
 * =====================================================
 * Mengakhiri session pengguna yang sedang login.
 */

// Memanggil helper autentikasi.
require_once __DIR__ . '/../helpers/auth.php';

// Logout hanya menerima metode POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    redirect('/index.php');

}

// Memeriksa token CSRF.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

    set_flash('error', 'Permintaan logout tidak valid.');

    redirect('/index.php');

}

// Menghapus seluruh data session.
$_SESSION = [];

// Menghapus cookie session jika digunakan.
if (ini_get('session.use_cookies')) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );

}

// Menghancurkan session.
session_destroy();

// Mengarahkan pengguna ke halaman login.
header('Location: ' . BASE_URL . '/pages/login.php');

exit;