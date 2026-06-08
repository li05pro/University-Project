<?php
/**
 * 源泉动态网站 - 用户认证相关功能
 */

// 错误处理
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 引入配置文件
$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    echo json_encode(['success' => false, 'message' => '配置文件不存在，请联系管理员']);
    exit;
}

require_once $configFile;

// 启动 session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 获取请求动作
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'register':
        if ($method === 'POST') {
            handleRegister();
        } else {
            jsonResponse(false, '注册请使用 POST 请求');
        }
        break;
    case 'login':
        if ($method === 'POST') {
            handleLogin();
        } else {
            jsonResponse(false, '登录请使用 POST 请求');
        }
        break;
    case 'logout':
        handleLogout();
        break;
    case 'check_username':
        checkUsername();
        break;
    case 'check_email':
        checkEmail();
        break;
    case 'check_login':
        checkLoginStatus();
        break;
    case 'debug':
        debugInfo();
        break;
    default:
        jsonResponse(false, '未知操作: ' . $action);
}

/**
 * 处理用户注册
 */
function handleRegister() {
    try {
        // 获取并验证输入
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $nickname = trim($_POST['nickname'] ?? '');
        $captcha = trim($_POST['captcha'] ?? '');

        // 验证必填字段
        if (empty($username) || empty($email) || empty($password) || empty($nickname)) {
            jsonResponse(false, '请填写所有必填项');
        }

        // 验证验证码
        if (empty($captcha)) {
            jsonResponse(false, '请输入验证码');
        }
        if (strtolower($captcha) !== strtolower($_SESSION['captcha'] ?? '')) {
            jsonResponse(false, '验证码错误，请重新输入');
        }

        // 清除验证码
        unset($_SESSION['captcha']);

        // 验证用户名格式
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            jsonResponse(false, '用户名只能包含字母、数字、下划线，长度3-20位');
        }

        // 验证邮箱格式
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(false, '请输入有效的邮箱地址');
        }

        // 验证密码强度
        if (strlen($password) < 6) {
            jsonResponse(false, '密码长度至少6位');
        }

        // 验证两次密码是否一致
        if ($password !== $confirmPassword) {
            jsonResponse(false, '两次输入的密码不一致');
        }

        // 验证昵称长度
        if (strlen($nickname) < 2 || strlen($nickname) > 50) {
            jsonResponse(false, '昵称长度应在2-50个字符之间');
        }

        // 连接数据库
        $db = getDB();

        // 检查并创建数据表
        $initResult = initDatabaseTables();
        if (!$initResult['success']) {
            jsonResponse(false, '数据库初始化失败：' . $initResult['error']);
        }

        // 检查用户名是否已存在
        $stmt = $db->prepare("SELECT user_id FROM user WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            jsonResponse(false, '该用户名已被注册，请更换用户名');
        }

        // 检查邮箱是否已存在
        $stmt = $db->prepare("SELECT user_id FROM user WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            jsonResponse(false, '该邮箱已被注册，请更换邮箱或直接登录');
        }

        // 密码加密
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // 插入用户数据
        $stmt = $db->prepare("INSERT INTO user (username, password, email, nickname, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$username, $passwordHash, $email, $nickname]);

        $userId = $db->lastInsertId();

        // 自动登录
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;

        jsonResponse(true, '注册成功！正在跳转到登录页面...', [
            'redirect' => 'login.html?registered=1&username=' . urlencode($username)
        ]);

    } catch (PDOException $e) {
        jsonResponse(false, '数据库错误：注册失败，请稍后重试');
    } catch (Exception $e) {
        jsonResponse(false, '注册失败：' . $e->getMessage());
    }
}

/**
 * 处理用户登录
 */
