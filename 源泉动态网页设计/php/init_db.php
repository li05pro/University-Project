<?php
/**
 * 数据库初始化脚本
 * 自动创建数据库和表结构
 */

require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    $created = [];
    
    // 创建分类表
    $db->exec("CREATE TABLE IF NOT EXISTS category (
        category_id INT PRIMARY KEY AUTO_INCREMENT COMMENT '分类ID',
        parent_id INT DEFAULT 0 COMMENT '父分类ID (0表示一级分类)',
        category_name VARCHAR(50) NOT NULL COMMENT '分类名称',
        description VARCHAR(255) NULL COMMENT '分类描述',
        sort_order INT DEFAULT 0 COMMENT '排序序号',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
        INDEX idx_parent (parent_id),
        INDEX idx_sort (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='分类表'");
    $created[] = 'category';
    
    // 创建资讯表
    $db->exec("CREATE TABLE IF NOT EXISTS article (
        article_id INT PRIMARY KEY AUTO_INCREMENT COMMENT '资讯ID',
        title VARCHAR(200) NOT NULL COMMENT '资讯标题',
        content TEXT NOT NULL COMMENT '资讯正文',
        summary VARCHAR(500) NULL COMMENT '资讯摘要',
        author_id INT NOT NULL COMMENT '作者ID',
        category_id INT NOT NULL COMMENT '所属分类',
        tags VARCHAR(255) NULL COMMENT '标签（逗号分隔）',
        cover_image VARCHAR(255) NULL COMMENT '封面图片路径',
        view_count INT DEFAULT 0 COMMENT '阅读量',
        like_count INT DEFAULT 0 COMMENT '点赞数',
        comment_count INT DEFAULT 0 COMMENT '评论数',
        status TINYINT DEFAULT 1 COMMENT '状态 (1=已发布, 0=草稿, 2=已删除)',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '发布时间',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
        INDEX idx_author (author_id),
        INDEX idx_category (category_id),
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='资讯表'");
    $created[] = 'article';
    
    // 创建评论表
    $db->exec("CREATE TABLE IF NOT EXISTS comment (
        comment_id INT PRIMARY KEY AUTO_INCREMENT COMMENT '评论ID',
        article_id INT NOT NULL COMMENT '资讯ID',
        user_id INT NOT NULL COMMENT '评论者ID',
        content TEXT NOT NULL COMMENT '评论内容',
        parent_id INT DEFAULT 0 COMMENT '父评论ID (0表示顶级评论)',
        reply_to INT NULL COMMENT '回复目标用户ID',
        like_count INT DEFAULT 0 COMMENT '点赞数',
        status TINYINT DEFAULT 1 COMMENT '状态 (1=正常, 0=删除)',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '评论时间',
        INDEX idx_article (article_id),
        INDEX idx_user (user_id),
        INDEX idx_parent (parent_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='评论表'");
    $created[] = 'comment';
    
    // 创建收藏表
    $db->exec("CREATE TABLE IF NOT EXISTS favorite (
        favorite_id INT PRIMARY KEY AUTO_INCREMENT COMMENT '收藏ID',
        user_id INT NOT NULL COMMENT '用户ID',
        article_id INT NOT NULL COMMENT '资讯ID',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '收藏时间',
        UNIQUE KEY unique_favorite (user_id, article_id),
        INDEX idx_user (user_id),
        INDEX idx_article (article_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='收藏表'");
    $created[] = 'favorite';
    
    // 创建好友表
    $db->exec("CREATE TABLE IF NOT EXISTS friend (
        friend_id INT PRIMARY KEY AUTO_INCREMENT COMMENT '好友关系ID',
        user_id INT NOT NULL COMMENT '用户ID',
        friend_user_id INT NOT NULL COMMENT '好友用户ID',
        status TINYINT DEFAULT 0 COMMENT '状态 (0=待确认, 1=已接受, 2=已拒绝)',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
        UNIQUE KEY unique_friend (user_id, friend_user_id),
        INDEX idx_user (user_id),
        INDEX idx_friend (friend_user_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='好友表'");
    $created[] = 'friend';
    
    // 插入默认分类数据
    $stmt = $db->query("SELECT COUNT(*) FROM category");
    if ($stmt->fetchColumn() == 0) {
        $categories = [
            ['category_id' => 1, 'category_name' => '技术教程', 'description' => '各种技术教程和入门指南', 'sort_order' => 1],
            ['category_id' => 2, 'category_name' => '经验分享', 'description' => '工作经验和心得分享', 'sort_order' => 2],
            ['category_id' => 3, 'category_name' => '行业动态', 'description' => '行业新闻和动态', 'sort_order' => 3],
            ['category_id' => 4, 'category_name' => '生活随笔', 'description' => '生活感悟和随笔', 'sort_order' => 4],
            ['category_id' => 5, 'category_name' => '前端开发', 'description' => '前端技术和框架', 'sort_order' => 5],
            ['category_id' => 6, 'category_name' => '后端开发', 'description' => '后端技术和框架', 'sort_order' => 6],
            ['category_id' => 7, 'category_name' => '数据库', 'description' => '数据库技术和优化', 'sort_order' => 7],
        ];
        
        $stmt = $db->prepare("INSERT INTO category (category_id, category_name, description, sort_order) VALUES (?, ?, ?, ?)");
        foreach ($categories as $cat) {
            $stmt->execute([$cat['category_id'], $cat['category_name'], $cat['description'], $cat['sort_order']]);
        }
    }
    
    // 创建上传目录
    $uploadDirs = ['uploads', 'uploads/covers', 'uploads/articles', 'uploads/avatars'];
    foreach ($uploadDirs as $dir) {
        $path = __DIR__ . '/../' . $dir;
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
    
    jsonResponse(true, '数据库初始化成功', ['tables' => $created]);
    
} catch (PDOException $e) {
    jsonResponse(false, '数据库初始化失败: ' . $e->getMessage());
} catch (Exception $e) {
    jsonResponse(false, '初始化失败: ' . $e->getMessage());
}
