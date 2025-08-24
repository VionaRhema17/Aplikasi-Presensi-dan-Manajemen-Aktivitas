<?php
//load.env
$env = parse_ini_file(__DIR__ . "/../.env");

$host = "localhost";
$user = "root";
$pass = ""; // default kosong di Laragon
$db   = "magang_edusoft"; // pastikan ini sesuai

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
?>
