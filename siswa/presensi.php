<?php
session_start();
include '../config/db.php';

// Set timezone to WIB (Asia/Jakarta)
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'siswa') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user']['id'];
$msg = '';

// Handle presensi masuk
if (isset($_POST['presensi_masuk'])) {
    $tanggal = date('Y-m-d');
    $jam_masuk = date('H:i:s');
    
    // Cek apakah sudah presensi hari ini
    $check_query = "SELECT * FROM presensi WHERE user_id = ? AND tanggal = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("is", $user_id, $tanggal);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $msg = 'Anda sudah melakukan presensi hari ini!';
    } else {
        $insert_query = "INSERT INTO presensi (user_id, tanggal, jam_masuk, status) VALUES (?, ?, ?, 'hadir')";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("iss", $user_id, $tanggal, $jam_masuk);
        
        if ($insert_stmt->execute()) {
            $msg = 'Presensi masuk berhasil dicatat!';
        } else {
            $msg = 'Error: Gagal mencatat presensi masuk.';
        }
        $insert_stmt->close();
    }
    $check_stmt->close();
}

// Handle presensi keluar
if (isset($_POST['presensi_keluar'])) {
    $tanggal = date('Y-m-d');
    $jam_keluar = date('H:i:s');
    
    // Update jam keluar untuk hari ini
    $update_query = "UPDATE presensi SET jam_keluar = ? WHERE user_id = ? AND tanggal = ? AND jam_keluar IS NULL AND status = 'hadir'";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("sis", $jam_keluar, $user_id, $tanggal);
    
    if ($update_stmt->execute()) {
        if ($update_stmt->affected_rows > 0) {
            $msg = 'Presensi keluar berhasil dicatat!';
        } else {
            $msg = 'Anda belum melakukan presensi masuk hari ini atau sudah presensi keluar!';
        }
    } else {
        $msg = 'Error: Gagal mencatat presensi keluar.';
    }
    $update_stmt->close();
}

// Handle presensi izin/alpha
if (isset($_POST['presensi_status'])) {
    $tanggal = date('Y-m-d');
    $status = $_POST['status'];

    // Cek apakah sudah presensi hari ini
    $check_query = "SELECT * FROM presensi WHERE user_id = ? AND tanggal = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("is", $user_id, $tanggal);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        $msg = 'Anda sudah melakukan presensi hari ini!';
    } else {
        // Baru: inisialisasi dan eksekusi insert statement untuk izin/alpha
        $insert_query = "INSERT INTO presensi (user_id, tanggal, status) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("iss", $user_id, $tanggal, $status);

        if ($insert_stmt->execute()) {
            $msg = ucfirst($status) . ' berhasil dicatat!';
        } else {
            $msg = 'Error: Gagal mencatat ' . $status . '.';
        }
        $insert_stmt->close();
    }
    $check_stmt->close();
}


// Ambil data presensi siswa
$presensi_query = "SELECT * FROM presensi WHERE user_id = ? ORDER BY tanggal DESC LIMIT 30";
$presensi_stmt = $conn->prepare($presensi_query);
$presensi_stmt->bind_param("i", $user_id);
$presensi_stmt->execute();
$presensi_result = $presensi_stmt->get_result();

