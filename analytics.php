<?php
require 'check_auth.php';
date_default_timezone_set('Asia/Kolkata');

$plantConfigs = [
    'vinoba-velliyanai' => ['name' => 'Vinoba Velliyanai', 'capacity' => 2.0, 'inverter_count' => 8],
    'makkalpower'       => ['name' => 'Makkal Power',       'capacity' => 2.0, 'inverter_count' => 8],
    'anushyam'          => ['name' => 'Anushyam Plant',     'capacity' => 2.0, 'inverter_count' => 8],
];
if (!isset($plantConfigs[$currentPlant])) $currentPlant = 'vinoba-velliyanai';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title id="pageTitle">Plant Analytics</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="sidebar-control.js?v=4" defer></script>
<style>
::-webkit-scrollbar{width:8px;height:8px}::-webkit-scrollbar-track{background:#f8fafc}
::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:4px}::-webkit-scrollbar-thumb:hover{background:#94a3b8}
</style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 font-sans">
<div class="min-h-screen flex relative">
<div id="overlay" class="fixed inset-0 bg-slate-900/40 hidden z-30 md:hidden"></div>
<div id="sidebar-container"></div>
<main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden">
<header class="bg-white p-4 sm:px-6 flex justify-between items-center sticky top-0 z-20 border-b border-slate-200 shadow-sm">
<div class="flex items-center gap-3 min-w-0">
<button id="menuBtn" class="md:hidden text-emerald-600 text-2xl">&#9776;</button>
<div class="min-w-0"><h2 class="text-xl font-black text-slate-800 tracking-tight truncate">Plant Analytics</h2>
<p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Live inverter output & today trend</p></div>
</div>
<div class="flex items-center gap-3 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100">
<div id="refreshPulse" class="w-2.5 h-2.5 bg-amber-500 rounded-full"></div>
<span id="clockDisplay" class="text-xs font-bold text-slate-600 tracking-widest hidden sm:inline">--:--:--</span>
</div>
</header>

<div class="p-4 sm:p-6 w-full flex flex-col gap-6 max-w-[1650px] mx-auto">
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 sm:gap-6">
<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
<h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Live Power</h3>
<p id="comb_power" class="font-black text-slate-800 text-3xl">-- <span class="text-sm font-bold text-blue-600">kW</span></p>
<p class="text-xs text-slate-500 mt-1">Combined inverter AC output</p></div>
<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
<h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Today Yield</h3>
<p id="yield_val" class="font-black text-slate-800 text-3xl">-- <span class="text-sm font-bold text-purple-600">kWh</span></p>
<p class="text-xs text-slate-500 mt-1">Latest cumulative inverter energy</p></div>
<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
<h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Availability</h3>
<p id="avail_val" class="font-black text-slate-800 text-3xl">-- <span class="text-sm font-bold text-emerald-600">%</span></p>
<p id="inv_active_count" class="text-xs text-slate-500 mt-1">0 / 0 online</p></div>
<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
<h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Capacity Factor</h3>
<p id="perf_val" class="font-black text-slate-800 text-3xl">-- <span class="text-sm font-bold text-amber-600">%</span></p>
<p id="capacityLabel" class="text-xs text-slate-500 mt-1">2.0 MWp</p></div>
</div>

<section class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 sm:p-5">
<div class="flex flex-col lg:flex-row lg:items-end gap-4 mb-5">
<div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Plant / Live source</p>
<h2 id="trendHeading" class="text-xl font-bold text-slate-900">Output Trend</h2>
<p id="trendHelp" class="mt-1 text-xs text-slate-500">Live WebSocket samples are retained for today's selected source.</p></div>
<div class="ml-auto flex flex-wrap items-end justify-end gap-2">
<label class="text-xs font-semibold text-slate-500 min-w-[190px]"><span class="block mb-1">Plant</span>
<select id="plantSwitcher" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50">
<?php foreach ($plantConfigs as $id=>$cfg): ?>
<option value="<?php echo htmlspecialchars($id); ?>"><?php echo htmlspecialchars($cfg['name']); ?></option>
<?php endforeach; ?>
</select></label>
<label class="text-xs font-semibold text-slate-500 min-w-[190px]"><span class="block mb-1">Data Source</span>
<select id="analyticsSourceSelect" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50"><option value="">Select Inverter</option></select></label>
<button id="downloadExcel" type="button" disabled class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-bold text-white disabled:opacity-40"><i class="fa-solid fa-file-excel"></i> Excel</button>
</div></div>

<div id="emptyState" class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500 mb-4">Waiting for live inverter telemetry...</div>
<div class="rounded-xl border border-slate-200 bg-slate-50/40 p-4">
<div class="flex flex-wrap items-center justify-between gap-2 mb-3">
<div><h3 id="outputTrendTitle" class="text-sm font-black text-slate-700">Inverter Output</h3>
<p id="outputTrendDescription" class="text-[11px] text-slate-400">Direct AC power; V × I × PF is used only when direct power is unavailable.</p></div>
<div class="text-right"><p id="latestLabel" class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Latest Output</p>
<p class="text-lg font-black text-blue-700"><span id="latestOutputValue">--</span> <span id="latestUnit" class="text-xs">kW</span></p>
<p class="text-[10px] text-slate-400">Live through <span id="analyticsLiveLabel">--</span></p></div>
</div>
<div class="h-[340px] sm:h-[410px]"><canvas id="outputTrendChart"></canvas></div>
</div>
</section>

<section class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
<div class="flex flex-wrap items-center justify-between gap-2 mb-4"><h3 class="text-sm font-black text-slate-600 uppercase tracking-widest">Live Inverter Snapshot</h3>
<span id="snapshotStatus" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">Waiting</span></div>
<div class="overflow-x-auto"><table class="w-full text-left border-collapse"><thead><tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-black text-slate-600 uppercase tracking-wider">
<th class="p-3">Inverter</th><th class="p-3 text-right">Power kW</th><th class="p-3 text-right">Today kWh</th><th class="p-3 text-center">Strings</th><th class="p-3 text-right">Voltage V</th><th class="p-3 text-right">Frequency Hz</th><th class="p-3 text-center">Status</th><th class="p-3 text-right">Last Update</th>
</tr></thead><tbody id="snapshotBody" class="divide-y divide-slate-100 text-xs"></tbody></table></div>
</section>
</div></main></div>

<script>
const PLANTS = <?php echo json_encode($plantConfigs, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
let currentPlant = <?php echo json_encode($currentPlant); ?>;
const WS_URL = 'wss://vinobasolar.scadahub.in:5001';
let socket = null, reconnectTimer = null, selectedSource = '', chart = null;
const state = { inverters:{}, history:{} };
const GENERATION_THRESHOLD_KW = 0.1;
const SOURCE_LABELS = {};

document.getElementById('plantSwitcher').value = currentPlant;
document.getElementById('pageTitle').textContent = (PLANTS[currentPlant]?.name || currentPlant) + ' - Analytics';
document.getElementById('capacityLabel').textContent = ((PLANTS[currentPlant]?.capacity || 2) + ' MWp');
setInterval(()=>document.getElementById('clockDisplay').textContent=new Date().toLocaleTimeString('en-IN',{hour12:false}),1000);

function normalizeName(v){return String(v||'').trim().replace(/\\s+/g,' ');}
function inverterKey(v){const m=normalizeName(v).match(/(?:inv(?:erter)?)[-\\s_]*(\\d+)/i)||normalizeName(v).match(/\\b(\\d+)\\b/);return m?'Inverter'+parseInt(m[1],10):normalizeName(v);}
function isInverter(v){const s=normalizeName(v).toLowerCase();return s && /inverter|(^|[^a-z])inv([^a-z]|$)/.test(s) && !/vcb|transformer|trafo/.test(s);}
function inverterLabel(k){const m=String(k).match(/\\d+/);return m?'Inverter '+parseInt(m[0],10):k;}
function ensureInverter(device){
 const key=inverterKey(device);
 if(!state.inverters[key]) state.inverters[key]={wsName:normalizeName(device)||key,power:0,dailyGen:0,activeStrings:0,totalStrings:0,voltage:0,freq:0,lastUpdate:0,online:false};
 if(normalizeName(device)) state.inverters[key].wsName=normalizeName(device);
 if(!state.history[key]) state.history[key]=new Map();
 SOURCE_LABELS[key]=inverterLabel(key);
 return key;
}
function num(v){if(v===null||v===undefined||v==='')return null; if(typeof v==='object'){for(const k of ['value','val','reading','data','result'])if(k in v){const n=num(v[k]);if(n!==null)return n}return null} const n=parseFloat(String(v).replace(/,/g,''));return Number.isFinite(n)?n:null;}
function direct(values,keys){for(const k of keys){if(Object.prototype.hasOwnProperty.call(values||{},k)){const n=num(values[k]);if(n!==null)return n}}return null;}
function metric(values,accept,reject=[]){for(const [k,v] of Object.entries(values||{})){const s=k.toLowerCase().replace(/[_-]+/g,' ').replace(/\\s+/g,' ').trim();if(reject.some(r=>r.test(s))||!accept.some(r=>r.test(s)))continue;const n=num(v);if(n!==null)return n}return null;}
function pf(v){if(v===null)return null;let n=Math.abs(v);if(n>1.2&&n<=100)n/=100;return n<=1?n:null;}
function outputFrom(values){
 const d=direct(values,['Total active power','a.c. active power','AC Power','active_power','power_kw']);
 const directKw=d!==null&&!/NaN/.test(String(d))?d:null;
 const va=metric(values,[/ry.*volt/,/v12/,/voltage.*ab/,/vac.*ab/],[/dc|string|temp/]);
 const vb=metric(values,[/yb.*volt/,/v23/,/voltage.*bc/,/vac.*bc/],[/dc|string|temp/]);
 const vc=metric(values,[/br.*volt/,/v31/,/voltage.*ca/,/vac.*ca/],[/dc|string|temp/]);
 const ia=metric(values,[/ry.*current/,/current.*a/,/a.*phase.*current/,/^i a$/],[/volt|string|mppt|dc/]);
 const ib=metric(values,[/yb.*current/,/current.*b/,/b.*phase.*current/,/^i b$/],[/volt|string|mppt|dc/]);
 const ic=metric(values,[/br.*current/,/current.*c/,/c.*phase.*current/,/^i c$/],[/volt|string|mppt|dc/]);
 const factor=pf(direct(values,['Power factor','power_factor','pf']) ?? metric(values,[/power factor/,/^pf$/]));
 const av=[va,vb,vc].filter(v=>v!==null), ai=[ia,ib,ic].filter(v=>v!==null);
 const calc=av.length&&ai.length&&factor!==null ? Math.sqrt(3)*(av.reduce((a,b)=>a+b,0)/av.length)*(ai.reduce((a,b)=>a+b,0)/ai.length)*factor/1000 : null;
 let power=null,source='';
 if(directKw!==null&&directKw>GENERATION_THRESHOLD_KW){power=Math.max(0,directKw);source='Direct AC power'}
 else if(calc!==null&&calc>GENERATION_THRESHOLD_KW){power=Math.max(0,calc);source='Calculated V × I × PF'}
 else if(directKw!==null){power=Math.max(0,directKw);source='Direct AC power'}
 else if(calc!==null){power=Math.max(0,calc);source='Calculated V × I × PF'}
 const daily=direct(values,['Daily power yields','daily generation','daily_generation','Day Energy','today_energy','daily_gen_kwh']) ?? metric(values,[/daily.*yield/,/daily.*gen/,/today.*energy/,/day.*energy/]);
 const work=String(values?.['Work state'] ?? values?.work_state ?? values?.Status ?? values?.status ?? '');
 return {power,source,daily,work,va:va??vb??vc,freq:metric(values,[/frequency/,/^freq/])};
}
function timestamp(raw){
 const s=String(raw||'').trim(); if(!s)return new Date();
 if(/[zZ]$|[+-]\\d{2}:?\\d{2}$/.test(s)){const d=new Date(s);if(!isNaN(d))return d}
 const f=s.match(/(\\d{4})[-/](\\d{1,2})[-/](\\d{1,2})[ T](\\d{1,2}):(\\d{2})(?::(\\d{2}))?/);
 if(f)return new Date(+f[1],+f[2]-1,+f[3],+f[4],+f[5],+(f[6]||0));
 const t=s.match(/\\b(\\d{1,2}):(\\d{2})(?::(\\d{2}))?/);if(t){const n=new Date();return new Date(n.getFullYear(),n.getMonth(),n.getDate(),+t[1],+t[2],+(t[3]||0))}
 const d=new Date(s);return isNaN(d)?new Date():d;
}
function today(d=new Date()){return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');}
function rowsFromMessage(msg){
 const out=[],base=msg.device||msg.deviceName||'';
 if(msg.values&&typeof msg.values==='object')out.push({device:base,values:msg.values,time:msg.time||msg.timestamp||msg.ts||''});
 if(Array.isArray(msg.data))msg.data.forEach(r=>{if(!r||typeof r!=='object')return;out.push({device:r.device||r.deviceName||base,values:r.values&&typeof r.values==='object'?r.values:r,time:r.time||r.timestamp||r.ts||msg.time||msg.timestamp||''})});
 return out;
}
function applyReading(device,values,sourceTime){
 if(!isInverter(device)||!values)return false;
 const key=ensureInverter(device), x=outputFrom(values), d=timestamp(sourceTime), st=state.inverters[key];
 if(x.power!==null)st.power=x.power;
 if(x.daily!==null)st.dailyGen=x.daily;
 if(x.va!==null)st.voltage=x.va;
 if(x.freq!==null)st.freq=x.freq;
 st.online=(st.power>GENERATION_THRESHOLD_KW)&&!/offline|fault|stop|standby|disconnect/i.test(x.work);
 st.lastUpdate=d.getTime();
 if(today(d)===today()&&x.power!==null)state.history[key].set(d.getTime(),{timestamp:d.getTime(),power:x.power,source:x.source,daily:x.daily,values});
 return x.power!==null||x.daily!==null;
}
function seedConfigured(){const n=Math.max(0,parseInt(PLANTS[currentPlant]?.inverter_count||0,10));for(let i=1;i<=n;i++)ensureInverter('Inverter'+i);populateSources();}
function populateSources(){
 const sel=document.getElementById('analyticsSourceSelect'),current=selectedSource||sel.value||'';
 const names=Object.keys(state.inverters).sort((a,b)=>(parseInt(a.match(/\\d+/)?.[0]||0)-parseInt(b.match(/\\d+/)?.[0]||0))||a.localeCompare(b));
 sel.innerHTML='<option value="">Select Inverter</option>'+names.map(k=>'<option value="'+k+'">'+inverterLabel(k)+'</option>').join('');
 sel.value=names.includes(current)?current:'';
 if(sel.value!==selectedSource){selectedSource=sel.value;renderTrend();}
}
function renderSnapshot(){
 const body=document.getElementById('snapshotBody'),names=Object.keys(state.inverters).sort((a,b)=>parseInt(a.match(/\\d+/)?.[0]||0)-parseInt(b.match(/\\d+/)?.[0]||0));
 if(!names.length){body.innerHTML='<tr><td colspan="8" class="p-8 text-center text-slate-400">No inverter telemetry received.</td></tr>';return}
 let total=0,energy=0,online=0;
 body.innerHTML=names.map(k=>{const s=state.inverters[k];total+=Number(s.power)||0;energy+=Number(s.dailyGen)||0;if(s.online)online++;return '<tr><td class="p-3 font-bold">'+inverterLabel(k)+'</td><td class="p-3 text-right font-mono font-bold text-blue-600">'+(Number(s.power)||0).toFixed(2)+'</td><td class="p-3 text-right font-mono text-purple-600">'+(Number(s.dailyGen)||0).toFixed(2)+'</td><td class="p-3 text-center">'+(s.activeStrings||'--')+' / '+(s.totalStrings||'--')+'</td><td class="p-3 text-right">'+(Number(s.voltage)||0).toFixed(1)+'</td><td class="p-3 text-right">'+(Number(s.freq)||0).toFixed(2)+'</td><td class="p-3 text-center"><span class="px-2 py-1 rounded text-[10px] font-black '+(s.online?'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-500')+'">'+(s.online?'ACTIVE':'STANDBY')+'</span></td><td class="p-3 text-right text-slate-500">'+(s.lastUpdate?new Date(s.lastUpdate).toLocaleTimeString('en-IN',{hour12:false}):'--')+'</td></tr>'}).join('');
 const cfg=PLANTS[currentPlant]||{capacity:2,inverter_count:names.length},cap=(Number(cfg.capacity)||2)*1000;
 document.getElementById('comb_power').innerHTML=total.toFixed(2)+' <span class="text-sm font-bold text-blue-600">kW</span>';
 document.getElementById('yield_val').innerHTML=energy.toFixed(2)+' <span class="text-sm font-bold text-purple-600">kWh</span>';
 document.getElementById('avail_val').innerHTML=((online/Math.max(names.length,Number(cfg.inverter_count)||names.length))*100).toFixed(1)+' <span class="text-sm font-bold text-emerald-600">%</span>';
 document.getElementById('inv_active_count').textContent=online+' / '+names.length+' online';
 document.getElementById('perf_val').innerHTML=((total/cap)*100).toFixed(1)+' <span class="text-sm font-bold text-amber-600">%</span>';
 document.getElementById('snapshotStatus').textContent='Live · '+new Date().toLocaleTimeString('en-IN',{hour12:false});
}
function displayedRows(){
 if(!selectedSource)return [];
 const raw=Array.from(state.history[selectedSource]?.values()||[]).filter(r=>today(new Date(r.timestamp))===today()).sort((a,b)=>a.timestamp-b.timestamp);
 const first=raw.findIndex(r=>Number(r.power)>GENERATION_THRESHOLD_KW);
 const rows=first>=0?raw.slice(first):raw;
 const bucket=new Map();rows.forEach(r=>bucket.set(Math.floor(r.timestamp/300000),r));
 const shown=Array.from(bucket.values()).sort((a,b)=>a.timestamp-b.timestamp);
 if(rows.length&&(!shown.length||shown[shown.length-1].timestamp!==rows[rows.length-1].timestamp))shown.push(rows[rows.length-1]);
 return shown;
}
function renderTrend(){
 const has=!!selectedSource,rows=displayedRows(),gen=rows.length>0;
 document.getElementById('emptyState').textContent=has?(gen?'':'Waiting for this inverter to start generating output today.'):'Select an inverter to load today’s output trend.';
 document.getElementById('emptyState').classList.toggle('hidden',has&&gen);
 document.getElementById('downloadExcel').disabled=!has||!gen;
 document.getElementById('outputTrendTitle').textContent=has?inverterLabel(selectedSource)+' Output':'Inverter Output';
 const latest=rows[rows.length-1];
 document.getElementById('latestOutputValue').textContent=latest?Number(latest.power).toFixed(2):'--';
 document.getElementById('analyticsLiveLabel').textContent=latest?new Date(latest.timestamp).toLocaleTimeString('en-IN',{hour12:false}):'--';
 if(!chart)return;
 chart.data.labels=rows.map(r=>new Date(r.timestamp).toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit',hour12:false}));
 chart.data.datasets[0].data=rows.map(r=>Number(Number(r.power).toFixed(2)));
 chart.update('none');
}
function handleDeviceList(devices){if(!Array.isArray(devices))return;devices.forEach(d=>{const n=d.name||d.device||'';if(isInverter(n))ensureInverter(n)});populateSources();requestHistory();}
function requestHistory(){if(!selectedSource||!socket||socket.readyState!==WebSocket.OPEN)return;const dev=state.inverters[selectedSource]?.wsName||selectedSource;socket.send(JSON.stringify({type:'get_daily_data',unit_id:currentPlant,device:dev,date:today()}));}
function connect(){
 clearTimeout(reconnectTimer);
 try{socket=new WebSocket(WS_URL)}catch(e){reconnectTimer=setTimeout(connect,2500);return}
 socket.onopen=()=>{document.getElementById('refreshPulse').className='w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse';socket.send(JSON.stringify({type:'subscribe',unit_id:currentPlant}));socket.send(JSON.stringify({type:'get_devices',unit_id:currentPlant}));requestHistory()};
 socket.onmessage=e=>{try{
   const m=JSON.parse(e.data),unit=m.unit_id||m.request?.unit_id||m.unitId||m.request?.unitId||'';if(unit&&unit!==currentPlant)return;
   rowsFromMessage(m).forEach(r=>applyReading(r.device,r.values,r.time));
   if(m.type==='device_list')handleDeviceList(m.devices||[]);
   if(m.type==='daily_data_result'&&Array.isArray(m.data))m.data.forEach(r=>{const dev=r.device||r.deviceName||m.device||'';if(isInverter(dev))applyReading(dev,r.values||r,r.time||r.timestamp||r.ts||'')});
   populateSources();renderSnapshot();renderTrend();
 }catch(err){}};
 socket.onclose=()=>{document.getElementById('refreshPulse').className='w-2.5 h-2.5 bg-red-500 rounded-full';reconnectTimer=setTimeout(connect,2500)};
 socket.onerror=()=>{};
}
function exportExcel(){
 if(!selectedSource)return;const raw=Array.from(state.history[selectedSource]?.values()||[]).sort((a,b)=>a.timestamp-b.timestamp);if(!raw.length){alert('No WebSocket history available yet.');return}
 const rows=raw.map(r=>({Date:today(new Date(r.timestamp)),Time:new Date(r.timestamp).toLocaleTimeString('en-IN',{hour12:false}),Plant:PLANTS[currentPlant]?.name||currentPlant,Inverter:inverterLabel(selectedSource),'Output (kW)':Number(Number(r.power).toFixed(3)),'Daily Energy (kWh)':r.daily??'','Output Source':r.source||'WebSocket','Sample Timestamp':new Date(r.timestamp).toLocaleString('en-IN')}));
 const ws=XLSX.utils.json_to_sheet(rows),wb=XLSX.utils.book_new();XLSX.utils.book_append_sheet(wb,ws,'Live Trend');XLSX.writeFile(wb,currentPlant+'_'+selectedSource+'_'+today()+'_analytics.xlsx');
}
document.getElementById('analyticsSourceSelect').addEventListener('change',()=>{selectedSource=document.getElementById('analyticsSourceSelect').value;requestHistory();renderTrend()});
document.getElementById('downloadExcel').addEventListener('click',exportExcel);
document.getElementById('plantSwitcher').addEventListener('change',e=>{const p=e.target.value;const u=new URL(window.location.href);u.searchParams.set('plant',p);window.location.href=u.toString()});
fetch('sidebar.html',{cache:'no-store'}).then(r=>r.text()).then(html=>{document.getElementById('sidebar-container').innerHTML=html;document.querySelectorAll('#sidebarNav a').forEach(a=>{let h=a.getAttribute('href');if(h&&!h.includes('logout')){const u=new URL(h,location.href);u.searchParams.set('plant',currentPlant);const t=new URLSearchParams(location.search).get('token');if(t)u.searchParams.set('token',t);a.href=u.pathname+u.search}});document.getElementById('sidebarPlantName')?.replaceChildren(document.createTextNode(PLANTS[currentPlant]?.name||currentPlant));if(typeof initSidebar==='function')initSidebar();const sidebar=document.getElementById('sidebar'),overlay=document.getElementById('overlay');document.getElementById('menuBtn')?.addEventListener('click',()=>{sidebar?.classList.remove('-translate-x-full');overlay?.classList.remove('hidden')});document.getElementById('closeSidebarBtn')?.addEventListener('click',()=>{sidebar?.classList.add('-translate-x-full');overlay?.classList.add('hidden')});overlay?.addEventListener('click',()=>{sidebar?.classList.add('-translate-x-full');overlay?.classList.add('hidden')})});
const ctx=document.getElementById('outputTrendChart').getContext('2d');
chart=new Chart(ctx,{type:'line',data:{labels:[],datasets:[{label:'Output (kW)',data:[],borderColor:'#2563eb',backgroundColor:'rgba(37,99,235,.12)',pointRadius:2,pointHoverRadius:5,borderWidth:2.5,tension:.28,fill:true}]},options:{responsive:true,maintainAspectRatio:false,animation:false,interaction:{mode:'index',intersect:false},scales:{x:{grid:{display:false},ticks:{color:'#64748b',autoSkip:true,maxTicksLimit:14,maxRotation:0},title:{display:true,text:'Time'}},y:{beginAtZero:true,grid:{color:'#e2e8f0'},title:{display:true,text:'Output (kW)'}}},plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>'Output: '+Number(c.parsed.y||0).toFixed(2)+' kW'}}}}});
seedConfigured();renderSnapshot();renderTrend();connect();
</script>
</body></html>