<?php

/**
 * =====================================================
 * UPDATE COMMENT PROCESS - CONNE
 * =====================================================
 * Memproses pembaruan data komentar:
 * 1. Memeriksa metode request POST dan validasi CSRF token.
 * 2. Memeriksa kepemilikan komentar (otorisasi).
 * 3. Memvalidasi isi teks komentar (maks. 250 karakter).
 * 4. Mengunggah gambar/dokumen baru jika disediakan.
 * 5. Memperbarui basis data, membersihkan berkas lama,
 *    dan melakukan rollback berkas jika terjadi kegagalan.
 */

// Memanggil modul autentikasi dan fungsi bantuan.
require_once __DIR__ . '/../helpers/auth.php';

// Memastikan hanya pengguna yang telah login yang dapat melakukan pembaruan.
require_login();

// -----------------------------------------------------
// 1. VALIDASI METODE REQUEST & CSRF TOKEN
// -----------------------------------------------------

// Memastikan request dikirimkan melalui metode POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/home.php');
}

// Memverifikasi kecocokan token CSRF untuk mencegah serangan CSRF.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Permintaan tidak valid atau token sesi telah berakhir.');
    redirect('/pages/home.php');
}

// -----------------------------------------------------
// 2. VALIDASI ID KOMENTAR
// -----------------------------------------------------

// Mengambil dan memvalidasi integer comment_id.
$commentId = filter_var(
    $_POST['comment_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$commentId || $commentId < 1) {
    set_flash('error', 'Komentar tidak valid.');
    redirect('/pages/home.php');
}

// Mengambil ID akun aktif dari session.
$currentUser = current_user_id();

// -----------------------------------------------------
// 3. MEMERIKSA KEPEMILIKAN KOMENTAR (OTORISASI)
// -----------------------------------------------------

// Memastikan komentar ada dan benar-benar milik pengguna yang sedang aktif.
$check = $pdo->prepare("
    SELECT *
    FROM comments
    WHERE id = :id
    AND user_id = :user_id
    LIMIT 1
");

$check->execute([
    ':id'      => $commentId,
    ':user_id' => $currentUser
]);

$comment = $check->fetch();

// Jika bukan pemiliknya, tolak akses.
if (!$comment) {
    set_flash('error', 'Komentar tidak ditemukan atau bukan milik kamu.');
    redirect('/pages/home.php');
}

// URL untuk kembali ke halaman edit jika terjadi error validasi.
$editUrl = '/pages/edit-comment.php?id=' . $commentId;

// URL halaman detail postingan tujuan kembali setelah berhasil.
$detailUrl = '/pages/detail-post.php?id=' . (int) $comment['post_id'];


// -----------------------------------------------------
// 4. VALIDASI PANJANG TEKS KOMENTAR
// -----------------------------------------------------

$content = trim($_POST['content'] ?? '');

// Memeriksa panjang string karakter (maksimal 250 karakter).
$length = function_exists('mb_strlen')
    ? mb_strlen($content, 'UTF-8')
    : strlen($content);

if ($length > 250) {
    set_flash('error', 'Komentar maksimal 250 karakter.');
    redirect($editUrl);
}


// -----------------------------------------------------
// 5. FORMAT BERKAS YANG DIIZINKAN
// -----------------------------------------------------

// Format gambar yang diizinkan (MIME type => ekstensi file).
$imageMimes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp'
];

// Format dokumen yang diizinkan (MIME type => ekstensi file).
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

$newImage = null;
$newFile = null;


// -----------------------------------------------------
// 6. PROSES UPDATE KOMENTAR & PENGELOLAAN BERKAS
// -----------------------------------------------------

try {

    // Mengunggah gambar baru jika dipilih.
    $newImage = upload_file(
        'image',
        'uploads/comments',
        $imageMimes
    );

    // Mengunggah file dokumen baru jika dipilih.
    $newFile = upload_file(
        'file',
        'uploads/comments',
        $fileMimes
    );

    // Menyiapkan nilai lampiran awal berdasarkan data yang sudah ada di database.
    $imagePath = $comment['image'];
    $filePath  = $comment['file'];
    $fileName  = $comment['file_name'];

    $oldImageToDelete = null;
    $oldFileToDelete  = null;

    // Logika pergantian atau penghapusan berkas gambar.
    if ($newImage) {
        $imagePath = $newImage;
        $oldImageToDelete = $comment['image'];
    } elseif (!empty($_POST['remove_image'])) {
        $imagePath = null;
        $oldImageToDelete = $comment['image'];
    }

    // Logika pergantian atau penghapusan berkas dokumen.
    if ($newFile) {
        $filePath = $newFile;
        $fileName = basename($_FILES['file']['name']);
        $oldFileToDelete = $comment['file'];
    } elseif (!empty($_POST['remove_file'])) {
        $filePath = null;
        $fileName = null;
        $oldFileToDelete = $comment['file'];
    }

    // Memastikan komentar tidak dikosongkan seluruhnya (wajib ada teks atau lampiran).
    if ($content === '' && !$imagePath && !$filePath) {
        throw new RuntimeException(
            'Komentar harus memiliki teks, gambar, atau file lampiran.'
        );
    }

    // Memperbarui data komentar di database.
    $update = $pdo->prepare("
        UPDATE comments
        SET
            content   = :content,
            image     = :image,
            file      = :file,
            file_name = :file_name
        WHERE id = :id
        AND user_id = :user_id
    ");

    $update->execute([
        ':content'   => $content,
        ':image'     => $imagePath,
        ':file'      => $filePath,
        ':file_name' => $fileName,
        ':id'        => $commentId,
        ':user_id'   => $currentUser
    ]);

    // Hapus berkas fisik lama dari server setelah database berhasil diperbarui.
    if ($oldImageToDelete) {
        $oldImageFullPath = BASE_PATH . '/' . $oldImageToDelete;

        if (is_file($oldImageFullPath)) {
            unlink($oldImageFullPath);
        }
    }

    if ($oldFileToDelete) {
        $oldFileFullPath = BASE_PATH . '/' . $oldFileToDelete;

        if (is_file($oldFileFullPath)) {
            unlink($oldFileFullPath);
        }
    }

    set_flash('success', 'Komentar berhasil diperbarui.');

} catch (RuntimeException $e) {

    // Bersihkan berkas baru jika proses gagal (rollback fisik).
    if ($newImage && is_file(BASE_PATH . '/' . $newImage)) {
        unlink(BASE_PATH . '/' . $newImage);
    }

    if ($newFile && is_file(BASE_PATH . '/' . $newFile)) {
        unlink(BASE_PATH . '/' . $newFile);
    }

    set_flash('error', $e->getMessage());
    redirect($editUrl);

} catch (PDOException $e) {

    error_log('Update comment error: ' . $e->getMessage());

    // Bersihkan berkas baru jika query database gagal.
    if ($newImage && is_file(BASE_PATH . '/' . $newImage)) {
        unlink(BASE_PATH . '/' . $newImage);
    }

    if ($newFile && is_file(BASE_PATH . '/' . $newFile)) {
        unlink(BASE_PATH . '/' . $newFile);
    }

    set_flash('error', 'Komentar gagal diperbarui.');
    redirect($editUrl);

}

// Mengarahkan kembali ke halaman detail postingan.
redirect($detailUrl);
