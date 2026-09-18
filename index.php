<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$token = !empty($_COOKIE['vs_token']) ? $_COOKIE['vs_token'] : (!empty($_SESSION['vs_token']) ? $_SESSION['vs_token'] : '');
if (!empty($token)) {
    require_once __DIR__ . '/config.php';
    if (isset($conn) && $conn instanceof mysqli) {
        $safeToken = $conn->real_escape_string($token);
        $res = $conn->query("SELECT role, plant_id FROM users WHERE auth_token = '$safeToken' LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $u = $res->fetch_assoc();
            if ($u['role'] === 'admin') {
                header('Location: admin.php?token=' . urlencode($token));
                exit;
            } else {
                $p = !empty($u['plant_id']) ? $u['plant_id'] : 'vinoba-velliyanai';
                header('Location: home.php?plant=' . urlencode($p) . '&token=' . urlencode($token));
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solar Plants Sign In</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-800 to-slate-900 px-4">

    <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-2xl w-full max-w-sm">
        <div class="text-center mb-6">
            <div class="w-12 h-12 bg-blue-600 text-white rounded-xl mx-auto flex items-center justify-center text-xl shadow-lg mb-3">
                <i class="fa-solid fa-solar-panel"></i>
            </div>
            <h2 class="text-2xl font-bold text-slate-800">Welcome Back</h2>
            <p class="text-xs text-slate-500 mt-1">Sign in to Solar Dashboard</p>
        </div>
        
        <form id="loginForm" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Email</label>
                <input type="email" id="email" required class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Password</label>
                <input type="password" id="password" required class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
            </div>
            <button type="submit" class="w-full py-2.5 px-4 text-sm text-white font-semibold rounded-lg bg-blue-600 hover:bg-blue-700 transition-all mt-4">
                Sign In
            </button>
            <p id="login-error" class="text-red-500 text-xs text-center hidden font-medium mt-2"></p>
        </form>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-2"></i> Verifying...`;
            btn.disabled = true;

            try {
                const res = await fetch('api.php?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: document.getElementById('email').value, password: document.getElementById('password').value })
                });
                const data = await res.json();
                
                if (data.status === 'success') {
                    localStorage.setItem('userRole', data.user.role);
                    localStorage.setItem('vs_token', data.token);
                    localStorage.setItem('vs_user', JSON.stringify(data.user));
                    
                    sessionStorage.setItem('userRole', data.user.role);
                    sessionStorage.setItem('vs_token', data.token);
                    sessionStorage.setItem('vs_user', JSON.stringify(data.user));

                    document.cookie = "vs_token=" + encodeURIComponent(data.token) + "; path=/; max-age=" + (86400 * 30);
                    document.cookie = "user_role=" + encodeURIComponent(data.user.role) + "; path=/; max-age=" + (86400 * 30);
                    if (data.user.plant_id) {
                        document.cookie = "user_plant=" + encodeURIComponent(data.user.plant_id) + "; path=/; max-age=" + (86400 * 30);
                    }

                    if (data.user.role === 'admin') {
                        window.location.href = 'admin.php?token=' + encodeURIComponent(data.token);
                    } else if (data.user.plant_id) {
                        window.location.href = 'home.php?plant=' + encodeURIComponent(data.user.plant_id) + '&token=' + encodeURIComponent(data.token);
                    } else {
                        window.location.href = 'home.php?token=' + encodeURIComponent(data.token);
                    }
                } else {
                    const err = document.getElementById('login-error');
                    err.innerHTML = `<i class="fa-solid fa-circle-exclamation mr-1"></i> ${data.message}`;
                    err.classList.remove('hidden');
                }
            } catch (error) {
                console.error('Login Error:', error);
            } finally {
                btn.innerHTML = `Sign In`;
                btn.disabled = false;
            }
        });
    </script>
</body>
</html>