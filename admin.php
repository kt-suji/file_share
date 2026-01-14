<?php
require 'config.php';

// ===================================================
// Basic認証 (アクセス制限)
// ===================================================
// config.php で設定された値を使用
$auth_user = isset($admin_auth_user) ? $admin_auth_user : 'admin';
$auth_pass = isset($admin_auth_pass) ? $admin_auth_pass : 'admin_default_pass'; 

if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] !== $auth_user || $_SERVER['PHP_AUTH_PW'] !== $auth_pass) {
    header('WWW-Authenticate: Basic realm="File Share Admin"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'このページを見るには認証が必要です。';
    exit;
}

// ！！注意！！ config.php は、既に読み込まれています。

// --- ★ モード切り替え処理 (セキュリティ強化版) ★ ---
$config_path = __DIR__ . '/config.php';
$update_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_mode'])) {
    
    // 1. 入力値の安全性チェック
    // 'nas' か 'local' 以外の文字列が来たら強制的に弾く (コード埋め込み防止)
    $new_mode = $_POST['change_mode'];
    if ($new_mode !== 'nas' && $new_mode !== 'local') {
        $update_message = "エラー: 不正なパラメータです。";
    } else {
        // 2. config.php の現在の内容を読み込む
        $config_content = file_get_contents($config_path);
        if ($config_content === false) {
            $update_message = "エラー: config.php が読み込めません。";
        } else {
            // 3. 正規表現で $storage_mode の行を書き換える
            // 安全な $new_mode だけを使うのでコードインジェクションを防げます
            $pattern = "/(\\\$storage_mode\s*=\s*['\"])(nas|local)(['\"];)/";
            
            if (preg_match($pattern, $config_content)) {
                $new_config_content = preg_replace($pattern, "$1" . $new_mode . "$3", $config_content);
                
                // 4. config.php に書き込む
                if (file_put_contents($config_path, $new_config_content) === false) {
                    $update_message = "エラー: config.php に書き込めません。権限を確認してください。";
                } else {
                    $update_message = "保存先を「" . ($new_mode === 'nas' ? 'NAS' : 'ローカル') . "」に変更しました。";
                }
            } else {
                $update_message = "エラー: config.php 内に \$storage_mode の設定が見つかりません。";
            }
        }
    }
}
// --- ★ モード切り替え処理 (ここまで) ★ ---


// config.php を読み込む
// config.php は冒頭で読み込み済み
// require 'config.php';  

$message = ""; // 削除処理用のメッセージ

// 削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file_id'])) {
    $id_to_delete = $_POST['delete_file_id'];
    $stmt = $pdo->prepare("SELECT filepath FROM files WHERE id = ?");
    $stmt->execute([$id_to_delete]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($file) {
        $delete_success = false;

        // パスが \\ で始まるかチェック (NAS判定)
        if (strpos($file['filepath'], '\\\\') === 0) {
            // --- NASパスの場合 ---
            $safe_nas_share = escapeshellarg($nas_share_path);
            $safe_nas_user = escapeshellarg($nas_user);
            $safe_nas_pass = escapeshellarg($nas_pass);
            
            // 念のため接続解除してから再接続
            @exec("net use $safe_nas_share /delete /Y");
            $connect_command = "net use $safe_nas_share $safe_nas_pass /user:$safe_nas_user /persistent:no";
            exec($connect_command, $connect_output, $connect_return_var);

            if ($connect_return_var === 0) { // 認証が成功したら
                @exec("del " . escapeshellarg($file['filepath']), $del_output, $del_return_var);
                $delete_success = ($del_return_var === 0);
                
                // コマンドが失敗しても、ファイルが既に無ければ成功とみなす
                if (!$delete_success && !file_exists($file['filepath'])) {
                    $delete_success = true;
                }
            } else {
                $message = "NASへの認証に失敗しました。";
            }
        } else {
            // --- ローカルパス (D:\ など) の場合 ---
            if (file_exists($file['filepath'])) {
                $delete_success = @unlink($file['filepath']);
            } else {
                $delete_success = true; // ファイルはもう無いが、DBからは消す
            }
        }

        if ($delete_success) {
            $del_stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
            $del_stmt->execute([$id_to_delete]);
            $message = "ファイル (ID: $id_to_delete) を削除しました。";
        } elseif (empty($message)) {
             $message = "ファイルの物理削除に失敗しました (ID: $id_to_delete)。";
        }

    } else {
        $message = "ファイル (ID: $id_to_delete) が見つかりませんでした。";
    }
}

