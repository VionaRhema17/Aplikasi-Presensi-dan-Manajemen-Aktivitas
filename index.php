<?php
header("Location: login.php");
exit;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Presensi & Aktivitas Harian</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <?php if (isset($_SESSION['user'])): ?>
        <div class="alert alert-success text-center">
            Anda login sebagai <strong><?= $_SESSION['user']['nama'] ?></strong> (<?= $_SESSION['user']['role'] ?>)
        </div>
        <div class="text-center">
            <a href="<?= $_SESSION['user']['role'] ?>/dashboard.php" class="btn btn-primary">Ke Dashboard</a>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>
    <?php else: ?>
        <div class="d-flex justify-content-center gap-3">
            <a href="login.php" class="btn btn-success btn-lg">Login</a>
            <a href="register.php" class="btn btn-outline-primary btn-lg">Daftar</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
