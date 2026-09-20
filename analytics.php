<?php
require 'check_auth.php';
require_once __DIR__ . '/config.php';
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

// Handle AJAX request for database data export
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_export_data') {
    // Ensure clean JSON output - no whitespace before headers
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    
    $device = isset($_GET['device']) ? trim($conn->real_escape_string($_GET['device'])) : '';
    $date = isset($_GET['date']) ? $conn->real_escape_string($_GET['date']) : date('Y-m-d');
    
    // Verify database connection
    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
        echo json_encode(['success' => false, 'error' => 'Database connection unavailable']);
        exit(0);
    }
    
    if (empty($device)) {
        echo json_encode(['success' => false, 'error' => 'Device parameter required']);
        exit(0);
    }
    
    // Plant access control
    $userRole = $user['role'] ?? '';
    $userPlant = $user['plant_id'] ?? '';
    $plantFilter = $currentPlant;
    if ($userRole !== 'admin') {
        $plantFilter = $userPlant;
    }
    $plantClause = ($plantFilter !== 'all' && $plantFilter !== '') ? " AND plant_id = '$plantFilter'" : "";
    
    try {
        $data = [];
        
        // Debug logging
        error_log("Analytics Export: device=$device, date=$date, plant=$plantFilter");
        
        if ($device === 'wmos') {
            // Query WMOS/Weather data - ALL 5 values per second
            // Try without plant filter first to see if data exists
            $sql = "SELECT 
                        recorded_at,
                        radiation,
                        panel_temp,
                        ambient_temp,
                        wind_speed,
                        humidity
                    FROM weather_readings 
                    WHERE DATE(recorded_at) = '$date' 
                      AND TIME(recorded_at) BETWEEN '05:00:00' AND '20:00:00'
                    ORDER BY recorded_at ASC
                    LIMIT 10000";
            
            $result = $conn->query($sql);
            
            if ($result === false) {
                throw new Exception("Query failed: " . $conn->error);
            }
            
            error_log("Analytics Export: weather_readings query returned " . $result->num_rows . " rows");
            
            // Fallback to wms_readings if weather_readings is empty
            if ($result && $result->num_rows === 0) {
                $sql = "SELECT 
                            recorded_at,
                            radiation,
                            panel_temp,
                            ambient_temp,
                            wind_speed,
                            humidity
                        FROM wms_readings 
                        WHERE DATE(recorded_at) = '$date' 
                          AND TIME(recorded_at) BETWEEN '05:00:00' AND '20:00:00'
                        ORDER BY recorded_at ASC
                        LIMIT 10000";
                $result = $conn->query($sql);
                
                if ($result === false) {
                    throw new Exception("Fallback query failed: " . $conn->error);
                }
                
                error_log("Analytics Export: wms_readings fallback query returned " . $result->num_rows . " rows");
            }
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        'timestamp' => strtotime($row['recorded_at']) * 1000, // milliseconds
                        'recorded_at' => $row['recorded_at'],
                        'radiation' => $row['radiation'] !== null ? (float)$row['radiation'] : null,
                        'panel_temp' => $row['panel_temp'] !== null ? (float)$row['panel_temp'] : null,
                        'ambient_temp' => $row['ambient_temp'] !== null ? (float)$row['ambient_temp'] : null,
                        'wind_speed' => $row['wind_speed'] !== null ? (float)$row['wind_speed'] : null,
                        'humidity' => $row['humidity'] !== null ? (float)$row['humidity'] : null
                    ];
                }
            }
            
        } else {
            // Query Inverter data - all columns per second
            $sql = "SELECT 
                        recorded_at,
                        device_name,
                        ac_active_power,
                        daily_generation,
                        internal_temp,
                        dc_voltage,
                        dc_current,
                        ac_voltage,
                        ac_current,
                        frequency
                    FROM inverter_readings 
                    WHERE DATE(recorded_at) = '$date' 
                      AND device_name = '$device'
                      AND TIME(recorded_at) BETWEEN '05:00:00' AND '20:00:00'
                    ORDER BY recorded_at ASC
                    LIMIT 10000";
            
            $result = $conn->query($sql);
            
            if ($result === false) {
                throw new Exception("Inverter query failed: " . $conn->error);
            }
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        'timestamp' => strtotime($row['recorded_at']) * 1000, // milliseconds
                        'recorded_at' => $row['recorded_at'],
                        'device' => $row['device_name'],
                        'powerKw' => $row['ac_active_power'] !== null ? (float)$row['ac_active_power'] : null,
                        'dailyKwh' => $row['daily_generation'] !== null ? (float)$row['daily_generation'] : null,
                        'temp' => $row['internal_temp'] !== null ? (float)$row['internal_temp'] : null,
                        'dcVoltage' => $row['dc_voltage'] !== null ? (float)$row['dc_voltage'] : null,
                        'dcCurrent' => $row['dc_current'] !== null ? (float)$row['dc_current'] : null,
                        'acVoltage' => $row['ac_voltage'] !== null ? (float)$row['ac_voltage'] : null,
                        'acCurrent' => $row['ac_current'] !== null ? (float)$row['ac_current'] : null,
                        'frequency' => $row['frequency'] !== null ? (float)$row['frequency'] : null
                    ];
                }
            }
        }
        
        $response = [
            'success' => true,
            'device' => $device,
            'date' => $date,
            'plant' => $plantFilter,
            'rowCount' => count($data),
            'data' => $data
        ];
        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'error' => 'Database query failed: ' . $e->getMessage()
        ];
        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    
    // Ensure clean exit with no additional output
    exit(0);
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
                        <p id="trendDescription" class="text-xs text-slate-500 mt-1">Choose an inverter or the single common WMOS / WMAS source.</p>
                    </div>
                    <div class="ml-auto flex flex-wrap items-end gap-2 w-full xl:w-auto">
                        <label class="text-xs font-bold text-slate-500 min-w-[260px]">
                            <span class="block mb-1">Live Data Source</span>
                            <select id="analyticsSourceSelect" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <option value="">Select Inverter / WMOS</option>
                                <optgroup id="inverterGroup" label="Inverters"></optgroup>
                                <option value="wmos:all">WMOS / WMAS - All Weather Data</option>
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
                        <h2 class="text-lg font-black text-slate-900">WMOS / WMAS - All Live Weather Data</h2>
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
                        <p class="mt-2 text-2xl font-black text-slate-900"><span id="wmRad">--</span> <span class="text-xs font-bold text-slate-500">W/m�</span></p>
                        <p class="text-[10px] text-slate-500 mt-1">Pyranometer / raw data</p>
                    </div>
                    <div class="rounded-xl border border-orange-100 bg-orange-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-orange-600">Panel Temp</p>
                        <p class="mt-2 text-2xl font-black text-slate-900"><span id="wmPanel">--</span> <span class="text-xs font-bold text-slate-500">�C</span></p>
                        <p class="text-[10px] text-slate-500 mt-1">Panel temperature device</p>
                    </div>
                    <div class="rounded-xl border border-sky-100 bg-sky-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-sky-600">Amb Temp</p>
                        <p class="mt-2 text-2xl font-black text-slate-900"><span id="wmAmbient">--</span> <span class="text-xs font-bold text-slate-500">�C</span></p>
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
                    <p class="text-xs font-bold text-slate-600">Live WMOS / WMAS values only.</p>
                    <p class="text-[11px] text-slate-500 mt-1">The five readings above update from the selected plant's live WebSocket telemetry. Download Live Excel exports the received WMOS / WMAS samples.</p>
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
    radiation: { label: 'Radiation', unit: 'W/m�', decimals: 0, device: 'Pyranometer', key: 'raw data' },
    panelTemp: { label: 'Panel Temp', unit: '�C', decimals: 1, device: 'pannel temperature', key: 'pannel temperature' },
    ambientTemp: { label: 'Amb Temp', unit: '�C', decimals: 1, device: 'Ambient Temperature', key: 'Ambient temperature' },
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

