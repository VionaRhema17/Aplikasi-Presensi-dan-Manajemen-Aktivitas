<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembimbing') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user']['id'];
$msg = '';

// Handle filter
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d');
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';

// Query untuk mendapatkan data presensi siswa
$where_conditions = ["u.role = 'siswa'"];
$params = [];
$param_types = "";

if ($filter_date) {
    $where_conditions[] = "p.tanggal = ?";
    $params[] = $filter_date;
    $param_types .= "s";
}

if ($filter_status) {
    $where_conditions[] = "p.status = ?";
    $params[] = $filter_status;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Query untuk mendapatkan presensi siswa dengan join ke tabel users
$presensi_query = "SELECT p.*, u.nama as nama_siswa
                   FROM presensi p 
                   LEFT JOIN users u ON p.user_id = u.id 
                   WHERE $where_clause 
                   ORDER BY p.tanggal DESC, p.jam_masuk DESC";

$presensi_stmt = $conn->prepare($presensi_query);
if (!empty($params)) {
    $presensi_stmt->bind_param($param_types, ...$params);
}
$presensi_stmt->execute();
$presensi_result = $presensi_stmt->get_result();

// Query untuk statistik hari ini
$today = date('Y-m-d');
$stats_query = "SELECT 
                COUNT(*) as total_siswa,
                COUNT(CASE WHEN p.tanggal = ? THEN 1 END) as hadir_hari_ini,
                COUNT(CASE WHEN p.tanggal = ? AND p.jam_keluar IS NOT NULL THEN 1 END) as sudah_pulang
                FROM users u 
                LEFT JOIN presensi p ON u.id = p.user_id AND p.tanggal = ?
                WHERE u.role = 'siswa'";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("sss", $today, $today, $today);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Query untuk mendapatkan daftar siswa yang belum presensi hari ini
$absent_query = "SELECT u.nama
                 FROM users u 
                 LEFT JOIN presensi p ON u.id = p.user_id AND p.tanggal = ?
                 WHERE u.role = 'siswa' AND p.id IS NULL";
$absent_stmt = $conn->prepare($absent_query);
$absent_stmt->bind_param("s", $today);
$absent_stmt->execute();
$absent_result = $absent_stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Presensi - Dashboard Pembimbing</title>
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

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .filter-section h5 {
            color: var(--dark-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .form-control {
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }

        .btn {
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        }

        .btn-secondary {
            background: #6b7280;
            border: none;
        }

        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-2px);
        }

        /* Content Card */
        .content-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .content-card h5 {
            color: var(--dark-color);
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        /* Table Styles */
        .table-responsive {
            border-radius: 15px;
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            font-weight: 600;
            border: none;
            padding: 1rem;
        }

        .table tbody td {
            padding: 1rem;
            border-color: #e5e7eb;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: #f8fafc;
        }

        .status-hadir {
            color: var(--success-color);
            font-weight: 600;
        }

        .status-alpha {
            color: var(--danger-color);
            font-weight: 600;
        }

        .status-izin {
            color: var(--warning-color);
            font-weight: 600;
        }

        /* Alert Styles */
        .alert {
            border-radius: 15px;
            border: none;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .alert-warning {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
        }

        .alert-info {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #1e40af;
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 500;
        }

        .badge-warning {
            background: var(--warning-color);
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
            
            .stats-grid {
                grid-template-columns: 1fr;
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
                <a href="dashboard.php" class="nav-link" data-section="dashboard">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="presensi.php" class="nav-link active" data-section="presensi">
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
                <h1><i class="fas fa-calendar-check"></i> Monitoring Presensi</h1>
                <p>Pantau data presensi siswa secara real-time</p>
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

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= $stats['total_siswa'] ?></div>
                        <div class="stat-label">Total Siswa</div>
                    </div>
                    <div class="stat-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card success">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= $stats['hadir_hari_ini'] ?></div>
                        <div class="stat-label">Hadir Hari Ini</div>
                    </div>
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card info">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= $stats['sudah_pulang'] ?></div>
                        <div class="stat-label">Sudah Pulang</div>
                    </div>
                    <div class="stat-icon info">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card warning">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= $absent_result->num_rows ?></div>
                        <div class="stat-label">Belum Presensi</div>
                    </div>
                    <div class="stat-icon warning">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Siswa Belum Presensi -->
        <?php if ($absent_result->num_rows > 0): ?>
        <div class="alert alert-warning">
            <h6><i class="fas fa-exclamation-triangle"></i> Siswa Belum Presensi Hari Ini:</h6>
            <div class="row mt-3">
                <?php 
                $absent_result->data_seek(0); // Reset pointer
                while ($absent = $absent_result->fetch_assoc()): 
                ?>
                    <div class="col-md-4 mb-2">
                        <span class="badge badge-warning"><?= htmlspecialchars($absent['nama']) ?></span>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <h5><i class="fas fa-filter"></i> Filter Data</h5>
            <form method="GET" action="">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Filter Tanggal:</label>
                        <input type="date" name="filter_date" class="form-control" value="<?= $filter_date ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Filter Status:</label>
                        <select name="filter_status" class="form-control">
                            <option value="">Semua Status</option>
                            <option value="hadir" <?= $filter_status == 'hadir' ? 'selected' : '' ?>>Hadir</option>
                            <option value="izin" <?= $filter_status == 'izin' ? 'selected' : '' ?>>Izin</option>
                            <option value="alpha" <?= $filter_status == 'alpha' ? 'selected' : '' ?>>Alpha</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="presensi.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Reset
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Data Presensi -->
        <div class="content-card">
            <h5>
                <i class="fas fa-list"></i> Data Presensi Siswa
                <?php if ($filter_date): ?>
                    - <?= date('d/m/Y', strtotime($filter_date)) ?>
                <?php endif; ?>
            </h5>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Siswa</th>
                            <th>Tanggal</th>
                            <th>Jam Masuk</th>
                            <th>Jam Keluar</th>
                            <th>Status</th>
                            <th>Durasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($presensi_result->num_rows > 0): ?>
                            <?php $no = 1; ?>
                            <?php while ($row = $presensi_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($row['nama_siswa']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                    <td>
                                        <?= $row['jam_masuk'] ? date('H:i', strtotime($row['jam_masuk'])) : '-' ?>
                                    </td>
                                    <td>
                                        <?= $row['jam_keluar'] ? date('H:i', strtotime($row['jam_keluar'])) : '-' ?>
                                    </td>
                                    <td>
                                        <span class="status-<?= $row['status'] ?>">
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        if ($row['jam_masuk'] && $row['jam_keluar']) {
                                            $masuk = new DateTime($row['jam_masuk']);
                                            $keluar = new DateTime($row['jam_keluar']);
                                            $durasi = $masuk->diff($keluar);
                                            echo $durasi->format('%H:%I');
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">Tidak ada data presensi untuk tanggal yang dipilih</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

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
       
        // Auto refresh data setiap 30 detik
    setInterval(function() {
        location.reload();
    }, 30000);

    // Logout Modal (Bootstrap 5, vanilla JS)
    document.getElementById('logoutBtn').addEventListener('click', function(e) {
        e.preventDefault();
        var logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
        logoutModal.show();
    });
</script>
      
<script>
    $('#logoutBtn').on('click', function(e) {
        e.preventDefault();
        $('#logoutModal').modal('show');
    });
</script>
</body>
</html>