<?php
session_start();
include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aktivitas_id'], $_POST['new_status'])) {
    $user_id = $_SESSION['user']['id'];
    $aktivitas_id = $_POST['aktivitas_id'];
    $new_status = $_POST['new_status'];

    $update_query = "UPDATE aktivitas SET status = ? WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sii", $new_status, $aktivitas_id, $user_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Status berhasil diubah']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengubah status']);
    }

    exit;
}
?>
