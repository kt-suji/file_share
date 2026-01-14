<?php
require 'config.php'; // 設定ファイル (NASのパスワード入り) を読み込む
header('Content-Type: application/json');

// JSONでPOSTされたデータを受け取る
$json_data = json_decode(file_get_contents('php://input'), true);

if (!isset($json_data['id']) || !isset($json_data['delete_key'])) {
    http_response_code(400);
    echo json_encode(['error' => 'IDまたは削除キーがありません。']);
    exit;
}

$id = $json_data['id'];
$delete_key = $json_data['delete_key'];

try {
    $stmt = $pdo->prepare("SELECT filepath, delete_key FROM files WHERE id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        http_response_code(404);
        echo json_encode(['error' => 'ファイルが見つかりません。']);
        exit;
    }

    // 削除キーの照合
    if (!password_verify($delete_key, $file['delete_key'])) {
        echo json_encode(['success' => false, 'error' => '削除キーが間違っています。']);
        exit;
    }
    
    // --- 削除キーが正しい場合、物理ファイル削除 ---
    $delete_success = false;
    $filepath = $file['filepath'];

    // パスが \\ で始まるかチェック
    if (substr($filepath, 0, 2) === '\\\\') {
        // --- NASパスの場合 ---
        $safe_nas_share = escapeshellarg($nas_share_path);
        $safe_nas_user = escapeshellarg($nas_user);
        $safe_nas_pass = escapeshellarg($nas_pass);
        @exec("net use $safe_nas_share /delete /Y");
        $connect_command = "net use $safe_nas_share $safe_nas_pass /user:$safe_nas_user /persistent:no";
        exec($connect_command, $connect_output, $connect_return_var);

        if ($connect_return_var === 0) { // 認証が成功したら
            @exec("del " . escapeshellarg($filepath), $del_output, $del_return_var);
            $delete_success = ($del_return_var === 0);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'NASへの認証に失敗しました。']);
            exit;
        }
    } else {
        // --- ローカルパス (D:\ など) の場合 ---
        if (file_exists($filepath)) {
            $delete_success = @unlink($filepath);
        } else {
            $delete_success = true; // ファイルはもう無い
        }
    }

    if ($delete_success) {
        // DBから削除
        $del_stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
        $del_stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'ファイルの物理削除に失敗しました。']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed']);
}
?>