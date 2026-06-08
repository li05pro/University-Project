<?php
/**
 * 添加指定用户到数据库
 */

require_once 'config.php';

echo "========================================\n";
echo "    添加用户到数据库\n";
echo "========================================\n\n";

// 用户信息
$username = 'add';
$password = 'a123456';
$email = 'add@example.com';
$nickname = 'AddUser';

echo "【要添加的用户信息】\n";
echo "  用户名: {$username}\n";
echo "  密码: {$password}\n";
echo "  邮箱: {$email}\n";
echo "  昵称: {$nickname}\n\n";

try {
    // 连接数据库
    $db = getDB();
    echo "✓ 数据库连接成功\n\n";
    
    // 确保表存在
    $initResult = initDatabaseTables();
    if (!$initResult['success']) {
        echo "✗ 数据库初始化失败: " . $initResult['error'] . "\n";
        exit(1);
    }
    
    // 检查用户名是否已存在
    $stmt = $db->prepare("SELECT user_id FROM user WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        echo "⚠ 用户名 '{$username}' 已存在，更新密码...\n";
        
        // 更新密码
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE user SET password = ? WHERE username = ?");
        $stmt->execute([$passwordHash, $username]);
        echo "✓ 密码已更新\n";
        
        // 获取用户信息
        $stmt = $db->prepare("SELECT user_id, username, email, nickname, created_at FROM user WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        echo "\n【用户信息】\n";
        echo "  用户ID: {$user['user_id']}\n";
        echo "  用户名: {$user['username']}\n";
        echo "  邮箱: {$user['email']}\n";
        echo "  昵称: {$user['nickname']}\n";
        echo "  注册时间: {$user['created_at']}\n";
    } else {
        // 创建新用户
        echo "✓ 用户名可用，创建新用户...\n";
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO user (username, password, email, nickname, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$username, $passwordHash, $email, $nickname]);
        
        $userId = $db->lastInsertId();
        echo "✓ 用户创建成功 (ID: {$userId})\n";
        
        // 显示用户信息
        $stmt = $db->prepare("SELECT user_id, username, email, nickname, created_at FROM user WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        echo "\n【用户信息】\n";
        echo "  用户ID: {$user['user_id']}\n";
        echo "  用户名: {$user['username']}\n";
        echo "  邮箱: {$user['email']}\n";
        echo "  昵称: {$user['nickname']}\n";
        echo "  注册时间: {$user['created_at']}\n";
    }
    
    // 验证密码
    $stmt = $db->prepare("SELECT password FROM user WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (password_verify($password, $user['password'])) {
        echo "\n✓ 密码验证成功\n";
    } else {
        echo "\n✗ 密码验证失败\n";
    }
    
    // 统计用户数量
    $stmt = $db->query("SELECT COUNT(*) as count FROM user");
    $result = $stmt->fetch();
    echo "\nℹ 当前数据库中共有 {$result['count']} 个用户\n";
    
    echo "\n========================================\n";
    echo "    ✅ 操作完成！\n";
    echo "========================================\n";
    echo "\n您现在可以使用以下信息登录:\n";
    echo "  用户名: {$username}\n";
    echo "  密码: {$password}\n";
    
} catch (PDOException $e) {
    echo "\n✗ 错误: " . $e->getMessage() . "\n";
    exit(1);
}
