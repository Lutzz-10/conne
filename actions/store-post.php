<?php

/**
 * =====================================================
 * STORE POST - CONNE
 * =====================================================
 * Memproses pembuatan postingan baru.
 */

require_once __DIR__ . '/../helpers/auth.php';

// Pastikan pengguna sudah login.
require_login();

// Proses hanya menerima POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/home.php');
}

// Memeriksa token CSRF.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

    set_flash('error', 'Permintaan tidak valid.');

    redirect('/pages/home.php');
}

// Mengambil teks postingan.
$content = trim($_POST['content'] ?? '');

// Memeriksa panjang teks maksimal 250 karakter.
$length = function_exists('mb_strlen')
    ? mb_strlen($content, 'UTF-8')
    : strlen($content);

if ($length > 250) {

    set_flash('error', 'Postingan maksimal 250 karakter.');

    redirect('/pages/home.php');
}

// Daftar format gambar yang diperbolehkan.
$imageMimes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp'
];

// Daftar format dokumen yang diperbolehkan.
$fileMimes = [
    'application/pdf' => 'pdf',
    'text/plain' => 'txt',
    'text/csv' => 'csv',
    'application/csv' => 'csv',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-excel' => 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    'application/vnd.ms-powerpoint' => 'ppt',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    'application/zip' => 'zip',
    'application/x-zip-compressed' => 'zip'
];

// Menyiapkan variabel lokasi file.
$imagePath = null;
$filePath = null;

try {

    // Upload gambar jika tersedia.
    $imagePath = upload_file(
        'image',
        'uploads/posts',
        $imageMimes
    );

    // Upload dokumen jika tersedia.
    $filePath = upload_file(
        'file',
        'uploads/posts',
        $fileMimes
    );

    // Postingan harus memiliki teks atau lampiran.
    if ($content === '' && !$imagePath && !$filePath) {

        throw new RuntimeException(
            'Postingan harus memiliki teks, gambar, atau file.'
        );

    }

    // Menyimpan postingan ke database.
    $query = $pdo->prepare("
        INSERT INTO posts (
            user_id,
            content,
            image,
            file,
            file_name
        )
        VALUES (
            :user_id,
            :content,
            :image,
            :file,
            :file_name
        )
    ");

    $query->execute([

        ':user_id' => current_user_id(),

        ':content' => $content,

        ':image' => $imagePath,

        ':file' => $filePath,

        // Mengambil nama asli file untuk ditampilkan.
        ':file_name' => $filePath
            ? basename($_FILES['file']['name'])
            : null

    ]);

    set_flash('success', 'Postingan berhasil dibuat.');

} catch (RuntimeException $e) {

    // Menghapus gambar jika proses selanjutnya gagal.
    if ($imagePath && is_file(BASE_PATH . '/' . $imagePath)) {
        unlink(BASE_PATH . '/' . $imagePath);
    }

    if ($filePath && is_file(BASE_PATH . '/' . $filePath)) {
        unlink(BASE_PATH . '/' . $filePath);
    }

    set_flash('error', $e->getMessage());

} catch (PDOException $e) {

    // Mencatat detail error tanpa membocorkannya ke pengguna.
    error_log('Store post error: ' . $e->getMessage());

    // Menghapus file jika database gagal menyimpan data.
    if ($imagePath && is_file(BASE_PATH . '/' . $imagePath)) {
        unlink(BASE_PATH . '/' . $imagePath);
    }

    if ($filePath && is_file(BASE_PATH . '/' . $filePath)) {
        unlink(BASE_PATH . '/' . $filePath);
    }

    set_flash('error', 'Postingan gagal disimpan.');

}

// Kembali ke halaman home.
redirect('/pages/home.php');