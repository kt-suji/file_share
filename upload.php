<?php
require 'config.php';

// POSTチェック
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    header('Location: upload_form.html');
    exit;
}

$file = $_FILES['file'];
$original_filename = basename($file['name']); // 元のファイル名（DB保存用、表示用）

// 【重要】保存名はランダムなID + .dat に強制変換（これで実行不可にする）
$saved_filename = uniqid('file_', true) . '.dat';

// フォーム情報の取得
$title = !empty($_POST['title']) ? $_POST['title'] : ' (タイトルなし) ';
$description = !empty($_POST['description']) ? $_POST['description'] : null;
$password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
$delete_key = !empty($_POST['delete_key']) ? password_hash($_POST['delete_key'], PASSWORD_DEFAULT) : null;
$expiry_days = $_POST['expiry_days'];
$expires_at = null;
if (in_array($expiry_days, ['3', '5', '7'])) {
    $expires_at = date('Y-m-d H:i:s', strtotime("+$expiry_days days"));
}

if (!$delete_key || !$title) {
    die("削除キーとタイトルは必須です。");
}

$upload_success = false;
$final_filepath = ''; // DBに保存する物理パス

// --- 保存処理 ---
if ($storage_mode === 'local') {
    // ローカル保存
    if (!is_dir($local_files_dir)) {
        mkdir($local_files_dir, 0777, true);
    }
    // .dat名で保存
    $final_filepath = $local_files_dir . '\\' . $saved_filename;
    
    if (move_uploaded_file($file['tmp_name'], $final_filepath)) {
        $upload_success = true;
    } else {
        echo "アップロード失敗。ローカルへの保存に失敗しました。";
    }

} elseif ($storage_mode === 'nas') {
    // NAS保存
    $final_filepath = $nas_files_dir . '\\' . $saved_filename;
    
    // 一時保存用ディレクトリ（Web公開領域外が望ましいが、今回は名前が.datなので安全）
    $local_stage_dir = __DIR__ . '\\uploads_stage';
    if (!is_dir($local_stage_dir)) {
        mkdir($local_stage_dir, 0777, true);
    }
    // 一時ファイルも .dat で作成
    $local_stage_file = $local_stage_dir . '\\' . $saved_filename;

    if (move_uploaded_file($file['tmp_name'], $local_stage_file)) {
        
        $safe_nas_share = escapeshellarg($nas_share_path);
        $safe_nas_user = escapeshellarg($nas_user);
        $safe_nas_pass = escapeshellarg($nas_pass);
        $safe_source = escapeshellarg($local_stage_file);
        $safe_dest = escapeshellarg($final_filepath);

        // NAS接続
        @exec("net use $safe_nas_share /delete /Y");
        $connect_command = "net use $safe_nas_share $safe_nas_pass /user:$safe_nas_user /persistent:no";
        exec($connect_command, $connect_output, $connect_return_var);

        if ($connect_return_var === 0) {
            // コピー実行
            $copy_command = "copy $safe_source $safe_dest";
            exec($copy_command, $copy_output, $copy_return_var);
            
            if ($copy_return_var === 0) {
                $upload_success = true;
                unlink($local_stage_file); // 一時ファイル削除
            } else {
                unlink($local_stage_file);
                echo "アップロード失敗。NASへのコピーに失敗しました。";
            }
        } else {
            unlink($local_stage_file);
            echo "アップロード失敗。NASへの認証に失敗しました。";
        }
    } else {
        echo "アップロード失敗。一時保存に失敗しました。";
    }
} else {
    die("config.php の設定エラーです。");
}

// --- 成功時のDB登録 ---
if ($upload_success) {
    // filenameには「元の名前」、filepathには「.datのパス」を入れる
    $stmt = $pdo->prepare(
        "INSERT INTO files (filename, filepath, password, delete_key, uploaded_at, expires_at, title, description) 
         VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)"
    );
    $stmt->execute([$original_filename, $final_filepath, $password, $delete_key, $expires_at, $title, $description]);

    $file_id = $pdo->lastInsertId();
    
    // URL生成
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    if ($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) {
        $host .= ':' . $_SERVER['SERVER_PORT'];
    }
    $base_url = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']);
    
    // file.php へのリンクを表示
    $file_page_url = rtrim($base_url, '/') . '/file.php?id=' . $file_id;

    echo "<!DOCTYPE html><html lang='ja'><head><meta charset='UTF-8'><title>アップロード成功</title>";
    echo "<style>body{font-family:sans-serif;text-align:center;padding:20px;}.container{max-width:600px;margin:0 auto;padding:20px;border:1px solid #ccc;border-radius:8px;}input{width:80%;padding:10px;margin:10px 0;}</style></head><body><div class='container'>";
    echo "<h2>アップロード成功！</h2>";
    echo "<p>共有URL:</p>";
    echo '<div style="display:flex; gap:10px; justify-content:center; margin-bottom: 20px;">';
    echo '<input type="text" id="urlInput" value="' . htmlspecialchars($file_page_url) . '" readonly onclick="this.select();">';
    echo '<button onclick="copyToClipboard()" style="padding:10px; cursor:pointer; background:#007bff; color:white; border:none; border-radius:5px;">コピー</button>';
    echo '</div>';
    
    echo "<script>
    function copyToClipboard() {
        var copyText = document.getElementById('urlInput');
        copyText.select();
        copyText.setSelectionRange(0, 99999); /* For mobile devices */
        navigator.clipboard.writeText(copyText.value).then(function() {
            alert('URLをコピーしました！');
        }, function(err) {
            alert('コピーに失敗しました: ' + err);
        });
    }
    </script>";
    echo "<p><a href='upload_form.html'>戻る</a></p>";
    echo "</div></body></html>";
}
?>