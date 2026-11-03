<?php

/**
 * =====================================================
 * REGISTER PAGE REDIRECT - CONNE
 * =====================================================
 * File ini mengarahkan pengguna ke tab daftar di halaman login.
 */

require_once __DIR__ . '/../helpers/auth.php';

redirect('/pages/login.php?tab=register');

