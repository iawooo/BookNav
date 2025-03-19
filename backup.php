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

// 处理导出请求
if (isset($_POST['export'])) {
    // 获取所有书签和分类数据
    $stmt = $pdo->query("SELECT * FROM bookmarks ORDER BY category, position, name");
    $bookmarks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 创建备份数据
    $backup = [
        'created_at' => date('Y-m-d H:i:s'),
        'bookmarks' => $bookmarks
    ];
    
    // 将数据转换为 JSON
    $json = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    // 设置响应头，使浏览器下载这个文件
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="bookmarks_backup_' . date('Y-m-d') . '.json"');
    header('Content-Length: ' . strlen($json));
    
    // 输出 JSON 数据并退出
    echo $json;
    exit;
}

// 处理导入请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import'])) {
    // 检查文件大小限制是否足够大（转换为字节）
    $max_file_size = ini_get('upload_max_filesize');
    $post_max_size = ini_get('post_max_size');
    $max_file_size_bytes = convertToBytes($max_file_size);
    $post_max_size_bytes = convertToBytes($post_max_size);
    if ($max_file_size_bytes < 1024 * 1024 || $post_max_size_bytes < 1024 * 1024) { // 小于 1MB
        header('Location: backup.php?error=服务器文件大小限制过小，请联系管理员调整 (upload_max_filesize=' . htmlspecialchars($max_file_size) . ', post_max_size=' . htmlspecialchars($post_max_size) . ')');
        exit;
    }

    // 检查文件是否上传
    if (!isset($_FILES['backup_file']) || empty($_FILES['backup_file']['name'])) {
        header('Location: backup.php?error=请先选择一个备份文件&clear_existing=' . (isset($_POST['clear_existing']) ? '1' : '0'));
        exit;
    }

    // 检查 $_FILES['backup_file'] 结构是否正确
    if (!isset($_FILES['backup_file']['tmp_name'])) {
        header('Location: backup.php?error=文件上传失败：参数不正确&clear_existing=' . (isset($_POST['clear_existing']) ? '1' : '0'));
        exit;
    }

    // 检查文件上传错误
    if ($_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => '文件大小超过服务器限制 (upload_max_filesize: ' . $max_file_size . ')',
            UPLOAD_ERR_FORM_SIZE => '文件大小超过表单限制',
            UPLOAD_ERR_PARTIAL => '文件只上传了一部分',
            UPLOAD_ERR_NO_FILE => '没有选择文件',
            UPLOAD_ERR_NO_TMP_DIR => '服务器临时文件夹不可用',
            UPLOAD_ERR_CANT_WRITE => '无法写入文件',
            UPLOAD_ERR_EXTENSION => '文件上传被扩展阻止'
        ];
        $error_msg = $upload_errors[$_FILES['backup_file']['error']] ?? '未知错误';
        header('Location: backup.php?error=文件上传失败：' . htmlspecialchars($error_msg) . '&clear_existing=' . (isset($_POST['clear_existing']) ? '1' : '0'));
        exit;
    }

    // 检查文件是否存在且是通过 HTTP POST 上传的
    if (!file_exists($_FILES['backup_file']['tmp_name']) || !is_uploaded_file($_FILES['backup_file']['tmp_name'])) {
        header('Location: backup.php?error=文件上传失败：无法访问上传的文件&clear_existing=' . (isset($_POST['clear_existing']) ? '1' : '0'));
        exit;
    }

    // 检查文件大小是否超过限制
    $file_size = $_FILES['backup_file']['size'];
    if ($file_size > $max_file_size_bytes) {
        header('Location: backup.php?error=文件大小超过服务器限制 (最大: ' . htmlspecialchars($max_file_size) . ')&clear_existing=' . (isset($_POST['clear_existing']) ? '1' : '0'));
        exit;
    }

    try {
        // 读取文件内容
        $file_content = file_get_contents($_FILES['backup_file']['tmp_name']);
        if ($file_content === false) {
            header('Location: backup.php?error=文件上传失败：无法读取文件内容&clear_existing=' . (isset($_POST['clear_existing']) ? '1' : '0'));
            exit;
        }

        if (empty($file_content)) {
            header('Location: backup.php?error=文件内容为空&clear_existing=' . (isset($_POST['clear_existing']) ? '1' : '0'));
            exit;
        }

        // 解析 JSON 数据
        $backup_data = json_decode($file_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            header('Location: backup.php?error=文件格式错误：JSON 解析失败 - ' . htmlspecialchars(json_last_error_msg()) . '&clear_existing=' . (isset($_POST['clear_existing']) ? '1' : '0'));
            exit;
        }

        if (!is_array($backup_data) || !isset($backup_data['bookmarks']) || !is_array($backup_data['bookmarks'])) {
            header('Location: backup.php?error=文件格式错误：请上传有效的 JSON 备份文件&clear_existing=' . (isset($_POST['clear_existing']) ? '1' : '0'));
            exit;
        }

        // 开始事务
        $pdo->beginTransaction();
        
        // 清空现有数据（可选）
        if (isset($_POST['clear_existing']) && $_POST['clear_existing'] == '1') {
            $pdo->exec("DELETE FROM bookmarks");
        }
        
        // 插入备份数据
        $stmt = $pdo->prepare("INSERT INTO bookmarks (name, url, category, icon, note, position, category_weight) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");

        foreach ($backup_data['bookmarks'] as $index => $bookmark) {
            // 确保 name 和 url 不为 NULL（表约束要求）
            $name = isset($bookmark['name']) ? $bookmark['name'] : '';
            $url = isset($bookmark['url']) ? $bookmark['url'] : '';
            
            if (empty($name) || empty($url)) {
                continue;
            }

            $stmt->execute([
                $name,
                $url,
                isset($bookmark['category']) ? $bookmark['category'] : '',
                isset($bookmark['icon']) ? $bookmark['icon'] : '',
                isset($bookmark['note']) ? $bookmark['note'] : '',
                isset($bookmark['position']) ? intval($bookmark['position']) : 0,
                isset($bookmark['category_weight']) ? intval($bookmark['category_weight']) : 0
            ]);
        }
        
        // 提交事务
        $pdo->commit();
        
        // 跳转到主页并显示成功提示
        header('Location: index.php?success=成功');
        exit;
    } catch (PDOException $e) {
        // 回滚事务
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // 跳转到主页并显示数据库相关错误
        header('Location: backup.php?error=数据库错误：' . htmlspecialchars($e->getMessage()) . '&clear_existing=' . (isset($_POST['clear_existing']) ? '1' : '0'));
        exit;
    } catch (Exception $e) {
        // 回滚事务（如果有）
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // 跳转到主页并显示其他错误
        header('Location: backup.php?error=导入失败：' . htmlspecialchars($e->getMessage()) . '&clear_existing=' . (isset($_POST['clear_existing']) ? '1' : '0'));
        exit;
    }
}