// ファイル一覧取得
$stmt = $pdo->query("SELECT id, title, description, filename, filepath, uploaded_at, expires_at FROM files ORDER BY uploaded_at DESC");
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ファイルサイズ取得関数 (NAS対応)
function get_file_size_robust($filepath, $nas_config) {
    // NASパス以外でファイルがない場合は早期リターン
    if (strpos($filepath, '\\\\') !== 0 && !file_exists($filepath)) {
        return '<span style="color: red;">ファイル無</span>';
    }

    if (strpos($filepath, '\\\\') === 0) {
        // --- NASパスの場合 ---
        $safe_nas_share = escapeshellarg($nas_config['nas_share_path']);
        $safe_nas_user = escapeshellarg($nas_config['nas_user']);
        $safe_nas_pass = escapeshellarg($nas_config['nas_pass']);
        
        @exec("net use $safe_nas_share /delete /Y");
        $connect_command = "net use $safe_nas_share $safe_nas_pass /user:$safe_nas_user /persistent:no";
        exec($connect_command, $connect_output, $connect_return_var);

        if ($connect_return_var === 0) {
            // dirコマンドでサイズを取得
            exec("dir " . escapeshellarg($filepath), $output, $return_var);
            if ($return_var === 0) {
                foreach ($output as $line) {
                    if (strpos($line, basename($filepath)) !== false) {
                        $parts = preg_split('/\s+/', trim($line));
                        // dirコマンドの出力形式からサイズ部分(通常は後ろから2番目や3番目)を探す
                        // Windowsのdir出力形式に依存するため、簡易的に数値っぽいものを探して結合
                        foreach($parts as $part) {
                             $num = str_replace(',', '', $part);
                             if(is_numeric($num) && $num > 0) {
                                 return format_filesize((int)$num);
                             }
                        }
                    }
                }
            }
        }
        return '<span style="color: red;">アクセス不可</span>';

    } else {
        // --- ローカルパスの場合 ---
        return format_filesize(filesize($filepath));
    }
}

