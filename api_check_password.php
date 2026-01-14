<?php
require 'config.php';
header('Content-Type: application/json');

// JSONでPOSTされたデータを受け取る
$json_data = json_decode(file_get_contents('php://input'), true);

if (!isset($json_data['id']) || !isset($json_data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'IDまたはパスワードがありません。']);
    exit;
}

$id = $json_data['id'];
$password = $json_data['password'];

try {
    $stmt = $pdo->prepare("SELECT password FROM files WHERE id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($file && password_verify($password, $file['password'])) {
        // 認証成功
        echo json_encode(['success' => true]);
    } else {
        // 認証失敗
        echo json_encode(['success' => false, 'error' => 'パスワードが間違っています。']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed']);
}
?>