<?php
require_once 'config.php';

$old_category = isset($_GET['category']) ? urldecode($_GET['category']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_category = trim($_POST['category']);
    if (!empty($new_category)) {
        try {
            $stmt = $pdo->prepare("UPDATE bookmarks SET category = ? WHERE category = ?");
            $stmt->execute([$new_category, $old_category]);
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            echo "更新失败: " . $e->getMessage();
            exit;
        }
    } else {
        echo "分类名称不能为空！";
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>修改分类</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="<?php echo FAVICON; ?>">
</head>
<body>
    <div class="container">
        <h1>修改分类</h1>
        <form method="POST">
            <label>分类名称:</label>
            <input type="text" name="category" value="<?php echo htmlspecialchars($old_category); ?>" required>
            <div class="form-buttons">
                <button type="submit" class="btn save-btn">保存</button>
                <a href="index.php" class="btn cancel-btn">取消</a>
            </div>
        </form>
    </div>
    <canvas id="sakura"></canvas>
    <script src="script.js"></script>
</body>
</html>