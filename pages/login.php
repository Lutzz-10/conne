<?php

/**
 * =====================================================
 * LOGIN & REGISTER PAGE - CONNE
 * =====================================================
 */

// Memanggil helper autentikasi.
require_once __DIR__ . '/../helpers/auth.php';

// Pengguna yang sudah login tidak perlu membuka halaman ini.
require_guest();

// Mengambil pesan sementara dari session.
$flash = get_flash();

// Menentukan tab aktif berdasarkan parameter URL (?tab=register atau login).
$activeTab = (isset($_GET['tab']) && $_GET['tab'] === 'register') ? 'register' : 'login';

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title><?= $activeTab === 'register' ? 'Daftar Akun' : 'Login'; ?> - CONNE</title>

    <link rel="stylesheet"
          href="<?= BASE_URL ?>/assets/css/style.css">

</head>

<body>

    <div class="login-container">

        <div class="card">

            <h1 class="login-title">CONNE</h1>

            <p style="text-align:center; color:#64748b; font-size:14px; margin-bottom:18px;">
                Platform berbagi koneksi & inspirasi
            </p>

            <!-- Navigasi Tab Login & Register -->
            <div class="auth-tabs" role="tablist">

                <button type="button"
                        id="tab-btn-login"
                        class="auth-tab-btn <?= $activeTab === 'login' ? 'active' : ''; ?>"
                        onclick="switchAuthTab('login')">
                    Masuk
                </button>

                <button type="button"
                        id="tab-btn-register"
                        class="auth-tab-btn <?= $activeTab === 'register' ? 'active' : ''; ?>"
                        onclick="switchAuthTab('register')">
                    Daftar Akun
                </button>

            </div>

            <!-- Pesan Flash (Sukses / Gagal) -->
            <?php if ($flash): ?>

                <div class="alert <?= $flash['type'] === 'success'
                    ? 'alert-success'
                    : 'alert-error' ?>">

                    <?= e($flash['message']); ?>

                </div>

            <?php endif; ?>

            <!-- ================= FORM LOGIN ================= -->
            <div id="auth-form-login"
                 class="auth-form <?= $activeTab === 'login' ? 'active' : ''; ?>">

                <form action="<?= BASE_URL ?>/actions/login.php"
                      method="POST">

                    <!-- Token CSRF untuk keamanan form -->
                    <input type="hidden"
                           name="csrf_token"
                           value="<?= e(csrf_token()); ?>">

                    <div class="form-group">

                        <label for="login-email">Email</label>

                        <input type="email"
                               id="login-email"
                               name="email"
                               class="form-control"
                               placeholder="nama@email.com"
                               required
                               autocomplete="username">

                    </div>

                    <div class="form-group">

                        <label for="login-password">Password</label>

                        <input type="password"
                               id="login-password"
                               name="password"
                               class="form-control"
                               placeholder="Masukkan password"
                               required
                               autocomplete="current-password">

                    </div>

                    <button type="submit"
                            class="btn btn-primary"
                            style="width:100%; margin-top:6px;">
                        Masuk ke Akun
                    </button>

                </form>

                <p class="auth-switch-text">
                    Belum punya akun?
                    <a href="javascript:void(0)" onclick="switchAuthTab('register')">
                        Daftar sekarang
                    </a>
                </p>

            </div>

            <!-- ================= FORM REGISTER ================= -->
            <div id="auth-form-register"
                 class="auth-form <?= $activeTab === 'register' ? 'active' : ''; ?>">

                <form action="<?= BASE_URL ?>/actions/register.php"
                      method="POST">

                    <!-- Token CSRF untuk keamanan form -->
                    <input type="hidden"
                           name="csrf_token"
                           value="<?= e(csrf_token()); ?>">

                    <div class="form-group">

                        <label for="reg-username">Username</label>

                        <input type="text"
                               id="reg-username"
                               name="username"
                               class="form-control"
                               placeholder="Contoh: johndoe"
                               required
                               minlength="3"
                               maxlength="50"
                               pattern="^[a-zA-Z0-9_]{3,50}$"
                               title="Username 3-50 karakter, hanya huruf, angka, dan underscore (_)"
                               autocomplete="username">

                        <small>Hanya huruf, angka, dan underscore (3-50 karakter)</small>

                    </div>

                    <div class="form-group">

                        <label for="reg-email">Alamat Email</label>

                        <input type="email"
                               id="reg-email"
                               name="email"
                               class="form-control"
                               placeholder="nama@email.com"
                               required
                               autocomplete="email">

                    </div>

                    <div class="form-group">

                        <label for="reg-password">Password</label>

                        <input type="password"
                               id="reg-password"
                               name="password"
                               class="form-control"
                               placeholder="Minimal 6 karakter"
                               required
                               minlength="6"
                               autocomplete="new-password">

                        <small>Minimal 6 karakter</small>

                    </div>

                    <div class="form-group">

                        <label for="reg-confirm-password">Konfirmasi Password</label>

                        <input type="password"
                               id="reg-confirm-password"
                               name="confirm_password"
                               class="form-control"
                               placeholder="Ulangi password"
                               required
                               minlength="6"
                               autocomplete="new-password">

                    </div>

                    <button type="submit"
                            class="btn btn-primary"
                            style="width:100%; margin-top:6px;">
                        Daftar Sekarang
                    </button>

                </form>

                <p class="auth-switch-text">
                    Sudah punya akun?
                    <a href="javascript:void(0)" onclick="switchAuthTab('login')">
                        Masuk di sini
                    </a>
                </p>

            </div>

        </div>

        <p style="text-align:center; color:#94a3b8; font-size:13px; margin-top:15px;">
            &copy; 2026 CONNE
        </p>

    </div>

    <!-- Script Tab Switching -->
    <script>
        function switchAuthTab(tabName) {
            const formLogin = document.getElementById('auth-form-login');
            const formRegister = document.getElementById('auth-form-register');
            const tabBtnLogin = document.getElementById('tab-btn-login');
            const tabBtnRegister = document.getElementById('tab-btn-register');

            if (tabName === 'register') {
                formLogin.classList.remove('active');
                formRegister.classList.add('active');
                tabBtnLogin.classList.remove('active');
                tabBtnRegister.classList.add('active');
                document.title = 'Daftar Akun - CONNE';

                // Perbarui URL tanpa reload
                if (window.history.replaceState) {
                    const newUrl = window.location.pathname + '?tab=register';
                    window.history.replaceState(null, '', newUrl);
                }
            } else {
                formRegister.classList.remove('active');
                formLogin.classList.add('active');
                tabBtnRegister.classList.remove('active');
                tabBtnLogin.classList.add('active');
                document.title = 'Login - CONNE';

                // Perbarui URL tanpa reload
                if (window.history.replaceState) {
                    const newUrl = window.location.pathname + '?tab=login';
                    window.history.replaceState(null, '', newUrl);
                }
            }
        }
    </script>

</body>

</html>
