<div class="card-datatable pt-0">
    <table class="table-bordered datatable-init w-100 table" id="{{ $tableId ?? 'datatable' }}"
        data-url="{{ $ajaxUrl }}" data-columns='@json($columns)'
        data-title="{{ $title ?? 'Data Table' }}">
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{!! $heading !!}</th>
                @endforeach
            </tr>
        </thead>
    </table>
</div>

<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(function() {
        $('.datatable-init').each(function() {
            const table = $(this);
            const ajaxUrl = table.data('url');
            const columns = table.data('columns');
            const title = table.data('title') || 'Data Table';

            const dt = table.DataTable({
                ajax: ajaxUrl,
                columns: columns,
                columnDefs: [{
                        targets: 0,
                        className: 'control',
                        orderable: false,
                        responsivePriority: 1,
                        render: () => ''
                    },
                    {
                        targets: 1,
                        orderable: false,
                        searchable: false,
                        render: () =>
                            '<input type="checkbox" class="dt-checkboxes form-check-input">'
                    },
                    {
                        targets: 2,
                        visible: false
                    },
                    {
                        targets: 3,
                        render: function(data, type, full) {
                            const name = full.full_name;
                            const post = full.post || '';
                            const avatar = full.avatar ?
                                `<img src="/img/avatars/${full.avatar}" class="rounded-circle" alt="Avatar">` :
                                `<span class="avatar-initial rounded-circle bg-label-info">${name.slice(0, 1)}</span>`;
                            return `
              <div class="d-flex align-items-center">
                <div class="avatar me-2">${avatar}</div>
                <div class="d-flex flex-column">
                  <span class="fw-semibold">${name}</span>
                  <small class="text-muted">${post}</small>
                </div>
              </div>
            `;
                        }
                    },
                    {
                        targets: -2,
                        render: function(data, type, full) {
                            const statusMap = {
                                1: {
                                    title: 'Current',
                                    class: 'bg-label-primary'
                                },
                                2: {
                                    title: 'Professional',
                                    class: 'bg-label-success'
                                },
                                3: {
                                    title: 'Rejected',
                                    class: 'bg-label-danger'
                                },
                                4: {
                                    title: 'Resigned',
                                    class: 'bg-label-warning'
                                },
                                5: {
                                    title: 'Applied',
                                    class: 'bg-label-info'
                                }
                            };
                            const status = statusMap[full.status] || {
                                title: data,
                                class: ''
                            };
                            return `<span class="badge rounded-pill ${status.class}">${status.title}</span>`;
                        }
                    },
                    {
                        targets: -1,
                        orderable: false,
                        searchable: false,
                        render: function() {
                            return `
              <div class="d-inline-block">
                <a class="btn btn-sm text-primary btn-icon dropdown-toggle" data-bs-toggle="dropdown">
                  <i class="bx bx-dots-vertical-rounded"></i>
                </a>
                <ul class="dropdown-menu">
                  <li><a class="dropdown-item">Details</a></li>
                  <li><a class="dropdown-item">Archive</a></li>
                  <li><hr class="dropdown-divider"></li>
                  <li><a class="dropdown-item text-danger">Delete</a></li>
                </ul>
              </div>
              <a class="btn btn-sm text-primary btn-icon item-edit">
                <i class="bx bxs-edit"></i>
              </a>
            `;
                        }
                    }
                ],
                order: [
                    [2, 'desc']
                ],
                dom: `
        <'card-header d-flex justify-content-between align-items-center'
          <'head-label'><'dt-action-buttons text-end'B>>
        <'row'<'col-sm-6'l><'col-sm-6'f>>
        <'table-responsive't>
        <'row'<'col-sm-6'i><'col-sm-6'p>>
      `,
                buttons: [{
                        extend: 'collection',
                        className: 'btn btn-label-primary dropdown-toggle me-2',
                        text: '<i class="bx bx-export me-1"></i>Export',
                        buttons: ['print', 'csv', 'excel', 'pdf', 'copy']
                    },
                    {
                        text: '<i class="bx bx-plus me-1"></i><span class="d-none d-lg-inline-block">Add New</span>',
                        className: 'btn btn-primary',
                        action: function() {
                            alert('Add new action triggered!');
                        }
                    }
                ],
                responsive: true
            });

            table.closest('.card-datatable').find('.head-label').html(
                `<h5 class="card-title mb-0">${title}</h5>`);
        });
    });
</script>
