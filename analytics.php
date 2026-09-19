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
        'ws_url' => $analyticsWsUrl,
    ],
    'makkalpower' => [
        'name' => 'Makkal Power',
        'capacity' => 2.0,
        'location' => 'Karur',
        'inverter_count' => 8,
        'ws_url' => $analyticsWsUrl,
    ],
    'anushyam' => [
        'name' => 'Anushyam Plant',
        'capacity' => 2.0,
        'location' => 'Karur',
        'inverter_count' => 8,
        'ws_url' => $analyticsWsUrl,
    ],
];

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
    <title id="pageTitle">Plant Analytics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
<div class="min-h-screen flex relative">
    <div id="overlay" class="fixed inset-0 bg-slate-900/40 hidden z-30 md:hidden"></div>
    <div id="sidebar-container"></div>

    <main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden">
        <header class="bg-white p-4 sm:px-6 flex justify-between items-center sticky top-0 z-20 border-b border-slate-200 shadow-sm">
            <div class="flex items-center gap-3 min-w-0">
                <button id="menuBtn" class="md:hidden text-emerald-600 text-2xl">&#9776;</button>
                <div class="min-w-0">
                    <h2 class="text-xl font-black text-slate-900 tracking-tight truncate" id="headerPlantName">Plant Analytics</h2>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.18em]">Live inverter + WMOS telemetry</p>
                </div>
            </div>
            <div class="flex items-center gap-3 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-200">
                <span id="liveDot" class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
                <span id="liveStatus" class="text-[10px] font-black text-slate-500 uppercase tracking-wider">Waiting for live telemetry</span>
                <span class="hidden sm:inline text-xs font-bold text-slate-600 font-mono" id="clockDisplay">--:--:--</span>
            </div>
        </header>

        <div class="p-4 sm:p-6 lg:p-8 w-full max-w-[1920px] mx-auto flex flex-col gap-6">
            <section class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 sm:p-5">
                <div class="flex flex-col xl:flex-row xl:items-end gap-4">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Today Data</p>
                        <h1 class="text-2xl font-black text-slate-900" id="trendHeading">Live Source Trend</h1>
                        <p id="trendDescription" class="text-xs text-slate-500 mt-1">Choose an inverter or the single common WMOS source.</p>
                    </div>
                    <div class="ml-auto flex flex-wrap items-end gap-2 w-full xl:w-auto">
                        <label class="text-xs font-bold text-slate-500 min-w-[260px]">
                            <span class="block mb-1">Live Data Source</span>
                            <select id="analyticsSourceSelect" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <option value="">Select Inverter / WMOS</option>
                                <optgroup id="inverterGroup" label="Inverters"></optgroup>
                                <option value="wmos:all">WMOS - All Weather Data</option>
                            </select>
                        </label>
                        <button id="generateAnalyticsExcel" disabled type="button" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-black text-white shadow-sm hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed">
                            <i class="fa-solid fa-file-excel"></i>
                            <span>Download Live Excel</span>
                        </button>
                    </div>
                </div>
            </section>

            <section id="inverterSection" class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-lg font-black text-slate-900" id="inverterChartTitle">Inverter Output</h2>
                        <p class="text-xs text-slate-500">Actual WebSocket samples only.</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Latest Output</p>
                        <p class="text-xl font-black text-blue-700"><span id="latestInverterValue">--</span> <span class="text-xs">kW</span></p>
                        <p class="text-[10px] text-slate-400">Sample <span id="latestInverterTime">--</span></p>
                    </div>
                </div>
                <div id="inverterEmpty" class="py-12 text-center rounded-xl border border-dashed border-slate-200 bg-slate-50 text-sm text-slate-500">
                    Select an inverter to load live output data.
                </div>
                <div id="inverterChartWrap" class="h-[420px] hidden">
                    <canvas id="inverterChart"></canvas>
                </div>
            </section>

            <section id="wmosSection" class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 sm:p-5 hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-lg font-black text-slate-900">WMOS - All Live Weather Data</h2>
                        <p class="text-xs text-slate-500">One common source. Radiation, panel temperature, ambient temperature, wind speed and humidity come from their exact WMOS devices and fields.</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Last Sensor Update</p>
                        <p id="wmosLastTime" class="text-xl font-black text-emerald-700">--</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3 mb-5">
                    <div class="rounded-xl border border-amber-100 bg-amber-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-amber-600">Radiation</p>
                        <p class="mt-2 text-2xl font-black text-slate-900"><span id="wmRad">--</span> <span class="text-xs font-bold text-slate-500">W/m²</span></p>
                        <p class="text-[10px] text-slate-500 mt-1">Pyranometer / raw data</p>
                    </div>
                    <div class="rounded-xl border border-orange-100 bg-orange-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-orange-600">Panel Temp</p>
                        <p class="mt-2 text-2xl font-black text-slate-900"><span id="wmPanel">--</span> <span class="text-xs font-bold text-slate-500">°C</span></p>
                        <p class="text-[10px] text-slate-500 mt-1">Panel temperature device</p>
                    </div>
                    <div class="rounded-xl border border-sky-100 bg-sky-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-sky-600">Amb Temp</p>
                        <p class="mt-2 text-2xl font-black text-slate-900"><span id="wmAmbient">--</span> <span class="text-xs font-bold text-slate-500">°C</span></p>
                        <p class="text-[10px] text-slate-500 mt-1">Ambient Temperature device</p>
                    </div>
                    <div class="rounded-xl border border-cyan-100 bg-cyan-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-cyan-600">Wind Speed</p>
                        <p class="mt-2 text-2xl font-black text-slate-900"><span id="wmWind">--</span> <span class="text-xs font-bold text-slate-500">m/s</span></p>
                        <p class="text-[10px] text-slate-500 mt-1">Wind / windspeed</p>
                    </div>
                    <div class="rounded-xl border border-violet-100 bg-violet-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-violet-600">Humidity</p>
                        <p class="mt-2 text-2xl font-black text-slate-900"><span id="wmHumidity">--</span> <span class="text-xs font-bold text-slate-500">%RH</span></p>
                        <p class="text-[10px] text-slate-500 mt-1">Humidity / relative humidity</p>
                    </div>
                </div>

                <div class="mt-2 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-xs font-bold text-slate-600">Live WMOS values only.</p>
                    <p class="text-[11px] text-slate-500 mt-1">The five readings above update from the selected plant's live WebSocket telemetry. Download Live Excel exports the received WMOS samples.</p>
                </div>
            </section>
        </div>
    </main>