function isWithinReportingHours(ts) {
    const d = new Date(ts);
    const hour = d.getHours();
    return hour >= 5 && hour < 20; // 5 AM to 8 PM (20:00 = 8 PM)
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

function hasWeatherTask(task) {
    return /^(wmos|wmas|weather)$/i.test(String(task || '').trim());
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
    const timestamp = time.getTime();
    
    // Only store data within reporting hours (5 AM to 8 PM)
    if (!isWithinReportingHours(timestamp)) return;
    
    const sample = {
        timestamp: timestamp,
        powerKw: reading.power,
        dailyKwh: reading.daily,
        device: state.inverters[key].wsName,
        values: cloneValues(values)
    };
    state.inverters[key].outputKw = reading.power ?? state.inverters[key].outputKw;
    state.inverters[key].dailyGen = reading.daily ?? state.inverters[key].dailyGen;
    state.inverters[key].lastSeen = timestamp;
    state.inverters[key].status = 'Live';
    if (!state.inverterHistory[key]) state.inverterHistory[key] = [];
    const arr = state.inverterHistory[key];
    
    // Store every second's data - no merging, just check for exact duplicate timestamp
    const idx = arr.findIndex(row => row.timestamp === sample.timestamp);
    if (idx >= 0) arr[idx] = sample; else arr.push(sample);
    arr.sort((a, b) => a.timestamp - b.timestamp);
    
    // Keep more samples for second-level data throughout the day
    if (arr.length > 54000) arr.splice(0, arr.length - 54000); // 15 hours * 60 min * 60 sec = 54000 samples
}

function mergeWmosSample(metric, value, sourceTime, device) {
    const time = telemetryDate(sourceTime);
    const timestamp = time.getTime();
    
    // Only store data within reporting hours (5 AM to 8 PM)
    if (!isWithinReportingHours(timestamp)) return;
    
    state.wmos[metric] = value;
    state.wmos.lastReceivedAt = Date.now();
    state.wmos.lastSampleAt = timestamp;
    
    // Store every second's data - no minute merging
    let row = state.wmosHistory.find(item => item.timestamp === timestamp);
    if (!row) {
        row = { timestamp: timestamp };
        state.wmosHistory.push(row);
    }
    row[metric] = value;
    row[metric + '_device'] = device || WMOS_DEVICES[metric].device;
    
    state.wmosHistory.sort((a, b) => a.timestamp - b.timestamp);
    
    // Keep more samples for second-level data throughout the day
    if (state.wmosHistory.length > 54000) state.wmosHistory.splice(0, state.wmosHistory.length - 54000); // 15 hours * 60 min * 60 sec
}

function consumeWmosFrame(values, device, task, sourceTime) {
    if (!hasWeatherTask(task)) return false;
    const metric = deviceToWmosMetric(device);
    if (!metric) return false;
    const value = exactWmosValue(metric, values);
    if (metric === 'panelTemp' && value === null) {
        state.wmos.panelTemp = null;
        state.wmos.lastReceivedAt = Date.now();
        state.wmos.lastSampleAt = telemetryDate(sourceTime).getTime();
        return true;
    }
    if (value === null) return false;
    mergeWmosSample(metric, value, sourceTime, device);
    return true;
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
        if (hasWeatherTask(task)) consumeWmosFrame(node.values, device, task, time);
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

    // Handle daily_data_result - this is historical data from database
    if (message.type === 'daily_data_result') {
        console.log('?? RAW daily_data_result received:', {
            type: message.type,
            device: device,
            task: task,
            dataLength: Array.isArray(message.data) ? message.data.length : 0,
            fullMessage: message
        });
        
        const rows = Array.isArray(message.data) ? message.data : [];
        console.log('?? Processing', rows.length, 'rows from daily_data_result');
        
        rows.forEach((row, index) => {
            if (!row || typeof row !== 'object') return;
            const rowDevice = row.device || row.deviceName || row.sensor || row.name || device;
            const rowTask = row.task || row.pageName || task;
            const rowTime = row.time || row.timestamp || row.ts || row.recorded_at || '';
            const rowValues = row.values && typeof row.values === 'object' && !Array.isArray(row.values) ? row.values : {};
            
            if (index < 5) {
                console.log('?? Sample row', index, ':', {
                    device: rowDevice,
                    task: rowTask,
                    time: rowTime,
                    values: rowValues
                });
            }
            
            if (rowValues && Object.keys(rowValues).length > 0) {
                if (hasWeatherTask(rowTask)) {
                    consumeWmosFrame(rowValues, rowDevice, rowTask, rowTime);
                } else if (isInverterDevice(rowDevice, rowTask) || /^inverter$/i.test(String(rowTask || ''))) {
                    addInverterSample(rowDevice, rowValues, rowTime, rowTask);
                }
            }
        });
        
        console.log('?? After processing - Inverter history:', Object.keys(state.inverterHistory).map(k => ({
            inverter: k,
            samples: state.inverterHistory[k].length
        })));
        console.log('?? After processing - WMOS history samples:', state.wmosHistory.length);
        return;
    }

    if (message.values && typeof message.values === 'object' && !Array.isArray(message.values)) {
        if (hasWeatherTask(task)) consumeWmosFrame(message.values, device, task, time);
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
        trendHeading.textContent = 'WMOS / WMAS - Live Data';
        trendDescription.textContent = 'One common source showing all five live weather measurements. No WMOS graphs; use Download Live Excel for the received live samples.';
        renderWmos();
    } else {
        trendHeading.textContent = state.selectedInverter ? 'Inverter Live Data' : 'Live Source Trend';
        trendDescription.textContent = 'Choose an inverter or the single common WMOS / WMAS source.';
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
    const today = todayKey();
    const request = { 
        type: 'get_daily_data', 
        unit_id: wsUnitId, 
        device: inv?.wsName || state.selectedInverter, 
        date: today,
        start_time: '05:00:00',
        end_time: '20:00:00'
    };
    console.log('?? Requesting inverter daily data:', request);
    socket.send(JSON.stringify(request));
}

function requestDailyWmos() {
    if (!socket || socket.readyState !== WebSocket.OPEN) return;
    const today = todayKey();
    Object.values(WMOS_DEVICES).forEach(src => {
        const request = { 
            type: 'get_daily_data', 
            unit_id: wsUnitId, 
            task: 'WMOS', 
            device: src.device, 
            date: today,
            start_time: '05:00:00',
            end_time: '20:00:00'
        };
        console.log('?? Requesting WMOS daily data:', request);
        socket.send(JSON.stringify(request));
    });
}

function waitForExportData(test, timeout = 10000) {
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
        const d = new Date(row.timestamp);
        const output = row.powerKw == null ? '' : Number(row.powerKw).toFixed(3);
        const daily = row.dailyKwh == null ? '' : Number(row.dailyKwh).toFixed(3);
        return {
            Date: d.toLocaleDateString('en-IN'),
            Time: d.toLocaleTimeString('en-IN', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' }),
            Inverter: row.device,
            'Output (kW)': output,
            'Daily Energy (kWh)': daily,
            'Sample Status': 'Historical data (5 AM - 8 PM)'
        };
    });
    const summary = [
        { Field: 'Plant', Value: cfg.name || currentPlant },
        { Field: 'Selected Source', Value: state.inverters[state.selectedInverter]?.wsName || state.selectedInverter },
        { Field: 'Export Type', Value: 'Full day historical data from 5 AM to 8 PM' },
        { Field: 'Data Period', Value: '5:00 AM to 8:00 PM (Today)' },
        { Field: 'Actual Samples', Value: rows.length },
        { Field: 'Sample Frequency', Value: 'All available data points within reporting hours' },
        { Field: 'Generated At', Value: new Date().toLocaleString('en-IN', { hour12: false }) }
    ];
    return xlsxWorkbook('Inverter Live Data', rows, summary);
}

function exportWmosExcel() {
    const sourceRows = state.wmosHistory.slice().sort((a, b) => a.timestamp - b.timestamp);
    if (!sourceRows.length) return false;
    const rows = sourceRows.map(row => {
        const d = new Date(row.timestamp);
        return {
            Date: d.toLocaleDateString('en-IN'),
            Time: d.toLocaleTimeString('en-IN', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' }),
            'Radiation (W/m�)': row.radiation != null ? Number(row.radiation).toFixed(2) : '',
            'Panel Temperature (�C)': row.panelTemp != null ? Number(row.panelTemp).toFixed(2) : '',
            'Ambient Temperature (�C)': row.ambientTemp != null ? Number(row.ambientTemp).toFixed(2) : '',
            'Wind Speed (m/s)': row.windSpeed != null ? Number(row.windSpeed).toFixed(2) : '',
            'Humidity (%RH)': row.humidity != null ? Number(row.humidity).toFixed(2) : '',
            'Radiation Device': row.radiation_device ?? '',
            'Panel Device': row.panelTemp_device ?? '',
            'Ambient Device': row.ambientTemp_device ?? '',
            'Wind Device': row.windSpeed_device ?? '',
            'Humidity Device': row.humidity_device ?? '',
            'Reading Type': 'Historical data (5 AM - 8 PM)'
        };
    });
    const summary = [
        { Field: 'Plant', Value: cfg.name || currentPlant },
        { Field: 'Selected Source', Value: 'WMOS / WMAS - All Weather Data' },
        { Field: 'Export Type', Value: 'Full day historical WMOS/WMAS data from 5 AM to 8 PM' },
        { Field: 'Data Period', Value: '5:00 AM to 8:00 PM (Today)' },
        { Field: 'Actual Weather Samples', Value: rows.length },
        { Field: 'Metrics', Value: 'Radiation, Panel Temp, Ambient Temp, Wind Speed, Humidity' },
        { Field: 'Sample Frequency', Value: 'All available data points within reporting hours' },
        { Field: 'Generated At', Value: new Date().toLocaleString('en-IN', { hour12: false }) }
    ];
    return xlsxWorkbook('WMOS All Live Data', rows, summary);
}

async function downloadSelectedExcel() {
    if (!selectedSource) return;
    const old = generateButton.innerHTML;
    generateButton.disabled = true;
    generateButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Fetching data from database...</span>';
    
    try {
        const today = new Date().toISOString().split('T')[0]; // YYYY-MM-DD format
        
        // Determine device parameter
        const device = selectedSource === 'wmos:all' ? 'wmos' : selectedSource;
        
        console.log('?? Fetching database data for:', device, 'Date:', today);
        
        // Fetch data from internal database API
        const url = `analytics.php?ajax=get_export_data&device=${encodeURIComponent(device)}&date=${today}&plant=${currentPlant}`;
        console.log('?? API URL:', url);
        
        const response = await fetch(url);
        console.log('?? Response status:', response.status, response.statusText);
        
        // Check if response is ok
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Get response text first to debug
        const responseText = await response.text();
        console.log('?? Raw response text:', responseText.substring(0, 500));
        
        // Check if response is empty
        if (!responseText || responseText.trim() === '') {
            throw new Error('Empty response from server');
        }
        
        // Try to parse JSON
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('?? JSON Parse Error:', parseError);
            console.error('?? Response text:', responseText);
            throw new Error('Failed to parse JSON response: ' + parseError.message);
        }
        
        console.log('?? Database API response:', {
            success: result.success,
            device: result.device,
            rowCount: result.rowCount,
            hasData: !!result.data,
            dataLength: result.data ? result.data.length : 0,
            firstRow: result.data && result.data[0] ? result.data[0] : null,
            error: result.error || null
        });
        
        // ALERT for visibility
        alert(`Database query returned ${result.rowCount || 0} rows.\nCheck console for details.`);
        
        if (!result.success || !result.data || result.data.length === 0) {
            alert(`No data available for ${device} on ${today} (5 AM - 8 PM).\n${result.error || 'No records found in database.'}`);
            return;
        }
        
        // Populate history arrays from database data
        if (selectedSource === 'wmos:all') {
            state.wmosHistory = result.data.map(row => ({
                timestamp: row.timestamp,
                radiation: row.radiation,
                panelTemp: row.panel_temp,
                ambientTemp: row.ambient_temp,
                windSpeed: row.wind_speed,
                humidity: row.humidity,
                radiation_device: 'Database',
                panelTemp_device: 'Database',
                ambientTemp_device: 'Database',
                windSpeed_device: 'Database',
                humidity_device: 'Database'
            }));
            console.log('? WMOS data loaded from database:', state.wmosHistory.length, 'samples');
            console.log('?? First 3 WMOS rows:', state.wmosHistory.slice(0, 3));
            if (!exportWmosExcel()) {
                alert('Failed to generate WMOS Excel file.');
            } else {
                console.log('?? WMOS Excel generated successfully with', state.wmosHistory.length, 'rows!');
            }
        } else {
            state.selectedInverter = selectedSource;
            state.inverterHistory[state.selectedInverter] = result.data.map(row => ({
                timestamp: row.timestamp,
                device: row.device,
                powerKw: row.powerKw,
                dailyKwh: row.dailyKwh,
                temp: row.temp,
                values: {
                    dcVoltage: row.dcVoltage,
                    dcCurrent: row.dcCurrent,
                    acVoltage: row.acVoltage,
                    acCurrent: row.acCurrent,
                    frequency: row.frequency
                }
            }));
            console.log('? Inverter data loaded from database:', state.inverterHistory[state.selectedInverter].length, 'samples');
            console.log('?? First 3 inverter rows:', state.inverterHistory[state.selectedInverter].slice(0, 3));
            if (!exportInverterExcel()) {
                alert('Failed to generate inverter Excel file.');
            } else {
                console.log('?? Inverter Excel generated successfully with', state.inverterHistory[state.selectedInverter].length, 'rows!');
            }
        }
        
    } catch (error) {
        console.error('? Error fetching data:', error);
        alert('Failed to fetch data: ' + error.message);
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
            console.log('?? WebSocket message received:', {
                type: message.type,
                task: message.task || message.pageName,
                device: message.device || message.deviceName,
                hasData: !!message.data,
                dataIsArray: Array.isArray(message.data),
                dataLength: Array.isArray(message.data) ? message.data.length : 'N/A'
            });
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
renderMode();
connectWebSocket();
setInterval(updateAll, 1000);
</script>
</body>
</html>
