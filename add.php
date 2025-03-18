<?php
require_once 'config.php';

$categories = $pdo->query("SELECT DISTINCT category FROM bookmarks WHERE category IS NOT NULL AND category != '' ORDER BY category_weight DESC, category")->fetchAll(PDO::FETCH_COLUMN);
$predefined_category = isset($_GET['category']) ? htmlspecialchars($_GET['category']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $url = filter_var($_POST['url'], FILTER_SANITIZE_URL);
    $category = trim($_POST['category']) === 'new' ? trim($_POST['new_category']) : trim($_POST['category']);
    $category_weight = isset($_POST['category_weight']) ? (int)$_POST['category_weight'] : 1;
    $note = trim($_POST['note']);
    $icon = !empty($_POST['icon']) ? filter_var($_POST['icon'], FILTER_SANITIZE_URL) : ($_POST['fetched_icon'] ?: DEFAULT_ICON);

    if (!empty($name)) {
        try {
            // 检查是否重复
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookmarks WHERE name = ? AND url = ?");
            $stmt->execute([$name, $url]);
            if ($stmt->fetchColumn() > 0) {
                header('Content-Type: application/json');
                echo json_encode(['error' => '此书签已存在！']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO bookmarks (name, url, category, category_weight, note, icon, position) VALUES (?, ?, ?, ?, ?, ?, 0)");
            $stmt->execute([$name, $url, $category, $category_weight, $note, $icon]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true]); // 返回成功 JSON
            exit;
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode(['error' => '保存失败: ' . $e->getMessage()]);
            exit;
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => '名称不能为空！']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>添加书签</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="<?php echo FAVICON; ?>">
</head>
<body>
    <div class="container">
        <h1>添加书签</h1>
        <form method="POST" id="bookmarkForm">
            <label>名称:</label>
            <input type="text" name="name" required>
            <div class="error-message" id="name-error"></div>
            <label>URL:</label>
            <input type="url" name="url" id="urlInput" required onblur="fetchFavicon()" oninput="fetchFavicon()">
            <label>分类（可选）:</label>
            <select name="category" id="categorySelect" onchange="toggleNewCategory()">
                <option value="">无分类</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $predefined_category === $cat ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                <?php endforeach; ?>
                <option value="new">新建分类</option>
            </select>
            <input type="text" name="new_category" id="newCategoryInput" style="display: none;" placeholder="输入新分类名称">
            <label>分类权重（1最小，越大越靠前）:</label>
            <input type="number" name="category_weight" min="1" value="1">
            <label>图标 URL（可选）:</label>
            <input type="url" name="icon" id="iconInput" placeholder="留空则自动抓取或使用默认图标" oninput="updateFetchedIcon()">
            <input type="hidden" name="fetched_icon" id="fetchedIcon">
            <div class="error-message" id="form-error"></div>
            <label>备注（可选）:</label>
            <textarea name="note"></textarea>
            <div class="form-buttons">
                <button type="submit" class="btn save-btn" id="saveButton">保存</button>
                <a href="index.php" class="btn cancel-btn">取消</a>
            </div>
        </form>
    </div>
    <canvas id="sakura"></canvas>
    <script src="script.js"></script>
    <script>
        function toggleNewCategory() {
            const select = document.getElementById('categorySelect');
            const newInput = document.getElementById('newCategoryInput');
            newInput.style.display = select.value === 'new' ? 'block' : 'none';
        }

        function updateFetchedIcon() {
            const iconInput = document.getElementById('iconInput').value;
            const fetchedIcon = document.getElementById('fetchedIcon');
            if (!iconInput) {
                fetchFavicon();
            } else {
                fetchedIcon.value = '';
            }
        }

        async function fetchFavicon() {
            const urlInput = document.getElementById('urlInput').value;
            const iconInput = document.getElementById('iconInput').value;
            const fetchedIcon = document.getElementById('fetchedIcon');

            if (iconInput || !urlInput) {
                fetchedIcon.value = '';
                return;
            }

            let url = urlInput;
            if (!url.match(/^(?:f|ht)tps?:\/\//i)) {
                url = 'https://' + url;
            }

            try {
                const parsedUrl = new URL(url);
                const domain = parsedUrl.hostname;
                const baseUrl = parsedUrl.origin;

                let html = await fetchContent(baseUrl) || await fetchContent(`https://api.allorigins.win/raw?url=${encodeURIComponent(baseUrl)}`);
                if (html) {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const links = doc.querySelectorAll('link[rel*="icon"], link[rel="apple-touch-icon"], link[rel="apple-touch-icon-precomposed"]');
                    let bestIcon = null;
                    let maxSize = 0;
                    for (const link of links) {
                        const href = link.getAttribute('href');
                        const sizes = link.getAttribute('sizes') || '0x0';
                        if (!href) continue;

                        const absoluteUrl = href.startsWith('http') ? href : new URL(href, baseUrl).href;
                        const sizeParts = sizes.split('x');
                        const size = parseInt(sizeParts[0] || 0);
                        if (await checkImage(absoluteUrl)) {
                            if (size > maxSize) {
                                maxSize = size;
                                bestIcon = absoluteUrl;
                            } else if (maxSize === 0) {
                                bestIcon = absoluteUrl;
                            }
                        }
                    }
                    if (bestIcon) {
                        fetchedIcon.value = bestIcon;
                        return;
                    }
                }

                const faviconUrl = `${parsedUrl.origin}/favicon.ico`;
                if (await checkImage(faviconUrl)) {
                    fetchedIcon.value = faviconUrl;
                    return;
                }

                const googleFavicon = `https://t0.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&url=${encodeURIComponent(parsedUrl.href)}&size=64`;
                if (await checkImage(googleFavicon)) {
                    fetchedIcon.value = googleFavicon;
                    return;
                }

                fetchedIcon.value = '';
            } catch (e) {
                fetchedIcon.value = '';
            }
        }

        async function checkImage(url) {
            return new Promise((resolve) => {
                const img = new Image();
                img.onload = () => resolve(true);
                img.onerror = () => resolve(false);
                img.src = url;
                setTimeout(() => resolve(false), 5000);
            });
        }

        async function fetchContent(url) {
            try {
                const response = await fetch(url, { mode: 'cors' });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return await response.text();
            } catch (e) {
                return null;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            toggleNewCategory();
            fetchFavicon();

            const form = document.getElementById('bookmarkForm');
            const saveButton = document.getElementById('saveButton');
            const formError = document.getElementById('form-error');

            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                saveButton.disabled = true;
                saveButton.textContent = '保存中...';
                formError.style.display = 'none';

                const formData = new FormData(form);
                try {
                    const response = await fetch('add.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (result.error) {
                        formError.textContent = result.error;
                        formError.style.display = 'block';
                        saveButton.disabled = false;
                        saveButton.textContent = '保存';
                    } else if (result.success) {
                        window.location.href = 'index.php'; // 成功后跳转
                    }
                } catch (error) {
                    formError.textContent = '提交失败，请检查网络连接';
                    formError.style.display = 'block';
                    saveButton.disabled = false;
                    saveButton.textContent = '保存';
                }
            });
        });
    </script>
</body>
</html>