</div>

<script>
const currentPlant = <?php echo json_encode($currentPlant); ?>;
const plantConfig = <?php echo json_encode($analyticsPlantConfig, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
const cfg = plantConfig[currentPlant] || { name: currentPlant, capacity: 1, inverter_count: 0, ws_url: 'wss://vinobasolar.scadahub.in:5001' };
const wsUnitId = currentPlant;
const WS_URL = cfg.ws_url || 'wss://vinobasolar.scadahub.in:5001';

document.getElementById('headerPlantName').textContent = (cfg.name || currentPlant) + ' - Analytics';
document.getElementById('pageTitle').textContent = (cfg.name || currentPlant) + ' - Analytics';

fetch('sidebar.html', { cache: 'no-store' }).then(r => r.text()).then(html => {
    const holder = document.getElementById('sidebar-container');
    holder.innerHTML = html;
    holder.querySelectorAll('script').forEach(oldScript => {
        const s = document.createElement('script');
        s.textContent = oldScript.textContent;
        oldScript.replaceWith(s);
    });
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
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
        overlay?.classList.add('hidden');
    });
}).catch(() => {});

const sourceSelect = document.getElementById('analyticsSourceSelect');
const inverterGroup = document.getElementById('inverterGroup');
const generateButton = document.getElementById('generateAnalyticsExcel');
const inverterSection = document.getElementById('inverterSection');
const wmosSection = document.getElementById('wmosSection');
const inverterEmpty = document.getElementById('inverterEmpty');
const inverterChartWrap = document.getElementById('inverterChartWrap');
const trendHeading = document.getElementById('trendHeading');
const trendDescription = document.getElementById('trendDescription');

let selectedSource = '';
let socket = null;
let reconnectTimer = null;
let inverterChart = null;
const wmosCharts = {};
const state = {
    inverters: {},
    selectedInverter: '',
    inverterHistory: {},
    wmos: {
        radiation: null,
        panelTemp: null,
        ambientTemp: null,
        windSpeed: null,
        humidity: null,
        lastReceivedAt: 0,
        lastSampleAt: 0
    },
    wmosHistory: []
};

const WMOS_DEVICES = {
    radiation: { label: 'Radiation', unit: 'W/m²', decimals: 0, device: 'Pyranometer', key: 'raw data' },
    panelTemp: { label: 'Panel Temp', unit: '°C', decimals: 1, device: 'pannel temperature', key: 'pannel temperature' },
    ambientTemp: { label: 'Amb Temp', unit: '°C', decimals: 1, device: 'Ambient Temperature', key: 'Ambient temperature' },
    windSpeed: { label: 'Wind Speed', unit: 'm/s', decimals: 1, device: 'Wind', key: 'windspeed' },
    humidity: { label: 'Humidity', unit: '%RH', decimals: 1, device: 'Humidity', key: 'humidity' }
};

function normalizeName(v) {
    return String(v ?? '').toLowerCase().replace(/[._-]+/g, ' ').replace(/\s+/g, ' ').trim();
}

