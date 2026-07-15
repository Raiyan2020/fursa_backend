@push('scripts')
   <script src="{{ asset('_dashboard/app-assets/vendors/js/ui/jquery.sticky.js') }}"></script>
   <script src="{{ asset('datatables/datatables.min.js') }}"></script>
    <script>
        (function () {
            function getContainer($table) {
                return $table.closest('.card-body, .card-content, .table-responsive').first();
            }

            function ensureTableWrap($table) {
                let $mount = $table.closest('.dataTables_wrapper');
                if (!$mount.length) { $mount = $table; }
                if (!$mount.parent().hasClass('admin-table-wrap__content')) {
                    $mount.wrap('<div class="admin-table-wrap__content"></div>');
                }
                let $content = $mount.parent('.admin-table-wrap__content');
                if (!$content.parent().hasClass('admin-table-wrap')) {
                    $content.wrap('<div class="admin-table-wrap"></div>');
                }
                $table.addClass('admin-data-table');
            }

            function ensureToolbar($table) {
                let tableId = $table.attr('id') || ('dt-' + Math.random().toString(36).slice(2, 8));
                $table.attr('id', tableId);
                let $container = getContainer($table);
                if (!$container.length) { return; }
                if ($container.find('.admin-table-toolbar-block[data-table-id="' + tableId + '"]').length) { return; }

                let toolbarHtml = `
                    <div class="admin-table-toolbar-block" data-table-id="${tableId}">
                        <div class="admin-table-toolbar">
                            <div class="admin-table-toolbar__actions">
                                <div class="admin-table-toolbar__buttons buttons">
                                    <button type="button" class="admin-tb-btn admin-tb-btn--reload reloadTable">
                                        <i class="feather icon-refresh-cw"></i>
                                        <span>{{ __('Refresh') }}</span>
                                    </button>
                                </div>
                            </div>
                            <div class="admin-table-toolbar__search-wrap">
                                <label class="admin-table-toolbar__search">
                                    <i class="feather icon-search"></i>
                                    <input type="text" class="admin-table-toolbar__search-input" placeholder="{{ __('Search') }}" autocomplete="off">
                                </label>
                            </div>
                            <div class="admin-table-toolbar__end">
                                <div class="admin-tb-perpage dropdown">
                                    <button type="button" class="admin-tb-btn admin-tb-btn--meta dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="feather icon-list"></i>
                                        <span class="admin-tb-perpage__label" data-suffix="{{ __('per page') }}">10 {{ __('per page') }}</span>
                                        <i class="feather icon-chevron-down admin-tb-perpage__chev"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right admin-tb-perpage__menu">
                                        <button type="button" class="dropdown-item admin-tb-perpage__option" data-per-page="10">10</button>
                                        <button type="button" class="dropdown-item admin-tb-perpage__option" data-per-page="20">20</button>
                                        <button type="button" class="dropdown-item admin-tb-perpage__option" data-per-page="30">30</button>
                                        <button type="button" class="dropdown-item admin-tb-perpage__option" data-per-page="50">50</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                $container.prepend(toolbarHtml);
            }

            function normalizeActionButtons($table) {
                $table.find('tbody tr').each(function () {
                    let $row = $(this);
                    let $actionsCell = $row.find('td.product-action, td:last-child').last();
                    if (!$actionsCell.length) { return; }
                    let $buttons = $actionsCell.find('a.btn, button.btn, span.btn');
                    if (!$buttons.length) { return; }
                    if (!$actionsCell.find('.admin-table-actions').length) {
                        $buttons.wrapAll('<div class="admin-table-actions"></div>');
                    }
                    $buttons.each(function () {
                        let $btn = $(this);
                        let classes = 'admin-table-action';
                        let text = ($btn.text() || '').toLowerCase();
                        let iconClasses = ($btn.find('i').attr('class') || '').toLowerCase();
                        if (iconClasses.includes('trash') || text.includes('delete') || $btn.hasClass('btn-danger')) {
                            classes += ' admin-table-action--delete';
                        } else if (iconClasses.includes('edit') || iconClasses.includes('pencil') || text.includes('edit') || $btn.hasClass('btn-warning')) {
                            classes += ' admin-table-action--edit';
                        } else if (iconClasses.includes('eye') || text.includes('show') || text.includes('view') || $btn.hasClass('btn-info')) {
                            classes += ' admin-table-action--view';
                        }
                        if (!$btn.hasClass('admin-table-action')) {
                            $btn.removeClass('btn btn-icon btn-info btn-warning btn-danger').addClass(classes);
                        }
                    });
                });
            }

            function bindToolbarEvents($table) {
                let tableId = $table.attr('id');
                let $toolbar = $('.admin-table-toolbar-block[data-table-id="' + tableId + '"]');
                if (!$toolbar.length || $toolbar.data('bound')) { return; }
                $toolbar.data('bound', true);

                $toolbar.on('input', '.admin-table-toolbar__search-input', function () {
                    let api = $.fn.DataTable && $.fn.DataTable.isDataTable($table) ? $table.DataTable() : null;
                    if (api) { api.search($(this).val()).draw(); }
                });
                $toolbar.on('click', '.reloadTable', function () {
                    let api = $.fn.DataTable && $.fn.DataTable.isDataTable($table) ? $table.DataTable() : null;
                    $toolbar.find('.admin-table-toolbar__search-input').val('');
                    if (api) { api.search('').draw(); }
                });
                $toolbar.on('click', '.admin-tb-perpage__option', function () {
                    let api = $.fn.DataTable && $.fn.DataTable.isDataTable($table) ? $table.DataTable() : null;
                    let size = $(this).data('per-page');
                    $toolbar.find('.admin-tb-perpage__label').text(size + ' {{ __('per page') }}');
                    if (api) { api.page.len(size).draw(); }
                });
            }

            function decorateTable($table) {
                ensureTableWrap($table);
                ensureToolbar($table);
                normalizeActionButtons($table);
                bindToolbarEvents($table);
            }

            function initializeTables() {
                $('table.dataex-html5-selectors').each(function () {
                    let $t = $(this);
                    if (!$.fn.DataTable.isDataTable($t)) {
                        $t.DataTable({
                            dom: 'Brtip',
                            order: [[0, 'desc']],
                            responsive: true,
                            buttons: [],
                            language: {
                                processing: @json(__('admin.datatables.loading')),
                                zeroRecords: @json(__('admin.datatables.no_data')),
                                emptyTable: @json(__('admin.datatables.no_data')),
                                search: '',
                                searchPlaceholder: @json(__('admin.datatables.search')),
                                info: @json(__('admin.datatables.info')),
                                infoEmpty: @json(__('admin.datatables.info_empty')),
                                infoFiltered: @json(__('admin.datatables.info_filtered')),
                                lengthMenu: @json(__('admin.datatables.length_menu')),
                                paginate: {
                                    next: "<i class='next'></i>",
                                    previous: "<i class='previous'></i>"
                                }
                            }
                        });
                    }
                });
            }

            $(document).on('init.dt draw.dt', function (e, settings) {
                let $table = settings && settings.nTable ? $(settings.nTable) : null;
                if ($table && $table.length) { decorateTable($table); }
            });

            $(document).ready(function () {
                initializeTables();
                $('table.dataex-html5-selectors').each(function () { decorateTable($(this)); });
            });
        })();
    </script>
@endpush
@push('styles')
     <link rel="stylesheet" href="{{ asset('datatables/datatables.min.css') }}">
@endpush
