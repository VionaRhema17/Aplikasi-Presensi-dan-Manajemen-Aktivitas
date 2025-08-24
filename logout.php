<?php
session_start();
session_destroy(); // Hapus semua session

// Redirect ke halaman index.php setelah logout
header("Location: login.php");
exit;
?>