function parseNumber(v) {
    if (v === null || v === undefined || v === '') return null;
    if (typeof v === 'object') {
        for (const k of ['value', 'val', 'reading', 'current', 'last']) {
            if (Object.prototype.hasOwnProperty.call(v, k)) {
                const nested = parseNumber(v[k]);
                if (nested !== null) return nested;
            }
        }
        return null;
    }
    const n = Number(String(v).replace(/,/g, ''));
    return Number.isFinite(n) ? n : null;
}

function telemetryDate(raw) {
    if (!raw) return new Date();
    if (raw instanceof Date) return raw;
    const s = String(raw).trim();
    const timeParts = s.split(':');
    if ((timeParts.length === 2 || timeParts.length === 3) && timeParts.every(part => /^\d+$/.test(part))) {
        const p = timeParts.map(Number);
        return new Date(new Date().getFullYear(), new Date().getMonth(), new Date().getDate(), p[0], p[1], p[2] || 0);
    }
    const d = new Date(s);
    return Number.isNaN(d.getTime()) ? new Date() : d;
}

function todayKey() {
    const d = new Date();
    return [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
}

function minuteKey(ts) {
    const d = new Date(ts);
    return Math.floor(d.getTime() / 60000);
}

function deviceToWmosMetric(device) {
    const n = normalizeName(device);
    if (/pyranometer|pyrimeter/.test(n)) return 'radiation';
    if (/pannel.*temp|panel.*temp|module.*temp/.test(n)) return 'panelTemp';
    if (/ambient.*temp/.test(n) || n === 'ambient') return 'ambientTemp';
    if (/^wind$|wind.*speed|anemometer/.test(n)) return 'windSpeed';
    if (/^humidity$|relative humidity/.test(n)) return 'humidity';
    return '';
}

function hasWeatherTask(task, device = '') {
    const taskName = String(task || '').trim().toLowerCase();
    if (/^(wmos|wmas|weather)$/.test(taskName)) return true;
    return deviceToWmosMetric(device) !== '';
}

function exactWmosValue(metric, values) {
    if (!values || typeof values !== 'object' || Array.isArray(values)) return null;
    const wanted = WMOS_DEVICES[metric]?.key || '';
    const wantedNormalized = normalizeName(wanted);
    for (const [key, raw] of Object.entries(values)) {
        if (normalizeName(key) !== wantedNormalized) continue;
        return parseNumber(raw);
    }
    return null;
}

function cloneValues(values) {
    const out = {};
    Object.entries(values || {}).forEach(([k, v]) => {
        out[k] = (v && typeof v === 'object') ? JSON.stringify(v) : v;
    });
    return out;
}

function ensureInverter(name) {
    const raw = String(name || '').trim();
    const clean = canonicalInverterName(raw);
    const key = clean.toLowerCase();
    if (!clean || !isInverterDevice(raw, 'inverter')) return '';
    if (!state.inverters[key]) {
        state.inverters[key] = {
            key,
            wsName: clean,
            outputKw: null,
            dailyGen: null,
            lastSeen: 0,
            status: 'Waiting'
        };
        state.inverterHistory[key] = [];
    }
    return key;
}

function canonicalInverterName(name) {
    const raw = String(name || '').trim();
    const match = raw.match(/(?:inverter|inv)[\s_-]*(\d{1,2})/i) || raw.match(/^(\d{1,2})$/);
    return match ? 'Inverter ' + String(parseInt(match[1], 10)) : raw;
}

function isInverterDevice(name, task = '') {
    const n = normalizeName(name);
    if (!n || /vcb|transformer|trafo|oil|winding/.test(n)) return false;
    if (/inverter/.test(n) || /^inv(?:\s|\d|-|_)/.test(n)) return true;
    return /inverter/i.test(String(task || '')) && /^\d{1,2}$/.test(n);
}

function extractInverter(values) {
    if (!values || typeof values !== 'object') return { power: null, daily: null };
    let power = null;
    let daily = null;
    for (const [key, raw] of Object.entries(values)) {
        const n = normalizeName(key);
        const v = parseNumber(raw);
        if (v === null) continue;
        if (power === null && /active.*power|ac.*power|power.*ac|a c .*power/.test(n) && !/reactive|apparent|limit|ratio|3 phase/.test(n)) power = v;
        if (daily === null && /daily.*generation|daily.*gen|today.*generation|today.*gen/.test(n)) daily = v;
    }
    return { power, daily };
}

function selectedInverterHistory() {
    return state.inverterHistory[state.selectedInverter] || [];
}

function addInverterSample(name, values, sourceTime, task = '') {
    const key = ensureInverter(name, task);
    if (!key) return;
    const reading = extractInverter(values);
    if (reading.power === null && reading.daily === null) return;
    const time = telemetryDate(sourceTime);
    const sample = {
        timestamp: time.getTime(),
        powerKw: reading.power,
        dailyKwh: reading.daily,
        device: state.inverters[key].wsName,
        values: cloneValues(values)
    };
    state.inverters[key].outputKw = reading.power ?? state.inverters[key].outputKw;
    state.inverters[key].dailyGen = reading.daily ?? state.inverters[key].dailyGen;
    state.inverters[key].lastSeen = time.getTime();
    state.inverters[key].status = 'Live';
    if (!state.inverterHistory[key]) state.inverterHistory[key] = [];
    const arr = state.inverterHistory[key];
    const idx = arr.findIndex(row => row.timestamp === sample.timestamp);
    if (idx >= 0) arr[idx] = sample; else arr.push(sample);
    arr.sort((a, b) => a.timestamp - b.timestamp);
    if (arr.length > 5000) arr.splice(0, arr.length - 5000);
}

function mergeWmosSample(metric, value, sourceTime, device) {
    const time = telemetryDate(sourceTime);
    const timestamp = time.getTime();
    state.wmos[metric] = value;
    state.wmos.lastReceivedAt = Date.now();
    state.wmos.lastSampleAt = timestamp;
    const minute = minuteKey(timestamp);
    let row = state.wmosHistory.find(item => item.minute === minute);
    if (!row) {
        row = { minute, timestamp: timestamp };
        state.wmosHistory.push(row);
    }
    row.timestamp = Math.max(row.timestamp, timestamp);
    row[metric] = value;
    row[metric + '_device'] = device || WMOS_DEVICES[metric].device;
    row[metric + '_sample_time'] = timestamp;
    state.wmosHistory.sort((a, b) => a.timestamp - b.timestamp);
    if (state.wmosHistory.length > 5000) state.wmosHistory.splice(0, state.wmosHistory.length - 5000);
}

function mergeStoredWmosRows(rows) {
    (rows || []).forEach(row => {
        const timestamp = telemetryDate(row.recorded_at || row.timestamp).getTime();
        if (!Number.isFinite(timestamp)) return;
        const minute = minuteKey(timestamp);
        let target = state.wmosHistory.find(item => item.minute === minute);
        if (!target) {
            target = { minute, timestamp };
            state.wmosHistory.push(target);
        }
        target.timestamp = Math.max(target.timestamp, timestamp);
        ['radiation', 'panelTemp', 'ambientTemp', 'windSpeed', 'humidity'].forEach(metric => {
            const value = parseNumber(row[metric]);
            const rowMetric = deviceToWmosMetric(row.device_name || '');
            const deviceMatches = rowMetric === metric;
            const legacyNonZero = !row.device_name && value !== null && value !== 0;
            if (value !== null && (deviceMatches || legacyNonZero)) {
                target[metric] = value;
                target[metric + '_device'] = row.device_name || WMOS_DEVICES[metric].device;
                target[metric + '_sample_time'] = timestamp;
            }
        });
    });
    state.wmosHistory.sort((a, b) => a.timestamp - b.timestamp);
    if (state.wmosHistory.length > 5000) state.wmosHistory.splice(0, state.wmosHistory.length - 5000);
    const latest = state.wmosHistory[state.wmosHistory.length - 1];
    if (latest) {
        ['radiation', 'panelTemp', 'ambientTemp', 'windSpeed', 'humidity'].forEach(metric => {
            if (latest[metric] !== undefined) state.wmos[metric] = latest[metric];
        });
        state.wmos.lastSampleAt = latest.timestamp;
    }
}

async function loadStoredWmosData() {
    try {
        const response = await fetch('analytics_data.php?plant=' + encodeURIComponent(currentPlant) + '&date=' + encodeURIComponent(todayKey()), { cache: 'no-store', credentials: 'same-origin' });
        if (!response.ok) return;
        const payload = await response.json();
        if (!payload || !payload.success) return;
        mergeStoredWmosRows(payload.rows || []);
        renderWmos();
    } catch (_) {}
}

function consumeWmosFrame(values, device, task, sourceTime) {
    if (!hasWeatherTask(task, device)) return false;

    // SCADA sends WMOS as separate device messages. Do not require the
    // device name to be perfectly configured: identify the metric from the
    // actual WMOS field first, then use the device name as a fallback.
    const candidates = Object.keys(WMOS_DEVICES);
    for (const metric of candidates) {
        const value = exactWmosValue(metric, values);
        if (value !== null) {
            mergeWmosSample(metric, value, sourceTime, device);
            return true;
        }
    }

    const metric = deviceToWmosMetric(device);
    if (!metric) return false;

    // Some SCADA configurations use an alias for the value field. Accept
    // the known aliases for the matching WMOS device without accepting
    // unrelated telemetry.
    const aliases = {
        radiation: ['raw data', 'radiation'],
        panelTemp: ['pannel temperature', 'panel temperature', 'module temperature'],
        ambientTemp: ['Ambient temperature', 'ambient temperature', 'ambient temp'],
        windSpeed: ['windspeed', 'wind speed'],
        humidity: ['humidity', 'Humidity', 'relative humidity']
    };
    for (const key of aliases[metric] || []) {
        if (!Object.prototype.hasOwnProperty.call(values || {}, key)) continue;
        const value = parseNumber(values[key]);
        if (value !== null) {
            mergeWmosSample(metric, value, sourceTime, device);
            return true;
        }
    }

    return false;
}

function messageUnitId(message) {
    return String(message?.unit_id || message?.unitId || message?.request?.unit_id || message?.request?.unitId || '').trim();
}

function walkMessage(node, inheritedDevice = '', inheritedTask = '', inheritedTime = '') {
    if (!node || typeof node !== 'object') return;
    const device = node.device || node.deviceName || node.sensor || node.name || inheritedDevice;
    const task = node.task || node.pageName || inheritedTask;
    const time = node.time || node.timestamp || node.ts || node.recorded_at || inheritedTime;

    if (node.values && typeof node.values === 'object' && !Array.isArray(node.values)) {
        if (hasWeatherTask(task, device)) consumeWmosFrame(node.values, device, task, time);
        else if (isInverterDevice(device, task) || /^inverter$/i.test(String(task || ''))) addInverterSample(device, node.values, time, task);
    }

    if (node.data && typeof node.data === 'object') {
        if (Array.isArray(node.data)) node.data.forEach(row => walkMessage(row, device, task, time));
        else walkMessage(node.data, device, task, time);
    }
    if (node.payload && typeof node.payload === 'object') walkMessage(node.payload, device, task, time);
    if (node.result && typeof node.result === 'object') walkMessage(node.result, device, task, time);
}

function collectInverterDeviceNames(node, out = [], depth = 0) {
    if (depth > 6 || node === null || node === undefined) return out;
    if (Array.isArray(node)) {
        node.forEach(item => collectInverterDeviceNames(item, out, depth + 1));
        return out;
    }
    if (typeof node !== 'object') {
        const text = String(node).trim();
        if (text && isInverterDevice(text, 'inverter')) out.push(text);
        return out;
    }
    const candidates = [node.name, node.device, node.deviceName, node.inverter, node.inverter_name, node.label];
    candidates.forEach(value => {
        const text = String(value || '').trim();
        if (text && isInverterDevice(text, 'inverter')) out.push(text);
    });
    ['devices', 'data', 'inverters', 'items', 'results'].forEach(key => {
        if (node[key] !== undefined) collectInverterDeviceNames(node[key], out, depth + 1);
    });
    return out;
}

function consumeLiveMessage(message) {
    if (!message || typeof message !== 'object') return;
    const unit = messageUnitId(message);
    if (unit && unit !== wsUnitId) return;

    const task = message.task || message.pageName || '';
    const device = message.device || message.deviceName || message.sensor || '';
    const time = message.time || message.timestamp || message.ts || message.recorded_at || '';

    if (message.values && typeof message.values === 'object' && !Array.isArray(message.values)) {
        if (hasWeatherTask(task, device)) consumeWmosFrame(message.values, device, task, time);
        else if (isInverterDevice(device, task) || /^inverter$/i.test(String(task || ''))) addInverterSample(device, message.values, time, task);
    }
    walkMessage(message.data, device, task, time);
    walkMessage(message.payload, device, task, time);
    walkMessage(message.result, device, task, time);

    if (message.type === 'device_list' || message.event === 'device_list' || message.type === 'devices') {
        const names = collectInverterDeviceNames(message.devices || message.data || message.inverters || message);
        [...new Set(names.map(canonicalInverterName))].forEach(name => ensureInverter(name, 'inverter'));
        populateInverterOptions();
    }
}

function populateInverterOptions() {
    const entries = Object.values(state.inverters).sort((a, b) => a.wsName.localeCompare(b.wsName, undefined, { numeric: true }));
    const key = entries.map(item => item.key).join('|');
    if (inverterGroup.dataset.key === key) return;
    inverterGroup.dataset.key = key;
    inverterGroup.innerHTML = entries.map(item => '<option value="' + item.key + '">' + escapeHtml(item.wsName) + '</option>').join('');
    if (selectedSource && !selectedSource.startsWith('wmos:') && !state.inverters[selectedSource]) {
        selectedSource = '';
        sourceSelect.value = '';
    }
    updateGenerateButton();
}

function escapeHtml(v) {
    return String(v).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
}

function initChart(canvasId, label, unit) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;
    return new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: { labels: [], datasets: [{ label, data: [], borderWidth: 2.5, pointRadius: 1.8, pointHoverRadius: 4, tension: 0.25, spanGaps: true, fill: false }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { ticks: { maxTicksLimit: 12, maxRotation: 0, font: { size: 9 } }, title: { display: true, text: 'Time', font: { size: 9, weight: 'bold' } } },
                y: { beginAtZero: false, ticks: { font: { size: 9 }, callback: v => v + (unit ? ' ' + unit : '') } }
            },
            plugins: { legend: { display: false } }
        }
    });
}