// Cek status presensi hari ini
$today = date('Y-m-d');
$today_query = "SELECT * FROM presensi WHERE user_id = ? AND tanggal = ?";
$today_stmt = $conn->prepare($today_query);
$today_stmt->bind_param("is", $user_id, $today);
$today_stmt->execute();
$today_result = $today_stmt->get_result();
$today_presensi = $today_result->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presensi Siswa</title>
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Custom purple theme */
        .bg-gradient-primary {
            background-color: #6f42c1 !important;
            background-image: linear-gradient(180deg,#7c3aed 10%,#6f42c1 100%) !important;
        }
        .sidebar .nav-item.active .nav-link, 
        .sidebar .nav-item .nav-link:hover {
            background-color: #7c3aed !important;
        }
        .btn-primary {
            background-color: #7c3aed;
            border-color: #7c3aed;
        }
        .btn-primary:hover {
            background-color: #6f42c1;
            border-color: #6f42c1;
        }
        .btn-success {
            background-color: #10b981;
            border-color: #10b981;
        }
        .btn-success:hover {
            background-color: #059669;
            border-color: #059669;
        }
        .btn-warning {
            background-color: #f59e0b;
            border-color: #f59e0b;
        }
        .btn-warning:hover {
            background-color: #d97706;
            border-color: #d97706;
        }
        .btn-danger {
            background-color: #ef4444;
            border-color: #ef4444;
        }
        .btn-danger:hover {
            background-color: #dc2626;
            border-color: #dc2626;
        }
        .card-header {
            background: #f3e8ff;
        }
        .sidebar {
            transition: width 0.2s;
        }
        .status-hadir {
            color: #10b981;
            font-weight: bold;
        }
        .status-alpha {
            color: #ef4444;
            font-weight: bold;
        }
        .status-izin {
            color: #f59e0b;
            font-weight: bold;
        }
        .presensi-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .time-display {
            font-size: 1.2rem;
            font-weight: bold;
            color: #6f42c1;
        }
    </style>
</head>
<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion toggled" id="accordionSidebar">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="#">
                <div class="sidebar-brand-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="sidebar-brand-text mx-3">Siswa</div>
            </a>
            <hr class="sidebar-divider my-0">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <hr class="sidebar-divider">
            <li class="nav-item active">
                <a class="nav-link" href="presensi.php">
                    <i class="fas fa-fw fa-calendar-check"></i>
                    <span>Presensi</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="aktivitas.php">
                    <i class="fas fa-fw fa-tasks"></i>
                    <span>Aktivitas</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#pengumuman">
                    <i class="fas fa-fw fa-bullhorn"></i>
                    <span>Pengumuman</span>
                </a>
            </li>
            <hr class="sidebar-divider d-none d-md-block">
           <li class="nav-item">
    <a class="nav-link" href="#" id="logoutBtn">
        <i class="fas fa-fw fa-sign-out-alt"></i>
        <span>Logout</span>
    </a>
</li>
            <div class="text-center d-none d-md-inline">
                <button class="rounded-circle border-0" id="sidebarToggle"></button>
            </div>
        </ul>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>
                    <span class="navbar-brand mb-0 h1">Presensi Siswa</span>
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                                    <?= isset($_SESSION['user']['nama']) ? htmlspecialchars($_SESSION['user']['nama']) : 'Pengguna' ?>
                                </span>
                                <i class="fas fa-user-circle fa-lg"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <?php if (!empty($msg)): ?>
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($msg) ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Info Waktu Saat Ini -->
                    <div class="presensi-info">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-calendar-alt"></i> Tanggal: <?= date('d/m/Y') ?></h6>
                                <h6><i class="fas fa-clock"></i> Waktu: <span class="time-display" id="current-time"></span> WIB</h6>
                            </div>
                            <div class="col-md-6">
                                <?php if ($today_presensi): ?>
                                    <h6><i class="fas fa-sign-in-alt text-success"></i> Masuk: <?= $today_presensi['jam_masuk'] ? date('H:i', strtotime($today_presensi['jam_masuk'])) : '-' ?> WIB</h6>
                                    <h6><i class="fas fa-sign-out-alt text-danger"></i> Keluar: <?= $today_presensi['jam_keluar'] ? date('H:i', strtotime($today_presensi['jam_keluar'])) : '-' ?> WIB</h6>
                                    <h6><i class="fas fa-info-circle"></i> Status: <span class="status-<?= $today_presensi['status'] ?>"><?= ucfirst($today_presensi['status']) ?></span></h6>
                                <?php else: ?>
                                    <h6 class="text-muted">Belum melakukan presensi hari ini</h6>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Presensi -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-sign-in-alt"></i> Presensi Masuk</h6>
                                </div>
                                <div class="card-body">
                                    <form method="post">
                                        <button name="presensi_masuk" class="btn btn-primary btn-block" 
                                                <?= $today_presensi ? 'disabled' : '' ?>>
                                            <i class="fas fa-sign-in-alt"></i> 
                                            <?= $today_presensi ? 'Sudah Presensi Masuk' : 'Presensi Masuk' ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-sign-out-alt"></i> Presensi Keluar</h6>
                                </div>
                                <div class="card-body">
                                    <form method="post">
                                        <button name="presensi_keluar" class="btn btn-success btn-block"
                                                <?= (!$today_presensi || $today_presensi['jam_keluar'] || $today_presensi['status'] !== 'hadir') ? 'disabled' : '' ?>>
                                            <i class="fas fa-sign-out-alt"></i> 
                                            <?= !$today_presensi ? 'Presensi Masuk Dulu' : 
                                                ($today_presensi['jam_keluar'] ? 'Sudah Presensi Keluar' : 
                                                ($today_presensi['status'] !== 'hadir' ? 'Status Bukan Hadir' : 'Presensi Keluar')) ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-warning"><i class="fas fa-file-alt"></i> Izin/Alpha</h6>
                                </div>
                                <div class="card-body">
                                    <form method="post">
                                        <div class="form-group">
                                            <select name="status" class="form-control" required
                                                    <?= $today_presensi ? 'disabled' : '' ?>>
                                                <option value="">Pilih Status</option>
                                                <option value="izin">Izin</option>
                                                <option value="alpha">Alpha</option>
                                            </select>
                                        </div>
                                        <button name="presensi_status" class="btn btn-warning btn-block"
                                                <?= $today_presensi ? 'disabled' : '' ?>>
                                            <i class="fas fa-file-alt"></i> Submit Status
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Riwayat Presensi -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history"></i> Riwayat Presensi (30 Hari Terakhir)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped" id="dataTable" width="100%" cellspacing="0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>No</th>
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
                                                    <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                                    <td>
                                                        <?= $row['jam_masuk'] ? date('H:i', strtotime($row['jam_masuk'])) : '-' ?> WIB
                                                    </td>
                                                    <td>
                                                        <?= $row['jam_keluar'] ? date('H:i', strtotime($row['jam_keluar'])) : '-' ?> WIB
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
                                                <td colspan="7" class="text-center">Belum ada data presensi</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <!-- Modal Konfirmasi Logout -->
<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-gradient-primary text-white">
        <h5 class="modal-title" id="logoutModalLabel"><i class="fas fa-sign-out-alt"></i> Konfirmasi Logout</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body text-center">
        <p>Apakah Anda yakin ingin logout dari akun ini?</p>
      </div>
      <div class="modal-footer justify-content-center">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <a href="../logout.php" class="btn btn-danger">Logout</a>
      </div>
    </div>
  </div>
</div>
                </div>
                <!-- /.container-fluid -->
            </div>
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <script>
        // Update waktu real-time dalam WIB
        function updateTime() {
            const now = new Date().toLocaleString('en-US', {
                timeZone: 'Asia/Jakarta',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            });
            document.getElementById('current-time').textContent = now;
        }
        
        // Update setiap detik
        setInterval(updateTime, 1000);
        updateTime(); // Jalankan sekali saat load
    </script>
    <script>
    $('#logoutBtn').on('click', function(e) {
        e.preventDefault();
        $('#logoutModal').modal('show');
    });
</script>
</body>
</html>