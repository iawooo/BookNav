<?php
require_once 'config.php';

// 如果 $pdo 未定义（数据库连接失败），删除配置文件并跳转到安装页面
if (!isset($pdo)) {
    if (file_exists(CONFIG_FILE)) {
        unlink(CONFIG_FILE); // 删除无效配置文件
    }
    header('Location: install.php');
    exit;
}

// 获取分类列表，按权重降序排序
$categories = $pdo->query("SELECT DISTINCT category FROM bookmarks WHERE category IS NOT NULL AND category != '' ORDER BY category_weight DESC, category")->fetchAll(PDO::FETCH_COLUMN);

// 处理搜索
$search = isset($_GET['search']) ? $_GET['search'] : '';
$query = "SELECT * FROM bookmarks";
if ($search) {
    $query .= " WHERE name LIKE :search OR url LIKE :search OR category LIKE :search OR note LIKE :search";
}
$query .= " ORDER BY category_weight DESC, category, position, name";
$stmt = $pdo->prepare($query);
if ($search) {
    $stmt->bindValue(':search', "%$search%");
}
$stmt->execute();
$bookmarks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 处理拖拽排序
if (isset($_POST['order'])) {
    $order = json_decode($_POST['order'], true);
    if (is_array($order)) {
        foreach ($order as $id => $data) {
            $stmt = $pdo->prepare("UPDATE bookmarks SET position = ?, category = ? WHERE id = ?");
            $stmt->execute([(int)$data['position'], $data['category'], (int)$id]);
        }
        exit('success');
    } else {
        exit('invalid order data');
    }
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
            <!-- 修改后的 header-top -->
            <div class="header-top">
                <a href="backup.php" class="btn add-btn" style="margin-right: 10px;">导出/入备份</a>
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
        <!-- 显示导入提示 -->
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="error-message"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>
        <?php
        $current_category = null;
        $has_uncategorized = false;

        // 先处理无分类书签
        echo '<div class="bookmark-container">';
        foreach ($bookmarks as $bookmark) {
            if (empty($bookmark['category'])) {
                $has_uncategorized = true;
                echo '<div class="bookmark" data-id="' . $bookmark['id'] . '" style="background-image: url(\'' . htmlspecialchars($bookmark['icon'] ?: DEFAULT_ICON) . '\');">';
                echo '<a href="' . htmlspecialchars($bookmark['url']) . '" target="_blank">';
                echo '<div class="bookmark-text">';
                echo '<span class="name">' . htmlspecialchars($bookmark['name']) . '</span>';
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
        }
        if ($has_uncategorized) echo '</div>';

        // 再处理有分类书签
        foreach ($bookmarks as $bookmark) {
            if (!empty($bookmark['category']) && $bookmark['category'] !== $current_category) {
                if ($current_category !== null) echo '</div></div></div>'; // 关闭上一个 category-wrapper
                echo '<div class="category-wrapper">';
                echo '<div class="category" id="' . urlencode($bookmark['category']) . '">';
                echo '<h2>';
                echo htmlspecialchars($bookmark['category']);
                echo ' <a href="add.php?category=' . urlencode($bookmark['category']) . '" class="btn add-btn small">+</a>';
                echo ' <a href="edit_category.php?category=' . urlencode($bookmark['category']) . '" class="btn edit-btn small">&#9998;</a>';
                echo ' <a href="delete_category.php?category=' . urlencode($bookmark['category']) . '" class="btn delete-btn small" onclick="return confirm(\'确定删除分类 [' . htmlspecialchars($bookmark['category']) . '] 及其所有书签?\')">&#128465;</a>';
                echo '</h2>';
                echo '<div class="bookmark-container">';
                $current_category = $bookmark['category'];
            }
            if (!empty($bookmark['category'])) {
                echo '<div class="bookmark" data-id="' . $bookmark['id'] . '" style="background-image: url(\'' . htmlspecialchars($bookmark['icon'] ?: DEFAULT_ICON) . '\');">';
                echo '<a href="' . htmlspecialchars($bookmark['url']) . '" target="_blank">';
                echo '<div class="bookmark-text">';
                echo '<span class="name">' . htmlspecialchars($bookmark['name']) . '</span>';
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
        }
        if ($current_category !== null) echo '</div></div></div>'; // 关闭最后一个 category-wrapper
        ?>
    </div>
    <canvas id="sakura"></canvas>
    <script src="script.js"></script>
</body>
</html>
