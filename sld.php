<?php
require 'check_auth.php';
date_default_timezone_set('Asia/Kolkata');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solar Plants - Live SLD</title>
    <link rel="stylesheet" href="assets/app.css?v=20260802-1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        html, body { min-height: 100%; }
        body { background:#f1f5f9; color:#0f172a; }
        .diagram-shell { width:100%; overflow:auto; }
        .sld-svg { display:block; width:100%; height:auto; min-width:520px; }
        .wire { stroke:#0f172a; stroke-width:3; fill:none; stroke-linecap:round; stroke-linejoin:round; }
        .wire-live { stroke:#10b981; stroke-width:4; fill:none; stroke-linecap:round; stroke-linejoin:round; }
        .symbol { stroke:#0f172a; stroke-width:2.5; fill:#fff; }
        .label { font-family:Inter,Arial,sans-serif; fill:#0f172a; }
        .mono { font-family:'JetBrains Mono',monospace; }
        .value { font-family:'JetBrains Mono',monospace; font-weight:800; }
        .muted { fill:#64748b; }
        .live { fill:#059669; }
        .warn { fill:#d97706; }
        .state-on { fill:#059669; }
        .state-off { fill:#64748b; }
        .plant-title { font:900 22px Inter,Arial,sans-serif; letter-spacing:.04em; }
        .section-title { font:800 11px Inter,Arial,sans-serif; letter-spacing:.12em; }
        .component-title { font:900 11px Inter,Arial,sans-serif; }
        .component-value { font:800 10px 'JetBrains Mono',monospace; }
        .small-value { font:800 9px 'JetBrains Mono',monospace; }
        .metric-title { font:800 9px Inter,Arial,sans-serif; fill:#475569; }
        .metric-value { font:900 14px 'JetBrains Mono',monospace; fill:#0f172a; }
        .status-dot { transition: fill .2s ease; }
        @media (max-width: 900px) {
            .sld-svg { min-width:460px; }
        }
    </style>
</head>
<body>
<div class="min-h-screen flex relative">
    <div id="overlay" class="fixed inset-0 bg-slate-900/40 hidden z-30 md:hidden"></div>
    <div id="sidebar-container"></div>
    <main class="flex-1 flex flex-col w-full md:ml-64 min-w-0">
        <header class="bg-white px-4 sm:px-6 py-4 flex items-center justify-between sticky top-0 z-20 border-b border-slate-200 shadow-sm">
            <div class="flex items-center gap-3 min-w-0">
                <button id="menuBtn" class="md:hidden text-emerald-700 text-2xl">&#9776;</button>
                <div class="min-w-0">
                    <h1 class="text-xl font-black truncate">Single Line Diagram (SLD)</h1>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.18em]">Three plants · live SCADA schematic</p>
                </div>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <span id="sldLiveDot" class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
                <span id="sldLiveStatus" class="hidden sm:inline text-[10px] font-black text-slate-500 uppercase tracking-wider">Connecting...</span>
                <span id="clockDisplay" class="hidden md:inline text-xs font-bold font-mono text-slate-600">--:--:--</span>
            </div>
        </header>

        <div class="p-3 sm:p-5 lg:p-7 w-full max-w-[1920px] mx-auto">
            <div id="diagramGrid" class="grid grid-cols-1 xl:grid-cols-3 gap-4 items-start"></div>
        </div>
    </main>
</div>

<script>
const WS_URL = 'wss://vinobasolar.scadahub.in:5001';
const PLANTS = {
    'vinoba-velliyanai': { name:'Vinoba Velliyanai', short:'VINOBA', capacity:'2 MW' },
    'makkalpower': { name:'Makkal Power', short:'MAKKAL', capacity:'2 MW' },
    'anushyam': { name:'Anushyam Plant', short:'ANUSHYAM', capacity:'2 MW' }
};

const states = {};
Object.keys(PLANTS).forEach(id => {
    states[id] = {
        vcbPower:null, vcbExport:null, vcbImport:null, vcbPf:null,
        oilTemp:null, windingTemp:null,
        radiation:null, panelTemp:null, ambientTemp:null, windSpeed:null, humidity:null,
        wmosLast:0,
        inverters:{},
        lastSeen:0
    };
    for (let i=1;i<=8;i++) {
        states[id].inverters['INV-'+String(i).padStart(2,'0')] = {
            power:null, daily:null, lastSeen:0
        };
    }
});

function nrm(v) {
    return String(v ?? '').toLowerCase().replace(/[._-]+/g,' ').replace(/\s+/g,' ').trim();
}

function num(v) {
    if (v === null || v === undefined || v === '') return null;
    if (typeof v === 'object') {
        for (const k of ['value','val','reading','current','last']) {
            if (Object.prototype.hasOwnProperty.call(v,k)) {
                const x=num(v[k]);
                if (x!==null) return x;
            }
        }
        return null;
    }
    const x=Number(String(v).replace(/,/g,''));
    return Number.isFinite(x) ? x : null;
}

function textTime(raw) {
    if (!raw) return Date.now();
    const s=String(raw).trim();
    if (/^\d{1,2}:\d{2}(?::\d{2})?$/.test(s)) {
        const p=s.split(':').map(Number);
        return new Date(new Date().getFullYear(),new Date().getMonth(),new Date().getDate(),p[0],p[1],p[2]||0).getTime();
    }
    const d=new Date(s);
    return Number.isNaN(d.getTime()) ? Date.now() : d.getTime();
}

function valueFor(values, keys) {
    if (!values || typeof values!=='object') return null;
    const wanted = keys.map(nrm);
    for (const [key, raw] of Object.entries(values)) {
        if (wanted.includes(nrm(key))) return num(raw);
    }
    return null;
}

function weatherMetric(device, values) {
    const d=nrm(device);
    if (/pyranometer|pyrimeter/.test(d)) return ['radiation', valueFor(values,['raw data'])];
    if (/pannel.*temp|panel.*temp|module.*temp/.test(d)) return ['panelTemp', valueFor(values,['pannel temperature','panel temperature','module temperature'])];
    if (/ambient.*temp/.test(d) || d==='ambient') return ['ambientTemp', valueFor(values,['ambient temperature'])];
    if (/^wind$|wind.*speed|anemometer/.test(d)) return ['windSpeed', valueFor(values,['windspeed','wind speed'])];
    if (/^humidity$|relative humidity/.test(d)) return ['humidity', valueFor(values,['humidity','relative humidity'])];
    return ['', null];
}

function isWeatherTask(task) {
    return /^(wmos|wmas|weather)$/i.test(String(task||'').trim());
}

function isInverter(device, task) {
    return /\binverter\b/i.test(String(device||'')) || /inverter/i.test(String(task||''));
}

function inverterNumber(device) {
    const m=String(device||'').match(/(?:inverter|inv)[\s_-]*(\d{1,2})/i);
    return m ? parseInt(m[1],10) : null;
}

function applyFrame(unitId, task, device, values, time) {
    if (!states[unitId] || !values || typeof values!=='object') return;
    const taskText=String(task||'').toLowerCase();
    const dev=nrm(device);
    const received=textTime(time);
    states[unitId].lastSeen=Date.now();

    if (taskText==='vcb' || dev.includes('vcb')) {
        const p=valueFor(values,['3 Phase Active Power']);
        if (p!==null) states[unitId].vcbPower=p;
        const ex=valueFor(values,['Active Total Export']);
        const im=valueFor(values,['Active Total Import']);
        const pf=valueFor(values,['Q1 PF','Power Factor']);
        if (ex!==null) states[unitId].vcbExport=ex;
        if (im!==null) states[unitId].vcbImport=im;
        if (pf!==null) states[unitId].vcbPf=pf;
        return;
    }

    if (taskText==='transformer' || dev.includes('transformer')) {
        const oil=valueFor(values,['oil-temp','oil temp','oil temperature']);
        const wt=valueFor(values,['winding-temp','winding temp','winding temperature']);
        if (oil!==null) states[unitId].oilTemp=oil;
        if (wt!==null) states[unitId].windingTemp=wt;
        return;
    }

    if (isWeatherTask(taskText)) {
        const [metric,val]=weatherMetric(device,values);
        if (!metric) return;
        states[unitId][metric]=val;
        states[unitId].wmosLast=received;
        return;
    }

    if (isInverter(device,taskText)) {
        const n=inverterNumber(device);
        if (!n || n<1 || n>8) return;
        const key='INV-'+String(n).padStart(2,'0');
        const powerCandidates=[];
        const dailyCandidates=[];
        Object.entries(values).forEach(([keyName,raw])=>{
            const keyNorm=nrm(keyName);
            const value=num(raw);
            if (value===null) return;
            if (/active.*power|ac.*power|power.*ac|a c .*power/.test(keyNorm) && !/reactive|apparent|limit|ratio|3 phase/.test(keyNorm)) powerCandidates.push(value);
            if (/daily.*generation|daily.*gen|today.*generation|today.*gen/.test(keyNorm)) dailyCandidates.push(value);
        });
        if (powerCandidates.length) states[unitId].inverters[key].power=powerCandidates[0];
        if (dailyCandidates.length) states[unitId].inverters[key].daily=dailyCandidates[0];
        states[unitId].inverters[key].lastSeen=received;
    }
}

function walk(node, inheritedUnit='', inheritedTask='', inheritedDevice='', inheritedTime='', depth=0) {
    if (!node || typeof node!=='object' || depth>8) return;
    const unit=String(node.unit_id || node.unitId || node.request?.unit_id || node.request?.unitId || inheritedUnit || '').trim();
    const task=node.task || node.pageName || inheritedTask || '';
    const device=node.device || node.deviceName || node.sensor || inheritedDevice || '';
    const time=node.time || node.timestamp || node.ts || node.recorded_at || inheritedTime || '';

    if (node.values && typeof node.values==='object' && !Array.isArray(node.values)) {
        applyFrame(unit,task,device,node.values,time);
    }
    if (Array.isArray(node.data)) node.data.forEach(x=>walk(x,unit,task,device,time,depth+1));
    else if (node.data && typeof node.data==='object') walk(node.data,unit,task,device,time,depth+1);
    if (node.payload && typeof node.payload==='object') walk(node.payload,unit,task,device,time,depth+1);
    if (node.result && typeof node.result==='object') walk(node.result,unit,task,device,time,depth+1);
}

function consume(message) {
    if (!message || typeof message!=='object') return;
    const unit=String(message.unit_id || message.unitId || message.request?.unit_id || message.request?.unitId || '').trim();
    if (message.values && typeof message.values==='object' && !Array.isArray(message.values)) {
        applyFrame(unit,message.task || message.pageName || '',message.device || message.deviceName || '',message.values,message.time || message.timestamp || message.ts || message.recorded_at || '');
    }
    walk(message.data,unit,message.task || message.pageName || '',message.device || message.deviceName || '',message.time || message.timestamp || message.ts || '',0);
    walk(message.payload,unit,message.task || message.pageName || '',message.device || message.deviceName || '',message.time || message.timestamp || message.ts || '',0);
    walk(message.result,unit,message.task || message.pageName || '',message.device || message.deviceName || '',message.time || message.timestamp || message.ts || '',0);
}

function esc(v) {
    return String(v ?? '').replace(/[&<>"]/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[ch]));
}

function fmt(v, decimals=1, suffix='') {
    return v===null || v===undefined || !Number.isFinite(Number(v)) ? '--' : Number(v).toFixed(decimals)+suffix;
}

function inverterLine(x, y, key, data) {
    const power=fmt(data.power,1,' kW');
    const daily=fmt(data.daily,1,' kWh');
    const live=data.lastSeen && (Date.now()-data.lastSeen)<=7000;
    const stroke=live ? '#10b981' : '#0f172a';
    return [
        '<line x1="'+x+'" y1="'+(y-34)+'" x2="'+x+'" y2="'+(y-4)+'" stroke="'+stroke+'" stroke-width="2.5"/>',
        '<path d="M '+(x-5)+' '+(y-10)+' L '+x+' '+y+' L '+(x+5)+' '+(y-10)+'" stroke="'+stroke+'" stroke-width="2" fill="none"/>',
        '<circle cx="'+x+'" cy="'+(y+20)+'" r="19" fill="#fff" stroke="'+stroke+'" stroke-width="2.5"/>',
        '<path d="M '+(x-10)+' '+(y+20)+' q 5 -10 10 0 t 10 0" stroke="'+stroke+'" stroke-width="1.7" fill="none"/>',
        '<text x="'+x+'" y="'+(y+45)+'" text-anchor="middle" class="component-title label">'+esc(key)+'</text>',
        '<text x="'+x+'" y="'+(y+61)+'" text-anchor="middle" class="component-value" fill="'+(live?'#059669':'#64748b')+'">'+esc(power)+'</text>',
        '<text x="'+x+'" y="'+(y+75)+'" text-anchor="middle" class="small-value muted">'+esc(daily)+'</text>'
    ].join('');
}

function renderDiagram(id) {
    const p=PLANTS[id], s=states[id];
    const invKeys=Object.keys(s.inverters).sort((a,b)=>parseInt(a.slice(4))-parseInt(b.slice(4)));
    const liveAge=s.lastSeen ? Date.now()-s.lastSeen : Infinity;
    const plantLive=liveAge<=7000;
    const statusText=plantLive?'LIVE':'WAITING';
    const statusFill=plantLive?'#059669':'#64748b';
    const vcbState=s.vcbPower!==null ? (s.vcbPower>0.05?'CLOSED':'OPEN') : '--';

    const invXs=[80,145,210,275,340,405,470,535];
    const invY=570;
    const invLines=invKeys.map((key,i)=>inverterLine(invXs[i],invY,key,s.inverters[key])).join('');

    return '<svg class="sld-svg" viewBox="0 0 615 760" role="img" aria-label="'+esc(p.name)+' live single line diagram">' +
        '<rect x="8" y="8" width="599" height="744" rx="10" fill="#fff" stroke="#cbd5e1" stroke-width="1.5"/>' +
        '<text x="30" y="42" class="plant-title label">'+esc(p.name.toUpperCase())+'</text>' +
        '<text x="30" y="60" class="section-title muted">LIVE SINGLE LINE DIAGRAM · '+esc(p.capacity.toUpperCase())+'</text>' +
        '<circle class="status-dot" cx="575" cy="37" r="5" fill="'+statusFill+'"/>' +
        '<text x="585" y="41" text-anchor="end" font-size="9" font-weight="900" fill="'+statusFill+'">'+statusText+'</text>' +

        '<text x="307" y="92" text-anchor="middle" class="section-title muted">33 kV EB INCOMING</text>' +
        '<text x="307" y="108" text-anchor="middle" class="small-value muted">50 Hz</text>' +
        '<line x1="307" y1="115" x2="307" y2="150" class="wire"/>' +
        '<path d="M300 142 L307 153 L314 142" class="wire"/>' +

        '<circle cx="270" cy="175" r="16" class="symbol"/>' +
        '<text x="270" y="179" text-anchor="middle" font-size="9" font-weight="900">LA</text>' +
        '<line x1="286" y1="175" x2="307" y2="175" class="wire"/>' +
        '<circle cx="344" cy="175" r="16" class="symbol"/>' +
        '<text x="344" y="179" text-anchor="middle" font-size="9" font-weight="900">PT</text>' +
        '<line x1="328" y1="175" x2="307" y2="175" class="wire"/>' +
        '<line x1="307" y1="153" x2="307" y2="205" class="wire"/>' +
        '<circle cx="307" cy="220" r="19" class="symbol"/>' +
        '<text x="307" y="224" text-anchor="middle" font-size="10" font-weight="900">CT</text>' +
        '<line x1="307" y1="239" x2="307" y2="280" class="wire"/>' +

        '<circle cx="307" cy="302" r="26" class="symbol"/>' +
        '<circle cx="307" cy="302" r="14" fill="#fff" stroke="#0f172a" stroke-width="2"/>' +
        '<text x="307" y="299" text-anchor="middle" font-size="8" font-weight="900">VCB</text>' +
        '<text x="307" y="311" text-anchor="middle" font-size="8" font-weight="900" fill="'+(vcbState==='CLOSED'?'#059669':'#64748b')+'">'+vcbState+'</text>' +
        '<line x1="307" y1="328" x2="307" y2="366" class="'+(vcbState==='CLOSED'?'wire-live':'wire')+'"/>' +

        '<text x="30" y="150" class="metric-title">ACTIVE EXPORT</text><text x="30" y="168" class="metric-value">'+esc(fmt(s.vcbExport,2,' kWh'))+'</text>' +
        '<text x="30" y="195" class="metric-title">ACTIVE IMPORT</text><text x="30" y="213" class="metric-value">'+esc(fmt(s.vcbImport,2,' kWh'))+'</text>' +
        '<text x="485" y="150" class="metric-title">VCB POWER</text><text x="485" y="168" class="metric-value">'+esc(fmt(s.vcbPower,2,' kW'))+'</text>' +
        '<text x="485" y="195" class="metric-title">POWER FACTOR</text><text x="485" y="213" class="metric-value">'+esc(fmt(s.vcbPf,2,''))+'</text>' +

        '<line x1="307" y1="366" x2="307" y2="402" class="wire"/>' +
        '<circle cx="307" cy="430" r="22" class="symbol"/>' +
        '<circle cx="307" cy="430" r="13" fill="#fff" stroke="#0f172a" stroke-width="2"/>' +
        '<text x="307" y="427" text-anchor="middle" font-size="8" font-weight="900">TX</text>' +
        '<text x="307" y="438" text-anchor="middle" font-size="8" font-weight="900">ΔY</text>' +
        '<text x="307" y="466" text-anchor="middle" class="component-title label">POWER TRANSFORMER</text>' +
        '<text x="307" y="481" text-anchor="middle" class="small-value muted">33 kV / 800 V</text>' +
        '<text x="150" y="443" class="metric-title">OTI</text><text x="150" y="460" class="metric-value">'+esc(fmt(s.oilTemp,1,' °C'))+'</text>' +
        '<text x="445" y="443" class="metric-title">WTI</text><text x="445" y="460" class="metric-value">'+esc(fmt(s.windingTemp,1,' °C'))+'</text>' +

        '<line x1="307" y1="492" x2="307" y2="525" class="wire"/>' +
        '<line x1="52" y1="525" x2="562" y2="525" stroke="#0f172a" stroke-width="6" stroke-linecap="round"/>' +
        '<text x="307" y="514" text-anchor="middle" class="section-title muted">800 V AC BUS</text>' +

        invLines +

        '<line x1="52" y1="650" x2="562" y2="650" stroke="#94a3b8" stroke-width="1"/>' +
        '<text x="307" y="674" text-anchor="middle" class="section-title muted">WMOS / WMAS LIVE WEATHER</text>' +
        '<text x="65" y="699" class="metric-title">RAD</text><text x="65" y="717" class="metric-value">'+esc(fmt(s.radiation,0,' W/m²'))+'</text>' +
        '<text x="180" y="699" class="metric-title">PANEL</text><text x="180" y="717" class="metric-value">'+esc(fmt(s.panelTemp,1,' °C'))+'</text>' +
        '<text x="300" y="699" class="metric-title">AMBIENT</text><text x="300" y="717" class="metric-value">'+esc(fmt(s.ambientTemp,1,' °C'))+'</text>' +
        '<text x="420" y="699" class="metric-title">WIND</text><text x="420" y="717" class="metric-value">'+esc(fmt(s.windSpeed,1,' m/s'))+'</text>' +
        '<text x="535" y="699" text-anchor="end" class="metric-title">HUM</text><text x="535" y="717" text-anchor="end" class="metric-value">'+esc(fmt(s.humidity,1,' %RH'))+'</text>' +
        '<text x="307" y="739" text-anchor="middle" class="small-value muted">Last SCADA sample: '+(s.wmosLast?new Date(s.wmosLast).toLocaleTimeString('en-IN',{hour12:false}):'--')+'</text>' +
    '</svg>';
}

function renderAll() {
    document.getElementById('diagramGrid').innerHTML=Object.keys(PLANTS).map(id=>'<div class="diagram-shell">'+renderDiagram(id)+'</div>').join('');
    const anyLive=Object.values(states).some(s=>s.lastSeen && Date.now()-s.lastSeen<=7000);
    document.getElementById('sldLiveDot').className='w-2.5 h-2.5 rounded-full '+(anyLive?'bg-emerald-500 animate-pulse':'bg-slate-400');
    document.getElementById('sldLiveStatus').textContent=anyLive?'Live SCADA':'Waiting for live SCADA';
}

function connectWS() {
    const socket=new WebSocket(WS_URL);
    socket.onopen=()=>{
        document.getElementById('sldLiveDot').className='w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse';
        document.getElementById('sldLiveStatus').textContent='Live SCADA';
        Object.keys(PLANTS).forEach(id=>{
            socket.send(JSON.stringify({type:'subscribe',unit_id:id}));
            socket.send(JSON.stringify({type:'get_devices',unit_id:id}));
        });
    };
    socket.onmessage=event=>{
        try {
            const message=JSON.parse(event.data);
            consume(message);
            renderAll();
        } catch (_) {}
    };
    socket.onclose=()=>{
        document.getElementById('sldLiveDot').className='w-2.5 h-2.5 rounded-full bg-red-500';
        document.getElementById('sldLiveStatus').textContent='Reconnecting...';
        setTimeout(connectWS,2500);
    };
    socket.onerror=()=>{};
}

fetch('sidebar.html',{cache:'no-store'}).then(r=>r.text()).then(html=>{
    const holder=document.getElementById('sidebar-container');
    holder.innerHTML=html;
    holder.querySelectorAll('script').forEach(oldScript=>{
        const s=document.createElement('script');
        s.textContent=oldScript.textContent;
        oldScript.replaceWith(s);
    });
    const sidebar=document.getElementById('sidebar');
    const overlay=document.getElementById('overlay');
    document.getElementById('menuBtn')?.addEventListener('click',()=>{
        sidebar?.classList.remove('-translate-x-full');
        overlay?.classList.remove('hidden');
    });
    document.getElementById('closeSidebarBtn')?.addEventListener('click',()=>{
        sidebar?.classList.add('-translate-x-full');
        overlay?.classList.add('hidden');
    });
    overlay?.addEventListener('click',()=>{
        sidebar?.classList.add('-translate-x-full');
        overlay?.classList.add('hidden');
    });
}).catch(()=>{});

renderAll();
connectWS();
setInterval(()=>{
    document.getElementById('clockDisplay').textContent=new Date().toLocaleTimeString('en-IN',{hour12:false});
    renderAll();
},1000);
</script>
</body>
</html>