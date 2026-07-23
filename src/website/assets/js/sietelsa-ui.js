(function () {
  'use strict';

  const labels = {
    success: 'Éxito',
    error: 'No fue posible completar la acción',
    warning: 'Atención',
    info: 'Información',
    question: 'Confirmación'
  };

  function fire(options) {
    if (window.Swal) {
      return window.Swal.fire(Object.assign({
        confirmButtonText: 'Aceptar',
        cancelButtonText: 'Cancelar',
        buttonsStyling: true,
        customClass: {
          popup: 'sietelsa-alert'
        }
      }, options));
    }

    if (options.showCancelButton) {
      return Promise.resolve({ isConfirmed: window.confirm(options.text || options.title || '') });
    }

    window.alert(options.text || options.title || '');
    return Promise.resolve({ isConfirmed: true });
  }

  window.SietelsaAlert = {
    success: function (message, title) {
      return fire({ icon: 'success', title: title || labels.success, text: message });
    },
    error: function (message, title) {
      return fire({ icon: 'error', title: title || labels.error, text: message });
    },
    warning: function (message, title) {
      return fire({ icon: 'warning', title: title || labels.warning, text: message });
    },
    info: function (message, title) {
      return fire({ icon: 'info', title: title || labels.info, text: message });
    },
    confirm: function (message, title) {
      return fire({
        icon: 'question',
        title: title || labels.question,
        text: message,
        showCancelButton: true,
        confirmButtonText: 'Confirmar'
      });
    },
    deletion: function (message) {
      return fire({
        icon: 'warning',
        title: 'Confirmar eliminación',
        text: message || 'Esta acción no se puede deshacer.',
        showCancelButton: true,
        confirmButtonText: 'Eliminar'
      });
    },
    sessionExpired: function () {
      return fire({ icon: 'warning', title: 'Sesión vencida', text: 'Inicie sesión nuevamente para continuar.' });
    },
    networkError: function () {
      return fire({ icon: 'error', title: 'Error de red', text: 'Revise su conexión e intente nuevamente.' });
    },
    imageUpload: function (message, isError) {
      return fire({ icon: isError ? 'error' : 'success', title: 'Carga de imagen', text: message });
    },
    saved: function () {
      return fire({ icon: 'success', title: 'Configuración guardada', text: 'Los cambios se guardaron correctamente.' });
    }
  };

  function hidePageLoader() {
    const loader = document.querySelector('.sietelsa-page-loader');
    if (!loader || loader.classList.contains('is-hidden')) {
      return;
    }
    loader.classList.add('is-hidden');
    window.setTimeout(function () {
      loader.remove();
    }, 250);
  }

  window.addEventListener('load', hidePageLoader, { once: true });
  window.setTimeout(hidePageLoader, 2500);

  document.querySelectorAll('[data-sietelsa-alert]').forEach(function (element) {
    const type = element.getAttribute('data-sietelsa-alert') || 'info';
    const message = element.textContent.trim();
    if (message && window.SietelsaAlert[type]) {
      window.SietelsaAlert[type](message);
      element.hidden = true;
    }
  });

  document.querySelectorAll('form[data-sietelsa-loading]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (form.dataset.sietelsaSubmitting === 'true') {
        event.preventDefault();
        return;
      }
      if (!form.checkValidity()) {
        return;
      }

      form.dataset.sietelsaSubmitting = 'true';
      form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
        button.disabled = true;
        if (button.tagName === 'BUTTON') {
          button.dataset.sietelsaOriginal = button.innerHTML;
          button.innerHTML = '<span class="sietelsa-button-spinner" aria-hidden="true"></span><span>Procesando…</span>';
        }
      });
    });
  });

  document.addEventListener('error', function (event) {
    const image = event.target;
    if (!(image instanceof HTMLImageElement) || image.dataset.sietelsaFallbackApplied === 'true') {
      return;
    }

    const brandLogo = document.querySelector('.sietelsa-brand-logo');
    if (!brandLogo || image.classList.contains('sietelsa-brand-logo')) {
      return;
    }

    console.warn('[SIETELSA] No fue posible cargar una imagen:', image.currentSrc || image.src);
    image.dataset.sietelsaFallbackApplied = 'true';
    image.srcset = '';
    image.src = brandLogo.currentSrc || brandLogo.src;
    image.classList.add('sietelsa-image-contain');
    if (!image.alt) {
      image.alt = 'Imagen no disponible';
    }
  }, true);

  document.addEventListener('sietelsa:form-complete', function (event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
      return;
    }
    delete form.dataset.sietelsaSubmitting;
    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
      button.disabled = false;
      if (button.dataset.sietelsaOriginal) {
        button.innerHTML = button.dataset.sietelsaOriginal;
        delete button.dataset.sietelsaOriginal;
      }
    });
  });
})();
