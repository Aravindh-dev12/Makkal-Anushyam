<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function findUserByAuthToken(string $token): ?array
{
    if ($token === '') {
        return null;
    }

    require_once __DIR__ . '/config.php';

    if (!isset($conn) || !($conn instanceof mysqli)) {
        return null;
    }

    $safeToken = $conn->real_escape_string($token);
    $res = $conn->query("SELECT * FROM users WHERE auth_token = '$safeToken' LIMIT 1");

    if (!$res || $res->num_rows === 0) {
        return null;
    }

    return $res->fetch_assoc();
}

/*
 * Session is the primary authenticated state.
 * This prevents a stale ?token=... URL from logging a valid user out
 * when moving between pages.
 */
$user = null;
$token = '';

if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    $candidate = $_SESSION['user'];
    if (!empty($candidate['email']) && !empty($candidate['role'])) {
        $user = $candidate;
        $token = trim((string)($_SESSION['vs_token'] ?? ''));
    }
}

/*
 * If the PHP session is unavailable/expired, recover with the persistent
 * browser cookie first, then use a URL token only as a last resort.
 */
if (!$user) {
    $cookieToken = trim((string)($_COOKIE['vs_token'] ?? ''));
    $urlToken = trim((string)($_GET['token'] ?? ''));

    if ($cookieToken !== '') {
        $token = $cookieToken;
        $user = findUserByAuthToken($cookieToken);
    }

    if (!$user && $urlToken !== '' && $urlToken !== $cookieToken) {
        $token = $urlToken;
        $user = findUserByAuthToken($urlToken);
    }

    if ($user) {
        $_SESSION['vs_token'] = $token;
        $_SESSION['user'] = $user;
        setcookie('vs_token', $token, time() + (86400 * 30), '/');
        if (!empty($user['role'])) {
            setcookie('user_role', (string)$user['role'], time() + (86400 * 30), '/');
        }
        if (!empty($user['plant_id'])) {
            setcookie('user_plant', (string)$user['plant_id'], time() + (86400 * 30), '/');
        }
    }
}

if (!$user) {
    /*
     * Only clear authentication state when there is actually no recoverable
     * authenticated user. Do not destroy a valid session because a stale URL
     * token was supplied.
     */
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    setcookie('vs_token', '', time() - 3600, '/');
    header('Location: index.php?expired=1');
    exit;
}

$currentPlant = isset($_GET['plant']) ? trim((string)$_GET['plant']) : '';

/*
 * Non-admin users stay on their assigned plant. Admin users may choose
 * the plant via the page's plant selector/query parameter.
 */
if (($user['role'] ?? '') !== 'admin' && !empty($user['plant_id'])) {
    $assignedPlant = trim((string)$user['plant_id']);

    if ($currentPlant === '') {
        $query = $_GET;
        $query['plant'] = $assignedPlant;

        /*
         * Keep token in the URL only when it was already explicitly supplied
         * by the caller. Normal navigation can rely on the session/cookie.
         */
        $currentPage = basename($_SERVER['PHP_SELF']);
        header('Location: ' . $currentPage . '?' . http_build_query($query));
        exit;
    }

    if ($currentPlant !== $assignedPlant) {
        $redirect = 'home.php?plant=' . urlencode($assignedPlant);
        header('Location: ' . $redirect);
        exit;
    }
}
?>
