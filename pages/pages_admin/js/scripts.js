/*!
 * Start Bootstrap - SB Admin v7.0.7 (https://startbootstrap.com/template/sb-admin)
 * Copyright 2013-2023 Start Bootstrap
 * Licensed under MIT (https://github.com/StartBootstrap/startbootstrap-sb-admin/blob/master/LICENSE)
 */

(function () {
    'use strict';

    var sweetAlertPromise = null;
    var themeStorageKey = 'sietelsa|theme';

    function storedTheme() {
        try {
            return localStorage.getItem(themeStorageKey);
        } catch (error) {
            return null;
        }
    }

    function currentTheme() {
        var saved = storedTheme();
        if (saved === 'dark' || saved === 'light') {
            return saved;
        }

        return 'light';
    }

    function setStoredTheme(theme) {
        try {
            localStorage.setItem(themeStorageKey, theme);
        } catch (error) {
            return;
        }
    }

    function syncThemeControls(theme) {
        var isDark = theme === 'dark';
        var toggles = document.querySelectorAll('[data-sietelsa-theme-toggle]');

        Array.prototype.forEach.call(toggles, function (toggle) {
            toggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            toggle.setAttribute('aria-label', isDark ? 'Activar modo claro' : 'Activar modo oscuro');
            toggle.setAttribute('title', isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');

            var label = toggle.querySelector('[data-sietelsa-theme-label]');
            if (label) {
                label.textContent = isDark ? 'Claro' : 'Oscuro';
            }

            var icon = toggle.querySelector('[data-sietelsa-theme-icon]');
            if (icon) {
                icon.className = isDark ? 'fas fa-sun me-1' : 'fas fa-moon me-1';
            }
        });
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-sietelsa-theme', theme);
        document.documentElement.style.colorScheme = theme;
        syncThemeControls(theme);
    }

    function bindThemeToggle() {
        var toggles = document.querySelectorAll('[data-sietelsa-theme-toggle]');
        if (!toggles.length) {
            return;
        }

        Array.prototype.forEach.call(toggles, function (toggle) {
            toggle.addEventListener('click', function () {
                var nextTheme = document.documentElement.getAttribute('data-sietelsa-theme') === 'dark' ? 'light' : 'dark';
                setStoredTheme(nextTheme);
                applyTheme(nextTheme);
            });
        });

        syncThemeControls(document.documentElement.getAttribute('data-sietelsa-theme') || currentTheme());
    }

    applyTheme(currentTheme());

    function ensureSweetAlert() {
        if (typeof window.Swal !== 'undefined') {
            return Promise.resolve(window.Swal);
        }

        if (sweetAlertPromise) {
            return sweetAlertPromise;
        }

        sweetAlertPromise = new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
            script.async = true;
            script.onload = function () {
                if (typeof window.Swal !== 'undefined') {
                    resolve(window.Swal);
                    return;
                }
                reject(new Error('SweetAlert2 no disponible.'));
            };
            script.onerror = function () {
                reject(new Error('No se pudo cargar SweetAlert2.'));
            };
            document.head.appendChild(script);
        });

        return sweetAlertPromise;
    }

    function fieldLabel(field) {
        if (!field) {
            return 'campo';
        }

        var id = field.getAttribute('id') || '';
        if (id !== '') {
            var label = document.querySelector('label[for="' + id + '"]');
            if (label) {
                var text = (label.textContent || '').trim();
                if (text !== '') {
                    return text.replace(/\s*\*+\s*$/, '').trim();
                }
            }
        }

        var placeholder = (field.getAttribute('placeholder') || '').trim();
        if (placeholder !== '') {
            return placeholder.replace(/\s*\*+\s*$/, '').trim();
        }

        return (field.getAttribute('name') || 'campo').replace(/[_\-]+/g, ' ');
    }

    function looksLikeTextField(field) {
        if (!field) {
            return false;
        }

        var tag = field.tagName.toLowerCase();
        if (tag === 'textarea') {
            return true;
        }

        if (tag !== 'input') {
            return false;
        }

        var type = (field.getAttribute('type') || 'text').toLowerCase();
        return [
            'text',
            'email',
            'password',
            'search',
            'tel',
            'url',
            'number'
        ].indexOf(type) !== -1;
    }

    function validateFileField(field) {
        if (!field || field.type !== 'file' || !field.files || field.files.length === 0) {
            return null;
        }

        var maxAttr = parseInt(field.getAttribute('data-max-filesize') || '', 10);
        if (!Number.isFinite(maxAttr) || maxAttr <= 0) {
            return null;
        }

        for (var i = 0; i < field.files.length; i++) {
            var file = field.files[i];
            if (file.size > maxAttr) {
                var mb = (maxAttr / (1024 * 1024)).toFixed(2).replace(/\.00$/, '');
                return {
                    field: field,
                    message: 'El archivo en "' + fieldLabel(field) + '" supera el limite permitido (' + mb + ' MB).'
                };
            }
        }

        return null;
    }

    function validateForm(form) {
        var fields = form.querySelectorAll('input, select, textarea');

        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            if (!field || field.disabled) {
                continue;
            }

            var type = (field.getAttribute('type') || '').toLowerCase();
            if (type === 'hidden' || type === 'button' || type === 'submit' || type === 'reset') {
                continue;
            }

            if (looksLikeTextField(field)) {
                field.value = field.value.trim();
            }

            var fileError = validateFileField(field);
            if (fileError) {
                return fileError;
            }

            if (!field.checkValidity()) {
                var label = fieldLabel(field);
                var msg = field.validationMessage || ('Verifica el campo "' + label + '".');

                if (field.validity.valueMissing) {
                    msg = 'El campo "' + label + '" es obligatorio.';
                } else if (field.validity.tooLong) {
                    msg = 'El campo "' + label + '" supera el limite permitido.';
                } else if (field.validity.tooShort) {
                    msg = 'El campo "' + label + '" no cumple la longitud minima requerida.';
                } else if (field.validity.patternMismatch) {
                    msg = 'El formato del campo "' + label + '" no es valido.';
                } else if (field.validity.typeMismatch && type === 'email') {
                    msg = 'El correo ingresado en "' + label + '" no es valido.';
                }

                return {
                    field: field,
                    message: msg
                };
            }
        }

        return null;
    }

    function showDialog(config) {
        return ensureSweetAlert()
            .then(function (Swal) {
                return Swal.fire(config);
            })
            .catch(function () {
                window.alert(config.text || config.title || 'Operacion no valida.');
                return { isConfirmed: true };
            });
    }

    function runConfirm(message) {
        return showDialog({
            icon: 'warning',
            title: 'Confirmar accion',
            text: message,
            showCancelButton: true,
            confirmButtonText: 'Continuar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d'
        });
    }

    function showFormError(message) {
        return showDialog({
            icon: 'error',
            title: 'Validacion',
            text: message,
            confirmButtonText: 'Entendido'
        });
    }

    function showFlashAlerts() {
        var notices = document.querySelectorAll('.alert[role="alert"], .alert-box[role="alert"]');
        if (!notices.length) {
            return;
        }

        Array.prototype.forEach.call(notices, function (notice) {
            if (!notice || notice.classList.contains('alert-inline')) {
                return;
            }

            var text = (notice.textContent || '').trim();
            if (text === '') {
                return;
            }

            var icon = 'info';
            var title = 'Informacion';

            if (notice.classList.contains('alert-danger') || notice.classList.contains('error')) {
                icon = 'error';
                title = 'Error';
            } else if (notice.classList.contains('alert-success') || notice.classList.contains('success')) {
                icon = 'success';
                title = 'Correcto';
            } else if (notice.classList.contains('alert-warning') || notice.classList.contains('warning')) {
                icon = 'warning';
                title = 'Atencion';
            }

            showDialog({
                icon: icon,
                title: title,
                text: text,
                confirmButtonText: 'Aceptar'
            });
        });
    }

    function bindFormValidation() {
        var forms = document.querySelectorAll('form');
        if (!forms.length) {
            return;
        }

        Array.prototype.forEach.call(forms, function (form) {
            form.addEventListener('submit', function (event) {
                if (form.getAttribute('data-sietelsa-submit-lock') === '1') {
                    form.removeAttribute('data-sietelsa-submit-lock');
                    return;
                }

                event.preventDefault();

                var error = validateForm(form);
                if (error) {
                    showFormError(error.message).then(function () {
                        if (error.field && typeof error.field.focus === 'function') {
                            error.field.focus();
                        }
                    });
                    return;
                }

                var submitter = event.submitter || null;
                var message = '';

                if (submitter && submitter.getAttribute('data-confirm-message')) {
                    message = submitter.getAttribute('data-confirm-message') || '';
                } else if (form.getAttribute('data-confirm-message')) {
                    message = form.getAttribute('data-confirm-message') || '';
                }

                var proceed = function () {
                    form.setAttribute('data-sietelsa-submit-lock', '1');
                    if (submitter && typeof form.requestSubmit === 'function') {
                        form.requestSubmit(submitter);
                    } else {
                        form.submit();
                    }
                };

                if (message.trim() === '') {
                    proceed();
                    return;
                }

                runConfirm(message).then(function (result) {
                    if (result && result.isConfirmed) {
                        proceed();
                    }
                });
            });
        });
    }

    function bindMobileNotificationDropdown() {
        var trigger = document.getElementById('navbarNotificaciones');
        var menu = document.querySelector('.admin-notifications-menu');

        if (!trigger || !menu) {
            return;
        }

        var mobileQuery = window.matchMedia('(max-width: 767.98px)');

        function clearInjectedStyles() {
            var props = [
                'position',
                'inset',
                'left',
                'right',
                'top',
                'transform',
                'width',
                'max-width',
                'max-height',
                'z-index',
                'margin-top'
            ];

            props.forEach(function (prop) {
                menu.style.removeProperty(prop);
            });
        }

        function applyMobileStyles() {
            if (!mobileQuery.matches || !menu.classList.contains('show')) {
                return;
            }

            menu.style.setProperty('position', 'fixed', 'important');
            menu.style.setProperty('inset', '56px 0.5rem auto 0.5rem', 'important');
            menu.style.setProperty('left', '0.5rem', 'important');
            menu.style.setProperty('right', '0.5rem', 'important');
            menu.style.setProperty('top', '56px', 'important');
            menu.style.setProperty('transform', 'none', 'important');
            menu.style.setProperty('width', 'calc(100vw - 1rem)', 'important');
            menu.style.setProperty('max-width', 'calc(100vw - 1rem)', 'important');
            menu.style.setProperty('max-height', 'min(65vh, 24rem)', 'important');
            menu.style.setProperty('margin-top', '0', 'important');
            menu.style.setProperty('z-index', '1045', 'important');
        }

        trigger.addEventListener('shown.bs.dropdown', applyMobileStyles);
        trigger.addEventListener('hidden.bs.dropdown', clearInjectedStyles);
        window.addEventListener('resize', function () {
            if (menu.classList.contains('show')) {
                clearInjectedStyles();
                applyMobileStyles();
            }
        });
    }

    window.addEventListener('DOMContentLoaded', function () {
        var sidebarToggle = document.body.querySelector('#sidebarToggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function (event) {
                event.preventDefault();
                document.body.classList.toggle('sb-sidenav-toggled');
                localStorage.setItem('sb|sidebar-toggle', document.body.classList.contains('sb-sidenav-toggled'));
            });
        }

        showFlashAlerts();
        bindFormValidation();
        bindMobileNotificationDropdown();
        bindThemeToggle();
    });
})();
