<?php

/**
 * =====================================================
 * AUTHENTICATION HELPER - CONNE
 * =====================================================
 * File ini mengatur pemeriksaan status login pengguna.
 */

// Memanggil konfigurasi database dan session.
require_once __DIR__ . '/../config/config.php';

// Memanggil fungsi helper umum.
require_once __DIR__ . '/functions.php';


// -----------------------------------------------------
// 1. MEMERIKSA STATUS LOGIN
// -----------------------------------------------------

/**
 * Memeriksa apakah pengguna sudah login.
 *
 * @return bool True jika sudah login.
 */
function is_logged_in()
{
    return isset($_SESSION['user_id']);
}


// -----------------------------------------------------
// 2. MENGAMBIL ID PENGGUNA YANG LOGIN
// -----------------------------------------------------

/**
 * Mengambil ID pengguna dari session.
 *
 * @return int|null ID pengguna atau null jika belum login.
 */
function current_user_id()
{
    if (!is_logged_in()) {
        return null;
    }

    return (int) $_SESSION['user_id'];
}


// -----------------------------------------------------
// 3. MEMBATASI HALAMAN UNTUK PENGGUNA LOGIN
// -----------------------------------------------------

/**
 * Mengarahkan pengguna ke halaman login jika
 * belum melakukan autentikasi.
 *
 * Fungsi ini digunakan pada halaman yang
 * hanya boleh diakses oleh pengguna login.
 *
 * @return void
 */
function require_login()
{
    if (!is_logged_in()) {

        set_flash(
            'error',
            'Silakan login terlebih dahulu.'
        );

        redirect('/pages/login.php');

    }
}


// -----------------------------------------------------
// 4. MEMBATASI HALAMAN UNTUK PENGGUNA YANG BELUM LOGIN
// -----------------------------------------------------

/**
 * Mencegah pengguna yang sudah login membuka
 * halaman login kembali.
 *
 * @return void
 */
function require_guest()
{
    if (is_logged_in()) {

        redirect('/index.php');

    }
}