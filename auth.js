(function() {
    function getCookie(name) {
        const v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
        return v ? decodeURIComponent(v[2]) : '';
    }

    const token = localStorage.getItem('vs_token') || sessionStorage.getItem('vs_token') || getCookie('vs_token');
    const userStr = localStorage.getItem('vs_user') || sessionStorage.getItem('vs_user');

    if (!token && !userStr && !getCookie('vs_token')) {
        window.location.replace('index.php');
        return;
    }

    let user = null;
    if (userStr) {
        try {
            user = JSON.parse(userStr);
        } catch(e) {
            localStorage.clear();
            sessionStorage.clear();
            window.location.replace('index.php');
            return;
        }
    }

    // Sync tokens across storage
    if (token) {
        if (!localStorage.getItem('vs_token')) localStorage.setItem('vs_token', token);
        if (!sessionStorage.getItem('vs_token')) sessionStorage.setItem('vs_token', token);
    }
    if (userStr) {
        if (!localStorage.getItem('vs_user')) localStorage.setItem('vs_user', userStr);
        if (!sessionStorage.getItem('vs_user')) sessionStorage.setItem('vs_user', userStr);
    }

    const urlParams = new URLSearchParams(window.location.search);
    const currentPlant = urlParams.get('plant') || '';

    if (user && user.role !== 'admin' && user.plant_id && currentPlant && currentPlant !== user.plant_id) {
        alert("Access Denied: You do not have permission to view this plant.");
        window.location.replace('home.php?plant=' + encodeURIComponent(user.plant_id) + (token ? '&token=' + encodeURIComponent(token) : ''));
        return;
    }
})();