function initCharts() {
    inverterChart = initChart('inverterChart', 'Output', 'kW');
}

function renderInverter() {
    const selected = state.inverters[state.selectedInverter];
    const rows = selectedInverterHistory().filter(row => row.powerKw !== null && row.powerKw !== undefined);
    inverterEmpty.classList.toggle('hidden', !!selected && rows.length > 0);
    inverterChartWrap.classList.toggle('hidden', !selected || rows.length === 0);
    document.getElementById('inverterChartTitle').textContent = selected ? selected.wsName + ' Output' : 'Inverter Output';
    document.getElementById('latestInverterValue').textContent = selected?.outputKw != null ? Number(selected.outputKw).toFixed(2) : '--';
    document.getElementById('latestInverterTime').textContent = selected?.lastSeen ? new Date(selected.lastSeen).toLocaleTimeString('en-IN', { hour12: false }) : '--';
    if (!inverterChart) return;
    inverterChart.data.labels = rows.map(row => new Date(row.timestamp).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: false }));
    inverterChart.data.datasets[0].data = rows.map(row => Number(row.powerKw));
    inverterChart.update('none');
}

function formatWmosValue(metric, value) {
    if (value === null || value === undefined || !Number.isFinite(Number(value))) return '--';
    return Number(value).toFixed(WMOS_DEVICES[metric].decimals);
}

