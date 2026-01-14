
<?php
// download.php
require 'config.php';

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($file && file_exists($file['filepath'])) {
        if (!empty($file['password'])) {
            echo '<form method="post">パスワード: <input type="password" name="password"><button type="submit">ダウンロード</button></form>';
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (password_verify($_POST['password'], $file['password'])) {
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . basename($file['filename']) . '"');
                    header('Content-Length: ' . filesize($file['filepath']));
                    readfile($file['filepath']);
                    exit;
                } else {
                    echo "パスワードが間違っています。";
                }
            }
        } else {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file['filename']) . '"');
            header('Content-Length: ' . filesize($file['filepath']));
            readfile($file['filepath']);
            exit;
        }
    } else {
        echo "ファイルが存在しません。";
    }
} else {
    echo "IDが指定されていません。";
}
?>

<?php
