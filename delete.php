<?php
require_once 'config.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM bookmarks WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        echo "删除失败: " . $e->getMessage();
        exit;
    }
} else {
    echo "无效的书签 ID";
}
?>