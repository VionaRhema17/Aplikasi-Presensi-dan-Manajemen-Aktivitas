<?php
session_start();
include '../config/db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user']['id'] ?? 0;
$tanggal_hari_ini = date('Y-m-d');

// Total presensi hari ini
$presensi_sql = "SELECT COUNT(*) AS total FROM presensi WHERE tanggal = '$tanggal_hari_ini'";
$presensi_result = $conn->query($presensi_sql);
$totalPresensi = $presensi_result->fetch_assoc()['total'] ?? 0;

// Total aktivitas hari ini
$aktivitas_sql = "SELECT COUNT(*) AS total FROM aktivitas WHERE tanggal = '$tanggal_hari_ini'";
$aktivitas_result = $conn->query($aktivitas_sql);
$totalAktivitas = $aktivitas_result->fetch_assoc()['total'] ?? 0;

// Jumlah siswa izin dan alpha
$izin_sql = "SELECT COUNT(*) AS total FROM presensi WHERE tanggal = '$tanggal_hari_ini' AND status IN ('izin', 'alpha')";
$izin_result = $conn->query($izin_sql);
$siswaIzin = $izin_result->fetch_assoc()['total'] ?? 0;

// Jumlah siswa tanpa aktivitas
$no_aktivitas_sql = "
    SELECT COUNT(*) AS total 
    FROM users 
    WHERE role = 'siswa' AND id NOT IN (
        SELECT DISTINCT siswa_id 
        FROM aktivitas 
        WHERE tanggal = '$tanggal_hari_ini'
    )
";
$no_aktivitas_result = $conn->query($no_aktivitas_sql);
$siswaTanpaAktivitas = $no_aktivitas_result->fetch_assoc()['total'] ?? 0;

// Output JSON
echo json_encode([
    'presensi' => $totalPresensi,
    'aktivitas' => $totalAktivitas,
    'izin' => $siswaIzin,
    'tanpaAktivitas' => $siswaTanpaAktivitas
]);
?>
