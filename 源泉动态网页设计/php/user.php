<?php
/**
 * 源泉动态网站 - 用户相关功能
 */

require_once 'config.php';
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

// 检查用户是否登录
if (!isLoggedIn()) {
    jsonResponse(false, '请先登录');
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_profile':
        getProfile();
        break;
    case 'update_profile':
        updateProfile();
        break;
    case 'change_password':
        changePassword();
        break;
    case 'upload_avatar':
        uploadAvatar();
        break;
    case 'get_my_articles':
        getMyArticles();
        break;
    case 'get_my_comments':
        getMyComments();
        break;
    case 'get_my_favorites':
        getMyFavorites();
        break;
    case 'get_my_friends':
        getMyFriends();
        break;
    case 'get_friend_requests':
        getFriendRequests();
        break;
    case 'add_friend':
        addFriend();
        break;
    case 'handle_friend_request':
        handleFriendRequest();
        break;
    case 'remove_friend':
        removeFriend();
        break;
    case 'get_user_stats':
        getUserStats();
        break;
    case 'delete_my_article':
        deleteMyArticle();
        break;
    case 'delete_my_comment':
        deleteMyComment();
        break;
    case 'cancel_favorite':
        cancelFavorite();
        break;
    default:
        jsonResponse(false, '未知操作');
}

/**
 * 获取个人资料
 */
function getProfile() {
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];

        $stmt = $db->prepare("SELECT user_id, username, email, nickname, bio, phone, avatar, created_at, last_login FROM user WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            jsonResponse(false, '用户不存在');
        }

        // 处理头像URL
        if ($user['avatar']) {
            $user['avatar'] = 'uploads/avatars/' . $user['avatar'];
        } else {
            $user['avatar'] = 'img/avatar-default.jpg';
        }

        jsonResponse(true, '获取成功', ['user' => $user]);

    } catch (PDOException $e) {
        jsonResponse(false, '获取失败，请稍后重试');
    }
}

/**
 * 更新个人资料
 */
function updateProfile() {
    $nickname = trim($_POST['nickname'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // 验证必填字段
    if (empty($nickname)) {
        jsonResponse(false, '请输入昵称');
    }
    
    // 验证昵称长度
    if (strlen($nickname) < 2 || strlen($nickname) > 50) {
        jsonResponse(false, '昵称长度应在2-50个字符之间');
    }
    
    // 验证手机号格式
    if (!empty($phone) && !preg_match('/^1[3-9]\d{9}$/', $phone)) {
        jsonResponse(false, '请输入有效的手机号码');
    }
    
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        // 更新用户信息
        $stmt = $db->prepare("UPDATE user SET nickname = ?, bio = ?, phone = ? WHERE user_id = ?");
        $stmt->execute([$nickname, $bio, $phone, $userId]);
        
        jsonResponse(true, '资料更新成功');
        
    } catch (PDOException $e) {
        jsonResponse(false, '更新失败，请稍后重试');
    }
}

/**
 * 修改密码
 */
function changePassword() {
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // 验证必填字段
    if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
        jsonResponse(false, '请填写所有密码字段');
    }
    
    // 验证新密码强度
    if (strlen($newPassword) < 6) {
        jsonResponse(false, '新密码长度至少6位');
    }
    if (!preg_match('/(?=.*[a-zA-Z])(?=.*\d)/', $newPassword)) {
        jsonResponse(false, '新密码必须包含字母和数字');
    }
    
    // 验证两次密码是否一致
    if ($newPassword !== $confirmPassword) {
        jsonResponse(false, '两次输入的新密码不一致');
    }
    
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        // 获取当前密码
        $stmt = $db->prepare("SELECT password FROM user WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(false, '用户不存在');
        }
        
        // 验证旧密码
        if (!password_verify($oldPassword, $user['password'])) {
            jsonResponse(false, '原密码错误');
        }
        
        // 加密新密码
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // 更新密码
        $stmt = $db->prepare("UPDATE user SET password = ? WHERE user_id = ?");
        $stmt->execute([$newPasswordHash, $userId]);
        
        jsonResponse(true, '密码修改成功');
        
    } catch (PDOException $e) {
        jsonResponse(false, '修改失败，请稍后重试');
    }
}

/**
 * 上传头像
 */
