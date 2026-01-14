<?php
require 'config.php';

if (!isset($_GET['id'])) {
    die("IDが指定されていません。");
}
$id = $_GET['id'];
$delete_error = ""; // 削除キーのエラー用
$password_error = ""; // パスワードのエラー用

// --- 削除処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!empty($_POST['delete_key'])) {
        $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file) {
            // ★ 削除ロジックもNAS/ローカル判別 (ここから) ★
            $delete_success = false;
            
            // パスが \\ で始まるかチェック
            if (substr($file['filepath'], 0, 2) === '\\\\') {
                // --- NASパスの場合 ---
                $safe_nas_share = escapeshellarg($nas_share_path);
                $safe_nas_user = escapeshellarg($nas_user);
                $safe_nas_pass = escapeshellarg($nas_pass);
                @exec("net use $safe_nas_share /delete /Y");
                $connect_command = "net use $safe_nas_share $safe_nas_pass /user:$safe_nas_user /persistent:no";
                exec($connect_command, $connect_output, $connect_return_var);

                if ($connect_return_var === 0) { // 認証が成功したら
                    @exec("del " . escapeshellarg($file['filepath']), $del_output, $del_return_var);
                    $delete_success = ($del_return_var === 0);
                } else {
                    $delete_error = "NASへの認証に失敗しました。";
                }
            } else {
                // --- ローカルパス (D:\ など) の場合 ---
                if (file_exists($file['filepath'])) {
                    $delete_success = @unlink($file['filepath']);
                } else {
                    $delete_success = true; // ファイルはもう無いが、DBからは消す
                }
            }
            // ★ 削除ロジック (ここまで) ★

            if ($delete_success) {
                $del_stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
                $del_stmt->execute([$id]);
                
                // 削除成功メッセージ
                echo "<!DOCTYPE html><html lang='ja'><head><meta charset='UTF-8'><title>削除完了</title><style>body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f4f4f8; margin: 0; padding: 20px; } .container { max-width: 600px; margin: 20px auto; background-color: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); } h2 { color: #dc3545; } a { color: #007bff; text-decoration: none; }</style></head><body><div class='container'><h2>ファイルを削除しました。</h2><a href='index.php'>ファイル一覧に戻る</a></div></body></html>";
                exit;
            } elseif (empty($delete_error)) {
                $delete_error = "ファイルの物理削除に失敗しました。";
            }
                
        }
    } else {
        $delete_error = "削除キーが入力されていません。";
    }
}
// --- 削除処理 終了 ---


$stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
$stmt->execute([$id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

// ★ file_exists はNASパスで不安定なため、チェックを簡略化
if (!$file) {
    die("ファイルが存在しないか、有効期限が切れました。");
}

$require_password = !empty($file['password']);
// ★ プレビューのパスも file_proxy.php を使うように修正
$show_preview = preg_match('/\.(mp4|webm|ogg|mp3|wav|jpg|jpeg|png|gif)$/i', $file['filename']); 
$preview_url = "file_proxy.php?id=" . $id;

// --- 認証チェック ---
$valid = !$require_password; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $require_password) {
    if (isset($_POST['password'])) {
        if (password_verify($_POST['password'], $file['password'])) {
            $valid = true;
        } else {
            $password_error = "パスワードが間違っています。";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ファイルページ</title>
    <style>
        /* ★ ヘッダーCSS (ここから) ★ */
        .site-header {
            background-color: #ffffff;
            padding: 15px 30px;
            margin-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .site-header a {
            text-decoration: none;
            color: #333;
            display: flex;
            align-items: center;
        }
        .header-icon {
            font-size: 28px;
            margin-right: 12px;
            color: #007bff;
        }
        .header-title {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        .header-title-gray {
            font-size: 24px;
            font-weight: bold;
            color: #888;
        }
        /* ★ ヘッダーCSS (ここまで) ★ */

        /* ★ body の padding を 0 に変更 ★ */
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f4f4f8; margin: 0; padding: 0; }
        .container { max-width: 800px; margin: 20px auto; background-color: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        h2 { margin-top: 0; color: #333; }
        .description {
            background-color: #f9f9f9;
            border-left: 4px solid #eee;
            padding: 10px 15px;
            margin-bottom: 20px;
            white-space: pre-wrap; 
            word-wrap: break-word; 
        }
        .file-meta {
            font-size: 0.9em;
            color: #555;
            margin-bottom: 20px;
        }
        .alert-danger { background-color: #f8d7da; color: #721c24; padding: 10px 15px; border: 1px solid #f5c6cb; border-radius: 6px; margin-bottom: 15px; }
        form div { margin-bottom: 15px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; }
        input[type="password"], input[type="text"] {
            width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 6px; font-size: 16px;
        }
        button, a.btn {
            display: inline-block; text-decoration: none; background-color: #007bff; color: white; padding: 12px 18px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; margin-right: 10px; margin-bottom: 10px; transition: opacity 0.2s;
        }
        a.btn-success { background-color: #28a745; }
        a.btn-danger, .delete-form-container button { background-color: #dc3545; }
        button:hover, a.btn:hover { opacity: 0.8; }
        
        .preview-box { border: 1px solid #eee; padding: 15px; margin-top: 20px; border-radius: 8px; }
        video, audio, img { max-width: 100%; height: auto; border-radius: 6px; }

        .delete-form-container { border-top: 1px solid #eee; margin-top: 25px; padding-top: 20px; }
        .delete-form-container form { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
        .delete-form-container label { margin-bottom: 0; font-weight: 600; }
        .delete-form-container input[type="password"] { width: 200px; }
        .delete-form-container button { margin-bottom: 0; }
    </style>
</head>
<body>

<header class="site-header">
    <a href="index.php">
        <span class="header-icon">📁</span>
        <span class="header-title">file</span>
        <span class="header-title-gray">share</span>
    </a>
</header>
<div class="container">
    <h2><?php echo htmlspecialchars($file['title']); ?></h2>
    
    <div class="file-meta">
        ファイル名: <?php echo htmlspecialchars($file['filename']); ?><br>
        アップロード日時: <?php echo $file['uploaded_at']; ?>
    </div>
    <?php if (!empty($file['description'])): ?>
        <div class="description">
            <?php echo htmlspecialchars($file['description']); ?>
        </div>
    <?php endif; ?>

    
    <?php if (!$valid): ?>
        <?php if (!empty($password_error)): ?>
            <div class="alert-danger"><?php echo $password_error; ?></div>
        <?php endif; ?>
        <form method="post">
            <div>
                <label>パスワードを入力してください</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">確認</button>
        </form>
    <?php else: ?>
        <a href="download.php?id=<?php echo $file['id']; ?>" class="btn btn-success">ファイルをダウンロード</a>
        
        <div class="delete-form-container">
            <?php if (!empty($delete_error)): ?>
                <div class="alert-danger"><?php echo $delete_error; ?></div>
            <?php endif; ?>
            <form method="post" onsubmit="return confirm('本当にファイルを削除しますか？');">
                <input type="hidden" name="action" value="delete">
                <label for="delete_key">ファイルを削除:</label>
                <input type="password" name="delete_key" id="delete_key" placeholder="削除キーを入力" required>
                <button type="submit" class="btn-danger">削除実行</button>
            </form>
        </div>
        
        <?php if ($show_preview): ?>
            <div class="preview-box">
                <?php if (preg_match('/\.(mp4|webm|ogg)$/i', $file['filename'])): ?>
                    <video controls width="100%"><source src="<?php echo $preview_url; ?>">この動画はプレビューできません。</video>
                <?php elseif (preg_match('/\.(mp3|wav)$/i', $file['filename'])): ?>
                    <audio controls style="width: 100%;"><source src="<?php echo $preview_url; ?>">この音声はプレビューできません。</audio>
                <?php elseif (preg_match('/\.(jpg|jpeg|png|gif)$/i', $file['filename'])): ?>
                    <img src="<?php echo $preview_url; ?>" alt="プレビュー">
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
</body>
</html>