<?php
/**
 * 数据库连接测试
 */

require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    
    // 检查表是否存在
    $tables = ['user', 'article', 'category', 'comment'];
    $existingTables = [];
    $missingTables = [];
    
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->fetch()) {
            $existingTables[] = $table;
        } else {
            $missingTables[] = $table;
        }
    }
    
    jsonResponse(true, '数据库连接正常', [
        'database' => DB_NAME,
        'existing_tables' => $existingTables,
        'missing_tables' => $missingTables,
        'need_init' => count($missingTables) > 0
    ]);
    
} catch (PDOException $e) {
    jsonResponse(false, '数据库连接失败: ' . $e->getMessage());
} catch (Exception $e) {
    jsonResponse(false, '错误: ' . $e->getMessage());
}
