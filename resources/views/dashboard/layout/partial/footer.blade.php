<footer class="footer footer-static admin-dashboard-footer">
    <p class="admin-dashboard-footer__text mb-0 text-center">
        {{ __('All rights reserved') }} &copy; {{ date('Y') }} Forsa
    </p>
</footer>

<div class="sidenav-overlay"></div>
<div class="drag-target"></div>

@include('dashboard.layout.scripts')
@stack('scripts')

<script>
    $(window).on('load', function () {
        $('.loader').fadeOut();
    });

    document.addEventListener('DOMContentLoaded', function () {
        const savedColor = localStorage.getItem('theme-primary-color');
        const savedHover = localStorage.getItem('theme-primary-hover');

        const hexToRgb = hex => {
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            return `${r}, ${g}, ${b}`;
        };

        const relativeLuminance = hex => {
            const channels = [hex.slice(1, 3), hex.slice(3, 5), hex.slice(5, 7)].map(value => {
                const channel = parseInt(value, 16) / 255;
                return channel <= 0.03928 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4);
            });
            return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
        };

        const applyThemeColors = function (color, hover) {
            document.documentElement.style.setProperty('--primary', color);
            document.documentElement.style.setProperty('--primary-hover', hover);
            document.documentElement.style.setProperty('--primary-rgb', hexToRgb(color));

            const isDarkAccent = relativeLuminance(color) < 0.24;
            document.documentElement.classList.toggle('theme-accent-dark', isDarkAccent);

            if (isDarkAccent) {
                document.documentElement.style.setProperty('--primary-ui', '#a78bfa');
                document.documentElement.style.setProperty('--primary-ui-hover', '#c4b5fd');
                document.documentElement.style.setProperty('--primary-ui-rgb', '167, 139, 250');
            } else {
                document.documentElement.style.setProperty('--primary-ui', color);
                document.documentElement.style.setProperty('--primary-ui-hover', hover);
                document.documentElement.style.setProperty('--primary-ui-rgb', hexToRgb(color));
            }
        };

        const colorCircles = document.querySelectorAll('.theme-color-circle');
        const defaultColor = '#7c3aed';
        const defaultHover = '#6d28d9';

        const setActiveCircle = function (color) {
            colorCircles.forEach(function (circle) {
                const isActive = circle.getAttribute('data-color') &&
                    circle.getAttribute('data-color').toLowerCase() === (color || '').toLowerCase();
                circle.classList.toggle('active', isActive);
            });
        };

        if (savedColor && savedHover) {
            applyThemeColors(savedColor, savedHover);
            setActiveCircle(savedColor);
        } else {
            applyThemeColors(defaultColor, defaultHover);
            localStorage.setItem('theme-primary-color', defaultColor);
            localStorage.setItem('theme-primary-hover', defaultHover);
            setActiveCircle(defaultColor);
        }

        colorCircles.forEach(function (circle) {
            circle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                const color = this.getAttribute('data-color');
                const hover = this.getAttribute('data-hover');

                applyThemeColors(color, hover);
                localStorage.setItem('theme-primary-color', color);
                localStorage.setItem('theme-primary-hover', hover);
                setActiveCircle(color);
            });
        });

        $('table.dataTable').addClass('admin-data-table');
    });
</script>
</body>
</html>
