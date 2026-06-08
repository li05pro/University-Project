-- 源泉动态网站数据库结构
-- 创建数据库
CREATE DATABASE IF NOT EXISTS yuanquan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE yuanquan;

-- 用户表
CREATE TABLE IF NOT EXISTS user (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- 分类表
CREATE TABLE IF NOT EXISTS category (
    category_id INT PRIMARY KEY AUTO_INCREMENT COMMENT '分类ID',
    parent_id INT DEFAULT 0 COMMENT '父分类ID (0表示一级分类)',
    category_name VARCHAR(50) NOT NULL COMMENT '分类名称',
    description VARCHAR(255) NULL COMMENT '分类描述',
    sort_order INT DEFAULT 0 COMMENT '排序序号',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    INDEX idx_parent (parent_id),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='分类表';

-- 资讯表
CREATE TABLE IF NOT EXISTS article (
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
    INDEX idx_created (created_at),
    FOREIGN KEY (author_id) REFERENCES user(user_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES category(category_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='资讯表';

-- 评论表
CREATE TABLE IF NOT EXISTS comment (
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
    INDEX idx_status (status),
    FOREIGN KEY (article_id) REFERENCES article(article_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='评论表';

-- 交流圈表
CREATE TABLE IF NOT EXISTS circle (
    circle_id INT PRIMARY KEY AUTO_INCREMENT COMMENT '圈子ID',
    circle_name VARCHAR(100) NOT NULL COMMENT '圈子名称',
    cover_image VARCHAR(255) NULL COMMENT '圈子封面',
    creator_id INT NOT NULL COMMENT '创建者ID',
    description TEXT NULL COMMENT '圈子简介',
    rules TEXT NULL COMMENT '圈子规则',
    member_count INT DEFAULT 0 COMMENT '成员数量',
    post_count INT DEFAULT 0 COMMENT '动态数量',
    status TINYINT DEFAULT 1 COMMENT '状态 (1=正常, 0=解散)',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    INDEX idx_creator (creator_id),
    INDEX idx_status (status),
    FOREIGN KEY (creator_id) REFERENCES user(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='交流圈表';

-- 圈子成员表
CREATE TABLE IF NOT EXISTS circle_member (
    member_id INT PRIMARY KEY AUTO_INCREMENT COMMENT '成员ID',
    circle_id INT NOT NULL COMMENT '圈子ID',
    user_id INT NOT NULL COMMENT '用户ID',
    role TINYINT DEFAULT 3 COMMENT '角色 (1=圈主, 2=管理员, 3=普通成员)',
    status TINYINT DEFAULT 1 COMMENT '状态 (1=正常, 0=已退出)',
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '加入时间',
    UNIQUE INDEX idx_circle_user (circle_id, user_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (circle_id) REFERENCES circle(circle_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='圈子成员表';

-- 圈子动态表
CREATE TABLE IF NOT EXISTS circle_post (
    post_id INT PRIMARY KEY AUTO_INCREMENT COMMENT '动态ID',
    circle_id INT NOT NULL COMMENT '圈子ID',
    user_id INT NOT NULL COMMENT '发布者ID',
    content TEXT NOT NULL COMMENT '动态内容',
    like_count INT DEFAULT 0 COMMENT '点赞数',
    status TINYINT DEFAULT 1 COMMENT '状态 (1=正常, 0=删除)',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '发布时间',
    INDEX idx_circle (circle_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (circle_id) REFERENCES circle(circle_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='圈子动态表';

-- 好友关系表
CREATE TABLE IF NOT EXISTS friend (
    friend_id INT PRIMARY KEY AUTO_INCREMENT COMMENT '关系ID',
    user_id INT NOT NULL COMMENT '用户ID',
    friend_user_id INT NOT NULL COMMENT '好友用户ID',
    status TINYINT DEFAULT 0 COMMENT '状态 (1=已添加, 0=待确认, 2=已拒绝)',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '添加时间',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    UNIQUE INDEX idx_friend (user_id, friend_user_id),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES user(user_id) ON DELETE CASCADE,
    FOREIGN KEY (friend_user_id) REFERENCES user(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='好友关系表';

-- 收藏表
CREATE TABLE IF NOT EXISTS favorite (
    favorite_id INT PRIMARY KEY AUTO_INCREMENT COMMENT '收藏ID',
    user_id INT NOT NULL COMMENT '用户ID',
    article_id INT NOT NULL COMMENT '资讯ID',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '收藏时间',
    UNIQUE INDEX idx_user_article (user_id, article_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES user(user_id) ON DELETE CASCADE,
    FOREIGN KEY (article_id) REFERENCES article(article_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='收藏表';

-- 插入默认分类数据
INSERT INTO category (category_name, parent_id, description, sort_order) VALUES
('技术教程', 0, '各类技术教程和学习资料', 1),
('经验分享', 0, '个人经验和心得分享', 2),
('行业动态', 0, '行业最新动态和资讯', 3),
('生活随笔', 0, '日常生活感悟和随笔', 4),
('前端开发', 1, 'HTML、CSS、JavaScript等前端技术', 1),
('后端开发', 1, 'PHP、Java、Python等后端技术', 2),
('数据库', 1, 'MySQL、Redis等数据库技术', 3),
('职场经验', 2, '职场工作经验和技巧', 1),
('学习心得', 2, '学习方法和心得体会', 2);

-- 插入测试用户
INSERT INTO user (username, password, email, nickname, bio, status) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@yuanquan.com', '管理员', '源泉平台管理员', 1),
('testuser', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'test@yuanquan.com', '测试用户', '这是一个测试用户账号', 1);
-- 默认密码: password
