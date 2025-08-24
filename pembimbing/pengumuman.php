<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembimbing') {
    header("Location: ../login.php");
    exit;
}

$msg = '';
$error = '';

date_default_timezone_set('Asia/Jakarta');
// Proses tambah pengumuman
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_pengumuman'])) {
    $judul = trim($_POST['judul']);
    $isi = trim($_POST['isi']);
    $tanggal = date('Y-m-d H:i:s');
    
    if (!empty($judul) && !empty($isi)) {
        $stmt = $conn->prepare("INSERT INTO pengumuman (judul, isi, tanggal) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $judul, $isi, $tanggal);
        
        if ($stmt->execute()) {
            $msg = "Pengumuman berhasil ditambahkan!";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Judul dan isi pengumuman harus diisi!";
    }
}

// Proses hapus pengumuman
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_pengumuman'])) {
    $id = $_POST['id_pengumuman'];
    
    $stmt = $conn->prepare("DELETE FROM pengumuman WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $msg = "Pengumuman berhasil dihapus!";
    } else {
        $error = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Query untuk mengambil pengumuman
$sql_pengumuman = "SELECT * FROM pengumuman ORDER BY tanggal DESC";
$pengumuman = $conn->query($sql_pengumuman);

if (!$pengumuman) {
    die("Query error: " . $conn->error);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengumuman - Dashboard Pembimbing</title>
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

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: all 0.3s ease;
            padding: 2rem;
        }

        .main-content.expanded {
            margin-left: var(--sidebar-collapsed);
        }

        /* Header */
        .header {
            background: white;
            border-radius: 15px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            gap: 1rem;
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

        /* Cards */
        .modern-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
            border: none;
        }

        .modern-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .card-header-modern {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }

        .card-icon.primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        }

        .card-icon.info {
            background: linear-gradient(135deg, var(--info-color), #2563eb);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }

        /* Button Styles */
        .btn-modern {
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary-modern {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
        }

        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        }

        .btn-danger-modern {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: white;
        }

        .btn-danger-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3);
        }

        /* Pengumuman Item */
        .pengumuman-item {
            background: #f8fafc;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }

        .pengumuman-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .pengumuman-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .pengumuman-date {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .pengumuman-content {
            color: var(--dark-color);
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        /* Alert Styles */
        .alert-modern {
            border-radius: 15px;
            padding: 1rem 1.5rem;
            border: none;
            margin-bottom: 2rem;
        }

        .alert-success-modern {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
        }

        .alert-danger-modern {
            background: linear-gradient(135deg, #fee2e2, #fca5a5);
            color: #991b1b;
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

        /* Modal Styles */
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 3rem;
            color: #d1d5db;
            margin-bottom: 1rem;
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
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="presensi.php" class="nav-link">
                    <i class="fas fa-calendar-check"></i>
                    <span>Monitoring Presensi</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="aktivitas.php" class="nav-link">
                    <i class="fas fa-tasks"></i>
                    <span>Monitoring Aktivitas</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="pengumuman.php" class="nav-link active">
                    <i class="fas fa-bullhorn"></i>
                    <span>Pengumuman</span>
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
    <main class="main-content" id="mainContent">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <h1><i class="fas fa-bullhorn"></i> Kelola Pengumuman</h1>
                <p>Buat dan kelola pengumuman untuk siswa</p>
            </div>
            <div class="header-right">
                <div class="time-widget">
                    <i class="fas fa-clock"></i>
                    <span id="current-time">00:00:00</span>
                </div>
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600; font-size: 0.9rem;">
                            <?= isset($_SESSION['user']['nama']) ? htmlspecialchars($_SESSION['user']['nama']) : 'Admin Pembimbing' ?>
                        </div>
                        <div style="font-size: 0.8rem; color: #6b7280;">Pembimbing</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (!empty($msg)): ?>
            <div class="alert alert-modern alert-success-modern" role="alert">
                <i class="fas fa-check-circle"></i> <?= $msg ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-modern alert-danger-modern" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Form Tambah Pengumuman -->
        <div class="modern-card">
            <div class="card-header-modern">
                <div class="card-icon primary">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div>
                    <h2 class="card-title">Tambah Pengumuman Baru</h2>
                    <p style="color: #6b7280; margin: 0;">Buat pengumuman untuk siswa</p>
                </div>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Judul Pengumuman</label>
                    <input type="text" class="form-control" name="judul" required placeholder="Masukkan judul pengumuman">
                </div>
                <div class="form-group">
                    <label class="form-label">Isi Pengumuman</label>
                    <textarea class="form-control" name="isi" rows="5" required placeholder="Masukkan isi pengumuman"></textarea>
                </div>
                <button type="submit" name="tambah_pengumuman" class="btn btn-modern btn-primary-modern">
                    <i class="fas fa-paper-plane"></i> Publikasikan Pengumuman
                </button>
            </form>
        </div>

        <!-- Daftar Pengumuman -->
        <div class="modern-card">
            <div class="card-header-modern">
                <div class="card-icon info">
                    <i class="fas fa-list"></i>
                </div>
                <div>
                    <h2 class="card-title">Daftar Pengumuman</h2>
                    <p style="color: #6b7280; margin: 0;">Kelola pengumuman yang telah dibuat</p>
                </div>
            </div>

            <?php if ($pengumuman->num_rows > 0): ?>
                <?php while($row = $pengumuman->fetch_assoc()): ?>
                    <div class="pengumuman-item">
                        <div class="pengumuman-title">
                            <i class="fas fa-bullhorn"></i> <?= htmlspecialchars($row['judul']) ?>
                        </div>
                        <div class="pengumuman-date">
                            <i class="fas fa-calendar-alt"></i> 
                            <?= date('d F Y, H:i', strtotime($row['tanggal'])) ?> WIB
                        </div>
                        <div class="pengumuman-content">
                            <?= nl2br(htmlspecialchars($row['isi'])) ?>
                        </div>
                        <button type="button" class="btn btn-modern btn-danger-modern btn-sm" 
                                onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars($row['judul']) ?>')">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-info-circle"></i>
                    <h5>Belum ada pengumuman</h5>
                    <p>Tambahkan pengumuman pertama untuk siswa</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-trash"></i> Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <div class="mb-3">
                        <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--warning-color);"></i>
                    </div>
                    <h6>Apakah Anda yakin?</h6>
                    <p class="text-muted">Pengumuman "<span id="deleteTitle"></span>" akan dihapus secara permanen.</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="id_pengumuman" id="deleteId">
                        <button type="submit" name="hapus_pengumuman" class="btn btn-modern btn-danger-modern">
                            <i class="fas fa-trash"></i> Ya, Hapus
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

     <!-- Logout Modal -->
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
                <a href="../logout.php" class="btn btn-logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
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

        // Logout Modal
        document.getElementById('logoutBtn').addEventListener('click', (e) => {
            e.preventDefault();
            const modal = new bootstrap.Modal(document.getElementById('logoutModal'));
            modal.show();
        });

        // Delete Confirmation
        function confirmDelete(id, title) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteTitle').textContent = title;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }

        // Mobile responsiveness
        function handleResize() {
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            }
        }

        window.addEventListener('resize', handleResize);
        handleResize();

        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-modern');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            });
        }, 5000);
    </script>
</body>
</html>