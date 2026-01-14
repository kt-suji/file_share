<?php
require 'config.php'; // config.php を読み込む

if (!isset($_GET['id'])) {
    http_response_code(404);
    exit;
}
$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
$stmt->execute([$id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file || !file_exists($file['filepath'])) {
    http_response_code(404);
    exit;
}

// --- 認証チェック (削除) ---
// $require_password = !empty($file['password']);
// $is_authenticated = isset($_SESSION['authenticated_file_id']) && $_SESSION['authenticated_file_id'] == $file['id'];
// if ($require_password && !$is_authenticated) {
//     http_response_code(403); 
//     echo "認証が必要です。";
//     exit;
// }
// --- 認証チェック終了 ---

// 誰でもファイルが表示されます
$finfo = mime_content_type($file['filepath']);
header("Content-Type: $finfo");
header('Content-Length: ' . filesize($file['filepath']));
ob_clean();
flush();
readfile($file['filepath']);
exit;
?>