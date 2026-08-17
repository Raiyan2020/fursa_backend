{{--
    Delete button that must sit inside another <form>.
    Nested <form> tags are invalid HTML and close the parent form, which
    leaves the Save button outside the edit form so it does nothing.
--}}
<button type="submit"
    form="{{ $formId }}"
    class="btn btn-xs btn-danger mt-50"
    onclick="return confirm(@json(__('Are you sure?')))">
    {{ __('delete') }}
</button>
@push('forms')
    <form id="{{ $formId }}" action="{{ $action }}" method="POST" class="d-none">
        @csrf
        @method('DELETE')
    </form>
@endpush
