<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
setcookie('vs_token', '', time() - 3600, '/');
setcookie('user_role', '', time() - 3600, '/');
setcookie('user_plant', '', time() - 3600, '/');
session_destroy();
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Logging out...</title>
<noscript><meta http-equiv="refresh" content="0; url=index.php"></noscript>
</head>
<body>
<script>
    localStorage.removeItem('userRole');
    localStorage.removeItem('vs_token');
    localStorage.removeItem('vs_user');
    sessionStorage.removeItem('userRole');
    sessionStorage.removeItem('vs_token');
    sessionStorage.removeItem('vs_user');
    document.cookie = "vs_token=; path=/; max-age=0; expires=Thu, 01 Jan 1970 00:00:00 GMT";
    document.cookie = "user_role=; path=/; max-age=0; expires=Thu, 01 Jan 1970 00:00:00 GMT";
    document.cookie = "user_plant=; path=/; max-age=0; expires=Thu, 01 Jan 1970 00:00:00 GMT";
    window.location.replace('index.php');
</script>
<p style="text-align:center;margin-top:40px;font-family:sans-serif;color:#666;">Logging out...</p>
</body>
</html>
