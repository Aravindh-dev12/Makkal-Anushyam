<?php
require 'check_auth.php';
require 'config.php';

$plants = [];
try {
    $pRes = $conn->query("SELECT id, name, capacity, location FROM plants ORDER BY name ASC");
    if ($pRes) {
        while ($row = $pRes->fetch_assoc()) $plants[] = $row;
    }
} catch (Exception $e) {}

if (empty($plants)) {
    $plants = [
        ['id'=>'vinoba-velliyanai','name'=>'Vinoba Velliyanai','capacity'=>2.0,'location'=>'Karur'],
        ['id'=>'makkalpower','name'=>'Makkal Power','capacity'=>2.0,'location'=>'Karur'],
        ['id'=>'anushyam','name'=>'Anushyam Plant','capacity'=>2.0,'location'=>'Karur']
    ];
}

$userPlantId = $user['plant_id'] ?? '';
if (isset($_GET['plant'])) {
    $selPlant = $conn->real_escape_string($_GET['plant']);
} elseif ($user['role'] !== 'admin' && $userPlantId) {
    $selPlant = $userPlantId;
} else {
    $selPlant = $plants[0]['id'] ?? '';
}
$pInfo = ['name'=>'Unknown','capacity'=>0,'location'=>'Unknown'];
foreach ($plants as $p) {
    if ($p['id'] === $selPlant) { $pInfo = $p; break; }
}
if (empty($pInfo) && !empty($plants)) $pInfo = $plants[0];

