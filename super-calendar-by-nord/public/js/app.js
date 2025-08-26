(function(){
  const rootId = 'scbn-root';

  function h(tag, props={}, ...children){
    const el = document.createElement(tag);
    Object.entries(props||{}).forEach(([k,v])=>{
      if(k==='class') el.className=v;
      else if(k==='style') Object.assign(el.style, v);
      else if(k.startsWith('on') && typeof v==='function') el.addEventListener(k.substring(2).toLowerCase(), v);
      else if(v!==undefined && v!==null) el.setAttribute(k, v);
    });
    children.flat().forEach(c=>{
      if (c===null || c===undefined) return;
      if (typeof c==='string') el.appendChild(document.createTextNode(c));
      else el.appendChild(c);
    });
    return el;
  }

  function fmtMonth(year, month){
    const d = new Date(Date.UTC(year, month-1, 1));
    return d.toLocaleString(undefined, {month:'long', year:'numeric'});
  }

  function getDaysInMonth(year, month){
    return new Date(year, month, 0).getDate();
  }

  function pad(n){ return n<10 ? '0'+n : ''+n; }
  function todayStr(){
    const t = new Date();
    return `${t.getFullYear()}-${pad(t.getMonth()+1)}-${pad(t.getDate())}`;
  }
  function isPastDate(ymd){
    return ymd < todayStr();
  }

  function CalendarApp(container){
    const theme = (window.SCBN && SCBN.theme) || '#66BB6A';
    container.innerHTML = '';

    const layout = h('div', {class:'scbn-grid'},
      h('div', {class:'scbn-left'},
        h('div', {class:'scbn-monthbar'},
          h('button', {class:'scbn-nav', id:'prevBtn'}, '◀'),
          h('div', {class:'scbn-monthlabel', id:'monthLabel'}),
          h('button', {class:'scbn-nav', id:'nextBtn'}, '▶')
        ),
        h('div', {class:'scbn-calendar', id:'cal'})
      ),
      h('div', {class:'scbn-right'},
        h('div', {class:'scbn-panel'},
          h('h3', null, (SCBN?.strings?.selectTime)||'Select a time'),
          h('div', {id:'times'}),
          h('div', {class:'scbn-form'},
            h('label', null, (SCBN?.strings?.name)||'Name'),
            h('input', {id:'name', type:'text', placeholder:'Jane Doe'}),
            h('label', null, (SCBN?.strings?.phone)||'Phone'),
            h('input', {id:'phone', type:'text', placeholder:'+64 ...'}),
            h('label', null, (SCBN?.strings?.email)||'Email'),
            h('input', {id:'email', type:'email', placeholder:'you@example.com'}),
            h('button', {id:'bookBtn', class:'scbn-book'}, (SCBN?.strings?.book)||'Book Appointment'),
            h('div', {id:'msg', class:'scbn-msg'})
          )
        )
      )
    );
    container.appendChild(layout);

    let today = new Date();
    const tz = SCBN?.tz||undefined;
    let viewYear = today.getFullYear();
    let viewMonth = today.getMonth() + 1;
    let selectedDate = null;
    let selectedHour = null;
    let bookedMap = {}; // { 'YYYY-MM-DD': [8,9,...] }

    const monthLabel = layout.querySelector('#monthLabel');
    const calEl = layout.querySelector('#cal');
    const prevBtn = layout.querySelector('#prevBtn');
    const nextBtn = layout.querySelector('#nextBtn');
    const timesEl = layout.querySelector('#times');
    const msgEl = layout.querySelector('#msg');
    const bookBtn = layout.querySelector('#bookBtn');

    function buildCalendar(){
      calEl.innerHTML='';
      const grid = h('div', {class:'scbn-calgrid'});
      const header = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
      const head = h('div', {class:'scbn-row scbn-head'}, header.map(d=>h('div', {class:'scbn-cell'}, d)));
      grid.appendChild(head);

      const first = new Date(viewYear, viewMonth-1, 1);
      const startDay = first.getDay();
      const days = getDaysInMonth(viewYear, viewMonth);
      let day = 1;
      for(let r=0; r<6; r++){
        const row = h('div', {class:'scbn-row'});
        for(let c=0; c<7; c++){
          const idx = r*7+c;
          const cell = h('div', {class:'scbn-cell scbn-day'});
          if (idx >= startDay && day<=days){
            const dstr = `${viewYear}-${pad(viewMonth)}-${pad(day)}`;
            const isToday = (dstr === `${today.getFullYear()}-${pad(today.getMonth()+1)}-${pad(today.getDate())}`);
            const isPast = isPastDate(dstr);
            const btn = h('button', {type:'button', class:'scbn-datebtn'+(isToday?' scbn-today':'')+(isPast?' scbn-disabled':''), 'data-date':dstr, disabled:isPast}, ''+day);
            btn.addEventListener('click', ()=>{
              selectedDate = dstr;
              renderTimes();
              // highlight selection
              calEl.querySelectorAll('.scbn-datebtn').forEach(b=>b.classList.remove('scbn-selected'));
              btn.classList.add('scbn-selected');
            });
            cell.appendChild(btn);
            day++;
          }
          row.appendChild(cell);
        }
        grid.appendChild(row);
      }
      calEl.appendChild(grid);
      monthLabel.textContent = fmtMonth(viewYear, viewMonth);
    }

    function renderTimes(){
      timesEl.innerHTML='';
      const taken = bookedMap[selectedDate] || [];
      for(let hour=8; hour<=18; hour++){
        const label = `${pad(hour)}:00–${pad(hour+1)}:00`;
        const isTaken = taken.includes(hour);
        const btn = h('button', {type:'button', class:'scbn-slot'+(isTaken?' scbn-slot-taken':''), disabled:isTaken, 'aria-pressed': 'false'}, label);
        btn.addEventListener('click', ()=>{
          if (isTaken) return;
          selectedHour = hour;
          timesEl.querySelectorAll('.scbn-slot').forEach(b=>{ b.classList.remove('scbn-slot-selected'); b.setAttribute('aria-pressed','false'); });
          btn.classList.add('scbn-slot-selected');
          btn.setAttribute('aria-pressed','true');
        });
        timesEl.appendChild(btn);
      }
    }

    async function fetchMonth(){
      bookedMap = {};
      try{
        const resp = await fetch(`${SCBN.restUrl}/bookings?year=${viewYear}&month=${viewMonth}`);
        bookedMap = await resp.json();
      }catch(e){ bookedMap = {}; }
    }

    async function syncAndRender(){
      await fetchMonth();
      buildCalendar();
      // Auto-select today if in view
      const todayStr = `${today.getFullYear()}-${pad(today.getMonth()+1)}-${pad(today.getDate())}`;
      if (today.getFullYear()===viewYear && (today.getMonth()+1)===viewMonth){
        selectedDate = todayStr;
      } else {
        selectedDate = `${viewYear}-${pad(viewMonth)}-01`;
      }
      renderTimes();
    }

    prevBtn.addEventListener('click', async ()=>{ 
      selectedHour=null;
      if (viewMonth===1){ viewMonth=12; viewYear--; } else viewMonth--;
      await syncAndRender();
    });
    nextBtn.addEventListener('click', async ()=>{ 
      selectedHour=null;
      if (viewMonth===12){ viewMonth=1; viewYear++; } else viewMonth++;
      await syncAndRender();
    });

    bookBtn.addEventListener('click', async ()=>{
      const name = document.getElementById('name').value.trim();
      const phone = document.getElementById('phone').value.trim();
      const email = document.getElementById('email').value.trim();
      if (!name || !email || !selectedDate || selectedHour===null){
        msgEl.textContent = (SCBN?.strings?.invalid)||'Please fill all required fields and choose a slot.';
        msgEl.className = 'scbn-msg scbn-error';
        return;
      }
      try{
        const resp = await fetch(`${SCBN.restUrl}/bookings`, {
          method:'POST',
          headers:{
            'Content-Type':'application/json',
            'X-WP-Nonce': SCBN.nonce
          },
          body: JSON.stringify({name, phone, email, date: selectedDate, hour: selectedHour})
        });
        if (!resp.ok){
          const jt = await resp.json().catch(()=>({}));
          throw new Error(jt?.message || 'Request failed');
        }
        msgEl.textContent = (SCBN?.strings?.success)||'Booked!';
        msgEl.className = 'scbn-msg scbn-ok';
        // mark slot as taken
        bookedMap[selectedDate] = bookedMap[selectedDate] || [];
        bookedMap[selectedDate].push(selectedHour);
        renderTimes();
      }catch(e){
        msgEl.textContent = (SCBN?.strings?.taken)||'This slot is already booked.';
        msgEl.className = 'scbn-msg scbn-error';
      }
    });

    syncAndRender();
  }

  document.addEventListener('DOMContentLoaded', function(){
    const root = document.getElementById('scbn-root');
    if (root) CalendarApp(root);
  });
})();