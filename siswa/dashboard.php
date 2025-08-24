<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'siswa') {
    header("Location: ../login.php");
    exit;
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
    <title>Dashboard Siswa</title>
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css" rel="stylesheet">
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
        .card-header {
            background: #f3e8ff;
        }
        .sidebar {
            transition: width 0.2s;
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
            <li class="nav-item active">
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

            <li class="nav-item">
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
            <hr class="sidebar-divider d-none d-md-block">
<li class="nav-item">
    <a class="nav-link" href="#" id="logoutBtn">
        <i class="fas fa-fw fa-sign-out-alt"></i>
        <span>Logout</span>
    </a>
</li>
            <!-- Sidebar Toggler (Sidebar) -->
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
                    <span class="navbar-brand mb-0 h1">Dashboard Siswa</span>
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
                        <div class="alert alert-success"><?= $msg ?></div>
                    <?php endif; ?>

                    <!-- Presensi -->
                   <div class="card shadow mb-4" id="presensi">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-calendar-check"></i> Presensi Masuk
        </h6>
    </div>
    <div class="card-body">
        <form action="presensi.php" method="get">
            <button type="submit" class="btn btn-primary">Presensi Masuk</button>
        </form>
    </div>
</div>

                    <!-- Aktivitas -->
                    <div class="card shadow mb-4" id="aktivitas">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-tasks"></i> Aktivitas Hari Ini</h6>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <div class="form-group">
                                    <textarea name="deskripsi" class="form-control" placeholder="Aktivitas hari ini" required></textarea>
                                </div>
                                <button name="aktivitas" class="btn btn-success">Simpan Aktivitas</button>
                            </form>
                        </div>
                    </div>

                    <!-- Pengumuman -->
                    <div class="card shadow mb-4" id="pengumuman">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-info"><i class="fas fa-bullhorn"></i> Pengumuman</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($pengumuman->num_rows > 0): ?>
                                <ul>
                                    <?php while($row = $pengumuman->fetch_assoc()): ?>
                                        <li>
                                            <strong><?= htmlspecialchars($row['judul']) ?></strong> (<?= $row['tanggal'] ?>)<br>
                                            <?= nl2br(htmlspecialchars($row['isi'])) ?>
                                        </li>
                                    <?php endwhile; ?>
                                </ul>
                            <?php else: ?>
                                <p>Tidak ada pengumuman.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
                <!-- /.container-fluid -->
            </div>
        </div>
        <!-- End of Content Wrapper -->
    </div>
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

    <!-- End of Page Wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
<script>
    $('#logoutBtn').on('click', function(e) {
        e.preventDefault();
        $('#logoutModal').modal('show');
    });
</script>
</body>
</html>