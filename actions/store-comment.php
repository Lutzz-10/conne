<?php

/**
 * =====================================================
 * STORE COMMENT PROCESS - CONNE
 * =====================================================
 * Memproses penyimpanan komentar baru pada postingan tertentu:
 * 1. Memeriksa status login dan metode HTTP POST.
 * 2. Memeriksa token CSRF untuk mencegah serangan CSRF.
 * 3. Memvalidasi keberadaan ID postingan target.
 * 4. Memeriksa panjang string karakter komentar (maks. 250 karakter).
 * 5. Mengunggah gambar atau berkas dokumen ke folder `uploads/comments/`.
 * 6. Menyimpan rekaman data ke tabel `comments`.
 * 7. Membersihkan berkas fisik jika terjadi kegagalan sistem.
 */

// Memanggil modul autentikasi dan fungsi bantuan.
require_once __DIR__ . '/../helpers/auth.php';

// Memastikan hanya pengguna yang telah login yang dapat mengirim komentar.
require_login();

// -----------------------------------------------------
// 1. MEMERIKSA REQUEST & CSRF TOKEN
// -----------------------------------------------------

// Memastikan request dikirim melalui metode POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/home.php');
}

// Memeriksa token CSRF.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Permintaan tidak valid atau token sesi telah berakhir.');
    redirect('/pages/home.php');
}

// -----------------------------------------------------
// 2. MENGAMBIL & MEMVALIDASI ID POSTINGAN
// -----------------------------------------------------

// Mengambil dan memvalidasi integer post_id tujuan komentar.
$postId = filter_var(
    $_POST['post_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$postId || $postId < 1) {
    set_flash('error', 'Postingan tidak valid.');
    redirect('/pages/home.php');
}

// URL halaman detail untuk kembali setelah proses komentar selesai.
$detailUrl = '/pages/detail-post.php?id=' . $postId;

// -----------------------------------------------------
// 3. MEMASTIKAN POSTINGAN TUJUAN ADA DI DATABASE
// -----------------------------------------------------

$checkPost = $pdo->prepare("
    SELECT id
    FROM posts
    WHERE id = :id
    LIMIT 1
");

$checkPost->execute([
    ':id' => $postId
]);

if (!$checkPost->fetch()) {
    set_flash('error', 'Postingan tidak ditemukan.');
    redirect('/pages/home.php');
}

// -----------------------------------------------------
// 4. VALIDASI PANJANG TEKS KOMENTAR
// -----------------------------------------------------

$content = trim($_POST['content'] ?? '');

// Memeriksa panjang teks maksimal 250 karakter.
$length = function_exists('mb_strlen')
    ? mb_strlen($content, 'UTF-8')
    : strlen($content);

if ($length > 250) {
    set_flash('error', 'Komentar maksimal 250 karakter.');
    redirect($detailUrl);
}

// -----------------------------------------------------
// 5. DAFTAR FORMAT BERKAS YANG DIIZINKAN
// -----------------------------------------------------

// Format gambar yang diizinkan (MIME type => ekstensi file).
$imageMimes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp'
];

// Format berkas dokumen yang diizinkan (MIME type => ekstensi file).
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
$filePath  = null;

// -----------------------------------------------------
// 6. PROSES UPLOAD DAN SIMPAN KOMENTAR
// -----------------------------------------------------

try {

    // Mengunggah gambar komentar jika dipilih.
    $imagePath = upload_file(
        'image',
        'uploads/comments',
        $imageMimes
    );

    // Mengunggah dokumen lampiran komentar jika dipilih.
    $filePath = upload_file(
        'file',
        'uploads/comments',
        $fileMimes
    );

    // Memastikan komentar tidak kosong seluruhnya (wajib ada teks, foto, atau file).
    if ($content === '' && !$imagePath && !$filePath) {
        throw new RuntimeException(
            'Komentar harus memiliki teks, gambar, atau file lampiran.'
        );
    }

    // Menyimpan data komentar ke database menggunakan prepared statement.
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
        ':post_id'   => $postId,
        ':user_id'   => current_user_id(),
        ':content'   => $content,
        ':image'     => $imagePath,
        ':file'      => $filePath,
        // Menyimpan nama asli file dokumen untuk tautan unduhan
        ':file_name' => $filePath
            ? basename($_FILES['file']['name'])
            : null
    ]);

    set_flash('success', 'Komentar berhasil ditambahkan.');

} catch (RuntimeException $e) {

    // Membersihkan berkas yang sempat terunggah jika terjadi error validasi (Rollback fisik).
    if ($imagePath && is_file(BASE_PATH . '/' . $imagePath)) {
        unlink(BASE_PATH . '/' . $imagePath);
    }

    if ($filePath && is_file(BASE_PATH . '/' . $filePath)) {
        unlink(BASE_PATH . '/' . $filePath);
    }

    set_flash('error', $e->getMessage());

} catch (PDOException $e) {

    // Mencatat detail error ke log server tanpa membocorkannya ke publik.
    error_log('Store comment error: ' . $e->getMessage());

    // Membersihkan berkas fisik jika database gagal menyimpan data.
    if ($imagePath && is_file(BASE_PATH . '/' . $imagePath)) {
        unlink(BASE_PATH . '/' . $imagePath);
    }

    if ($filePath && is_file(BASE_PATH . '/' . $filePath)) {
        unlink(BASE_PATH . '/' . $filePath);
    }

    set_flash('error', 'Komentar gagal disimpan.');

}

// -----------------------------------------------------
// 7. KEMBALI KE HALAMAN DETAIL POSTINGAN
// -----------------------------------------------------

redirect($detailUrl);
