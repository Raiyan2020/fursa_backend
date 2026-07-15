<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
@if (app()->getLocale() === 'ar')
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap" rel="stylesheet">
@else
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
@endif

<!-- BEGIN: Vendor CSS-->
@if (app()->getLocale() === 'ar')
    <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/vendors/css/vendors-rtl.min.css') }}">
@else
    <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/vendors/css/vendors.min.css') }}">
@endif
<link rel="stylesheet" href="{{ asset('_dashboard/app-assets/vendors/css/ui/prism.min.css') }}">

<!-- BEGIN: Theme CSS-->
@if (app()->getLocale() === 'ar')
    <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css-rtl/bootstrap.css') }}">
    <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css-rtl/bootstrap-extended.css') }}">
    <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css-rtl/colors.css') }}">
    <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css-rtl/components.css') }}">
    <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css-rtl/themes/dark-layout.css') }}">
    <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css-rtl/themes/semi-dark-layout.css') }}">
    <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css-rtl/core/menu/menu-types/vertical-menu.css') }}">
    <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css-rtl/core/colors/palette-gradient.css') }}">
    <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css-rtl/pages/dashboard-ecommerce.css') }}">
    <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css-rtl/custom-rtl.css') }}">
    <link rel="stylesheet" href="{{ asset('_dashboard/assets/css/style-rtl.css') }}">
@else
    <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css/bootstrap.css') }}">
    <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css/bootstrap-extended.css') }}">
    <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css/colors.css') }}">
    <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css/components.css') }}">
    <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css/themes/dark-layout.css') }}">
    <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css/themes/semi-dark-layout.css') }}">
    <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css/core/menu/menu-types/vertical-menu.css') }}">
    <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css/core/colors/palette-gradient.css') }}">
    <link rel="stylesheet" href="{{ asset('_dashboard/app-assets/css/pages/dashboard-ecommerce.css') }}">
    <link rel="stylesheet" href="{{ asset('_dashboard/assets/css/style.css') }}">
@endif

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css">
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/v4-shims.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.47.0/tabler-icons.min.css" crossorigin="anonymous">

<!-- Forsa redesign (Nafas base) -->
<link rel="stylesheet" href="{{ asset('_dashboard/assets/css/modern-redesign.css') }}?v={{ @filemtime(public_path('_dashboard/assets/css/modern-redesign.css')) }}">
<link rel="stylesheet" href="{{ asset('_dashboard/assets/css/sidebar-redesign.css') }}?v={{ @filemtime(public_path('_dashboard/assets/css/sidebar-redesign.css')) }}">
<link rel="stylesheet" href="{{ asset('_dashboard/assets/css/admin-table-toolbar.css') }}?v={{ @filemtime(public_path('_dashboard/assets/css/admin-table-toolbar.css')) }}">
<link rel="stylesheet" href="{{ asset('_dashboard/assets/css/admin-data-table.css') }}?v={{ @filemtime(public_path('_dashboard/assets/css/admin-data-table.css')) }}">
<link rel="stylesheet" href="{{ asset('_dashboard/assets/css/admin-form-page.css') }}?v={{ @filemtime(public_path('_dashboard/assets/css/admin-form-page.css')) }}">
<link rel="stylesheet" href="{{ asset('_dashboard/assets/css/show-page.css') }}?v={{ @filemtime(public_path('_dashboard/assets/css/show-page.css')) }}">
<link rel="stylesheet" href="{{ asset('_dashboard/assets/css/dashboard-charts.css') }}?v={{ @filemtime(public_path('_dashboard/assets/css/dashboard-charts.css')) }}">
<link rel="stylesheet" href="{{ asset('_dashboard/assets/css/admin-notifications-page.css') }}?v={{ @filemtime(public_path('_dashboard/assets/css/admin-notifications-page.css')) }}">
<link rel="stylesheet" href="{{ asset('_dashboard/assets/css/nafas-toast.css') }}?v={{ @filemtime(public_path('_dashboard/assets/css/nafas-toast.css')) }}">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/css/dropify.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/css/bootstrap-datepicker.standalone.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.css" rel="stylesheet">

<style>
    .datepicker-dropdown { max-width: 300px; }
    .datepicker { float: right; }
    .datepicker.dropdown-menu { right: auto; }
    .btn-purple { background-color: #7c3aed; color: white; }

    .loader {
        position: fixed;
        z-index: 100000;
        width: 100%;
        height: 100%;
        top: 0;
        right: 0;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .sk-chase { width: 40px; height: 40px; position: relative; animation: sk-chase 2.5s infinite linear both; }
    .sk-chase-dot { width: 100%; height: 100%; position: absolute; left: 0; top: 0; animation: sk-chase-dot 2s infinite ease-in-out both; }
    .sk-chase-dot:before { content: ""; display: block; width: 25%; height: 25%; background-color: #fff; border-radius: 100%; animation: sk-chase-dot-before 2s infinite ease-in-out both; }
    .sk-chase-dot:nth-child(1) { animation-delay: -1.1s; }
    .sk-chase-dot:nth-child(2) { animation-delay: -1s; }
    .sk-chase-dot:nth-child(3) { animation-delay: -0.9s; }
    .sk-chase-dot:nth-child(4) { animation-delay: -0.8s; }
    .sk-chase-dot:nth-child(5) { animation-delay: -0.7s; }
    .sk-chase-dot:nth-child(6) { animation-delay: -0.6s; }
    .sk-chase-dot:nth-child(1):before { animation-delay: -1.1s; }
    .sk-chase-dot:nth-child(2):before { animation-delay: -1s; }
    .sk-chase-dot:nth-child(3):before { animation-delay: -0.9s; }
    .sk-chase-dot:nth-child(4):before { animation-delay: -0.8s; }
    .sk-chase-dot:nth-child(5):before { animation-delay: -0.7s; }
    .sk-chase-dot:nth-child(6):before { animation-delay: -0.6s; }
    @keyframes sk-chase { 100% { transform: rotate(360deg); } }
    @keyframes sk-chase-dot { 80%, 100% { transform: rotate(360deg); } }
    @keyframes sk-chase-dot-before { 50% { transform: scale(0.4); } 100%, 0% { transform: scale(1); } }
</style>
