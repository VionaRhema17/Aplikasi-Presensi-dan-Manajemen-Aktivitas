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
$filter_siswa = isset($_GET['filter_siswa']) ? $_GET['filter_siswa'] : '';

// Query untuk mendapatkan data aktivitas siswa
$where_conditions = ["u.role = 'siswa'"];
$params = [];
$param_types = "";

if ($filter_date) {
    $where_conditions[] = "a.tanggal = ?";
    $params[] = $filter_date;
    $param_types .= "s";
}

if ($filter_status) {
    $where_conditions[] = "a.status = ?";
    $params[] = $filter_status;
    $param_types .= "s";
}

if ($filter_siswa) {
    $where_conditions[] = "a.user_id = ?";
    $params[] = $filter_siswa;
    $param_types .= "i";
}

$where_clause = implode(" AND ", $where_conditions);

// Query untuk mendapatkan aktivitas siswa dengan join ke tabel users
$aktivitas_query = "SELECT a.*, u.nama as nama_siswa
                    FROM aktivitas a 
                    LEFT JOIN users u ON a.user_id = u.id 
                    WHERE $where_clause 
                    ORDER BY a.tanggal DESC, a.jam_submit DESC";

$aktivitas_stmt = $conn->prepare($aktivitas_query);
if (!empty($params)) {
    $aktivitas_stmt->bind_param($param_types, ...$params);
}
$aktivitas_stmt->execute();
$aktivitas_result = $aktivitas_stmt->get_result();

// Query untuk statistik hari ini
$today = date('Y-m-d');
$stats_query = "SELECT 
                COUNT(DISTINCT u.id) as total_siswa,
                COUNT(CASE WHEN a.tanggal = ? THEN 1 END) as total_aktivitas_hari_ini,
                COUNT(CASE WHEN a.tanggal = ? AND a.status = 'selesai' THEN 1 END) as aktivitas_selesai,
                COUNT(CASE WHEN a.tanggal = ? AND a.status = 'sedang_dikerjakan' THEN 1 END) as aktivitas_progress,
                COUNT(CASE WHEN a.tanggal = ? AND a.status = 'belum_mulai' THEN 1 END) as aktivitas_belum_mulai
                FROM users u 
                LEFT JOIN aktivitas a ON u.id = a.user_id AND a.tanggal = ?
                WHERE u.role = 'siswa'";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("sssss", $today, $today, $today, $today, $today);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Query untuk mendapatkan daftar siswa untuk filter
$siswa_query = "SELECT id, nama FROM users WHERE role = 'siswa' ORDER BY nama";
$siswa_result = $conn->query($siswa_query);

// Query untuk mendapatkan siswa yang belum mengirimkan aktivitas hari ini
$query_siswa_belum_aktivitas = "
    SELECT u.id, u.nama 
    FROM users u 
    WHERE u.role = 'siswa' 
    AND u.id NOT IN (
        SELECT DISTINCT user_id 
        FROM aktivitas 
        WHERE tanggal = CURDATE() 
        AND user_id IS NOT NULL
    )
    ORDER BY u.nama ASC
";
$result_siswa_belum_aktivitas = mysqli_query($conn, $query_siswa_belum_aktivitas);

if (!$result_siswa_belum_aktivitas) {
    die("Query error: " . mysqli_error($conn));
}

$siswa_belum_aktivitas = [];
while ($row = mysqli_fetch_assoc($result_siswa_belum_aktivitas)) {
    $siswa_belum_aktivitas[] = $row;
}

