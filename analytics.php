<?php
require 'check_auth.php';
require 'config.php';

$plant = isset($_GET['plant']) ? $conn->real_escape_string($_GET['plant']) : 'vinoba-velliyanai';
$hist = [];
try {
    $check = $conn->query("SHOW TABLES LIKE 'telemetry_history'");
    if ($check && $check->num_rows > 0) {
        $res = $conn->query("SELECT metric_type, metric_value, recorded_at FROM telemetry_history WHERE plant_id='$plant' AND DATE(recorded_at)=CURDATE() AND metric_type='vcb_power' ORDER BY recorded_at ASC LIMIT 50");
        if ($res) while ($row = $res->fetch_assoc()) $hist[] = $row;
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title id="pageTitle">Solar Plant - Analytics & Inverter Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="sidebar-control.js?v=3" defer></script>
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
                <div class="flex items-center gap-3">
                    <button id="menuBtn" class="md:hidden text-emerald-600 text-2xl focus:outline-none">&#9776;</button>
                    <div>
                        <h2 class="text-xl font-black text-slate-800 tracking-tight">Plant Inverter Analytics</h2>
                        <p class="text-xs text-slate-500 hidden sm:block">Per-inverter telemetry, performance analysis & export</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100">
                        <div id="refreshPulse" class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.6)]"></div>
                        <span class="text-xs font-bold text-slate-600 tracking-widest hidden sm:inline" id="clockDisplay">--:--:--</span>
                    </div>
                </div>
            </header>

            <div class="p-4 sm:p-6 w-full flex flex-col gap-6 max-w-[1750px] mx-auto">
                <!-- Top Summary Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group hover:shadow-md transition">
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Combined Inverter Power</h3>
                        <p class="font-black text-slate-800 text-3xl" id="comb_power">0.00 <span class="text-sm font-bold text-blue-600">kW</span></p>
                        <p class="text-xs text-slate-500 font-medium mt-1">Live active sum</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group hover:shadow-md transition">
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Today Combined Yield</h3>
                        <p class="font-black text-slate-800 text-3xl" id="yield_val">0.00 <span class="text-sm font-bold text-purple-600">kWh</span></p>
                        <p class="text-xs text-slate-500 font-medium mt-1">Total generation today</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group hover:shadow-md transition">
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Inverter Availability</h3>
                        <p class="font-black text-slate-800 text-3xl" id="avail_val">100.0 <span class="text-sm font-bold text-emerald-600">%</span></p>
                        <p class="text-xs text-slate-500 font-medium mt-1" id="inv_active_count">0 / 0 online</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group hover:shadow-md transition">
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Capacity Factor (CUF)</h3>
                        <p class="font-black text-slate-800 text-3xl" id="perf_val">0.0 <span class="text-sm font-bold text-amber-600">%</span></p>
                        <p class="text-xs text-slate-500 font-medium mt-1">Plant 2.0 MWp</p>
                    </div>
                </div>

                <!-- Export & View Controls Bar -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 sm:p-5 flex flex-col md:flex-row gap-4 justify-between items-start md:items-center">
                    <div class="flex items-center gap-3 flex-wrap">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Plant:</span>
                        <select id="plantSwitcher" onchange="changePlant(this.value)" class="border border-slate-300 rounded-lg px-3 py-2 text-sm font-bold text-slate-800 bg-slate-50 focus:ring-2 focus:ring-emerald-500 outline-none cursor-pointer">
                            <option value="vinoba-velliyanai">Vinoba Velliyanai (2.0 MW)</option>
                            <option value="makkalpower">Makkal Power (2.0 MW)</option>
                            <option value="anushyam">Anushyam Plant (2.0 MW)</option>
                        </select>

                        <!-- Toggle Live Data vs Today Data -->
                        <div class="inline-flex rounded-lg border border-slate-200 p-1 bg-slate-100">
                            <button onclick="setViewMode('live')" id="btn-mode-live" class="px-3 py-1.5 text-xs font-bold rounded-md bg-white text-emerald-700 shadow-sm transition">
                                <i class="fa-solid fa-bolt mr-1"></i> Live Data
                            </button>
                            <button onclick="setViewMode('today')" id="btn-mode-today" class="px-3 py-1.5 text-xs font-semibold rounded-md text-slate-600 hover:text-slate-900 transition">
                                <i class="fa-solid fa-calendar-day mr-1"></i> Today Data
                            </button>
                        </div>
                    </div>

                    <!-- Download Buttons -->
                    <div class="flex items-center gap-2 w-full md:w-auto">
                        <button onclick="exportToExcel()" class="flex-1 md:flex-none bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition flex items-center justify-center gap-2 text-sm">
                            <i class="fa-solid fa-file-excel text-base"></i> Download Excel
                        </button>
                        <button onclick="exportToPDF()" class="flex-1 md:flex-none bg-rose-600 hover:bg-rose-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition flex items-center justify-center gap-2 text-sm">
                            <i class="fa-solid fa-file-pdf text-base"></i> Download PDF
                        </button>
                    </div>
                </div>

                <!-- Per-Inverter Analytics Table -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden" id="reportContent">
                    <div class="p-5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                                <i class="fa-solid fa-server text-emerald-600"></i>
                                <span id="tableTitle">Each Inverter Telemetry Analysis</span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">Real-time parameters for individual inverters</p>
                        </div>
                        <span class="text-xs font-bold text-slate-500 bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200" id="snapshotTime">Updated: --:--:--</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse" id="inverterTable">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-black text-slate-600 uppercase tracking-wider">
                                    <th class="p-3 pl-5">Inverter Name</th>
                                    <th class="p-3 text-right">AC Active Power (kW)</th>
                                    <th class="p-3 text-right">Today Generation (kWh)</th>
                                    <th class="p-3 text-center">Active PV Strings</th>
                                    <th class="p-3 text-right">AC Voltage (V)</th>
                                    <th class="p-3 text-right">Frequency (Hz)</th>
                                    <th class="p-3 text-center">Status</th>
                                    <th class="p-3 text-right pr-5">Last Telemetry</th>
                                </tr>
                            </thead>
                            <tbody id="inverterTableBody" class="divide-y divide-slate-100 text-xs">
                                <tr>
                                    <td colspan="8" class="p-8 text-center text-slate-400 italic">
                                        <div class="flex flex-col items-center justify-center">
                                            <div class="w-6 h-6 border-2 border-slate-200 border-t-emerald-600 rounded-full animate-spin mb-2"></div>
                                            Awaiting inverter telemetry...
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot id="inverterTableFoot" class="bg-slate-50 font-bold text-xs border-t-2 border-slate-200">
                                <!-- Totals row -->
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Plant Generation Output Chart -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Hourly Profile</p>
                            <h2 class="text-lg font-bold text-slate-900">Today Generation Curve (kW)</h2>
                        </div>
                    </div>
                    <div style="height:280px; min-height:280px;"><canvas id="analyticsChart"></canvas></div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        let currentPlant = urlParams.get('plant') || 'vinoba-velliyanai';
        const authToken = urlParams.get('token') || sessionStorage.getItem('vs_token') || '';
        const plantNames = { 'vinoba-velliyanai': 'Vinoba Velliyanai', 'makkalpower': 'Makkal Power', 'anushyam': 'Anushyam Plant' };

        document.getElementById('pageTitle').textContent = (plantNames[currentPlant] || currentPlant) + ' - Analytics';
        document.getElementById('plantSwitcher').value = currentPlant;

        setInterval(() => { document.getElementById('clockDisplay').innerText = new Date().toLocaleTimeString('en-IN', {hour12: false}); }, 1000);

        // Sidebar init
        fetch('sidebar.html', { cache: 'no-store' }).then(r => r.text()).then(html => {
            document.getElementById('sidebar-container').innerHTML = html;
            const _token = new URLSearchParams(window.location.search).get('token') || sessionStorage.getItem('vs_token') || '';
            document.querySelectorAll('#sidebarNav a').forEach(link => {
                let href = link.getAttribute('href');
                if (!href || href.indexOf('logout') !== -1) return;
                if (href.indexOf('?plant=') === -1) {
                    link.setAttribute('href', href + '?plant=' + encodeURIComponent(currentPlant) + '&token=' + encodeURIComponent(_token));
                } else if (href.indexOf('token=') === -1) {
                    link.setAttribute('href', href + '&token=' + encodeURIComponent(_token));
                }
            });
            const _pn = document.getElementById('sidebarPlantName');
            if (_pn) _pn.textContent = plantNames[currentPlant] || currentPlant;
            if (typeof initSidebar === 'function') initSidebar();
            const curPage = window.location.pathname.split('/').pop() || 'analytics.php';
            document.querySelectorAll('#sidebarNav a').forEach(link => {
                const dp = link.getAttribute('data-page');
                if (dp && (dp === curPage || dp.replace('.php','.html') === curPage)) {
                    link.classList.add('!bg-emerald-50', '!text-emerald-700', '!border-emerald-500');
                }
            });
            const overlay = document.getElementById('overlay'), sidebar = document.getElementById('sidebar');
            document.getElementById('menuBtn')?.addEventListener('click', () => { sidebar?.classList.remove('-translate-x-full'); overlay?.classList.remove('hidden'); });
            document.getElementById('closeSidebarBtn')?.addEventListener('click', () => { sidebar?.classList.add('-translate-x-full'); overlay?.classList.add('hidden'); });
            overlay?.addEventListener('click', () => { sidebar?.classList.add('-translate-x-full'); overlay.classList.add('hidden'); });
        });

        let viewMode = 'live'; // 'live' or 'today'
        function setViewMode(mode) {
            viewMode = mode;
            const btnLive = document.getElementById('btn-mode-live');
            const btnToday = document.getElementById('btn-mode-today');
            if (mode === 'live') {
                btnLive.className = "px-3 py-1.5 text-xs font-bold rounded-md bg-white text-emerald-700 shadow-sm transition";
                btnToday.className = "px-3 py-1.5 text-xs font-semibold rounded-md text-slate-600 hover:text-slate-900 transition";
                document.getElementById('tableTitle').textContent = "Each Inverter Live Data Analysis";
            } else {
                btnToday.className = "px-3 py-1.5 text-xs font-bold rounded-md bg-white text-emerald-700 shadow-sm transition";
                btnLive.className = "px-3 py-1.5 text-xs font-semibold rounded-md text-slate-600 hover:text-slate-900 transition";
                document.getElementById('tableTitle').textContent = "Each Inverter Today Cumulative Data";
            }
            renderTable();
        }

        function changePlant(p) {
            currentPlant = p;
            const newUrl = new URL(window.location.href);
            newUrl.searchParams.set('plant', p);
            window.history.pushState({}, '', newUrl);
            document.getElementById('pageTitle').textContent = (plantNames[p] || p) + ' - Analytics';
            const _pn = document.getElementById('sidebarPlantName');
            if (_pn) _pn.textContent = plantNames[p] || p;
            inverters = {};
            if (ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({ type: "subscribe", unit_id: currentPlant }));
            }
            renderTable();
        }

        // State storage for inverters
        let inverters = {};
        let vcbPower = 0;
        let vcbToday = 0;

        function renderTable() {
            const tbody = document.getElementById('inverterTableBody');
            const tfoot = document.getElementById('inverterTableFoot');
            const invNames = Object.keys(inverters).filter(k => !k.toLowerCase().includes('vcb') && !k.toLowerCase().includes('transformer'));
            invNames.sort();

            if (invNames.length === 0) {
                tbody.innerHTML = `<tr><td colspan="8" class="p-8 text-center text-slate-400 italic">No inverter telemetry received yet for ${plantNames[currentPlant]}.</td></tr>`;
                tfoot.innerHTML = '';
                return;
            }

            let totalKw = 0;
            let totalGen = 0;
            let totalActStr = 0;
            let totalStr = 0;
            let activeInvCount = 0;

            let rowsHtml = '';
            invNames.forEach(name => {
                const inv = inverters[name];
                const pwr = inv.power || 0;
                const gen = inv.dailyGen || 0;
                const actStr = inv.activeStrings || 0;
                const totStr = inv.totalStrings || 32;
                const volt = inv.voltage || 800;
                const freq = inv.freq || 50.0;
                const lastTime = inv.lastUpdate || '--:--:--';
                const isOnline = pwr > 0.05;

                totalKw += pwr;
                totalGen += gen;
                totalActStr += actStr;
                totalStr += totStr;
                if (isOnline) activeInvCount++;

                rowsHtml += `
                    <tr class="hover:bg-slate-50 transition">
                        <td class="p-3 pl-5 font-bold text-slate-800 flex items-center gap-2">
                            <i class="fa-solid fa-server text-slate-400"></i> ${name}
                        </td>
                        <td class="p-3 text-right font-mono font-bold ${pwr > 0 ? 'text-blue-600' : 'text-slate-400'}">${pwr.toFixed(2)}</td>
                        <td class="p-3 text-right font-mono font-bold text-purple-600">${gen.toFixed(2)}</td>
                        <td class="p-3 text-center">
                            <span class="inline-block px-2 py-0.5 rounded font-mono text-[11px] font-bold ${actStr > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500'}">
                                ${actStr} / ${totStr}
                            </span>
                        </td>
                        <td class="p-3 text-right font-mono text-slate-600">${volt.toFixed(1)}</td>
                        <td class="p-3 text-right font-mono text-slate-600">${freq.toFixed(2)}</td>
                        <td class="p-3 text-center">
                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-black uppercase ${isOnline ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-slate-100 text-slate-500'}">
                                ${isOnline ? 'Active' : 'Standby'}
                            </span>
                        </td>
                        <td class="p-3 text-right pr-5 font-mono text-[11px] text-slate-500">${lastTime}</td>
                    </tr>
                `;
            });

            tbody.innerHTML = rowsHtml;

            // Summary row in tfoot
            tfoot.innerHTML = `
                <tr>
                    <td class="p-3 pl-5 font-black text-slate-800 uppercase">Total (${invNames.length} Inverters)</td>
                    <td class="p-3 text-right font-mono font-black text-blue-700 text-sm">${totalKw.toFixed(2)} kW</td>
                    <td class="p-3 text-right font-mono font-black text-purple-700 text-sm">${totalGen.toFixed(2)} kWh</td>
                    <td class="p-3 text-center font-mono font-bold text-slate-700">${totalActStr} / ${totalStr}</td>
                    <td class="p-3 text-right text-slate-400 font-normal">--</td>
                    <td class="p-3 text-right text-slate-400 font-normal">--</td>
                    <td class="p-3 text-center font-bold text-emerald-700">${activeInvCount} / ${invNames.length} Active</td>
                    <td class="p-3 text-right pr-5 text-slate-400 font-normal">Live</td>
                </tr>
            `;

            // Update Top Summary Cards
            document.getElementById('comb_power').innerHTML = totalKw.toFixed(2) + ' <span class="text-sm font-bold text-blue-600">kW</span>';
            const effectiveYield = vcbToday > 0 ? vcbToday : totalGen;
            document.getElementById('yield_val').innerHTML = effectiveYield.toFixed(2) + ' <span class="text-sm font-bold text-purple-600">kWh</span>';
            const availPct = invNames.length > 0 ? ((activeInvCount / invNames.length) * 100) : 0;
            document.getElementById('avail_val').innerHTML = availPct.toFixed(1) + ' <span class="text-sm font-bold text-emerald-600">%</span>';
            document.getElementById('inv_active_count').textContent = `${activeInvCount} / ${invNames.length} online`;
            const cuf = (totalKw / 2000) * 100;
            document.getElementById('perf_val').innerHTML = cuf.toFixed(1) + ' <span class="text-sm font-bold text-amber-600">%</span>';
            document.getElementById('snapshotTime').textContent = 'Updated: ' + new Date().toLocaleTimeString('en-IN');
        }

        // Export to Excel function using SheetJS
        function exportToExcel() {
            const invNames = Object.keys(inverters).filter(k => !k.toLowerCase().includes('vcb') && !k.toLowerCase().includes('transformer'));
            if (invNames.length === 0) {
                alert('No inverter data available to export.');
                return;
            }

            const rows = [
                ['Plant Name', plantNames[currentPlant] || currentPlant],
                ['Report Type', viewMode === 'live' ? 'Inverter Live Telemetry Snapshot' : 'Inverter Today Cumulative Data'],
                ['Export Timestamp', new Date().toLocaleString('en-IN')],
                [],
                ['Inverter Name', 'AC Active Power (kW)', 'Today Generation (kWh)', 'Active Strings', 'Total Strings', 'AC Voltage (V)', 'Frequency (Hz)', 'Status', 'Timestamp']
            ];

            let sumKw = 0, sumGen = 0;
            invNames.sort().forEach(name => {
                const inv = inverters[name];
                const pwr = inv.power || 0;
                const gen = inv.dailyGen || 0;
                sumKw += pwr;
                sumGen += gen;
                rows.push([
                    name,
                    pwr.toFixed(2),
                    gen.toFixed(2),
                    inv.activeStrings || 0,
                    inv.totalStrings || 32,
                    (inv.voltage || 800).toFixed(1),
                    (inv.freq || 50).toFixed(2),
                    pwr > 0.05 ? 'Active' : 'Standby',
                    inv.lastUpdate || ''
                ]);
            });

            rows.push([]);
            rows.push(['Total Combined', sumKw.toFixed(2), sumGen.toFixed(2)]);

            const ws = XLSX.utils.aoa_to_sheet(rows);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Inverter_Analysis");
            const filename = `${currentPlant}_inverter_analysis_${viewMode}_${new Date().toISOString().slice(0,10)}.xlsx`;
            XLSX.writeFile(wb, filename);
        }

        // Export to PDF function using jsPDF + AutoTable
        function exportToPDF() {
            const invNames = Object.keys(inverters).filter(k => !k.toLowerCase().includes('vcb') && !k.toLowerCase().includes('transformer'));
            if (invNames.length === 0) {
                alert('No inverter data available to export.');
                return;
            }

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'mm', 'a4');

            // Header Banner
            doc.setFillColor(16, 185, 129); // emerald
            doc.rect(0, 0, 210, 22, 'F');
            doc.setTextColor(255, 255, 255);
            doc.setFontSize(16);
            doc.setFont('helvetica', 'bold');
            doc.text((plantNames[currentPlant] || currentPlant).toUpperCase() + ' - SOLAR SCADA', 14, 12);
            doc.setFontSize(10);
            doc.setFont('helvetica', 'normal');
            doc.text('Each Inverter Telemetry Analysis Report (' + (viewMode === 'live' ? 'Live Data' : 'Today Data') + ')', 14, 18);

            // Sub-details
            doc.setTextColor(50, 50, 50);
            doc.setFontSize(9);
            doc.text(`Generated: ${new Date().toLocaleString('en-IN')}`, 14, 28);
            doc.text(`Plant Capacity: 2.0 MWp | Location: Karur`, 14, 33);

            const tableBody = [];
            let sumKw = 0, sumGen = 0;
            invNames.sort().forEach(name => {
                const inv = inverters[name];
                const pwr = inv.power || 0;
                const gen = inv.dailyGen || 0;
                sumKw += pwr;
                sumGen += gen;
                tableBody.push([
                    name,
                    pwr.toFixed(2),
                    gen.toFixed(2),
                    `${inv.activeStrings || 0} / ${inv.totalStrings || 32}`,
                    (inv.voltage || 800).toFixed(1),
                    pwr > 0.05 ? 'Active' : 'Standby',
                    inv.lastUpdate || ''
                ]);
            });

            tableBody.push([
                'TOTAL COMBINED',
                sumKw.toFixed(2) + ' kW',
                sumGen.toFixed(2) + ' kWh',
                '--',
                '--',
                '--',
                '--'
            ]);

            doc.autoTable({
                startY: 38,
                head: [['Inverter', 'Power (kW)', 'Generation (kWh)', 'Strings', 'Voltage (V)', 'Status', 'Timestamp']],
                body: tableBody,
                theme: 'striped',
                headStyles: { fillColor: [15, 23, 42], textColor: [255, 255, 255], fontStyle: 'bold' },
                styles: { fontSize: 8.5, cellPadding: 2.5 },
                footStyles: { fillColor: [241, 245, 249], textColor: [15, 23, 42], fontStyle: 'bold' }
            });

            const filename = `${currentPlant}_inverter_analysis_${viewMode}_${new Date().toISOString().slice(0,10)}.pdf`;
            doc.save(filename);
        }

        // Chart Init
        const chartLabels = [];
        const chartValues = [];
        for (let h = 5; h <= 19; h++) {
            chartLabels.push(String(h).padStart(2, '0') + ':00');
            chartValues.push(0);
        }
        const ctx = document.getElementById('analyticsChart').getContext('2d');
        const analyticsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Combined Generation (kW)',
                    data: chartValues,
                    borderColor: '#059669',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { color: '#64748b' } },
                    x: { grid: { display: false }, ticks: { color: '#64748b' } }
                },
                plugins: { legend: { display: false } }
            }
        });

        // WebSocket Telemetry Client
        let ws;
        function connectWS() {
            ws = new WebSocket("wss://vinobasolar.scadahub.in:5001");
            ws.onopen = function() {
                document.getElementById('refreshPulse').className = 'w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.6)]';
                ws.send(JSON.stringify({ type: "subscribe", unit_id: currentPlant }));
            };
            ws.onmessage = function(e) {
                try {
                    const d = JSON.parse(e.data);
                    if (d.unit_id !== currentPlant) return;

                    const taskStr = d.task ? d.task.toString().toLowerCase() : '';
                    const devStr = d.device ? d.device.toString().toLowerCase() : '';

                    if (taskStr === 'vcb' || devStr.includes('vcb') || (d.values && d.values["3 Phase Active Power"] !== undefined)) {
                        if (d.values && d.values["3 Phase Active Power"] !== undefined) {
                            vcbPower = parseFloat(d.values["3 Phase Active Power"]) || 0;
                        }
                        if (d.virtualTags && d.virtualTags["vcb-today"] !== undefined) {
                            const vt = parseFloat(d.virtualTags["vcb-today"].value);
                            if (vt > 0) vcbToday = vt;
                        }
                    }

                    if (d.values && !(taskStr === 'vcb' || devStr.includes('vcb') || taskStr === 'transformer' || devStr.includes('transformer'))) {
                        const keys = Object.keys(d.values);
                        const hasInvPower = keys.some(pk => {
                            const pkl = pk.toLowerCase();
                            return (/power/.test(pkl) && /active|ac/.test(pkl) && !/reactive|apparent/.test(pkl));
                        });
                        const hasStrings = keys.some(k => /\d/.test(k) && /curr|current|amp/i.test(k) && !/phase|freq|temp/i.test(k));
                        if (taskStr === 'inverter' || hasInvPower || hasStrings) {
                            const devName = d.device || 'Inverter';
                            let activeCount = 0;
                            let totalCount = 0;
                            let pwr = 0;
                            let dgen = 0;
                            let volt = 800;
                            let freq = 50.0;

                            for (const k in d.values) {
                                const kl = k.toLowerCase();
                                if (/\b(curr|current|amp|i)\b/i.test(kl) && !/\b(volt|temp|freq|phase|total)\b/i.test(kl) && /\d/.test(k)) {
                                    totalCount++;
                                    if (parseFloat(d.values[k]) > 0.5) activeCount++;
                                }
                                if (/active.*power|ac.*power|power.*ac|a\.c\..*power/i.test(kl) && !/reactive|apparent|3.phase|limit|ratio/i.test(kl)) {
                                    pwr = parseFloat(d.values[k]) || 0;
                                }
                                if (/daily.*generation|daily.*gen|today.*gen/i.test(kl)) {
                                    dgen = parseFloat(d.values[k]) || 0;
                                }
                                if (/grid.*volt|ac.*volt|phase.*volt/i.test(kl)) {
                                    const v = parseFloat(d.values[k]);
                                    if (v > 100) volt = v;
                                }
                                if (/freq/i.test(kl)) {
                                    const f = parseFloat(d.values[k]);
                                    if (f > 40 && f < 65) freq = f;
                                }
                            }

                            if (!inverters[devName]) {
                                inverters[devName] = { power: 0, dailyGen: 0, activeStrings: 0, totalStrings: 32, voltage: 800, freq: 50.0, lastUpdate: '' };
                            }
                            if (pwr > 0 || !inverters[devName].power) inverters[devName].power = pwr;
                            if (dgen > (inverters[devName].dailyGen || 0)) inverters[devName].dailyGen = dgen;
                            if (totalCount > 0) {
                                inverters[devName].activeStrings = activeCount;
                                inverters[devName].totalStrings = totalCount;
                            }
                            inverters[devName].voltage = volt;
                            inverters[devName].freq = freq;
                            inverters[devName].lastUpdate = d.time || new Date().toLocaleTimeString('en-IN', {hour12: false});
                        }
                    }

                    renderTable();
                } catch(err) {
                    console.error("Analytics WS error:", err);
                }
            };
            ws.onclose = function() {
                document.getElementById('refreshPulse').className = 'w-2.5 h-2.5 bg-red-500 rounded-full';
                setTimeout(connectWS, 5000);
            };
        }
        connectWS();
    </script>
</body>
</html>
