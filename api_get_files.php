<?php
require 'config.php'; // DB接続と設定を読み込む

try {
    // データを取得 (パスワード自体ではなく、パスワードの有無(1/0)を取得)
    $stmt = $pdo->query(
        "SELECT id, title, description, filename, 
                (password IS NOT NULL AND password != '') AS has_password, 
                uploaded_at
         FROM files 
         ORDER BY uploaded_at DESC"
    );
    
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // PHPの結果をJSON形式で出力
    header('Content-Type: application/json');
    echo json_encode($files);

} catch (PDOException $e) {
    // エラーが発生した場合
    header('Content-Type: application/json', true, 500); // 500 Internal Server Error
    echo json_encode(['error' => 'Database query failed']);
}
?>