function renderWmos() {
    document.getElementById('wmRad').textContent = formatWmosValue('radiation', state.wmos.radiation);
    document.getElementById('wmPanel').textContent = formatWmosValue('panelTemp', state.wmos.panelTemp);
    document.getElementById('wmAmbient').textContent = formatWmosValue('ambientTemp', state.wmos.ambientTemp);
    document.getElementById('wmWind').textContent = formatWmosValue('windSpeed', state.wmos.windSpeed);
    document.getElementById('wmHumidity').textContent = formatWmosValue('humidity', state.wmos.humidity);
    document.getElementById('wmosLastTime').textContent = state.wmos.lastSampleAt ? new Date(state.wmos.lastSampleAt).toLocaleTimeString('en-IN', { hour12: false }) : '--';

    const body = document.getElementById('wmosLiveTableBody');
    if (!body) return;
    const rows = state.wmosHistory.slice().sort((a, b) => b.timestamp - a.timestamp).slice(0, 100);
    body.innerHTML = rows.length ? rows.map(row => {
        const time = new Date(row.timestamp).toLocaleTimeString('en-IN', { hour12: false });
        return '<tr class="border-b border-slate-100">' +
            '<td class="px-3 py-2 font-mono text-slate-600">' + time + '</td>' +
            '<td class="px-3 py-2 text-right font-bold text-slate-800">' + formatWmosValue('radiation', row.radiation) + '</td>' +
            '<td class="px-3 py-2 text-right font-bold text-slate-800">' + formatWmosValue('panelTemp', row.panelTemp) + '</td>' +
            '<td class="px-3 py-2 text-right font-bold text-slate-800">' + formatWmosValue('ambientTemp', row.ambientTemp) + '</td>' +
            '<td class="px-3 py-2 text-right font-bold text-slate-800">' + formatWmosValue('windSpeed', row.windSpeed) + '</td>' +
            '<td class="px-3 py-2 text-right font-bold text-slate-800">' + formatWmosValue('humidity', row.humidity) + '</td>' +
        '</tr>';
    }).join('') : '<tr><td colspan="6" class="px-3 py-8 text-center text-slate-500">Waiting for WMOS data...</td></tr>';
}

