<?php
/**
 * 源泉动态网站 - 交流圈相关功能
 */

require_once 'config.php';
session_start();

// 检查用户是否登录
if (!isLoggedIn()) {
    jsonResponse(false, '请先登录');
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'create':
        createCircle();
        break;
    case 'update':
        updateCircle();
        break;
    case 'join':
        joinCircle();
        break;
    case 'leave':
        leaveCircle();
        break;
    case 'post':
        createPost();
        break;
    case 'delete_post':
        deletePost();
        break;
    case 'manage_member':
        manageMember();
        break;
    default:
        jsonResponse(false, '未知操作');
}

/**
 * 创建交流圈
 */
function createCircle() {
    $circleName = trim($_POST['circle_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $rules = trim($_POST['rules'] ?? '');
    
    // 验证必填字段
    if (empty($circleName)) {
        jsonResponse(false, '请输入圈子名称');
    }
    if (empty($description)) {
        jsonResponse(false, '请输入圈子简介');
    }
    
    // 验证名称长度
    if (strlen($circleName) > 100) {
        jsonResponse(false, '圈子名称不能超过100个字符');
    }
    if (strlen($description) > 500) {
        jsonResponse(false, '圈子简介不能超过500个字符');
    }
    
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        // 处理封面图片
        $coverImage = null;
        if (!empty($_FILES['cover_image']['tmp_name'])) {
            $coverImage = uploadFile($_FILES['cover_image'], 'circles');
        }
        
        // 插入圈子数据
        $stmt = $db->prepare("INSERT INTO circle (circle_name, cover_image, creator_id, description, rules, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$circleName, $coverImage, $userId, $description, $rules]);
        
        $circleId = $db->lastInsertId();
        
        // 将创建者添加为圈主
        $stmt = $db->prepare("INSERT INTO circle_member (circle_id, user_id, role, joined_at) VALUES (?, ?, 1, NOW())");
        $stmt->execute([$circleId, $userId]);
        
        jsonResponse(true, '圈子创建成功', ['circle_id' => $circleId]);
        
    } catch (PDOException $e) {
        jsonResponse(false, '创建失败，请稍后重试');
    }
}

/**
 * 更新交流圈
 */
function updateCircle() {
    $circleId = intval($_POST['circle_id'] ?? 0);
    $circleName = trim($_POST['circle_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $rules = trim($_POST['rules'] ?? '');
    
    if ($circleId <= 0) {
        jsonResponse(false, '参数错误');
    }
    
    if (empty($circleName) || empty($description)) {
        jsonResponse(false, '请填写完整信息');
    }
    
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        // 检查权限（只有圈主和管理员可以修改）
        $stmt = $db->prepare("SELECT role FROM circle_member WHERE circle_id = ? AND user_id = ? AND status = 1");
        $stmt->execute([$circleId, $userId]);
        $member = $stmt->fetch();
        
        if (!$member || $member['role'] > 2) {
            jsonResponse(false, '无权修改此圈子');
        }
        
        // 处理封面图片
        $coverImageSql = '';
        $params = [$circleName, $description, $rules];
        
        if (!empty($_FILES['cover_image']['tmp_name'])) {
            $coverImage = uploadFile($_FILES['cover_image'], 'circles');
            if ($coverImage) {
                $coverImageSql = ', cover_image = ?';
                $params[] = $coverImage;
            }
        }
        
        $params[] = $circleId;
        
        // 更新圈子信息
        $stmt = $db->prepare("UPDATE circle SET circle_name = ?, description = ?, rules = ? $coverImageSql WHERE circle_id = ?");
        $stmt->execute($params);
        
        jsonResponse(true, '更新成功');
        
    } catch (PDOException $e) {
        jsonResponse(false, '更新失败，请稍后重试');
    }
}

/**
 * 加入交流圈
 */
function joinCircle() {
    $circleId = intval($_POST['circle_id'] ?? 0);
    
    if ($circleId <= 0) {
        jsonResponse(false, '参数错误');
    }
    
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        // 检查圈子是否存在
        $stmt = $db->prepare("SELECT circle_id FROM circle WHERE circle_id = ? AND status = 1");
        $stmt->execute([$circleId]);
        if (!$stmt->fetch()) {
            jsonResponse(false, '圈子不存在');
        }
        
        // 检查是否已经是成员
        $stmt = $db->prepare("SELECT member_id, status FROM circle_member WHERE circle_id = ? AND user_id = ?");
        $stmt->execute([$circleId, $userId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            if ($existing['status'] == 1) {
                jsonResponse(false, '您已经是该圈子的成员');
            } else {
                // 重新加入
                $stmt = $db->prepare("UPDATE circle_member SET status = 1 WHERE member_id = ?");
                $stmt->execute([$existing['member_id']]);
            }
        } else {
            // 添加新成员
            $stmt = $db->prepare("INSERT INTO circle_member (circle_id, user_id, role, status, joined_at) VALUES (?, ?, 3, 1, NOW())");
            $stmt->execute([$circleId, $userId]);
        }
        
        // 更新圈子成员数
        $stmt = $db->prepare("UPDATE circle SET member_count = member_count + 1 WHERE circle_id = ?");
        $stmt->execute([$circleId]);
        
        jsonResponse(true, '加入成功');
        
    } catch (PDOException $e) {
        jsonResponse(false, '加入失败，请稍后重试');
    }
}

/**
 * 退出交流圈
 */
function leaveCircle() {
    $circleId = intval($_POST['circle_id'] ?? 0);
    
    if ($circleId <= 0) {
        jsonResponse(false, '参数错误');
    }
    
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        // 检查是否是成员
        $stmt = $db->prepare("SELECT member_id, role FROM circle_member WHERE circle_id = ? AND user_id = ? AND status = 1");
        $stmt->execute([$circleId, $userId]);
        $member = $stmt->fetch();
        
        if (!$member) {
            jsonResponse(false, '您不是该圈子的成员');
        }
        
        // 圈主不能退出
        if ($member['role'] == 1) {
            jsonResponse(false, '圈主不能退出圈子，请转让圈主身份或解散圈子');
        }
        
        // 更新成员状态
        $stmt = $db->prepare("UPDATE circle_member SET status = 0 WHERE member_id = ?");
        $stmt->execute([$member['member_id']]);
        
        // 更新圈子成员数
        $stmt = $db->prepare("UPDATE circle SET member_count = member_count - 1 WHERE circle_id = ?");
        $stmt->execute([$circleId]);
        
        jsonResponse(true, '退出成功');
        
    } catch (PDOException $e) {
        jsonResponse(false, '退出失败，请稍后重试');
    }
}

/**
 * 发布圈子动态
 */
function createPost() {
    $circleId = intval($_POST['circle_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    
    if ($circleId <= 0) {
        jsonResponse(false, '参数错误');
    }
    
    if (empty($content)) {
        jsonResponse(false, '请输入动态内容');
    }
    
    if (strlen($content) > 2000) {
        jsonResponse(false, '动态内容不能超过2000个字符');
    }
    
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        // 检查是否是圈子成员
        $stmt = $db->prepare("SELECT member_id FROM circle_member WHERE circle_id = ? AND user_id = ? AND status = 1");
        $stmt->execute([$circleId, $userId]);
        if (!$stmt->fetch()) {
            jsonResponse(false, '您不是该圈子的成员，无法发布动态');
        }
        
        // 插入动态
        $stmt = $db->prepare("INSERT INTO circle_post (circle_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$circleId, $userId, $content]);
        
        // 更新圈子动态数
        $stmt = $db->prepare("UPDATE circle SET post_count = post_count + 1 WHERE circle_id = ?");
        $stmt->execute([$circleId]);
        
        jsonResponse(true, '发布成功');
        
    } catch (PDOException $e) {
        jsonResponse(false, '发布失败，请稍后重试');
    }
}

/**
 * 删除圈子动态
 */
function deletePost() {
    $postId = intval($_POST['post_id'] ?? 0);
    
    if ($postId <= 0) {
        jsonResponse(false, '参数错误');
    }
    
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        // 获取动态信息
        $stmt = $db->prepare("SELECT cp.*, cm.role FROM circle_post cp LEFT JOIN circle_member cm ON cp.circle_id = cm.circle_id AND cm.user_id = ? WHERE cp.post_id = ? AND cp.status = 1");
        $stmt->execute([$userId, $postId]);
        $post = $stmt->fetch();
        
        if (!$post) {
            jsonResponse(false, '动态不存在');
        }
        
        // 检查权限（发布者、管理员或圈主可以删除）
        if ($post['user_id'] != $userId && (!isset($post['role']) || $post['role'] > 2)) {
            jsonResponse(false, '无权删除此动态');
        }
        
        // 软删除
        $stmt = $db->prepare("UPDATE circle_post SET status = 0 WHERE post_id = ?");
        $stmt->execute([$postId]);
        
        // 更新圈子动态数
        $stmt = $db->prepare("UPDATE circle SET post_count = post_count - 1 WHERE circle_id = ?");
        $stmt->execute([$post['circle_id']]);
        
        jsonResponse(true, '删除成功');
        
    } catch (PDOException $e) {
        jsonResponse(false, '删除失败，请稍后重试');
    }
}

/**
 * 管理成员
 */
function manageMember() {
    $memberId = intval($_POST['member_id'] ?? 0);
    $action = $_POST['manage_action'] ?? '';
    
    if ($memberId <= 0 || !in_array($action, ['set_admin', 'remove_admin', 'remove'])) {
        jsonResponse(false, '参数错误');
    }
    
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'];
        
        // 获取成员信息
        $stmt = $db->prepare("SELECT cm.*, c.creator_id FROM circle_member cm JOIN circle c ON cm.circle_id = c.circle_id WHERE cm.member_id = ?");
        $stmt->execute([$memberId]);
        $member = $stmt->fetch();
        
        if (!$member) {
            jsonResponse(false, '成员不存在');
        }
        
        // 检查当前用户权限
        $stmt = $db->prepare("SELECT role FROM circle_member WHERE circle_id = ? AND user_id = ? AND status = 1");
        $stmt->execute([$member['circle_id'], $userId]);
        $currentUser = $stmt->fetch();
        
        if (!$currentUser) {
            jsonResponse(false, '无权操作');
        }
        
        // 圈主可以执行所有操作，管理员只能移除普通成员
        if ($currentUser['role'] == 2 && $member['role'] <= 2) {
            jsonResponse(false, '无权操作此成员');
        }
        
        if ($currentUser['role'] > 2) {
            jsonResponse(false, '无权操作');
        }
        
        switch ($action) {
            case 'set_admin':
                $stmt = $db->prepare("UPDATE circle_member SET role = 2 WHERE member_id = ?");
                $stmt->execute([$memberId]);
                jsonResponse(true, '已设为管理员');
                break;
                
            case 'remove_admin':
                $stmt = $db->prepare("UPDATE circle_member SET role = 3 WHERE member_id = ?");
                $stmt->execute([$memberId]);
                jsonResponse(true, '已取消管理员权限');
                break;
                
            case 'remove':
                $stmt = $db->prepare("UPDATE circle_member SET status = 0 WHERE member_id = ?");
                $stmt->execute([$memberId]);
                
                // 更新圈子成员数
                $stmt = $db->prepare("UPDATE circle SET member_count = member_count - 1 WHERE circle_id = ?");
                $stmt->execute([$member['circle_id']]);
                
                jsonResponse(true, '已移除成员');
                break;
        }
        
    } catch (PDOException $e) {
        jsonResponse(false, '操作失败，请稍后重试');
    }
}
