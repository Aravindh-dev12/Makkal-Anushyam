<?php
require 'check_auth.php';
date_default_timezone_set('Asia/Kolkata');

$analyticsWsUrl = 'wss://vinobasolar.scadahub.in:5001';
$analyticsPlantConfig = [
    'vinoba-velliyanai' => [
        'name' => 'Vinoba Velliyanai',
        'capacity' => 2.0,
        'location' => 'Karur',
        'inverter_count' => 8,
        'ws_unit_id' => 'vinoba-velliyanai',
        'ws_url' => $analyticsWsUrl,
    ],
    'makkalpower' => [
        'name' => 'Makkal Power',
        'capacity' => 2.0,
        'location' => 'Karur',
        'inverter_count' => 8,
        'ws_unit_id' => 'makkalpower',
        'ws_url' => $analyticsWsUrl,
    ],
    'anushyam' => [
        'name' => 'Anushyam Plant',
        'capacity' => 2.0,
        'location' => 'Karur',
        'inverter_count' => 8,
        'ws_unit_id' => 'anushyam',
        'ws_url' => $analyticsWsUrl,
    ],
];

/* Prefer DB plant capacity/counts when the tables are available. */
try {
    if (isset($conn) && $conn instanceof mysqli) {
        $plantRes = @$conn->query("SELECT id, name, capacity, location FROM plants");
        if ($plantRes) {
            while ($row = $plantRes->fetch_assoc()) {
                $id = trim((string)($row['id'] ?? ''));
                if ($id === '' || !isset($analyticsPlantConfig[$id])) continue;
                $name = trim((string)($row['name'] ?? ''));
                $capacity = (float)($row['capacity'] ?? 0);
                if ($name !== '') $analyticsPlantConfig[$id]['name'] = $name;
                if ($capacity > 0) $analyticsPlantConfig[$id]['capacity'] = $capacity;
                if (isset($row['location'])) $analyticsPlantConfig[$id]['location'] = (string)$row['location'];
            }
        }
    }
} catch (Throwable $e) {
    /* Static defaults above remain valid. */
}

