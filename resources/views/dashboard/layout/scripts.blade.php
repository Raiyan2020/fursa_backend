<!-- BEGIN: Vendor JS-->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="{{ asset('_dashboard/app-assets/vendors/js/vendors.min.js') }}"></script>
<script src="{{ asset('_dashboard/app-assets/vendors/js/extensions/tether.min.js') }}"></script>

<!-- BEGIN: Theme JS-->
<script src="{{ asset('_dashboard/app-assets/js/core/app-menu.js') }}"></script>
<script src="{{ asset('_dashboard/app-assets/js/core/app.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" crossorigin="anonymous"></script>
<script src="{{ asset('_dashboard/app-assets/js/core/libraries/bootstrap.min.js') }}"></script>
<script src="{{ asset('_dashboard/app-assets/js/scripts/components.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/js/bootstrap-datepicker.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="{{ asset('swal/sweeralert2.js') }}"></script>
<script src="{{ asset('_dashboard/assets/js/nafas-toast.js') }}?v={{ @filemtime(public_path('_dashboard/assets/js/nafas-toast.js')) }}"></script>
<script defer src="https://use.fontawesome.com/releases/v5.15.4/js/all.js"></script>
<script defer src="https://use.fontawesome.com/releases/v5.15.4/js/v4-shims.js"></script>
<script src="{{ asset('ckeditor/ckeditor.js') }}"></script>

<form method="POST" id="delete-form">
    @csrf
    @method('DELETE')
</form>

<script type="text/javascript">
    var lis = $('.check-active');
    lis.each(function(index) {
        if ($(this).attr('href') === "{!! url('/') !!}" + window.location.pathname) {
            $(this).parent().addClass('active');
        }
    });

    function forsaConfirmAction(element, title, formId) {
        let url = $(element).data('href');
        NafasConfirm.fire({
            title: title,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: "{{ __('Confirm') }}",
            cancelButtonText: "{{ __('Cancel') }}"
        }).then((result) => {
            if (result.isConfirmed) {
                $('#' + formId).attr('action', url).submit();
            }
        });
    }

    function delete_form(element) {
        forsaConfirmAction(element, "{{ __('Do you want to delete the item ?') }}", 'delete-form');
    }

    $('.select2:not(n-select2)').select2({
        width: '100%',
        language: {
            noResults: function () { return "{{ __('No results found') }}"; },
            searching: function () { return "{{ __('Searching...') }}"; }
        }
    });
    $('.select2-multiple').select2({
        tags: false,
        width: '100%',
        language: {
            noResults: function () { return "{{ __('No results found') }}"; },
            searching: function () { return "{{ __('Searching...') }}"; }
        }
    });

    $('.pickadate').datepicker({ format: 'yyyy-mm-dd', startDate: '-70y', rtl: {{ app()->getLocale() === 'ar' ? 'true' : 'false' }} });

    $(document).ready(function($) {
        $('[data-toggle="popover"]').popover({ trigger: 'focus' });
        if (typeof CKEDITOR !== 'undefined' && document.getElementById('ckeditor')) {
            CKEDITOR.replace('ckeditor');
        }
    });
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/js/dropify.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>

<script>
    $(document).ready(function() {
        $('.dropify').dropify({
            messages: {
                default: @json(__('Drag and drop a file here or click')),
                replace: @json(__('Drag and drop a file here or click')),
                remove: @json(__('Remove')),
                error: @json(__('ooops, something wrong appended.'))
            },
            error: { fileSize: @json(__('Sorry, the file is too large')) }
        });
    });
</script>

<!-- BEGIN: Page JS-->
@include('dashboard.partials.nafas-alert')
<!-- END: Page JS-->

<script>
$(function () {
    var currentLayout = localStorage.getItem('caberz_currentLayout');

    if (currentLayout === null) {
        currentLayout = 'dark';
        localStorage.setItem('caberz_currentLayout', 'dark');
    }

    $('#content_body').data('type', currentLayout);

    if (currentLayout === 'light') {
        $('#layout-mode').html('<i class="ficon feather icon-moon" onclick="changeMode()"></i>');
        $('#content_body').removeClass('dark-layout').addClass('light-mode');
    } else {
        $('#layout-mode').html('<i class="ficon feather icon-sun" onclick="changeMode()"></i>');
        $('#content_body').addClass('dark-layout').removeClass('light-mode');
    }
});

function changeMode() {
    var layoutOptions = $('#content_body').data('type');
    if (layoutOptions == 'dark') {
        localStorage.setItem('caberz_currentLayout', 'light');
        $('#content_body').data('type', 'light').removeClass('dark-layout').addClass('light-mode');
        $('#layout-mode').html('<i class="ficon feather icon-moon" onclick="changeMode()"></i>');
    } else {
        localStorage.setItem('caberz_currentLayout', 'dark');
        $('#content_body').data('type', 'dark').addClass('dark-layout').removeClass('light-mode');
        $('#layout-mode').html('<i class="ficon feather icon-sun" onclick="changeMode()"></i>');
    }
}
</script>
