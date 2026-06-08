<?php
/**
 * 源泉动态网站 - 文章API接口（包含分页功能）
 */

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

// 开启错误显示（开发环境）
error_reporting(E_ALL);
ini_set('display_errors', 0);

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        getArticleList();
        break;
    case 'detail':
        getArticleDetail();
        break;
    case 'categories':
        getCategories();
        break;
    case 'hot':
        getHotArticles();
        break;
    case 'latest':
        getLatestArticles();
        break;
    case 'get_comments':
        getComments();
        break;
    case 'add_comment':
        addComment();
        break;
    case 'toggle_favorite':
        toggleFavorite();
        break;
    case 'check_favorite':
        checkFavorite();
        break;
    default:
        jsonResponse(false, '未知操作');
}

/**
 * 获取文章列表（带分页）
 */
function getArticleList() {
    // 获取分页参数
    $page = max(1, intval($_GET['page'] ?? 1));
    $pageSize = min(50, max(1, intval($_GET['pageSize'] ?? PAGE_SIZE)));
    $categoryId = intval($_GET['category_id'] ?? 0);
    $keyword = trim($_GET['keyword'] ?? '');
    
    try {
        $db = getDB();
        
        // 构建查询条件
        $where = ['a.status = 1'];
        $params = [];
        
        if ($categoryId > 0) {
            $where[] = 'a.category_id = ?';
            $params[] = $categoryId;
        }
        
        if (!empty($keyword)) {
            $where[] = '(a.title LIKE ? OR a.summary LIKE ? OR a.content LIKE ?)';
            $keywordLike = '%' . $keyword . '%';
            $params[] = $keywordLike;
            $params[] = $keywordLike;
            $params[] = $keywordLike;
        }
        
        $whereClause = implode(' AND ', $where);
        
        // 获取总记录数
        $countSql = "SELECT COUNT(*) FROM article a WHERE $whereClause";
        $stmt = $db->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();
        
        // 计算总页数
        $totalPages = ceil($total / $pageSize);
        $page = min($page, max(1, $totalPages));
        $offset = ($page - 1) * $pageSize;
        
        // 获取文章列表
        // 注意：使用直接拼接而不是参数绑定，因为某些MySQL版本对LIMIT参数绑定支持不好
        $sql = "SELECT 
                    a.article_id,
                    a.title,
                    a.summary,
                    a.cover_image,
                    a.view_count,
                    a.comment_count,
                    a.created_at,
                    c.category_name,
                    u.nickname as author_name,
                    u.avatar as author_avatar
                FROM article a
                LEFT JOIN category c ON a.category_id = c.category_id
                LEFT JOIN user u ON a.author_id = u.user_id
                WHERE $whereClause
                ORDER BY a.created_at DESC
                LIMIT $offset, $pageSize";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $articles = $stmt->fetchAll();
        
        // 处理数据
        foreach ($articles as &$article) {
            $article['cover_image'] = $article['cover_image'] ?: 'img/article-thumb.jpg';
            $article['author_avatar'] = $article['author_avatar'] ?: 'img/default-avatar.jpg';
            $article['created_at'] = formatDate($article['created_at']);
            $article['view_count'] = formatNumber($article['view_count']);
            $article['comment_count'] = formatNumber($article['comment_count']);
        }
        
        // 生成分页HTML
        $paginationHtml = generatePaginationHtml($page, $totalPages, $total, $pageSize);
        
        jsonResponse(true, '获取成功', [
            'articles' => $articles,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_count' => $total,
                'page_size' => $pageSize,
                'has_more' => $page < $totalPages
            ],
            'pagination_html' => $paginationHtml
        ]);
        
    } catch (PDOException $e) {
        // 检查是否是表不存在错误
        if (strpos($e->getMessage(), 'Base table or view not found') !== false) {
            jsonResponse(true, '获取成功', [
                'articles' => [],
                'pagination' => [
                    'current_page' => 1,
                    'total_pages' => 0,
                    'total_count' => 0,
                    'page_size' => $pageSize,
                    'has_more' => false
                ],
                'pagination_html' => '<div class="pagination-info">暂无文章数据，请先初始化数据库</div>'
            ]);
        } else {
            jsonResponse(false, '获取失败：' . $e->getMessage());
        }
    }
}

/**
 * 获取文章详情
 */
