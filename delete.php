<?php
require 'config.php'; // 設定ファイル (NASのパスワード入り) を読み込む

if (!isset($_GET['id'])) {
    die("IDが指定されていません。");
}
$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
$stmt->execute([$id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

$error = "";
$deleted = false;

if ($file && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_key']) && password_verify($_POST['delete_key'], $file['delete_key'])) {
        // 削除キーが正しい
        
        $delete_success = false;
        
        // ★ パスが \\ で始まるかチェック
        if (substr($file['filepath'], 0, 2) === '\\\\') {
            // --- NASパスの場合 ---
            $safe_nas_share = escapeshellarg($nas_share_path);
            $safe_nas_user = escapeshellarg($nas_user);
            $safe_nas_pass = escapeshellarg($nas_pass);
            
            @exec("net use $safe_nas_share /delete /Y");
            $connect_command = "net use $safe_nas_share $safe_nas_pass /user:$safe_nas_user /persistent:no";
            exec($connect_command, $connect_output, $connect_return_var);

            if ($connect_return_var === 0) { // 認証が成功したら
                $safe_filepath = escapeshellarg($file['filepath']);
                $delete_command = "del $safe_filepath";
                exec($delete_command, $delete_output, $delete_return_var);
                $delete_success = ($delete_return_var === 0);
            } else {
                $error = "NASへの認証に失敗しました。削除できませんでした。";
            }
        } else {
            // --- ローカルパス (D:\ など) の場合 ---
            if (file_exists($file['filepath'])) {
                $delete_success = @unlink($file['filepath']);
            } else {
                $delete_success = true; // ファイルはもう無いが、DBからは消す
            }
        }

        // --- 物理ファイルの削除に成功したら、DBから削除 ---
        if ($delete_success) {
            $del = $pdo->prepare("DELETE FROM files WHERE id = ?");
            $del->execute([$file['id']]);
            $deleted = true;
        } elseif (empty($error)) {
             $error = "ファイルの物理削除に失敗しました。";
        }
        
    } else {
        $error = "削除キーが間違っています。";
    }
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ファイル削除</title>
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

        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f4f4f8; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        input[type="password"] { width: 100%; padding: 8px; box-sizing: border-box; }
        button { background: #dc3545; color: white; padding: 10px; border: none; cursor: pointer; }
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
        <h1>ファイル削除</h1>
        <?php if ($deleted): ?>
            <p style="color: green;">ファイルを削除しました。</p>
            <a href="index.php">一覧に戻る</a>
        <?php elseif (!$file): ?>
            <p style="color: red;">ファイルが見つかりません。</p>
        <?php else: ?>
            <p><strong>ファイル名:</strong> <?php echo htmlspecialchars($file['filename']); ?></p>
            <p><strong>タイトル:</strong> <?php echo htmlspecialchars($file['title']); ?></p>
            <p>このファイルを削除しますか？</p>

            <?php if ($error): ?>
                <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <form method="post">
                <div>
                    <label for="delete_key">削除キー（必須）</label>
                    <input type="password" name="delete_key" id="delete_key" required>
                </div>
                <button type="submit">削除実行</button>
            </form>
            <br>
            <a href="file.php?id=<?php echo $file['id']; ?>">キャンセル</a>
        <?php endif; ?>
    </div>
</body>
</html>