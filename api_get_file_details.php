<?php
require 'config.php';
header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'IDが指定されていません。']);
    exit;
}
$id = $_GET['id'];

try {
    $stmt = $pdo->prepare(
        "SELECT id, title, description, filename, 
                (password IS NOT NULL AND password != '') AS has_password, 
                uploaded_at
         FROM files 
         WHERE id = ?"
    );
    $stmt->execute([$id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($file) {
        echo json_encode($file);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'ファイルが見つかりません。']);
    }

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Database query failed']);
}
?>