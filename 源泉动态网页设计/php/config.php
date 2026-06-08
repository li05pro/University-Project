<?php
/**
 * 源泉动态网站 - 数据库配置文件
 */

// 数据库配置
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '123456';
$db_name = 'root';
$db_charset = 'utf8mb4';

// 网站配置
$site_name = '源泉';
$site_url = 'http://localhost/源泉';
$upload_path = __DIR__ . '/../uploads/';
$upload_url = $site_url . '/uploads/';

// 分页配置
$page_size = 10;

// 存储配置到常量
define('DB_HOST', $db_host);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass);
define('DB_NAME', $db_name);
define('DB_CHARSET', $db_charset);
define('SITE_NAME', $site_name);
define('SITE_URL', $site_url);
define('UPLOAD_PATH', $upload_path);
define('UPLOAD_URL', $upload_url);
define('PAGE_SIZE', $page_size);

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 开启错误显示（开发环境）
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * 获取数据库连接（带自动创建数据库功能）
 * @return PDO
 * @throws PDOException
 */
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        // 先尝试连接MySQL服务器（不指定数据库）
        try {
            $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
            $tempPdo = new PDO($dsn, DB_USER, DB_PASS);
            $tempPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // 检查数据库是否存在，不存在则创建
            $stmt = $tempPdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
            if (!$stmt->fetch()) {
                // 数据库不存在，创建数据库
                $tempPdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci");
            }
            $tempPdo = null; // 关闭临时连接
        } catch (PDOException $e) {
            throw new PDOException("数据库服务器连接失败: " . $e->getMessage());
        }

        // 连接到指定数据库
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    return $pdo;
}

/**
 * 检查并创建数据表
 * @return array 返回创建结果
 */
function initDatabaseTables() {
    try {
        $db = getDB();
        $created = [];

        // 检查user表是否存在
        $stmt = $db->query("SHOW TABLES LIKE 'user'");
        if (!$stmt->fetch()) {
            // 创建用户表
            $db->exec("CREATE TABLE IF NOT EXISTS user (
                user_id INT PRIMARY KEY AUTO_INCREMENT COMMENT '用户ID',
                username VARCHAR(50) NOT NULL UNIQUE COMMENT '用户名',
                password VARCHAR(255) NOT NULL COMMENT '密码',
                email VARCHAR(100) NOT NULL UNIQUE COMMENT '邮箱地址',
                avatar VARCHAR(255) DEFAULT 'default.jpg' COMMENT '头像路径',
                nickname VARCHAR(50) NOT NULL COMMENT '昵称',
                bio TEXT NULL COMMENT '个人简介',
                phone VARCHAR(20) NULL COMMENT '联系电话',
                status TINYINT DEFAULT 1 COMMENT '账号状态 (1=正常, 0=禁用)',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '注册时间',
                last_login DATETIME NULL COMMENT '最后登录时间',
                INDEX idx_username (username),
                INDEX idx_email (email),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表'");
            $created[] = 'user';
        }

        return ['success' => true, 'created' => $created];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * 安全过滤函数
 * @param string $data
 * @return string
 */
function clean($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * 生成JSON响应
 * @param bool $success
 * @param string $message
 * @param array $data
 */
function jsonResponse($success, $message = '', $data = []) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/**
 * 检查用户是否登录
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/**
 * 获取当前登录用户信息
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT user_id, username, email, avatar, nickname, bio, phone, created_at FROM user WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * 重定向函数
 * @param string $url
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * 生成随机字符串
 * @param int $length
 * @return string
 */
function randomString($length = 6) {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $result = '';
    for ($i = 0; $i < $length; $i++) {
        $result .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $result;
}

/**
 * 上传文件处理
 * @param array $file
 * @param string $subdir
 * @return string|false
 */
function uploadFile($file, $subdir = '') {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return false;
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }

    if ($file['size'] > $max_size) {
        return false;
    }

    $upload_dir = UPLOAD_PATH . ($subdir ? $subdir . '/' : '');
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . randomString(8) . '.' . $ext;
    $filepath = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return 'uploads/' . ($subdir ? $subdir . '/' : '') . $filename;
    }

    return false;
}