@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $map = [
        'approved' => 'success',
        'pending' => 'warning',
        'rejected' => 'danger',
        'not_requested' => 'secondary',
        'upcoming' => 'info',
        'inprogress' => 'primary',
        'completed' => 'success',
        'cancelled' => 'danger',
    ];
    $class = $map[$value] ?? 'secondary';
    $label = __('admin.statuses.'.$value);
    if ($label === 'admin.statuses.'.$value) {
        $label = __(ucfirst(str_replace('_', ' ', $value)));
    }
@endphp
<span class="badge badge-light-{{ $class }}">{{ $label }}</span>