// Handle update status aktivitas
if (isset($_POST['update_status'])) {
    $aktivitas_id = $_POST['aktivitas_id'];
    $new_status = $_POST['new_status'];
    $komentar = $_POST['komentar'];
    
    $update_query = "UPDATE aktivitas SET status = ?, komentar_pembimbing = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ssi", $new_status, $komentar, $aktivitas_id);
    
    if ($update_stmt->execute()) {
        $msg = '<div class="alert alert-success alert-modern"><i class="fas fa-check-circle"></i> Status aktivitas berhasil diupdate!</div>';
    } else {
        $msg = '<div class="alert alert-danger alert-modern"><i class="fas fa-exclamation-circle"></i> Gagal mengupdate status aktivitas!</div>';
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Aktivitas - Dashboard Pembimbing</title>
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

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }

        .filter-section h5 {
            color: var(--dark-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-control {
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }

        .btn-modern {
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary.btn-modern {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        }

        .btn-primary.btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        }

        .btn-secondary.btn-modern {
            background: #6b7280;
        }

        .btn-secondary.btn-modern:hover {
            background: #4b5563;
            transform: translateY(-2px);
        }

        /* Content Cards */
        .content-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .content-card-header {
            background: linear-gradient(135deg, #f3e8ff, #e0e7ff);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .content-card-header h5 {
            margin: 0;
            color: var(--dark-color);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .content-card-body {
            padding: 2rem;
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

        /* Table Styles */
        .table-modern {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .table-modern thead th {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            padding: 1rem;
            border: none;
        }

        .table-modern tbody tr {
            transition: all 0.3s ease;
        }

        .table-modern tbody tr:hover {
            background: #f8fafc;
            transform: scale(1.01);
        }

        .table-modern tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #e5e7eb;
        }

        /* Badge Styles */
        .badge-modern {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-success.badge-modern {
            background: linear-gradient(135deg, var(--success-color), #059669);
        }

        .badge-warning.badge-modern {
            background: linear-gradient(135deg, var(--warning-color), #d97706);
        }

        .badge-danger.badge-modern {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
        }

        /* Button Styles */
        .btn-action {
            border-radius: 8px;
            padding: 0.5rem;
            width: 35px;
            height: 35px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-action:hover {
            transform: scale(1.1);
        }

        /* Alert Styles */
        .alert-modern {
            border-radius: 15px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }

        .alert-success.alert-modern {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
        }

        .alert-danger.alert-modern {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
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
            padding: 1.5rem 2rem;
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid #e5e7eb;
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

        /* Activity Image Styles */
        .activity-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .activity-image:hover {
            transform: scale(1.1);
            border-color: var(--primary-color);
        }

        /* Warning Section untuk Siswa Belum Aktivitas */
        .warning-section {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }

        .warning-section h5 {
            color: #92400e;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .warning-section .icon {
            background: #f59e0b;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .student-badge {
            background: white;
            color: #f59e0b;
            padding: 0.75rem 1.25rem;
            border-radius: 25px;
            margin: 0.5rem 0.5rem 0.5rem 0;
            display: inline-block;
            font-weight: 600;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.2);
            transition: all 0.3s ease;
            border: 2px solid #fbbf24;
        }

        .student-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(245, 158, 11, 0.3);
        }

        .student-badge i {
            margin-right: 0.5rem;
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
                <a href="aktivitas.php" class="nav-link active" data-section="aktivitas">
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
                <h1><i class="fas fa-tasks"></i> Monitoring Aktivitas</h1>
                <p>Kelola dan pantau aktivitas siswa</p>
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

        <?= $msg ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= $stats['total_aktivitas_hari_ini'] ?? 0 ?></div>
                        <div class="stat-label">Total Aktivitas Hari Ini</div>
                    </div>
                    <div class="stat-icon primary">
                        <i class="fas fa-tasks"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card success">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= $stats['aktivitas_selesai'] ?? 0 ?></div>
                        <div class="stat-label">Aktivitas Selesai</div>
                    </div>
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card warning">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= $stats['aktivitas_progress'] ?? 0 ?></div>
                        <div class="stat-label">Sedang Dikerjakan</div>
                    </div>
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card info">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= $stats['aktivitas_belum_mulai'] ?? 0 ?></div>
                        <div class="stat-label">Belum Mulai</div>
                    </div>
                    <div class="stat-icon info">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Siswa Belum Mengirimkan Aktivitas Hari Ini -->
        <?php if (!empty($siswa_belum_aktivitas)): ?>
        <div class="warning-section">
            <h5>
                <div class="icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                Siswa Belum Mengirimkan Aktivitas Hari Ini:
            </h5>
            <div>
                <?php foreach ($siswa_belum_aktivitas as $siswa): ?>
                    <span class="student-badge">
                        <i class="fas fa-user"></i>
                        <?= htmlspecialchars($siswa['nama']) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="content-card">
            <div class="content-card-body text-center" style="background: linear-gradient(135deg, #d1fae5, #a7f3d0); color: #065f46;">
                <i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                <h5 style="color: #065f46; font-weight: 700;">Semua siswa sudah mengirimkan aktivitas hari ini!</h5>
                <p>Tidak ada siswa yang belum mengirimkan aktivitas pada hari ini.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <h5><i class="fas fa-filter"></i> Filter Data</h5>
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Tanggal:</label>
                    <input type="date" name="filter_date" class="form-control" value="<?= $filter_date ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Status:</label>
                    <select name="filter_status" class="form-control">
                        <option value="">Semua Status</option>
                        <option value="belum_mulai" <?= $filter_status == 'belum_mulai' ? 'selected' : '' ?>>Belum Mulai</option>
                        <option value="sedang_dikerjakan" <?= $filter_status == 'sedang_dikerjakan' ? 'selected' : '' ?>>Sedang Dikerjakan</option>
                        <option value="selesai" <?= $filter_status == 'selesai' ? 'selected' : '' ?>>Selesai</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Siswa:</label>
                    <select name="filter_siswa" class="form-control">
                        <option value="">Semua Siswa</option>
                        <?php 
                        $siswa_result->data_seek(0); // Reset result pointer
                        while ($siswa = $siswa_result->fetch_assoc()): 
                        ?>
                            <option value="<?= $siswa['id'] ?>" <?= $filter_siswa == $siswa['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($siswa['nama']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-modern">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <a href="aktivitas.php" class="btn btn-secondary btn-modern">
                            <i class="fas fa-refresh"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Data Aktivitas -->
        <div class="content-card">
            <div class="content-card-header">
                <h5><i class="fas fa-tasks"></i> Data Aktivitas Siswa</h5>
            </div>
            <div class="content-card-body">
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Siswa</th>
                                <th>Tanggal</th>
                                <th>Judul Aktivitas</th>
                                <th>Deskripsi</th>
                                <th>Status</th>
                                <th>Jam Submit</th>
                                <th>Gambar</th>
                                <th>Komentar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($aktivitas_result->num_rows > 0):
                                $no = 1;
                                while ($aktivitas = $aktivitas_result->fetch_assoc()): 
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($aktivitas['nama_siswa']) ?></td>
                                <td><?= date('d/m/Y', strtotime($aktivitas['tanggal'])) ?></td>
                                <td><?= htmlspecialchars($aktivitas['judul_aktivitas']) ?></td>
                                <td class="activity-description"><?= htmlspecialchars($aktivitas['deskripsi']) ?></td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    $status_text = '';
                                    switch($aktivitas['status']) {
                                        case 'belum_mulai':
                                            $status_class = 'badge-danger';
                                            $status_text = 'Belum Mulai';
                                            break;
                                        case 'sedang_dikerjakan':
                                            $status_class = 'badge-warning';
                                            $status_text = 'Sedang Dikerjakan';
                                            break;
                                        case 'selesai':
                                            $status_class = 'badge-success';
                                            $status_text = 'Selesai';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?= $status_class ?> badge-modern"><?= $status_text ?></span>
                                </td>
                                <td><?= $aktivitas['jam_submit'] ? date('H:i', strtotime($aktivitas['jam_submit'])) : '-' ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <?php if (!empty($aktivitas['gambar'])): ?>
                                            <a href="../uploads/<?= htmlspecialchars($aktivitas['gambar']) ?>" target="_blank">
                                                <img src="../uploads/<?= htmlspecialchars($aktivitas['gambar']) ?>" width="60" height="60" style="object-fit: cover; border-radius: 6px; border: 1px solid #eee;" alt="gambar aktivitas">
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted" style="min-width:60px; text-align:center;">Tidak ada</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-success" title="Edit Status" onclick="updateStatus(<?= $aktivitas['id'] ?>, '<?= $aktivitas['status'] ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php 
                                endwhile;
                            else:
                            ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <i class="fas fa-inbox" style="font-size: 3rem; color: #d1d5db; margin-bottom: 1rem;"></i>
                                    <p class="text-muted">Tidak ada data aktivitas yang ditemukan.</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal untuk Update Status -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Status Aktivitas</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="aktivitas_id" id="aktivitas_id">
                        <div class="form-group">
                            <label>Status:</label>
                            <select name="new_status" class="form-control" required>
                                <option value="belum_mulai">Belum Mulai</option>
                                <option value="sedang_dikerjakan">Sedang Dikerjakan</option>
                                <option value="selesai">Selesai</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Komentar Pembimbing:</label>
                            <textarea name="komentar" class="form-control" rows="3" placeholder="Berikan komentar atau feedback..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
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
                    <a href="../logout.php" class="btn btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function updateStatus(id, currentStatus) {
            document.getElementById('aktivitas_id').value = id;
            document.querySelector('select[name="new_status"]').value = currentStatus;
            const modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
            modal.show();
        }

        // Sidebar Toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
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

        // Auto refresh data setiap 60 detik
        setInterval(function() {
            location.reload();
        }, 60000);
    </script>
</body>
</html>