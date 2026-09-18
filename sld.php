<?php require 'check_auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title id="pageTitle">4MW Solar Power Plant - Single Line Diagram (SLD)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="sidebar-control.js?v=3" defer></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f8fafc; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

        /* Engineering drawing sheet matching reference drawing */
        .sld-sheet {
            background: #ffffff;
            border: 2px solid #0f172a;
        }
        .section-row {
            border-bottom: 1.5px dashed #2563eb;
            position: relative;
        }
        .section-label {
            position: absolute; left: 8px; top: 8px;
            font-size: 11px; font-weight: 800; color: #1d4ed8;
            line-height: 1.25; z-index: 2;
        }
        .legend-tbl th, .legend-tbl td { border: 1px solid #64748b; padding: 2px 5px; font-size: 8.5px; }
        .details-tbl td { border: 1px solid #64748b; padding: 2px 6px; font-size: 8.5px; }
        .title-tbl td { border: 1px solid #0f172a; padding: 2px 6px; font-size: 8.5px; }
    </style>
</head>
<body class="h-full bg-slate-100 text-slate-900 font-sans">
    <div class="min-h-screen flex relative">
        <div id="overlay" class="fixed inset-0 bg-slate-900/40 hidden z-30 md:hidden transition-opacity"></div>
        <div id="sidebar-container"></div>
        <main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden min-h-screen bg-slate-100">
            <!-- Top Header -->
            <header class="bg-white p-3.5 sm:px-6 flex justify-between items-center sticky top-0 z-20 border-b border-slate-200 shadow-xs">
                <div class="flex items-center gap-3">
                    <button id="menuBtn" class="md:hidden text-emerald-700 text-2xl focus:outline-none">&#9776;</button>
                    <div>
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-diagram-project text-emerald-600 text-lg"></i>
                            <h2 class="text-lg font-black text-slate-900 tracking-tight">Single Line Diagram (SLD)</h2>
                        </div>
                        <p class="text-xs text-slate-500 hidden sm:block">Standard Electrical Schematic with Live SCADA Telemetry</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="inline-flex bg-slate-100 rounded-lg p-1 border border-slate-200 shadow-inner">
                        <button onclick="switchPlant('vinoba-velliyanai')" id="pill-vinoba" class="px-3 py-1.5 rounded-md text-xs font-bold transition">Vinoba Velliyanai</button>
                        <button onclick="switchPlant('makkalpower')" id="pill-makkal" class="px-3 py-1.5 rounded-md text-xs font-bold transition">Makkal Power</button>
                        <button onclick="switchPlant('anushyam')" id="pill-anushyam" class="px-3 py-1.5 rounded-md text-xs font-bold transition">Anushyam Plant</button>
                    </div>
                    <div class="flex items-center gap-2 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-200 text-xs">
                        <div id="refreshPulse" class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.8)]"></div>
                        <span class="font-bold text-slate-700 font-mono hidden sm:inline" id="clockDisplay">--:--:--</span>
                    </div>
                </div>
            </header>

            <!-- SLD Drawing Area -->
            <div class="p-3 sm:p-5 w-full flex flex-col gap-4 max-w-[1900px] mx-auto">
                <div class="sld-sheet rounded p-3 sm:p-4 overflow-x-auto">
                    <div class="min-w-[1350px] flex flex-col">

                        <!-- Title Bar (Red text matching reference drawing) -->
                        <div class="text-center pb-2 border-b-2 border-slate-900 mb-3">
                            <h1 class="text-xl sm:text-2xl font-black text-red-600 uppercase tracking-wide" id="sld_header_title">4MW SOLAR POWER PLANT</h1>
                            <h2 class="text-xs sm:text-sm font-black text-red-600 uppercase tracking-wider mt-0.5">SINGLE LINE DIAGRAM (SLD)</h2>
                        </div>

                        <!-- Main Grid: Left Schematic (col-span-9) + Right Info (col-span-3) -->
                        <div class="grid grid-cols-12 gap-3">

                            <!-- LEFT: SCHEMATIC -->
                            <div class="col-span-9 flex flex-col select-none pr-3 border-r border-slate-300">

                                <!-- SECTION 1: EB LINE 33kV -->
                                <div class="section-row py-2 min-h-[130px] flex items-center">
                                    <div class="section-label">1. EB LINE 33kV</div>
                                    <div class="w-full flex items-center justify-center">
                                        <div class="relative" style="width:520px;">
                                            <!-- EB Incoming Label -->
                                            <div class="text-center mb-1">
                                                <span class="text-xs font-black text-slate-900 uppercase block">EB LINE INCOMING</span>
                                                <span class="text-[10px] text-slate-700 font-mono font-bold">33kV, 50Hz</span>
                                            </div>
                                            <!-- Down arrow -->
                                            <div class="flex justify-center">
                                                <svg width="14" height="16"><path d="M7 0 L7 12 M3 8 L7 13 L11 8" stroke="#1e293b" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
                                            </div>
                                            <!-- Main vertical trunk with LA, PT on left and CT, MFM on right -->
                                            <div class="flex items-start justify-center gap-16 mt-1">
                                                <!-- Left: LA + PT -->
                                                <div class="flex items-end gap-5">
                                                    <!-- LA -->
                                                    <div class="flex flex-col items-center cursor-pointer" onclick="showInspect('la')">
                                                        <div class="w-9 h-9 border-2 border-slate-800 bg-white rounded flex items-center justify-center"><i class="fa-solid fa-bolt text-amber-600 text-xs"></i></div>
                                                        <div class="w-px h-2 bg-slate-800"></div>
                                                        <div class="flex flex-col items-center"><div class="w-3 h-px bg-slate-800"></div><div class="w-2 h-px bg-slate-800 mt-px"></div><div class="w-1 h-px bg-slate-800 mt-px"></div></div>
                                                        <span class="text-[8px] font-black text-slate-800 mt-0.5">LA</span>
                                                        <span class="text-[7px] text-slate-500 font-mono">33kV, 10kA</span>
                                                    </div>
                                                    <!-- PT -->
                                                    <div class="flex flex-col items-center cursor-pointer" onclick="showInspect('pt')">
                                                        <div class="w-9 h-9 border-2 border-slate-800 bg-white rounded-full flex items-center justify-center">
                                                            <svg width="20" height="20"><circle cx="7" cy="10" r="5" stroke="#1e293b" stroke-width="1.5" fill="none"/><circle cx="13" cy="10" r="5" stroke="#1e293b" stroke-width="1.5" fill="none"/></svg>
                                                        </div>
                                                        <div class="w-px h-2 bg-slate-800"></div>
                                                        <div class="flex flex-col items-center"><div class="w-3 h-px bg-slate-800"></div><div class="w-2 h-px bg-slate-800 mt-px"></div><div class="w-1 h-px bg-slate-800 mt-px"></div></div>
                                                        <span class="text-[8px] font-black text-slate-800 mt-0.5">PT</span>
                                                        <span class="text-[7px] text-slate-500 font-mono">33kV / 110V</span>
                                                    </div>
                                                    <!-- Horizontal line connecting to trunk -->
                                                    <svg width="30" height="2" class="self-start mt-4"><line x1="0" y1="1" x2="30" y2="1" stroke="#1e293b" stroke-width="2"/></svg>
                                                </div>

                                                <!-- Center: Vertical trunk with CT coil -->
                                                <div class="flex flex-col items-center">
                                                    <svg width="20" height="50">
                                                        <line x1="10" y1="0" x2="10" y2="50" stroke="#1e293b" stroke-width="2.5"/>
                                                        <path d="M5 15 C5 10, 15 10, 15 15 C15 20, 5 20, 5 25 C5 30, 15 30, 15 25" stroke="#1e293b" stroke-width="1.8" fill="none"/>
                                                    </svg>
                                                </div>

                                                <!-- Right: CT label + MFM -->
                                                <div class="flex items-center gap-2">
                                                    <svg width="30" height="2" class="mt-1"><line x1="0" y1="1" x2="30" y2="1" stroke="#1e293b" stroke-width="2"/></svg>
                                                    <div class="text-right mr-1">
                                                        <span class="text-[8px] font-black text-slate-800 block">CT</span>
                                                        <span class="text-[7px] text-slate-500 font-mono">33kV / 1A</span>
                                                    </div>
                                                    <div class="w-6 border-t border-dashed border-slate-600 relative"><i class="fa-solid fa-play text-[6px] text-slate-600 absolute -right-1 -top-1"></i></div>
                                                    <div class="border border-slate-800 bg-white rounded p-1.5 text-center cursor-pointer hover:border-cyan-600" onclick="showInspect('meter')">
                                                        <i class="fa-solid fa-gauge-high text-cyan-700 text-[10px]"></i>
                                                        <p class="text-[8px] font-black text-slate-900">MFM 33kV</p>
                                                        <p class="text-[7px] text-slate-600">(EB METER)</p>
                                                        <div class="mt-0.5 pt-0.5 border-t border-slate-200 text-[7px] font-mono">
                                                            <p class="text-cyan-800">Exp: <span id="sld_eb_exp">0.000 MWh</span></p>
                                                            <p class="text-amber-700">Imp: <span id="sld_eb_imp">0.000 MWh</span></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- SECTION 2: HT PANEL + VCB + TRANSFORMER -->
                                <div class="section-row py-2 min-h-[140px] flex items-center">
                                    <div class="section-label">2. HT PANEL<br><span class="text-[9px] text-slate-500">(33kV)</span></div>
                                    <div class="w-full flex items-center justify-center">
                                        <div class="flex flex-col items-center" style="width:520px;">
                                            <!-- VCB -->
                                            <div class="flex items-center gap-3">
                                                <div class="border-2 border-slate-800 bg-white rounded px-3 py-1.5 text-center cursor-pointer hover:border-emerald-600" onclick="showInspect('vcb')">
                                                    <div class="flex items-center gap-1 text-emerald-700"><i class="fa-solid fa-plug text-xs"></i><span class="text-[10px] font-black">VCB</span></div>
                                                    <p class="text-[8px] text-slate-600 font-mono">33kV, 1250A</p>
                                                    <p class="text-[7px] text-slate-500 font-mono">25kA</p>
                                                    <span id="vcb_status_badge" class="inline-block mt-0.5 px-1.5 py-px rounded text-[7px] font-black bg-emerald-100 text-emerald-800 border border-emerald-300 uppercase">CLOSED</span>
                                                </div>
                                                <!-- VCB Status to SCADA -->
                                                <div class="flex items-center gap-1">
                                                    <div class="w-6 border-t border-dashed border-slate-600 relative"><i class="fa-solid fa-play text-[6px] text-slate-600 absolute -right-1 -top-1"></i></div>
                                                    <div class="border border-dashed border-slate-700 bg-slate-50 px-2 py-1 rounded text-center">
                                                        <span class="text-[7px] font-black text-slate-800 block">VCB STATUS</span>
                                                        <span class="text-[6px] text-slate-500">(OPEN / CLOSE)</span>
                                                        <span class="text-[6px] font-bold text-emerald-700 block">TO SCADA</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Vertical line -->
                                            <svg width="4" height="12"><line x1="2" y1="0" x2="2" y2="12" stroke="#1e293b" stroke-width="2"/></svg>
                                            <!-- Transformer Symbol -->
                                            <div class="flex items-center gap-4 cursor-pointer" onclick="showInspect('transformer')">
                                                <div class="flex flex-col items-center relative">
                                                    <div class="w-11 h-11 rounded-full border-2 border-slate-800 bg-white flex items-center justify-center font-black text-sm text-slate-900">Δ</div>
                                                    <div class="w-11 h-11 rounded-full border-2 border-slate-800 bg-white flex items-center justify-center font-black text-sm text-slate-900 -mt-3">Y</div>
                                                    <!-- Ground -->
                                                    <div class="flex flex-col items-center mt-0.5"><div class="w-px h-1.5 bg-slate-800"></div><div class="w-3 h-px bg-slate-800"></div><div class="w-2 h-px bg-slate-800 mt-px"></div><div class="w-1 h-px bg-slate-800 mt-px"></div></div>
                                                </div>
                                                <div>
                                                    <p class="text-[10px] font-black text-slate-900 uppercase">POWER TRANSFORMER</p>
                                                    <p class="text-[9px] font-bold text-slate-700 font-mono">33kV / 800V</p>
                                                    <p class="text-[8px] text-slate-600 font-mono">4.5MVA, ONAN, Dyn11</p>
                                                    <div class="flex gap-2 mt-0.5 text-[8px] font-mono">
                                                        <span class="text-amber-700 font-bold">OTI: <b id="sld_oil_temp">-- °C</b></span>
                                                        <span class="text-amber-700 font-bold">WTI: <b id="sld_wti_temp">-- °C</b></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- SECTION 3: LT PANEL + ACB + BUSBAR -->
                                <div class="section-row py-2 min-h-[100px] flex flex-col items-center justify-center">
                                    <div class="section-label">3. LT PANEL<br><span class="text-[9px] text-slate-500">(800V)</span></div>
                                    <div class="flex items-center gap-3">
                                        <div class="border-2 border-slate-800 bg-white rounded px-3 py-1.5 text-center cursor-pointer hover:border-blue-600" onclick="showInspect('acb')">
                                            <div class="flex items-center gap-1 text-blue-700"><i class="fa-solid fa-shield-halved text-xs"></i><span class="text-[10px] font-black">ACB</span></div>
                                            <p class="text-[8px] text-slate-600 font-mono">800V, 4000A</p>
                                            <p class="text-[7px] text-slate-500 font-mono">50kA</p>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <div class="w-6 border-t border-dashed border-slate-600 relative"><i class="fa-solid fa-play text-[6px] text-slate-600 absolute -right-1 -top-1"></i></div>
                                            <div class="border border-dashed border-slate-700 bg-slate-50 px-2 py-1 rounded text-center">
                                                <span class="text-[7px] font-black text-slate-800 block">ACB STATUS</span>
                                                <span class="text-[6px] text-slate-500">(OPEN / CLOSE)</span>
                                        </div>
                                    </div>
                                    <!-- Vertical line to busbar -->
                                    <svg width="4" height="12"><line x1="2" y1="0" x2="2" y2="12" stroke="#0f172a" stroke-width="2.5"/></svg>
                                    <!-- 800V Busbar with label above -->
                                    <div class="w-full mt-0.5">
                                        <div class="text-center mb-0.5"><span class="text-[9px] font-black text-slate-900 font-mono tracking-wider uppercase">800V, 3Φ, 3W, 50Hz</span></div>
                                        <div class="w-full h-1.5 bg-slate-900"></div>
                                    </div>
                                </div>

                                <!-- SECTION 4 + 5: MCCB + INVERTERS + PV STRINGS -->
                                <div class="relative pt-2 pb-2 flex flex-col">
                                    <div class="flex items-center justify-between mb-1.5 px-2">
                                        <span class="text-xs font-black text-blue-700 tracking-tight" id="sld_mccb_header">4. MCCB TO INVERTERS</span>
                                        <span class="text-xs font-black text-blue-700 tracking-tight" id="sld_inv_header">5. INVERTERS (<span id="sld_inv_count_label">14</span> NOS)</span>
                                    </div>

                                    <!-- Dynamic inverter columns -->
                                    <div class="flex flex-wrap justify-center gap-3 text-center my-1" id="inverter_schematic_grid">
                                        <!-- Rendered by JS -->
                                    </div>

                                    <!-- Capacity Banner matching drawing -->
                                    <div class="mt-4 mx-auto border-2 border-blue-600 rounded bg-white px-8 py-2 text-center shadow-xs">
                                        <p class="text-xs font-black text-blue-700 uppercase tracking-wide" id="sld_banner_inv_count">14 Nos. STRING INVERTERS (800V AC OUTPUT)</p>
                                        <p class="text-xs font-black text-blue-700 uppercase tracking-wide mt-0.5">TOTAL PLANT CAPACITY : <span id="sld_capacity_text">4.0 MWp (DC) / APPROX 4.0 MW (AC)</span></p>
                                    </div>
                                </div>

                            </div>

                            <!-- RIGHT: LEGEND + PLANT DETAILS + TITLE BLOCK -->
                            <div class="col-span-3 flex flex-col gap-2.5 select-none pl-1">

                                <!-- LEGEND TABLE -->
                                <div class="border-2 border-slate-900 rounded overflow-hidden bg-white">
                                    <div class="bg-white border-b border-slate-900 text-blue-700 text-center py-1 font-black text-[11px] uppercase tracking-wider">LEGEND</div>
                                    <table class="w-full legend-tbl">
                                        <tbody>
                                            <tr><td class="font-bold text-center w-9">LA</td><td class="text-center w-7"><i class="fa-solid fa-bolt text-amber-600 text-[9px]"></i></td><td class="font-semibold text-slate-800">LIGHTNING ARRESTER</td></tr>
                                            <tr><td class="font-bold text-center">PT</td><td class="text-center font-bold text-sky-700">⚯</td><td class="font-semibold text-slate-800">POTENTIAL TRANSFORMER</td></tr>
                                            <tr><td class="font-bold text-center">CT</td><td class="text-center font-bold text-slate-700">➰</td><td class="font-semibold text-slate-800">CURRENT TRANSFORMER</td></tr>
                                            <tr><td class="font-bold text-center">MFM</td><td class="text-center"><i class="fa-solid fa-gauge-high text-cyan-700 text-[9px]"></i></td><td class="font-semibold text-slate-800">MULTI FUNCTION METER</td></tr>
                                            <tr><td class="font-bold text-center">VCB</td><td class="text-center"><i class="fa-solid fa-plug text-emerald-700 text-[9px]"></i></td><td class="font-semibold text-slate-800">VACUUM CIRCUIT BREAKER</td></tr>
                                            <tr><td class="font-bold text-center">ACB</td><td class="text-center"><i class="fa-solid fa-shield-halved text-blue-700 text-[9px]"></i></td><td class="font-semibold text-slate-800">AIR CIRCUIT BREAKER</td></tr>
                                            <tr><td class="font-bold text-center">⏚</td><td class="text-center">⏚</td><td class="font-semibold text-slate-800">EARTHING</td></tr>
                                            <tr><td class="font-bold text-center">MCCB</td><td class="text-center">⊘</td><td class="font-semibold text-slate-800">MOULDED CASE CIRCUIT BREAKER</td></tr>
                                            <tr><td class="font-bold text-center">INV</td><td class="text-center font-bold text-blue-700">∿</td><td class="font-semibold text-slate-800">STRING INVERTER</td></tr>
                                            <tr><td class="font-bold text-center">PV</td><td class="text-center"><i class="fa-solid fa-solar-panel text-amber-600 text-[9px]"></i></td><td class="font-semibold text-slate-800">PV STRINGS / MODULES</td></tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- PLANT DETAILS -->
                                <div class="border-2 border-slate-900 rounded overflow-hidden bg-white">
                                    <div class="bg-white border-b border-slate-900 text-blue-700 text-center py-1 font-black text-[11px] uppercase tracking-wider">PLANT DETAILS</div>
                                    <table class="w-full details-tbl">
                                        <tbody>
                                            <tr><td class="font-bold text-slate-600 uppercase w-28">Plant Capacity</td><td class="font-bold text-slate-900" id="plant_dt_capacity">4 MWp (DC) / ~4 MW (AC)</td></tr>
                                            <tr><td class="font-bold text-slate-600 uppercase">Grid Connection</td><td class="font-bold text-slate-900">33kV, 50Hz</td></tr>
                                            <tr><td class="font-bold text-slate-600 uppercase">Inverter Type</td><td class="font-bold text-slate-900">String Inverter</td></tr>
                                            <tr><td class="font-bold text-slate-600 uppercase">No. of Inverters</td><td class="font-bold text-blue-700 font-mono" id="plant_dt_invcount">14 NOS</td></tr>
                                            <tr><td class="font-bold text-slate-600 uppercase">Transformer</td><td class="font-bold text-slate-900 font-mono">33kV/800V, 4.5MVA</td></tr>
                                            <tr><td class="font-bold text-slate-600 uppercase">Transformer Type</td><td class="font-bold text-slate-900 font-mono">ONAN, Dyn11</td></tr>
                                            <tr><td class="font-bold text-slate-600 uppercase">LT System Voltage</td><td class="font-bold text-slate-900 font-mono">800V AC, 3Φ, 3W</td></tr>
                                            <tr><td class="font-bold text-slate-600 uppercase">Frequency</td><td class="font-bold text-slate-900 font-mono">50 Hz</td></tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- TITLE BLOCK -->
                                <div class="border-2 border-slate-900 rounded overflow-hidden bg-white">
                                    <div class="text-center py-1.5 px-2 border-b border-slate-900 bg-white">
                                        <span class="text-[9px] font-black text-blue-700 uppercase block tracking-wider">TITLE</span>
                                        <p class="text-xs font-black text-slate-900 uppercase mt-0.5" id="title_block_title">4MW SOLAR POWER PLANT</p>
                                        <p class="text-[10px] font-black text-red-600 uppercase tracking-wide">SINGLE LINE DIAGRAM (SLD)</p>
                                    </div>
                                    <table class="w-full title-tbl">
                                        <tbody>
                                            <tr><td class="font-bold text-slate-500 w-20">DRAWN BY</td><td class="font-black text-slate-900">NUCLEI TECH</td></tr>
                                            <tr><td class="font-bold text-slate-500">CHECKED BY</td><td class="font-black text-slate-900">NUCLEI TECH</td></tr>
                                            <tr><td class="font-bold text-slate-500">APPROVED BY</td><td class="font-black text-slate-900">NUCLEI TECH</td></tr>
                                            <tr><td class="font-bold text-slate-500">DATE</td><td class="font-mono text-slate-800" id="title_block_date"><?php echo date('d-m-Y'); ?></td></tr>
                                            <tr><td class="font-bold text-slate-500">DRAWING NO</td><td class="font-mono font-bold text-slate-900" id="title_block_dwg">NUC/SLD/01</td></tr>
                                            <tr><td class="font-bold text-slate-500">REV.</td><td class="font-mono text-slate-800">00</td></tr>
                                            <tr><td class="font-bold text-slate-500">SCALE</td><td class="font-mono text-slate-800">NTS</td></tr>
                                            <tr><td class="font-bold text-slate-500">SHEET</td><td class="font-mono font-bold text-slate-900">1 OF 1</td></tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- NUCLEI TECH Logo -->
                                <div class="border-2 border-slate-800 rounded p-2 flex items-center justify-between bg-white">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded bg-emerald-600 flex items-center justify-center text-white font-black text-xs"><i class="fa-solid fa-atom"></i></div>
                                        <div>
                                            <p class="text-[10px] font-black text-slate-900">NUCLEI TECH</p>
                                            <p class="text-[7px] font-bold text-emerald-700 uppercase">SMART SCADA SOLUTIONS</p>
                                        </div>
                                    </div>
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                </div>
                            </div>

                        </div>

                        <!-- BOTTOM: NOTES + SCADA ARCHITECTURE -->
                        <div class="mt-3 pt-2 border-t-2 border-slate-800 grid grid-cols-12 gap-3 text-[8.5px]">
                            <div class="col-span-5 text-slate-700 space-y-0.5">
                                <b class="text-slate-900 uppercase block font-black text-[9px]">NOTES :</b>
                                <p>1. ALL EQUIPMENTS SHALL BE AS PER RELEVANT IEC STANDARDS.</p>
                                <p>2. ALL RATINGS ARE INDICATIVE AND SUBJECT TO DETAILED ENGINEERING.</p>
                                <p>3. SCADA SYSTEM SHALL MONITOR ALL METERS, VCB STATUS, ACB STATUS, INVERTERS, TRANSFORMER PARAMETERS & WEATHER STATION.</p>
                            </div>
                            <div class="col-span-7 border-l border-slate-300 pl-3">
                                <b class="text-slate-900 uppercase block font-black text-[9px] text-center mb-1">SCADA / COMMUNICATION ARCHITECTURE</b>
                                <div class="flex items-center justify-between gap-1.5 text-center text-[8px] font-bold">
                                    <div class="bg-slate-50 border border-slate-300 rounded p-1 flex-1"><i class="fa-solid fa-server text-blue-600 block text-[10px] mb-0.5"></i>INVERTERS / METERS / RELAYS</div>
                                    <div class="text-[7px] font-mono text-slate-400"><span class="block">RS485 / ETH</span>⟷</div>
                                    <div class="bg-slate-50 border border-slate-300 rounded p-1 flex-1"><i class="fa-solid fa-microchip text-amber-600 block text-[10px] mb-0.5"></i>SCADA PANEL / PLC / RTU</div>
                                    <div class="text-[7px] font-mono text-slate-400"><span class="block">ETHERNET</span>⟷</div>
                                    <div class="bg-slate-50 border border-slate-300 rounded p-1 flex-1"><i class="fa-solid fa-database text-purple-600 block text-[10px] mb-0.5"></i>SCADA SERVER</div>
                                    <div class="text-[7px] font-mono text-slate-400"><span class="block">ETH / 4G</span>⟷</div>
                                    <div class="bg-slate-50 border border-slate-300 rounded p-1 flex-1"><i class="fa-solid fa-mobile-screen-button text-emerald-600 block text-[10px] mb-0.5"></i>REMOTE ACCESS (WEB / APP)</div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Inspector Modal -->
    <div id="inspectModal" class="fixed inset-0 bg-black/60 backdrop-blur-xs hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white border border-slate-300 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="flex items-center justify-between p-4 border-b border-slate-200 bg-slate-50">
                <div class="flex items-center gap-2">
                    <i id="modalIcon" class="fa-solid fa-circle-info text-emerald-600"></i>
                    <h3 class="text-base font-black text-slate-900" id="modalTitle">Component Inspector</h3>
                </div>
                <button onclick="closeInspect()" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 hover:text-slate-800 transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="p-5 space-y-4 text-xs" id="modalBody"></div>
        </div>
    </div>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        let currentPlant = urlParams.get('plant') || 'vinoba-velliyanai';

        const plantConfig = {
            'vinoba-velliyanai': {
                name: 'Vinoba Velliyanai',
                capacity: '2.0 MWp (DC) / ~2.0 MW (AC)',
                titleCapacity: '2.0MW SOLAR POWER PLANT',
                drawingNo: 'NUC/SLD/VNB/01',
                defaultInverters: [1, 5, 6, 7]
            },
            'makkalpower': {
                name: 'Makkal Power',
                capacity: '2.0 MWp (DC) / ~2.0 MW (AC)',
                titleCapacity: '2.0MW SOLAR POWER PLANT',
                drawingNo: 'NUC/SLD/MKP/01',
                defaultInverters: [1, 2, 3]
            },
            'anushyam': {
                name: 'Anushyam Plant',
                capacity: '2.0 MWp (DC) / ~2.0 MW (AC)',
                titleCapacity: '2.0MW SOLAR POWER PLANT',
                drawingNo: 'NUC/SLD/ASY/01',
                defaultInverters: [3, 4, 5, 6]
            }
        };

        // Telemetry state
        let sldData = {
            vcbPower: 0, vcbToday: 0, vcbExpMwh: 0, vcbImpMwh: 0, vcbPf: 0.99,
            oilTemp: 0, wtiTemp: 0,
            inverters: {}
        };

        function updatePlantDetailsUI(pcfg) {
            const count = Object.keys(sldData.inverters).length;
            const fullTitle = (pcfg.titleCapacity || 'SOLAR POWER PLANT');
            document.getElementById('pageTitle').textContent = pcfg.name + ' - ' + fullTitle + ' (SLD)';
            document.getElementById('sld_header_title').textContent = pcfg.name.toUpperCase() + ' - ' + fullTitle;
            const tbTitle = document.getElementById('title_block_title');
            if (tbTitle) tbTitle.textContent = pcfg.name.toUpperCase();
            const plantDtCap = document.getElementById('plant_dt_capacity');
            if (plantDtCap) plantDtCap.textContent = pcfg.capacity;
            const plantDtCnt = document.getElementById('plant_dt_invcount');
            if (plantDtCnt) plantDtCnt.textContent = count + ' NOS';
            const capText = document.getElementById('sld_capacity_text');
            if (capText) capText.textContent = pcfg.capacity;
            const invCountLabel = document.getElementById('sld_inv_count_label');
            if (invCountLabel) invCountLabel.textContent = count;
            const bannerCount = document.getElementById('sld_banner_inv_count');
            if (bannerCount) bannerCount.textContent = count + ' Nos. STRING INVERTERS (800V AC OUTPUT)';
            const dwgEl = document.getElementById('title_block_dwg');
            if (dwgEl) dwgEl.textContent = pcfg.drawingNo || 'NUC/SLD/01';
        }

        function updatePlantPills() {
            ['vinoba-velliyanai', 'makkalpower', 'anushyam'].forEach(p => {
                const pill = document.getElementById(p === 'vinoba-velliyanai' ? 'pill-vinoba' : (p === 'makkalpower' ? 'pill-makkal' : 'pill-anushyam'));
                if (pill) {
                    pill.className = p === currentPlant
                        ? "px-3 py-1.5 rounded-md text-xs font-black bg-emerald-600 text-white shadow-sm"
                        : "px-3 py-1.5 rounded-md text-xs font-semibold text-slate-600 hover:text-slate-900";
                }
            });
            const pcfg = plantConfig[currentPlant] || { name: currentPlant, titleCapacity: 'SOLAR POWER PLANT', capacity: '2.0 MW', drawingNo: 'NUC/SLD/01', defaultInverters: [1,2,3,4] };
            updatePlantDetailsUI(pcfg);
        }

        function switchPlant(plantId) {
            currentPlant = plantId;
            const newUrl = new URL(window.location.href);
            newUrl.searchParams.set('plant', plantId);
            window.history.pushState({}, '', newUrl);
            updatePlantPills();
            initPlantState();
            if (ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({ type: "subscribe", unit_id: currentPlant }));
            }
        }

        updatePlantPills();
        setInterval(() => { document.getElementById('clockDisplay').innerText = new Date().toLocaleTimeString('en-IN', {hour12: false}); }, 1000);

        // Sidebar
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
            const pcfg = plantConfig[currentPlant];
            if (_pn) _pn.textContent = pcfg ? pcfg.name : currentPlant;
            if (typeof initSidebar === 'function') initSidebar();
            const curPage = window.location.pathname.split('/').pop() || 'sld.php';
            document.querySelectorAll('#sidebarNav a').forEach(link => {
                const dp = link.getAttribute('data-page');
                if (dp && (dp === curPage || dp.replace('.php','.html') === curPage)) {
                    link.classList.add('!bg-slate-100', '!text-emerald-700', '!border-emerald-500');
                }
            });
            const overlay = document.getElementById('overlay'), sidebar = document.getElementById('sidebar');
            document.getElementById('menuBtn')?.addEventListener('click', () => { sidebar?.classList.remove('-translate-x-full'); overlay?.classList.remove('hidden'); });
            document.getElementById('closeSidebarBtn')?.addEventListener('click', () => { sidebar?.classList.add('-translate-x-full'); overlay?.classList.add('hidden'); });
            overlay?.addEventListener('click', () => { sidebar?.classList.add('-translate-x-full'); overlay.classList.add('hidden'); });
        });

        function initPlantState() {
            const pcfg = plantConfig[currentPlant] || {
                name: currentPlant, capacity: '2.0 MWp (DC)',
                titleCapacity: '2.0MW SOLAR POWER PLANT', drawingNo: 'NUC/SLD/01', defaultInverters: [1,2,3,4]
            };
            sldData = {
                vcbPower: 0, vcbToday: 0, vcbExpMwh: 0, vcbImpMwh: 0, vcbPf: 0.99,
                oilTemp: 0, wtiTemp: 0, inverters: {}
            };
            pcfg.defaultInverters.forEach(num => {
                const pad = String(num).padStart(2, '0');
                sldData.inverters['INV-' + pad] = {
                    key: 'inverter' + num, num: num, power: 0, dailyGen: 0,
                    activeStrings: 0, totalStrings: 24, status: 'Standby'
                };
            });
            updatePlantDetailsUI(pcfg);
            renderSchematic();
        }
        initPlantState();

        function renderSchematic() {
            const grid = document.getElementById('inverter_schematic_grid');
            if (!grid) return;
            let html = '';
            const invKeys = Object.keys(sldData.inverters).sort((a,b) => (parseInt(a.replace(/\D/g,''))||0) - (parseInt(b.replace(/\D/g,''))||0));

            invKeys.forEach((invLabel, idx) => {
                const inv = sldData.inverters[invLabel];
                const isOn = (inv.power > 0.05);
                const fNum = idx + 1;
                const lineColor = isOn ? '#1e293b' : '#94a3b8';
                const mccbFill = isOn ? '#10b981' : '#ffffff';

                html += `
                    <div class="flex flex-col items-center" style="width:96px;">
                        <!-- Feeder # -->
                        <span class="text-[9px] font-black text-slate-800 font-mono mb-0.5">${fNum}</span>
                        <!-- Tap from busbar -->
                        <svg width="4" height="10"><line x1="2" y1="0" x2="2" y2="10" stroke="#0f172a" stroke-width="2"/></svg>
                        <!-- MCCB symbol -->
                        <div class="flex flex-col items-center cursor-pointer hover:scale-105 transition" onclick="showInspect('mccb','${invLabel}')">
                            <svg width="24" height="28" viewBox="0 0 24 28">
                                <circle cx="12" cy="5" r="2.5" stroke="#0f172a" stroke-width="1.8" fill="${mccbFill}"/>
                                <line x1="12" y1="7.5" x2="${isOn ? '12' : '19'}" y2="20.5" stroke="#0f172a" stroke-width="2" stroke-linecap="round"/>
                                <circle cx="12" cy="23" r="2.5" stroke="#0f172a" stroke-width="1.8" fill="${mccbFill}"/>
                            </svg>
                            <span class="text-[8px] font-black text-slate-800">MCCB</span>
                            <span class="text-[7px] text-slate-600 font-mono leading-tight">800V<br>630A<br>36kA</span>
                        </div>
                        <!-- Line with downward arrow to inverter -->
                        <svg width="10" height="18" viewBox="0 0 10 18">
                            <line x1="5" y1="0" x2="5" y2="14" stroke="#0f172a" stroke-width="2"/>
                            <path d="M2 11 L5 16 L8 11" stroke="#0f172a" stroke-width="1.8" fill="none" stroke-linecap="round"/>
                        </svg>
                        <!-- Inverter block with diagonal split (~ / =) -->
                        <div class="w-full border-2 border-slate-900 bg-white rounded p-1 text-center cursor-pointer hover:border-blue-600 transition" onclick="showInspect('inverter','${invLabel}')">
                            <div class="w-10 h-10 mx-auto border border-slate-900 bg-white relative flex flex-col justify-between p-0.5 overflow-hidden">
                                <svg class="absolute inset-0 w-full h-full" viewBox="0 0 40 40">
                                    <line x1="0" y1="40" x2="40" y2="0" stroke="#0f172a" stroke-width="1.5"/>
                                </svg>
                                <div class="flex justify-between items-center text-[10px] font-black z-10">
                                    <span class="text-slate-900 pl-0.5">~</span>
                                </div>
                                <div class="flex justify-end items-center text-[10px] font-black z-10">
                                    <span class="text-slate-900 pr-0.5">=</span>
                                </div>
                            </div>
                            <p class="text-[9px] font-black text-slate-900 mt-1 uppercase">${invLabel}</p>
                            <p class="text-[8px] font-bold ${isOn ? 'text-emerald-700' : 'text-slate-500'} font-mono">${(inv.power||0).toFixed(1)} kW</p>
                            <p class="text-[7px] text-slate-500 font-mono">${(inv.dailyGen||0).toFixed(0)} kWh</p>
                        </div>
                        <!-- Line with downward arrow to PV Strings -->
                        <svg width="10" height="16" viewBox="0 0 10 16">
                            <line x1="5" y1="0" x2="5" y2="12" stroke="#0f172a" stroke-width="2"/>
                            <path d="M2 9 L5 14 L8 9" stroke="#0f172a" stroke-width="1.8" fill="none" stroke-linecap="round"/>
                        </svg>
                        <!-- PV STRINGS dashed box -->
                        <div class="w-full border-2 border-dashed border-slate-800 bg-white rounded p-1 text-center cursor-pointer hover:border-amber-600 transition" onclick="showInspect('pv','${invLabel}')">
                            <span class="text-[8px] font-black text-slate-900 block tracking-tight">PV<br>STRINGS</span>
                        </div>
                    </div>
                `;
            });

            grid.innerHTML = html;

            // Update live values on diagram
            const expEl = document.getElementById('sld_eb_exp');
            const impEl = document.getElementById('sld_eb_imp');
            if (expEl) expEl.textContent = sldData.vcbExpMwh.toFixed(3) + ' MWh';
            if (impEl) impEl.textContent = sldData.vcbImpMwh.toFixed(3) + ' MWh';
            const oilEl = document.getElementById('sld_oil_temp');
            const wtiEl = document.getElementById('sld_wti_temp');
            if (oilEl) oilEl.textContent = (sldData.oilTemp > 0 ? sldData.oilTemp.toFixed(1) : '--') + ' °C';
            if (wtiEl) wtiEl.textContent = (sldData.wtiTemp > 0 ? sldData.wtiTemp.toFixed(1) : '--') + ' °C';
            const vcbBadge = document.getElementById('vcb_status_badge');
            if (vcbBadge) {
                vcbBadge.textContent = sldData.vcbPower > 0.05 ? 'CLOSED' : 'OPEN';
                vcbBadge.className = sldData.vcbPower > 0.05
                    ? "inline-block mt-0.5 px-1.5 py-px rounded text-[7px] font-black bg-emerald-100 text-emerald-800 border border-emerald-300 uppercase"
                    : "inline-block mt-0.5 px-1.5 py-px rounded text-[7px] font-black bg-slate-100 text-slate-600 border border-slate-300 uppercase";
            }
        }

        // WebSocket
        let ws;
        function connectWS() {
            ws = new WebSocket("wss://vinobasolar.scadahub.in:5001");
            ws.onopen = function() {
                document.getElementById('refreshPulse').className = 'w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.8)]';
                ws.send(JSON.stringify({ type: "subscribe", unit_id: currentPlant }));
            };
            ws.onmessage = function(e) {
                try {
                    const d = JSON.parse(e.data);
                    if (d.unit_id !== currentPlant) return;

                    const taskStr = d.task ? d.task.toString().toLowerCase() : '';
                    const devStr = d.device ? d.device.toString().toLowerCase() : '';

                    // VCB
                    if (taskStr === 'vcb' || devStr.includes('vcb') || (d.values && d.values["3 Phase Active Power"] !== undefined)) {
                        if (d.values) {
                            if (d.values["3 Phase Active Power"] !== undefined) sldData.vcbPower = parseFloat(d.values["3 Phase Active Power"]) || 0;
                            for (const k in d.values) {
                                const kl = k.toLowerCase();
                                if (/export/i.test(kl) && !/reactive/i.test(kl)) { const v = parseFloat(d.values[k]) || 0; sldData.vcbExpMwh = v > 10000 ? (v/1000) : v; }
                                if (/import/i.test(kl) && !/reactive/i.test(kl)) { const v = parseFloat(d.values[k]) || 0; sldData.vcbImpMwh = v > 10000 ? (v/1000) : v; }
                                if (/power.*factor|cosphi/i.test(kl)) sldData.vcbPf = Math.abs(parseFloat(d.values[k])) || 0.99;
                            }
                        }
                        if (d.virtualTags && d.virtualTags["vcb-today"] !== undefined) {
                            const vt = parseFloat(d.virtualTags["vcb-today"].value);
                            if (vt > 0) sldData.vcbToday = vt;
                        }
                    }

                    // Transformer
                    if (taskStr === 'transformer' || devStr.includes('transformer')) {
                        if (d.values) {
                            for (const k in d.values) {
                                const kl = k.toLowerCase();
                                if (/oil/i.test(kl)) sldData.oilTemp = parseFloat(d.values[k]) || sldData.oilTemp;
                                if (/winding/i.test(kl)) sldData.wtiTemp = parseFloat(d.values[k]) || sldData.wtiTemp;
                            }
                        }
                    }

                    // Inverter
                    if (d.values && !(taskStr === 'vcb' || devStr.includes('vcb') || taskStr === 'transformer' || devStr.includes('transformer'))) {
                        const devName = d.device || '';
                        let matchLabel = null;
                        const match = devName.match(/inverter\s*(\d+)/i);
                        if (match) {
                            const num = parseInt(match[1]);
                            matchLabel = 'INV-' + String(num).padStart(2, '0');
                            if (!sldData.inverters[matchLabel]) {
                                sldData.inverters[matchLabel] = {
                                    key: 'inverter' + num, num: num, power: 0, dailyGen: 0,
                                    activeStrings: 0, totalStrings: 24, status: 'Standby'
                                };
                                const pcfg = plantConfig[currentPlant] || { name: currentPlant, capacity: '2.0 MW', titleCapacity: '2.0MW SOLAR POWER PLANT', drawingNo: 'NUC/SLD/01' };
                                updatePlantDetailsUI(pcfg);
                            }
                        }

                        if (matchLabel && sldData.inverters[matchLabel]) {
                            let pwr = 0, dgen = 0, activeCount = 0, totalCount = 0;
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
                            }
                            sldData.inverters[matchLabel].power = pwr;
                            if (dgen > 0) sldData.inverters[matchLabel].dailyGen = dgen;
                            if (totalCount > 0) {
                                sldData.inverters[matchLabel].activeStrings = activeCount;
                                sldData.inverters[matchLabel].totalStrings = totalCount;
                            }
                        }
                    }

                    renderSchematic();
                } catch (err) {
                    console.error("SLD WS error:", err);
                }
            };
            ws.onclose = function() {
                document.getElementById('refreshPulse').className = 'w-2.5 h-2.5 bg-red-500 rounded-full';
                setTimeout(connectWS, 5000);
            };
        }
        connectWS();

        // Inspector Modal
        function showInspect(type, extra) {
            const modal = document.getElementById('inspectModal');
            const title = document.getElementById('modalTitle');
            const body = document.getElementById('modalBody');
            const icon = document.getElementById('modalIcon');

            if (type === 'vcb') {
                title.textContent = 'Vacuum Circuit Breaker (VCB) - 33kV';
                icon.className = 'fa-solid fa-plug text-emerald-600 text-lg';
                body.innerHTML = `
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-slate-50 p-3 rounded-lg border"><span class="text-slate-500 block text-[10px] uppercase font-bold">Breaker Status</span><b class="text-emerald-700 text-sm">${sldData.vcbPower > 0.05 ? 'CLOSED' : 'OPEN'}</b></div>
                        <div class="bg-slate-50 p-3 rounded-lg border"><span class="text-slate-500 block text-[10px] uppercase font-bold">3-Phase Active Power</span><b class="text-slate-900 text-sm">${sldData.vcbPower.toFixed(2)} kW</b></div>
                        <div class="bg-slate-50 p-3 rounded-lg border"><span class="text-slate-500 block text-[10px] uppercase font-bold">Active Total Export</span><b class="text-cyan-700 text-sm">${sldData.vcbExpMwh.toFixed(3)} MWh</b></div>
                        <div class="bg-slate-50 p-3 rounded-lg border"><span class="text-slate-500 block text-[10px] uppercase font-bold">Active Total Import</span><b class="text-amber-700 text-sm">${sldData.vcbImpMwh.toFixed(3)} MWh</b></div>
                    </div>`;
            } else if (type === 'transformer') {
                title.textContent = 'Power Transformer (33kV / 800V, 4.5MVA)';
                icon.className = 'fa-solid fa-bolt text-amber-600 text-lg';
                body.innerHTML = `
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-slate-50 p-3 rounded-lg border"><span class="text-slate-500 block text-[10px] uppercase font-bold">Oil Temp (OTI)</span><b class="text-amber-700 text-sm">${sldData.oilTemp > 0 ? sldData.oilTemp.toFixed(1) : '--'} °C</b></div>
                        <div class="bg-slate-50 p-3 rounded-lg border"><span class="text-slate-500 block text-[10px] uppercase font-bold">Winding Temp (WTI)</span><b class="text-amber-700 text-sm">${sldData.wtiTemp > 0 ? sldData.wtiTemp.toFixed(1) : '--'} °C</b></div>
                        <div class="bg-slate-50 p-3 rounded-lg border col-span-2"><span class="text-slate-500 block text-[10px] uppercase font-bold">Vector Group</span><b class="text-slate-900 text-sm">Dyn11 (Delta Primary / Star Secondary)</b></div>
                    </div>`;
            } else if (type === 'inverter' && extra) {
                const inv = sldData.inverters[extra] || {};
                title.textContent = extra + ' String Inverter';
                icon.className = 'fa-solid fa-server text-blue-600 text-lg';
                body.innerHTML = `
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-slate-50 p-3 rounded-lg border"><span class="text-slate-500 block text-[10px] uppercase font-bold">AC Active Power</span><b class="text-emerald-700 text-sm">${(inv.power||0).toFixed(2)} kW</b></div>
                        <div class="bg-slate-50 p-3 rounded-lg border"><span class="text-slate-500 block text-[10px] uppercase font-bold">Daily Generation</span><b class="text-purple-700 text-sm">${(inv.dailyGen||0).toFixed(2)} kWh</b></div>
                        <div class="bg-slate-50 p-3 rounded-lg border"><span class="text-slate-500 block text-[10px] uppercase font-bold">Active PV Strings</span><b class="text-blue-700 text-sm">${inv.activeStrings||0} / ${inv.totalStrings||24}</b></div>
                        <div class="bg-slate-50 p-3 rounded-lg border"><span class="text-slate-500 block text-[10px] uppercase font-bold">Output Voltage</span><b class="text-slate-900 text-sm">800V AC (3Φ)</b></div>
                    </div>`;
            } else if (type === 'mccb' && extra) {
                title.textContent = 'MCCB - Feeder to ' + extra;
                icon.className = 'fa-solid fa-circle-nodes text-slate-700 text-lg';
                body.innerHTML = `
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-slate-50 p-3 rounded-lg border"><span class="text-slate-500 block text-[10px] uppercase font-bold">Rated Voltage</span><b class="text-slate-900 text-sm">800V AC</b></div>
                        <div class="bg-slate-50 p-3 rounded-lg border"><span class="text-slate-500 block text-[10px] uppercase font-bold">Rated Current</span><b class="text-slate-900 text-sm">630A</b></div>
                        <div class="bg-slate-50 p-3 rounded-lg border"><span class="text-slate-500 block text-[10px] uppercase font-bold">Breaking Capacity</span><b class="text-slate-900 text-sm">36kA</b></div>
                        <div class="bg-slate-50 p-3 rounded-lg border"><span class="text-slate-500 block text-[10px] uppercase font-bold">Feeder</span><b class="text-blue-700 text-sm">${extra}</b></div>
                    </div>`;
            } else {
                title.textContent = 'Electrical Component';
                icon.className = 'fa-solid fa-circle-info text-slate-700 text-lg';
                body.innerHTML = `<p class="text-slate-700">Component per IEC 60364 / IS 3043 standards.</p>`;
            }
            modal.classList.remove('hidden');
        }

        function closeInspect() {
            document.getElementById('inspectModal').classList.add('hidden');
        }
    </script>
</body>
</html>