// 辅助函数：将 php.ini 中的文件大小配置转换为字节
function convertToBytes($value) {
    $value = trim($value);
    $last = strtolower($value[strlen($value) - 1]);
    $value = (int)$value;
    switch ($last) {
        case 'g':
            $value *= 1024;
        case 'm':
            $value *= 1024;
        case 'k':
            $value *= 1024;
    }
    return $value;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>备份与恢复 - 书签导航</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="<?php echo FAVICON; ?>">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-top">
                <a href="index.php" class="btn cancel-btn" style="margin-right: 10px;">返回主页</a>
                <div class="title-wrapper">
                    <h1>备份与恢复</h1>
                </div>
                <button id="theme-toggle" class="btn search-btn">切换主题</button>
            </div>
        </header>
        
        <!-- 显示错误提示 -->
        <?php if (isset($_GET['error'])): ?>
            <div class="error-message"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>
        
        <div class="backup-container">
            <!-- 导出备份 -->
            <div class="backup-section">
                <h2>导出备份</h2>
                <p>导出所有书签和分类数据为 JSON 文件，以便将来恢复或迁移。</p>
                <form method="post">
                    <button type="submit" name="export" class="btn save-btn">下载备份文件</button>
                </form>
            </div>
            
            <!-- 导入备份 -->
            <div class="backup-section">
                <h2>导入备份</h2>
                <p>从之前导出的 JSON 文件中恢复书签和分类数据（最大文件大小：<?php echo htmlspecialchars(ini_get('upload_max_filesize')); ?>）。</p>
                <form method="post" enctype="multipart/form-data" id="import-form" onsubmit="return validateForm()">
                    <div class="checkbox-container">
                        <input type="checkbox" id="clear_existing" name="clear_existing" value="1" <?php echo (isset($_GET['clear_existing']) && $_GET['clear_existing'] == '1') ? 'checked' : ''; ?>>
                        <label for="clear_existing">导入前清空现有数据</label>
                    </div>
                    
                    <div class="drag-drop-area" id="drag-drop-area">
                        <div class="icon">📁</div>
                        <p id="drag-drop-text">拖放备份文件到这里，或点击选择文件</p>
                        <input type="file" name="backup_file" id="backup_file" class="file-input" accept=".json" required>
                    </div>
                    
                    <div style="margin-top: 20px; text-align: right;">
                        <button type="submit" name="import" class="btn save-btn" id="import-button" disabled>导入备份</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <canvas id="sakura"></canvas>
    <script src="script.js"></script>
    <script>
        // 简化后的文件处理逻辑
        const dragDropArea = document.getElementById('drag-drop-area');
        const dragDropText = document.getElementById('drag-drop-text');
        const fileInput = document.getElementById('backup_file');
        const importButton = document.getElementById('import-button');

        // 检查 JavaScript 是否启用
        if (typeof window.alert !== 'function') {
            dragDropText.textContent = '请启用 JavaScript 以使用文件上传功能';
            importButton.disabled = true;
        }

        // 点击选择文件
        dragDropArea.addEventListener('click', () => {
            fileInput.click();
        });

        // 拖放事件
        dragDropArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            dragDropArea.classList.add('dragover');
        });

        dragDropArea.addEventListener('dragleave', () => {
            dragDropArea.classList.remove('dragover');
        });

        dragDropArea.addEventListener('drop', (e) => {
            e.preventDefault();
            dragDropArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length) {
                fileInput.files = files;
                updateFileInfo(files[0]);
            } else {
                dragDropText.textContent = '拖放失败，请点击选择文件';
            }
        });

        // 文件选择事件
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length) {
                updateFileInfo(fileInput.files[0]);
            } else {
                importButton.disabled = true;
                dragDropText.textContent = '拖放备份文件到这里，或点击选择文件';
            }
        });

        // 更新文件信息
        function updateFileInfo(file) {
            dragDropText.textContent = `已选择文件: ${file.name} (${(file.size / 1024).toFixed(2)} KB)`;
            importButton.disabled = false;
        }

        // 表单提交验证
        function validateForm() {
            if (!fileInput.files.length) {
                alert('请先选择一个备份文件！');
                return false;
            }
            return true;
        }
    </script>
    <!-- 备用提示：如果 JavaScript 被禁用 -->
    <noscript>
        <div class="error-message">
            请启用 JavaScript 以使用文件上传功能。如果无法启用，请通过点击选择文件并确保已选择文件后再提交。
        </div>
    </noscript>
</body>
</html>