<?php

/**
 * =====================================================
 * UPDATE PROFILE PHOTO - CONNE
 * Memproses perubahan foto profil pengguna.
 * =====================================================
 */

require_once __DIR__ . '/../helpers/auth.php';

require_login();

// Memastikan request menggunakan POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/profile.php');
}

// Memeriksa CSRF token.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Permintaan tidak valid.');
    redirect('/pages/profile.php');
}

// Jenis gambar yang diperbolehkan.
$imageMimes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp'
];

$newPhoto = null;

try {
    // Mengambil foto lama.
    $userQuery = $pdo->prepare("
        SELECT profile_photo
        FROM users
        WHERE id = :id
    ");

    $userQuery->execute([
        ':id' => current_user_id()
    ]);

    $user = $userQuery->fetch();

    if (!$user) {
        throw new RuntimeException('Pengguna tidak ditemukan.');
    }

    // Mengunggah foto baru.
    $newPhoto = upload_file(
        'profile_photo',
        'uploads/profiles',
        $imageMimes,
        5242880
    );

    if (!$newPhoto) {
        throw new RuntimeException('Silakan pilih foto profil terlebih dahulu.');
    }

    // Menyimpan lokasi foto baru ke database.
    $updateQuery = $pdo->prepare("
        UPDATE users
        SET profile_photo = :photo
        WHERE id = :id
    ");

    $updateQuery->execute([
        ':photo' => $newPhoto,
        ':id' => current_user_id()
    ]);

    // Menghapus foto lama setelah database berhasil diperbarui.
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

    // Menghapus foto baru jika proses gagal.
    if ($newPhoto && is_file(BASE_PATH . '/' . $newPhoto)) {
        unlink(BASE_PATH . '/' . $newPhoto);
    }

    set_flash('error', $e->getMessage());

} catch (PDOException $e) {

    error_log('Update profile photo error: ' . $e->getMessage());

    if ($newPhoto && is_file(BASE_PATH . '/' . $newPhoto)) {
        unlink(BASE_PATH . '/' . $newPhoto);
    }

    set_flash('error', 'Foto profil gagal diperbarui.');
}

redirect('/pages/profile.php');