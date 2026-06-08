<?php
/**
 * 源泉动态网站 - 资讯管理相关功能
 */

require_once 'config.php';
require_once 'validator.php';
session_start();

/**
 * 检查comment表是否有status字段
 */
function commentHasStatusField($db) {
    try {
        $db->query("SELECT status FROM comment LIMIT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// 开启错误显示（开发环境）
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 检查用户是否登录
if (!isLoggedIn()) {
    jsonResponse(false, '请先登录');
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'create':
        createArticle();
        break;
    case 'update':
        updateArticle();
        break;
    case 'delete':
        deleteArticle();
        break;
    case 'upload_image':
        uploadImage();
        break;
    case 'add_comment':
        addComment();
        break;
    case 'delete_comment':
        deleteComment();
        break;
    case 'toggle_favorite':
        toggleFavorite();
        break;
    default:
        jsonResponse(false, '未知操作');
}

/**
 * 创建资讯
 */
function createArticle() {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $categoryId = intval($_POST['category_id'] ?? 0);
    $tags = trim($_POST['tags'] ?? '');
    $status = isset($_POST['draft']) ? 0 : 1;
    
    // 使用验证器验证数据
    $validation = validateArticle([
        'title' => $title,
        'content' => $content,
        'category_id' => $categoryId,
        'tags' => $tags
    ]);
    
    if (!$validation['valid']) {
        jsonResponse(false, $validation['first']);
    }
    
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        // 生成摘要
        $summary = mb_substr(strip_tags($content), 0, 200);
        
        // 处理封面图片
        $coverImage = null;
        if (!empty($_FILES['cover_image']['tmp_name'])) {
            $coverImage = uploadFile($_FILES['cover_image'], 'covers');
        }
        
        // 插入资讯
        $stmt = $db->prepare("INSERT INTO article (title, content, summary, author_id, category_id, tags, cover_image, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$title, $content, $summary, $userId, $categoryId, $tags, $coverImage, $status]);
        
        $articleId = $db->lastInsertId();
        
        jsonResponse(true, $status == 1 ? '发布成功' : '已保存为草稿', ['article_id' => $articleId]);
        
    } catch (PDOException $e) {
        // 检查是否是表不存在错误
        if (strpos($e->getMessage(), 'Base table or view not found') !== false) {
            jsonResponse(false, '数据库表不存在，请先运行初始化');
        } else {
            jsonResponse(false, '发布失败: ' . $e->getMessage());
        }
    }
}

/**
 * 更新资讯
 */
function updateArticle() {
    $articleId = intval($_POST['article_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $categoryId = intval($_POST['category_id'] ?? 0);
    $tags = trim($_POST['tags'] ?? '');
    
    if ($articleId <= 0) {
        jsonResponse(false, '参数错误');
    }
    
    // 验证必填字段
    if (empty($title)) {
        jsonResponse(false, '请输入资讯标题');
    }
    if (empty($content)) {
        jsonResponse(false, '请输入资讯内容');
    }
    if ($categoryId <= 0) {
        jsonResponse(false, '请选择资讯分类');
    }
    
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        // 检查资讯是否存在且属于当前用户
        $stmt = $db->prepare("SELECT article_id FROM article WHERE article_id = ? AND author_id = ? AND status != 2");
        $stmt->execute([$articleId, $userId]);
        if (!$stmt->fetch()) {
            jsonResponse(false, '资讯不存在或无权编辑');
        }
        
        // 生成摘要
        $summary = mb_substr(strip_tags($content), 0, 200);
        
        // 处理封面图片
        $coverImageSql = '';
        $params = [$title, $content, $summary, $categoryId, $tags];
        
        if (!empty($_FILES['cover_image']['tmp_name'])) {
            $coverImage = uploadFile($_FILES['cover_image'], 'covers');
            if ($coverImage) {
                $coverImageSql = ', cover_image = ?';
                $params[] = $coverImage;
            }
        }
        
        $params[] = $articleId;
        
        // 更新资讯
        $stmt = $db->prepare("UPDATE article SET title = ?, content = ?, summary = ?, category_id = ?, tags = ? $coverImageSql WHERE article_id = ?");
        $stmt->execute($params);
        
        jsonResponse(true, '更新成功');
        
    } catch (PDOException $e) {
        jsonResponse(false, '更新失败，请稍后重试');
    }
}

/**
 * 删除资讯
 */
function deleteArticle() {
    $articleId = intval($_POST['article_id'] ?? 0);
    
    if ($articleId <= 0) {
        jsonResponse(false, '参数错误');
    }
    
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        // 检查资讯是否存在且属于当前用户
        $stmt = $db->prepare("SELECT article_id FROM article WHERE article_id = ? AND author_id = ?");
        $stmt->execute([$articleId, $userId]);
        if (!$stmt->fetch()) {
            jsonResponse(false, '资讯不存在或无权删除');
        }
        
        // 软删除，将状态设为2
        $stmt = $db->prepare("UPDATE article SET status = 2 WHERE article_id = ?");
        $stmt->execute([$articleId]);
        
        jsonResponse(true, '删除成功');
        
    } catch (PDOException $e) {
        jsonResponse(false, '删除失败，请稍后重试');
    }
}

/**
 * 上传图片
 */
function uploadImage() {
    if (empty($_FILES['image']['tmp_name'])) {
        jsonResponse(false, '请选择图片');
    }
    
    $path = uploadFile($_FILES['image'], 'articles');
    
    if ($path) {
        jsonResponse(true, '上传成功', ['url' => $path]);
    } else {
        jsonResponse(false, '上传失败，请检查图片格式和大小');
    }
}

/**
 * 添加评论
 */
function addComment() {
    $articleId = intval($_POST['article_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    $parentId = intval($_POST['parent_id'] ?? 0);
    $replyTo = intval($_POST['reply_to'] ?? 0);
    
    if ($articleId <= 0) {
        jsonResponse(false, '参数错误');
    }
    
    if (empty($content)) {
        jsonResponse(false, '请输入评论内容');
    }
    
    if (strlen($content) > 1000) {
        jsonResponse(false, '评论内容不能超过1000个字符');
    }
    
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        // 检查资讯是否存在
        $stmt = $db->prepare("SELECT article_id FROM article WHERE article_id = ? AND status = 1");
        $stmt->execute([$articleId]);
        if (!$stmt->fetch()) {
            jsonResponse(false, '资讯不存在');
        }
        
        // 插入评论
        $stmt = $db->prepare("INSERT INTO comment (article_id, user_id, content, parent_id, reply_to, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$articleId, $userId, $content, $parentId, $replyTo]);
        
        // 更新资讯评论数
        $stmt = $db->prepare("UPDATE article SET comment_count = comment_count + 1 WHERE article_id = ?");
        $stmt->execute([$articleId]);
        
        jsonResponse(true, '评论成功');
        
    } catch (PDOException $e) {
        jsonResponse(false, '评论失败，请稍后重试');
    }
}

/**
 * 删除评论
 */
function deleteComment() {
    $commentId = intval($_POST['comment_id'] ?? 0);
    
    if ($commentId <= 0) {
        jsonResponse(false, '参数错误');
    }
    
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        // 检查评论是否存在且属于当前用户
        $stmt = $db->prepare("SELECT article_id FROM comment WHERE comment_id = ? AND user_id = ?");
        $stmt->execute([$commentId, $userId]);
        $comment = $stmt->fetch();
        
        if (!$comment) {
            jsonResponse(false, '评论不存在或无权删除');
        }
        
        // 软删除或硬删除
        if (commentHasStatusField($db)) {
            $stmt = $db->prepare("UPDATE comment SET status = 0 WHERE comment_id = ?");
            $stmt->execute([$commentId]);
        } else {
            $stmt = $db->prepare("DELETE FROM comment WHERE comment_id = ?");
            $stmt->execute([$commentId]);
        }
        
        // 更新资讯评论数
        $stmt = $db->prepare("UPDATE article SET comment_count = comment_count - 1 WHERE article_id = ?");
        $stmt->execute([$comment['article_id']]);
        
        jsonResponse(true, '删除成功');
        
    } catch (PDOException $e) {
        jsonResponse(false, '删除失败，请稍后重试');
    }
}

/**
 * 切换收藏状态
 */
function toggleFavorite() {
    $articleId = intval($_POST['article_id'] ?? 0);
    
    if ($articleId <= 0) {
        jsonResponse(false, '参数错误');
    }
    
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        // 检查是否已收藏
        $stmt = $db->prepare("SELECT favorite_id FROM favorite WHERE user_id = ? AND article_id = ?");
        $stmt->execute([$userId, $articleId]);
        $favorite = $stmt->fetch();
        
        if ($favorite) {
            // 取消收藏
            $stmt = $db->prepare("DELETE FROM favorite WHERE favorite_id = ?");
            $stmt->execute([$favorite['favorite_id']]);
            jsonResponse(true, '已取消收藏', ['action' => 'remove']);
        } else {
            // 添加收藏
            $stmt = $db->prepare("INSERT INTO favorite (user_id, article_id, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$userId, $articleId]);
            jsonResponse(true, '收藏成功', ['action' => 'add']);
        }
        
    } catch (PDOException $e) {
        jsonResponse(false, '操作失败，请稍后重试');
    }
}
