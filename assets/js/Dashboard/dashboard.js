'use strict';
async function dashboard(rid){
 const d=await api('dashboard');
 const counts=Object.fromEntries(d.counts.map(x=>[x.status,Number(x.count)]));
 const active=(counts.Pending||0)+(counts.Approved||0)+(counts['Revision Required']||0)+(counts.Scheduled||0)+(counts['In Progress']||0);
 const total=Object.values(counts).reduce((sum,value)=>sum+value,0);
 const chart=`<section class="panel chart-panel" aria-label="Service status overview"><div class="panel-header"><div><h3>Service activity</h3><p>${total} total request${total===1?'':'s'} · Select a status to explore the requests.</p></div></div>${total?`<div class="service-chart">${['Pending','Approved','Revision Required','Scheduled','In Progress','Completed','Cancelled'].map(status=>{const count=counts[status]||0,percent=total?Math.round(count/total*100):0;return `<a class="chart-bar" href="#services/${encodeURIComponent(status)}" aria-label="${esc(status)}: ${count} requests, ${percent}% of all requests"><span>${esc(status)}</span><strong>${count}</strong><span class="chart-track" aria-hidden="true"><span style="width:${percent}%"></span></span><small>${percent}% of requests</small></a>`;}).join('')}</div>`:empty('Your activity starts here','Service status and progress will appear when requests are created.','chart')}</section>`;
 const name=user.name.split(' ')[0];
 const technician=user.role==='technician';
 const customer=user.role==='customer';
 const staff=isStaff();
 let hero;
 if(staff){
  hero=`<section class="hero-card dashboard-operations" aria-label="Operations summary"><div><div class="eyebrow hero-kicker">${icon('clock')} OFFICE OPERATIONS</div><h2>Today, clearly <span>organized.</span></h2><p>Review pending appointments, quotations, revisions and low-stock items from one place.</p><div class="hero-actions"><a class="btn btn-primary" href="#schedule">Review schedule ${icon('arrow')}</a><a class="hero-secondary" href="#quotations">Review quotations &nearr;</a></div></div></section>`;
 }else if(technician){
  const next=d.summary.next_service;
  hero=`<section class="hero-card dashboard-operations" aria-label="Technician workspace"><div><div class="eyebrow hero-kicker">${icon('tool')} TECHNICIAN WORKSPACE</div><h2>Keep every <span>service moving.</span></h2><p>${next?`Next: ${esc(next.title)} · ${datetime(next.scheduled_at)}.`:'No upcoming assigned visit is currently confirmed.'}</p><div class="hero-actions"><a class="btn btn-primary" href="#schedule">Open my schedule ${icon('arrow')}</a></div></div></section>`;
 }else{
  hero=`<section class="hero-card hero-photo-card" aria-label="CCTV planning"><img class="hero-photo" src="assets/images/security-home.webp" width="1600" height="900" alt="Illustrative modern home with a security camera overlooking the entrance" fetchpriority="high"><div class="hero-copy"><div class="eyebrow hero-kicker">${icon('shield')} SMARTER PLANNING. GREATER PEACE OF MIND.</div><h2>A clear plan for<br><span>a safer home.</span></h2><p>Bring your house blueprint to life with suggested CCTV positions and a clear equipment and labor estimate.</p><div class="hero-actions"><a class="btn btn-primary" href="#planner">Explore CCTV planner ${icon('arrow')}</a><a class="hero-secondary" href="#messages">Talk to our team &nearr;</a></div></div><div class="hero-photo-caption">${icon('shield')}<span>Thoughtful coverage.<strong>A little more peace of mind.</strong></span></div></section>`;
 }
 let stats;
 if(staff){
  stats=stat('Active requests',active,'All open and in-progress work','tool')+stat('Pending appointments',d.summary.pending_appointments,'Awaiting office approval','calendar')+stat('Inventory products',d.products,'Active items in your catalog','box')+stat('Customers',d.customers,'Active customer accounts','users');
 }else if(technician){
  stats=stat("Today's jobs",d.summary.today_jobs,'Visits scheduled for today','calendar')+stat('Pending visits',d.summary.pending_appointments,'Appointment requests assigned','clock')+stat('In progress',counts['In Progress']||0,'Work currently underway','tool')+stat('Completed',counts.Completed||0,'Assigned work completed','check');
 }else{
  stats=stat('Active requests',active,'Open service requests','tool')+stat('Pending appointments',d.summary.pending_appointments,'Requests awaiting approval','calendar')+stat('Quotations to review',d.summary.pending_quotations,'Sent or revision requested','box')+stat('Completed services',counts.Completed||0,'All-time completed requests','check');
 }
 const next=d.summary.next_service;
 let nextPanel;
 if(staff){
  nextPanel=d.low_stock.length?d.low_stock.map(p=>`<a class="low-item" href="#inventory"><span class="product-symbol">${icon('box')}</span><div><strong>${esc(p.name)}</strong><small>Reorder at ${p.reorder_level} units</small></div><span class="stock-count">${p.stock} left</span></a>`).join(''):empty('Stock is in good shape','No products are below their reorder points.','check');
 }else if(technician){
  nextPanel=`<div class="dashboard-next"><span class="dashboard-next-icon">${icon(next?'tool':'check')}</span><div><strong>${next?esc(next.title):'No upcoming visit'}</strong><p>${next?(next.scheduled_at?datetime(next.scheduled_at):'Appointment to be confirmed'):'You have no upcoming assigned visit.'}</p></div><a class="btn btn-outline-primary" href="#schedule">${next?'Open schedule':'View schedule'} ${icon('arrow')}</a></div>`;
 }else{
  const nextLabel=next?(next.status==='In Progress'?'Work in progress':next.status==='Scheduled'?'Next appointment':'Request awaiting scheduling'):'No active service';
  nextPanel=`<div class="dashboard-next"><span class="dashboard-next-icon">${icon(next?'calendar':'check')}</span><div><strong>${next?esc(nextLabel):'You are all caught up'}</strong><p>${next?(next.scheduled_at?datetime(next.scheduled_at):esc(next.title)):d.summary.revision_requests?'There is a revision request to review.':'No appointment or service work is currently active.'}</p></div><a class="btn btn-outline-primary" href="${d.summary.pending_quotations?'#quotations':next?'#services':'#booking'}">${d.summary.pending_quotations?'Review quotation':next?'View service':'Book a visit'} ${icon('arrow')}</a></div>`;
 }
  render(heading(`Hello, ${name}.`,'Here’s what needs attention in your '+(customer?'customer':technician?'technician':'office')+' workspace today.',staff||customer?button('New service request','new-service'):'','OVERVIEW')+hero+`<div class="stats-grid">${stats}</div>${chart}<div class="dashboard-grid"><section class="panel dashboard-recent"><div class="panel-header"><div><h3>Recent service requests</h3><p>The latest work, all in one place.</p></div><a class="link-button" href="#services">View all →</a></div>${d.latest.length?serviceTable(d.latest):empty('A fresh start','Your service requests will appear here.','tool')}</section><section class="panel"><div class="panel-header"><div><h3>${staff?'Stock watch':'Your next step'}</h3><p>${staff?'Items at or below their reorder point.':'Clear, practical next action.'}</p></div></div><div class="panel-body">${nextPanel}</div></section></div>`,rid);
}
