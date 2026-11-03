<?php

/**
 * =====================================================
 * UPDATE PROFILE PHOTO PROCESS - CONNE
 * =====================================================
 * Memproses penggantian foto profil akun pengguna:
 * 1. Memeriksa status login dan metode HTTP POST.
 * 2. Memeriksa validitas CSRF token.
 * 3. Memvalidasi tipe MIME berkas gambar (JPEG, PNG, GIF, WebP)
 *    serta batas ukuran maksimal 5 MB.
 * 4. Menyimpan foto baru ke `uploads/profiles/`.
 * 5. Memperbarui kolom `profile_photo` pada tabel `users`.
 * 6. Menghapus berkas foto profil lama dari server disk.
 * 7. Membersihkan berkas foto baru jika terjadi kegagalan (Rollback).
 */

// Memanggil modul autentikasi dan fungsi bantuan.
require_once __DIR__ . '/../helpers/auth.php';

// Memastikan hanya pengguna yang telah login yang dapat mengubah foto profil.
require_login();

// -----------------------------------------------------
// 1. MEMERIKSA REQUEST & CSRF TOKEN
// -----------------------------------------------------

// Memastikan request dikirim melalui metode POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/profile.php');
}

// Memeriksa keabsahan token CSRF.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Permintaan tidak valid atau token sesi telah berakhir.');
    redirect('/pages/profile.php');
}

// -----------------------------------------------------
// 2. DAFTAR FORMAT GAMBAR YANG DIIZINKAN
// -----------------------------------------------------

// Jenis MIME gambar yang diperbolehkan beserta ekstensinya.
$imageMimes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp'
];

$newPhoto = null;

try {

    // -------------------------------------------------
    // 3. MENGAMBIL INFORMASI PENGGUNA & FOTO LAMA
    // -------------------------------------------------

    $userQuery = $pdo->prepare("
        SELECT profile_photo
        FROM users
        WHERE id = :id
        LIMIT 1
    ");

    $userQuery->execute([
        ':id' => current_user_id()
    ]);

    $user = $userQuery->fetch();

    if (!$user) {
        throw new RuntimeException('Pengguna tidak ditemukan.');
    }

    // -------------------------------------------------
    // 4. MENGUNGGAH BERKAS FOTO BARU
    // -------------------------------------------------

    // Mengunggah gambar ke direktori uploads/profiles dengan batas 5MB (5242880 byte).
    $newPhoto = upload_file(
        'profile_photo',
        'uploads/profiles',
        $imageMimes,
        5242880
    );

    if (!$newPhoto) {
        throw new RuntimeException('Silakan pilih foto profil terlebih dahulu.');
    }

    // -------------------------------------------------
    // 5. MEMPERBARUI BASIS DATA
    // -------------------------------------------------

    $updateQuery = $pdo->prepare("
        UPDATE users
        SET profile_photo = :photo
        WHERE id = :id
    ");

    $updateQuery->execute([
        ':photo' => $newPhoto,
        ':id'    => current_user_id()
    ]);

    // -------------------------------------------------
    // 6. MENGHAPUS BERKAS FOTO LAMA DARI SERVER
    // -------------------------------------------------

    // Hapus foto profil lama dari disk agar tidak memenuhi kapasitas penyimpanan.
    $oldPhoto = $user['profile_photo'];

    if (
        !empty($oldPhoto) &&
        strpos($oldPhoto, 'uploads/profiles/') === 0 &&
        $oldPhoto !== $newPhoto
    ) {
        $oldPhotoPath = BASE_PATH . '/' . $oldPhoto;

        if (is_file($oldPhotoPath)) {
            unlink($oldPhotoPath);
        }
    }

    set_flash('success', 'Foto profil berhasil diperbarui.');

} catch (RuntimeException $e) {

    // Menghapus foto baru yang sempat terunggah jika terjadi kegagalan validasi (Rollback).
    if ($newPhoto && is_file(BASE_PATH . '/' . $newPhoto)) {
        unlink(BASE_PATH . '/' . $newPhoto);
    }

    set_flash('error', $e->getMessage());

} catch (PDOException $e) {

    // Catat pesan error ke log server.
    error_log('Update profile photo error: ' . $e->getMessage());

    // Bersihkan foto baru jika update basis data gagal.
    if ($newPhoto && is_file(BASE_PATH . '/' . $newPhoto)) {
        unlink(BASE_PATH . '/' . $newPhoto);
    }

    set_flash('error', 'Terjadi kesalahan sistem saat memperbarui foto profil.');

}

// Mengarahkan kembali ke halaman profil pengguna.
redirect('/pages/profile.php');
