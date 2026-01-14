<?php
// ===================================================
// ★★★ 保存先 切り替えスイッチ ★★★
// ===================================================
// 'nas'    = NAS (\\192.168.0.xx\share\files) に保存
// 'local'  = ローカル (D:\file_uploads など) に保存
//
$storage_mode = 'local'; // ←★ ここを 'nas' か 'local' に切り替える

// ===================================================
// 1. NAS (OMV) 設定
// ===================================================
$nas_user = 'nas_user';
$nas_pass = 'nas_password'; // ← あなたのNASのパスワード
$nas_share_path = '\\\\192.168.0.xx\\share_name'; // 共有フォルダのルート (最後は \ 無し)
$nas_files_dir = $nas_share_path . '\\files';   // 実際の保存先 (手動で作ったフォルダ)

// ===================================================
// 管理者パスワード設定 (admin.php用)
// ===================================================
$admin_auth_user = 'admin';//変更推奨
$admin_auth_pass = 'Password'; // ← 必ず変更してください


// ===================================================
// 2. ローカル (Dドライブなど) 設定
// ===================================================
// ※バックスラッシュは2重 (\\) で書きます
$local_files_dir = 'C:\\xampp\\htdocs\\file_uploads'; // ←★ 好きなパスに変更OK

// ===================================================
// 3. データベース (DB) 設定
// ===================================================
$host = 'localhost';
$db = 'file_share';
$user = 'root';
$pass = ''; // あなたが設定したMySQLのパスワード

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: ". $e->getMessage());
}

// ===================================================
// 4. 自動削除ロジック (NAS/ローカル自動判別)
// ===================================================
try {
    $stmt = $pdo->prepare("SELECT id, filepath FROM files WHERE expires_at IS NOT NULL AND expires_at < NOW()");
    $stmt->execute();
    $expired_files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($expired_files) > 0) {
        // NAS認証が必要かもしれないので、一度だけ実行
        $safe_nas_share = escapeshellarg($nas_share_path);
        $safe_nas_user = escapeshellarg($nas_user);
        $safe_nas_pass = escapeshellarg($nas_pass);
        @exec("net use $safe_nas_share /delete /Y");
        $connect_command = "net use $safe_nas_share $safe_nas_pass /user:$safe_nas_user /persistent:no";
        exec($connect_command, $connect_output, $connect_return_var);
        
        $del_stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");

        foreach ($expired_files as $file) {
            // パスが \\ で始まるかチェック
            if (substr($file['filepath'], 0, 2) === '\\\\') {
                // NASパスの場合
                if ($connect_return_var === 0) { // 認証が成功していたら
                    @exec("del " . escapeshellarg($file['filepath'])); // OSの del で削除
                }
            } else {
                // ローカルパスの場合
                @unlink($file['filepath']); // PHPの unlink で削除
            }
            // DBからは常に削除
            $del_stmt->execute([$file['id']]);
        }
    }
} catch (PDOException $e) {
    // クリーンアップ処理は続行
}
?>