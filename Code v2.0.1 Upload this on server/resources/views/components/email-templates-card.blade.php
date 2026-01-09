@if (is_countable($templates) && count($templates) > 0)
    <div class="card">
        <div class="card-body">
            {{ $slot }}
            <div class="table-responsive text-nowrap">
                <input type="hidden" id="data_type" value="email_templates">
                <table id="table" data-toggle="table" data-loading-template="loadingTemplate"
                    data-url="{{ route('email.templates.list') }}" data-icons-prefix="bx" data-icons="icons"
                    data-show-refresh="true" data-total-field="total" data-trim-on-search="false" data-data-field="rows"
                    data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-side-pagination="server"
                    data-show-columns="true" data-pagination="true" data-sort-name="id" data-sort-order="desc"
                    data-mobile-responsive="true" data-query-params="queryParams">
                    <thead>
                        <tr>
                            <th data-checkbox="true"></th>
                            <th data-field="id" data-sortable="true">{{ get_label('id', 'ID') }}</th>
                            <th data-field="name" data-sortable="true">{{ get_label('name', 'Name') }}</th>
                            <th data-field="subject">{{ get_label('subject', 'Subject') }}</th>
                            <th data-field="placeholders" data-escap="false">
                                {{ get_label('placeholders', 'Placeholders') }}</th>
                            <th data-field="created_at" data-sortable="true">{{ get_label('created_at', 'Created at') }}
                            </th>
                            <th data-field="updated_at" data-sortable="true">{{ get_label('updated_at', 'Updated at') }}
                            </th>
                            <th data-field="actions">{{ get_label('actions', 'Actions') }}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
@else
    <?php $type = 'Email Templates'; ?>
    <x-empty-state-card :type="$type" />
@endif
