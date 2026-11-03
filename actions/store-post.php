<?php

/**
 * =====================================================
 * STORE POST PROCESS - CONNE
 * =====================================================
 * Memproses pembuatan postingan baru di linimasa:
 * 1. Memeriksa status login dan metode HTTP POST.
 * 2. Memeriksa keabsahan token CSRF.
 * 3. Memvalidasi panjang karakter teks status (maks. 250 karakter).
 * 4. Memeriksa tipe MIME & ukuran berkas lampiran (foto / dokumen).
 * 5. Menyimpan data postingan ke tabel `posts`.
 * 6. Melakukan rollback berkas fisik jika penyimpanan basis data gagal.
 */

// Memanggil modul autentikasi dan fungsi bantuan.
require_once __DIR__ . '/../helpers/auth.php';

// Pastikan hanya pengguna yang sudah login yang dapat membuat postingan.
require_login();

// -----------------------------------------------------
// 1. MEMERIKSA REQUEST & CSRF TOKEN
// -----------------------------------------------------

// Proses hanya menerima metode HTTP POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/home.php');
}

// Memeriksa token CSRF untuk keamanan form.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Permintaan tidak valid atau token sesi telah berakhir.');
    redirect('/pages/home.php');
}

// -----------------------------------------------------
// 2. VALIDASI TEKS POSTINGAN
// -----------------------------------------------------

// Mengambil dan membersihkan spasi di awal/akhir teks postingan.
$content = trim($_POST['content'] ?? '');

// Memeriksa panjang teks maksimal 250 karakter menggunakan mb_strlen (aman untuk UTF-8).
$length = function_exists('mb_strlen')
    ? mb_strlen($content, 'UTF-8')
    : strlen($content);

if ($length > 250) {
    set_flash('error', 'Postingan maksimal 250 karakter.');
    redirect('/pages/home.php');
}

// -----------------------------------------------------
// 3. DAFTAR FORMAT BERKAS YANG DIIZINKAN
// -----------------------------------------------------

// Daftar format gambar yang diperbolehkan (MIME type => ekstensi).
$imageMimes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp'
];

// Daftar format dokumen yang diperbolehkan (MIME type => ekstensi).
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

// Menyiapkan variabel lokasi berkas unggahan.
$imagePath = null;
$filePath  = null;

try {

    // -------------------------------------------------
    // 4. MEMPROSES UNGGAHAN BERKAS (FOTO & DOKUMEN)
    // -------------------------------------------------

    // Upload berkas gambar jika dipilih oleh pengguna.
    $imagePath = upload_file(
        'image',
        'uploads/posts',
        $imageMimes
    );

    // Upload berkas dokumen jika dipilih oleh pengguna.
    $filePath = upload_file(
        'file',
        'uploads/posts',
        $fileMimes
    );

    // Memastikan postingan tidak kosong (wajib ada teks, foto, atau berkas).
    if ($content === '' && !$imagePath && !$filePath) {
        throw new RuntimeException(
            'Postingan harus memiliki teks, gambar, atau file lampiran.'
        );
    }

    // -------------------------------------------------
    // 5. MENYIMPAN POSTINGAN KE BASIS DATA
    // -------------------------------------------------

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
        ':image'   => $imagePath,
        ':file'    => $filePath,
        // Mengambil nama asli file dokumen untuk ditampilkan sebagai label tautan
        ':file_name' => $filePath
            ? basename($_FILES['file']['name'])
            : null
    ]);

    set_flash('success', 'Postingan berhasil dibuat.');

} catch (RuntimeException $e) {

    // Jika upload/validasi gagal, hapus berkas baru yang sempat terunggah ke disk.
    if ($imagePath && is_file(BASE_PATH . '/' . $imagePath)) {
        unlink(BASE_PATH . '/' . $imagePath);
    }

    if ($filePath && is_file(BASE_PATH . '/' . $filePath)) {
        unlink(BASE_PATH . '/' . $filePath);
    }

    set_flash('error', $e->getMessage());

} catch (PDOException $e) {

    // Mencatat detail error ke log server tanpa membocorkannya ke pengguna.
    error_log('Store post error: ' . $e->getMessage());

    // Menghapus berkas fisik jika database gagal menyimpan data (Rollback fisik).
    if ($imagePath && is_file(BASE_PATH . '/' . $imagePath)) {
        unlink(BASE_PATH . '/' . $imagePath);
    }

    if ($filePath && is_file(BASE_PATH . '/' . $filePath)) {
        unlink(BASE_PATH . '/' . $filePath);
    }

    set_flash('error', 'Terjadi kesalahan sistem saat menyimpan postingan.');

}

// Kembali ke linimasa halaman beranda.
redirect('/pages/home.php');
