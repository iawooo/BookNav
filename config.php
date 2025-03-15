<?php
// config.php
define('CONFIG_FILE', __DIR__ . '/config.inc.php');

// 如果配置文件不存在，重定向到 install.php
if (!file_exists(CONFIG_FILE)) {
    if (basename($_SERVER['SCRIPT_NAME']) !== 'install.php') {
        header('Location: install.php');
        exit;
    }
} else {
    // 加载配置文件
    require_once CONFIG_FILE;
}

// 如果配置文件存在但未正确初始化（缺少 DB_HOST 或 $pdo），跳转到 install.php
if (file_exists(CONFIG_FILE) && (!defined('DB_HOST') || !isset($pdo))) {
    if (basename($_SERVER['SCRIPT_NAME']) !== 'install.php') {
        header('Location: install.php');
        exit;
    }
}

// 定义默认图标路径
define('DEFAULT_ICON', 'images/default-bookmark.png');
define('FAVICON', 'images/favicon.ico');

// 会话管理
session_start();

// 登录逻辑：只有在配置文件存在且 SITE_PASSWORD 已定义时才检查
if (file_exists(CONFIG_FILE) && defined('SITE_PASSWORD')) {
    if (empty($SITE_PASSWORD)) {
        $_SESSION['logged_in'] = true;
        setcookie('logged_in', 'auto_login', time() + 30 * 24 * 3600, '/');
    } elseif (!isset($_SESSION['logged_in']) && (!isset($_COOKIE['logged_in']) || $_COOKIE['logged_in'] !== md5($SITE_PASSWORD))) {
        if (isset($_POST['password']) && $_POST['password'] === $SITE_PASSWORD) {
            $_SESSION['logged_in'] = true;
            setcookie('logged_in', md5($SITE_PASSWORD), time() + 30 * 24 * 3600, '/');
        } else {
            include 'login.php';
            exit;
        }
    }
}
?>