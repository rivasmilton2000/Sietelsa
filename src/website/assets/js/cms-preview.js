(() => {
  'use strict';
  if (window.parent === window) return;
  const origin = window.location.origin;
  let selected = null;
  document.documentElement.classList.add('cms-preview-active');
  document.addEventListener('click', (event) => {
    const target = event.target.closest('[data-cms-entity][data-cms-id]');
    if (!target) return;
    event.preventDefault();
    selected?.classList.remove('cms-preview-selected');
    selected = target;
    selected.classList.add('cms-preview-selected');
    window.parent.postMessage({
      type: 'sietelsa:select',
      entity: target.dataset.cmsEntity,
      id: Number(target.dataset.cmsId),
      field: target.dataset.contentType,
      sectionKey: target.dataset.sectionKey,
      contentKey: target.dataset.contentKey
    }, origin);
  });
  window.addEventListener('message', (event) => {
    if (event.origin !== origin || event.source !== window.parent) return;
    const data = event.data;
    if (!data || !['sietelsa:preview', 'sietelsa:preview-media'].includes(data.type) || !['section', 'item'].includes(data.entity) || !Number.isInteger(data.id)) return;
    if (data.type === 'sietelsa:preview-media') {
      if (typeof data.url !== 'string' || (data.url !== '' && !data.url.startsWith('/'))) return;
      document.querySelectorAll(`img[data-cms-entity="${CSS.escape(data.entity)}"][data-cms-id="${data.id}"]`).forEach((image) => {
        if (data.url) image.src = data.url;
      });
      return;
    }
    document.querySelectorAll(`[data-cms-entity="${CSS.escape(data.entity)}"][data-cms-id="${data.id}"][data-content-type="${CSS.escape(data.field)}"]`)
      .forEach((node) => { node.textContent = String(data.value ?? ''); });
  });
})();
