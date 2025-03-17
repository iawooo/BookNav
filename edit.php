<?php
require_once 'config.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if ($id === false) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM bookmarks WHERE id = ?");
$stmt->execute([$id]);
$bookmark = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bookmark) {
    header("Location: index.php");
    exit;
}

$categories = $pdo->query("SELECT DISTINCT category FROM bookmarks WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $url = filter_var($_POST['url'], FILTER_SANITIZE_URL);
    $category = trim($_POST['category']) === 'new' ? trim($_POST['new_category']) : trim($_POST['category']);
    $note = trim($_POST['note']);
    $icon = !empty($_POST['icon']) ? filter_var($_POST['icon'], FILTER_SANITIZE_URL) : ($_POST['fetched_icon'] ?: DEFAULT_ICON);

    // 检查是否需要重新抓取图标
    $original_url = $bookmark['url'];
    $original_icon = $bookmark['icon'];
    if ($url !== $original_url || (!empty($_POST['icon']) && $_POST['icon'] !== $original_icon) || (empty($_POST['icon']) && $original_icon !== DEFAULT_ICON)) {
        // 如果 URL 或图标 URL 修改，则重新抓取图标
        $icon = $_POST['fetched_icon'] ?: DEFAULT_ICON; // 使用前端抓取的图标或默认图标
    }

    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("UPDATE bookmarks SET name = ?, url = ?, category = ?, note = ?, icon = ? WHERE id = ?");
            $stmt->execute([$name, $url, $category, $note, $icon, $id]);
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            header("Location: index.php?error=" . urlencode("更新失败: " . $e->getMessage()));
            exit;
        }
    } else {
        header("Location: index.php?error=" . urlencode("名称不能为空！"));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>编辑书签</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="<?php echo FAVICON; ?>">
</head>
<body>
    <div class="container">
        <h1>编辑书签</h1>
        <form method="POST" id="bookmarkForm">
            <label>名称:</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($bookmark['name']); ?>" required>
            <label>URL:</label>
            <input type="url" name="url" id="urlInput" value="<?php echo htmlspecialchars($bookmark['url']); ?>" required onblur="fetchFavicon()" oninput="fetchFavicon()">
            <label>分类（可选）:</label>
            <select name="category" id="categorySelect" onchange="toggleNewCategory()">
                <option value="">无分类</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $bookmark['category'] === $cat ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                <?php endforeach; ?>
                <option value="new">新建分类</option>
            </select>
            <input type="text" name="new_category" id="newCategoryInput" style="display: none;" placeholder="输入新分类名称" value="">
            <label>图标 URL（可选）:</label>
            <input type="url" name="icon" id="iconInput" value="<?php echo htmlspecialchars($bookmark['icon'] === DEFAULT_ICON ? '' : $bookmark['icon']); ?>" placeholder="留空则自动抓取或使用默认图标" oninput="updateFetchedIcon()">
            <input type="hidden" name="fetched_icon" id="fetchedIcon">
            <label>备注（可选）:</label>
            <textarea name="note"><?php echo htmlspecialchars($bookmark['note']); ?></textarea>
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
                fetchFavicon();
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
                const baseUrl = parsedUrl.origin;
                logDebug(`Processing: ${url}`);

                // Step 1: Check default favicon.ico
                logDebug("Checking for default favicon.ico...");
                const faviconUrl = `${baseUrl}/favicon.ico`;
                if (await checkImage(faviconUrl)) {
                    fetchedIcon.value = faviconUrl;
                    logDebug(`✓ Found default favicon.ico: ${faviconUrl}`);
                    return;
                }
                logDebug(`⚠ No default favicon.ico found at ${faviconUrl}`);

                // Step 2: Fetch HTML and extract icons
                logDebug("Fetching HTML to look for icon links...");
                let html = await fetchContent(baseUrl);
                if (!html) {
                    logDebug("⚠ Direct fetch failed, trying CORS proxy...");
                    html = await fetchContent(`https://api.allorigins.win/raw?url=${encodeURIComponent(baseUrl)}`);
                }
                if (html) {
                    logDebug(`✓ HTML fetched successfully`);
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const links = doc.querySelectorAll('link[rel*="icon"], link[rel="apple-touch-icon"], link[rel="apple-touch-icon-precomposed"], link[rel="mask-icon"]');
                    logDebug(`Found ${links.length} potential icon link elements`);

                    let bestIcon = null;
                    let maxSize = 0;
                    for (const link of links) {
                        const rel = link.getAttribute('rel');
                        const href = link.getAttribute('href');
                        const sizes = link.getAttribute('sizes') || '0x0';
                        if (!href) continue;

                        const absoluteUrl = resolveUrl(href, baseUrl);
                        logDebug(`Checking icon: ${rel} at ${absoluteUrl}`);
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
                        logDebug(`✓ Found valid icon: ${bestIcon}`);
                        return;
                    }

                    // Step 3: Check Web App Manifest
                    logDebug("Looking for Web App Manifest...");
                    const manifestLink = doc.querySelector('link[rel="manifest"]');
                    if (manifestLink && manifestLink.getAttribute('href')) {
                        const manifestUrl = resolveUrl(manifestLink.getAttribute('href'), baseUrl);
                        logDebug(`Found manifest at: ${manifestUrl}`);
                        const manifestContent = await fetchContent(manifestUrl) || await fetchContent(`https://api.allorigins.win/raw?url=${encodeURIComponent(manifestUrl)}`);
                        if (manifestContent) {
                            try {
                                const manifest = JSON.parse(manifestContent);
                                if (manifest.icons && Array.isArray(manifest.icons)) {
                                    let bestManifestIcon = null;
                                    let maxManifestSize = 0;
                                    for (const icon of manifest.icons) {
                                        if (icon.src) {
                                            const iconUrl = resolveUrl(icon.src, baseUrl);
                                            const sizes = icon.sizes || '0x0';
                                            const sizeParts = sizes.split('x');
                                            const size = parseInt(sizeParts[0] || 0);
                                            if (await checkImage(iconUrl)) {
                                                if (size > maxManifestSize) {
                                                    maxManifestSize = size;
                                                    bestManifestIcon = iconUrl;
                                                } else if (maxManifestSize === 0) {
                                                    bestManifestIcon = iconUrl;
                                                }
                                            }
                                        }
                                    }
                                    if (bestManifestIcon) {
                                        fetchedIcon.value = bestManifestIcon;
                                        logDebug(`✓ Found manifest icon: ${bestManifestIcon}`);
                                        return;
                                    }
                                }
                                logDebug("⚠ No valid icons in manifest");
                            } catch (e) {
                                logDebug(`Manifest parse error: ${e.message}`);
                            }
                        }
                    } else {
                        logDebug("⚠ No Web App Manifest found");
                    }

                    // Step 4: Check browserconfig.xml
                    logDebug("Checking for Microsoft browserconfig.xml...");
                    const browserConfigUrl = `${baseUrl}/browserconfig.xml`;
                    const browserConfig = await fetchContent(browserConfigUrl) || await fetchContent(`https://api.allorigins.win/raw?url=${encodeURIComponent(browserConfigUrl)}`);
                    if (browserConfig) {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(browserConfig, 'text/xml');
                        const tiles = doc.querySelectorAll('square70x70logo, square150x150logo, square310x310logo, wide310x150logo');
                        if (tiles.length > 0) {
                            let bestTileIcon = null;
                            let maxTileSize = 0;
                            for (const tile of tiles) {
                                const src = tile.getAttribute('src');
                                if (src) {
                                    const tileUrl = resolveUrl(src, baseUrl);
                                    const sizeMatch = tile.tagName.match(/(\d+)x(\d+)/);
                                    const size = sizeMatch ? parseInt(sizeMatch[1]) : 0;
                                    if (await checkImage(tileUrl)) {
                                        if (size > maxTileSize) {
                                            maxTileSize = size;
                                            bestTileIcon = tileUrl;
                                        } else if (maxTileSize === 0) {
                                            bestTileIcon = tileUrl;
                                        }
                                    }
                                }
                            }
                            if (bestTileIcon) {
                                fetchedIcon.value = bestTileIcon;
                                logDebug(`✓ Found tile icon: ${bestTileIcon}`);
                                return;
                            }
                            logDebug("⚠ No valid tile images found in browserconfig.xml");
                        }
                    } else {
                        logDebug("⚠ No browserconfig.xml found");
                    }
                } else {
                    logDebug("⚠ Failed to fetch HTML");
                }

                // Step 5: Fallback to default if no icons found
                fetchedIcon.value = '';
                logDebug("⚠ No icons found, using default");
            } catch (e) {
                fetchedIcon.value = '';
                logDebug(`Error: ${e.message}`);
            }
        }

        async function checkImage(url) {
            return new Promise((resolve) => {
                logDebug(`Checking image: ${url}`);

                // 检查是否为模糊地球图标（通过 URL 特征）
                const isDefaultIcon = url.includes('google.com') || url.includes('gstatic.com') || url.includes('default') || url.includes('generic');
                if (isDefaultIcon) {
                    logDebug(`Detected default or generic icon (likely a blurry globe): ${url}, skipping...`);
                    resolve(false);
                    return;
                }

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

        function resolveUrl(href, baseUrl) {
            try {
                return new URL(href, baseUrl).href;
            } catch (e) {
                return href.startsWith('/') ? `${baseUrl}${href}` : `${baseUrl}/${href}`;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            toggleNewCategory();
            fetchFavicon();
        });
    </script>
</body>
</html>