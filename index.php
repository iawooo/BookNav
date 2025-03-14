<?php
require_once 'config.php';

// 获取分类列表
$categories = $pdo->query("SELECT DISTINCT category FROM bookmarks WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// 处理搜索
$search = isset($_GET['search']) ? $_GET['search'] : '';
$query = "SELECT * FROM bookmarks";
if ($search) {
    $query .= " WHERE NAME LIKE :search OR url LIKE :search OR category LIKE :search OR note LIKE :search";
}
$query .= " ORDER BY category, POSITION, NAME";
$stmt = $pdo->prepare($query);
if ($search) {
    $stmt->bindValue(':search', "%$search%");
}
$stmt->execute();
$bookmarks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 处理拖拽排序
if (isset($_POST['order'])) {
    $order = json_decode($_POST['order'], true);
    foreach ($order as $pos => $id) {
        $stmt = $pdo->prepare("UPDATE bookmarks SET POSITION = ? WHERE id = ?");
        $stmt->execute([$pos, $id]);
    }
    exit('success');
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>书签导航</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="<?php echo FAVICON; ?>">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-top">
                <div class="title-wrapper">
                    <h1>书签导航</h1>
                </div>
                <button id="theme-toggle" class="btn search-btn">切换主题</button>
            </div>
            <div class="search-bar">
                <input type="text" id="search" placeholder="搜索书签..." value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn search-btn" onclick="searchBookmarks()">🔍</button>
                <a href="add.php" class="btn search-btn">+</a>
            </div>
            <nav class="category-nav">
                <a href="index.php#all" class="<?php echo !$search ? 'active' : ''; ?>">全部</a>
                <?php foreach ($categories as $cat): ?>
                    <a href="index.php#<?php echo urlencode($cat); ?>" class="<?php echo !$search && $cat === $current_category ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($cat); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </header>
        <?php
        $current_category = '';
        foreach ($bookmarks as $bookmark) {
            // 调试：检查数据
            // echo '<pre>'; var_dump($bookmark); echo '</pre>';
            if ($bookmark['category'] !== $current_category) {
                if ($current_category !== '') echo '</div></div>';
                echo '<div class="category" id="' . ($bookmark['category'] ? urlencode($bookmark['category']) : 'uncategorized') . '">';
                echo '<h2>';
                echo ($bookmark['category'] ?: '未分类');
                echo ' <a href="add.php?category=' . urlencode($bookmark['category'] ?: '') . '" class="btn add-btn small">+</a>';
                if ($bookmark['category']) {
                    echo ' <a href="edit_category.php?category=' . urlencode($bookmark['category']) . '" class="btn edit-btn small">✏️</a>';
                    echo ' <a href="delete_category.php?category=' . urlencode($bookmark['category']) . '" class="btn delete-btn small" onclick="return confirm(\'确定删除分类 [' . htmlspecialchars($bookmark['category']) . '] 及其所有书签?\')">🗑️</a>';
                }
                echo '</h2>';
                echo '<div class="bookmark-container">';
                $current_category = $bookmark['category'];
            }
            echo '<div class="bookmark" data-id="' . $bookmark['id'] . '" style="background-image: url(\'' . htmlspecialchars($bookmark['icon'] ?: DEFAULT_ICON) . '\');">';
            echo '<a href="' . htmlspecialchars($bookmark['url']) . '" target="_blank">';
            echo '<div class="bookmark-text">';
            echo '<span class="name">' . htmlspecialchars($bookmark['NAME']) . '</span>';
            if (!empty($bookmark['note'])) {
                echo '<p class="note">' . htmlspecialchars($bookmark['note']) . '</p>';
            }
            echo '</div>';
            echo '</a>';
            echo '<div class="actions">';
            echo '<a href="edit.php?id=' . $bookmark['id'] . '" class="edit">编辑</a>';
            echo '<a href="delete.php?id=' . $bookmark['id'] . '" class="delete" onclick="return confirm(\'确定删除?\')">删除</a>';
            echo '<a href="#" class="close" onclick="this.parentElement.parentElement.classList.remove(\'active\'); return false;">关闭</a>';
            echo '</div>';
            echo '</div>';
        }
        if ($current_category !== '') echo '</div></div>';
        ?>
    </div>
    <canvas id="sakura"></canvas>
    <script src="script.js"></script>
</body>
</html>