function updateCards() {
    const rows = Object.values(state.inverters).filter(inv => inv.lastSeen && Date.now() - inv.lastSeen <= 5000);
    if (!rows.length) {
        return;
    }

}

function renderMode() {
    const isWmos = selectedSource === 'wmos:all';
    inverterSection.classList.toggle('hidden', isWmos);
    wmosSection.classList.toggle('hidden', !isWmos);
    if (isWmos) {
        trendHeading.textContent = 'WMOS - Live Data';
        trendDescription.textContent = 'One common source showing all five live weather measurements. No WMOS graphs; use Download Live Excel for the received live samples.';
        renderWmos();
    } else {
        trendHeading.textContent = state.selectedInverter ? 'Inverter Live Data' : 'Live Source Trend';
        trendDescription.textContent = 'Choose an inverter or the single common WMOS source.';
        renderInverter();
    }
}

function updateGenerateButton() {
    generateButton.disabled = !selectedSource;
}

function seedConfiguredInverters() {
    // Make the separate Inverters group available immediately. These are
    // names only; all power/energy readings still come exclusively from SCADA.
    const count = Math.max(0, parseInt(cfg.inverter_count || 0, 10));
    for (let i = 1; i <= count; i++) ensureInverter('Inverter ' + i, 'inverter');
    populateInverterOptions();
}

