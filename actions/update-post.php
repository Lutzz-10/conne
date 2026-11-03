<?php

/**
 * =====================================================
 * UPDATE POST PROCESS - CONNE
 * =====================================================
 * Memproses pembaruan data postingan (teks, gambar, dokumen).
 * Memvalidasi otorisasi pemilik postingan, ukuran karakter,
 * keamanan berkas unggahan, serta membersihkan berkas lama.
 */

// Memanggil modul autentikasi dan fungsi bantuan.
require_once __DIR__ . '/../helpers/auth.php';

// Memastikan hanya pengguna yang telah login yang dapat melakukan pembaruan.
require_login();

// -----------------------------------------------------
// 1. VALIDASI METODE REQUEST & CSRF TOKEN
// -----------------------------------------------------

// Memastikan request dikirimkan melalui metode HTTP POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/home.php');
}

// Memverifikasi kecocokan token CSRF untuk mencegah serangan CSRF.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Permintaan tidak valid atau token sesi telah berakhir.');
    redirect('/pages/home.php');
}

// -----------------------------------------------------
// 2. VALIDASI ID POSTINGAN
// -----------------------------------------------------

// Mengambil dan memvalidasi tipe data integer dari post_id.
$postId = filter_var(
    $_POST['post_id'] ?? null,
    FILTER_VALIDATE_INT
);

// Jika ID tidak valid, alihkan kembali ke beranda.
if (!$postId || $postId < 1) {
    set_flash('error', 'Postingan tidak valid.');
    redirect('/pages/home.php');
}

// Mengambil ID pengguna aktif dari session.
$currentUser = current_user_id();

// -----------------------------------------------------
// 3. MEMERIKSA HAK KEPEMILIKAN POSTINGAN (OTORISASI)
// -----------------------------------------------------

// Prepared statement untuk memastikan postingan ada dan benar-benar milik pengguna yang login.
$check = $pdo->prepare("
    SELECT *
    FROM posts
    WHERE id = :id AND user_id = :user_id
    LIMIT 1
");

$check->execute([
    ':id'      => $postId,
    ':user_id' => $currentUser
]);

$post = $check->fetch();

// Jika bukan pemiliknya atau postingan tidak ditemukan, tolak akses.
if (!$post) {
    set_flash('error', 'Postingan tidak ditemukan atau bukan milik kamu.');
    redirect('/pages/home.php');
}

// URL halaman edit untuk pengalihan jika terjadi kesalahan validasi.
$detailUrl = '/pages/edit-post.php?id=' . $postId;

// -----------------------------------------------------
// 4. VALIDASI ISI TEKS POSTINGAN
// -----------------------------------------------------

$content = trim($_POST['content'] ?? '');

// Memeriksa panjang string teks (maksimal 250 karakter).
$length = function_exists('mb_strlen')
    ? mb_strlen($content, 'UTF-8')
    : strlen($content);

if ($length > 250) {
    set_flash('error', 'Postingan maksimal 250 karakter.');
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

// Format dokumen lampiran yang diizinkan (MIME type => ekstensi file).
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

$oldImageToDelete = null;
$oldFileToDelete = null;

try {

    // -------------------------------------------------
    // 6. PROSES UNGGAH LAMPIRAN BARU (JIKA ADA)
    // -------------------------------------------------

    // Mengunggah gambar baru jika dipilih oleh pengguna.
    $newImage = upload_file(
        'image',
        'uploads/posts',
        $imageMimes
    );

    // Mengunggah dokumen baru jika dipilih oleh pengguna.
    $newFile = upload_file(
        'file',
        'uploads/posts',
        $fileMimes
    );

    // Menentukan path gambar akhir yang disimpan ke database:
    $imagePath = $post['image'];

    if ($newImage) {
        $imagePath = $newImage;
        $oldImageToDelete = $post['image']; // Tandai gambar lama untuk dihapus
    } elseif (!empty($_POST['remove_image'])) {
        $imagePath = null;
        $oldImageToDelete = $post['image']; // Pengguna memilih hapus gambar
    }

    // Menentukan path dokumen akhir yang disimpan ke database:
    $filePath = $post['file'];
    $fileName = $post['file_name'];

    if ($newFile) {
        $filePath = $newFile;
        $fileName = basename($_FILES['file']['name']);
        $oldFileToDelete = $post['file']; // Tandai berkas lama untuk dihapus
    } elseif (!empty($_POST['remove_file'])) {
        $filePath = null;
        $fileName = null;
        $oldFileToDelete = $post['file']; // Pengguna memilih hapus berkas
    }

    // Memastikan postingan tidak sepenuhnya kosong.
    if ($content === '' && !$imagePath && !$filePath) {
        throw new RuntimeException(
            'Postingan harus memiliki teks, gambar, atau file lampiran.'
        );
    }

    // -------------------------------------------------
    // 7. MEMPERBARUI DATA PADA DATABASE
    // -------------------------------------------------

    $update = $pdo->prepare("
        UPDATE posts
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
        ':id'        => $postId,
        ':user_id'   => $currentUser
    ]);

    // -------------------------------------------------
    // 8. PEMBERSIHAN BERKAS FISIK LAMA DARI SERVER
    // -------------------------------------------------

    // Hapus gambar lama dari disk jika diganti atau dihapus.
    if ($oldImageToDelete) {
        $path = BASE_PATH . '/' . $oldImageToDelete;

        if (is_file($path)) {
            unlink($path);
        }
    }

    // Hapus dokumen lama dari disk jika diganti atau dihapus.
    if ($oldFileToDelete) {
        $path = BASE_PATH . '/' . $oldFileToDelete;

        if (is_file($path)) {
            unlink($path);
        }
    }

    set_flash('success', 'Postingan berhasil diperbarui.');

} catch (RuntimeException $e) {

    // Jika terjadi error validasi berkas, hapus berkas baru yang sempat terunggah (rollback fisik).
    if ($newImage && is_file(BASE_PATH . '/' . $newImage)) {
        unlink(BASE_PATH . '/' . $newImage);
    }

    if ($newFile && is_file(BASE_PATH . '/' . $newFile)) {
        unlink(BASE_PATH . '/' . $newFile);
    }

    set_flash('error', $e->getMessage());
    redirect($detailUrl);

} catch (PDOException $e) {

    // Mencatat detail error database ke log server.
    error_log('Update post error: ' . $e->getMessage());

    // Bersihkan berkas baru jika database gagal memperbarui.
    if ($newImage && is_file(BASE_PATH . '/' . $newImage)) {
        unlink(BASE_PATH . '/' . $newImage);
    }

    if ($newFile && is_file(BASE_PATH . '/' . $newFile)) {
        unlink(BASE_PATH . '/' . $newFile);
    }

    set_flash('error', 'Postingan gagal diperbarui.');
    redirect($detailUrl);
}

// Kembali ke linimasa beranda setelah berhasil.
redirect('/pages/home.php');
