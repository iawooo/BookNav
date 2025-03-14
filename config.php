<?php
$db_host = 'localhost';           // 数据库主机，通常为 localhost
$db_name = 'bookmark_navigator';  // 刚创建的数据库名
$db_user = 'your_username';       // MySQL 用户名
$db_pass = 'your_password';       // MySQL 密码

$site_password = 'your_custom_password'; // 自定义密码

define('DEFAULT_ICON', 'images/default-bookmark.png'); // 相对路径
define('FAVICON', 'images/favicon.ico');

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}

session_start();

// 如果密码为空，自动登录
if (empty($site_password)) {
    $_SESSION['logged_in'] = true;
    setcookie('logged_in', 'auto_login', time() + 30 * 24 * 3600, '/');
}
// 如果没有登录并且没有有效的cookie
else if (!isset($_SESSION['logged_in']) && (!isset($_COOKIE['logged_in']) || $_COOKIE['logged_in'] !== md5($site_password))) {
    if (isset($_POST['password']) && $_POST['password'] === $site_password) {
        $_SESSION['logged_in'] = true;
        setcookie('logged_in', md5($site_password), time() + 30 * 24 * 3600, '/');
    } else {
        include 'login.php';
        exit;
    }
}
?>