function addDailyValue(value, time) {
    if (!value || typeof value !== 'object') return;
    consumeLiveMessage(value);
}

function requestDailyInverter() {
    if (!socket || socket.readyState !== WebSocket.OPEN || !state.selectedInverter) return;
    const inv = state.inverters[state.selectedInverter];
    socket.send(JSON.stringify({ type: 'get_daily_data', unit_id: wsUnitId, device: inv?.wsName || state.selectedInverter, date: todayKey() }));
}

function requestDailyWmos() {
    if (!socket || socket.readyState !== WebSocket.OPEN) return;
    Object.values(WMOS_DEVICES).forEach(src => {
        socket.send(JSON.stringify({ type: 'get_daily_data', unit_id: wsUnitId, task: 'WMOS', device: src.device, date: todayKey() }));
    });
}

function waitForExportData(test, timeout = 3500) {
    return new Promise(resolve => {
        if (test()) return resolve(true);
        const start = Date.now();
        const timer = setInterval(() => {
            if (test()) {
                clearInterval(timer);
                resolve(true);
            } else if (Date.now() - start >= timeout) {
                clearInterval(timer);
                resolve(false);
            }
        }, 100);
    });
}

function xlsxWorkbook(sheetName, rows, summary) {
    if (!rows.length || !window.XLSX) return false;
    const wb = XLSX.utils.book_new();
    const summarySheet = XLSX.utils.json_to_sheet(summary);
    summarySheet['!cols'] = [{ wch: 24 }, { wch: 42 }];
    XLSX.utils.book_append_sheet(wb, summarySheet, 'Summary');
    const sheet = XLSX.utils.json_to_sheet(rows);
    sheet['!cols'] = Object.keys(rows[0]).map(k => ({ wch: Math.min(32, Math.max(12, k.length + 2)) }));
    XLSX.utils.book_append_sheet(wb, sheet, sheetName);
    XLSX.writeFile(wb, currentPlant + '_' + sheetName.replace(/[^a-z0-9]+/ig, '_') + '_' + todayKey() + '.xlsx');
    return true;
}

function exportInverterExcel() {
    if (!state.selectedInverter) return false;
    const rows = selectedInverterHistory().sort((a, b) => a.timestamp - b.timestamp).map(row => {
        const output = row.powerKw == null ? '' : Number(row.powerKw).toFixed(3);
        const daily = row.dailyKwh == null ? '' : Number(row.dailyKwh).toFixed(3);
        return {
            Date: new Date(row.timestamp).toLocaleDateString('en-IN'),
            Time: new Date(row.timestamp).toLocaleTimeString('en-IN', { hour12: false }),
            Inverter: row.device,
            'Output (kW)': output,
            'Daily Energy (kWh)': daily,
            'Sample Status': 'Live WebSocket sample'
        };
    });
    const summary = [
        { Field: 'Plant', Value: cfg.name || currentPlant },
        { Field: 'Selected Source', Value: state.inverters[state.selectedInverter]?.wsName || state.selectedInverter },
        { Field: 'Export Type', Value: 'Live WebSocket data received by Analytics' },
        { Field: 'Actual Samples', Value: rows.length },
        { Field: 'Generated At', Value: new Date().toLocaleString('en-IN', { hour12: false }) }
    ];
    return xlsxWorkbook('Inverter Live Data', rows, summary);
}

async function exportWmosExcel() {
    try {
        const response = await fetch('analytics_data.php?plant=' + encodeURIComponent(currentPlant) + '&date=' + encodeURIComponent(todayKey()) + '&export=1', { cache: 'no-store', credentials: 'same-origin' });
        if (response.ok) {
            const payload = await response.json();
            if (payload?.success && Array.isArray(payload.rows) && payload.rows.length) {
                mergeStoredWmosRows(payload.rows);
            }
        }
    } catch (_) {}
    const sourceRows = state.wmosHistory.slice().sort((a, b) => a.timestamp - b.timestamp);
    if (!sourceRows.length) return false;
    const rows = sourceRows.map(row => ({
        Date: new Date(row.timestamp).toLocaleDateString('en-IN'),
        Time: new Date(row.timestamp).toLocaleTimeString('en-IN', { hour12: false }),
        'Radiation (W/m²)': row.radiation ?? '',
        'Panel Temperature (°C)': row.panelTemp ?? '',
        'Ambient Temperature (°C)': row.ambientTemp ?? '',
        'Wind Speed (m/s)': row.windSpeed ?? '',
        'Humidity (%RH)': row.humidity ?? '',
        'Radiation Device': row.radiation_device ?? '',
        'Panel Device': row.panelTemp_device ?? '',
        'Ambient Device': row.ambientTemp_device ?? '',
        'Wind Device': row.windSpeed_device ?? '',
        'Humidity Device': row.humidity_device ?? '',
        'Reading Type': 'Actual live WebSocket samples merged by minute'
    }));
    const summary = [
        { Field: 'Plant', Value: cfg.name || currentPlant },
        { Field: 'Selected Source', Value: 'WMOS - All Weather Data' },
        { Field: 'Export Type', Value: 'Live WebSocket WMOS data received by Analytics' },
        { Field: 'Actual Weather Rows', Value: rows.length },
        { Field: 'Metrics', Value: 'Radiation, Panel Temp, Ambient Temp, Wind Speed, Humidity' },
        { Field: 'Generated At', Value: new Date().toLocaleString('en-IN', { hour12: false }) }
    ];
    return xlsxWorkbook('WMOS All Live Data', rows, summary);
}