function uploadAvatar() {
    if (empty($_FILES['avatar']['tmp_name'])) {
        jsonResponse(false, '请选择图片');
    }
    
    $userId = $_SESSION['user_id'];
    $filename = 'avatar_' . $userId . '_' . time() . '.jpg';
    
    $uploadDir = UPLOAD_PATH . 'avatars/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filepath = $uploadDir . $filename;
    
    // 检查文件类型
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($_FILES['avatar']['type'], $allowedTypes)) {
        jsonResponse(false, '只支持JPG、PNG、GIF格式的图片');
    }
    
    // 检查文件大小（最大2MB）
    if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
        jsonResponse(false, '图片大小不能超过2MB');
    }
    
    // 处理图片（压缩并裁剪为正方形）
    $srcImage = null;
    switch ($_FILES['avatar']['type']) {
        case 'image/jpeg':
            $srcImage = imagecreatefromjpeg($_FILES['avatar']['tmp_name']);
            break;
        case 'image/png':
            $srcImage = imagecreatefrompng($_FILES['avatar']['tmp_name']);
            break;
        case 'image/gif':
            $srcImage = imagecreatefromgif($_FILES['avatar']['tmp_name']);
            break;
    }
    
    if (!$srcImage) {
        jsonResponse(false, '图片处理失败');
    }
    
    // 获取原图尺寸
    $srcWidth = imagesx($srcImage);
    $srcHeight = imagesy($srcImage);
    
    // 计算裁剪尺寸（取最小边）
    $size = min($srcWidth, $srcHeight);
    $srcX = ($srcWidth - $size) / 2;
    $srcY = ($srcHeight - $size) / 2;
    
    // 创建目标图像（200x200）
    $dstImage = imagecreatetruecolor(200, 200);
    
    // 裁剪并缩放
    imagecopyresampled($dstImage, $srcImage, 0, 0, $srcX, $srcY, 200, 200, $size, $size);
    
    // 保存图片
    imagejpeg($dstImage, $filepath, 85);
    
    // 释放内存
    imagedestroy($srcImage);
    imagedestroy($dstImage);
    
    // 更新数据库
    try {
        $db = getDB();
        // 数据库只存储文件名
        $stmt = $db->prepare("UPDATE user SET avatar = ? WHERE user_id = ?");
        $stmt->execute([$filename, $userId]);

        // 返回完整URL
        $avatarUrl = 'uploads/avatars/' . $filename;
        jsonResponse(true, '头像上传成功', ['avatar' => $avatarUrl]);
    } catch (PDOException $e) {
        jsonResponse(false, '保存失败，请稍后重试');
    }
}

/**
 * 添加好友
 */
function addFriend() {
    $friendId = intval($_POST['friend_id'] ?? 0);
    
    if ($friendId <= 0) {
        jsonResponse(false, '参数错误');
    }
    
    if ($friendId == $_SESSION['user_id']) {
        jsonResponse(false, '不能添加自己为好友');
    }
    
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        // 检查用户是否存在
        $stmt = $db->prepare("SELECT user_id FROM user WHERE user_id = ? AND status = 1");
        $stmt->execute([$friendId]);
        if (!$stmt->fetch()) {
            jsonResponse(false, '用户不存在');
        }
        
        // 检查是否已经有我发送的请求或是好友
        $stmt = $db->prepare("SELECT friend_id, status FROM friend WHERE user_id = ? AND friend_user_id = ?");
        $stmt->execute([$userId, $friendId]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ($existing['status'] == 1) {
                jsonResponse(false, '你们已经是好友了');
            } else if ($existing['status'] == 0) {
                jsonResponse(false, '好友请求已发送，请等待对方确认');
            } else if ($existing['status'] == 2) {
                // 之前被拒绝，更新为待确认状态
                $stmt = $db->prepare("UPDATE friend SET status = 0, created_at = NOW() WHERE friend_id = ?");
                $stmt->execute([$existing['friend_id']]);
                jsonResponse(true, '好友请求已发送');
            }
        } else {
            // 添加好友请求
            $stmt = $db->prepare("INSERT INTO friend (user_id, friend_user_id, status, created_at) VALUES (?, ?, 0, NOW())");
            $stmt->execute([$userId, $friendId]);
        }
        
        jsonResponse(true, '好友请求已发送');
        
    } catch (PDOException $e) {
        jsonResponse(false, '操作失败，请稍后重试');
    }
}

/**
 * 处理好友请求
 */