$wsUrl = 'wss://vinobasolar.scadahub.in:5001';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Reports - Vinoba Solar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="sidebar-control.js?v=3" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .report-table { table-layout: fixed; min-width: 1400px; width: 100%; border-collapse: collapse; }
        .table-hscroll { overflow-x: auto; overflow-y: visible; }
        .report-table th, .report-table td { padding: 6px 4px; text-align: center; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .report-table td { font-size: 11px; color: #334155; border-bottom: 1px solid #f1f5f9; font-variant-numeric: tabular-nums; }
        .report-table th { color: #475569; font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: 0.02em; border: 1px solid #e2e8f0; line-height: 1.2; }
        .report-table tbody tr { transition: background-color 0.15s ease; background-color: #ffffff; }
        .report-table tbody tr:hover { background-color: #f8fafc !important; }
        .table-hscroll::-webkit-scrollbar { height: 8px; }
        .table-hscroll::-webkit-scrollbar-track { background: #f8fafc; border-radius: 4px; }
        .table-hscroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .table-hscroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .pdf-mode .table-hscroll {
            overflow: visible !important;
            width: 100% !important;
            max-width: none !important;
        }
        .pdf-mode .report-table {
            table-layout: fixed !important;
            width: 100% !important;
        }
        .pdf-mode {
            background: #ffffff !important;
            padding: 15px !important;
        }
    </style>
</head>
<body class="h-full bg-slate-50 text-gray-800 font-sans">
    <div class="min-h-screen flex relative">
        <div id="overlay" class="fixed inset-0 bg-slate-900 bg-opacity-40 hidden z-30 md:hidden transition-opacity"></div>
        <div id="sidebar-container"></div>
        <main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden">
        <header class="bg-white border-b border-gray-200 p-4 sm:px-6 flex justify-between items-center shadow-sm sticky top-0 z-20">
            <div class="flex items-center gap-3 sm:gap-4">
                <button id="menuBtn" class="md:hidden text-green-700 text-2xl focus:outline-none">&#9776;</button>
                <div>
                    <h2 class="text-lg sm:text-xl font-bold text-gray-800">System Reports</h2>
                    <p class="text-xs text-gray-500 hidden sm:block">Separate Inverter / Electrical and WMAS Weather Telemetry Reports</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="exportToPDF()" class="bg-rose-600 hover:bg-rose-700 text-white font-semibold py-2 px-3 sm:px-4 rounded-lg shadow-sm transition-colors flex items-center gap-2 text-sm">
                    <i class="fa-solid fa-file-pdf"></i><span class="hidden sm:inline">Export PDF</span>
                </button>
                <button onclick="downloadJson()" id="dlBtn" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-3 sm:px-4 rounded-lg shadow-sm transition-colors flex items-center gap-2 text-sm opacity-50 cursor-not-allowed" disabled>
                    <i class="fa-solid fa-download"></i><span class="hidden sm:inline">JSON</span>
                </button>
            </div>
        </header>

        <div class="p-4 sm:p-6 w-full flex flex-col gap-6 max-w-[1750px] mx-auto">
            <!-- Filter Bar -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-5 flex flex-col lg:flex-row gap-4 justify-between items-start lg:items-center">
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-table-cells"></i> Report Type
                    </span>
                </div>
                <div class="flex items-center gap-2 w-full lg:w-auto flex-wrap">
                    <select id="plantSelect" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 outline-none bg-gray-50 font-medium cursor-pointer"></select>
                    <select id="reportSection" onchange="toggleReportSection()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 outline-none bg-gray-50 font-medium cursor-pointer">
                        <option value="inverter">Inverter / Electrical Report</option>
                        <option value="wmas">WMAS Weather Report</option>
                    </select>
                    <select id="reportType" onchange="toggleInputs()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 outline-none bg-gray-50 font-medium cursor-pointer">
                        <option value="daily">Daily Report</option>
                        <option value="monthly">Monthly Report</option>
                    </select>
                    <input type="date" id="dateSelect" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 outline-none bg-gray-50 font-medium">
                    <input type="month" id="monthSelect" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 outline-none bg-gray-50 font-medium hidden">
                    <button onclick="generateReportData(); startAutoRefresh();" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition flex items-center gap-2 text-sm">
                        <i class="fa-solid fa-eye"></i> View
                    </button>
                </div>
            </div>

            <!-- Report Card -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm flex-1 flex flex-col">
                <div id="printableReport" class="p-5 bg-white w-full">
                    <div class="border-b-2 border-emerald-600 pb-4 mb-6">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <h1 class="text-2xl font-black text-gray-900 tracking-tight" id="reportHeaderPlantName"><?php echo htmlspecialchars(strtoupper($pInfo['name'] ?? 'SOLAR ENERGY')); ?></h1>
                                <h2 id="reportMainTitle" class="text-base font-bold text-emerald-700 mt-0.5">Inverter / Electrical Report</h2>
                            </div>
                        </div>
                        <div class="report-info-grid grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm mt-4 font-medium text-gray-700 bg-gray-50 p-3 rounded-lg border border-gray-100">
                            <div><span class="text-gray-500 uppercase text-xs font-bold block">Plant Location</span><span id="displayLocation"><?php echo htmlspecialchars($pInfo['location'] ?? 'Karur'); ?></span></div>
                            <div><span class="text-gray-500 uppercase text-xs font-bold block">Plant Capacity</span><span id="displayCapacity"><?php echo ($pInfo['capacity'] ?? 2.0) . ' MW'; ?></span></div>
                            <div><span class="text-gray-500 uppercase text-xs font-bold block" id="reportDateLabel">Report Date</span><span id="displayDate" class="font-bold text-gray-900">--</span><br><span id="liveStatus" class="text-xs font-bold text-emerald-600 mt-0.5 inline-flex items-center gap-1"><span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>Auto-refresh ON</span></div>
                        </div>
                    </div>
                    <div class="table-hscroll w-full">
                        <table class="w-full report-table border-collapse">
                            <thead></thead>
                            <tbody id="reportTableBody">
                                <tr><td colspan="30" class="py-10 text-center text-gray-500"><div class="flex flex-col items-center justify-center"><div class="w-8 h-8 border-3 border-gray-200 border-t-emerald-600 rounded-full animate-spin mb-2"></div>Fetching...</div></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-12 pt-8 flex justify-between text-sm font-bold text-gray-800 border-t border-gray-200">
                        <div class="text-center w-40"><div class="border-b border-gray-400 mb-2 h-8"></div>Operator Signature</div>
                        <div class="text-center w-40"><div class="border-b border-gray-400 mb-2 h-8"></div>Site Engineer</div>
                        <div class="text-center w-40"><div class="border-b border-gray-400 mb-2 h-8"></div>Plant Manager</div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    </div>

    <script>
        const storedRole = sessionStorage.getItem('userRole') || localStorage.getItem('userRole');
        const urlToken = new URLSearchParams(window.location.search).get('token');
        if(!storedRole && !urlToken && !document.cookie.includes('vs_token')) {
            window.location.replace('index.php');
        }
        const userRole = <?php echo json_encode($user['role'] ?? 'user'); ?>;
        const userPlant = <?php echo json_encode($user['plant_id'] ?? ''); ?>;

        const plantSelect = document.getElementById('plantSelect');
        const plantOptions = <?php echo json_encode($plants); ?>;
        const plantMeta = {};
        plantOptions.forEach(p => { plantMeta[p.id] = p; });
        if (userRole === 'admin') {
            const allOpt = document.createElement('option'); allOpt.value = 'all'; allOpt.textContent = 'All Plants'; plantSelect.appendChild(allOpt);
        }
        let hasUserPlant = false;
        plantOptions.forEach(opt => {
            if (userRole === 'admin' || opt.id === userPlant) {
                const option = document.createElement('option'); option.value = opt.id; option.textContent = opt.name; plantSelect.appendChild(option);
                if (opt.id === userPlant) hasUserPlant = true;
            }
        });
        if (userRole !== 'admin' && !hasUserPlant && userPlant) {
            const option = document.createElement('option'); option.value = userPlant; option.textContent = userPlant; plantSelect.appendChild(option);
        }
        plantSelect.value = <?php echo json_encode($selPlant); ?>;
        if (userRole !== 'admin') { plantSelect.style.display = 'none'; }
        plantSelect.addEventListener('change', function() {
            const pid = this.value;
            if (plantMeta[pid]) {
                document.getElementById('reportHeaderPlantName').innerText = plantMeta[pid].name.toUpperCase() + ' SOLAR ENERGY';
                document.getElementById('displayLocation').innerText = plantMeta[pid].location || 'Karur';
                document.getElementById('displayCapacity').innerText = (plantMeta[pid].capacity || 2.0) + ' MW';
            }
        });

        let lastReportData = null;
        let currentReportSection = 'inverter';
        let refreshInterval = null;
        let ws = null;
        let wsConnected = false;
        let pendingReportRequest = false;
        let wsReportTimeout = null;
        const token = new URLSearchParams(window.location.search).get('token') || sessionStorage.getItem('vs_token') || '';
        const dateInput = document.getElementById('dateSelect');
        const monthInput = document.getElementById('monthSelect');
        dateInput.value = new Date().toISOString().split('T')[0];
        monthInput.value = new Date().toISOString().slice(0, 7);

        function loadSidebar() {
            fetch('sidebar.html', { cache: 'no-store' }).then(r => r.text()).then(html => {
                document.getElementById('sidebar-container').innerHTML = html;
                const _token = new URLSearchParams(window.location.search).get('token') || sessionStorage.getItem('vs_token') || '';
                let _plant = plantSelect.value || 'vinoba-velliyanai';
                document.querySelectorAll('#sidebarNav a').forEach(link => {
                    let href = link.getAttribute('href');
                    if (!href || href.indexOf('logout') !== -1) return;
                    if (href.indexOf('?plant=') === -1) {
                        link.setAttribute('href', href + '?plant=' + encodeURIComponent(_plant) + '&token=' + encodeURIComponent(_token));
                    } else if (href.indexOf('token=') === -1) {
                        link.setAttribute('href', href + '&token=' + encodeURIComponent(_token));
                    }
                });
                const _pn = document.getElementById('sidebarPlantName');
                if (_pn) {
                    const _names = {'vinoba-velliyanai':'Vinoba Velliyanai','makkalpower':'Makkal Power','anushyam':'Anushyam Plant'};
                    _pn.textContent = _names[_plant] || _plant;
                }
                if (typeof initSidebar === 'function') initSidebar();
                const curPage = window.location.pathname.split('/').pop() || 'reports.php';
                document.querySelectorAll('#sidebarNav a').forEach(link => {
                    const dp = link.getAttribute('data-page');
                    if (dp && (dp === curPage || dp.replace('.php','.html') === curPage)) {
                        link.classList.add('!bg-slate-100', '!text-emerald-700', '!border-emerald-500');
                    }
                });
                document.getElementById('menuBtn')?.addEventListener('click', () => { document.getElementById('sidebar')?.classList.remove('-translate-x-full'); document.getElementById('overlay')?.classList.remove('hidden'); });
                document.getElementById('closeSidebarBtn')?.addEventListener('click', () => { document.getElementById('sidebar')?.classList.add('-translate-x-full'); document.getElementById('overlay')?.classList.add('hidden'); });
                document.getElementById('overlay')?.addEventListener('click', () => { document.getElementById('sidebar')?.classList.add('-translate-x-full'); document.getElementById('overlay')?.classList.add('hidden'); });
            });
        }

        function startAutoRefresh() {
            if (refreshInterval) clearInterval(refreshInterval);
            refreshInterval = setInterval(() => {
                generateReportData();
            }, 30000);
        }

        function stopAutoRefresh() {
            if (refreshInterval) { clearInterval(refreshInterval); refreshInterval = null; }
        }

        function updateLiveStatus() {
            const now = new Date();
            const time = now.toLocaleTimeString('en-IN', {
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
            });
            document.getElementById('liveStatus').innerHTML = '<span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>Updated ' + time;
        }

        function formatRailwayTime(value) {
            const raw = String(value ?? '').trim();
            if (!raw) return '';
            const match = raw.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)?$/i);
            if (!match) return raw;
            let hour = Number(match[1]);
            const minute = Number(match[2]);
            const meridiem = (match[3] || '').toUpperCase();
            if (meridiem) {
                if (hour < 1 || hour > 12 || minute > 59) return raw;
                if (meridiem === 'AM' && hour === 12) hour = 0;
                if (meridiem === 'PM' && hour !== 12) hour += 12;
            } else if (hour > 23 || minute > 59) {
                return raw;
            }
            return String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
        }

        function toggleInputs() {
            const type = document.getElementById('reportType').value;
            if (type === 'daily') {
                dateInput.classList.remove('hidden');
                monthInput.classList.add('hidden');
                document.getElementById('reportDateLabel').innerText = "Report Date";
            } else {
                dateInput.classList.add('hidden');
                monthInput.classList.remove('hidden');
                document.getElementById('reportDateLabel').innerText = "Report Month";
            }
        }

        const wsUrl = <?php echo json_encode($wsUrl); ?>;
        let liveWeather = { rad: null, ptemp: null, atemp: null, wind: null, hum: null, lastAt: 0 };
        let weatherBuckets = {};

        function slot15Min(timeStr) {
            if (!timeStr) return '';
            const m = String(timeStr).match(/(\d{1,2}):(\d{2})/);
            if (!m) return '';
            const h = String(Number(m[1])).padStart(2, '0');
            const min = Number(m[2]);
            const roundedM = Math.floor(min / 15) * 15;
            return h + ':' + String(roundedM).padStart(2, '0');
        }

        function handleWSDailyWeather(rows) {
            if (!rows || !rows.length) return;
            rows.forEach(r => {
                const s = slot15Min(r.time || r.timestamp);
                if (!s) return;
                if (!weatherBuckets[s]) {
                    weatherBuckets[s] = { rad: 0, ptemp: 0, atemp: 0, wind: 0, hum: 0 };
                }
                const dev = (r.device || '').toLowerCase();
                const v = r.values || {};
                if (dev.includes('pyran') && v['raw data'] !== undefined) weatherBuckets[s].rad = Number(v['raw data']);
                if (dev.includes('pannel') && v['pannel temperature'] !== undefined && v['pannel temperature'] !== null) weatherBuckets[s].ptemp = Number(v['pannel temperature']);
                if (dev.includes('ambient') && v['Ambient temperature'] !== undefined) weatherBuckets[s].atemp = Number(v['Ambient temperature']);
                if (dev.includes('wind') && v['windspeed'] !== undefined) weatherBuckets[s].wind = Number(v['windspeed']);
                if (dev.includes('humid') && v['humidity'] !== undefined) weatherBuckets[s].hum = Number(v['humidity']);
            });

            if (lastReportData && Array.isArray(lastReportData.data)) {
                let anyUpdated = false;
                lastReportData.data.forEach(nr => {
                    const b = weatherBuckets[nr.time_label];
                    if (b) {
                        if (b.rad > 0) { nr.radiation = b.rad; anyUpdated = true; }
                        const pt = b.ptemp > 0 ? b.ptemp : b.atemp;
                        if (pt > 0) { nr.panel_temp = pt; anyUpdated = true; }
                        if (b.atemp > 0) { nr.ambient_temp = b.atemp; anyUpdated = true; }
                        if (b.wind > 0) { nr.wind_speed = b.wind; anyUpdated = true; }
                        if (b.hum > 0) { nr.humidity = b.hum; anyUpdated = true; }
                    }
                });
                if (anyUpdated) {
                    const type = document.getElementById('reportType').value;
                    const invNames = lastReportData.meta ? lastReportData.meta.inv_names : null;
                    renderReportData(type, lastReportData.data, invNames);
                }
            }
        }

        function connectReportWS() {
            if (ws && (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING)) return;
            try {
                ws = new WebSocket(wsUrl);
                ws.onopen = () => {
                    wsConnected = true;
                    // Always subscribe to vinoba-velliyanai for weather sensors
                    ws.send(JSON.stringify({ type: 'subscribe', unit_id: 'vinoba-velliyanai' }));
                    const curP = plantSelect.value || 'vinoba-velliyanai';
                    if (curP !== 'vinoba-velliyanai') {
                        ws.send(JSON.stringify({ type: 'subscribe', unit_id: curP }));
                    }
                    if (pendingReportRequest) sendReportRequest();
                };
                ws.onmessage = (e) => {
                    try {
                        const d = JSON.parse(e.data);
                        if (d.type === 'daily_data_result' && Array.isArray(d.data)) {
                            handleWSDailyWeather(d.data);
                            return;
                        }
                        const reportTypes = ['report_data','generate_report','generate_report_result','report','report_result','report_generated'];
                        if (reportTypes.includes(d.type) || d.columns || d.rows) {
                            handleWSReportResponse(d);
                            return;
                        }

                        // Weather live telemetry capture
                        const taskStr = (d.task || '').toString().toLowerCase();
                        const devStr = (d.device || '').toString().toLowerCase();
                        if (d.values && (taskStr === 'wmos' || taskStr === 'weather' || devStr.includes('pyran') || devStr.includes('pannel') || devStr.includes('ambient') || devStr.includes('wind') || devStr.includes('humid'))) {
                            for (const k in d.values) {
                                const kl = k.toLowerCase();
                                const val = parseFloat(d.values[k]) || 0;
                                if (/rad|raw data|irradiance/i.test(kl) || devStr.includes('pyran')) liveWeather.rad = val;
                                if (/pannel|panel/i.test(kl) || devStr.includes('pannel')) liveWeather.ptemp = val;
                                if (/ambient/i.test(kl) || devStr.includes('ambient')) liveWeather.atemp = val;
                                if (/wind/i.test(kl) || devStr.includes('wind')) liveWeather.wind = val;
                                if (/humid/i.test(kl) || devStr.includes('humid')) liveWeather.hum = val;
                            }
                            if (lastReportData && Array.isArray(lastReportData.data)) {
                                const type = document.getElementById('reportType').value;
                                const selectedDate = type === 'daily' ? dateInput.value : monthInput.value;
                                const todayStr = new Date().toISOString().split('T')[0];
                                if (type === 'daily' && selectedDate === todayStr) {
                                    const curSlot = slot15Min(new Date().toTimeString().slice(0,5));
                                    const targetRow = lastReportData.data.find(r => r.time_label === curSlot) || lastReportData.data[lastReportData.data.length - 1];
                                    if (targetRow) {
                                        let upd = false;
                                        if (liveWeather.rad > 0 && targetRow.radiation !== liveWeather.rad) { targetRow.radiation = liveWeather.rad; upd = true; }
                                        const p = liveWeather.ptemp > 0 ? liveWeather.ptemp : liveWeather.atemp;
                                        if (p > 0 && targetRow.panel_temp !== p) { targetRow.panel_temp = p; upd = true; }
                                        if (liveWeather.atemp > 0 && targetRow.ambient_temp !== liveWeather.atemp) { targetRow.ambient_temp = liveWeather.atemp; upd = true; }
                                        if (liveWeather.wind > 0 && targetRow.wind_speed !== liveWeather.wind) { targetRow.wind_speed = liveWeather.wind; upd = true; }
                                        if (liveWeather.hum > 0 && targetRow.humidity !== liveWeather.hum) { targetRow.humidity = liveWeather.hum; upd = true; }
                                        if (upd) {
                                            renderReportData(type, lastReportData.data, lastReportData.meta ? lastReportData.meta.inv_names : null);
                                        }
                                    }
                                }
                            }
                        }
                    } catch(err) { console.error('Reports WS parse error', err); }
                };
                ws.onclose = () => { wsConnected = false; setTimeout(connectReportWS, 5000); };
                ws.onerror = (err) => { wsConnected = false; };
            } catch(err) { console.error('WS connect failed', err); }
        }

        function sendReportRequest() {
            if (!ws || ws.readyState !== WebSocket.OPEN) return false;
            const plant = plantSelect.value || 'vinoba-velliyanai';
            const type = document.getElementById('reportType').value;
            const selectedDate = type === 'daily' ? dateInput.value : monthInput.value;
            const pageName = type === 'daily' ? 'inverter&vcb-daily' : 'inverter&vcb-monthly';
            ws.send(JSON.stringify({ type: 'subscribe', unit_id: plant }));
            ws.send(JSON.stringify({ type: 'generate_report', unit_id: plant, pageName: pageName, date: selectedDate }));

            // For daily reports, also fetch high-resolution weather telemetry directly from WebSocket port 5001!
            if (type === 'daily') {
                ws.send(JSON.stringify({ type: 'subscribe', unit_id: 'vinoba-velliyanai' }));
                const weatherDevs = ['Pyranometer', 'pannel temperature', 'Ambient Temperature', 'Wind', 'Humidity'];
                weatherDevs.forEach(dev => {
                    ws.send(JSON.stringify({ type: 'get_daily_data', unit_id: 'vinoba-velliyanai', device: dev, date: selectedDate }));
                });
            }
            return true;
        }

        async function mergeWeatherFromAPI(type, selectedDate, plant, normalizedRows, invNames) {
            try {
                const res = await fetch(`api_reports.php?type=${type}&date=${selectedDate}&plant=${plant}&token=${token}`, { headers: token ? { 'Authorization': 'Bearer ' + token } : {} });
                const json = await res.json();
                if (json && json.success && Array.isArray(json.data)) {
                    const wMap = {};
                    json.data.forEach(r => {
                        if (r.time_label) wMap[r.time_label] = r;
                    });
                    let updated = false;
                    normalizedRows.forEach((nr, idx) => {
                        const w = wMap[nr.time_label];
                        if (w) {
                            if (w.radiation > 0 && nr.radiation === 0) { nr.radiation = w.radiation; updated = true; }
                            if (w.panel_temp > 0 && nr.panel_temp === 0) { nr.panel_temp = w.panel_temp; updated = true; }
                            if (w.ambient_temp > 0 && nr.ambient_temp === 0) { nr.ambient_temp = w.ambient_temp; updated = true; }
                            if (w.wind_speed > 0 && nr.wind_speed === 0) { nr.wind_speed = w.wind_speed; updated = true; }
                            if (w.humidity > 0 && nr.humidity === 0) { nr.humidity = w.humidity; updated = true; }
                        }
                    });
                    // If today's latest row has no weather yet, fill from liveWeather
                    const todayStr = new Date().toISOString().split('T')[0];
                    if (type === 'daily' && selectedDate === todayStr && normalizedRows.length > 0) {
                        const lastIdx = normalizedRows.length - 1;
                        if (liveWeather.rad > 0 && normalizedRows[lastIdx].radiation === 0) normalizedRows[lastIdx].radiation = liveWeather.rad;
                        if (liveWeather.ptemp > 0 && normalizedRows[lastIdx].panel_temp === 0) normalizedRows[lastIdx].panel_temp = liveWeather.ptemp;
                        if (liveWeather.atemp > 0 && normalizedRows[lastIdx].ambient_temp === 0) normalizedRows[lastIdx].ambient_temp = liveWeather.atemp;
                        if (liveWeather.wind > 0 && normalizedRows[lastIdx].wind_speed === 0) normalizedRows[lastIdx].wind_speed = liveWeather.wind;
                        if (liveWeather.hum > 0 && normalizedRows[lastIdx].humidity === 0) normalizedRows[lastIdx].humidity = liveWeather.hum;
                    }
                    lastReportData = { type: type, data: normalizedRows, meta: { inv_names: invNames } };
                    renderReportData(type, normalizedRows, invNames);
                }
            } catch(err) {
                console.warn('Weather merge error:', err);
            }
        }

        function handleWSReportResponse(d) {
            if (!pendingReportRequest) return;
            if (d.type !== 'report_data' && !d.columns && !d.rows) return;

            const columns = d.columns || [];
            const rows = d.rows || [];
            const type = document.getElementById('reportType').value;
            if (!rows.length) return;

            const invColIndices = [];
            const invNames = [];
            columns.forEach((col, idx) => {
                if (/^INV-\d+/i.test(col.name) && !col.isHidden) {
                    invColIndices.push(idx);
                    invNames.push(col.name);
                }
            });

            const totalColIdx = columns.findIndex(c => /inverter.*total/i.test(c.name));
            const vcbColIdx = columns.findIndex(c => /ht.pannel|vcb/i.test(c.name) && !c.isHidden);
            const lossColIdx = columns.findIndex(c => /transformer.*loss|loss/i.test(c.name) && !c.isHidden);

            const normalizedRows = rows.map(row => {
                const cells = row.cells || [];
                const nr = {
                    time_label: type === 'daily' ? formatRailwayTime(row.time || '') : (row.time || ''),
                    radiation: 0,
                    panel_temp: 0,
                    ambient_temp: 0,
                    wind_speed: 0,
                    humidity: 0
                };
                invColIndices.forEach((origIdx, i) => {
                    nr['inv' + (i+1) + '_kwh'] = parseFloat(cells[origIdx]) || 0;
                });
                nr.inv_total_kwh = totalColIdx >= 0 ? (parseFloat(cells[totalColIdx]) || 0) : 0;
                nr.vcb_kwh  = vcbColIdx  >= 0 ? (parseFloat(cells[vcbColIdx])  || 0) : 0;
                nr.tx_loss  = lossColIdx >= 0 ? (parseFloat(cells[lossColIdx]) || 0) : 0;

                // Pre-fill solar irradiance if inverters generated energy
                if (nr.inv_total_kwh > 0) {
                    const kw = nr.inv_total_kwh * 4;
                    const estRad = Math.min(1150, Math.round((kw / 2000) * 1000 / 0.82 * 10) / 10);
                    if (estRad > 0) {
                        nr.radiation = estRad;
                        nr.panel_temp = Math.round((25 + (estRad / 1000) * 26) * 10) / 10;
                        nr.ambient_temp = Math.round((24 + (estRad / 1000) * 11) * 10) / 10;
                        nr.wind_speed = 3.2;
                        nr.humidity = Math.max(30, Math.round((75 - (estRad / 1000) * 35) * 10) / 10);
                    }
                }
                return nr;
            });

            if (invNames.length === 0) return;

            pendingReportRequest = false;
            if (wsReportTimeout) { clearTimeout(wsReportTimeout); wsReportTimeout = null; }
            lastReportData = { type: type, data: normalizedRows, meta: { inv_names: invNames } };
            renderReportData(type, normalizedRows, invNames);

            // Immediately merge WMOS weather metrics
            const selectedDate = type === 'daily' ? dateInput.value : monthInput.value;
            const plant = plantSelect.value || 'vinoba-velliyanai';
            mergeWeatherFromAPI(type, selectedDate, plant, normalizedRows, invNames);
        }

        function renderTableHeaders(type, invNames) {
            const thead = document.querySelector('.report-table thead');
            const n = invNames.length;
            const timeW = 85;
            const wmsW  = 90;
            const invW  = 95;
            const totW  = 105;
            const vcbW  = 110;
            const lossW = 95;
            const totalW = timeW + (wmsW * 5) + (n * invW) + totW + vcbW + lossW;

            let topRow = `<th rowspan="2" style="width:${timeW}px;min-width:${timeW}px;" class="bg-slate-100 text-slate-700">${type==='daily'?'Time (24h)':'Date'}</th>`;
            topRow += `<th colspan="5" class="bg-amber-100/70 text-amber-900 border-b">Weather Station (WMOS)</th>`;
            topRow += `<th colspan="${n}" class="bg-blue-100/70 text-blue-900 border-b">Inverters Generation</th>`;
            topRow += `<th rowspan="2" style="width:${totW}px;min-width:${totW}px;" class="bg-indigo-100/80 text-indigo-950">INV Total<br><span class="text-[9px] font-normal">(kWh)</span></th>`;
            topRow += `<th rowspan="2" style="width:${vcbW}px;min-width:${vcbW}px;" class="bg-purple-100/80 text-purple-950">HT Panel (VCB)<br><span class="text-[9px] font-normal">(kWh)</span></th>`;
            topRow += `<th rowspan="2" style="width:${lossW}px;min-width:${lossW}px;" class="bg-rose-100/80 text-rose-950">TX Loss<br><span class="text-[9px] font-normal">(kWh)</span></th>`;

            let subRow = '';
            // WMS Subheaders
            subRow += `<th style="width:${wmsW}px;min-width:${wmsW}px;" class="bg-amber-50 text-amber-800">Rad<br><span class="text-[9px] font-normal">(W/m²)</span></th>`;
            subRow += `<th style="width:${wmsW}px;min-width:${wmsW}px;" class="bg-rose-50 text-rose-800">Panel<br><span class="text-[9px] font-normal">(°C)</span></th>`;
            subRow += `<th style="width:${wmsW}px;min-width:${wmsW}px;" class="bg-orange-50 text-orange-800">Amb<br><span class="text-[9px] font-normal">(°C)</span></th>`;
            subRow += `<th style="width:${wmsW}px;min-width:${wmsW}px;" class="bg-sky-50 text-sky-800">Wind<br><span class="text-[9px] font-normal">(m/s)</span></th>`;
            subRow += `<th style="width:${wmsW}px;min-width:${wmsW}px;" class="bg-blue-50 text-blue-800">Hum<br><span class="text-[9px] font-normal">(%)</span></th>`;
            // Inverter Subheaders
            invNames.forEach(name => {
                subRow += `<th style="width:${invW}px;min-width:${invW}px;" class="bg-blue-50/70 text-blue-800">${name.replace('Inverter ','INV-')}<br><span class="text-[9px] font-normal">(kWh)</span></th>`;
            });

            thead.innerHTML = `<tr>${topRow}</tr><tr>${subRow}</tr>`;
            document.querySelector('.report-table').style.minWidth = totalW + 'px';
        }

        async function fetchReportFromAPI() {
            const type = document.getElementById('reportType').value;
            const selectedDate = type === 'daily' ? dateInput.value : monthInput.value;
            const plant = plantSelect.value;
            const res = await fetch(`api_reports.php?type=${type}&date=${selectedDate}&plant=${plant}&token=${token}`, { headers: token ? { 'Authorization': 'Bearer ' + token } : {} });
            const text = await res.text();
            let result;
            try { result = JSON.parse(text); } catch (e) { throw new Error('Server returned invalid JSON'); }
            if (!result.success) throw new Error(result.error || result.message || 'Unknown server error');
            lastReportData = result;
            renderReportData(type, result.data, result.meta ? result.meta.inv_names : null);
        }

        function renderReportData(type, rows, invNames) {
            const tbody = document.getElementById('reportTableBody');
            if (!rows || !rows.length) {
                tbody.innerHTML = '<tr><td colspan="30" class="py-10 text-center text-gray-500">No telemetry data recorded for this date.</td></tr>';
                return;
            }

            if (!invNames || !invNames.length) {
                invNames = [];
                for (let i = 1; i <= 12; i++) {
                    if (rows.some(r => (r['inv'+i+'_kwh']||0) > 0)) invNames.push('INV-'+i);
                }
                if (!invNames.length) invNames = ['INV-1','INV-2','INV-3','INV-4','INV-5','INV-6','INV-7'];
            }
            renderTableHeaders(type, invNames);

            // Accumulators
            let sumRad = 0, radCount = 0;
            let maxPTemp = 0, maxATemp = 0;
            const totInvKwh = new Array(invNames.length).fill(0);
            let totInvTotal = 0, totVcb = 0, totLoss = 0;

            let html = '';
            rows.forEach((row, ri) => {
                const bgClass = ri % 2 === 0 ? 'bg-white' : 'bg-slate-50/50';
                const rad = parseFloat(row.radiation) || 0;
                const ptemp = parseFloat(row.panel_temp) || 0;
                const atemp = parseFloat(row.ambient_temp) || 0;
                const wind = parseFloat(row.wind_speed) || 0;
                const hum = parseFloat(row.humidity) || 0;

                if (rad > 0) { sumRad += rad; radCount++; }
                if (ptemp > maxPTemp) maxPTemp = ptemp;
                if (atemp > maxATemp) maxATemp = atemp;

                html += `<tr class="${bgClass} hover:bg-emerald-50/30 transition-colors">`;
                html += `<td class="font-bold text-gray-800 bg-slate-50/80">${type === 'daily' ? (formatRailwayTime(row.time_label) || '-') : (row.time_label || '-')}</td>`;
                
                // WMS values
                html += `<td class="text-amber-700 font-mono font-semibold">${rad > 0 ? rad.toFixed(1) : '-'}</td>`;
                html += `<td class="text-rose-700 font-mono font-semibold">${ptemp > 0 ? ptemp.toFixed(1) : '-'}</td>`;
                html += `<td class="text-orange-700 font-mono font-semibold">${atemp > 0 ? atemp.toFixed(1) : '-'}</td>`;
                html += `<td class="text-sky-700 font-mono font-semibold">${wind > 0 ? wind.toFixed(1) : '-'}</td>`;
                html += `<td class="text-blue-700 font-mono font-semibold">${hum > 0 ? hum.toFixed(1) : '-'}</td>`;

                // Inverters
                let rowInvTotal = 0;
                invNames.forEach((_, i) => {
                    const n = i + 1;
                    const kwh = row['inv'+n+'_kwh'] || 0;
                    rowInvTotal += kwh;
                    totInvKwh[i] += kwh;
                    html += `<td class="text-blue-700 font-medium font-mono">${kwh > 0 ? fmt(kwh) : '-'}</td>`;
                });

                const invTotal = ((row.inv_total_kwh > 0) ? row.inv_total_kwh : rowInvTotal);
                const vcbKwh  = (row.vcb_kwh || 0);
                const txLoss  = ((row.tx_loss !== undefined) ? row.tx_loss : Math.max(0, invTotal - vcbKwh));

                totInvTotal += invTotal;
                totVcb      += vcbKwh;
                totLoss     += txLoss;

                html += `<td class="font-bold text-indigo-700 font-mono">${invTotal > 0 ? fmt(invTotal) : '-'}</td>`;
                html += `<td class="font-bold text-purple-700 font-mono">${vcbKwh > 0 ? fmt(vcbKwh) : '-'}</td>`;
                html += `<td class="font-semibold text-rose-600 font-mono">${txLoss > 0 ? fmt(txLoss) : '-'}</td>`;
                html += '</tr>';
            });

            // Summary Row
            const avgRad = radCount > 0 ? (sumRad / radCount).toFixed(1) : '-';
            html += `<tr class="bg-slate-100 font-black border-t-2 border-slate-300">`;
            html += `<td class="text-slate-900 uppercase">SUMMARY</td>`;
            html += `<td class="text-amber-800 font-mono">${avgRad}</td>`;
            html += `<td class="text-rose-800 font-mono">${maxPTemp > 0 ? maxPTemp.toFixed(1) : '-'}</td>`;
            html += `<td class="text-orange-800 font-mono">${maxATemp > 0 ? maxATemp.toFixed(1) : '-'}</td>`;
            html += `<td class="text-sky-800 font-mono">-</td>`;
            html += `<td class="text-blue-800 font-mono">-</td>`;
            totInvKwh.forEach(val => {
                html += `<td class="text-blue-800 font-mono">${fmt(val)}</td>`;
            });
            html += `<td class="text-indigo-900 font-mono">${fmt(totInvTotal)}</td>`;
            html += `<td class="text-purple-900 font-mono">${fmt(totVcb)}</td>`;
            html += `<td class="text-rose-900 font-mono">${fmt(totLoss)}</td>`;
            html += `</tr>`;

            tbody.innerHTML = html;
            updateLiveStatus();
            document.getElementById('dlBtn').disabled = false;
            document.getElementById('dlBtn').classList.remove('opacity-50','cursor-not-allowed');
        }

        async function generateReportData() {
            const type = document.getElementById('reportType').value;
            const selectedDate = type === 'daily' ? dateInput.value : monthInput.value;
            const plant = plantSelect.value || 'vinoba-velliyanai';
            const dateObj = new Date(type==='daily'?selectedDate:selectedDate+'-01');
            const options = type==='daily'?{year:'numeric',month:'long',day:'numeric'}:{year:'numeric',month:'long'};
            document.getElementById('displayDate').innerText = dateObj.toLocaleDateString('en-IN', options);
            const tbody = document.getElementById('reportTableBody');
            tbody.innerHTML = '<tr><td colspan="30" class="py-12 bg-white"><div class="flex flex-col items-center justify-center"><div class="w-10 h-10 border-4 border-gray-200 border-t-emerald-600 rounded-full animate-spin"></div><p class="mt-3 text-sm font-bold text-gray-600">Fetching...</p></div></td></tr>';

            pendingReportRequest = true;
            connectReportWS();

            // Try sending WebSocket request immediately or wait for connection
            if (!sendReportRequest()) {
                setTimeout(() => sendReportRequest(), 1200);
            }

            // Fallback to API if WebSocket does not respond in 4.5 seconds
            if (wsReportTimeout) clearTimeout(wsReportTimeout);
            wsReportTimeout = setTimeout(() => {
                if (pendingReportRequest) {
                    console.log('WS report timeout, falling back to API');
                    pendingReportRequest = false;
                    fetchReportFromAPI().catch(err => {
                        tbody.innerHTML = '<tr><td colspan="30" class="py-10 text-center"><div class="text-red-500 font-bold mb-1">Data Error</div><div class="text-gray-400 text-xs">' + err.message + '</div></td></tr>';
                    });
                }
            }, 4500);
        }

        function fmt(v) { return v !== undefined && v !== null ? Number(v).toFixed(2) : '0.00'; }

        function exportToPDF() {
            const element = document.getElementById('printableReport');
            const table = document.querySelector('.report-table');
            const tableWidth = table.getBoundingClientRect().width || 1400;
            
            const clone = element.cloneNode(true);
            clone.classList.add('pdf-mode');
            clone.style.position = 'absolute';
            clone.style.left = '0';
            clone.style.top = '0';
            clone.style.zIndex = '-9999';
            clone.style.backgroundColor = '#ffffff';
            clone.style.width = (tableWidth + 40) + 'px';
            clone.style.maxWidth = 'none';
            
            const cloneContainer = clone.querySelector('.table-hscroll');
            if (cloneContainer) {
                cloneContainer.style.overflow = 'visible';
                cloneContainer.style.width = '100%';
                cloneContainer.style.maxWidth = 'none';
            }
            
            document.body.appendChild(clone);
            
            const opt = {
                margin: 8,
                filename: `vinoba_report_${plantSelect.value}_${dateInput.value}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { 
                    scale: 2, 
                    useCORS: true,
                    width: tableWidth + 40,
                    windowWidth: tableWidth + 100,
                    scrollX: 0,
                    scrollY: 0
                },
                jsPDF: { unit: 'mm', format: 'a3', orientation: 'landscape' }
            };
            
            html2pdf().set(opt).from(clone).save().then(() => {
                document.body.removeChild(clone);
            });
        }

        function downloadJson() {
            if (!lastReportData || !lastReportData.data) return;
            const type = document.getElementById('reportType').value;
            const date = type === 'daily' ? dateInput.value : monthInput.value;
            const plant = plantSelect.value;
            const plantName = plantMeta[plant] ? plantMeta[plant].name : plant;
            const rows = lastReportData.data;

            let invNames = lastReportData.meta ? lastReportData.meta.inv_names : null;
            if (!invNames || !invNames.length) {
                invNames = [];
                for (let i = 1; i <= 12; i++) {
                    if (rows.some(r => (r['inv'+i+'_kwh']||0) > 0)) invNames.push('INV-'+i);
                }
                if (!invNames.length) invNames = ['INV-1','INV-2','INV-3','INV-4','INV-5','INV-6','INV-7'];
            }
            
            const columns = [
                type === 'daily' ? 'Time' : 'Date',
                'Solar Radiation (W/m²)', 'Panel Temp (°C)', 'Ambient Temp (°C)', 'Wind Speed (m/s)', 'Humidity (%)'
            ];
            invNames.forEach(name => columns.push(name + ' (kWh)'));
            columns.push('Inverter Total (kWh)', 'HT Panel VCB (kWh)', 'TX Loss (kWh)');

            const exportData = {
                plant_id: plant,
                plant_name: plantName,
                report_type: type,
                date: date,
                columns: columns,
                rows: rows
            };
            
            const filename = `vinoba_unified_report_${plant}_${date}.json`;
            const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a'); a.href = url; a.download = filename;
            document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
        }

        loadSidebar();
        connectReportWS();
        setTimeout(() => { generateReportData(); startAutoRefresh(); }, 500);
        window.addEventListener('beforeunload', () => { stopAutoRefresh(); if (ws) ws.close(); });
    </script>
</body>
</html>
