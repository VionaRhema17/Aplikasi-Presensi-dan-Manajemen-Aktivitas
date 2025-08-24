<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembimbing') {
    header('Location: login.php');
    exit();
}


$user = $_SESSION['user']; // <-- Tambahkan baris ini!
$user_id = $user['id'];
$msg = '';

$user_id = $_SESSION['user']['id'];
$msg = '';

if (isset($_POST['upload_foto']) && isset($_FILES['foto'])) {
    $target_dir = "../uploads/profil/";
    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($ext, $allowed)) {
        $error = "Format file tidak didukung. Gunakan JPG, PNG, atau GIF.";
    } elseif ($_FILES['foto']['size'] > $max_size) {
        $error = "Ukuran file terlalu besar. Maksimal 5MB.";
    } else {
        $filename = "profil_" . $user_id . "_" . time() . "." . $ext;
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
            // Simpan nama file ke session
            $_SESSION['user']['foto'] = $filename;
            // Jika pakai database, update juga di database di sini
            // Contoh: mysqli_query($conn, "UPDATE users SET foto='$filename' WHERE id='$user_id'");
            header("Location: profil.php?success=1");
            exit();
        } else {
            $error = "Gagal upload foto.";
        }
    }
}


// Ambil nama file foto terbaru dari session
$foto = isset($_SESSION['user']['foto']) && $_SESSION['user']['foto'] ? $_SESSION['user']['foto'] : 'default-avatar.png';
?>
 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pembimbing - Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --secondary-color: #8b5cf6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --dark-color: #1f2937;
            --light-bg: #f8fafc;
            --sidebar-width: 280px;
            --sidebar-collapsed: 80px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--light-bg);
            color: var(--dark-color);
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            transition: all 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed);
        }

        .sidebar-brand {
            padding: 1.5rem;
            text-align: center;
            color: white;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-brand h3 {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }

        .sidebar-brand .brand-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0.25rem 1rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
        }

        .nav-link i {
            width: 20px;
            margin-right: 1rem;
            text-align: center;
        }

        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            padding: 2rem;
        }


        .profile-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .profile-header {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 3rem;
            margin-bottom: 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #6366f1, #8b5cf6, #ec4899);
        }

        .profile-avatar-container {
            position: relative;
            display: inline-block;
            margin-bottom: 2rem;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }

        .profile-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }

        .avatar-badge {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            border: 3px solid white;
        }

        .profile-name {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .profile-role {
            color: #6366f1;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .profile-stats {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 2rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            display: block;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Upload Section */
        .upload-section {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        }

        .upload-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .upload-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .upload-subtitle {
            color: #64748b;
            font-size: 0.95rem;
        }

        .upload-zone {
            border: 2px dashed #d1d5db;
            border-radius: 16px;
            padding: 3rem 2rem;
            text-align: center;
            background: #f9fafb;
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 1.5rem;
        }

        .upload-zone:hover {
            border-color: #6366f1;
            background: #f0f9ff;
        }

        .upload-zone.dragover {
            border-color: #6366f1;
            background: #eff6ff;
        }

        .upload-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.5rem;
        }

        .upload-text {
            color: #374151;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .upload-hint {
            color: #6b7280;
            font-size: 0.9rem;
        }

        .file-input {
            display: none;
        }

        .preview-container {
            margin-top: 1rem;
            text-align: center;
        }

        .preview-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .btn-upload {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border: none;
            color: white;
            padding: 0.875rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 1rem;
        }

        .btn-upload:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
            color: white;
        }

        .btn-upload:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Alert Styles */
        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-top: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .alert-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        /* Header */
       .header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}

        .header-left h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }

        .header-left p {
            color: #6b7280;
            margin: 0.25rem 0 0 0;
        }

        .header-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

        .time-widget {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 3rem 2rem;
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            animation: float 20s linear infinite;
        }

        @keyframes float {
            0% { transform: translateY(0px) rotate(0deg); }
            100% { transform: translateY(-20px) rotate(360deg); }
        }

        .welcome-section h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .welcome-section p {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-color);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            text-decoration: none;
            color: inherit;
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        .stat-card.primary::before { background: var(--primary-color); }
        .stat-card.success::before { background: var(--success-color); }
        .stat-card.info::before { background: var(--info-color); }
        .stat-card.warning::before { background: var(--warning-color); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.primary { background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); }
        .stat-icon.success { background: linear-gradient(135deg, var(--success-color), #059669); }
        .stat-icon.info { background: linear-gradient(135deg, var(--info-color), #2563eb); }
        .stat-icon.warning { background: linear-gradient(135deg, var(--warning-color), #d97706); }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
            font-size: 0.85rem;
        }

        .trend-up {
            color: var(--success-color);
        }

        .trend-down {
            color: var(--danger-color);
        }

        /* Toggle Button */
        .sidebar-toggle {
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .sidebar-toggle:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }

        /* Logout Modal */
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 20px 20px 0 0;
            border-bottom: none;
        }

        .btn-logout {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .welcome-section h2 {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>

<body>
    <!-- Sidebar Toggle Button -->
    <button class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <h3>Dashboard Pembimbing</h3>
        </div>
        
        <div class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link" data-section="dashboard">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="presensi.php" class="nav-link" data-section="presensi">
                    <i class="fas fa-calendar-check"></i>
                    <span>Monitoring Presensi</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="aktivitas.php" class="nav-link" data-section="aktivitas">
                    <i class="fas fa-tasks"></i>
                    <span>Monitoring Aktivitas</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="pengumuman.php" class="nav-link" data-section="pengumuman">
                    <i class="fas fa-bullhorn"></i>
                    <span>Pengumuman</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="profil.php" class="nav-link active" data-section="profil">
                    <i class="fas fa-user-circle"></i>
                    <span>Profil</span>
                </a>
            </div>
            <div class="nav-item" style="margin-top: 2rem;">
                <a href="#" class="nav-link" id="logoutBtn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
      <div class="main-content">
        <div class="profile-container">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar-container">
                    <img src="<?= '../uploads/profil/' . htmlspecialchars($foto) ?>" class="profile-avatar" alt="Foto Profil" id="currentAvatar">
                </div>
                
                <h1 class="profile-name"><?= htmlspecialchars($user['nama']) ?></h1>
                <div class="profile-role">
                    <i class="fas fa-user-tie"></i> <?= ucfirst($user['role']) ?>
                </div>

                <div class="profile-stats">
                    <div class="stat-item">
                        <span class="stat-number">12</span>
                        <span class="stat-label">Siswa Bimbingan</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">5</span>
                        <span class="stat-label">Tahun Pengalaman</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">98%</span>
                        <span class="stat-label">Tingkat Kehadiran</span>
                    </div>
                </div>
            </div>

            <!-- Upload Section -->
            <div class="upload-section">
                <div class="upload-header">
                    <h2 class="upload-title">Perbarui Foto Profil</h2>
                    <p class="upload-subtitle">Pilih foto yang akan menggantikan foto profil Anda saat ini</p>
                </div>

                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="upload-zone" id="uploadZone">
                        <div class="upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <div class="upload-text">Klik untuk memilih foto atau seret ke sini</div>
                        <div class="upload-hint">Format: JPG, PNG, GIF (Maksimal 5MB)</div>
                        <input type="file" name="foto" id="foto" class="file-input" accept="image/*" required>
                    </div>

                    <div class="preview-container" id="previewContainer" style="display: none;">
                        <img id="previewImage" class="preview-image" alt="Preview">
                    </div>

                    <button type="submit" name="upload_foto" class="btn-upload" id="uploadBtn" disabled>
                        <i class="fas fa-upload"></i> Upload Foto Profil
                    </button>
                </form>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                    </div>
                <?php elseif (isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Foto profil berhasil diperbarui!
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Logout Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-sign-out-alt"></i> Konfirmasi Logout
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div class="mb-3">
                    <i class="fas fa-question-circle" style="font-size: 3rem; color: var(--primary-color);"></i>
                </div>
                <h6>Apakah Anda yakin ingin keluar?</h6>
                <p class="text-muted">Anda akan diarahkan ke halaman login.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <!-- ✅ Tombol Logout dengan id yang diperlukan -->
                <button type="button" class="btn btn-logout" id="confirmLogout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </div>
    </div>
</div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar Toggle
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');

        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });

        // Navigation
        const navLinks = document.querySelectorAll('.nav-link[data-section]');
       navLinks.forEach(link => {
    link.addEventListener('click', (e) => {
        // Hanya prevent default kalau link tidak mengarah ke halaman nyata
        if (link.getAttribute('href') === '#') {
            e.preventDefault();

            // Remove active class from all links
            navLinks.forEach(l => l.classList.remove('active'));

            // Add active class to clicked link
            link.classList.add('active');

            console.log('Navigating to:', link.dataset.section);
        }
    });
});


        // Stat cards navigation
        const statCards = document.querySelectorAll('.stat-card[data-section]');
        statCards.forEach(card => {
            card.addEventListener('click', (e) => {
                e.preventDefault();
                
                // Remove active from nav links
                navLinks.forEach(l => l.classList.remove('active'));
                
                // Add active to corresponding nav link
                const targetSection = card.dataset.section;
                const targetNavLink = document.querySelector(`.nav-link[data-section="${targetSection}"]`);
                if (targetNavLink) {
                    targetNavLink.classList.add('active');
                }
                
                console.log('Navigating to:', targetSection);
            });
        });

        // Time Update
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('current-time').textContent = timeString;
        }

        setInterval(updateTime, 1000);
        updateTime();

    // Tampilkan modal Logout
    document.getElementById('logoutBtn').addEventListener('click', (e) => {
        e.preventDefault();
        const modal = new bootstrap.Modal(document.getElementById('logoutModal'));
        modal.show();
    });

    // ✅ Fungsi Logout Redirect
    document.getElementById('confirmLogout').addEventListener('click', () => {
        window.location.href = 'logout.php'; // Ganti jika file logout kamu bukan logout.php
    });

       // Ambil data asli dari server
function updateDashboardStats() {
    fetch('dashboard-data.php')
        .then(response => response.json())
        .then(data => {
            document.getElementById('totalPresensi').textContent = data.presensi;
            document.getElementById('totalAktivitas').textContent = data.aktivitas;
            document.getElementById('siswaIzin').textContent = data.izin;
            document.getElementById('siswaTanpaAktivitas').textContent = data.tanpaAktivitas;
        })
        .catch(error => {
            console.error('Gagal mengambil data:', error);
        });
}

// Panggil saat halaman dimuat dan setiap 30 detik
updateDashboardStats();
setInterval(updateDashboardStats, 30000);

        // Mobile responsiveness
        function handleResize() {
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            }
        }

        window.addEventListener('resize', handleResize);
        handleResize();

        // Loading animation for stat cards
        function showLoading(element) {
            const originalContent = element.innerHTML;
            element.innerHTML = '<div class="loading"></div>';
            
            setTimeout(() => {
                element.innerHTML = originalContent;
            }, 1000);
        }

        // Add click animations
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('click', () => {
                card.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    card.style.transform = '';
                }, 150);
            });
        });
    </script>
    <script>
