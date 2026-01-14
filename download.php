<?php
require 'config.php';

if (!isset($_GET['id'])) {
    http_response_code(404);
    die("IDが指定されていません。");
}

$id = $_GET['id'];
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'download'; // defaultはダウンロード

// DB取得
$stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
$stmt->execute([$id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

// ファイル存在確認
if (!$file || !file_exists($file['filepath'])) {
    http_response_code(404);
    die("ファイルが見つかりません（削除された可能性があります）。");
}

// NAS認証が必要な場合（NASモードかつファイルがネットワークパスの場合）
if ($storage_mode === 'nas' && strpos($file['filepath'], '\\\\') === 0) {
    $safe_nas_share = escapeshellarg($nas_share_path);
    $safe_nas_user = escapeshellarg($nas_user);
    $safe_nas_pass = escapeshellarg($nas_pass);
    exec("net use $safe_nas_share $safe_nas_pass /user:$safe_nas_user /persistent:no");
}

// 元のファイル名と拡張子を取得
$original_filename = basename($file['filename']);
$ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));

// MIMEタイプと動作（ダウンロードor表示）の初期値
$mime_type = 'application/octet-stream';
$disposition = 'attachment'; // 強制ダウンロード

// --- プレビューモードの処理 ---
if ($mode === 'preview') {
    $allow_preview = false;

    // 画像
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $mime_type = mime_content_type($file['filepath']);
        $allow_preview = true;
    }
    // 動画
    elseif (in_array($ext, ['mp4', 'webm', 'mov'])) {
        $mime_type = mime_content_type($file['filepath']);
        $allow_preview = true;
    }
    // テキスト・ソースコード（中身をテキストとして表示）
    elseif (in_array($ext, ['txt', 'log', 'md', 'py', 'php', 'c', 'cpp', 'cs', 'java', 'html', 'js', 'css', 'json', 'xml'])) {
        $mime_type = 'text/plain'; // 安全のため text/plain で強制表示
        $allow_preview = true;
    }

    if ($allow_preview) {
        $disposition = 'inline'; // ブラウザ内で表示
    }
}

// ヘッダー送信
header('Content-Type: ' . $mime_type);
header('Content-Disposition: ' . $disposition . '; filename="' . $original_filename . '"');
header('Content-Length: ' . filesize($file['filepath']));
// キャッシュ制御（動画のシーク等のため）
header('Accept-Ranges: bytes');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');

// ファイル出力
ob_clean();
flush();
readfile($file['filepath']);
exit;
?>