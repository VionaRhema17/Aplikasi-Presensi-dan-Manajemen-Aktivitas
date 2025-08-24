<?php
session_start();
include 'config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = $user;
        header("Location: " . ($user['role'] == 'pembimbing' ? "pembimbing/dashboard.php" : "siswa/dashboard.php"));
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Presensi & Aktivitas Harian</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            min-height: 100vh;
        }
        .card-custom {
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.2);
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.25);
            border-color: #667eea;
        }
        .input-group-text {
            background: transparent;
            border-left: none;
            cursor: pointer;
            color: #667eea;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        .btn-outline-primary {
            border-color: #667eea;
            color: #667eea;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-outline-primary:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        .text-primary {
            color: #667eea !important;
        }
        .alert-success {
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            border: 1px solid #667eea30;
            color: #5a6fd8;
            border-radius: 12px;
        }
        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b15 0%, #ee5a5215 100%);
            border: 1px solid #ff6b6b30;
            color: #e55353;
            border-radius: 12px;
        }
        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            border: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-danger:hover {
            background: linear-gradient(135deg, #ff5252 0%, #e53e3e 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 107, 107, 0.3);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6169 100%);
            border: none;
            border-radius: 12px;
            font-weight: 600;
        }
        .modal-content {
            border-radius: 20px;
            border: none;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
        .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
        }
        .input-group-text {
            border-radius: 0 12px 12px 0;
            border: 2px solid #e9ecef;
            border-left: none;
        }
        .form-control:focus + .input-group-text {
            border-color: #667eea;
        }
        label {
            color: #495057;
            margin-bottom: 8px;
        }
        .text-muted {
            color: #6c757d !important;
        }
        /* Animasi gradient background */
        body {
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }
        @keyframes gradientShift {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }
        /* Efek hover pada card */
        .card-custom:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.25);
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="d-flex align-items-center" style="min-height: 100vh;">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 bg-white p-4 card-custom">
            <div class="text-center mb-4">
                <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" width="60" alt="App Icon">
                <h4 class="fw-bold text-primary text-center mt-2">Selamat Datang</h4>
                <p class="text-muted">Silakan login untuk melanjutkan</p>
            </div>

            <?php if (isset($_SESSION['user'])): ?>
                <div class="alert alert-success text-center">
                    Anda login sebagai <strong><?= $_SESSION['user']['nama'] ?></strong> (<?= $_SESSION['user']['role'] ?>)
                </div>
                <div class="text-center">
                    <a href="<?= $_SESSION['user']['role'] ?>/dashboard.php" class="btn btn-primary w-100 mb-2">Ke Dashboard</a>
                    <a href="#" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#logoutModal">Keluar</a>
                </div>
            <?php else: ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger text-center"><?= $error ?></div>
                <?php endif; ?>
                <form method="post">
                    <div class="mb-3">
                        <label class="fw-semibold">Nama Pengguna</label>
                        <input type="text" name="username" class="form-control" placeholder="Masukkan nama pengguna" required>
                    </div>
                    <div class="mb-3">
                        <label class="fw-semibold">Kata Sandi</label>
                        <div class="input-group">
                            <input type="password" name="password" class="form-control" id="password" placeholder="Masukkan kata sandi" required>
                            <span class="input-group-text" id="togglePassword">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </span>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100 fw-bold">Masuk</button>
                </form>
                <div class="text-center mt-3">
                    <span class="text-muted d-block mb-2">Atau</span>
                    <a href="register.php" class="btn btn-outline-primary w-100 fw-semibold">Daftar</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Logout -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header border-0">
        <h5 class="modal-title text-danger fw-bold" id="logoutModalLabel">Konfirmasi Logout</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body text-center">
        <i class="bi bi-box-arrow-right display-4 text-danger mb-3"></i>
        <p class="mb-3">Apakah Anda yakin ingin keluar dari akun?</p>
      </div>
      <div class="modal-footer border-0 justify-content-center">
        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Batal</button>
        <a href="logout.php" class="btn btn-danger px-4">Ya, Keluar</a>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    if (togglePassword) {
        togglePassword.addEventListener('click', function () {
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;
            eyeIcon.classList.toggle('bi-eye');
            eyeIcon.classList.toggle('bi-eye-slash');
        });
    }
</script>

<script>
    document.querySelectorAll('input, select, textarea').forEach(el => {
        el.addEventListener('focus', function () {
            this.dataset.placeholder = this.placeholder;
            this.placeholder = '';
        });
        el.addEventListener('blur', function () {
            if (this.value === '') {
                this.placeholder = this.dataset.placeholder;
            }
        });
    });
</script>

</body>
</html>