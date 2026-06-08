<?php
/**
 * 命令行注册测试脚本
 */

require_once 'config.php';

echo "========================================\n";
echo "    源泉注册流程测试 (CLI)\n";
echo "========================================\n\n";

// 测试数据
$testData = [
    'username' => 'testuser_' . time(),
    'email' => 'test_' . time() . '@example.com',
    'nickname' => '测试用户' . rand(1000, 9999),
    'password' => 'test123',
    'confirm_password' => 'test123',
    'captcha' => '123456'
];

echo "【测试数据】\n";
foreach ($testData as $key => $value) {
    if ($key === 'password' || $key === 'confirm_password') {
        echo "  {$key}: ******\n";
    } else {
        echo "  {$key}: {$value}\n";
    }
}
echo "\n";

// 步骤1: 检查数据库连接
echo "步骤 1: 检查数据库连接...\n";
try {
    $db = getDB();
    echo "  ✓ 数据库连接成功\n\n";
} catch (PDOException $e) {
    echo "  ✗ 数据库连接失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 步骤2: 初始化数据表
echo "步骤 2: 检查/创建数据表...\n";
$initResult = initDatabaseTables();
if ($initResult['success']) {
    if (!empty($initResult['created'])) {
        echo "  ✓ 已自动创建表: " . implode(', ', $initResult['created']) . "\n\n";
    } else {
        echo "  ✓ 所有表已存在\n\n";
    }
} else {
    echo "  ✗ 初始化失败: " . $initResult['error'] . "\n";
    exit(1);
}

// 步骤3: 检查用户名和邮箱是否已存在
echo "步骤 3: 检查用户名和邮箱...\n";
$stmt = $db->prepare("SELECT user_id FROM user WHERE username = ?");
$stmt->execute([$testData['username']]);
if ($stmt->fetch()) {
    echo "  ✗ 用户名已存在\n";
    exit(1);
}
echo "  ✓ 用户名可用\n";

$stmt = $db->prepare("SELECT user_id FROM user WHERE email = ?");
$stmt->execute([$testData['email']]);
if ($stmt->fetch()) {
    echo "  ✗ 邮箱已存在\n";
    exit(1);
}
echo "  ✓ 邮箱可用\n\n";

// 步骤4: 插入用户数据
echo "步骤 4: 插入用户数据...\n";
try {
    $passwordHash = password_hash($testData['password'], PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO user (username, password, email, nickname, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([
        $testData['username'],
        $passwordHash,
        $testData['email'],
        $testData['nickname']
    ]);
    $userId = $db->lastInsertId();
    echo "  ✓ 用户创建成功 (ID: {$userId})\n\n";
} catch (PDOException $e) {
    echo "  ✗ 插入失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 步骤5: 验证用户是否插入成功
echo "步骤 5: 验证用户数据...\n";
$stmt = $db->prepare("SELECT user_id, username, email, nickname, created_at FROM user WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($user) {
    echo "  ✓ 用户数据验证成功\n";
    echo "    - 用户ID: {$user['user_id']}\n";
    echo "    - 用户名: {$user['username']}\n";
    echo "    - 邮箱: {$user['email']}\n";
    echo "    - 昵称: {$user['nickname']}\n";
    echo "    - 注册时间: {$user['created_at']}\n\n";
} else {
    echo "  ✗ 无法找到刚创建的用户\n";
    exit(1);
}

// 步骤6: 测试登录验证
echo "步骤 6: 测试登录验证...\n";
$stmt = $db->prepare("SELECT user_id, username, password FROM user WHERE username = ?");
$stmt->execute([$testData['username']]);
$user = $stmt->fetch();

if ($user && password_verify($testData['password'], $user['password'])) {
    echo "  ✓ 密码验证成功\n\n";
} else {
    echo "  ✗ 密码验证失败\n";
    exit(1);
}

// 步骤7: 清理测试数据
echo "步骤 7: 清理测试数据...\n";
try {
    $stmt = $db->prepare("DELETE FROM user WHERE user_id = ?");
    $stmt->execute([$userId]);
    echo "  ✓ 测试用户已删除\n\n";
} catch (PDOException $e) {
    echo "  ⚠ 清理失败: " . $e->getMessage() . "\n\n";
}

// 统计用户数量
echo "步骤 8: 统计用户数量...\n";
$stmt = $db->query("SELECT COUNT(*) as count FROM user");
$result = $stmt->fetch();
echo "  ℹ 当前数据库中共有 {$result['count']} 个用户\n\n";

echo "========================================\n";
echo "    ✅ 所有测试通过！\n";
echo "========================================\n";
echo "\n注册流程可以正常工作。\n";
echo "请访问 http://localhost/源泉/register.html 进行实际测试。\n";
