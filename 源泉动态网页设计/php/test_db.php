<?php
/**
 * 数据库连接测试脚本
 */

require_once 'config.php';

echo "=== 数据库连接测试 ===\n\n";

try {
    $db = getDB();
    echo "✓ 数据库连接成功\n";
    echo "  数据库名: " . DB_NAME . "\n";
    echo "  数据库用户: " . DB_USER . "\n\n";
    
    // 检查 user 表是否存在
    $stmt = $db->query("SHOW TABLES LIKE 'user'");
    if ($stmt->fetch()) {
        echo "✓ user 表存在\n";
        
        // 检查表结构
        $stmt = $db->query("DESCRIBE user");
        $columns = $stmt->fetchAll();
        echo "  表字段:\n";
        foreach ($columns as $col) {
            echo "    - {$col['Field']}: {$col['Type']}\n";
        }
        echo "\n";
        
        // 检查是否有测试用户
        $stmt = $db->query("SELECT COUNT(*) as count FROM user");
        $result = $stmt->fetch();
        echo "✓ 当前用户数量: " . $result['count'] . "\n\n";
        
    } else {
        echo "✗ user 表不存在，请导入 database.sql\n\n";
    }
    
} catch (PDOException $e) {
    echo "✗ 数据库连接失败\n";
    echo "  错误信息: " . $e->getMessage() . "\n";
    echo "\n请检查:\n";
    echo "1. MySQL 服务是否已启动\n";
    echo "2. 数据库配置是否正确 (config.php)\n";
    echo "3. 数据库 'root' 是否已创建\n";
}

echo "\n=== 测试完成 ===\n";
