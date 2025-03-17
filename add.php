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
            $stmt = $pdo->prepare("INSERT INTO bookmarks (name, url, category, note, icon, position) VALUES (?, ?, ?, ?, ?, 0)");
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
            if (!iconInput) {
                logDebug("Icon input cleared or empty, triggering fetch");
                fetchFavicon();
            } else {
                fetchedIcon.value = '';
                logDebug("User provided icon, clearing fetched icon");
            }
        }

        async function checkImage(url) {
            return new Promise((resolve) => {
                logDebug(`Checking image: ${url}`);
                const img = new Image();
                img.onload = () => {
                    logDebug(`Image loaded: ${url}`);
                    resolve(true);
                };
                img.onerror = () => {
                    logDebug(`Image failed to load: ${url}`);
                    resolve(false);
                };
                img.src = url;
                setTimeout(() => {
                    if (!img.complete) {
                        logDebug(`Image load timeout: ${url}`);
                        resolve(false);
                    }
                }, 5000);
            });
        }

        async function fetchContent(url) {
            try {
                logDebug(`Fetching content from: ${url}`);
                const response = await fetch(url, { mode: 'cors' });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const content = await response.text();
                logDebug(`Content fetched: ${content.substring(0, 50)}...`);
                return content;
            } catch (e) {
                logDebug(`Fetch failed: ${e.message}`);
                return null;
            }
        }

        async function fetchFavicon() {
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
                const rootDomain = domain.split('.').slice(-2).join('.');
                const baseUrl = parsedUrl.origin;
                logDebug(`Parsed URL: ${parsedUrl.href}, Domain: ${domain}, Root Domain: ${rootDomain}`);

                // Step 1: Check HTML for <link rel="icon">
                logDebug("Fetching HTML to look for icon links...");
                let html = await fetchContent(baseUrl) || await fetchContent(`https://api.allorigins.win/raw?url=${encodeURIComponent(baseUrl)}`);
                if (html) {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const links = doc.querySelectorAll('link[rel*="icon"], link[rel="apple-touch-icon"], link[rel="apple-touch-icon-precomposed"]');
                    logDebug(`Found ${links.length} potential icon link elements`);

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
                        logDebug(`Found valid icon in HTML: ${bestIcon}`);
                        return;
                    }
                }

                // Step 2: Subdomain favicon.ico
                const faviconUrl = `${parsedUrl.origin}/favicon.ico`;
                logDebug(`Trying subdomain favicon: ${faviconUrl}`);
                if (await checkImage(faviconUrl)) {
                    fetchedIcon.value = faviconUrl;
                    logDebug(`Subdomain favicon found: ${faviconUrl}`);
                    return;
                }

                // Step 3: Root domain favicon.ico
                const rootFaviconUrl = `https://${rootDomain}/favicon.ico`;
                logDebug(`Trying root domain favicon: ${rootFaviconUrl}`);
                if (await checkImage(rootFaviconUrl)) {
                    fetchedIcon.value = rootFaviconUrl;
                    logDebug(`Root favicon found: ${rootFaviconUrl}`);
                    return;
                }

                // Step 4: Google FaviconV2 with full URL (fallback)
                const googleFavicon = `https://t0.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&url=${encodeURIComponent(parsedUrl.href)}&size=64`;
                logDebug(`Trying Google FaviconV2 (full URL): ${googleFavicon}`);
                if (await checkImage(googleFavicon)) {
                    fetchedIcon.value = googleFavicon;
                    logDebug(`Google FaviconV2 found: ${googleFavicon}`);
                    return;
                }

                fetchedIcon.value = '';
                logDebug('No favicon found');
            } catch (e) {
                fetchedIcon.value = '';
                logDebug(`Error: ${e.message}`);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            toggleNewCategory();
            fetchFavicon();
        });

        document.getElementById('bookmarkForm').addEventListener('submit', async (e) => {
            const iconInput = document.getElementById('iconInput').value;
            const fetchedIcon = document.getElementById('fetchedIcon');
            if (!iconInput && !fetchedIcon.value) {
                e.preventDefault();
                logDebug("No icon provided or fetched yet, fetching now...");
                await fetchFavicon();
                logDebug("Favicon fetch completed, submitting form...");
                e.target.submit();
            }
        });
    </script>
</body>
</html>