function handleLogin() {
    try {
        // 检查是否处于锁定状态
        $lockKey = 'login_lock_until';
        if (isset($_SESSION[$lockKey]) && $_SESSION[$lockKey] > time()) {
            $remaining = $_SESSION[$lockKey] - time();
            jsonResponse(false, '登录错误次数过多，请' . ceil($remaining) . '秒后再试');
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $captcha = trim($_POST['captcha'] ?? '');

        // 验证必填字段
        if (empty($username) || empty($password)) {
            jsonResponse(false, '请输入用户名和密码');
        }

        // 验证验证码
        if (empty($captcha)) {
            jsonResponse(false, '请输入验证码');
        }
        if (strtolower($captcha) !== strtolower($_SESSION['captcha'] ?? '')) {
            // 验证码错误，增加错误次数
            incrementLoginAttempts();
            jsonResponse(false, '验证码输入错误');
        }

        // 清除验证码
        unset($_SESSION['captcha']);

        // 连接数据库
        $db = getDB();

        // 检查并创建数据表
        $initResult = initDatabaseTables();
        if (!$initResult['success']) {
            jsonResponse(false, '数据库初始化失败');
        }

        // 查询用户信息
        $stmt = $db->prepare("SELECT user_id, username, password, nickname, avatar, status FROM user WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user) {
            incrementLoginAttempts();
            jsonResponse(false, '用户名或密码错误');
        }

        // 检查账号状态
        if ($user['status'] != 1) {
            jsonResponse(false, '账号已被禁用，请联系管理员');
        }

        // 验证密码
        if (!password_verify($password, $user['password'])) {
            incrementLoginAttempts();
            jsonResponse(false, '用户名或密码错误');
        }

        // 登录成功，清除错误次数
        clearLoginAttempts();

        // 创建Session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];

        // 更新最后登录时间
        $stmt = $db->prepare("UPDATE user SET last_login = NOW() WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);

        jsonResponse(true, '登录成功！正在跳转到首页...', [
            'redirect' => 'index.html',
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'nickname' => $user['nickname'],
            'avatar' => $user['avatar'] ? 'uploads/avatars/' . $user['avatar'] : null
        ]);

    } catch (PDOException $e) {
        jsonResponse(false, '数据库错误：登录失败，请稍后重试');
    } catch (Exception $e) {
        jsonResponse(false, '登录失败：' . $e->getMessage());
    }
}

/**
 * 处理用户退出
 */
function handleLogout() {
    session_destroy();
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
    jsonResponse(true, '已退出登录');
}

/**
 * 检查用户名是否可用
 */
function checkUsername() {
    try {
        $username = trim($_GET['username'] ?? '');

        if (empty($username)) {
            jsonResponse(false, '用户名不能为空');
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT user_id FROM user WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->fetch()) {
            jsonResponse(false, '该用户名已被使用');
        } else {
            jsonResponse(true, '用户名可用');
        }
    } catch (PDOException $e) {
        jsonResponse(false, '检查失败');
    }
}

/**
 * 检查邮箱是否可用
 */
function checkEmail() {
    try {
        $email = trim($_GET['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(false, '请输入有效的邮箱地址');
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT user_id FROM user WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            jsonResponse(false, '该邮箱已被注册');
        } else {
            jsonResponse(true, '邮箱可用');
        }
    } catch (PDOException $e) {
        jsonResponse(false, '检查失败');
    }
}

/**
 * 检查用户登录状态
 */
function checkLoginStatus() {
    try {
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
            $db = getDB();
            $stmt = $db->prepare("SELECT user_id, username, nickname, avatar FROM user WHERE user_id = ? AND status = 1");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if ($user) {
                jsonResponse(true, '已登录', [
                    'logged_in' => true,
                    'user' => [
                        'user_id' => $user['user_id'],
                        'username' => $user['username'],
                        'nickname' => $user['nickname'],
                        'avatar' => $user['avatar'] ? 'uploads/avatars/' . $user['avatar'] : null
                    ]
                ]);
            } else {
                jsonResponse(false, '用户不存在或已被禁用', ['logged_in' => false]);
            }
        } else {
            jsonResponse(false, '未登录', ['logged_in' => false]);
        }
    } catch (PDOException $e) {
        jsonResponse(false, '检查登录状态失败', ['logged_in' => false]);
    }
}

/**
 * 调试信息
 */
function debugInfo() {
    echo json_encode([
        'success' => true,
        'message' => 'auth.php 正常工作',
        'session_id' => session_id(),
        'session_data' => $_SESSION,
        'post_data' => array_keys($_POST),
        'get_data' => array_keys($_GET)
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 增加登录错误次数
 * 达到5次错误后锁定15秒
 */
function incrementLoginAttempts() {
    $attemptsKey = 'login_attempts';
    $lockKey = 'login_lock_until';
    $maxAttempts = 5;
    $lockDuration = 15; // 锁定15秒

    // 初始化错误次数
    if (!isset($_SESSION[$attemptsKey])) {
        $_SESSION[$attemptsKey] = 0;
    }

    // 增加错误次数
    $_SESSION[$attemptsKey]++;

    // 检查是否达到最大错误次数
    if ($_SESSION[$attemptsKey] >= $maxAttempts) {
        $_SESSION[$lockKey] = time() + $lockDuration;
        $_SESSION[$attemptsKey] = 0; // 重置错误次数
    }
}

/**
 * 清除登录错误次数
 */
function clearLoginAttempts() {
    $attemptsKey = 'login_attempts';
    $lockKey = 'login_lock_until';

    unset($_SESSION[$attemptsKey]);
    unset($_SESSION[$lockKey]);
}
