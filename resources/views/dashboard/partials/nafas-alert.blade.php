@if (Session::has('alert.config'))
    @php
        $alertConfig = json_decode(Session::pull('alert.config'), true) ?? [];
    @endphp
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var config = @json($alertConfig);
            var message = config.title || config.text || config.html || '';
            var type = config.icon || 'success';

            if (typeof NafasToast !== 'undefined' && message) {
                NafasToast.show(message, type, { duration: config.timer || 4000 });
            }
        });
    </script>
@endif