function format_filesize($bytes) {
    if ($bytes >= 1073741824) { return number_format($bytes / 1073741824, 2) . ' GB'; }
    elseif ($bytes >= 1048576) { return number_format($bytes / 1048576, 2) . ' MB'; }
    elseif ($bytes >= 1024) { return number_format($bytes / 1024, 2) . ' KB'; }
    elseif ($bytes > 0) { return $bytes . ' B'; }
    else { return '0 B'; }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>管理ページ - ファイル一覧</title>
    <style>
        .site-header { background-color: #ffffff; padding: 15px 30px; margin-bottom: 20px; border-bottom: 1px solid #e0e0e0; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .site-header a { text-decoration: none; color: #333; display: flex; align-items: center; }
        .header-icon { font-size: 28px; margin-right: 12px; color: #007bff; }
        .header-title { font-size: 24px; font-weight: bold; color: #007bff; }
        .header-title-gray { font-size: 24px; font-weight: bold; color: #888; }

        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f4f4f8; margin: 0; padding: 0; }
        .container { max-width: 1200px; margin: 20px auto; background-color: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        h1 { margin-top: 0; color: #333; }
        .message { background-color: #d4edda; color: #155724; padding: 10px 15px; border: 1px solid #c3e6cb; border-radius: 6px; margin-bottom: 20px; }
        .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        
        .mode-switcher { background-color: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .mode-switcher h3 { margin-top: 0; }
        .mode-switcher p { margin-bottom: 10px; }
        .mode-switcher button { padding: 8px 16px; font-size: 14px; font-weight: 600; border: none; border-radius: 5px; cursor: pointer; }
        .mode-switcher button.nas { background-color: #007bff; color: white; }
        .mode-switcher button.local { background-color: #28a745; color: white; }
        .mode-switcher button:disabled { background-color: #ccc; cursor: not-allowed; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
        th { background-color: #f8f9fa; font-weight: 600; }
        .col-desc { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .col-file { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .description { white-space: pre-wrap; }
        .delete-btn { background-color: #dc3545; color: white; padding: 6px 12px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; }
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
    <h1>管理者ページ</h1>
    
    <?php // 削除処理のメッセージ
    if ($message): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php // config.php 書き換え処理のメッセージ
    if ($update_message): ?>
        <div class="message <?php echo (strpos($update_message, 'エラー') !== false) ? 'error' : ''; ?>">
            <?php echo htmlspecialchars($update_message); ?>
        </div>
    <?php endif; ?>

    <div class="mode-switcher">
        <h3>保存先 設定</h3>
        <p>現在の保存先: 
            <?php if ($storage_mode === 'nas'): ?>
                <strong style="color: #007bff;">NAS (<?php echo htmlspecialchars($nas_files_dir); ?>)</strong>
            <?php else: ?>
                <strong style="color: #28a745;">ローカル (<?php echo htmlspecialchars($local_files_dir); ?>)</strong>
            <?php endif; ?>
        </p>
        <form method="post" style="display: inline;">
            <input type="hidden" name="change_mode" value="local">
            <button type="submit" class="local" <?php echo ($storage_mode === 'local') ? 'disabled' : ''; ?>>
                ローカルに切り替え
            </button>
        </form>
        <form method="post" style="display: inline;">
            <input type="hidden" name="change_mode" value="nas">
            <button type="submit" class="nas" <?php echo ($storage_mode === 'nas') ? 'disabled' : ''; ?>>
                NASに切り替え
            </button>
        </form>
    </div>

    <h2>アップロード済みファイル一覧</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>名前（タイトル）</th>
                <th class="col-desc">説明</th>
                <th class="col-file">ファイル名</th>
                <th>サイズ</th>
                <th>アップロード日時</th>
                <th>有効期限</th>
                <th>削除</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($files)): ?>
                <tr>
                    <td colspan="8" style="text-align: center;">アップロードされているファイルはありません。</td>
                </tr>
            <?php else: ?>
                <?php // NAS設定を関数に渡す準備
                $nas_config = [
                    'nas_share_path' => $nas_share_path,
                    'nas_user' => $nas_user,
                    'nas_pass' => $nas_pass
                ];
                ?>
                <?php foreach ($files as $file): ?>
                    <tr>
                        <td><?php echo $file['id']; ?></td>
                        <td>
                            <a href="file.php?id=<?php echo $file['id']; ?>" title="<?php echo htmlspecialchars($file['title']); ?>">
                                <?php echo htmlspecialchars($file['title']); ?>
                            </a>
                        </td>
                        <td class="description"><?php echo htmlspecialchars($file['description']); ?></td>
                        <td class="col-file" title="<?php echo htmlspecialchars($file['filename']); ?>">
                            <?php echo htmlspecialchars($file['filename']); ?>
                        </td>
                        <td>
                            <?php 
                            // ★ 強化されたファイルサイズチェック ★
                            echo get_file_size_robust($file['filepath'], $nas_config);
                            ?>
                        </td>
                        <td><?php echo $file['uploaded_at']; ?></td>
                        <td><?php echo $file['expires_at'] ?? '無期限'; ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('本当にこのファイル (ID: <?php echo $file['id']; ?>) を削除しますか？');">
                                <input type="hidden" name="delete_file_id" value="<?php echo $file['id']; ?>">
                                <button type="submit" class="delete-btn">削除</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>