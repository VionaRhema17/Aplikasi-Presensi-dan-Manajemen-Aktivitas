<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit;
}

$role = $_SESSION['user']['role'];
$user_id = $_SESSION['user']['id'];
$msg = '';

// Determine the interface based on role
if ($role === 'siswa') {
    $tanggal = date('Y-m-d');

    // Fetch aktivitas hari ini
    $query = "SELECT a.*, u.nama AS nama_lengkap 
              FROM aktivitas a 
              JOIN users u ON a.user_id = u.id 
              WHERE a.user_id = ? AND a.tanggal = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $user_id, $tanggal);
    $stmt->execute();
    $result = $stmt->get_result();
    $activities = $result->fetch_all(MYSQLI_ASSOC);

    // Proses tambah aktivitas
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['aktivitas'])) {
        $judul_aktivitas = $_POST['judul_aktivitas'] ?? '';
        $deskripsi = $_POST['deskripsi'] ?? '';
        $status = $_POST['status'] ?? 'sedang_dikerjakan';

        // Cek apakah sudah ada aktivitas hari ini
        $cek_query = "SELECT id FROM aktivitas WHERE user_id = ? AND tanggal = ?";
        $cek_stmt = $conn->prepare($cek_query);
        $cek_stmt->bind_param("is", $user_id, $tanggal);
        $cek_stmt->execute();
        $cek_result = $cek_stmt->get_result();

        if ($cek_result->num_rows > 0) {
            $_SESSION['msg'] = "Anda sudah mengisi aktivitas hari ini. Silakan edit jika ingin mengubah.";
            header("Location: aktivitas.php");
            exit;
        }

        // Upload gambar jika ada
        $gambar = null;
        if (!empty($_FILES['gambar']['name']) && $_FILES['gambar']['error'] == 0) {
            $target_dir = "../uploads/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('img_') . '.' . $ext;
            $target_file = $target_dir . $filename;

            if (move_uploaded_file($_FILES['gambar']['tmp_name'], $target_file)) {
                $gambar = $filename;
            }
        }

        // Simpan aktivitas
        $insert_query = "INSERT INTO aktivitas (user_id, tanggal, judul_aktivitas, deskripsi, status, jam_submit, gambar) 
                         VALUES (?, ?, ?, ?, ?, NOW(), ?)";
        $stmt_insert = $conn->prepare($insert_query);
        $stmt_insert->bind_param("isssss", $user_id, $tanggal, $judul_aktivitas, $deskripsi, $status, $gambar);
        if ($stmt_insert->execute()) {
            $_SESSION['msg'] = "Aktivitas berhasil disimpan!";
        } else {
            $_SESSION['msg'] = "Gagal menyimpan aktivitas.";
        }
        header("Location: aktivitas.php");
        exit;
    }

    // Proses edit aktivitas
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['aktivitas_id'])) {
    $aktivitas_id = $_POST['aktivitas_id'];
    $judul_aktivitas = $_POST['judul_aktivitas'] ?? '';
    $deskripsi = $_POST['deskripsi'] ?? '';
    $status = $_POST['status'] ?? 'sedang_dikerjakan';
    $gambar = null;

    // Upload gambar baru jika ada
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
        $target_dir = "../uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $ext;
        $target_file = $target_dir . $filename;

        if (move_uploaded_file($_FILES['gambar']['tmp_name'], $target_file)) {
            $gambar = $filename;
        }
    }

    $update_query = "UPDATE aktivitas SET judul_aktivitas = ?, deskripsi = ?, status = ?, gambar = COALESCE(?, gambar), jam_submit = NOW() 
                     WHERE id = ? AND user_id = ?";
    $stmt_update = $conn->prepare($update_query);
    $stmt_update->bind_param("ssssii", $judul_aktivitas, $deskripsi, $status, $gambar, $aktivitas_id, $user_id);
    if ($stmt_update->execute()) {
        $_SESSION['msg'] = "Aktivitas berhasil diperbarui!";
    } else {
        $_SESSION['msg'] = "Gagal memperbarui aktivitas.";
    }
    header("Location: aktivitas.php");
    exit;
}

}



