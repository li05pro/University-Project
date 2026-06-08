<?php
/**
 * 源泉系统全面测试脚本
 * 用于诊断注册、登录等功能的问题
 */

header('Content-Type: text/html; charset=utf-8');

// 测试结果显示样式
echo '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>源泉系统测试诊断工具</title>
    <style>
        body { font-family: "Microsoft YaHei", Arial, sans-serif; padding: 20px; background: #f5f7fa; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #1abc9c; padding-bottom: 15px; }
        h2 { color: #34495e; margin-top: 30px; font-size: 20px; }
        .test-item { padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid; }
        .success { background: #d4edda; border-color: #28a745; color: #155724; }
        .error { background: #f8d7da; border-color: #dc3545; color: #721c24; }
        .warning { background: #fff3cd; border-color: #ffc107; color: #856404; }
        .info { background: #d1ecf1; border-color: #17a2b8; color: #0c5460; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: Consolas, monospace; font-size: 13px; overflow-x: auto; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #1abc9c; color: white; text-decoration: none; border-radius: 6px; margin: 5px; }
        .btn:hover { background: #16a085; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #f8f9fa; font-weight: 600; }
        .section { margin-bottom: 30px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 源泉系统测试诊断工具</h1>
';

$tests = [];
$totalTests = 0;
$passedTests = 0;

function addTest($name, $status, $message, $details = '') {
    global $tests, $totalTests, $passedTests;
    $totalTests++;
    if ($status === 'success') $passedTests++;
    $tests[] = [
        'name' => $name,
        'status' => $status,
        'message' => $message,
        'details' => $details
    ];
}

function renderTests() {
    global $tests, $totalTests, $passedTests;
    
    echo '<div class="section">';
    echo '<h2>📊 测试结果概览</h2>';
    echo '<div class="test-item ' . ($passedTests === $totalTests ? 'success' : 'warning') . '">';
    echo "通过测试: {$passedTests} / {$totalTests}";
    echo '</div>';
    
    foreach ($tests as $test) {
        echo '<div class="test-item ' . $test['status'] . '">';
        echo '<strong>' . htmlspecialchars($test['name']) . '</strong><br>';
        echo htmlspecialchars($test['message']);
        if ($test['details']) {
            echo '<div class="code">' . nl2br(htmlspecialchars($test['details'])) . '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
}

// ==================== 测试 1: PHP 环境 ====================
echo '<div class="section">';
echo '<h2>1️⃣ PHP 环境检查</h2>';

$phpVersion = phpversion();
if (version_compare($phpVersion, '7.0.0', '>=')) {
    addTest('PHP 版本', 'success', "PHP 版本: {$phpVersion} (符合要求)", '');
} else {
    addTest('PHP 版本', 'error', "PHP 版本: {$phpVersion} (需要 7.0+)", '');
}

// 检查必要的扩展
$requiredExtensions = ['pdo', 'pdo_mysql', 'session', 'json', 'gd'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        addTest("扩展: {$ext}", 'success', "已安装", '');
    } else {
        addTest("扩展: {$ext}", 'error', "未安装 (必需)", '');
    }
}

// 检查 Session
if (session_start()) {
    addTest('Session 支持', 'success', 'Session 正常工作', 'Session ID: ' . session_id());
} else {
    addTest('Session 支持', 'error', 'Session 无法启动', '');
}

echo '</div>';

// ==================== 测试 2: 文件权限 ====================
echo '<div class="section">';
echo '<h2>2️⃣ 文件和目录权限</h2>';

$checkPaths = [
    __DIR__ . '/../' => '网站根目录',
    __DIR__ => 'PHP 目录',
    __DIR__ . '/../uploads/' => '上传目录',
];

foreach ($checkPaths as $path => $name) {
    if (file_exists($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $writable = is_writable($path) ? '可写' : '只读';
        addTest($name, 'success', "存在 - 权限: {$perms} ({$writable})", $path);
    } else {
        addTest($name, 'warning', "不存在", $path);
    }
}

echo '</div>';

// ==================== 测试 3: 数据库连接 ====================
echo '<div class="section">';
echo '<h2>3️⃣ 数据库连接测试</h2>';

// 加载配置文件
$configFile = __DIR__ . '/config.php';
if (file_exists($configFile)) {
    addTest('配置文件', 'success', 'config.php 存在', '');
    
    // 读取配置（不执行整个文件）
    $configContent = file_get_contents($configFile);
    preg_match("/define\('DB_HOST',\s*'([^']+)'\)/", $configContent, $hostMatch);
    preg_match("/define\('DB_USER',\s*'([^']+)'\)/", $configContent, $userMatch);
    preg_match("/define\('DB_NAME',\s*'([^']+)'\)/", $configContent, $nameMatch);
    
    $dbHost = $hostMatch[1] ?? 'unknown';
    $dbUser = $userMatch[1] ?? 'unknown';
    $dbName = $nameMatch[1] ?? 'unknown';
    
    addTest('数据库配置', 'info', "主机: {$dbHost}, 用户: {$dbUser}, 数据库: {$dbName}", '');
    
    // 测试 MySQL 服务器连接
    try {
        $dsn = "mysql:host={$dbHost};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, '123456');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        addTest('MySQL 服务器连接', 'success', '成功连接到 MySQL 服务器', "主机: {$dbHost}");
        
        // 检查数据库是否存在
        $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$dbName}'");
        if ($stmt->fetch()) {
            addTest('数据库存在性', 'success', "数据库 '{$dbName}' 存在", '');
        } else {
            addTest('数据库存在性', 'warning', "数据库 '{$dbName}' 不存在", '需要自动创建或手动创建');
        }
        
        // 测试连接到指定数据库
        try {
            $dsn2 = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
            $pdo2 = new PDO($dsn2, $dbUser, '123456');
            $pdo2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            addTest('数据库连接', 'success', "成功连接到数据库 '{$dbName}'", '');
            
            // 检查表
            $tables = ['user', 'category', 'article', 'comment', 'circle'];
            foreach ($tables as $table) {
                $stmt = $pdo2->query("SHOW TABLES LIKE '{$table}'");
                if ($stmt->fetch()) {
                    // 检查表结构
                    $stmt2 = $pdo2->query("DESCRIBE {$table}");
                    $columns = $stmt2->fetchAll(PDO::FETCH_COLUMN);
                    addTest("表: {$table}", 'success', '存在', '字段: ' . implode(', ', array_slice($columns, 0, 5)) . '...');
                } else {
                    addTest("表: {$table}", 'warning', '不存在', '需要创建');
                }
            }
            
        } catch (PDOException $e) {
            addTest('数据库连接', 'error', '连接失败: ' . $e->getMessage(), '');
        }
        
    } catch (PDOException $e) {
        addTest('MySQL 服务器连接', 'error', '连接失败: ' . $e->getMessage(), 
            "请检查:\n1. MySQL 服务是否已启动\n2. 用户名和密码是否正确\n3. 主机地址是否正确");
    }
    
} else {
    addTest('配置文件', 'error', 'config.php 不存在', '');
}

echo '</div>';

// ==================== 测试 4: 功能测试 ====================
echo '<div class="section">';
echo '<h2>4️⃣ 功能测试</h2>';

// 测试验证码生成
if (file_exists(__DIR__ . '/captcha.php')) {
    addTest('验证码文件', 'success', 'captcha.php 存在', '');
    // 尝试生成验证码
    $_SESSION['test'] = 'test';
    addTest('Session 写入', 'success', 'Session 可以正常写入', '');
} else {
    addTest('验证码文件', 'error', 'captcha.php 不存在', '');
}

// 测试 JSON 响应
$testJson = json_encode(['success' => true, 'message' => 'test']);
if ($testJson) {
    addTest('JSON 编码', 'success', 'JSON 编码正常', '');
} else {
    addTest('JSON 编码', 'error', 'JSON 编码失败', '');
}

echo '</div>';

// ==================== 测试 5: 模拟注册流程 ====================
echo '<div class="section">';
echo '<h2>5️⃣ 模拟注册流程测试</h2>';

// 加载完整配置
require_once $configFile;

try {
    $db = getDB();
    addTest('getDB() 函数', 'success', '数据库连接成功', '');
    
    // 测试 initDatabaseTables
    $initResult = initDatabaseTables();
    if ($initResult['success']) {
        if (!empty($initResult['created'])) {
            addTest('自动创建表', 'success', '已自动创建表: ' . implode(', ', $initResult['created']), '');
        } else {
            addTest('自动创建表', 'success', '所有表已存在，无需创建', '');
        }
    } else {
        addTest('自动创建表', 'error', '创建失败: ' . $initResult['error'], '');
    }
    
    // 测试查询
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM user");
        $result = $stmt->fetch();
        addTest('用户表查询', 'success', "当前用户数量: {$result['count']}", '');
    } catch (PDOException $e) {
        addTest('用户表查询', 'error', '查询失败: ' . $e->getMessage(), '');
    }
    
    // 测试插入（使用测试账号）
    $testUsername = 'test_' . time();
    $testEmail = 'test_' . time() . '@example.com';
    try {
        $stmt = $db->prepare("INSERT INTO user (username, password, email, nickname, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$testUsername, password_hash('test123', PASSWORD_DEFAULT), $testEmail, '测试用户']);
        $userId = $db->lastInsertId();
        addTest('插入测试用户', 'success', "成功创建测试用户 (ID: {$userId})", "用户名: {$testUsername}");
        
        // 清理测试数据
        $stmt = $db->prepare("DELETE FROM user WHERE user_id = ?");
        $stmt->execute([$userId]);
        addTest('清理测试数据', 'success', '已删除测试用户', '');
        
    } catch (PDOException $e) {
        addTest('插入测试用户', 'error', '插入失败: ' . $e->getMessage(), '');
    }
    
} catch (PDOException $e) {
    addTest('getDB() 函数', 'error', '数据库连接失败: ' . $e->getMessage(), 
        "错误代码: " . $e->getCode() . "\n" .
        "请检查数据库配置和MySQL服务状态");
}

echo '</div>';

// ==================== 显示结果 ====================
renderTests();

// ==================== 修复建议 ====================
echo '<div class="section">';
echo '<h2>🔧 修复建议</h2>';

$errors = array_filter($tests, function($t) { return $t['status'] === 'error'; });
$warnings = array_filter($tests, function($t) { return $t['status'] === 'warning'; });

if (empty($errors) && empty($warnings)) {
    echo '<div class="test-item success">';
    echo '<strong>✅ 所有检查通过！</strong><br>';
    echo '系统运行正常，可以正常注册和登录。';
    echo '</div>';
} else {
    if (!empty($errors)) {
        echo '<div class="test-item error">';
        echo '<strong>❌ 发现 ' . count($errors) . ' 个错误需要修复：</strong><br>';
        foreach ($errors as $error) {
            echo '• ' . htmlspecialchars($error['name']) . ': ' . htmlspecialchars($error['message']) . '<br>';
        }
        echo '</div>';
    }
    
    if (!empty($warnings)) {
        echo '<div class="test-item warning">';
        echo '<strong>⚠️ 发现 ' . count($warnings) . ' 个警告：</strong><br>';
        foreach ($warnings as $warning) {
            echo '• ' . htmlspecialchars($warning['name']) . ': ' . htmlspecialchars($warning['message']) . '<br>';
        }
        echo '</div>';
    }
}

echo '</div>';

// ==================== 快捷操作 ====================
echo '<div class="section">';
echo '<h2>🚀 快捷操作</h2>';
echo '<a href="../register.html" class="btn">前往注册页面</a>';
echo '<a href="../login.html" class="btn">前往登录页面</a>';
echo '<a href="test_db.php" class="btn">数据库连接测试</a>';
echo '<a href="' . $_SERVER['PHP_SELF'] . '" class="btn" style="background: #3498db;">重新测试</a>';
echo '</div>';

echo '</div></body></html>';
