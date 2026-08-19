document.addEventListener('DOMContentLoaded',()=>{
  const sidebar=document.querySelector('.sidebar');
  document.querySelectorAll('[data-sidebar-toggle]').forEach(button=>button.addEventListener('click',()=>sidebar?.classList.toggle('open')));
  document.querySelectorAll('[data-modal-open]').forEach(button=>button.addEventListener('click',()=>document.getElementById(button.dataset.modalOpen)?.classList.add('open')));
  document.querySelectorAll('[data-modal-close]').forEach(button=>button.addEventListener('click',()=>button.closest('.modal')?.classList.remove('open')));
  document.querySelectorAll('.modal').forEach(modal=>modal.addEventListener('click',event=>{if(event.target===modal)modal.classList.remove('open')}));
  document.addEventListener('keydown',event=>{if(event.key==='Escape')document.querySelectorAll('.modal.open').forEach(modal=>modal.classList.remove('open'))});
  document.querySelectorAll('[data-confirm]').forEach(element=>element.addEventListener('click',event=>{if(!confirm(element.dataset.confirm||'Lanjutkan tindakan ini?'))event.preventDefault()}));
  document.querySelectorAll('[data-search-table]').forEach(input=>input.addEventListener('input',()=>{
    const table=document.querySelector(input.dataset.searchTable); if(!table)return;
    const needle=input.value.trim().toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row=>row.hidden=!row.textContent.toLowerCase().includes(needle));
  }));
  document.querySelectorAll('[data-score-group]').forEach(group=>{
    const update=()=>{const scores=[...group.querySelectorAll('[data-score]')].map(x=>Number(x.value||0));const avg=scores.length?scores.reduce((a,b)=>a+b,0)/scores.length:0;const output=group.querySelector('[data-average]');if(output)output.textContent=avg.toFixed(2)};
    group.querySelectorAll('[data-score]').forEach(input=>input.addEventListener('input',update));update();
  });
  document.querySelectorAll('form[data-ajax]').forEach(form=>form.addEventListener('submit',async event=>{
    event.preventDefault(); const submit=form.querySelector('[type=submit]'); submit?.setAttribute('disabled','disabled'); form.classList.add('loading');
    try{const response=await fetch(form.action,{method:form.method||'POST',body:new FormData(form),headers:{Accept:'application/json'}});const payload=await response.json();alert(payload.message);if(payload.success){if(form.dataset.redirect)location.href=form.dataset.redirect;else location.reload()}}
    catch(error){alert('Terjadi gangguan saat mengirim data.')}finally{submit?.removeAttribute('disabled');form.classList.remove('loading')}
  }));
});

