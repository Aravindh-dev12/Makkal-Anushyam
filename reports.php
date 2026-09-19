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
        function localDateKey(d = new Date()) {
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        }
        dateInput.value = localDateKey();
        monthInput.value = localDateKey().slice(0, 7);

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

        function getReportSection() {
            return document.getElementById('reportSection')?.value || 'inverter';
        }

        function toggleReportSection() {
            currentReportSection = getReportSection();
            const isWmas = currentReportSection === 'wmas';
            document.getElementById('reportMainTitle').innerText = isWmas
                ? 'WMAS Weather Report (Live Telemetry)'
                : 'Inverter / Electrical Report';
            document.getElementById('reportMainTitle').className = isWmas
                ? 'text-base font-bold text-amber-700 mt-0.5'
                : 'text-base font-bold text-emerald-700 mt-0.5';
            lastReportData = null;
            weatherBuckets = {};
            liveWeather = { rad: null, ptemp: null, atemp: null, wind: null, hum: null, lastAt: 0 };
            generateReportData();
            startAutoRefresh();
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
        let liveWmasUnitId = '';
        let liveWeather = { rad: null, ptemp: null, atemp: null, wind: null, hum: null, lastAt: 0, lastSampleAt: 0 };
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

        function normalizeWeatherValue(value, depth = 0) {
            if (value === null || value === undefined || value === '' || depth > 5) return null;
            if (typeof value === 'object') {
                for (const key of ['value','val','reading','data','result','current','last']) {
                    if (Object.prototype.hasOwnProperty.call(value, key)) {
                        const n = normalizeWeatherValue(value[key], depth + 1);
                        if (n !== null) return n;
                    }
                }
                return null;
            }
            const n = parseFloat(String(value).replace(/,/g, ''));
            return Number.isFinite(n) ? n : null;
        }

        function captureLiveWeatherValues(values, device, task, sourceTime, unitId = '') {
            if (!values || typeof values !== 'object' || Array.isArray(values)) return false;
            const dev = String(device || '').toLowerCase().trim();
            const taskStr = String(task || '').toLowerCase().trim();
            const normalizeKey = k => String(k).toLowerCase().replace(/[._-]+/g, ' ').replace(/\s+/g, ' ').trim();
            if (unitId && String(unitId) !== String(plantSelect.value || 'vinoba-velliyanai')) return false;
            if (!/^(wmos|wmas|weather)$/.test(taskStr)) return false;

            let metric = '';
            let raw = null;
            if (/pyranometer|pyrimeter/.test(dev)) {
                metric = 'rad';
                const key = Object.keys(values).find(k => normalizeKey(k) === 'raw data');
                raw = key ? values[key] : null;
            } else if (/pannel.*temp|panel.*temp|module.*temp/.test(dev)) {
                metric = 'ptemp';
                const key = Object.keys(values).find(k => ['pannel temperature','panel temperature','module temperature'].includes(normalizeKey(k)));
                raw = key ? values[key] : null;
            } else if (/^ambient(?: temperature)?$|ambient.*temp/.test(dev)) {
                metric = 'atemp';
                const key = Object.keys(values).find(k => normalizeKey(k) === 'ambient temperature');
                raw = key ? values[key] : null;
            } else if (/^wind$|wind.*speed|anemometer/.test(dev)) {
                metric = 'wind';
                const key = Object.keys(values).find(k => ['windspeed','wind speed'].includes(normalizeKey(k)));
                raw = key ? values[key] : null;
            } else if (/^humidity$|humid/.test(dev)) {
                metric = 'hum';
                const key = Object.keys(values).find(k => ['humidity','relative humidity'].includes(normalizeKey(k)));
                raw = key ? values[key] : null;
            } else {
                return false;
            }

            const numeric = raw === null || raw === undefined ? null : normalizeWeatherValue(raw);
            if (metric === 'ptemp' && numeric === null) return false;
            if (numeric === null) return false;
            if (metric === 'rad') liveWeather.rad = numeric;
            if (metric === 'ptemp') liveWeather.ptemp = numeric;
            if (metric === 'atemp') liveWeather.atemp = numeric;
            if (metric === 'wind') liveWeather.wind = numeric;
            if (metric === 'hum') liveWeather.hum = numeric;
            liveWeather.lastAt = Date.now();
            liveWeather.lastSampleAt = sourceTime ? new Date(sourceTime).getTime() || Date.now() : Date.now();
            if (unitId) liveWmasUnitId = unitId;
            if (currentReportSection === 'wmas' && document.getElementById('reportType').value === 'daily' && dateInput.value === localDateKey()) {
                renderWmasLiveRow();
            }
            return true;
        }
        function handleWSDailyWeather(rows, fallbackDevice = '', fallbackTask = '') {
            if (!Array.isArray(rows) || !rows.length) return;
            rows.forEach(r => {
                const s = slot15Min(r.time || r.timestamp);
                if (!s) return;
                if (!weatherBuckets[s]) weatherBuckets[s] = { rad: null, ptemp: null, atemp: null, wind: null, hum: null };
                const dev = String(r.device || r.deviceName || fallbackDevice || '').toLowerCase().trim();
                const task = String(r.task || fallbackTask || '').toLowerCase().trim();
                const v = r.values && typeof r.values === 'object' ? r.values : {};
                if (!/^(wmos|wmas|weather)$/.test(task)) return;

                const normalizeKey = k => String(k).toLowerCase().replace(/[._-]+/g, ' ').replace(/\s+/g, ' ').trim();
                const readExact = keys => {
                    const wanted = new Set(keys.map(normalizeKey));
                    const key = Object.keys(v).find(k => wanted.has(normalizeKey(k)));
                    return key ? normalizeWeatherValue(v[key]) : null;
                };

                if (/pyranometer|pyrimeter/.test(dev)) {
                    const value = readExact(['raw data']);
                    if (value !== null) weatherBuckets[s].rad = value;
                } else if (/pannel.*temp|panel.*temp|module.*temp/.test(dev)) {
                    const value = readExact(['pannel temperature','panel temperature','module temperature']);
                    if (value !== null) weatherBuckets[s].ptemp = value;
                } else if (/^ambient(?: temperature)?$|ambient.*temp/.test(dev)) {
                    const value = readExact(['ambient temperature']);
                    if (value !== null) weatherBuckets[s].atemp = value;
                } else if (/^wind$|wind.*speed|anemometer/.test(dev)) {
                    const value = readExact(['windspeed','wind speed']);
                    if (value !== null) weatherBuckets[s].wind = value;
                } else if (/^humidity$|humid/.test(dev)) {
                    const value = readExact(['humidity','relative humidity']);
                    if (value !== null) weatherBuckets[s].hum = value;
                }
            });
            if (currentReportSection === 'wmas') renderWmasReportFromBuckets();
        }

        function renderWmasLiveRow() {
            const time = new Date().toTimeString().slice(0,5);
            weatherBuckets[slot15Min(time)] = {
                rad: liveWeather.rad,
                ptemp: liveWeather.ptemp,
                atemp: liveWeather.atemp,
                wind: liveWeather.wind,
                hum: liveWeather.hum
            };
            renderWmasReportFromBuckets();
        }

        function renderWmasReportFromBuckets() {
            const rows = Object.keys(weatherBuckets).sort().map(time => ({
                time_label: time,
                radiation: weatherBuckets[time].rad,
                panel_temp: weatherBuckets[time].ptemp,
                ambient_temp: weatherBuckets[time].atemp,
                wind_speed: weatherBuckets[time].wind,
                humidity: weatherBuckets[time].hum
            }));
            lastReportData = {
                type: 'daily',
                data: rows,
                meta: { report_section: 'wmas', source: 'Real WebSocket WMAS/WMOS telemetry' }
            };
            renderWmasReportData('daily', rows);
        }


        function connectReportWS() {
            if (ws && (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING)) return;
            try {
                ws = new WebSocket(wsUrl);
                ws.onopen = () => {
                    wsConnected = true;
                    const plant = plantSelect.value || 'vinoba-velliyanai';
                    ws.send(JSON.stringify({ type: 'subscribe', unit_id: plant }));
                    ws.send(JSON.stringify({ type: 'get_devices', unit_id: plant }));
                    if (currentReportSection === 'inverter') {
                        if (pendingReportRequest) sendReportRequest();
                    } else if (currentReportSection === 'wmas' && document.getElementById('reportType').value === 'daily') {
                        requestWmasDailyHistory();
                    }
                };
                ws.onmessage = (e) => {
                    try {
                        const d = JSON.parse(e.data);
                        const messageUnit = d.unit_id || d.request?.unit_id || d.unitId || d.request?.unitId || '';
                        const task = d.task || d.pageName || d.type || '';
                        const device = d.device || d.deviceName || d.sensor || '';

                        const consumeWeather = (values, dev, tsk, time, unit) => {
                            captureLiveWeatherValues(values, dev, tsk, time, unit);
                        };
                        consumeWeather(d.values, device, task, d.time || d.timestamp || d.ts || '', messageUnit);

                        const data = d.data;
                        const rows = Array.isArray(data) ? data : (data && typeof data === 'object' ? [data] : []);
                        rows.forEach(row => {
                            if (!row || typeof row !== 'object') return;
                            const rowUnit = row.unit_id || row.unitId || messageUnit || '';
                            const rowDevice = row.device || row.deviceName || row.sensor || row.name || device;
                            const rowTask = row.task || row.pageName || row.type || task;
                            const rowTime = row.time || row.timestamp || row.ts || row.recorded_at || d.time || d.timestamp || '';
                            const rowValues = row.values && typeof row.values === 'object' && !Array.isArray(row.values)
                                ? row.values
                                : row.data && typeof row.data === 'object' && !Array.isArray(row.data)
                                    ? row.data
                                    : row;
                            consumeWeather(rowValues, rowDevice, rowTask, rowTime, rowUnit);
                        });

                        for (const containerKey of ['payload','result']) {
                            const container = d[containerKey];
                            if (!container || typeof container !== 'object' || Array.isArray(container)) continue;
                            consumeWeather(container.values, container.device || container.deviceName || device, container.task || container.pageName || task, container.time || container.timestamp || d.time || '', container.unit_id || container.unitId || messageUnit || '');
                        }

                        if (d.type === 'daily_data_result') {
                            handleWSDailyWeather(Array.isArray(d.data) ? d.data : [], d.device || d.deviceName || device, d.task || d.pageName || task);
                            return;
                        }

                        if (currentReportSection === 'inverter') {
                            const reportTypes = ['report_data','generate_report','generate_report_result','report','report_result','report_generated'];
                            if ((reportTypes.includes(d.type) || d.columns || d.rows) && (!messageUnit || messageUnit === (plantSelect.value || 'vinoba-velliyanai'))) handleWSReportResponse(d);
                        } else if (currentReportSection === 'wmas' && /wmos|wmas|weather|pyran|pyrimeter|panel|pannel|ambient|wind|humid|radiat|irradiance/i.test(String(task) + ' ' + String(device))) {
                            renderWmasLiveRow();
                        }
                    } catch(err) {
                        console.error('Reports WS parse error', err);
                    }
                };
                ws.onclose = () => { wsConnected = false; setTimeout(connectReportWS, 3000); };
                ws.onerror = () => { wsConnected = false; };
            } catch(err) { console.error('WS connect failed', err); }
        }

        function requestWmasDailyHistory() {
            if (!ws || ws.readyState !== WebSocket.OPEN) return false;
            const selectedDate = dateInput.value;
            const plant = plantSelect.value || 'vinoba-velliyanai';
            const units = [liveWmasUnitId || plant];
            if (!units.includes(plant)) units.push(plant);
            const weatherDevs = ['Pyranometer', 'pannel temperature', 'Ambient Temperature', 'Wind', 'Humidity'];
            units.forEach(unit => weatherDevs.forEach(dev => {
                ws.send(JSON.stringify({ type: 'get_daily_data', unit_id: unit, device: dev, date: selectedDate }));
            }));
            return true;
        }

        function sendReportRequest() {
            if (!ws || ws.readyState !== WebSocket.OPEN) return false;
            const plant = plantSelect.value || 'vinoba-velliyanai';
            const type = document.getElementById('reportType').value;
            const selectedDate = type === 'daily' ? dateInput.value : monthInput.value;

            if (currentReportSection === 'wmas') {
                if (type === 'daily') {
                    ws.send(JSON.stringify({ type: 'subscribe', unit_id: plant }));
                    return requestWmasDailyHistory();
                }
                return false;
            }

            const pageName = type === 'daily' ? 'inverter&vcb-daily' : 'inverter&vcb-monthly';
            ws.send(JSON.stringify({ type: 'subscribe', unit_id: plant }));
            ws.send(JSON.stringify({ type: 'generate_report', unit_id: plant, pageName, date: selectedDate }));
            return true;
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
                    time_label: type === 'daily' ? formatRailwayTime(row.time || '') : (row.time || '')
                };
                invColIndices.forEach((origIdx, i) => {
                    nr['inv' + (i+1) + '_kwh'] = parseFloat(cells[origIdx]) || 0;
                });
                nr.inv_total_kwh = totalColIdx >= 0 ? (parseFloat(cells[totalColIdx]) || 0) : 0;
                nr.vcb_kwh  = vcbColIdx  >= 0 ? (parseFloat(cells[vcbColIdx])  || 0) : 0;
                nr.tx_loss  = lossColIdx >= 0 ? (parseFloat(cells[lossColIdx]) || 0) : 0;

                return nr;
            });

            if (invNames.length === 0) return;

            pendingReportRequest = false;
            if (wsReportTimeout) { clearTimeout(wsReportTimeout); wsReportTimeout = null; }
            lastReportData = { type: type, data: normalizedRows, meta: { inv_names: invNames } };
            renderReportData(type, normalizedRows, invNames);


        }

        function renderTableHeaders(type, invNames) {
            const table = document.querySelector('.report-table');
            const thead = table.querySelector('thead');
            const names = Array.isArray(invNames) && invNames.length ? invNames : ['INV-1'];
            const n = names.length;
            const timeW = 92, invW = 108, totalW = 112, vcbW = 122, lossW = 108;
            const tableW = timeW + (n * invW) + totalW + vcbW + lossW;

            table.style.tableLayout = 'fixed';
            table.style.width = Math.max(1400, tableW) + 'px';
            table.style.minWidth = Math.max(1400, tableW) + 'px';

            table.querySelector('colgroup')?.remove();
            const colGroup = document.createElement('colgroup');
            [timeW, ...Array(n).fill(invW), totalW, vcbW, lossW].forEach(width => {
                const col = document.createElement('col');
                col.style.width = width + 'px';
                col.style.minWidth = width + 'px';
                colGroup.appendChild(col);
            });
            table.insertBefore(colGroup, thead);

            const label = type === 'daily' ? 'Time (24h)' : 'Date';
            const topRow = `<tr>
                <th rowspan="2" class="bg-slate-100 text-slate-700">${label}</th>
                <th colspan="${n}" class="bg-blue-100/70 text-blue-900 border-b">Inverters Generation</th>
                <th rowspan="2" class="bg-indigo-100/80 text-indigo-950">INV Total<br><span class="text-[9px] font-normal">(kWh)</span></th>
                <th rowspan="2" class="bg-purple-100/80 text-purple-950">HT Panel (VCB)<br><span class="text-[9px] font-normal">(kWh)</span></th>
                <th rowspan="2" class="bg-rose-100/80 text-rose-950">TX Loss<br><span class="text-[9px] font-normal">(kWh)</span></th>
            </tr>`;
            const subRow = `<tr>${names.map(name =>
                `<th class="bg-blue-50/70 text-blue-800">${String(name).replace('Inverter ','INV-')}<br><span class="text-[9px] font-normal">(kWh)</span></th>`
            ).join('')}</tr>`;
            thead.innerHTML = topRow + subRow;
        }


        function renderWmasTableHeaders(type) {
            const thead = document.querySelector('.report-table thead');
            const label = type === 'daily' ? 'Time (24h)' : 'Date';
            thead.innerHTML = `<tr>
                <th class="bg-slate-100 text-slate-700" style="width:110px;min-width:110px;">${label}</th>
                <th class="bg-amber-50 text-amber-800" style="min-width:130px;">Radiation<br><span class="text-[9px] font-normal">(W/m²)</span></th>
                <th class="bg-rose-50 text-rose-800" style="min-width:130px;">Panel Temp<br><span class="text-[9px] font-normal">(°C)</span></th>
                <th class="bg-orange-50 text-orange-800" style="min-width:130px;">Ambient Temp<br><span class="text-[9px] font-normal">(°C)</span></th>
                <th class="bg-sky-50 text-sky-800" style="min-width:130px;">Wind Speed<br><span class="text-[9px] font-normal">(m/s)</span></th>
                <th class="bg-blue-50 text-blue-800" style="min-width:130px;">Humidity<br><span class="text-[9px] font-normal">(%)</span></th>
            </tr>`;
            document.querySelector('.report-table').style.width = '760px';
            document.querySelector('.report-table').style.minWidth = '760px';
        }

        async function fetchReportFromAPI() {
            const type = document.getElementById('reportType').value;
            const selectedDate = type === 'daily' ? dateInput.value : monthInput.value;
            const plant = plantSelect.value || 'vinoba-velliyanai';
            const res = await fetch(`api_reports.php?type=${type}&date=${selectedDate}&plant=${plant}&token=${token}`, { headers: token ? { 'Authorization': 'Bearer ' + token } : {} });
            const text = await res.text();
            let result;
            try { result = JSON.parse(text); } catch (e) { throw new Error('Server returned invalid JSON'); }
            if (!result.success) throw new Error(result.error || result.message || 'Unknown server error');

            if (currentReportSection === 'wmas') {
                lastReportData = { type, data: result.data || [], meta: { report_section: 'wmas', source: 'Stored real WMAS/WMOS telemetry' } };
                renderWmasReportData(type, result.data || []);
            } else {
                lastReportData = result;
                renderReportData(type, result.data, result.meta ? result.meta.inv_names : null);
            }
        }

        function renderReportData(type, rows, invNames) {
            if (!rows || !rows.length) {
                const tbody = document.getElementById('reportTableBody');
                tbody.innerHTML = '<tr><td colspan="30" class="py-10 text-center text-gray-500">No inverter/electrical telemetry data recorded for this period.</td></tr>';
                return;
            }
            if (!invNames || !invNames.length) {
                invNames = [];
                for (let i = 1; i <= 12; i++) {
                    if (rows.some(r => Number(r['inv'+i+'_kwh']) > 0)) invNames.push('INV-' + i);
                }
                if (!invNames.length) invNames = ['INV-1'];
            }
            renderTableHeaders(type, invNames);
            const tbody = document.getElementById('reportTableBody');

            const totals = new Array(invNames.length).fill(0);
            let totalInv = 0, totalVcb = 0, totalLoss = 0;
            let html = '';
            rows.forEach((row, ri) => {
                const bg = ri % 2 === 0 ? 'bg-white' : 'bg-slate-50/50';
                html += `<tr class="${bg} hover:bg-emerald-50/30 transition-colors">`;
                html += `<td class="font-bold text-gray-800 bg-slate-50/80">${type === 'daily' ? (formatRailwayTime(row.time_label) || '-') : (row.time_label || '-')}</td>`;
                let rowInv = 0;
                invNames.forEach((_, i) => {
                    const kwh = Number(row['inv' + (i+1) + '_kwh']) || 0;
                    rowInv += kwh; totals[i] += kwh;
                    html += `<td class="text-blue-700 font-medium font-mono">${kwh > 0 ? fmt(kwh) : '-'}</td>`;
                });
                const invTotal = Number(row.inv_total_kwh) > 0 ? Number(row.inv_total_kwh) : rowInv;
                const vcb = Number(row.vcb_kwh) || 0;
                const loss = row.tx_loss !== undefined ? (Number(row.tx_loss) || 0) : Math.max(0, invTotal - vcb);
                totalInv += invTotal; totalVcb += vcb; totalLoss += loss;
                html += `<td class="font-bold text-indigo-700 font-mono">${invTotal > 0 ? fmt(invTotal) : '-'}</td>`;
                html += `<td class="font-bold text-purple-700 font-mono">${vcb > 0 ? fmt(vcb) : '-'}</td>`;
                html += `<td class="font-semibold text-rose-600 font-mono">${loss > 0 ? fmt(loss) : '-'}</td></tr>`;
            });
            html += '<tr class="bg-slate-100 font-black border-t-2 border-slate-300"><td class="text-slate-900 uppercase">SUMMARY</td>';
            totals.forEach(v => html += `<td class="text-blue-800 font-mono">${fmt(v)}</td>`);
            html += `<td class="text-indigo-900 font-mono">${fmt(totalInv)}</td><td class="text-purple-900 font-mono">${fmt(totalVcb)}</td><td class="text-rose-900 font-mono">${fmt(totalLoss)}</td></tr>`;
            tbody.innerHTML = html;
            updateLiveStatus();
            document.getElementById('dlBtn').disabled = false;
            document.getElementById('dlBtn').classList.remove('opacity-50','cursor-not-allowed');
        }

        function renderWmasReportData(type, rows) {
            renderWmasTableHeaders(type);
            const tbody = document.getElementById('reportTableBody');
            const validRows = (rows || []).filter(row => row && (row.time_label || row.bTime || row.report_day));
            if (!validRows.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-gray-500">Waiting for real WMAS/WMOS telemetry...</td></tr>';
                return;
            }
            let sumRad=0, radN=0, maxPanel=null, maxAmbient=null;
            let html='';
            validRows.forEach((row,ri)=>{
                const t=row.time_label || row.bTime || row.report_day || '-';
                const num=v=>{const n=parseFloat(v); return Number.isFinite(n)?n:null;};
                const rad=num(row.radiation), panel=num(row.panel_temp), ambient=num(row.ambient_temp), wind=num(row.wind_speed), hum=num(row.humidity);
                if(rad!==null){sumRad+=rad;radN++;}
                if(panel!==null) maxPanel=maxPanel===null?panel:Math.max(maxPanel,panel);
                if(ambient!==null) maxAmbient=maxAmbient===null?ambient:Math.max(maxAmbient,ambient);
                html += `<tr class="${ri%2===0?'bg-white':'bg-slate-50/50'} hover:bg-amber-50/30">`;
                html += `<td class="font-bold text-gray-800 bg-slate-50/80">${type==='daily'?formatRailwayTime(t):t}</td>`;
                html += `<td class="text-amber-700 font-mono font-semibold">${rad!==null?rad.toFixed(1):'-'}</td>`;
                html += `<td class="text-rose-700 font-mono font-semibold">${panel!==null?panel.toFixed(1):'-'}</td>`;
                html += `<td class="text-orange-700 font-mono font-semibold">${ambient!==null?ambient.toFixed(1):'-'}</td>`;
                html += `<td class="text-sky-700 font-mono font-semibold">${wind!==null?wind.toFixed(1):'-'}</td>`;
                html += `<td class="text-blue-700 font-mono font-semibold">${hum!==null?hum.toFixed(1):'-'}</td></tr>`;
            });
            html += '<tr class="bg-slate-100 font-black border-t-2 border-slate-300"><td class="text-slate-900 uppercase">SUMMARY</td>';
            html += `<td class="text-amber-800 font-mono">${radN?(sumRad/radN).toFixed(1):'-'}</td>`;
            html += `<td class="text-rose-800 font-mono">${maxPanel!==null?maxPanel.toFixed(1):'-'}</td>`;
            html += `<td class="text-orange-800 font-mono">${maxAmbient!==null?maxAmbient.toFixed(1):'-'}</td><td class="text-sky-800 font-mono">-</td><td class="text-blue-800 font-mono">-</td></tr>`;
            tbody.innerHTML=html;
            updateLiveStatus();
            document.getElementById('dlBtn').disabled=false;
            document.getElementById('dlBtn').classList.remove('opacity-50','cursor-not-allowed');
        }

        async function generateReportData() {
            currentReportSection = getReportSection();
            const type = document.getElementById('reportType').value;
            const selectedDate = type === 'daily' ? dateInput.value : monthInput.value;
            const plant = plantSelect.value || 'vinoba-velliyanai';
            const dateObj = new Date(type === 'daily' ? selectedDate : selectedDate + '-01');
            const options = type === 'daily' ? {year:'numeric',month:'long',day:'numeric'} : {year:'numeric',month:'long'};
            document.getElementById('displayDate').innerText = dateObj.toLocaleDateString('en-IN', options);
            document.getElementById('reportHeaderPlantName').innerText = (plantMeta[plant]?.name || plant).toUpperCase() + ' SOLAR ENERGY';

            const tbody = document.getElementById('reportTableBody');
            tbody.innerHTML = '<tr><td colspan="30" class="py-12 bg-white"><div class="flex flex-col items-center justify-center"><div class="w-10 h-10 border-4 border-gray-200 border-t-emerald-600 rounded-full animate-spin"></div><p class="mt-3 text-sm font-bold text-gray-600">Connecting to live telemetry...</p></div></td></tr>';

            if (currentReportSection === 'wmas') {
                pendingReportRequest = false;
                if (wsReportTimeout) { clearTimeout(wsReportTimeout); wsReportTimeout = null; }
                lastReportData = null;
                weatherBuckets = {};
                liveWmasUnitId = '';
                if (type === 'daily' && selectedDate === localDateKey()) {
                    // Live-first: connect/render directly from the SCADA stream.
                    connectReportWS();
                    renderWmasLiveRow();
                    // History is supplemental and must never delay live values.
                    setTimeout(() => { requestWmasDailyHistory(); }, 100);
                } else {
                    try {
                        await fetchReportFromAPI();
                    } catch (err) {
                        tbody.innerHTML = '<tr><td colspan="6" class="py-10 text-center"><div class="text-red-500 font-bold mb-1">Data Error</div><div class="text-gray-400 text-xs">' + err.message + '</div></td></tr>';
                    }
                }
                return;
            }

            pendingReportRequest = true;
            connectReportWS();
            if (!sendReportRequest()) setTimeout(() => sendReportRequest(), 800);

            if (wsReportTimeout) clearTimeout(wsReportTimeout);
            wsReportTimeout = setTimeout(() => {
                if (pendingReportRequest) {
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
                filename: `vinoba_${currentReportSection}_report_${plantSelect.value}_${document.getElementById('reportType').value === 'daily' ? dateInput.value : monthInput.value}.pdf`,
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
            if (!lastReportData || !Array.isArray(lastReportData.data)) return;
            const type = document.getElementById('reportType').value;
            const date = type === 'daily' ? dateInput.value : monthInput.value;
            const plant = plantSelect.value || 'vinoba-velliyanai';
            const plantName = plantMeta[plant] ? plantMeta[plant].name : plant;
            const rows = lastReportData.data;

            let columns;
            let filename;
            if (currentReportSection === 'wmas') {
                columns = [
                    type === 'daily' ? 'Time' : 'Date',
                    'Solar Radiation (W/m²)', 'Panel Temp (°C)', 'Ambient Temp (°C)',
                    'Wind Speed (m/s)', 'Humidity (%)'
                ];
                filename = `vinoba_wmas_report_${plant}_${date}.json`;
            } else {
                let invNames = lastReportData.meta ? lastReportData.meta.inv_names : null;
                if (!invNames || !invNames.length) {
                    invNames = [];
                    for (let i = 1; i <= 12; i++) {
                        if (rows.some(r => Number(r['inv'+i+'_kwh']) > 0)) invNames.push('INV-' + i);
                    }
                }
                columns = [type === 'daily' ? 'Time' : 'Date'];
                invNames.forEach(name => columns.push(name + ' (kWh)'));
                columns.push('Inverter Total (kWh)', 'HT Panel VCB (kWh)', 'TX Loss (kWh)');
                filename = `vinoba_inverter_report_${plant}_${date}.json`;
            }

            const exportData = {
                report_section: currentReportSection,
                plant_id: plant,
                plant_name: plantName,
                report_type: type,
                date,
                source: lastReportData.meta?.source || (currentReportSection === 'wmas' ? 'WMAS/WMOS telemetry' : 'Inverter/electrical telemetry'),
                columns,
                rows
            };
            const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a'); a.href = url; a.download = filename;
            document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
        }


        loadSidebar();
        toggleInputs();
        toggleReportSection();

        setInterval(() => {
            if (currentReportSection === 'wmas' && document.getElementById('reportType').value === 'daily' && dateInput.value === localDateKey()) {
                renderWmasLiveRow();
            }
        }, 1000);

        window.addEventListener('beforeunload', () => { stopAutoRefresh(); if (ws) ws.close(); });
    </script>
</body>
</html>
