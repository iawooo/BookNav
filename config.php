<?php
$db_host = 'localhost';
$db_name = 'raybee9_dh';
$db_user = 'raybee9_dh';
$db_pass = 'qq965868345';

$site_password = 'qq965868345';

define('DEFAULT_ICON', 'images/default-bookmark.png'); // 相对路径
define('FAVICON', 'images/favicon.ico');

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}

session_start();
if (!isset($_SESSION['logged_in']) && (!isset($_COOKIE['logged_in']) || $_COOKIE['logged_in'] !== md5($site_password))) {
    if (isset($_POST['password']) && $_POST['password'] === $site_password) {
        $_SESSION['logged_in'] = true;
        setcookie('logged_in', md5($site_password), time() + 30 * 24 * 3600, '/');
    } else {
        include 'login.php';
        exit;
    }
}
?>