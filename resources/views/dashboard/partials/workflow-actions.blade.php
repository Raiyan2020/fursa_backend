<script>
    function forsaPostForm(url, data) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        var token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = '{{ csrf_token() }}';
        form.appendChild(token);
        Object.keys(data || {}).forEach(function (key) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = data[key];
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    }

    function forsaApprove(url) {
        NafasConfirm.fire({
            title: "{{ __('Do you want to approve this item ?') }}",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: "{{ __('Approve') }}",
            cancelButtonText: "{{ __('Cancel') }}"
        }).then(function (result) {
            if (result.isConfirmed) { forsaPostForm(url, {}); }
        });
    }

    function forsaReject(url) {
        NafasConfirm.fire({
            title: "{{ __('Reject item') }}",
            input: 'textarea',
            inputLabel: "{{ __('Rejection reason') }}",
            inputPlaceholder: "{{ __('Rejection reason') }}",
            showCancelButton: true,
            confirmButtonText: "{{ __('Reject') }}",
            cancelButtonText: "{{ __('Cancel') }}",
            inputValidator: function (value) {
                if (!value) { return "{{ __('admin.messages.rejection_reason_required') }}"; }
            }
        }).then(function (result) {
            if (result.isConfirmed) { forsaPostForm(url, { reason: result.value }); }
        });
    }

    function forsaConfirmPost(url, title) {
        NafasConfirm.fire({
            title: title,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: "{{ __('Confirm') }}",
            cancelButtonText: "{{ __('Cancel') }}"
        }).then(function (result) {
            if (result.isConfirmed) { forsaPostForm(url, {}); }
        });
    }
</script>
