<?php

/**
 * =====================================================
 * MAIN ROUTER - CONNE
 * =====================================================
 * File utama yang mengarahkan pengguna ke halaman
 * berdasarkan status autentikasi.
 */

// Memanggil helper autentikasi.
require_once __DIR__ . '/helpers/auth.php';

// Jika pengguna sudah login, buka halaman home.
if (is_logged_in()) {

    redirect('/pages/home.php');

}

// Jika belum login, buka halaman login.
redirect('/pages/login.php');