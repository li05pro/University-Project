<?php
/**
 * 源泉动态网站 - 作者相关API
 */

require_once 'config.php';
session_start();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'detail':
        getAuthorDetail();
        break;
    case 'articles':
        getAuthorArticles();
        break;
    case 'check_friend_status':
        checkFriendStatus();
        break;
    default:
        jsonResponse(false, '未知操作');
}

/**
 * 获取作者详情
 */
function getAuthorDetail() {
    $authorId = intval($_GET['id'] ?? 0);
    
    if ($authorId <= 0) {
        jsonResponse(false, '参数错误');
    }
    
    try {
        $db = getDB();
        
        // 获取作者信息
        $stmt = $db->prepare("SELECT 
                u.user_id,
                u.username,
                u.nickname,
                u.avatar,
                u.bio,
                u.created_at as join_date
            FROM user u
            WHERE u.user_id = ? AND u.status = 1");
        $stmt->execute([$authorId]);
        $author = $stmt->fetch();
        
        if (!$author) {
            jsonResponse(false, '作者不存在');
        }
        
        // 处理数据
        $author['avatar'] = $author['avatar'] ? 'uploads/avatars/' . $author['avatar'] : 'img/avatar-default.jpg';
        $author['join_date'] = date('Y-m-d', strtotime($author['join_date']));
        
        // 获取作者统计
        // 文章数
        $stmt = $db->prepare("SELECT COUNT(*) FROM article WHERE author_id = ? AND status = 1");
        $stmt->execute([$authorId]);
        $author['article_count'] = $stmt->fetchColumn();
        
        // 总阅读量
        $stmt = $db->prepare("SELECT COALESCE(SUM(view_count), 0) FROM article WHERE author_id = ? AND status = 1");
        $stmt->execute([$authorId]);
        $author['total_views'] = $stmt->fetchColumn();
        
        // 总点赞数
        $stmt = $db->prepare("SELECT COALESCE(SUM(like_count), 0) FROM article WHERE author_id = ? AND status = 1");
        $stmt->execute([$authorId]);
        $author['total_likes'] = $stmt->fetchColumn();
        
        // 好友数
        $stmt = $db->prepare("SELECT COUNT(*) FROM friend WHERE user_id = ? AND status = 1");
        $stmt->execute([$authorId]);
        $author['friend_count'] = $stmt->fetchColumn();
        
        // 检查当前用户是否已加好友
        $isFriend = false;
        $friendStatus = null; // null=未添加, 0=待确认, 1=已是好友
        if (isset($_SESSION['user_id'])) {
            $currentUserId = $_SESSION['user_id'];
            $stmt = $db->prepare("SELECT status FROM friend WHERE user_id = ? AND friend_user_id = ?");
            $stmt->execute([$currentUserId, $authorId]);
            $friendRelation = $stmt->fetch();
            if ($friendRelation) {
                $friendStatus = $friendRelation['status'];
                $isFriend = ($friendRelation['status'] == 1);
            }
        }
        $author['is_friend'] = $isFriend;
        $author['friend_status'] = $friendStatus;
        $author['is_self'] = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $authorId;
        
        jsonResponse(true, '获取成功', ['author' => $author]);
        
    } catch (PDOException $e) {
        jsonResponse(false, '获取失败：' . $e->getMessage());
    }
}

/**
 * 获取作者文章列表
 */
function getAuthorArticles() {
    $authorId = intval($_GET['id'] ?? 0);
    $page = max(1, intval($_GET['page'] ?? 1));
    $pageSize = min(20, max(1, intval($_GET['pageSize'] ?? 10)));
    
    if ($authorId <= 0) {
        jsonResponse(false, '参数错误');
    }
    
    try {
        $db = getDB();
        
        // 获取总数
        $stmt = $db->prepare("SELECT COUNT(*) FROM article WHERE author_id = ? AND status = 1");
        $stmt->execute([$authorId]);
        $total = $stmt->fetchColumn();
        
        // 获取文章列表
        $offset = ($page - 1) * $pageSize;
        $sql = "SELECT 
                    a.article_id,
                    a.title,
                    a.summary,
                    a.cover_image,
                    a.view_count,
                    a.comment_count,
                    a.created_at,
                    c.category_name
                FROM article a
                LEFT JOIN category c ON a.category_id = c.category_id
                WHERE a.author_id = ? AND a.status = 1
                ORDER BY a.created_at DESC
                LIMIT $offset, $pageSize";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$authorId]);
        $articles = $stmt->fetchAll();
        
        // 处理数据
        foreach ($articles as &$article) {
            $article['cover_image'] = $article['cover_image'] ?: 'img/article-thumb.jpg';
            $article['created_at'] = formatDate($article['created_at']);
        }
        
        $totalPages = ceil($total / $pageSize);
        
        jsonResponse(true, '获取成功', [
            'articles' => $articles,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_count' => $total,
                'page_size' => $pageSize
            ]
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(false, '获取失败：' . $e->getMessage());
    }
}

/**
 * 检查好友状态
 */
function checkFriendStatus() {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(false, '请先登录');
    }
    
    $friendId = intval($_GET['friend_id'] ?? 0);
    
    if ($friendId <= 0) {
        jsonResponse(false, '参数错误');
    }
    
    $userId = $_SESSION['user_id'];
    
    if ($friendId == $userId) {
        jsonResponse(true, '获取成功', ['status' => 'self']);
    }
    
    try {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT status FROM friend WHERE user_id = ? AND friend_user_id = ?");
        $stmt->execute([$userId, $friendId]);
        $relation = $stmt->fetch();
        
        if (!$relation) {
            jsonResponse(true, '获取成功', ['status' => 'none']);
        } else if ($relation['status'] == 1) {
            jsonResponse(true, '获取成功', ['status' => 'friend']);
        } else if ($relation['status'] == 0) {
            jsonResponse(true, '获取成功', ['status' => 'pending']);
        } else {
            jsonResponse(true, '获取成功', ['status' => 'rejected']);
        }
        
    } catch (PDOException $e) {
        jsonResponse(false, '查询失败：' . $e->getMessage());
    }
}

/**
 * 格式化日期
 */
function formatDate($date) {
    $timestamp = strtotime($date);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return '刚刚';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . '分钟前';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . '小时前';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . '天前';
    } else {
        return date('Y-m-d', $timestamp);
    }
}
