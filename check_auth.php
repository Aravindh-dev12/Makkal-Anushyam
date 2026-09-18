<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token = '';
if (!empty($_GET['token'])) {
    $token = trim((string)$_GET['token']);
} elseif (!empty($_COOKIE['vs_token'])) {
    $token = trim((string)$_COOKIE['vs_token']);
} elseif (!empty($_SESSION['vs_token'])) {
    $token = trim((string)$_SESSION['vs_token']);
}

$user = null;

if (!empty($token)) {
    require_once __DIR__ . '/config.php';
    if (isset($conn) && $conn instanceof mysqli) {
        $safeToken = $conn->real_escape_string($token);
        $res = $conn->query("SELECT * FROM users WHERE auth_token = '$safeToken' LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $user = $res->fetch_assoc();
            $_SESSION['vs_token'] = $token;
            $_SESSION['user'] = $user;
            if (empty($_COOKIE['vs_token']) || $_COOKIE['vs_token'] !== $token) {
                setcookie('vs_token', $token, time() + (86400 * 30), '/');
            }
        }
    }
}

if (!$user) {
    // Clear any stale session or cookie data
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    setcookie('vs_token', '', time() - 3600, '/');
    header('Location: index.php');
    exit;
}

$currentPlant = isset($_GET['plant']) ? trim((string)$_GET['plant']) : '';
// Only non-admin users are restricted to their assigned plant
if ($user['role'] !== 'admin' && !empty($user['plant_id'])) {
    if (empty($currentPlant)) {
        $query = $_GET;
        $query['plant'] = $user['plant_id'];
        if (empty($query['token'])) {
            $query['token'] = $token;
        }
        $currentPage = basename($_SERVER['PHP_SELF']);
        header('Location: ' . $currentPage . '?' . http_build_query($query));
        exit;
    }
    if ($currentPlant !== $user['plant_id']) {
        $redirect = 'home.php?plant=' . urlencode($user['plant_id']) . '&token=' . urlencode($token);
        header('Location: ' . $redirect);
        exit;
    }
}
?>
