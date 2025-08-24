<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama = trim($_POST['nama']);
    $username = trim($_POST['username']);
    $passwordRaw = $_POST['password'];
    $role = $_POST['role'];

    if (strlen($passwordRaw) < 6) {
        $error = "Password minimal 6 karakter.";
    } else {
        $password = password_hash($passwordRaw, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $nama, $username, $password, $role);

        if ($stmt->execute()) {
            header("Location: login.php");
            exit;
        } else {
            $error = "Gagal mendaftar: " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Register - Presensi</title>
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
        .form-control:focus, .form-select:focus {
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
        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b15 0%, #ee5a5215 100%);
            border: 1px solid #ff6b6b30;
            color: #e55353;
            border-radius: 12px;
        }
        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
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
        /* Custom styling untuk select */
        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23667eea' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m1 6 6 6 6-6'/%3e%3c/svg%3e");
        }
        .form-select:focus {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23667eea' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m1 6 6 6 6-6'/%3e%3c/svg%3e");
        }
        /* Efek glow subtle pada form elements saat focus */
        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.25), 0 0 15px rgba(102, 126, 234, 0.1);
        }
        /* Animasi untuk form labels */
        label {
            transition: color 0.3s ease;
        }
        .form-control:focus + label,
        .form-select:focus + label {
            color: #667eea;
        }
        /* Styling khusus untuk eye icon */
        #eyeIcon {
            transition: color 0.3s ease;
        }
        #togglePassword:hover #eyeIcon {
            color: #764ba2;
        }
    </style>
</head>
<body class="d-flex align-items-center" style="min-height: 100vh;">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 bg-white p-4 card-custom">
            <div class="text-center mb-4">
                <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" width="60" alt="Register Icon">
                <h3 class="mt-2 fw-bold text-primary">Daftar Akun</h3>
                <p class="text-muted">Buat akun baru untuk melanjutkan</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="fw-semibold">Nama Lengkap</label>
                    <input type="text" name="nama" class="form-control" placeholder="Masukkan nama lengkap" required>
                </div>
                <div class="mb-3">
                    <label class="fw-semibold">Nama Pengguna</label>
                    <input type="text" name="username" class="form-control" placeholder="Masukkan nama pengguna" required>
                </div>
                <div class="mb-3">
                    <label class="fw-semibold">Password</label>
                    <div class="input-group">
                        <input type="password" name="password" class="form-control" id="password" placeholder="Buat kata sandi anda" required>
                        <span class="input-group-text" id="togglePassword">  
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="fw-semibold">Daftar sebagai</label>
                    <select name="role" class="form-select" required>
                        <option value="" disabled selected>Pilih peran</option>
                        <option value="siswa">Siswa</option>
                        <option value="pembimbing">Pembimbing</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary w-100 fw-bold">Daftar</button>
            </form>

            <div class="text-center mt-3">
                <span class="text-muted d-block mb-2">Sudah punya akun?</span>
                <a href="login.php" class="btn btn-outline-primary w-100 fw-semibold">Masuk</a>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Toggle password visibility
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    togglePassword.addEventListener('click', function () {
        const type = passwordInput.type === 'password' ? 'text' : 'password';
        passwordInput.type = type;
        eyeIcon.classList.toggle('bi-eye');
        eyeIcon.classList.toggle('bi-eye-slash');
    });

    // Placeholder behavior
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

    // Enhanced form validation feedback
    const form = document.querySelector('form');
    const inputs = document.querySelectorAll('input[required], select[required]');
    
    inputs.forEach(input => {
        input.addEventListener('invalid', function(e) {
            e.preventDefault();
            this.style.borderColor = '#ff6b6b';
            this.style.boxShadow = '0 0 0 3px rgba(255, 107, 107, 0.25)';
        });
        
        input.addEventListener('input', function() {
            if (this.validity.valid) {
                this.style.borderColor = '#667eea';
                this.style.boxShadow = '0 0 0 3px rgba(102, 126, 234, 0.25)';
            }
        });
    });
</script>

</body>
</html>