async function downloadSelectedExcel() {
    if (!selectedSource) return;
    const old = generateButton.innerHTML;
    generateButton.disabled = true;
    generateButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Preparing live data...</span>';
    try {
        if (selectedSource === 'wmos:all') {
            requestDailyWmos();
            await waitForExportData(() => state.wmosHistory.length > 0);
            if (!(await exportWmosExcel())) alert('No live WMOS samples are available yet.');
        } else {
            state.selectedInverter = selectedSource;
            requestDailyInverter();
            await waitForExportData(() => selectedInverterHistory().length > 0);
            if (!exportInverterExcel()) alert('No live inverter samples are available yet.');
        }
    } finally {
        generateButton.innerHTML = old;
        updateGenerateButton();
    }
}

sourceSelect.addEventListener('change', () => {
    selectedSource = sourceSelect.value || '';
    if (selectedSource === 'wmos:all') {
        state.selectedInverter = '';
        requestDailyWmos();
    } else {
        state.selectedInverter = selectedSource;
        requestDailyInverter();
    }
    renderMode();
    updateGenerateButton();
});

generateButton.addEventListener('click', downloadSelectedExcel);

function connectWebSocket() {
    if (socket) {
        try { socket.close(); } catch (_) {}
    }
    socket = new WebSocket(WS_URL);
    socket.onopen = () => {
        document.getElementById('liveDot').className = 'w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse';
        document.getElementById('liveStatus').textContent = 'Live telemetry';
        socket.send(JSON.stringify({ type: 'subscribe', unit_id: wsUnitId }));
        socket.send(JSON.stringify({ type: 'get_devices', unit_id: wsUnitId }));
        if (state.selectedInverter) requestDailyInverter();
        if (selectedSource === 'wmos:all') requestDailyWmos();
    };
    socket.onmessage = event => {
        try {
            const message = JSON.parse(event.data);
            consumeLiveMessage(message);
            populateInverterOptions();
        } catch (_) {}
    };
    socket.onclose = () => {
        document.getElementById('liveDot').className = 'w-2.5 h-2.5 rounded-full bg-red-500';
        document.getElementById('liveStatus').textContent = 'Reconnecting...';
        clearTimeout(reconnectTimer);
        reconnectTimer = setTimeout(connectWebSocket, 2500);
    };
    socket.onerror = () => {};
}

function refreshLiveStatus() {
    const age = state.wmos.lastReceivedAt ? Date.now() - state.wmos.lastReceivedAt : Infinity;
    if (!socket || socket.readyState !== WebSocket.OPEN) {
        document.getElementById('liveDot').className = 'w-2.5 h-2.5 rounded-full bg-red-500';
        document.getElementById('liveStatus').textContent = 'WebSocket disconnected';
        return;
    }
    if (selectedSource === 'wmos:all' && age <= 5000) {
        document.getElementById('liveDot').className = 'w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse';
        document.getElementById('liveStatus').textContent = 'Live WMOS telemetry';
    } else if (selectedSource === 'wmos:all' && age > 15000) {
        document.getElementById('liveDot').className = 'w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse';
        document.getElementById('liveStatus').textContent = 'WMOS telemetry delayed';
    } else {
        document.getElementById('liveDot').className = 'w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse';
        document.getElementById('liveStatus').textContent = 'Live telemetry';
    }
}

function updateAll() {
    document.getElementById('clockDisplay').textContent = new Date().toLocaleTimeString('en-IN', { hour12: false });
    updateCards();
    if (selectedSource === 'wmos:all') renderWmos(); else renderInverter();
    refreshLiveStatus();
}

initCharts();
seedConfiguredInverters();
loadStoredWmosData();
renderMode();
connectWebSocket();
setInterval(loadStoredWmosData, 5000);
setInterval(updateAll, 1000);
</script>
</body>
</html>