function handleFriendRequest() {
    $friendId = intval($_POST['friend_id'] ?? 0);
    $action = $_POST['handle_action'] ?? '';

    if ($friendId <= 0) {
        jsonResponse(false, '参数错误：friend_id无效');
    }

    if (!in_array($action, ['accept', 'reject'])) {
        jsonResponse(false, '参数错误：action无效');
    }

    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];

        // 查找好友请求（对方发送给我的请求）
        $stmt = $db->prepare("SELECT friend_id FROM friend WHERE user_id = ? AND friend_user_id = ? AND status = 0");
        $stmt->execute([$friendId, $userId]);
        $request = $stmt->fetch();

        if (!$request) {
            jsonResponse(false, '好友请求不存在或已处理（friend_id: ' . $friendId . ', user_id: ' . $userId . '）');
        }
        
        if ($action == 'accept') {
            // 开始事务
            $db->beginTransaction();

            try {
                // 接受请求，更新状态
                $stmt = $db->prepare("UPDATE friend SET status = 1 WHERE friend_id = ?");
                $stmt->execute([$request['friend_id']]);

                // 检查是否已存在反向关系
                $stmt = $db->prepare("SELECT friend_id FROM friend WHERE user_id = ? AND friend_user_id = ?");
                $stmt->execute([$userId, $friendId]);
                $existing = $stmt->fetch();

                if ($existing) {
                    // 更新现有记录为已接受
                    $stmt = $db->prepare("UPDATE friend SET status = 1 WHERE friend_id = ?");
                    $stmt->execute([$existing['friend_id']]);
                } else {
                    // 创建双向关系
                    $stmt = $db->prepare("INSERT INTO friend (user_id, friend_user_id, status, created_at) VALUES (?, ?, 1, NOW())");
                    $stmt->execute([$userId, $friendId]);
                }

                $db->commit();
                jsonResponse(true, '已接受好友请求');
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
        } else {
            // 拒绝请求
            $stmt = $db->prepare("UPDATE friend SET status = 2 WHERE friend_id = ?");
            $stmt->execute([$request['friend_id']]);

            jsonResponse(true, '已拒绝好友请求');
        }
        
    } catch (PDOException $e) {
        jsonResponse(false, '操作失败：' . $e->getMessage());
    }
}

/**
 * 删除好友
 */
function removeFriend() {
    $friendId = intval($_POST['friend_id'] ?? 0);
    
    if ($friendId <= 0) {
        jsonResponse(false, '参数错误');
    }
    
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        // 删除双向好友关系
        $stmt = $db->prepare("DELETE FROM friend WHERE (user_id = ? AND friend_user_id = ?) OR (user_id = ? AND friend_user_id = ?)");
        $stmt->execute([$userId, $friendId, $friendId, $userId]);
        
        jsonResponse(true, '已删除好友');
        
    } catch (PDOException $e) {
        jsonResponse(false, '操作失败，请稍后重试');
    }
}

/**
 * 获取用户统计数据（文章数、评论数、收藏数、好友数）
 */
function getUserStats() {
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        // 获取文章数
        $stmt = $db->prepare("SELECT COUNT(*) FROM article WHERE author_id = ? AND status != 2");
        $stmt->execute([$userId]);
        $articleCount = $stmt->fetchColumn();
        
        // 获取评论数
        if (commentHasStatusField($db)) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM comment WHERE user_id = ? AND status = 1");
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM comment WHERE user_id = ?");
        }
        $stmt->execute([$userId]);
        $commentCount = $stmt->fetchColumn();
        
        // 获取收藏数
        $stmt = $db->prepare("SELECT COUNT(*) FROM favorite WHERE user_id = ?");
        $stmt->execute([$userId]);
        $favoriteCount = $stmt->fetchColumn();
        
        // 获取好友数
        $stmt = $db->prepare("SELECT COUNT(*) FROM friend WHERE user_id = ? AND status = 1");
        $stmt->execute([$userId]);
        $friendCount = $stmt->fetchColumn();
        
        jsonResponse(true, '获取成功', [
            'article_count' => $articleCount,
            'comment_count' => $commentCount,
            'favorite_count' => $favoriteCount,
            'friend_count' => $friendCount
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(false, '获取失败，请稍后重试');
    }
}

/**
 * 获取我的文章列表
 */