if ($role === 'pembimbing') {
    // Handle filter
    $filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d');
    $filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
    $filter_siswa = isset($_GET['filter_siswa']) ? $_GET['filter_siswa'] : '';

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
                    COUNT(*) as total_siswa,
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

    // Query untuk mendapatkan daftar siswa yang belum submit aktivitas hari ini
    $no_activity_query = "SELECT u.nama
                          FROM users u 
                          LEFT JOIN aktivitas a ON u.id = a.user_id AND a.tanggal = ?
                          WHERE u.role = 'siswa' AND a.id IS NULL";
    $no_activity_stmt = $conn->prepare($no_activity_query);
    $no_activity_stmt->bind_param("s", $today);
    $no_activity_stmt->execute();
    $no_activity_result = $no_activity_stmt->get_result();

    // Handle update status aktivitas
    if (isset($_POST['update_status'])) {
        $aktivitas_id = $_POST['aktivitas_id'];
        $new_status = $_POST['new_status'];
        $komentar = $_POST['komentar'];
        
        $update_query = "UPDATE aktivitas SET status = ?, komentar_pembimbing = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ssi", $new_status, $komentar, $aktivitas_id);
        
        if ($update_stmt->execute()) {
            $msg = '<div class="alert alert-success">Status aktivitas berhasil diupdate!</div>';
        } else {
            $msg = '<div class="alert alert-danger">Gagal mengupdate status aktivitas!</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $role === 'siswa' ? 'Dashboard Siswa' : 'Monitoring Aktivitas - Pembimbing'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css" rel="stylesheet">
    <style>
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
        .card-header {
            background: #f3e8ff;
        }
        .stats-card {
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .table-hover tbody tr:hover {
            background-color: #f8f9fc;
        }
        .badge-status {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .activity-description {
            max-width: 300px;
            word-wrap: break-word;
        }
        .modal-backdrop {
            z-index: 1040;
        }
        .modal {
            z-index: 1050;
        }
        .filter-section {
            background: #f8f9fc;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion toggled" id="accordionSidebar">
            <?php if ($role === 'siswa'): ?>
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
                <li class="nav-item">
                    <a class="nav-link" href="presensi.php">
                        <i class="fas fa-fw fa-calendar-check"></i>
                        <span>Presensi</span>
                    </a>
                </li>
                <li class="nav-item active">
                    <a class="nav-link" href="aktivitas.php">
                        <i class="fas fa-fw fa-tasks"></i>
                        <span>Aktivitas</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="pengumuman.php">
                        <i class="fas fa-fw fa-bullhorn"></i>
                        <span>Pengumuman</span>
                    </a>
                </li>
            <?php elseif ($role === 'pembimbing'): ?>
                <a class="sidebar-brand d-flex align-items-center justify-content-center" href="#">
                    <div class="sidebar-brand-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="sidebar-brand-text mx-3">Pembimbing</div>
                </a>
                <hr class="sidebar-divider my-0">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-fw fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <hr class="sidebar-divider">
                <li class="nav-item">
                    <a class="nav-link" href="presensi.php">
                        <i class="fas fa-fw fa-calendar-check"></i>
                        <span>Monitoring Presensi</span>
                    </a>
                </li>
                <li class="nav-item active">
                    <a class="nav-link" href="aktivitas.php">
                        <i class="fas fa-fw fa-tasks"></i>
                        <span>Monitoring Aktivitas</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="pengumuman.php">
                        <i class="fas fa-fw fa-bullhorn"></i>
                        <span>Pengumuman</span>
                    </a>
                </li>
            <?php endif; ?>
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
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>
                    <span class="navbar-brand mb-0 h1"><?php echo $role === 'siswa' ? 'Dashboard Siswa' : 'Monitoring Aktivitas Siswa'; ?></span>
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
                <div class="container-fluid">
                    <?= $msg ?>
                    <?php
if (isset($_SESSION['msg'])) {
    echo '<div class="alert alert-info">' . $_SESSION['msg'] . '</div>';
    unset($_SESSION['msg']);
}
?>

                   <?php if ($role === 'siswa'): ?>
    <div class="card shadow mb-4" id="aktivitas">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-tasks"></i> Aktivitas Hari Ini</h6>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <input type="text" name="judul_aktivitas" class="form-control" placeholder="Masukkan judul aktivitas" required>
                <div class="form-group">
                    <label for="deskripsi">Deskripsi</label>
                    <textarea name="deskripsi" class="form-control" placeholder="Deskripsi aktivitas" required></textarea>
                </div>
                <div class="form-group">
                    <label for="status">Status Aktivitas</label>
                    <select name="status" class="form-control" required>
                        <option value="belum_mulai">Belum Mulai</option>
                        <option value="sedang_dikerjakan">Sedang Dikerjakan</option>
                        <option value="selesai">Selesai</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="gambar">Upload Gambar (Opsional)</label>
                    <input type="file" name="gambar" class="form-control-file" accept="image/*">
                </div>
                <button name="aktivitas" class="btn btn-success">Simpan Aktivitas</button>
            </form>
        </div>
    </div>
    <!-- Tabel aktivitas hari ini -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list"></i> Aktivitas Anda Hari Ini</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
               <table class="table table-bordered">
    <thead>
        <tr>
            <th>No</th>
            <th>Judul</th>
            <th>Deskripsi</th>
            <th>Status</th>
            <th>Komentar Pembimbing</th>
            <th>Jam Submit</th>
            <th>Edit Status</th>
            <th>Gambar</th> <!-- HARUS ditambahkan -->
        </tr>
    </thead>
    <tbody>
        <?php $no = 1; foreach ($activities as $aktivitas): ?>
        <tr>
            <td><?= $no++ ?></td>
            <td><?= htmlspecialchars($aktivitas['judul_aktivitas']) ?></td>
            <td><?= htmlspecialchars($aktivitas['deskripsi']) ?></td>
            <td>
                <?php
                $status_class = '';
                $status_text = '';
                switch ($aktivitas['status']) {
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
                <span id="status-badge-<?= $aktivitas['id'] ?>" class="badge <?= $status_class ?> badge-status"><?= $status_text ?></span>
            </td>
            <td><?= htmlspecialchars($aktivitas['komentar_pembimbing'] ?? '-') ?></td>
            <td><?= $aktivitas['jam_submit'] ? date('H:i', strtotime($aktivitas['jam_submit'])) : '-' ?></td>
            <td>
                <button class="btn btn-sm btn-success" onclick="editStatusSiswa(<?= $aktivitas['id'] ?>, '<?= $aktivitas['status'] ?>')">
                    <i class="fas fa-edit"></i> Edit Status
                </button>
            </td>
            <td>
                <?php if (!empty($aktivitas['gambar'])): ?>
                    <a href="../uploads/<?= htmlspecialchars($aktivitas['gambar']) ?>" target="_blank">
                        <img src="../uploads/<?= htmlspecialchars($aktivitas['gambar']) ?>" width="60" height="60" style="object-fit: cover;" alt="gambar aktivitas">
                    </a>
                <?php else: ?>
                    <span class="text-muted">Tidak ada</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

            </div>
        </div>
    </div>
    <!-- Modal Edit Status Siswa -->
    <div class="modal fade" id="editStatusSiswaModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form method="POST" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Status Aktivitas</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>×</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="aktivitas_id" id="aktivitas_id_siswa">
                    <div class="form-group">
                        <label>Status:</label>
                        <select name="new_status" class="form-control" id="status_siswa" required>
                            <option value="belum_mulai">Belum Mulai</option>
                            <option value="sedang_dikerjakan">Sedang Dikerjakan</option>
                            <option value="selesai">Selesai</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                  <button type="button" class="btn btn-primary" onclick="submitStatusSiswa()">Update Status</button>
                </div>
            </form>
        </div>
    </div>
                    <?php elseif ($role === 'pembimbing'): ?>
                        <!-- Statistics Cards -->
                        <div class="row mb-4">
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card border-left-primary shadow h-100 py-2 stats-card">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                    Total Aktivitas Hari Ini
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    <?= $stats['total_aktivitas_hari_ini'] ?? 0 ?>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-tasks fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card border-left-success shadow h-100 py-2 stats-card">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                    Aktivitas Selesai
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    <?= $stats['aktivitas_selesai'] ?? 0 ?>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card border-left-warning shadow h-100 py-2 stats-card">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                    Sedang Dikerjakan
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    <?= $stats['aktivitas_progress'] ?? 0 ?>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card border-left-danger shadow h-100 py-2 stats-card">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                                    Belum Mulai
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    <?= $stats['aktivitas_belum_mulai'] ?? 0 ?>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filter Section -->
                        <div class="filter-section">
                            <form method="GET" class="row">
                                <div class="col-md-3">
                                    <label class="form-label">Tanggal:</label>
                                    <input type="date" name="filter_date" class="form-control" value="<?= $filter_date ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status:</label>
                                    <select name="filter_status" class="form-control">
                                        <option value="">Semua Status</option>
                                        <option value="belum_mulai" <?= $filter_status == 'belum_mulai' ? 'selected' : '' ?>>Belum Mulai</option>
                                        <option value="sedang_dikerjakan" <?= $filter_status == 'sedang_dikerjakan' ? 'selected' : '' ?>>Sedang Dikerjakan</option>
                                        <option value="selesai" <?= $filter_status == 'selesai' ? 'selected' : '' ?>>Selesai</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Siswa:</label>
                                    <select name="filter_siswa" class="form-control">
                                        <option value="">Semua Siswa</option>
                                        <?php while ($siswa = $siswa_result->fetch_assoc()): ?>
                                            <option value="<?= $siswa['id'] ?>" <?= $filter_siswa == $siswa['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($siswa['nama']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label"> </label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-filter"></i> Filter
                                        </button>
                                        <a href="aktivitas.php" class="btn btn-secondary">
                                            <i class="fas fa-refresh"></i> Reset
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Aktivitas Table -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-tasks"></i> Data Aktivitas Siswa
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="dataTable">
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>Judul</th>
                                                <th>Deskripsi</th>
                                                <th>Status</th>
                                                <th>Komentar Pembimbing</th>
                                                <th>Jam Submit</th>
                                                <th>Aksi</th>
                                                <th>Gambar</th> <!-- Tambahkan ini -->
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
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
                                                    <span class="badge <?= $status_class ?> badge-status"><?= $status_text ?></span>
                                                </td>
                                                <td><?= $aktivitas['jam_submit'] ? date('H:i', strtotime($aktivitas['jam_submit'])) : '-' ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" onclick="viewActivity(<?= $aktivitas['id'] ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-success" onclick="updateStatus(<?= $aktivitas['id'] ?>, '<?= $aktivitas['status'] ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Siswa Belum Submit Aktivitas -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-warning">
                                    <i class="fas fa-exclamation-triangle"></i> Tidak Beraktivitas Hari Ini
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if ($no_activity_result->num_rows > 0): ?>
                                    <div class="row">
                                        <?php while ($student = $no_activity_result->fetch_assoc()): ?>
                                            <div class="col-md-4 mb-2">
                                                <span class="badge badge-warning p-2">
                                                    <i class="fas fa-user"></i> <?= htmlspecialchars($student['nama']) ?>
                                                </span>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-success">
                                        <i class="fas fa-check-circle"></i> Semua siswa sudah submit aktivitas hari ini!
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal untuk Update Status -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Status Aktivitas</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>×</span>
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

    <!-- Modal untuk View Activity -->
    <div class="modal fade" id="viewActivityModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Aktivitas</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>×</span>
                    </button>
                </div>
                <div class="modal-body" id="activityDetails">
                    <!-- Content will be loaded here -->
                </div>
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

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    
    <script>
        function updateStatus(id, currentStatus) {
            document.getElementById('aktivitas_id').value = id;
            document.querySelector('select[name="new_status"]').value = currentStatus;
            $('#updateStatusModal').modal('show');
        }

        function viewActivity(id) {
            $.ajax({
                url: 'get_activity_details.php',
                method: 'POST',
                data: {id: id},
                success: function(response) {
                    $('#activityDetails').html(response);
                    $('#viewActivityModal').modal('show');
                },
                error: function() {
                    alert('Gagal memuat detail aktivitas');
                }
            });
        }

        setInterval(function() {
            location.reload();
        }, 60000);
    </script>
    <script>
        function editStatusSiswa(id, currentStatus) {
            document.getElementById('aktivitas_id_siswa').value = id;
            document.getElementById('status_siswa').value = currentStatus;
            $('#editStatusSiswaModal').modal('show');
        }
    </script>
    <script>
function submitStatusSiswa() {
    var id = $('#aktivitas_id_siswa').val();
    var status = $('#status_siswa').val();

    $.ajax({
        url: 'update_status_siswa.php',
        type: 'POST',
        data: {
            aktivitas_id: id,
            new_status: status
        },
        success: function(response) {
            const res = JSON.parse(response);
            if (res.success) {
                $('#editStatusSiswaModal').modal('hide');
                // Update status badge di baris yang diedit
                const badge = document.querySelector(`#status-badge-${id}`);
                if (badge) {
                    badge.className = 'badge badge-status ' + getStatusClass(status);
                    badge.innerText = getStatusText(status);
                }
                // Optional: tampilkan pesan sukses
                showSuccessToast('Status berhasil diperbarui!');
            } else {
                alert(res.message);
            }
        },
        error: function() {
            alert('Gagal mengirim permintaan.');
        }
    });
}

// Fungsi toast sederhana
function showSuccessToast(msg) {
    let toast = document.createElement('div');
    toast.className = 'alert alert-success fixed-top m-3';
    toast.style.zIndex = 9999;
    toast.innerText = msg;
    document.body.appendChild(toast);
    setTimeout(() => { toast.remove(); }, 2000);
}

function getStatusClass(status) {
    switch(status) {
        case 'belum_mulai': return 'badge-danger';
        case 'sedang_dikerjakan': return 'badge-warning';
        case 'selesai': return 'badge-success';
        default: return 'badge-secondary';
    }
}

function getStatusText(status) {
    switch(status) {
        case 'belum_mulai': return 'Belum Mulai';
        case 'sedang_dikerjakan': return 'Sedang Dikerjakan';
        case 'selesai': return 'Selesai';
        default: return '-';
    }
}
</script>
<script>
    $('#logoutBtn').on('click', function(e) {
        e.preventDefault();
        $('#logoutModal').modal('show');
    });
</script>
</body>
</html>