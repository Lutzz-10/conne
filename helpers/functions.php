<?php

/**
 * =====================================================
 * HELPER FUNCTIONS - CONNE
 * =====================================================
 * Berisi fungsi-fungsi umum agar kode lebih modular
 * dan tidak perlu menulis kode yang sama berulang kali.
 */


// -----------------------------------------------------
// 1. FUNGSI ESCAPE HTML
// -----------------------------------------------------

/**
 * Mengamankan teks sebelum ditampilkan ke HTML.
 *
 * Tujuannya mencegah XSS (Cross-Site Scripting).
 *
 * @param string $value Teks yang akan ditampilkan.
 * @return string Teks yang sudah diamankan.
 */
function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


// -----------------------------------------------------
// 2. FUNGSI REDIRECT
// -----------------------------------------------------

/**
 * Mengarahkan pengguna ke halaman lain.
 *
 * @param string $path Alamat halaman tujuan.
 * @return void
 */
function redirect($path)
{
    header('Location: ' . BASE_URL . $path);
    exit;
}


// -----------------------------------------------------
// 3. FUNGSI MEMBUAT TOKEN CSRF
// -----------------------------------------------------

/**
 * Membuat token CSRF untuk melindungi form.
 *
 * Token disimpan di session agar bisa dibandingkan
 * dengan token yang dikirim melalui form.
 *
 * @return string Token CSRF.
 */
function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {

        $_SESSION['csrf_token'] = bin2hex(
            random_bytes(32)
        );

    }

    return $_SESSION['csrf_token'];
}


// -----------------------------------------------------
// 4. FUNGSI MEMERIKSA TOKEN CSRF
// -----------------------------------------------------

/**
 * Memeriksa apakah token dari form valid.
 *
 * @param string|null $token Token dari form.
 * @return bool True jika valid, false jika tidak.
 */
function verify_csrf_token($token)
{
    return isset($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}


// -----------------------------------------------------
// 5. FUNGSI MENYIMPAN PESAN SESSION
// -----------------------------------------------------

/**
 * Menyimpan pesan sementara menggunakan session.
 *
 * Contoh: pesan berhasil login atau gagal menyimpan data.
 *
 * @param string $type Jenis pesan.
 * @param string $message Isi pesan.
 * @return void
 */
function set_flash($type, $message)
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}


// -----------------------------------------------------
// 6. FUNGSI MENGAMBIL PESAN SESSION
// -----------------------------------------------------

/**
 * Mengambil pesan flash dan menghapusnya dari session.
 *
 * @return array|null Data pesan atau null jika kosong.
 */
function get_flash()
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];

    unset($_SESSION['flash']);

    return $flash;
}

// -----------------------------------------------------
// 7. FUNGSI UPLOAD FILE
// -----------------------------------------------------

/**
 * Mengunggah gambar atau dokumen ke folder aplikasi.
 *
 * File diperiksa berdasarkan MIME type, ukuran,
 * dan status upload sebelum disimpan.
 *
 * @param string $field Nama input file.
 * @param string $folder Folder tujuan upload.
 * @param array $allowedMimes Daftar MIME yang diizinkan.
 * @param int $maxSize Ukuran maksimum dalam byte.
 * @return string|null Lokasi file atau null jika kosong.
 * @throws RuntimeException Jika upload gagal.
 */
function upload_file(
    $field,
    $folder,
    $allowedMimes,
    $maxSize = 5242880
) {

    // Jika pengguna tidak memilih file.
    if (
        !isset($_FILES[$field]) ||
        $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    $file = $_FILES[$field];

    // Memeriksa error upload.
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload file gagal.');
    }

    // Memeriksa ukuran file maksimal.
    if ($file['size'] <= 0 || $file['size'] > $maxSize) {
        throw new RuntimeException('Ukuran file tidak valid atau terlalu besar.');
    }

    // Memastikan file benar-benar berasal dari proses upload.
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('File upload tidak valid.');
    }

    // Membaca MIME type berdasarkan isi file.
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $mime = $finfo->file($file['tmp_name']);

    // Memeriksa MIME type yang diperbolehkan.
    if (!isset($allowedMimes[$mime])) {
        throw new RuntimeException('Jenis file tidak diperbolehkan.');
    }

    // Menentukan lokasi folder penyimpanan.
    $uploadDirectory = BASE_PATH . '/' . $folder;

    // Membuat folder jika belum tersedia.
    if (!is_dir($uploadDirectory)) {

        if (!mkdir($uploadDirectory, 0755, true)) {
            throw new RuntimeException('Folder upload gagal dibuat.');
        }

    }

    // Membuat nama file acak agar tidak bertabrakan.
    $extension = $allowedMimes[$mime];

    $fileName = bin2hex(random_bytes(16)) . '.' . $extension;

    // Menentukan lokasi penyimpanan file.
    $destination = $uploadDirectory . '/' . $fileName;

    // Memindahkan file ke folder tujuan.
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('File gagal disimpan.');
    }

    // Mengembalikan lokasi relatif file.
    return $folder . '/' . $fileName;
}