function getArticleDetail() {
    $articleId = intval($_GET['id'] ?? 0);
    
    if ($articleId <= 0) {
        jsonResponse(false, '参数错误');
    }
    
    try {
        $db = getDB();
        
        // 获取文章详情
        $stmt = $db->prepare("SELECT 
                a.article_id,
                a.title,
                a.content,
                a.cover_image,
                a.view_count,
                a.comment_count,
                a.created_at,
                a.author_id,
                c.category_name,
                u.nickname as author_name,
                u.avatar as author_avatar,
                u.bio as author_bio
            FROM article a
            LEFT JOIN category c ON a.category_id = c.category_id
            LEFT JOIN user u ON a.author_id = u.user_id
            WHERE a.article_id = ? AND a.status = 1");
        $stmt->execute([$articleId]);
        $article = $stmt->fetch();
        
        if (!$article) {
            jsonResponse(false, '文章不存在');
        }
        
        // 增加阅读量
        $db->prepare("UPDATE article SET view_count = view_count + 1 WHERE article_id = ?")
           ->execute([$articleId]);
        
        // 处理数据
        $article['cover_image'] = $article['cover_image'] ?: 'img/article-thumb.jpg';
        $article['author_avatar'] = $article['author_avatar'] ?: 'img/default-avatar.jpg';
        $article['created_at'] = formatDate($article['created_at']);
        $article['view_count'] = formatNumber($article['view_count']);
        $article['comment_count'] = formatNumber($article['comment_count']);
        
        // 获取相关文章
        $relatedStmt = $db->prepare("SELECT 
                article_id, title, cover_image, view_count
            FROM article 
            WHERE category_id = ? AND article_id != ? AND status = 1
            ORDER BY created_at DESC LIMIT 5");
        $relatedStmt->execute([$article['category_id'], $articleId]);
        $related = $relatedStmt->fetchAll();
        
        jsonResponse(true, '获取成功', [
            'article' => $article,
            'related' => $related
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(false, '获取失败: ' . $e->getMessage());
    }
}

/**
 * 获取分类列表
 */
function getCategories() {
    try {
        $db = getDB();
        
        // 先检查表是否存在
        $stmt = $db->query("SHOW TABLES LIKE 'category'");
        if (!$stmt->fetch()) {
            // 表不存在，返回默认分类
            $categories = [
                ['category_id' => 1, 'category_name' => '技术教程', 'article_count' => 0],
                ['category_id' => 2, 'category_name' => '经验分享', 'article_count' => 0],
                ['category_id' => 3, 'category_name' => '行业动态', 'article_count' => 0],
                ['category_id' => 4, 'category_name' => '生活随笔', 'article_count' => 0],
                ['category_id' => 5, 'category_name' => '前端开发', 'article_count' => 0],
                ['category_id' => 6, 'category_name' => '后端开发', 'article_count' => 0],
                ['category_id' => 7, 'category_name' => '数据库', 'article_count' => 0],
            ];
            jsonResponse(true, '获取成功', ['categories' => $categories]);
            return;
        }
        
        $stmt = $db->query("SELECT 
                c.*,
                COUNT(a.article_id) as article_count
            FROM category c
            LEFT JOIN article a ON c.category_id = a.category_id AND a.status = 1
            GROUP BY c.category_id
            ORDER BY c.sort_order ASC");
        $categories = $stmt->fetchAll();
        
        jsonResponse(true, '获取成功', ['categories' => $categories]);
    } catch (PDOException $e) {
        jsonResponse(false, '获取失败: ' . $e->getMessage());
    }
}

/**
 * 获取热门文章
 */
function getHotArticles() {
    $limit = min(20, max(1, intval($_GET['limit'] ?? 5)));
    
    try {
        $db = getDB();
        $sql = "SELECT 
                a.article_id,
                a.title,
                a.cover_image,
                a.view_count,
                a.comment_count,
                c.category_name,
                u.nickname as author_name
            FROM article a
            LEFT JOIN category c ON a.category_id = c.category_id
            LEFT JOIN user u ON a.author_id = u.user_id
            WHERE a.status = 1
            ORDER BY a.view_count DESC, a.created_at DESC
            LIMIT $limit";
        $stmt = $db->query($sql);
        $articles = $stmt->fetchAll();
        
        jsonResponse(true, '获取成功', ['articles' => $articles]);
    } catch (PDOException $e) {
        jsonResponse(false, '获取失败: ' . $e->getMessage());
    }
}

/**
 * 获取最新文章
 */
function getLatestArticles() {
    $limit = min(20, max(1, intval($_GET['limit'] ?? 5)));

    try {
        $db = getDB();
        $sql = "SELECT
                a.article_id,
                a.title,
                a.summary,
                a.cover_image,
                a.view_count,
                a.comment_count,
                a.created_at,
                a.author_id,
                c.category_name,
                u.nickname as author_name
            FROM article a
            LEFT JOIN category c ON a.category_id = c.category_id
            LEFT JOIN user u ON a.author_id = u.user_id
            WHERE a.status = 1
            ORDER BY a.created_at DESC
            LIMIT $limit";
        $stmt = $db->query($sql);
        $articles = $stmt->fetchAll();

        foreach ($articles as &$article) {
            $article['created_at'] = formatDate($article['created_at']);
        }

        jsonResponse(true, '获取成功', ['articles' => $articles]);
    } catch (PDOException $e) {
        jsonResponse(false, '获取失败: ' . $e->getMessage());
    }
}

/**
 * 生成分页HTML
 */
function generatePaginationHtml($currentPage, $totalPages, $total, $pageSize) {
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<div class="pagination">';
    
    // 上一页
    if ($currentPage > 1) {
        $html .= '<a href="javascript:void(0)" data-page="' . ($currentPage - 1) . '" class="page-link">上一页</a>';
    } else {
        $html .= '<span class="disabled">上一页</span>';
    }
    
    // 页码
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    if ($startPage > 1) {
        $html .= '<a href="javascript:void(0)" data-page="1" class="page-link">1</a>';
        if ($startPage > 2) {
            $html .= '<span>...</span>';
        }
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $currentPage) {
            $html .= '<span class="current">' . $i . '</span>';
        } else {
            $html .= '<a href="javascript:void(0)" data-page="' . $i . '" class="page-link">' . $i . '</a>';
        }
    }
    
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<span>...</span>';
        }
        $html .= '<a href="javascript:void(0)" data-page="' . $totalPages . '" class="page-link">' . $totalPages . '</a>';
    }
    
    // 下一页
    if ($currentPage < $totalPages) {
        $html .= '<a href="javascript:void(0)" data-page="' . ($currentPage + 1) . '" class="page-link">下一页</a>';
    } else {
        $html .= '<span class="disabled">下一页</span>';
    }
    
    $html .= '</div>';
    
    // 分页信息
    $start = ($currentPage - 1) * $pageSize + 1;
    $end = min($currentPage * $pageSize, $total);
    $html .= '<div class="pagination-info">显示 ' . $start . '-' . $end . ' 条，共 ' . $total . ' 条</div>';
    
    return $html;
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

/**
 * 格式化数字
 */
function formatNumber($num) {
    if ($num >= 10000) {
        return round($num / 10000, 1) . '万';
    } elseif ($num >= 1000) {
        return round($num / 1000, 1) . 'k';
    }
    return $num;
}

/**
 * 获取文章评论列表
 */
function getComments() {
    $articleId = intval($_GET['article_id'] ?? 0);
    $page = max(1, intval($_GET['page'] ?? 1));
    $pageSize = min(50, max(1, intval($_GET['pageSize'] ?? 10)));
    
    if ($articleId <= 0) {
        jsonResponse(false, '参数错误');
    }
    
    try {
        $db = getDB();
        
        // 检查comment表是否有status字段
        $hasStatus = false;
        try {
            $db->query("SELECT status FROM comment LIMIT 1");
            $hasStatus = true;
        } catch (PDOException $e) {
            // status字段不存在
        }
        
        // 获取总评论数
        if ($hasStatus) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM comment WHERE article_id = ? AND status = 1");
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM comment WHERE article_id = ?");
        }
        $stmt->execute([$articleId]);
        $total = $stmt->fetchColumn();
        
        // 获取评论列表
        $offset = ($page - 1) * $pageSize;
        if ($hasStatus) {
            $sql = "SELECT 
                        c.comment_id,
                        c.content,
                        c.created_at,
                        u.user_id,
                        u.nickname,
                        u.avatar
                    FROM comment c
                    LEFT JOIN user u ON c.user_id = u.user_id
                    WHERE c.article_id = ? AND c.status = 1
                    ORDER BY c.created_at DESC
                    LIMIT $offset, $pageSize";
        } else {
            $sql = "SELECT 
                        c.comment_id,
                        c.content,
                        c.created_at,
                        u.user_id,
                        u.nickname,
                        u.avatar
                    FROM comment c
                    LEFT JOIN user u ON c.user_id = u.user_id
                    WHERE c.article_id = ?
                    ORDER BY c.created_at DESC
                    LIMIT $offset, $pageSize";
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$articleId]);
        $comments = $stmt->fetchAll();
        
        // 处理数据
        foreach ($comments as &$comment) {
            $comment['avatar'] = $comment['avatar'] ? 'uploads/avatars/' . $comment['avatar'] : 'img/avatar-default.jpg';
            $comment['created_at'] = formatDate($comment['created_at']);
        }
        
        $totalPages = ceil($total / $pageSize);
        
        jsonResponse(true, '获取成功', [
            'comments' => $comments,
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
 * 添加评论
 */
function addComment() {
    // 检查是否登录
    session_start();
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(false, '请先登录后再评论');
    }
    
    $articleId = intval($_POST['article_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    
    if ($articleId <= 0) {
        jsonResponse(false, '参数错误');
    }
    
    if (empty($content)) {
        jsonResponse(false, '请输入评论内容');
    }
    
    if (mb_strlen($content) > 1000) {
        jsonResponse(false, '评论内容不能超过1000字');
    }
    
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        // 检查文章是否存在
        $stmt = $db->prepare("SELECT article_id FROM article WHERE article_id = ? AND status = 1");
        $stmt->execute([$articleId]);
        if (!$stmt->fetch()) {
            jsonResponse(false, '文章不存在');
        }
        
        // 插入评论 - 不指定status字段，使用默认值
        $stmt = $db->prepare("INSERT INTO comment (article_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$articleId, $userId, $content]);
        
        // 更新文章评论数
        $stmt = $db->prepare("UPDATE article SET comment_count = comment_count + 1 WHERE article_id = ?");
        $stmt->execute([$articleId]);
        
        // 获取刚插入的评论信息
        $commentId = $db->lastInsertId();
        $stmt = $db->prepare("SELECT 
                    c.comment_id,
                    c.content,
                    c.created_at,
                    u.user_id,
                    u.nickname,
                    u.avatar
                FROM comment c
                LEFT JOIN user u ON c.user_id = u.user_id
                WHERE c.comment_id = ?");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();
        
        $comment['avatar'] = $comment['avatar'] ? 'uploads/avatars/' . $comment['avatar'] : 'img/avatar-default.jpg';
        $comment['created_at'] = formatDate($comment['created_at']);
        
        jsonResponse(true, '评论成功', ['comment' => $comment]);

    } catch (PDOException $e) {
        jsonResponse(false, '评论失败：' . $e->getMessage());
    }
}

/**
 * 切换收藏状态（收藏/取消收藏）
 */
function toggleFavorite() {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(false, '请先登录');
    }

    $articleId = intval($_POST['article_id'] ?? 0);

    if ($articleId <= 0) {
        jsonResponse(false, '参数错误');
    }

    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];

        // 检查文章是否存在
        $stmt = $db->prepare("SELECT article_id FROM article WHERE article_id = ? AND status = 1");
        $stmt->execute([$articleId]);
        if (!$stmt->fetch()) {
            jsonResponse(false, '文章不存在');
        }

        // 检查是否已收藏
        $stmt = $db->prepare("SELECT favorite_id FROM favorite WHERE user_id = ? AND article_id = ?");
        $stmt->execute([$userId, $articleId]);
        $favorite = $stmt->fetch();

        if ($favorite) {
            // 取消收藏
            $stmt = $db->prepare("DELETE FROM favorite WHERE favorite_id = ?");
            $stmt->execute([$favorite['favorite_id']]);
            jsonResponse(true, '已取消收藏', ['is_favorite' => false]);
        } else {
            // 添加收藏
            $stmt = $db->prepare("INSERT INTO favorite (user_id, article_id, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$userId, $articleId]);
            jsonResponse(true, '收藏成功', ['is_favorite' => true]);
        }

    } catch (PDOException $e) {
        jsonResponse(false, '操作失败：' . $e->getMessage());
    }
}

/**
 * 检查是否已收藏
 */
function checkFavorite() {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(false, '请先登录');
    }

    $articleId = intval($_GET['article_id'] ?? 0);

    if ($articleId <= 0) {
        jsonResponse(false, '参数错误');
    }

    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];

        $stmt = $db->prepare("SELECT favorite_id FROM favorite WHERE user_id = ? AND article_id = ?");
        $stmt->execute([$userId, $articleId]);
        $isFavorite = $stmt->fetch() ? true : false;

        jsonResponse(true, '获取成功', ['is_favorite' => $isFavorite]);

    } catch (PDOException $e) {
        jsonResponse(false, '查询失败：' . $e->getMessage());
    }
}