if (!isset($analyticsPlantConfig[$currentPlant])) {
    $currentPlant = array_key_first($analyticsPlantConfig);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/app.css?v=20260802-1">
    <title id="pageTitle">Solar Plant - Analytics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f8fafc; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="h-full bg-slate-50 text-slate-800 font-sans">
    <div class="min-h-screen flex relative">
        <div id="overlay" class="fixed inset-0 bg-slate-900 bg-opacity-40 hidden z-30 md:hidden transition-opacity"></div>
        <div id="sidebar-container"></div>
        <main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden">
            <header class="bg-white p-4 sm:px-6 flex justify-between items-center sticky top-0 z-20 border-b border-slate-200 shadow-sm">
                <div class="flex items-center gap-3 min-w-0">
                    <button id="menuBtn" class="md:hidden text-emerald-600 text-2xl focus:outline-none shrink-0">&#9776;</button>
                    <div class="min-w-0">
                        <h2 class="text-xl font-black text-slate-800 tracking-tight truncate">Plant Analytics</h2>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Live inverter &amp; WMAS telemetry</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100 shrink-0">
                    <div id="refreshPulse" class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.6)]"></div>
                    <span class="text-xs font-bold text-slate-600 tracking-widest hidden sm:inline" id="clockDisplay">--:--:--</span>
                </div>
            </header>

            <div class="p-4 sm:p-6 lg:p-8 w-full flex flex-col gap-6 lg:gap-8 max-w-[1920px] mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group hover:shadow-md transition duration-300">
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-blue-50 rounded-full blur-xl -z-10 group-hover:bg-blue-100 transition"></div>
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Performance</h3>
                        <p class="font-black text-slate-800 text-3xl" id="perf_val">-- <span class="text-sm font-bold text-blue-600">%</span></p>
                        <p class="text-xs text-slate-500 font-medium mt-1">Capacity factor</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group hover:shadow-md transition duration-300">
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-purple-50 rounded-full blur-xl -z-10 group-hover:bg-purple-100 transition"></div>
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Yield</h3>
                        <p class="font-black text-slate-800 text-3xl" id="yield_val">-- <span class="text-sm font-bold text-purple-600">kWh</span></p>
                        <p class="text-xs text-slate-500 font-medium mt-1">Daily energy</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group hover:shadow-md transition duration-300">
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-emerald-50 rounded-full blur-xl -z-10 group-hover:bg-emerald-100 transition"></div>
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Availability</h3>
                        <p class="font-black text-slate-800 text-3xl" id="avail_val">-- <span class="text-sm font-bold text-emerald-600">%</span></p>
                        <p class="text-xs text-slate-500 font-medium mt-1">Inverter uptime</p>
                    </div>
                </div>

                <section id="outputTrendSection" class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 sm:p-5">
                    <div class="flex flex-col sm:flex-row sm:items-end gap-4 mb-5">
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Today Data</p>
                            <h2 class="text-xl font-bold text-slate-900">Output Trend</h2>
                            <p class="mt-1 text-xs text-slate-500">Chart keeps the existing live trend · Excel exports the full selected source from first sample to last sample.</p>
                        </div>
                        <div class="ml-auto flex flex-wrap items-end justify-end gap-2 w-full sm:w-auto">
                            <button id="generateAnalyticsExcel" type="button" disabled class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed transition">
                                <i class="fa-solid fa-file-excel"></i>
                                <span>Generate Excel</span>
                            </button>
                            <button id="exportAnalyticsExcel" type="button" disabled class="hidden inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-700 hover:bg-emerald-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
                                <i class="fa-solid fa-file-excel"></i>
                                <span>Export Excel</span>
                            </button>
                            <label class="text-xs font-semibold text-slate-500 min-w-[220px] sm:min-w-[280px]">
                                <span class="block mb-1 text-right">Live Data Source</span>
                                <select id="analyticsSourceSelect" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Inverter / WMAS</option>
                                    <optgroup label="Inverters"></optgroup>
                                    <optgroup label="WMAS / WMOS">
                                        <option value="wmos:pyranometer">Radiation</option>
                                        <option value="wmos:panel">Panel Temperature</option>
                                        <option value="wmos:ambient">Ambient Temperature</option>
                                        <option value="wmos:wind">Wind Speed</option>
                                        <option value="wmos:humidity">Humidity</option>
                                    </optgroup>
                                </select>
                            </label>
                        </div>
                    </div>

                    <div id="analyticsEmptyState" class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500 mb-4">
                        Select an inverter to load today’s output trend.
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50/40 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <div>
                                <h3 class="text-sm font-black text-slate-700" id="outputTrendTitle">Inverter Output</h3>
                                <p class="text-[11px] text-slate-400">Direct AC power, with V × I × PF fallback only when direct power is unavailable.</p>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Latest Output</p>
                                <p class="text-lg font-black text-blue-700"><span id="latestOutputValue">--</span> <span class="text-xs">kW</span></p>
                                <p class="text-[10px] text-slate-400">Live through <span id="analyticsLiveLabel">--</span></p>
                            </div>
                        </div>
                        <div class="h-[340px] sm:h-[400px]"><canvas id="outputTrendChart"></canvas></div>
                    </div>
                </section>

                <section id="wmasTrendSection" class="hidden bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <div class="flex flex-col sm:flex-row sm:items-end gap-4 mb-5">
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Today Data</p>
                            <h2 class="text-xl font-bold text-slate-900">WMAS Live Trend</h2>
                            <p class="mt-1 text-xs text-slate-500">Select a WMAS measurement above. The graph uses only telemetry received from the live plant WebSocket.</p>
                        </div>
                        <div class="ml-auto text-right">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Latest WMAS</p>
                            <p class="text-lg font-black text-emerald-700"><span id="latestWmasValue">--</span> <span class="text-xs" id="latestWmasUnit"></span></p>
                            <p class="text-[10px] text-slate-400">Live through <span id="wmasLiveLabel">--</span></p>
                        </div>
                    </div>
                    <div id="wmasEmptyState" class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500 mb-4">Select a WMAS measurement to load its live trend.</div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/40 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <div>
                                <h3 class="text-sm font-black text-slate-700" id="wmasTrendTitle">WMAS Data</h3>
                                <p class="text-[11px] text-slate-400">Actual WebSocket samples only. No hardcoded or estimated sensor values.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span id="wmasGraphLiveDot" class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
                                <span class="text-[10px] font-black text-slate-500 uppercase tracking-wider" id="wmasGraphStatus">Waiting for live telemetry</span>
                            </div>
                        </div>
                        <div class="h-[340px] sm:h-[400px]"><canvas id="wmasTrendChart"></canvas></div>
                    </div>
                </section>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                        <h3 class="text-sm font-black text-slate-600 uppercase tracking-widest">Alerts & Recommendations</h3>
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700 shrink-0" id="recBadge">0 items</span>
                    </div>
                    <div id="recContainer" class="space-y-3">
                        <div class="bg-slate-50 rounded-lg p-4 border border-slate-200">
                            <p class="font-black text-slate-800 text-sm">No Alerts</p>
                            <p class="mt-2 text-sm text-slate-600">All systems operating within normal parameters. No action required.</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const currentPlant = '<?php echo addslashes($currentPlant); ?>';
        const wsUnitId = <?php echo json_encode($currentPlant); ?>;
        const plantConfig = <?php echo json_encode($analyticsPlantConfig, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
        const plantNames = Object.fromEntries(Object.entries(plantConfig).map(([id, cfg]) => [id, cfg.name]));
        const cfg = plantConfig[currentPlant] || { capacity: 1.0, inverter_count: 0 };
        // WMOS/WMAS telemetry is broadcast through the same live SCADA stream used by Home.
        const LIVE_WMOS_UNIT_ID = wsUnitId;
        const LIVE_WMOS_WS_URL = cfg.ws_url || '';
        const TREND_START_MINUTE = 6 * 60;
        const TREND_END_MINUTE = 19 * 60;
        const TREND_BUCKET_MINUTES = 5;
        const GENERATION_THRESHOLD_KW = 0.1;

        document.getElementById('pageTitle').textContent = (plantNames[currentPlant] || currentPlant) + ' - Analytics';
        setInterval(() => {
            document.getElementById('clockDisplay').innerText = new Date().toLocaleTimeString('en-IN', { hour12: false });
            updateWmosLiveStatus();
            renderWmasTrend();
        }, 1000);

        fetch('sidebar.html', { cache: 'no-store' }).then(r => r.text()).then(html => {
            document.getElementById('sidebar-container').innerHTML = html;
            document.getElementById('sidebar-container').querySelectorAll('script').forEach(s => {
                const ns = document.createElement('script');
                ns.textContent = s.textContent;
                s.replaceWith(ns);
            });
            const overlay = document.getElementById('overlay');
            const sidebar = document.getElementById('sidebar');
            document.getElementById('menuBtn')?.addEventListener('click', () => {
                sidebar?.classList.remove('-translate-x-full');
                overlay?.classList.remove('hidden');
            });
            document.getElementById('closeSidebarBtn')?.addEventListener('click', () => {
                sidebar?.classList.add('-translate-x-full');
                overlay?.classList.add('hidden');
            });
            overlay?.addEventListener('click', () => {
                sidebar?.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            });
        });

        let selectedInverter = '';
        let selectedWmas = '';
        let selectedSource = '';
        let liveWmasUnitId = '';
        let analyticsSocket = null;
        let analyticsWmosSocket = null;
        let lastInverterOptionsKey = '';
        let outputTrendChart = null;
        let wmasTrendChart = null;
        const sourceSelect = document.getElementById('analyticsSourceSelect');
        const analyticsLiveLabel = document.getElementById('analyticsLiveLabel');
        const exportButton = document.getElementById('exportAnalyticsExcel');
        const generateExcelButton = document.getElementById('generateAnalyticsExcel');
        const emptyState = document.getElementById('analyticsEmptyState');
        const latestOutputValue = document.getElementById('latestOutputValue');
        const outputTrendTitle = document.getElementById('outputTrendTitle');
        const wmasEmptyState = document.getElementById('wmasEmptyState');
        const latestWmasValue = document.getElementById('latestWmasValue');
        const latestWmasUnit = document.getElementById('latestWmasUnit');
        const wmasTrendTitle = document.getElementById('wmasTrendTitle');
        const wmasLiveLabel = document.getElementById('wmasLiveLabel');
        const wmasGraphLiveDot = document.getElementById('wmasGraphLiveDot');
        const wmasGraphStatus = document.getElementById('wmasGraphStatus');

        const aState = {
            inverters: {},
            history: {},
            weather: {
                radiation: null,
                panelTemp: null,
                ambientTemp: null,
                windSpeed: null,
                humidity: null,
                lastReceivedAt: 0,
                lastSampleAt: 0,
                lastDevice: ''
            }
        };
        const analyticsRawInverterHistory = {};

        const WMOS_EXPORT_SOURCES = {
            'wmos:wind': { label: 'Wind Speed', device: 'Wind', unit: 'm/s', decimals: 2, keys: ['windspeed', 'wind speed'] },
            'wmos:humidity': { label: 'Humidity', device: 'Humidity', unit: '%', decimals: 1, keys: ['humidity', 'relative humidity'] },
            'wmos:ambient': { label: 'Ambient Temperature', device: 'Ambient Temperature', unit: '°C', decimals: 1, keys: ['ambient temperature'] },
            'wmos:panel': { label: 'Panel Temperature', device: 'pannel temperature', unit: '°C', decimals: 1, keys: ['pannel temperature', 'panel temperature', 'module temperature'] },
            'wmos:pyranometer': { label: 'Pyranometer', device: 'Pyranometer', unit: '', decimals: 0, keys: ['raw data'] }
        };
        const analyticsWmosHistory = Object.fromEntries(Object.keys(WMOS_EXPORT_SOURCES).map(key => [key, new Map()]));

        function canonicalDeviceName(device) {
            const name = (device || '').toString().trim();
            const match = name.match(/(?:inv(?:erter)?)[-\s_]*(\d+)/i) || name.match(/\b(\d+)\b/);
            if (match) return 'Inverter' + parseInt(match[1], 10);
            return name || 'Inverter';
        }
        function inverterLabel(name) { const match = (name || '').toString().match(/\d+/); return match ? `Inverter ${parseInt(match[0], 10)}` : (name || 'Inverter'); }
        function isInverterDeviceName(name) { const text = (name || '').toString().toLowerCase(); if (!text || /vcb|transformer|trafo|oil|winding/.test(text)) return false; return /(^|[^a-z])inv([^a-z]|$)|inverter/.test(text); }
        function ensureInverter(device) {
            const key = canonicalDeviceName(device);
            if (!aState.inverters[key]) aState.inverters[key] = { wsName: (device || key).toString(), outputKw: 0, outputSource: '', dailyGen: 0, online: false, lastSeen: 0 };
            const rawName = (device || '').toString().trim();
            if (rawName && isInverterDeviceName(rawName)) aState.inverters[key].wsName = rawName;
            if (!aState.history[key]) aState.history[key] = new Map();
            if (!analyticsRawInverterHistory[key]) analyticsRawInverterHistory[key] = new Map();
            return key;
        }
        function seedConfiguredInverters() {
            // No synthetic inverter options. The dropdown is populated only from
            // the live device_list or actual inverter telemetry.
            populateAnalyticsInverterOptions();
        }
        function populateAnalyticsInverterOptions() {
            if (!sourceSelect) return;
            const current = selectedSource || sourceSelect.value || '';
            const names = Object.keys(aState.inverters).sort((a, b) => {
                const numA = parseInt(a.match(/\d+/)?.[0] || '0', 10);
                const numB = parseInt(b.match(/\d+/)?.[0] || '0', 10);
                return numA - numB || a.localeCompare(b);
            });
            const optionsKey = names.join('|');
            if (optionsKey === lastInverterOptionsKey) return;
            lastInverterOptionsKey = optionsKey;
            sourceSelect.innerHTML = [
                '<option value="">Select Inverter / WMAS</option>',
                '<optgroup label="Inverters">',
                ...names.map(name => '<option value="' + name.replace(/"/g, '&quot;') + '">' + inverterLabel(name) + '</option>'),
                '</optgroup>',
                '<optgroup label="WMAS / WMOS">',
                '<option value="wmos:pyranometer">Radiation</option>',
                '<option value="wmos:panel">Panel Temperature</option>',
                '<option value="wmos:ambient">Ambient Temperature</option>',
                '<option value="wmos:wind">Wind Speed</option>',
                '<option value="wmos:humidity">Humidity</option>',
                '</optgroup>'
            ].join('');
            sourceSelect.value = current && (names.includes(current) || WMOS_EXPORT_SOURCES[current]) ? current : '';
            selectedSource = sourceSelect.value || '';
        }

        function readDirectNumber(values, keys) { for (const key of keys) { if (!Object.prototype.hasOwnProperty.call(values || {}, key)) continue; const raw = values[key]; if (raw === null || raw === undefined || raw === '') continue; const n = parseFloat(raw); if (Number.isFinite(n)) return n; } return null; }
        function readRegexNumber(values, accepts, rejects = []) { for (const [key, raw] of Object.entries(values || {})) { const normalized = key.toLowerCase().replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim(); if (rejects.some(rx => rx.test(normalized)) || !accepts.some(rx => rx.test(normalized))) continue; const n = parseFloat(raw); if (Number.isFinite(n)) return n; } return null; }
        function readMetric(values, keys, accepts = [], rejects = []) { const direct = readDirectNumber(values, keys); return direct !== null ? direct : (accepts.length ? readRegexNumber(values, accepts, rejects) : null); }
        function metricCandidates(values, keys, accepts = [], rejects = []) {
            const candidates = [], exactKeys = new Set(keys);
            keys.forEach(key => { if (!Object.prototype.hasOwnProperty.call(values || {}, key)) return; const raw = values[key]; if (raw === null || raw === undefined || raw === '') return; const n = parseFloat(raw); if (Number.isFinite(n)) candidates.push(n); });
            Object.entries(values || {}).forEach(([key, raw]) => { if (exactKeys.has(key) || raw === null || raw === undefined || raw === '') return; const normalized = key.toLowerCase().replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim(); if (rejects.some(rx => rx.test(normalized)) || !accepts.some(rx => rx.test(normalized))) return; const n = parseFloat(raw); if (Number.isFinite(n)) candidates.push(n); });
            return candidates;
        }
        function preferredMetric(values, keys, accepts = [], rejects = []) { const candidates = metricCandidates(values, keys, accepts, rejects); const nonZero = candidates.find(value => Math.abs(value) > 0.000001); return nonZero !== undefined ? nonZero : (candidates.length ? candidates[0] : null); }
        function averageAvailable(values) { const valid = values.filter(value => value !== null && value !== undefined && Number.isFinite(Number(value))); return valid.length ? valid.reduce((sum, value) => sum + Number(value), 0) / valid.length : null; }
        function normalizePowerFactor(value) { if (value === null || value === undefined || !Number.isFinite(Number(value))) return null; let pf = Math.abs(Number(value)); if (pf > 1.2 && pf <= 100) pf /= 100; return Math.min(pf, 1); }

        function extractOutput(values) {
            const directPowerCandidates = metricCandidates(values, ['Total active power', 'a.c. active power', 'AC Power', 'active_power', 'power_kw'], [/active.*power/, /ac.*power/], [/reactive/, /apparent/, /nominal/, /rated/, /dc.*power/, /string/, /mppt/, /energy/, /yield/, /factor/, /phase/]);
            const positiveDirectPower = directPowerCandidates.find(value => Number(value) > GENERATION_THRESHOLD_KW);
            const dailyGen = readMetric(values, ['Daily power yields', 'daily generation', 'daily_generation', 'Day Energy', 'today_energy', 'daily_gen_kwh'], [/daily.*yield/, /daily.*gen/, /today.*energy/, /day.*energy/]);
            const workState = (values?.['Work state'] ?? values?.work_state ?? values?.Status ?? values?.status ?? '').toString();
            const vacAB = preferredMetric(values, ['RYvolatge', 'RY voltage', 'V12 (RY)', 'VAC A', 'Vac A', 'vac_ab'], [/ry.*volt/, /v12/, /voltage.*ab/, /vac.*ab/]);
            const vacBC = preferredMetric(values, ['YB voltage', 'V23 (YB)', 'VAC B', 'Vac B', 'vac_bc'], [/yb.*volt/, /v23/, /voltage.*bc/, /vac.*bc/]);
            const vacCA = preferredMetric(values, ['BR voltage', 'V31 (BR)', 'VAC C', 'Vac C', 'vac_ca'], [/br.*volt/, /v31/, /voltage.*ca/, /vac.*ca/]);
            const currentA = preferredMetric(values, ['RY current', 'I A', 'Current A', 'current_a'], [/ry.*current/, /current.*a/, /a.*phase.*current/, /^i a$/], [/volt/]);
            const currentB = preferredMetric(values, ['YB current', 'I B', 'Current B', 'current_b'], [/yb.*current/, /current.*b/, /b.*phase.*current/, /^i b$/], [/volt/]);
            const currentC = preferredMetric(values, ['BR current', 'I C', 'Current C', 'current_c'], [/br.*current/, /current.*c/, /c.*phase.*current/, /^i c$/], [/volt/]);
            const pf = normalizePowerFactor(preferredMetric(values, ['Power factor', 'power_factor', 'pf'], [/power factor/, /^pf$/]));
            const avgVoltage = averageAvailable([vacAB, vacBC, vacCA]), avgCurrent = averageAvailable([currentA, currentB, currentC]);
            const calculatedKw = avgVoltage !== null && avgVoltage > 0 && avgCurrent !== null && avgCurrent >= 0 && pf !== null ? (Math.sqrt(3) * avgVoltage * avgCurrent * pf) / 1000 : null;
            if (positiveDirectPower !== undefined) return { outputKw: Math.max(0, positiveDirectPower), source: 'Direct AC power', dailyGen, workState };
            if (calculatedKw !== null && calculatedKw > GENERATION_THRESHOLD_KW) return { outputKw: Math.max(0, calculatedKw), source: 'Calculated from V × I × PF', dailyGen, workState };
            if (directPowerCandidates.length) return { outputKw: Math.max(0, ...directPowerCandidates), source: 'Direct AC power', dailyGen, workState };
            if (calculatedKw !== null) return { outputKw: Math.max(0, calculatedKw), source: 'Calculated from V × I × PF', dailyGen, workState };
            return { outputKw: null, source: '', dailyGen, workState };
        }

        function localDateKey(date) { return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`; }
        function todayKey() { return localDateKey(new Date()); }
        function parseTelemetryTime(sourceTime) {
            const raw = (sourceTime || '').toString().trim(); if (!raw) return new Date();
            if (/[zZ]$|[+-]\d{2}:?\d{2}$/.test(raw)) { const zoned = new Date(raw); if (!Number.isNaN(zoned.getTime())) return zoned; }
            const full = raw.match(/(\d{4})[-/](\d{1,2})[-/](\d{1,2})[ T](\d{1,2}):(\d{2})(?::(\d{2}))?/); if (full) return new Date(parseInt(full[1],10),parseInt(full[2],10)-1,parseInt(full[3],10),parseInt(full[4],10),parseInt(full[5],10),parseInt(full[6]||'0',10));
            const timeOnly = raw.match(/\b(\d{1,2}):(\d{2})(?::(\d{2}))?/); if (timeOnly) { const now = new Date(); return new Date(now.getFullYear(),now.getMonth(),now.getDate(),parseInt(timeOnly[1],10),parseInt(timeOnly[2],10),parseInt(timeOnly[3]||'0',10)); }
            const parsed = new Date(raw); return Number.isNaN(parsed.getTime()) ? new Date() : parsed;
        }
        function minuteOfDay(date) { return date.getHours() * 60 + date.getMinutes(); }
        function cloneWsValues(values) { const cloned = {}; Object.entries(values || {}).forEach(([key, value]) => { cloned[key] = value && typeof value === 'object' ? JSON.stringify(value) : value; }); return cloned; }

        function captureRawInverterReading(device, values, sourceTime) {
            if (!values || typeof values !== 'object' || !isInverterDeviceName(device)) return false;
            const key = ensureInverter(device), timestamp = parseTelemetryTime(sourceTime);
            if (localDateKey(timestamp) !== todayKey()) return false;
            const reading = extractOutput(values), timestampKey = timestamp.getTime();
            analyticsRawInverterHistory[key].set(timestampKey, { timestamp: timestampKey, device: aState.inverters[key]?.wsName || device, outputKw: reading.outputKw, outputSource: reading.source, dailyGen: reading.dailyGen, workState: reading.workState, values: cloneWsValues(values) });
            return true;
        }

        function applyInverterReading(device, values, sourceTime) {
            if (!values || typeof values !== 'object') return false;
            const key = ensureInverter(device), reading = extractOutput(values);
            captureRawInverterReading(device, values, sourceTime);
            if (reading.outputKw === null && reading.dailyGen === null) return false;
            const timestamp = parseTelemetryTime(sourceTime), latest = aState.inverters[key];
            if (reading.outputKw !== null) { latest.outputKw = reading.outputKw; latest.outputSource = reading.source; }
            if (reading.dailyGen !== null) latest.dailyGen = reading.dailyGen;
            latest.online = latest.outputKw > GENERATION_THRESHOLD_KW || !/offline|fault|stop|standby|disconnect/i.test(reading.workState || ''); latest.lastSeen = timestamp.getTime();
            if (reading.outputKw !== null && localDateKey(timestamp) === todayKey()) aState.history[key].set(timestamp.getTime(), { timestamp: timestamp.getTime(), date: localDateKey(timestamp), time: timestamp.toLocaleTimeString('en-IN',{hour12:false}), outputKw: reading.outputKw, source: reading.source });
            if (!lastInverterOptionsKey.includes(key)) populateAnalyticsInverterOptions(); return true;
        }

        function normalizeWmosMetricName(value) {
            return String(value || '').toLowerCase().replace(/[._-]+/g, ' ').replace(/\s+/g, ' ').trim();
        }
        function unwrapWmosValue(value, depth = 0) {
            if (value === null || value === undefined || value === '' || depth > 4) return null;
            if (typeof value !== 'object') return value;
            for (const key of ['value', 'val', 'reading', 'data', 'result', 'current', 'last']) {
                if (!Object.prototype.hasOwnProperty.call(value, key)) continue;
                const next = unwrapWmosValue(value[key], depth + 1);
                if (next !== null) return next;
            }
            return null;
        }
        function wmosNumber(value) {
            const raw = unwrapWmosValue(value);
            if (raw === null || raw === undefined || raw === '') return null;
            const number = parseFloat(String(raw).replace(/,/g, ''));
            return Number.isFinite(number) ? number : null;
        }
        function wmosSourceForDevice(device) {
            const name = normalizeWmosMetricName(device);
            if (/pyranometer|pyrimeter/.test(name)) return 'wmos:pyranometer';
            if (/pannel.*temp|panel.*temp|module.*temp/.test(name)) return 'wmos:panel';
            if (/ambient.*temp/.test(name)) return 'wmos:ambient';
            if (/humidity/.test(name)) return 'wmos:humidity';
            if (/^wind$|wind.*speed|wind.*velocity|velocity.*wind|anemometer|^speed$|^velocity$/.test(name)) return 'wmos:wind';
            return '';
        }
        function readWmosMetricValue(values, directKeys, patterns = []) {
            if (!values || typeof values !== 'object') return null;
            const direct = new Set(directKeys.map(normalizeWmosMetricName));
            for (const [key, raw] of Object.entries(values)) {
                const normalized = normalizeWmosMetricName(key);
                if (!direct.has(normalized)) continue;
                const value = wmosNumber(raw);
                if (value !== null) return value;
            }
            for (const [key, raw] of Object.entries(values)) {
                const normalized = normalizeWmosMetricName(key);
                if (!patterns.some(rx => rx.test(normalized))) continue;
                const value = wmosNumber(raw);
                if (value !== null) return value;
            }
            return null;
        }
        function setWmosPanelValue(elementId, value, decimals) {
            const el = document.getElementById(elementId);
            if (!el || value === null || value === undefined || !Number.isFinite(Number(value))) return false;
            el.textContent = Number(value).toFixed(decimals);
            el.dataset.live = 'true';
            return true;
        }
        function recordWmosHistory(sourceKey, value, sourceTime, device) {
            if (!analyticsWmosHistory[sourceKey] || value === null || value === undefined) return;
            const timestamp = parseTelemetryTime(sourceTime);
            if (localDateKey(timestamp) !== todayKey()) return;
            analyticsWmosHistory[sourceKey].set(timestamp.getTime(), {
                timestamp: timestamp.getTime(),
                value: Number(value),
                device: device || WMOS_EXPORT_SOURCES[sourceKey].device
            });
        }
        function captureWmosValues(values, sourceTime, device = '', task = '') {
            if (!values || typeof values !== 'object') return false;
            const context = normalizeWmosMetricName(task + ' ' + device);
            const valueKeys = Object.keys(values).map(normalizeWmosMetricName);
            const weatherSignal =
                /(?:^|\s)(?:wmos|wmas)(?:\s|$)|weather|pyranometer|pyrimeter|panel|pannel|ambient|wind|humidity|radiation|irradiance/.test(context) ||
                valueKeys.some(key => /pyran|radiat|irradiance|pannel.*temp|panel.*temp|module.*temp|ambient.*temp|wind.*speed|humidity/.test(key));
            if (!weatherSignal || (/inverter|^inv\b/.test(context) && !/(?:^|\s)(?:wmos|wmas|weather)(?:\s|$)/.test(context))) return false;

            const rad = readWmosMetricValue(values, ['raw data', 'radiation', 'solar radiation', 'irradiance'], [/^raw data$/, /radiation/, /irradiance/, /pyran/]);
            let panel = readWmosMetricValue(values, ['pannel temperature', 'panel temperature', 'module temperature'], [/pannel.*temp/, /panel.*temp/, /module.*temp/, /^temperature$/, /^temp$/, /^temp data$/]);
            let ambient = readWmosMetricValue(values, ['Ambient temperature', 'ambient temperature'], [/ambient.*temp/]);
            // Exact live SCADA Wind frame: device="Wind", values.windspeed.
            let wind = null;
            if (/^wind$|wind speed|anemometer/.test(deviceText)) {
                const directWindKey = Object.keys(values).find(key => normalizeWmosMetricName(key) === 'windspeed' || normalizeWmosMetricName(key) === 'wind speed');
                if (directWindKey) wind = wmosNumber(values[directWindKey]);
            }
            if (wind === null) wind = readWmosMetricValue(values, ['windspeed', 'wind speed', 'wind_speed', 'wind velocity', 'windvelocity', 'wind', 'speed', 'velocity', 'anemometer'], [/wind.*speed/, /windspeed/, /wind.*velocity/, /velocity.*wind/, /^wind$/, /anemometer/, /(^|\\s)speed(?:\\s|$)/, /velocity/]);
            let humidity = readWmosMetricValue(values, ['humidity', 'Humidity', 'relative humidity'], [/humidity/]);

            // Match Makkal Home's device-context fallbacks for generic sensor keys.
            const deviceText = normalizeWmosMetricName(device);
            if (panel === null && /pannel|panel|module/.test(deviceText)) panel = readWmosMetricValue(values, [], [/temp/]);
            if (ambient === null && /ambient/.test(deviceText)) ambient = readWmosMetricValue(values, [], [/temp/]);
            if (wind === null && /wind|anemometer|anem/.test(deviceText)) wind = readWmosMetricValue(values, [], [/wind|speed|velocity|anemometer/]);
            if (humidity === null && /humid/.test(deviceText)) humidity = readWmosMetricValue(values, [], [/hum/]);

            let updated = false;
            if (rad !== null) { setWmosPanelValue('wmos_rad', rad, 0); recordWmosHistory('wmos:pyranometer', rad, sourceTime, device || 'Pyranometer'); updated = true; }
            if (panel !== null) { setWmosPanelValue('wmos_ptemp', panel, 1); recordWmosHistory('wmos:panel', panel, sourceTime, device || 'pannel temperature'); updated = true; }
            if (ambient !== null) { setWmosPanelValue('wmos_atemp', ambient, 1); recordWmosHistory('wmos:ambient', ambient, sourceTime, device || 'Ambient Temperature'); updated = true; }
            if (wind !== null) { setWmosPanelValue('wmos_wind', wind, 1); recordWmosHistory('wmos:wind', wind, sourceTime, device || 'Wind'); updated = true; }
            if (humidity !== null) { setWmosPanelValue('wmos_hum', humidity, 1); recordWmosHistory('wmos:humidity', humidity, sourceTime, device || 'Humidity'); updated = true; }

            if (updated) {
                const now = Date.now();
                aState.weather.lastReceivedAt = now;
                aState.weather.lastSampleAt = sourceTime ? parseTelemetryTime(sourceTime).getTime() : now;
                aState.weather.lastDevice = device || task || 'WMOS';
                const sampleLabel = document.getElementById('wmosLastSample');
                if (sampleLabel) sampleLabel.textContent = new Date(aState.weather.lastSampleAt).toLocaleTimeString('en-IN', { hour12: false });
                updateWmosLiveStatus();
            }
            return updated;
        }
        function updateWmosLiveStatus() {
            const dot = document.getElementById('wmosLiveDot');
            const label = document.getElementById('wmosLiveStatus');
            if (!dot || !label) return;
            const age = aState.weather.lastReceivedAt ? Date.now() - aState.weather.lastReceivedAt : Infinity;
            if (age <= 5000) {
                dot.className = 'w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse';
                label.className = 'text-[10px] font-black text-emerald-600 uppercase tracking-wider';
                label.textContent = 'Live Telemetry';
            } else if (age <= 15000) {
                dot.className = 'w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse';
                label.className = 'text-[10px] font-black text-amber-600 uppercase tracking-wider';
                label.textContent = 'Telemetry delayed';
            } else {
                dot.className = 'w-2.5 h-2.5 rounded-full bg-slate-400';
                label.className = 'text-[10px] font-black text-slate-500 uppercase tracking-wider';
                label.textContent = 'Waiting for live telemetry';
            }
        }
        function analyticsMessageUnitId(message = {}) {
            return String(
                message.unit_id ||
                message.request?.unit_id ||
                message.unitId ||
                message.request?.unitId ||
                ''
            ).trim();
        }

        function captureAnalyticsWmosMessage(message) {
            if (!message || typeof message !== 'object') return;
            const messageUnit = analyticsMessageUnitId(message);
            const messageTask = message.task || message.pageName || message.type || '';
            const baseDevice = message.device || message.deviceName || message.sensor || message.name || '';
            const weatherContext = /wmos|wmas|weather|pyran|pyrimeter|panel|pannel|ambient|wind|humid|radiat|irradiance|anemometer|wind\s*speed|wind\s*velocity/i.test(String(messageTask) + ' ' + String(baseDevice));
            if (messageUnit && messageUnit !== wsUnitId && !weatherContext) return;
            if (weatherContext && messageUnit) liveWmasUnitId = messageUnit;
            const defaultTime = message.time || message.timestamp || message.ts || message.recorded_at || '';
            let updated = false;
            const consume = (values, device, task, time) => {
                if (!values || typeof values !== 'object' || Array.isArray(values)) return;
                updated = captureWmosValues(values, time || defaultTime, device || baseDevice, task || messageTask) || updated;
            };
            const walk = (node, inheritedDevice = baseDevice, inheritedTask = messageTask, inheritedTime = defaultTime, depth = 0) => {
                if (!node || typeof node !== 'object' || depth > 7) return;
                const nodeDevice = node.device || node.deviceName || node.sensor || node.name || inheritedDevice;
                const nodeTask = node.task || node.pageName || node.type || inheritedTask;
                const nodeTime = node.time || node.timestamp || node.ts || node.recorded_at || inheritedTime;
                if (node.values && typeof node.values === 'object' && !Array.isArray(node.values)) consume(node.values, nodeDevice, nodeTask, nodeTime);
                if (node.data && typeof node.data === 'object') {
                    if (Array.isArray(node.data)) node.data.forEach(row => walk(row, nodeDevice, nodeTask, nodeTime, depth + 1));
                    else walk(node.data, nodeDevice, nodeTask, nodeTime, depth + 1);
                }
                if (node.payload && typeof node.payload === 'object') walk(node.payload, nodeDevice, nodeTask, nodeTime, depth + 1);
                if (node.result && typeof node.result === 'object') walk(node.result, nodeDevice, nodeTask, nodeTime, depth + 1);
                if (!node.values && !node.data && !node.payload && !node.result) consume(node, nodeDevice, nodeTask, nodeTime);
            };
            if (message.values && typeof message.values === 'object') consume(message.values, baseDevice, messageTask, defaultTime);
            walk(message.data, baseDevice, messageTask, defaultTime, 0);
            walk(message.payload, baseDevice, messageTask, defaultTime, 0);
            walk(message.result, baseDevice, messageTask, defaultTime, 0);
            if (updated) updateWmosLiveStatus();
        }

        function requestSelectedWmosToday() {
            const source = WMOS_EXPORT_SOURCES[selectedWmas || ''];
            if (!source || !analyticsSocket || analyticsSocket.readyState !== WebSocket.OPEN) return;
            analyticsSocket.send(JSON.stringify({ type: 'get_daily_data', unit_id: liveWmasUnitId || LIVE_WMOS_UNIT_ID, device: source.device, date: todayKey() }));
        }

        function renderWmasLiveStatus() {
            const sourceKey = selectedWmas || '';
            const latestSource = sourceKey
                ? Array.from(analyticsWmosHistory[sourceKey]?.values() || []).sort((a,b) => b.timestamp - a.timestamp)[0]
                : null;
            const age = latestSource
                ? Date.now() - Number(latestSource.timestamp)
                : (aState.weather.lastReceivedAt ? Date.now() - aState.weather.lastReceivedAt : Infinity);
            if (!wmasGraphLiveDot || !wmasGraphStatus) return;
            if (age <= 5000) {
                wmasGraphLiveDot.className = 'w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse';
                wmasGraphStatus.className = 'text-[10px] font-black text-emerald-600 uppercase tracking-wider';
                wmasGraphStatus.textContent = 'Live Telemetry';
            } else if (age <= 15000) {
                wmasGraphLiveDot.className = 'w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse';
                wmasGraphStatus.className = 'text-[10px] font-black text-amber-600 uppercase tracking-wider';
                wmasGraphStatus.textContent = 'Telemetry delayed';
            } else {
                wmasGraphLiveDot.className = 'w-2.5 h-2.5 rounded-full bg-slate-400';
                wmasGraphStatus.className = 'text-[10px] font-black text-slate-500 uppercase tracking-wider';
                wmasGraphStatus.textContent = 'Waiting for live telemetry';
            }
        }

        function initWmasTrendChart() {
            const canvas = document.getElementById('wmasTrendChart');
            if (!canvas) return;
            wmasTrendChart = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: { labels: [], datasets: [{ label: 'WMAS', data: [], borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.10)', pointRadius: 2, pointHoverRadius: 5, borderWidth: 2.5, tension: 0.28, spanGaps: true, fill: true }] },
                options: { responsive: true, maintainAspectRatio: false, animation: false, interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: { type: 'category', offset: false, grid: { display: false }, ticks: { color: '#64748b', autoSkip: true, maxTicksLimit: 14, maxRotation: 0, minRotation: 0, font: { size: 10 } }, title: { display: true, text: 'Time', color: '#64748b', font: { size: 10, weight: 'bold' } } },
                        y: { beginAtZero: false, grid: { color: '#e2e8f0' }, ticks: { color: '#64748b', font: { size: 10 } } }
                    },
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label(context) { return 'WMAS: ' + Number(context.parsed.y || 0).toFixed(2) + ' ' + (latestWmasUnit?.textContent || ''); } } } }
                }
            });
        }

        function renderAnalyticsSourceMode() {
            const showWmas = !!selectedWmas;
            document.getElementById('outputTrendSection')?.classList.toggle('hidden', showWmas);
            document.getElementById('wmasTrendSection')?.classList.toggle('hidden', !showWmas);
            if (showWmas) renderWmasTrend();
            else renderOutputTrend();
        }

        function renderWmasTrend() {
            const sourceKey = selectedWmas || '';
            const source = WMOS_EXPORT_SOURCES[sourceKey];
            const raw = source ? Array.from(analyticsWmosHistory[sourceKey]?.values() || []).sort((a,b) => a.timestamp - b.timestamp) : [];
            const hasSelection = !!source;
            const hasData = raw.length > 0;
            if (wmasEmptyState) {
                wmasEmptyState.classList.toggle('hidden', hasSelection && hasData);
                wmasEmptyState.textContent = !hasSelection ? 'Select a WMAS measurement to load its live trend.' : 'Waiting for live sensor telemetry for this WMAS measurement.';
            }
            if (wmasTrendTitle) wmasTrendTitle.textContent = source ? source.label : 'WMAS Data';
            if (latestWmasUnit) latestWmasUnit.textContent = source?.unit || '';
            const latest = hasData ? raw[raw.length - 1] : null;
            if (latestWmasValue) latestWmasValue.textContent = latest ? Number(latest.value).toFixed(source.decimals) : '--';
            if (wmasLiveLabel) wmasLiveLabel.textContent = latest ? new Date(latest.timestamp).toLocaleTimeString('en-IN', {hour12:false}) : '--';
            if (wmasTrendChart) {
                wmasTrendChart.data.labels = raw.map(row => new Date(row.timestamp).toLocaleTimeString('en-IN', {hour:'2-digit',minute:'2-digit',hour12:false}));
                wmasTrendChart.data.datasets[0].data = raw.map(row => Number(Number(row.value).toFixed(source?.decimals ?? 2)));
                wmasTrendChart.update('none');
            }
            renderWmasLiveStatus();
        }
        function updateAnalyticsCards() {
            const inverterRows = Object.values(aState.inverters);
            const liveRows = inverterRows.filter(row => Number(row.lastSeen) > 0 && (Date.now() - Number(row.lastSeen)) <= 5000);
            const perfEl = document.getElementById('perf_val');
            const yieldEl = document.getElementById('yield_val');
            const availEl = document.getElementById('avail_val');
            if (!liveRows.length) {
                perfEl.innerHTML = '-- <span class="text-sm font-bold text-blue-600">%</span>';
                yieldEl.innerHTML = '-- <span class="text-sm font-bold text-purple-600">kWh</span>';
                availEl.innerHTML = '-- <span class="text-sm font-bold text-emerald-600">%</span>';
                return;
            }
            const totalInv = liveRows.length;
            const activeInv = liveRows.filter(row => row.online && Number(row.outputKw) > GENERATION_THRESHOLD_KW).length;
            const livePower = liveRows.reduce((sum, row) => sum + (Number(row.outputKw) || 0), 0);
            const liveEnergy = liveRows.reduce((sum, row) => sum + (Number(row.dailyGen) || 0), 0);
            const perf = Number(cfg.capacity) > 0 ? ((livePower / (Number(cfg.capacity) * 1000)) * 100) : 0;
            const avail = totalInv > 0 ? ((activeInv / totalInv) * 100) : 0;
            perfEl.innerHTML = perf.toFixed(1) + ' <span class="text-sm font-bold text-blue-600">%</span>';
            yieldEl.innerHTML = liveEnergy.toFixed(2) + ' <span class="text-sm font-bold text-purple-600">kWh</span>';
            availEl.innerHTML = avail.toFixed(1) + ' <span class="text-sm font-bold text-emerald-600">%</span>';
        }

        function rawSelectedOutputRows() {
            if (!selectedInverter || !aState.history[selectedInverter]) return [];
            const now=new Date(),currentMinute=minuteOfDay(now),endMinute=Math.min(TREND_END_MINUTE,currentMinute);
            const rows=Array.from(aState.history[selectedInverter].values()).filter(row=>{const date=new Date(row.timestamp),minute=minuteOfDay(date);return row.date===todayKey()&&minute>=TREND_START_MINUTE&&minute<=endMinute&&Number.isFinite(Number(row.outputKw));}).sort((a,b)=>a.timestamp-b.timestamp);
            const firstGenerationIndex=rows.findIndex(row=>Number(row.outputKw)>GENERATION_THRESHOLD_KW); return firstGenerationIndex>=0?rows.slice(firstGenerationIndex):[];
        }
        function displayedOutputRows() { const rows=rawSelectedOutputRows(); if(!rows.length)return[]; const bucketed=new Map(); rows.forEach(row=>{const date=new Date(row.timestamp),minute=minuteOfDay(date),bucketMinute=Math.floor(minute/TREND_BUCKET_MINUTES)*TREND_BUCKET_MINUTES;bucketed.set(bucketMinute,row)}); const displayed=Array.from(bucketed.values()).sort((a,b)=>a.timestamp-b.timestamp),latestRaw=rows[rows.length-1],latestDisplayed=displayed[displayed.length-1]; if(!latestDisplayed||latestDisplayed.timestamp!==latestRaw.timestamp)displayed.push(latestRaw); return displayed; }

        function initOutputTrendChart() {
            const canvas=document.getElementById('outputTrendChart'); if(!canvas)return;
            outputTrendChart=new Chart(canvas.getContext('2d'),{type:'line',data:{labels:[],datasets:[{label:'Output (kW)',data:[],borderColor:'#2563eb',backgroundColor:'rgba(37,99,235,0.12)',pointBackgroundColor:'#2563eb',pointBorderColor:'#ffffff',pointRadius:2,pointHoverRadius:5,borderWidth:2.5,tension:.28,spanGaps:true,fill:true}]},options:{responsive:true,maintainAspectRatio:false,animation:false,interaction:{mode:'index',intersect:false},scales:{x:{type:'category',offset:false,grid:{display:false},ticks:{color:'#64748b',autoSkip:true,maxTicksLimit:14,maxRotation:0,minRotation:0,font:{size:10}},title:{display:true,text:'Time',color:'#64748b',font:{size:10,weight:'bold'}}},y:{beginAtZero:true,grid:{color:'#e2e8f0'},ticks:{color:'#64748b',font:{size:10}},title:{display:true,text:'Output (kW)',color:'#64748b',font:{size:10,weight:'bold'}}}},plugins:{legend:{display:false},tooltip:{callbacks:{label(context){return`Output: ${Number(context.parsed.y||0).toFixed(2)} kW`;}}}}}});
        }
        function renderOutputTrend() {
            const rows=displayedOutputRows(),hasSelection=!!selectedInverter&&!selectedWmas,hasGeneration=rows.length>0; emptyState.classList.toggle('hidden',hasSelection&&hasGeneration);
            if(!hasSelection)emptyState.textContent='Select an inverter to load today’s output trend.';else if(!hasGeneration)emptyState.textContent='Waiting for this inverter to start generating output today.';
            exportButton.disabled=!hasSelection||!hasGeneration;
            if (!selectedInverter && !selectedWmas) generateExcelButton.disabled = true;
            outputTrendTitle.textContent=hasSelection?`${inverterLabel(selectedInverter)} Output`:'Inverter Output'; const latest=hasGeneration?rows[rows.length-1]:null; analyticsLiveLabel.textContent=latest?new Date(latest.timestamp).toLocaleTimeString('en-IN',{hour12:false}):'--'; latestOutputValue.textContent=latest?Number(latest.outputKw).toFixed(2):'--';
            if(!outputTrendChart)return; outputTrendChart.data.labels=rows.map(row=>new Date(row.timestamp).toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit',hour12:false})); outputTrendChart.data.datasets[0].data=rows.map(row=>Number(Number(row.outputKw).toFixed(2))); outputTrendChart.update('none');
        }

        function dbInverterValues(row){return{'Total active power':row.power_kw,'Power factor':row.power_factor,'RY voltage':row.vac_ab,'YB voltage':row.vac_bc,'BR voltage':row.vac_ca,'RY current':row.current_a,'YB current':row.current_b,'BR current':row.current_c,'Daily power yields':row.daily_gen_kwh,'Work state':row.work_state||row.status_text||''};}
        function loadLatestSnapshot(){if(!window.LiveWsStore?.fastSnapshot)return;window.LiveWsStore.fastSnapshot(currentPlant).then(res=>res.json()).then(res=>{if(res.status!=='success'||!res.data)return;(res.data.inverters||[]).forEach(row=>applyInverterReading(row.inverter_name,dbInverterValues(row),row.snapshot_at||''));populateAnalyticsInverterOptions();updateAnalyticsCards();renderOutputTrend();}).catch(()=>{});}
        function normalizeWsRows(message){
            const rows = [];
            const baseDevice = message.device || message.deviceName || '';
            const baseTime = message.time || message.timestamp || message.ts || '';
            if (message.values && typeof message.values === 'object' && !Array.isArray(message.values)) {
                rows.push({device:baseDevice,values:message.values,time:baseTime});
            }
            const data = message.data;
            const rawRows = Array.isArray(data) ? data : (data && typeof data === 'object' ? [data] : []);
            rawRows.forEach(row => {
                if (!row || typeof row !== 'object') return;
                const values = row.values && typeof row.values === 'object' && !Array.isArray(row.values)
                    ? row.values
                    : row.data && typeof row.data === 'object' && !Array.isArray(row.data)
                        ? row.data
                        : row;
                rows.push({
                    device: row.device || row.deviceName || baseDevice,
                    values,
                    time: row.time || row.timestamp || row.ts || row.recorded_at || baseTime
                });
            });
            return rows;
        }
        function requestSelectedInverterToday(){if(!selectedInverter||!analyticsSocket||analyticsSocket.readyState!==WebSocket.OPEN)return;const deviceName=aState.inverters[selectedInverter]?.wsName||selectedInverter;analyticsSocket.send(JSON.stringify({type:'get_daily_data',unit_id:wsUnitId,device:deviceName,date:todayKey()}));}
        function handleDeviceList(devices){
            if(!Array.isArray(devices)) return;
            devices.forEach(device => {
                const name = (typeof device === 'string' ? device : (device?.name || device?.device || device?.deviceName || '')).toString().trim();
                if (isInverterDeviceName(name)) ensureInverter(name);
            });
            populateAnalyticsInverterOptions();
            requestSelectedInverterToday();
        }

        function connectWSAnalytics(){
            const wsUrl=(cfg.ws_url || "wss://vinobasolar.scadahub.in:5001"); if(!wsUrl)return; const ws=new WebSocket(wsUrl); analyticsSocket=ws;
             ws.onopen=function(){
                document.getElementById('refreshPulse').className='w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.6)]';
                // Subscribe only to the selected plant. Never mix telemetry from another plant.
                ws.send(JSON.stringify({type:'subscribe',unit_id:wsUnitId}));
                ws.send(JSON.stringify({type:'get_devices',unit_id:wsUnitId}));
                requestSelectedInverterToday();
            };
             ws.onmessage=function(event){
                try {
                    const message=JSON.parse(event.data);
                    // Process WMOS/WMAS before unit filtering: weather broadcasts may
                    // carry a weather-unit id while Home receives them on this socket.
                    captureAnalyticsWmosMessage(message);
                    const messageUnitId=message.unit_id||message.request?.unit_id||message.unitId||message.request?.unitId||'';
                    window.LiveWsStore?.storeMessage?.(message,currentPlant);
                    if(message.type==='device_list'){
                        handleDeviceList(message.devices || message.data || []);
                        return;
                    }
                    if(messageUnitId&&messageUnitId!==wsUnitId)return;
                    let updated=false;
                    normalizeWsRows(message).forEach(row=>{
                        const device=row.device||message.device||message.deviceName||'';
                        if(!isInverterDeviceName(device))return;
                        if(applyInverterReading(device,row.values,row.time||''))updated=true;
                    });
                    if(updated){updateAnalyticsCards();renderOutputTrend();}
                } catch(err){}
            };
            ws.onclose=function(){if(analyticsSocket===ws)analyticsSocket=null;document.getElementById('refreshPulse').className='w-2.5 h-2.5 bg-red-500 rounded-full';setTimeout(connectWSAnalytics,2000);};
        }

        function connectWSAnalyticsWmos(){
            // WMOS/WMAS is streamed by the same live plant WebSocket above.
            analyticsWmosSocket = null;
        }

        // Builds a complete minute-by-minute report only between the first and
        // last actual WebSocket samples. It never invents rows after the last sample.
        function minuteSeries(rawRows,valueSelector,sourceSelector){
            const raw=(rawRows||[]).filter(row=>Number.isFinite(Number(row.timestamp))).sort((a,b)=>a.timestamp-b.timestamp);if(!raw.length)return[];
            const actualByMinute=new Map();raw.forEach(row=>actualByMinute.set(Math.floor(Number(row.timestamp)/60000),row));
            const firstMinute=Math.floor(Number(raw[0].timestamp)/60000),lastMinute=Math.floor(Number(raw[raw.length-1].timestamp)/60000),rows=[];let lastReading=null;
            for(let minute=firstMinute;minute<=lastMinute;minute+=1){const actual=actualByMinute.get(minute)||null;if(actual)lastReading=actual;if(!lastReading)continue;const timestamp=new Date(minute*60000);rows.push({date:localDateKey(timestamp),time:timestamp.toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false}),value:valueSelector(lastReading),source:sourceSelector(lastReading),sampleTime:new Date(lastReading.timestamp).toLocaleTimeString('en-IN',{hour12:false}),status:actual?'Live sample':'Carried forward',reading:lastReading});}
            return rows;
        }

        function rawSelectedInverterExportRows(){if(!selectedInverter||!analyticsRawInverterHistory[selectedInverter])return[];return Array.from(analyticsRawInverterHistory[selectedInverter].values()).filter(row=>localDateKey(new Date(row.timestamp))===todayKey()).sort((a,b)=>a.timestamp-b.timestamp);}
        function excelCellValue(value){if(value===null||value===undefined)return'';if(typeof value==='number'||typeof value==='boolean')return value;if(typeof value==='object')return JSON.stringify(value);return String(value);}

        function fullInverterExportRows(){
            const raw=rawSelectedInverterExportRows();if(!raw.length)return[];
            const metricKeys=[];const seen=new Set();raw.forEach(row=>Object.keys(row.values||{}).forEach(key=>{if(!seen.has(key)){seen.add(key);metricKeys.push(key);}}));
            return minuteSeries(raw,row=>row.outputKw,row=>row.outputSource||'WebSocket').map(minute=>{
                const reading=minute.reading||{},base={Date:minute.date,Time:minute.time,Inverter:inverterLabel(selectedInverter),'WebSocket Device':reading.device||aState.inverters[selectedInverter]?.wsName||selectedInverter,'Output (kW)':Number.isFinite(Number(reading.outputKw))?Number(Number(reading.outputKw).toFixed(3)):'','Daily Energy (kWh)':Number.isFinite(Number(reading.dailyGen))?Number(Number(reading.dailyGen).toFixed(3)):'','Output Source':reading.outputSource||'', 'Work State':reading.workState||'','Sample Time':minute.sampleTime,'Reading Status':minute.status};
                metricKeys.forEach(key=>{const column=Object.prototype.hasOwnProperty.call(base,key)?`WS ${key}`:key;base[column]=excelCellValue(reading.values?.[key]);});return base;
            });
        }

        function reportSummary(sourceLabel,rawRows,exportedRows,unitId){
            const raw=(rawRows||[]).filter(row=>Number.isFinite(Number(row.timestamp))).sort((a,b)=>a.timestamp-b.timestamp);if(!raw.length)return[];
            const first=new Date(raw[0].timestamp),last=new Date(raw[raw.length-1].timestamp);return[
                {Field:'Plant',Value:plantNames[currentPlant]||currentPlant},{Field:'Selected Source',Value:sourceLabel},{Field:'Report Date',Value:todayKey()},{Field:'WebSocket Unit ID',Value:unitId},{Field:'Start Time',Value:first.toLocaleTimeString('en-IN',{hour12:false})},{Field:'End Time',Value:last.toLocaleTimeString('en-IN',{hour12:false})},{Field:'Actual WebSocket Samples',Value:raw.length},{Field:'Exported Minute Rows',Value:exportedRows.length},{Field:'Generated At',Value:new Date().toLocaleString('en-IN',{hour12:false})}
            ];
        }

        function writeAnalyticsWorkbook(rows,sheetName,fileBase,widths,summaryRows=[]){
            if(!rows.length)return false;if(window.XLSX){const book=XLSX.utils.book_new();if(summaryRows.length){const summary=XLSX.utils.json_to_sheet(summaryRows);summary['!cols']=[{wch:24},{wch:32}];XLSX.utils.book_append_sheet(book,summary,'Summary');}const sheet=XLSX.utils.json_to_sheet(rows);sheet['!cols']=widths;XLSX.utils.book_append_sheet(book,sheet,sheetName);XLSX.writeFile(book,`${fileBase}.xlsx`);return true;}
            const headers=Object.keys(rows[0]),csv=[headers.join(','),...rows.map(row=>headers.map(key=>`"${String(row[key]??'').replace(/"/g,'""')}"`).join(','))].join('\n'),blob=new Blob([csv],{type:'text/csv;charset=utf-8'}),url=URL.createObjectURL(blob),link=document.createElement('a');link.href=url;link.download=`${fileBase}.csv`;document.body.appendChild(link);link.click();link.remove();URL.revokeObjectURL(url);return true;
        }

        function exportSelectedInverterExcel(){
            if(!selectedInverter)return false;const raw=rawSelectedInverterExportRows(),rows=fullInverterExportRows();if(!rows.length)return false;const safeName=selectedInverter.replace(/[^a-z0-9_-]+/gi,'_'),summary=reportSummary(inverterLabel(selectedInverter),raw,rows,wsUnitId);const widths=Object.keys(rows[0]).map(key=>({wch:Math.min(32,Math.max(12,key.length+2))}));return writeAnalyticsWorkbook(rows,'Full Inverter Data',`${currentPlant}_${safeName}_${todayKey()}_full_report`,widths,summary);
        }

        function exportSelectedWmosExcel(sourceKey){
            const source=WMOS_EXPORT_SOURCES[sourceKey];if(!source)return false;const raw=Array.from(analyticsWmosHistory[sourceKey]?.values()||[]).sort((a,b)=>a.timestamp-b.timestamp),minuteRows=minuteSeries(raw,row=>Number(row.value),()=>source.label);if(!minuteRows.length)return false;
            const rows=minuteRows.map(row=>({Date:row.date,Time:row.time,'Data Source':source.label,'WebSocket Device':row.reading?.device||source.device,Value:Number(Number(row.value).toFixed(source.decimals)),Unit:source.unit,'Sample Time':row.sampleTime,'Reading Status':row.status}));
            const safeName=source.label.replace(/[^a-z0-9_-]+/gi,'_'),summary=reportSummary(source.label,raw,rows,LIVE_WMOS_UNIT_ID);return writeAnalyticsWorkbook(rows,'Full WMOS Data',`${currentPlant}_${safeName}_${todayKey()}_full_report`,[{wch:12},{wch:12},{wch:24},{wch:24},{wch:14},{wch:12},{wch:14},{wch:18}],summary);
        }

        function waitForHistory(test,timeoutMs=3500){return new Promise(resolve=>{if(test()){resolve(true);return;}const started=Date.now(),timer=setInterval(()=>{if(test()){clearInterval(timer);resolve(true);}else if(Date.now()-started>=timeoutMs){clearInterval(timer);resolve(false);}},100);});}

        async function generateSelectedAnalyticsExcel(){
            const selected = sourceSelect?.value || selectedSource || '';
            if (!selected) return;
            const oldHtml = generateExcelButton.innerHTML;
            generateExcelButton.disabled = true;
            generateExcelButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Loading full history...</span>';
            try {
                if (WMOS_EXPORT_SOURCES[selected]) {
                    selectedWmas = selected;
                    requestSelectedWmosToday();
                    await waitForHistory(() => analyticsWmosHistory[selected]?.size > 0);
                    if (!exportSelectedWmosExcel(selected)) alert('No live WMAS WebSocket history is available yet for the selected source.');
                } else {
                    selectedInverter = selected;
                    requestSelectedInverterToday();
                    await waitForHistory(() => analyticsRawInverterHistory[selected]?.size > 0);
                    if (!exportSelectedInverterExcel()) alert('No live inverter WebSocket history is available yet for the selected inverter.');
                }
            } finally {
                generateExcelButton.innerHTML = oldHtml;
                generateExcelButton.disabled = !sourceSelect?.value;
            }
        }

        sourceSelect?.addEventListener('change', () => {
            selectedSource = sourceSelect.value || '';
            if (WMOS_EXPORT_SOURCES[selectedSource]) {
                selectedWmas = selectedSource;
                selectedInverter = '';
                requestSelectedWmosToday();
            } else {
                selectedInverter = selectedSource;
                selectedWmas = '';
                requestSelectedInverterToday();
            }
            generateExcelButton.disabled = !selectedSource;
            renderAnalyticsSourceMode();
        });
        exportButton?.addEventListener('click', exportSelectedInverterExcel);
        generateExcelButton?.addEventListener('click', generateSelectedAnalyticsExcel);

        initOutputTrendChart();
        initWmasTrendChart();
        seedConfiguredInverters();
        renderAnalyticsSourceMode();
        connectWSAnalytics();
        connectWSAnalyticsWmos();

        // Refresh the visible Analytics values every second from the latest live
        // WebSocket readings already stored in memory. This never generates or
        // estimates telemetry when the gateway has not delivered a new sample.
        setInterval(() => {
            updateAnalyticsCards();
            renderOutputTrend();
            updateWmosLiveStatus();
        }, 1000);
    </script>
</body>
</html>