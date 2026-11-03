<?php

/**
 * =====================================================
 * EDIT PROFILE PAGE - CONNE
 * =====================================================
 * Halaman pengalihan ke profil pengguna.
 * Pengaturan profil dan pembaruan foto profil telah
 * diintegrasikan langsung pada halaman profile.php.
 */

// Memanggil modul autentikasi dan fungsi bantuan.
require_once __DIR__ . '/../helpers/auth.php';

// Memastikan hanya pengguna yang login yang dapat mengakses.
require_login();

// Mengarahkan ke halaman profil utama.
redirect('/pages/profile.php');

