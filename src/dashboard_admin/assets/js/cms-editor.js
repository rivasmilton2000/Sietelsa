(() => {
  'use strict';
  const config = window.SIETELSA_EDITOR;
  const frame = document.getElementById('websitePreview');
  const form = document.getElementById('visualForm');
  const fields = document.getElementById('fieldList');
  let original = null;
  let dirty = false;
  const setDirty = (value) => { dirty = value; document.getElementById('unsaved').hidden = !value; };
  const preview = (field, value) => frame.contentWindow.postMessage({type:'sietelsa:preview',entity:document.getElementById('entity').value,id:Number(document.getElementById('entityId').value),field,value}, config.origin);
  async function select(data) {
    const url = new URL(config.api, window.location.href);
    url.searchParams.set('entity', data.entity); url.searchParams.set('id', String(data.id));
    const response = await fetch(url, {credentials:'same-origin'});
    const result = await response.json();
    if (!result.ok) throw new Error(result.message);
    original = structuredClone(result);
    document.getElementById('editorEmpty').hidden = true; form.hidden = false;
    document.getElementById('entity').value = result.entity; document.getElementById('entityId').value = result.id;
    document.getElementById('selectionName').textContent = `${data.sectionKey} / ${data.contentKey}`;
    document.getElementById('contentStatus').textContent = result.status === 'published' ? 'Publicado' : 'Borrador';
    document.getElementById('mediaId').value = result.media_id ?? '';
    document.getElementById('sortOrder').value = result.sort_order; document.getElementById('visible').checked = result.visible;
    document.getElementById('updatedAt').textContent = `Última modificación: ${result.updated_at}`;
    fields.replaceChildren();
    Object.entries(result.data).forEach(([key,value]) => {
      if (typeof value !== 'string') return;
      const wrap=document.createElement('div'); wrap.className='mb-3';
      const label=document.createElement('label'); label.className='form-label'; label.textContent=key.replaceAll('_',' ');
      const input=(key==='content'||key==='lead')?document.createElement('textarea'):document.createElement('input');
      input.className='form-control'; input.dataset.field=key; input.value=value;
      input.addEventListener('input',()=>{setDirty(true);preview(key,input.value)});
      wrap.append(label,input); fields.append(wrap);
    });
    setDirty(false);
  }
  window.addEventListener('message', (event) => {
    const data=event.data;
    if (event.origin!==config.origin||event.source!==frame.contentWindow||!data||data.type!=='sietelsa:select'||!['section','item'].includes(data.entity)||!Number.isInteger(data.id)) return;
    select(data).catch(error => window.SietelsaAlert?.error(error.message));
  });
  document.querySelectorAll('.cms-device').forEach(button=>button.addEventListener('click',()=>document.getElementById('previewShell').dataset.device=button.dataset.device));
  form.addEventListener('input', event => {if (!event.target.matches('[data-field]')) setDirty(true)});
  document.getElementById('mediaId').addEventListener('change', event => {
    setDirty(true);
    const option = event.target.selectedOptions[0];
    frame.contentWindow.postMessage({type:'sietelsa:preview-media',entity:document.getElementById('entity').value,id:Number(document.getElementById('entityId').value),url:option?.dataset.url||''}, config.origin);
  });
  document.getElementById('undo').addEventListener('click',()=>{if(original) { select({entity:original.entity,id:original.id,sectionKey:'Contenido',contentKey:'restaurado'}); frame.contentWindow.location.reload(); }});
  document.querySelectorAll('[data-action]').forEach(button=>button.addEventListener('click',async()=>{
    const data={}; fields.querySelectorAll('[data-field]').forEach(input=>data[input.dataset.field]=input.value);
    const payload={csrf_token:config.csrf,action:button.dataset.action,entity:document.getElementById('entity').value,id:Number(document.getElementById('entityId').value),data,media_id:document.getElementById('mediaId').value?Number(document.getElementById('mediaId').value):null,sort_order:Number(document.getElementById('sortOrder').value),visible:document.getElementById('visible').checked};
    button.disabled=true;
    try { const response=await fetch(config.api,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}); const result=await response.json(); if(!result.ok)throw new Error(result.message); setDirty(false); window.SietelsaAlert?.success(result.message); if(payload.action==='publish') frame.contentWindow.location.reload(); }
    catch(error){window.SietelsaAlert?.error(error.message)} finally{button.disabled=false}
  }));
  window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue=''}});
})();
