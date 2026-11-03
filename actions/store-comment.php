<?php

/**
 * =====================================================
 * STORE COMMENT - CONNE
 * Memproses pembuatan komentar.
 * =====================================================
 */

require_once __DIR__ . '/../helpers/auth.php';

// Memastikan pengguna sudah login.
require_login();

// -----------------------------------------------------
// 1. MEMERIKSA REQUEST
// -----------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/home.php');
}

// Memeriksa token CSRF.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

    set_flash('error', 'Permintaan tidak valid.');

    redirect('/pages/home.php');
}

// -----------------------------------------------------
// 2. MENGAMBIL ID POSTINGAN
// -----------------------------------------------------

$postId = filter_var(
    $_POST['post_id'] ?? null,
    FILTER_VALIDATE_INT
);

// Memastikan ID postingan valid.
if (!$postId || $postId < 1) {

    set_flash('error', 'Postingan tidak valid.');

    redirect('/pages/home.php');
}

// Alamat halaman detail untuk kembali setelah proses komentar.
$detailUrl = '/pages/detail-post.php?id=' . $postId;


// -----------------------------------------------------
// 3. MEMERIKSA POSTINGAN
// -----------------------------------------------------

$checkPost = $pdo->prepare("
    SELECT id
    FROM posts
    WHERE id = :id
");

$checkPost->execute([
    ':id' => $postId
]);

if (!$checkPost->fetch()) {

    set_flash('error', 'Postingan tidak ditemukan.');

    redirect('/pages/home.php');
}


// -----------------------------------------------------
// 4. MENGAMBIL ISI KOMENTAR
// -----------------------------------------------------

$content = trim($_POST['content'] ?? '');

// Memeriksa panjang komentar maksimal 250 karakter.
$length = function_exists('mb_strlen')
    ? mb_strlen($content, 'UTF-8')
    : strlen($content);

if ($length > 250) {

    set_flash('error', 'Komentar maksimal 250 karakter.');

    redirect($detailUrl);
}


// -----------------------------------------------------
// 5. FORMAT FILE YANG DIPERBOLEHKAN
// -----------------------------------------------------

// Format gambar.
$imageMimes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp'
];

// Format dokumen.
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

$imagePath = null;
$filePath = null;


// -----------------------------------------------------
// 6. PROSES UPLOAD DAN SIMPAN KOMENTAR
// -----------------------------------------------------

try {

    // Mengunggah gambar komentar.
    $imagePath = upload_file(
        'image',
        'uploads/comments',
        $imageMimes
    );

    // Mengunggah file komentar.
    $filePath = upload_file(
        'file',
        'uploads/comments',
        $fileMimes
    );


    // Memastikan komentar tidak kosong seluruhnya.
    if ($content === '' && !$imagePath && !$filePath) {

        throw new RuntimeException(
            'Komentar harus memiliki teks, gambar, atau file.'
        );
    }


    // Menyimpan komentar ke database.
    $query = $pdo->prepare("
        INSERT INTO comments (
            post_id,
            user_id,
            content,
            image,
            file,
            file_name
        )
        VALUES (
            :post_id,
            :user_id,
            :content,
            :image,
            :file,
            :file_name
        )
    ");

    $query->execute([

        ':post_id' => $postId,

        ':user_id' => current_user_id(),

        ':content' => $content,

        ':image' => $imagePath,

        ':file' => $filePath,

        ':file_name' => $filePath
            ? basename($_FILES['file']['name'])
            : null

    ]);


    set_flash('success', 'Komentar berhasil ditambahkan.');

} catch (RuntimeException $e) {

    // Membersihkan gambar jika proses gagal.
    if ($imagePath && is_file(BASE_PATH . '/' . $imagePath)) {
        unlink(BASE_PATH . '/' . $imagePath);
    }

    // Membersihkan file jika proses gagal.
    if ($filePath && is_file(BASE_PATH . '/' . $filePath)) {
        unlink(BASE_PATH . '/' . $filePath);
    }

    set_flash('error', $e->getMessage());

} catch (PDOException $e) {

    error_log('Store comment error: ' . $e->getMessage());

    // Membersihkan gambar jika proses gagal.
    if ($imagePath && is_file(BASE_PATH . '/' . $imagePath)) {
        unlink(BASE_PATH . '/' . $imagePath);
    }

    // Membersihkan file jika proses gagal.
    if ($filePath && is_file(BASE_PATH . '/' . $filePath)) {
        unlink(BASE_PATH . '/' . $filePath);
    }

    set_flash('error', 'Komentar gagal disimpan.');

}


// -----------------------------------------------------
// 7. KEMBALI KE HALAMAN DETAIL POSTINGAN
// -----------------------------------------------------

redirect($detailUrl);