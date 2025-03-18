<?php
require_once 'config.php';

$old_category = isset($_GET['category']) ? urldecode($_GET['category']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_category = trim($_POST['category']);
    $category_weight = (int)$_POST['category_weight'];
    if (!empty($new_category)) {
        try {
            $stmt = $pdo->prepare("UPDATE bookmarks SET category = ?, category_weight = ? WHERE category = ?");
            $stmt->execute([$new_category, $category_weight, $old_category]);
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

// 获取当前分类的权重
$stmt = $pdo->prepare("SELECT category_weight FROM bookmarks WHERE category = ? LIMIT 1");
$stmt->execute([$old_category]);
$current_weight = $stmt->fetchColumn() ?: 1;
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
            <label>分类权重（1最小，越大越靠前）:</label>
            <input type="number" name="category_weight" min="1" value="<?php echo htmlspecialchars($current_weight); ?>" required>
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