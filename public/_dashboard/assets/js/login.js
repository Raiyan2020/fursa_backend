(function () {
    'use strict';

    function getConfig() {
        return window.loginPageConfig || {};
    }

    function getLoginBtnHtml() {
        var config = getConfig();
        if (config.loginBtnHtml) {
            return config.loginBtnHtml;
        }

        var $btn = $('.submit_button');
        if ($btn.length) {
            return $btn.html();
        }

        return 'Login';
    }

    function initToastr() {
        if (typeof toastr === 'undefined') {
            return;
        }

        toastr.options = {
            closeButton: true,
            newestOnTop: false,
            progressBar: true,
            positionClass: 'toast-top-right',
            showMethod: 'slideDown',
            hideMethod: 'slideUp',
            timeOut: 2000
        };
    }

    function updateLoginThemeIcon() {
        if (typeof jQuery === 'undefined') {
            return;
        }

        var isDark = localStorage.getItem('caberz_currentLayout') !== 'light';
        var iconClass = isDark ? 'icon-sun' : 'icon-moon';
        jQuery('#layout-mode-login').html('<i class="ficon feather ' + iconClass + '"></i>');
    }

    function initLoginTheme() {
        var $body = jQuery('#content_body');
        if (!$body.length) {
            return;
        }

        var stored = localStorage.getItem('caberz_currentLayout');
        if (stored === 'light') {
            $body.removeClass('dark-layout').addClass('light-mode').data('type', 'light');
        } else {
            $body.addClass('dark-layout').removeClass('light-mode').data('type', 'dark');
        }

        updateLoginThemeIcon();
    }

    function initStarfield() {
        var canvas = document.getElementById('stars-canvas');
        if (!canvas) {
            return;
        }

        var ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }

        var stars = [];
        var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function resize() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }

        function createStars(count) {
            stars = [];
            for (var i = 0; i < count; i++) {
                stars.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    r: Math.random() * 1.5 + 0.3,
                    alpha: Math.random(),
                    speed: Math.random() * 0.3 + 0.05,
                    twinkle: Math.random() * 0.02 + 0.005
                });
            }
        }

        function getStarColor(alpha) {
            var isLight = document.body.classList.contains('light-mode');
            if (isLight) {
                return 'rgba(99,102,241,' + (alpha * 0.55) + ')';
            }
            return 'rgba(255,255,255,' + alpha + ')';
        }

        function draw() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            for (var i = 0; i < stars.length; i++) {
                var s = stars[i];
                if (!reducedMotion) {
                    s.alpha += s.twinkle * (Math.random() > 0.5 ? 1 : -1);
                    if (s.alpha > 1) s.alpha = 1;
                    if (s.alpha < 0.1) s.alpha = 0.1;
                    s.y -= s.speed * 0.15;
                    if (s.y < 0) s.y = canvas.height;
                }

                ctx.beginPath();
                ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
                ctx.fillStyle = getStarColor(s.alpha);
                ctx.fill();
            }

            if (!reducedMotion) {
                window.requestAnimationFrame(draw);
            }
        }

        resize();
        createStars(Math.floor((canvas.width * canvas.height) / 3500));
        draw();

        window.addEventListener('resize', function () {
            resize();
            createStars(Math.floor((canvas.width * canvas.height) / 3500));
        });
    }

    function initFirebase() {
        var config = getConfig();

        if (!config.firebase || typeof firebase === 'undefined') {
            return;
        }

        try {
            if (!firebase.apps || !firebase.apps.length) {
                firebase.initializeApp(config.firebase);
            }
        } catch (err) {
            console.warn('[FCM] Firebase init skipped:', err);
            return;
        }

        try {
            var messaging = firebase.messaging();

            messaging.getToken({ vapidKey: config.vapidKey })
                .then(function (token) {
                    if (!token) {
                        return;
                    }
                    var deviceInput = document.getElementById('fcm_device_id');
                    if (deviceInput) {
                        deviceInput.value = token;
                    }
                })
                .catch(function (err) {
                    console.warn('[FCM] Token error:', err);
                });
        } catch (err) {
            console.warn('[FCM] Messaging error:', err);
        }
    }

    function initLoginForm() {
        var $form = jQuery('#login-form');
        if (!$form.length) {
            return;
        }

        $form.on('submit', function (e) {
            e.preventDefault();

            var $currentForm = jQuery(this);
            var url = $currentForm.attr('action');
            var csrfToken = jQuery('meta[name="csrf-token"]').attr('content');
            var loginBtnHtml = getLoginBtnHtml();

            jQuery.ajax({
                url: url,
                method: 'POST',
                data: new FormData(this),
                dataType: 'json',
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                beforeSend: function () {
                    jQuery('.submit_button')
                        .html('<i class="fas fa-spinner fa-spin"></i>')
                        .prop('disabled', true);
                },
                success: function (response) {
                    jQuery('.text-danger').remove();
                    $currentForm.find('input').removeClass('border-danger');

                    if (response.status === 'login') {
                        if (typeof toastr !== 'undefined') {
                            toastr.success(response.message);
                        }
                        window.setTimeout(function () {
                            window.location.replace(response.url);
                        }, 1000);
                        return;
                    }

                    jQuery('.submit_button').html(loginBtnHtml).prop('disabled', false);
                    if (response.message && typeof toastr !== 'undefined') {
                        toastr.error(response.message);
                    }
                },
                error: function (xhr) {
                    jQuery('.submit_button').html(loginBtnHtml).prop('disabled', false);
                    jQuery('.text-danger').remove();
                    $currentForm.find('input').removeClass('border-danger');

                    var json = xhr.responseJSON;
                    if (json && json.message && typeof toastr !== 'undefined') {
                        toastr.error(json.message);
                        return;
                    }

                    if (!json || !json.errors) {
                        return;
                    }

                    jQuery.each(json.errors, function (key, value) {
                        var msg = Array.isArray(value) ? value[0] : value;
                        $currentForm.find('input[name="' + key + '"]').addClass('border-danger');
                        $currentForm.find('input[name="' + key + '"]')
                            .closest('.input-icon-wrap')
                            .after('<span class="mt-1 text-danger d-block" style="font-size:0.8rem">' + msg + '</span>');
                    });
                }
            });
        });
    }

    function initControls() {
        jQuery('#layout-mode-login').on('click', function (e) {
            e.preventDefault();
            if (typeof changeMode === 'function') {
                changeMode();
            }
            updateLoginThemeIcon();
        });

        jQuery('.toggle-password').on('click', function () {
            var $input = jQuery('#password');
            var $icon = jQuery('#toggle-password-icon');

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                $input.attr('type', 'password');
                $icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        });
    }

    function boot() {
        if (typeof jQuery === 'undefined') {
            console.error('[Login] jQuery is required.');
            return;
        }

        initToastr();
        initLoginForm();
        initControls();
        initLoginTheme();

        try {
            initStarfield();
        } catch (err) {
            console.warn('[Login] Starfield error:', err);
        }

        try {
            initFirebase();
        } catch (err) {
            console.warn('[Login] Firebase error:', err);
        }
    }

    if (typeof jQuery !== 'undefined') {
        jQuery(boot);
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof jQuery !== 'undefined') {
                jQuery(boot);
            }
        });
    }
})();
