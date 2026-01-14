<?php
require 'config.php';

// ファイル一覧を取得
$stmt = $pdo->query("SELECT id, title, description, filename, password, uploaded_at 
                     FROM files 
                     ORDER BY uploaded_at DESC");
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ファイル一覧</title>
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
            font-size: 28px; /* アイコンのサイズ */
            margin-right: 12px;
            color: #007bff; /* アイコンの色 */
        }
        .header-title {
            font-size: 24px;
            font-weight: bold;
            color: #007bff; /* ロゴテキストの色 */
        }
        .header-title-gray {
            font-size: 24px;
            font-weight: bold;
            color: #888; /* 'share' の部分 */
        }
        /* ★ ヘッダーCSS (ここまで) ★ */

        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f4f4f8; margin: 0; padding: 0; }
        .container { max-width: 1000px; margin: 20px auto; background-color: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        h1 { margin-top: 0; color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
        th { background-color: #f8f9fa; font-weight: 600; }
        tr:nth-child(even) { background-color: #fdfdfd; }
        
        .col-title { width: 25%; }
        .col-desc { width: 35%; word-break: break-all; }
        .col-file { width: 20%; word-break: break-all; }
        .col-pw { width: 5%; text-align: center; }
        .col-action { width: 15%; text-align: center; }
        
        .description { white-space: pre-wrap; /* 改行をそのまま表示 */ }
        .download-btn {
            display: inline-block;
            text-decoration: none;
            background-color: #007bff;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            font-weight: 600;
            transition: background-color 0.2s;
        }
        .download-btn:hover { background-color: #0056b3; }
        .nav-link { margin-top: 15px; }
        .nav-link a { margin-right: 15px; color: #007bff; text-decoration: none; }
        .nav-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<header class="site-header">
    <a href="index.php">
        <span class="header-icon">📁</span> <span class="header-title">file</span>
        <span class="header-title-gray">share</span>
    </a>
</header>
<div class="container">
    <h1>ファイル一覧</h1>
    <div class="nav-link">
        <a href="upload_form.html">新規アップロード</a>
        <!-- <a href="admin.php">管理ページ</a> -->
    </div>
    <table>
        <thead>
            <tr>
                <th class="col-title">名前（タイトル）</th>
                <th class="col-desc">説明</th>
                <th class="col-file">ファイル名</th>
                <th class="col-pw">PW</th>
                <th class="col-action">アクション</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($files)): ?>
                <tr>
                    <td colspan="5" style="text-align: center;">アップロードされているファイルはありません。</td>
                </tr>
            <?php else: ?>
                <?php foreach ($files as $file): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($file['title']); ?></td>
                        <td class="description"><?php echo htmlspecialchars($file['description']); ?></td>
                        <td><?php echo htmlspecialchars($file['filename']); ?></td>
                        <td class="col-pw">
                            <?php if (!empty($file['password'])): ?>
                                🔒
                            <?php endif; ?>
                        </td>
                        <td class="col-action">
                            <a href="file.php?id=<?php echo $file['id']; ?>" class="download-btn">
                                ダウンロードページへ
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>