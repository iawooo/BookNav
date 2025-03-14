<?php
require_once 'config.php';

if (isset($_GET['category'])) {
    $category = urldecode($_GET['category']);
    try {
        $stmt = $pdo->prepare("DELETE FROM bookmarks WHERE category = ?");
        $stmt->execute([$category]);
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        echo "删除失败: " . $e->getMessage();
        exit;
    }
} else {
    echo "无效的分类";
}
?>