function getMyArticles() {
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        $page = max(1, intval($_GET['page'] ?? 1));
        $pageSize = min(50, max(1, intval($_GET['pageSize'] ?? 10)));
        $status = intval($_GET['status'] ?? -1); // -1表示全部，0草稿，1已发布
        
        $offset = ($page - 1) * $pageSize;
        
        // 构建查询条件
        $where = "WHERE author_id = ? AND status != 2";
        $params = [$userId];
        
        if ($status >= 0) {
            $where .= " AND status = ?";
            $params[] = $status;
        }
        
        // 获取总数
        $stmt = $db->prepare("SELECT COUNT(*) FROM article $where");
        $stmt->execute($params);
        $total = $stmt->fetchColumn();
        
        // 获取文章列表
        $sql = "SELECT 
                    a.article_id,
                    a.title,
                    a.summary,
                    a.cover_image,
                    a.view_count,
                    a.comment_count,
                    a.status,
                    a.created_at,
                    c.category_name
                FROM article a
                LEFT JOIN category c ON a.category_id = c.category_id
                $where
                ORDER BY a.created_at DESC
                LIMIT $offset, $pageSize";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $articles = $stmt->fetchAll();
        
        // 处理数据
        foreach ($articles as &$article) {
            $article['cover_image'] = $article['cover_image'] ?: 'img/article-thumb.jpg';
            $article['created_at'] = formatDateTime($article['created_at']);
            $article['status_text'] = $article['status'] == 1 ? '已发布' : '草稿';
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
 * 获取我的评论列表
 */
function getMyComments() {
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        $page = max(1, intval($_GET['page'] ?? 1));
        $pageSize = min(50, max(1, intval($_GET['pageSize'] ?? 10)));
        
        $offset = ($page - 1) * $pageSize;
        
        // 检查是否有status字段
        $hasStatus = commentHasStatusField($db);
        
        // 获取总数
        if ($hasStatus) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM comment WHERE user_id = ? AND status = 1");
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM comment WHERE user_id = ?");
        }
        $stmt->execute([$userId]);
        $total = $stmt->fetchColumn();
        
        // 获取评论列表
        if ($hasStatus) {
            $sql = "SELECT 
                        c.comment_id,
                        c.content,
                        c.created_at,
                        a.article_id,
                        a.title as article_title
                    FROM comment c
                    LEFT JOIN article a ON c.article_id = a.article_id
                    WHERE c.user_id = ? AND c.status = 1
                    ORDER BY c.created_at DESC
                    LIMIT $offset, $pageSize";
        } else {
            $sql = "SELECT 
                        c.comment_id,
                        c.content,
                        c.created_at,
                        a.article_id,
                        a.title as article_title
                    FROM comment c
                    LEFT JOIN article a ON c.article_id = a.article_id
                    WHERE c.user_id = ?
                    ORDER BY c.created_at DESC
                    LIMIT $offset, $pageSize";
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
        $comments = $stmt->fetchAll();
        
        // 处理数据
        foreach ($comments as &$comment) {
            $comment['created_at'] = formatDateTime($comment['created_at']);
            $comment['content_short'] = mb_substr($comment['content'], 0, 100) . (mb_strlen($comment['content']) > 100 ? '...' : '');
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
 * 获取我的收藏列表
 */
function getMyFavorites() {
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        $page = max(1, intval($_GET['page'] ?? 1));
        $pageSize = min(50, max(1, intval($_GET['pageSize'] ?? 10)));
        
        $offset = ($page - 1) * $pageSize;
        
        // 获取总数
        $stmt = $db->prepare("SELECT COUNT(*) FROM favorite WHERE user_id = ?");
        $stmt->execute([$userId]);
        $total = $stmt->fetchColumn();
        
        // 获取收藏列表
        $sql = "SELECT 
                    f.favorite_id,
                    f.created_at as favorited_at,
                    a.article_id,
                    a.title,
                    a.summary,
                    a.cover_image,
                    a.view_count,
                    a.comment_count,
                    u.nickname as author_name
                FROM favorite f
                LEFT JOIN article a ON f.article_id = a.article_id
                LEFT JOIN user u ON a.author_id = u.user_id
                WHERE f.user_id = ? AND a.status = 1
                ORDER BY f.created_at DESC
                LIMIT $offset, $pageSize";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
        $favorites = $stmt->fetchAll();
        
        // 处理数据
        foreach ($favorites as &$favorite) {
            $favorite['cover_image'] = $favorite['cover_image'] ?: 'img/article-thumb.jpg';
            $favorite['favorited_at'] = formatDateTime($favorite['favorited_at']);
        }
        
        $totalPages = ceil($total / $pageSize);
        
        jsonResponse(true, '获取成功', [
            'favorites' => $favorites,
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
 * 获取我的好友列表
 */
function getMyFriends() {
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        // 获取好友列表
        $stmt = $db->prepare("SELECT 
                    u.user_id,
                    u.username,
                    u.nickname,
                    u.avatar,
                    u.bio,
                    f.created_at as friend_since
                FROM friend f
                LEFT JOIN user u ON f.friend_user_id = u.user_id
                WHERE f.user_id = ? AND f.status = 1
                ORDER BY f.created_at DESC");
        $stmt->execute([$userId]);
        $friends = $stmt->fetchAll();
        
        // 处理数据
        foreach ($friends as &$friend) {
            $friend['avatar'] = $friend['avatar'] ? 'uploads/avatars/' . $friend['avatar'] : 'img/avatar-default.jpg';
            $friend['friend_since'] = formatDateTime($friend['friend_since']);
        }
        
        jsonResponse(true, '获取成功', ['friends' => $friends]);
        
    } catch (PDOException $e) {
        jsonResponse(false, '获取失败：' . $e->getMessage());
    }
}

/**
 * 获取好友请求列表
 */
function getFriendRequests() {
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        // 获取收到的请求
        $stmt = $db->prepare("SELECT 
                    f.friend_id,
                    f.user_id as requester_id,
                    f.created_at as request_time,
                    u.username,
                    u.nickname,
                    u.avatar,
                    u.bio
                FROM friend f
                LEFT JOIN user u ON f.user_id = u.user_id
                WHERE f.friend_user_id = ? AND f.status = 0
                ORDER BY f.created_at DESC");
        $stmt->execute([$userId]);
        $requests = $stmt->fetchAll();
        
        // 处理数据
        foreach ($requests as &$request) {
            $request['requester_id'] = intval($request['requester_id']);
            $request['avatar'] = $request['avatar'] ? 'uploads/avatars/' . $request['avatar'] : 'img/avatar-default.jpg';
            $request['request_time'] = formatDateTime($request['request_time']);
        }

        jsonResponse(true, '获取成功', ['requests' => $requests]);
        
    } catch (PDOException $e) {
        jsonResponse(false, '获取失败：' . $e->getMessage());
    }
}

/**
 * 删除我的文章
 */
function deleteMyArticle() {
    $articleId = intval($_POST['article_id'] ?? 0);
    
    if ($articleId <= 0) {
        jsonResponse(false, '参数错误');
    }
    
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        // 检查文章是否存在且属于当前用户
        $stmt = $db->prepare("SELECT article_id FROM article WHERE article_id = ? AND author_id = ?");
        $stmt->execute([$articleId, $userId]);
        if (!$stmt->fetch()) {
            jsonResponse(false, '文章不存在或无权删除');
        }
        
        // 软删除
        $stmt = $db->prepare("UPDATE article SET status = 2 WHERE article_id = ?");
        $stmt->execute([$articleId]);
        
        jsonResponse(true, '删除成功');
        
    } catch (PDOException $e) {
        jsonResponse(false, '删除失败，请稍后重试');
    }
}

/**
 * 删除我的评论
 */
function deleteMyComment() {
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
        
        // 更新文章评论数
        $stmt = $db->prepare("UPDATE article SET comment_count = comment_count - 1 WHERE article_id = ?");
        $stmt->execute([$comment['article_id']]);
        
        jsonResponse(true, '删除成功');
        
    } catch (PDOException $e) {
        jsonResponse(false, '删除失败，请稍后重试');
    }
}

/**
 * 取消收藏
 */
function cancelFavorite() {
    $articleId = intval($_POST['article_id'] ?? 0);
    
    if ($articleId <= 0) {
        jsonResponse(false, '参数错误');
    }
    
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        $stmt = $db->prepare("DELETE FROM favorite WHERE user_id = ? AND article_id = ?");
        $stmt->execute([$userId, $articleId]);
        
        jsonResponse(true, '已取消收藏');
        
    } catch (PDOException $e) {
        jsonResponse(false, '操作失败，请稍后重试');
    }
}

/**
 * 格式化日期时间
 */
function formatDateTime($date) {
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
        return date('Y-m-d H:i', $timestamp);
    }
}
