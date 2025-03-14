<?php
require_once 'config.php';

$categories = $pdo->query("SELECT DISTINCT category FROM bookmarks WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$predefined_category = isset($_GET['category']) ? htmlspecialchars($_GET['category']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $url = filter_var($_POST['url'], FILTER_SANITIZE_URL);
    $category = trim($_POST['category']) === 'new' ? trim($_POST['new_category']) : trim($_POST['category']);
    $note = trim($_POST['note']);
    $icon = !empty($_POST['icon']) ? filter_var($_POST['icon'], FILTER_SANITIZE_URL) : ($_POST['fetched_icon'] ?: DEFAULT_ICON);

    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO bookmarks (NAME, url, category, note, icon, POSITION) VALUES (?, ?, ?, ?, ?, 0)");
            $stmt->execute([$name, $url, $category, $note, $icon]);
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            echo "保存失败: " . $e->getMessage();
            exit;
        }
    } else {
        echo "名称不能为空！";
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
            <label>图标 URL（可选）:</label>
            <input type="url" name="icon" id="iconInput" placeholder="留空则自动抓取或使用默认图标" oninput="updateFetchedIcon()">
            <input type="hidden" name="fetched_icon" id="fetchedIcon">
            <label>备注（可选）:</label>
            <textarea name="note"></textarea>
            <div class="form-buttons">
                <button type="submit" class="btn save-btn">保存</button>
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

        function logDebug(message) {
            console.log(`[Favicon Fetch] ${message}`);
        }

        function updateFetchedIcon() {
            const iconInput = document.getElementById('iconInput').value;
            const fetchedIcon = document.getElementById('fetchedIcon');
            if (iconInput) {
                fetchedIcon.value = '';
                logDebug("User provided icon, clearing fetched icon");
            } else {
                fetchFavicon(); // Re-trigger fetch if icon is cleared
            }
        }

        function fetchFavicon() {
            const urlInput = document.getElementById('urlInput').value;
            const iconInput = document.getElementById('iconInput').value;
            const fetchedIcon = document.getElementById('fetchedIcon');

            if (iconInput) {
                fetchedIcon.value = '';
                logDebug("Icon input provided, skipping fetch");
                return;
            }

            if (!urlInput) {
                fetchedIcon.value = '';
                logDebug("URL is empty, resetting fetched icon");
                return;
            }

            let url = urlInput;
            if (!url.match(/^(?:f|ht)tps?:\/\//i)) {
                url = 'https://' + url;
                logDebug(`Added protocol: ${url}`);
            }

            try {
                const parsedUrl = new URL(url);
                const domain = parsedUrl.hostname;
                const rootDomain = domain.split('.').slice(-2).join('.'); // e.g., rvv.pp.ua
                logDebug(`Parsed URL: ${parsedUrl.href}, Domain: ${domain}, Root Domain: ${rootDomain}`);

                // Step 1: Google FaviconV2 with full URL
                const googleFavicon = `https://t0.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&url=${encodeURIComponent(parsedUrl.href)}&size=64`;
                logDebug(`Trying Google FaviconV2 (full URL): ${googleFavicon}`);
                checkImage(googleFavicon, (exists) => {
                    if (exists) {
                        fetchedIcon.value = googleFavicon;
                        logDebug(`Google FaviconV2 found: ${googleFavicon}`);
                        return;
                    }

                    // Step 2: Google FaviconV2 with root domain
                    const googleFaviconRoot = `https://t0.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&url=https://${rootDomain}&size=64`;
                    logDebug(`Trying Google FaviconV2 (root domain): ${googleFaviconRoot}`);
                    checkImage(googleFaviconRoot, (exists) => {
                        if (exists) {
                            fetchedIcon.value = googleFaviconRoot;
                            logDebug(`Google FaviconV2 found (root): ${googleFaviconRoot}`);
                            return;
                        }

                        // Step 3: Subdomain favicon.ico
                        const faviconUrl = `${parsedUrl.origin}/favicon.ico`;
                        logDebug(`Trying subdomain favicon: ${faviconUrl}`);
                        checkImage(faviconUrl, (exists) => {
                            if (exists) {
                                fetchedIcon.value = faviconUrl;
                                logDebug(`Subdomain favicon found: ${faviconUrl}`);
                                return;
                            }

                            // Step 4: Root domain favicon.ico
                            const rootFaviconUrl = `https://${rootDomain}/favicon.ico`;
                            logDebug(`Trying root domain favicon: ${rootFaviconUrl}`);
                            checkImage(rootFaviconUrl, (exists) => {
                                fetchedIcon.value = exists ? rootFaviconUrl : '';
                                logDebug(exists ? `Root favicon found: ${rootFaviconUrl}` : 'No favicon found');
                            });
                        });
                    });
                });
            } catch (e) {
                fetchedIcon.value = '';
                logDebug(`URL parsing error: ${e.message}`);
            }
        }

        function checkImage(url, callback) {
            logDebug(`Checking image: ${url}`);
            const img = new Image();
            img.onload = () => {
                logDebug(`Image loaded: ${url}`);
                callback(true);
            };
            img.onerror = () => {
                logDebug(`Image failed to load: ${url}`);
                callback(false);
            };
            img.src = url;
            setTimeout(() => {
                if (!img.complete) {
                    logDebug(`Image load timeout: ${url}`);
                    callback(false);
                }
            }, 5000);
        }

        document.addEventListener('DOMContentLoaded', () => {
            toggleNewCategory();
            fetchFavicon(); // Trigger fetch on page load if URL exists
        });
    </script>
</body>
</html>