function updateStatCards() {
    fetch('dashboard-data.php')
        .then(response => response.json())
        .then(data => {
            document.getElementById('totalPresensi').textContent = data.presensi;
            document.getElementById('totalAktivitas').textContent = data.aktivitas;
            document.getElementById('siswaIzin').textContent = data.izin;
            document.getElementById('siswaTanpaAktivitas').textContent = data.tanpa_aktivitas;
        })
        .catch(error => {
            console.error('Gagal mengambil data statistik:', error);
        });
}

updateStatCards(); // Jalankan saat halaman load
setInterval(updateStatCards, 30000); // Update setiap 30 detik

        // File upload functionality
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('foto');
        const previewContainer = document.getElementById('previewContainer');
        const previewImage = document.getElementById('previewImage');
        const uploadBtn = document.getElementById('uploadBtn');
        const uploadForm = document.getElementById('uploadForm');

        // Click to select file
        uploadZone.addEventListener('click', () => {
            fileInput.click();
        });

        // Drag and drop functionality
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect(files[0]);
            }
        });

        // File input change
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });

        function handleFileSelect(file) {
            // Validation
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            const maxSize = 5 * 1024 * 1024; // 5MB

            if (!allowedTypes.includes(file.type)) {
                alert('Format file tidak didukung. Gunakan JPG, PNG, atau GIF.');
                return;
            }

            if (file.size > maxSize) {
                alert('Ukuran file terlalu besar. Maksimal 5MB.');
                return;
            }

            // Show preview
            const reader = new FileReader();
            reader.onload = (e) => {
                previewImage.src = e.target.result;
                previewContainer.style.display = 'block';
                uploadBtn.disabled = false;
            };
            reader.readAsDataURL(file);
        }

        // Form submission with loading state
        uploadForm.addEventListener('submit', (e) => {
            uploadBtn.innerHTML = '<span class="loading"></span> Uploading...';
            uploadBtn.disabled = true;
        });

        // Logout functionality
        document.getElementById('logoutBtn').addEventListener('click', (e) => {
            e.preventDefault();
            if (confirm('Apakah Anda yakin ingin logout?')) {
                window.location.href = 'logout.php';
            }
        });

        // Auto-hide success message
        if (document.querySelector('.alert-success')) {
            setTimeout(() => {
                document.querySelector('.alert-success').style.opacity = '0';
                setTimeout(() => {
                    document.querySelector('.alert-success').style.display = 'none';
                }, 300);
            }, 3000);
        }
    </script>
</script>

</body>
</html>