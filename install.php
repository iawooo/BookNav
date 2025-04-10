<?php
require_once 'config.php';

// 开启错误日志记录（调试用，可在生产环境关闭）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 如果配置文件存在但连接失败，删除它并停留在安装页面
if (file_exists(CONFIG_FILE)) {
    if (!defined('DB_HOST') || !isset($pdo)) {
        if (!unlink(CONFIG_FILE)) {
            die("无法删除无效配置文件 config.inc.php，请手动删除后重试！");
        }
    } else {
        header('Location: index.php');
        exit;
    }
}

// 检查配置文件是否存在
if (file_exists(CONFIG_FILE)) {
    $error = '配置文件 config.inc.php 已存在，请删除后再安装！';
    echo "<p style='color: red;'>$error</p>";
    exit;
}

// 初始化变量
$error = '';
$db_host = '';
$db_port = '3306';
$db_name = '';
$db_user = '';
$db_pass = '';
$site_password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取并清理用户输入
    $db_host = preg_replace('/[^a-zA-Z0-9.-]/', '', trim($_POST['db_host']));
    $db_port = preg_replace('/[^0-9]/', '', trim($_POST['db_port']) ?: '3306');
    $db_name = trim($_POST['db_name']);
    $db_user = trim($_POST['db_user']);
    $db_pass = trim($_POST['db_pass']);
    $site_password = trim($_POST['site_password']);

    try {
        // 测试数据库连接
        $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
        $pdo_test = new PDO($dsn, $db_user, $db_pass);
        $pdo_test->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 创建书签表
        $sql = "
            CREATE TABLE IF NOT EXISTS bookmarks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                url TEXT NOT NULL,
                category VARCHAR(100),
                category_weight INT DEFAULT 1,
                note TEXT,
                icon TEXT,
                position INT DEFAULT 0,
                INDEX idx_category (category),
                INDEX idx_position (position),
                INDEX idx_weight (category_weight)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $pdo_test->exec($sql);

        // 检查目录是否可写
        if (!is_writable(__DIR__)) {
            $error = '目录不可写，请将当前目录权限设置为 755 或 777';
            throw new Exception($error);
        }

        // 生成配置文件内容
        $config_content = "<?php\n";
        $config_content .= "define('DB_HOST', '" . addslashes($db_host) . "');\n";
        $config_content .= "define('DB_PORT', '" . addslashes($db_port) . "');\n";
        $config_content .= "define('DB_NAME', '" . addslashes($db_name) . "');\n";
        $config_content .= "define('DB_USER', '" . addslashes($db_user) . "');\n";
        $config_content .= "define('DB_PASS', '" . addslashes($db_pass) . "');\n";
        $config_content .= "define('SITE_PASSWORD', '" . addslashes($site_password) . "');\n";
        $config_content .= "try {\n";
        $config_content .= "    \$pdo = new PDO(\"mysql:host=\" . DB_HOST . \";port=\" . DB_PORT . \";dbname=\" . DB_NAME . \";charset=utf8mb4\", DB_USER, DB_PASS);\n";
        $config_content .= "    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n";
        $config_content .= "} catch (PDOException \$e) {\n";
        $config_content .= "    if (file_exists(__DIR__ . '/config.inc.php')) unlink(__DIR__ . '/config.inc.php');\n";
        $config_content .= "    header('Location: install.php');\n";
        $config_content .= "    exit;\n";
        $config_content .= "}\n";

        // 写入配置文件
        if (file_put_contents(CONFIG_FILE, $config_content) === false) {
            $error = '无法写入配置文件，请检查目录权限';
            throw new Exception($error);
        }

        // 验证配置文件
        require_once CONFIG_FILE;
        if (!isset($pdo)) {
            unlink(CONFIG_FILE);
            $error = '配置文件写入成功但连接仍失败，请检查参数';
            throw new Exception($error);
        }

        // 成功后跳转
        header('Location: index.php');
        exit;

    } catch (Exception $e) {
        // 处理所有异常（包括 PDOException 和普通 Exception）
        if ($e instanceof PDOException) {
            $error_code = $e->getCode();
            switch ($error_code) {
                case 1045:
                    $error = "数据库连接失败：用户名或密码错误，请检查数据库用户名和密码是否正确。";
                    break;
                case 2002:
                    $error = "数据库连接失败：无法连接到数据库主机，请检查主机地址和端口是否正确。";
                    break;
                case 1049:
                    $error = "数据库连接失败：数据库名称不存在，请确认输入的数据库名称正确。";
                    break;
                default:
                    $error = "数据库连接失败：未知错误 (" . $e->getMessage() . ")，请检查所有输入参数。";
                    break;
            }
        } else {
            $error = $e->getMessage();
        }
        // 记录错误到日志（可选）
        error_log("安装错误: " . $error . " | " . date('Y-m-d H:i:s'));
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>安装 - 书签导航</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>安装书签导航</h1>
        <?php if ($error): ?>
            <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST">
            <label>数据库主机:</label>
            <input type="text" name="db_host" value="<?php echo htmlspecialchars($db_host); ?>" required>
            <small>如 localhost（查看主机提供商控制面板）</small>
            <label>数据库端口（默认 3306）:</label>
            <input type="text" name="db_port" value="<?php echo htmlspecialchars($db_port); ?>" placeholder="3306">
            <small>通常为 3306，除非主机提供商指定其他端口</small>
            <label>数据库名称:</label>
            <input type="text" name="db_name" value="<?php echo htmlspecialchars($db_name); ?>" required>
            <label>数据库用户名:</label>
            <input type="text" name="db_user" value="<?php echo htmlspecialchars($db_user); ?>" required>
            <label>数据库密码:</label>
            <input type="password" name="db_pass" value="<?php echo htmlspecialchars($db_pass); ?>">
            <label>网站登录密码（可选）:</label>
            <input type="password" name="site_password" value="<?php echo htmlspecialchars($site_password); ?>" placeholder="留空则无需密码">
            <div class="form-buttons">
                <button type="submit" class="btn save-btn">安装</button>
            </div>
        </form>
    </div>
    <canvas id="sakura"></canvas>
    <script src="script.js"></script>
</body>
</html>