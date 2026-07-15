(function (window) {
    'use strict';

    var ICONS = {
        success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
        error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>',
        warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
    };

    function getContainer() {
        var el = document.getElementById('nafas-toast-container');
        if (!el) {
            el = document.createElement('div');
            el.id = 'nafas-toast-container';
            el.className = 'nafas-toast-container';
            el.setAttribute('aria-live', 'polite');
            el.setAttribute('aria-atomic', 'true');
            document.body.appendChild(el);
        }
        return el;
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function isDarkTheme() {
        var body = document.getElementById('content_body');
        return body && body.classList.contains('dark-layout');
    }

    function getSwalThemeOptions() {
        return {
            customClass: { popup: 'nafas-swal-popup' },
            background: isDarkTheme() ? '#2d2d44' : '#ffffff',
            color: isDarkTheme() ? '#e2e8f0' : '#0f172a',
            confirmButtonColor: '#f97316',
            cancelButtonColor: '#64748b'
        };
    }

    var NafasToast = {
        show: function (message, type, options) {
            type = type || 'success';
            options = options || {};
            var duration = options.duration || 4000;
            var container = getContainer();
            var toast = document.createElement('div');
            toast.className = 'nafas-toast nafas-toast--' + type;
            toast.innerHTML =
                '<div class="nafas-toast__icon">' + (ICONS[type] || ICONS.info) + '</div>' +
                '<div class="nafas-toast__body"><div class="nafas-toast__message">' + escapeHtml(message) + '</div></div>' +
                '<button type="button" class="nafas-toast__close" aria-label="Close">&times;</button>' +
                '<div class="nafas-toast__progress" style="animation-duration:' + duration + 'ms"></div>';

            container.appendChild(toast);

            requestAnimationFrame(function () {
                toast.classList.add('is-visible');
            });

            var timer = setTimeout(function () {
                NafasToast.dismiss(toast);
            }, duration);

            toast.querySelector('.nafas-toast__close').addEventListener('click', function () {
                clearTimeout(timer);
                NafasToast.dismiss(toast);
            });
        },

        dismiss: function (toast) {
            if (!toast || toast.classList.contains('is-leaving')) {
                return;
            }
            toast.classList.remove('is-visible');
            toast.classList.add('is-leaving');
            setTimeout(function () {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 280);
        },

        success: function (message, options) { this.show(message, 'success', options); },
        error: function (message, options) { this.show(message, 'error', options); },
        warning: function (message, options) { this.show(message, 'warning', options); },
        info: function (message, options) { this.show(message, 'info', options); }
    };

    var NafasConfirm = {
        fire: function (options) {
            if (typeof Swal === 'undefined') {
                if (window.confirm(options.title || options.text || 'Confirm?')) {
                    return Promise.resolve({ isConfirmed: true });
                }
                return Promise.resolve({ isConfirmed: false });
            }

            var merged = Object.assign({}, getSwalThemeOptions(), options || {});
            return Swal.fire(merged);
        }
    };

    window.NafasToast = NafasToast;
    window.NafasConfirm = NafasConfirm;
})(window);
