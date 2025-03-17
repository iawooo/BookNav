<?php
// config.php
define('CONFIG_FILE', __DIR__ . '/config.inc.php');

// 定义默认图标路径（确保这些文件存在于项目中）
define('DEFAULT_ICON', 'images/default-bookmark.png');
define('FAVICON', 'images/favicon.ico');

// 会话管理
session_start();

// 如果配置文件不存在，重定向到 install.php
if (!file_exists(CONFIG_FILE)) {
    if (basename($_SERVER['SCRIPT_NAME']) !== 'install.php') {
        header('Location: install.php');
        exit;
    }
} else {
    // 加载配置文件
    try {
        require_once CONFIG_FILE;
    } catch (Exception $e) {
        if (basename($_SERVER['SCRIPT_NAME']) !== 'install.php') {
            header('Location: install.php');
            exit;
        }
    }
}

// 如果配置文件存在但未正确初始化（缺少 DB_HOST 或 $pdo），跳转到 install.php
if (file_exists(CONFIG_FILE) && (!defined('DB_HOST') || !isset($pdo))) {
    if (basename($_SERVER['SCRIPT_NAME']) !== 'install.php') {
        header('Location: install.php');
        exit;
    }
}

// CSRF 令牌生成（用于表单验证）
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 登录逻辑：只有在配置文件存在且 SITE_PASSWORD 已定义时才检查
if (file_exists(CONFIG_FILE) && defined('SITE_PASSWORD')) {
    // 如果 SITE_PASSWORD 为空，自动登录
    if (empty(SITE_PASSWORD)) {
        $_SESSION['logged_in'] = true;
        // 设置一个安全的 Cookie，标记已登录，30天有效期
        setcookie('logged_in', 'auto_login', time() + 30 * 24 * 3600, '/', '', false, true); // HttpOnly 开启
    } else {
        // 检查是否已登录（通过 Session 或 Cookie）
        if (!isset($_SESSION['logged_in'])) {
            $stored_hash = isset($_COOKIE['logged_in']) ? $_COOKIE['logged_in'] : null;

            // 如果有 POST 提交的密码，验证并设置登录状态
            if (isset($_POST['password'])) {
                if ($_POST['password'] === SITE_PASSWORD) {
                    $_SESSION['logged_in'] = true;
                    // 生成安全的哈希值存储在 Cookie 中（避免明文或弱哈希如 md5）
                    $secure_hash = password_hash(SITE_PASSWORD, PASSWORD_DEFAULT);
                    setcookie('logged_in', $secure_hash, time() + 30 * 24 * 3600, '/', '', false, true);
                } else {
                    // 密码错误，显示登录页面
                    include 'login.php';
                    exit;
                }
            } elseif ($stored_hash && password_verify(SITE_PASSWORD, $stored_hash)) {
                // Cookie 有效，自动登录
                $_SESSION['logged_in'] = true;
            } else {
                // 未登录且无有效 Cookie，显示登录页面
                include 'login.php';
                exit;
            }
        }
    }
}

// 如果未定义 SITE_PASSWORD（安装后未设置），允许访问（视情况调整）
if (!defined('SITE_PASSWORD') && basename($_SERVER['SCRIPT_NAME']) !== 'install.php') {
    header('Location: install.php');
    exit;
}
?>