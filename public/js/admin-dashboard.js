(function () {
    const charts = {};
    const $ = (selector) => document.querySelector(selector);
    const $$ = (selector) => [...document.querySelectorAll(selector)];
    let caseDetail = 'posesion';
    let eventsPage = 1;
    let currentPayload = null;
    const colors = ['#26875d', '#9bc65d', '#3f91a0', '#d28b4d', '#9d6ba6', '#d36e82', '#7693bc'];
    const eventNames = {
        page_view: 'Visita', session_start: 'Inicio de sesión', case_view: 'Vista de caso', case_select: 'Selección de caso',
        requirements_view: 'Requisitos', steps_view: 'Pasos', checklist_start: 'Inicio de lista', checklist_progress: 'Avance de lista',
        checklist_complete: 'Lista completada', help_click: 'Orientación', navigation_click: 'Navegación', page_exit: 'Salida'
    };

    function params() {
        const query = new URLSearchParams({period: $('#period').value, case: $('#caseFilter').value, device: $('#device').value, detail_case: caseDetail, page: String(eventsPage)});
        if ($('#period').value === 'custom') { query.set('from', $('#from').value); query.set('to', $('#to').value); }
        return query;
    }
    function emptyState(id, empty) {
        const box = $('#' + id);
        if (box) box.classList.toggle('is-empty', empty);
    }
    function chart(id, element, config, empty) {
        emptyState(element, empty);
        if (charts[id]) charts[id].destroy();
        if (!empty && window.Chart) charts[id] = new Chart($('#' + id), config);
    }
    function fmt(value) { return new Intl.NumberFormat('es-PE').format(value || 0); }
    function esc(value) { return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char])); }
    function aggregateTrend(rows, granularity) {
        const map = new Map();
        rows.forEach((row) => {
            const date = new Date(row.date + 'T12:00:00');
            let key, label;
            if (granularity === 'month') { key = row.date.slice(0, 7); label = key; }
            else if (granularity === 'week') { const day = (date.getDay() + 6) % 7; date.setDate(date.getDate() - day); key = date.toISOString().slice(0, 10); label = 'Sem. ' + date.toLocaleDateString('es-PE', {day:'2-digit', month:'short'}); }
            else { key = row.date; label = date.toLocaleDateString('es-PE', {day:'2-digit', month:'short'}); }
            if (!map.has(key)) map.set(key, {label, visits:0, sessions:0, unique:0});
            const item = map.get(key); item.visits += row.visits; item.sessions += row.sessions; item.unique += row.unique;
        });
        return [...map.values()];
    }
    function drawTrend(data) {
        const grouped = aggregateTrend(data.trend, $('#granularity').value);
        chart('trendChart', 'trendBox', {type:'line',data:{labels:grouped.map(x=>x.label),datasets:[
            {label:data.trend_mode==='case'?'Vistas del caso':'Visitas',data:grouped.map(x=>x.visits),borderColor:colors[0],backgroundColor:'#26875d20',fill:true,tension:.35,pointRadius:2},
            {label:data.trend_mode==='case'?'Selecciones':'Sesiones',data:grouped.map(x=>x.sessions),borderColor:colors[1],backgroundColor:'transparent',tension:.35,pointRadius:2},
            {label:'Sesiones únicas',data:grouped.map(x=>x.unique),borderColor:colors[2],backgroundColor:'transparent',tension:.35,pointRadius:2}
        ]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'bottom',labels:{boxWidth:9,font:{size:9}}}},scales:{x:{grid:{display:false},ticks:{font:{size:9},maxRotation:0,autoSkip:true}},y:{beginAtZero:true,ticks:{precision:0,font:{size:9}},grid:{color:'#edf0ed'}}}}},!data.has_data);
    }
    function drawCases(data) {
        chart('caseChart','caseBox',{type:'bar',data:{labels:data.cases.map(x=>'Caso '+(data.cases.indexOf(x)+1)),datasets:[{label:'Sesiones únicas',data:data.cases.map(x=>x.unique),backgroundColor:colors.slice(0,4),borderRadius:6,maxBarThickness:42}]},options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',plugins:{legend:{display:false},tooltip:{callbacks:{afterLabel:(ctx)=>{const item=data.cases[ctx.dataIndex];return `${item.share}% · ${item.title}`}}}},scales:{x:{beginAtZero:true,ticks:{precision:0,font:{size:9}},grid:{color:'#edf0ed'}},y:{grid:{display:false},ticks:{font:{size:9}}}},onClick:(_, elements)=>{if(elements.length){setCase(data.cases[elements[0].index].slug)}}}},!data.has_data);
    }
    function drawBehavior(data) {
        const deviceLabels=Object.keys(data.behavior.devices||{}), deviceValues=Object.values(data.behavior.devices||{});
        chart('deviceChart','deviceBox',{type:'doughnut',data:{labels:deviceLabels.map(x=>({desktop:'Computadora',mobile:'Celular',tablet:'Tableta',other:'Otro'}[x]||x)),datasets:[{data:deviceValues,backgroundColor:colors,borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,cutout:'63%',plugins:{legend:{position:'right',labels:{boxWidth:9,font:{size:9}}}}}},!data.has_data);
        const act=Object.entries(data.behavior.actions||{}).sort((a,b)=>b[1]-a[1]).slice(0,7);
        chart('actionChart','actionBox',{type:'bar',data:{labels:act.map(x=>eventNames[x[0]]||x[0]),datasets:[{data:act.map(x=>x[1]),backgroundColor:colors[0],borderRadius:5,maxBarThickness:22}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,ticks:{precision:0,font:{size:9}},grid:{color:'#edf0ed'}},y:{grid:{display:false},ticks:{font:{size:9}}}}}},!data.has_data);
        const hours=Array.from({length:24},(_,i)=>[String(i).padStart(2,'0')+':00',Number(data.behavior.hours?.[i]||0)]);
        chart('hourChart','hourBox',{type:'bar',data:{labels:hours.map(x=>x[0]),datasets:[{label:'Eventos',data:hours.map(x=>x[1]),backgroundColor:colors[2],borderRadius:3}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false},ticks:{font:{size:8},maxTicksLimit:8}},y:{beginAtZero:true,ticks:{precision:0,font:{size:9}},grid:{color:'#edf0ed'}}}}},!data.has_data);
        const weekdays=['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
        const weekdayData=weekdays.map((_,i)=>Number(data.behavior.weekdays?.[i+1]||0));
        chart('weekdayChart','weekdayBox',{type:'bar',data:{labels:weekdays,datasets:[{data:weekdayData,backgroundColor:colors[0],borderRadius:5}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false},ticks:{font:{size:8}}},y:{beginAtZero:true,ticks:{precision:0,font:{size:9}},grid:{color:'#edf0ed'}}}}},!data.has_data);
        const pages=Object.entries(data.behavior.pages||{});
        chart('pagesChart','pagesBox',{type:'bar',data:{labels:pages.map(x=>x[0]||'/'),datasets:[{data:pages.map(x=>x[1]),backgroundColor:colors[2],borderRadius:5}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,ticks:{precision:0,font:{size:9}},grid:{color:'#edf0ed'}},y:{grid:{display:false},ticks:{font:{size:9}}}}}},!data.has_data);
    }
    function drawFunnel(data) {
        const labels={page_view:'Ingresaron',case_select:'Eligieron un caso',requirements_view:'Vieron requisitos',steps_view:'Vieron pasos',checklist_start:'Iniciaron lista',checklist_complete:'Completaron lista',help_click:'Pidieron orientación'};
        const max=Math.max(1,...data.funnel.map(x=>x.sessions));
        $('#funnel').innerHTML=data.has_data?data.funnel.map((item,i)=>{const prev=i?data.funnel[i-1].sessions:item.sessions;const rate=i&&prev?Math.round(item.sessions/prev*100):100;return `<div class="funnel-row"><span>${labels[item.event]}</span><div class="funnel-bar"><span style="width:${Math.max(1,item.sessions/max*100)}%"></span></div><strong>${item.sessions} <small>${rate}%</small></strong></div>`}).join(''):'<p class="muted" style="font-size:11px">Aún no hay datos suficientes.</p>';
    }
    function drawCaseDetail(item) {
        const el=$('#caseDetails');
        if(!item){el.innerHTML='<p class="muted">No hay datos para este caso.</p>';return;}
        const rows=[['Visitas al caso',item.views],['Visitantes únicos',item.unique],['Participación',item.share+'%'],['Requisitos consultados',item.requirements],['Pasos consultados',item.steps],['Clics en orientación',item.help],['Listas iniciadas / completadas',item.checklist_start+' / '+item.checklist_complete],['Avances de lista / tiempo medio',item.checklist_progress+' / '+item.avg_seconds+' s']];
        el.innerHTML=rows.map(([label,value])=>`<div class="detail"><span>${label}</span><strong>${typeof value==='number'?fmt(value):esc(value)}</strong><small>${esc(item.title)}</small></div>`).join('');
    }
    function drawCaseTrend(rows, hasData) {
        const days=[...new Set(rows.map(row=>row.day))];
        const series=(type)=>days.map(day=>Number(rows.find(row=>row.day===day&&row.event_type===type)?.total||0));
        chart('caseTrendChart','caseTrendBox',{type:'line',data:{labels:days,datasets:[
            {label:'Vistas del caso',data:series('case_view'),borderColor:colors[0],tension:.3,pointRadius:2},
            {label:'Selecciones',data:series('case_select'),borderColor:colors[1],tension:.3,pointRadius:2}
        ]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{boxWidth:9,font:{size:9}}}},scales:{x:{grid:{display:false},ticks:{font:{size:8},maxTicksLimit:10}},y:{beginAtZero:true,ticks:{precision:0,font:{size:9}},grid:{color:'#edf0ed'}}}}},!hasData||rows.length===0);
    }
    function drawRecent(data) {
        const rows=data.recent.data||[];
        $('#eventRows').innerHTML=rows.length?rows.map(row=>`<tr><td>${esc(row.date)}</td><td><span class="badge" style="${row.simulated?'background:#fff1d6;color:#795b20':''}">${row.simulated?'Demo':'Real'}</span></td><td><span class="badge">${esc(eventNames[row.event]||row.event)}</span></td><td>${esc(row.case||'—')}</td><td>${esc(row.page||'—')}</td><td>${esc(row.device||'—')}</td><td>${esc(row.browser||'—')}</td><td>${row.duration_seconds?fmt(row.duration_seconds)+' s':'—'}</td></tr>`).join(''):'<tr><td colspan="8" class="muted">Aún no hay eventos para estos filtros.</td></tr>';
        $('#pageInfo').textContent=rows.length?`Página ${data.recent.current_page} de ${data.recent.last_page} · ${data.recent.total} eventos`:'Sin registros';
        $('#prevPage').disabled=data.recent.current_page<=1;$('#nextPage').disabled=data.recent.current_page>=data.recent.last_page;
    }
    function drawSatisfaction(data) {
        $('#surveyAverage').textContent=data.count?Number(data.average).toFixed(1):'—';
        $('#surveyCount').textContent=`${fmt(data.count)} ${data.count===1?'respuesta':'respuestas'}`;
        $('#surveySource').textContent=`${fmt(data.count-data.demo_count)} reales · ${fmt(data.demo_count)} demo`;
        const max=Math.max(1,...data.ratings.map(item=>item.count));
        $('#surveyBars').innerHTML=data.ratings.map(item=>`<div class="survey-row"><span class="survey-stars-text">${item.rating} ${item.rating===1?'estrella':'estrellas'}</span><div class="survey-bar"><span style="width:${item.count?Math.max(2,item.count/max*100):0}%"></span></div><strong>${fmt(item.count)}</strong></div>`).join('');
        $('#surveyRows').innerHTML=data.recent.length?data.recent.map(item=>`<tr><td>${esc(item.date)}</td><td><span class="badge" style="${item.simulated?'background:#fff1d6;color:#795b20':''}">${item.simulated?'Demo (simulada)':'Real'}</span></td><td>${esc(item.case||'—')}</td><td><span class="survey-stars-text">${'★'.repeat(item.rating)}${'☆'.repeat(5-item.rating)}</span> <strong>${item.rating}/5</strong></td></tr>`).join(''):'<tr><td colspan="4" class="muted">Todavía no hay respuestas para estos filtros.</td></tr>';
    }
    function setCase(slug) {caseDetail=slug;$$('.tab').forEach(t=>t.classList.toggle('active',t.dataset.case===slug));load();}
    function setCards(cards) {Object.entries(cards).forEach(([key,value])=>{const el=$(`[data-card="${key}"]`);if(el)el.textContent=key==='checklist_rate'?value+'%':key==='avg_seconds'?value+'s':fmt(value);});}
    async function load() {
        const error=$('#errorBox');error.style.display='none';
        if($('#period').value==='custom'&&(!$('#from').value||!$('#to').value)){error.textContent='Selecciona las fechas de inicio y fin.';error.style.display='block';return;}
        const exportParams=params();$('#exportLink').href=window.dashboardConfig.exportUrl+'?'+exportParams.toString();$('#summaryExportLink').href=window.dashboardConfig.summaryExportUrl+'?'+exportParams.toString();$('#casesExportLink').href=window.dashboardConfig.casesExportUrl+'?'+exportParams.toString();
        try {const response=await fetch(window.dashboardConfig.dataUrl+'?'+params().toString(),{headers:{Accept:'application/json'}});if(!response.ok)throw new Error(response.status===403?'No tienes permisos para ver este panel.':'No se pudieron cargar las métricas.');const data=await response.json();currentPayload=data;const demoNotice=$('#demoNotice');const hasDemo=data.demo_sessions||data.satisfaction.demo_count;demoNotice.hidden=!hasDemo;demoNotice.innerHTML=hasDemo?`<strong>Datos de demostración:</strong> el periodo y los filtros incluyen ${fmt(data.demo_sessions)} sesiones simuladas y ${fmt(data.satisfaction.demo_count)} respuestas simuladas de satisfacción. Se muestran como “Demo” y no corresponden a visitantes reales.`:'';setCards(data.cards);drawTrend(data);drawCases(data);drawBehavior(data);drawFunnel(data);drawCaseDetail(data.case_detail);drawCaseTrend(data.case_trend,data.has_data);drawRecent(data);drawSatisfaction(data.satisfaction);}
        catch(e){error.textContent=e.message;error.style.display='block';}
    }
    $('#apply').addEventListener('click',()=>{eventsPage=1;load();});
    $('#period').addEventListener('change',()=>{$$('.custom-date').forEach(x=>x.hidden=$('#period').value!=='custom');});
    $('#granularity').addEventListener('change',()=>currentPayload&&drawTrend(currentPayload));
    $('#prevPage').addEventListener('click',()=>{eventsPage=Math.max(1,eventsPage-1);load();});
    $('#nextPage').addEventListener('click',()=>{eventsPage++;load();});
    $$('.tab').forEach(tab=>tab.addEventListener('click',()=>setCase(tab.dataset.case)));
    load();
})();
