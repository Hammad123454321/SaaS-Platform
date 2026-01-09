@if (is_countable($interviews) && count($interviews) > 0)
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="input-group input-group-merge">
                        <input type="text" class="form-control" id="interview_date_between"
                            placeholder="<?= get_label('date_between', 'Date Between') ?>" autocomplete="off">
                    </div>
                    <input type="hidden" id="interview_date_between_from" name="start_date" />
                                <input type="hidden" id="interview_date_between_to" name="end_date" />
                </div>
                <div class="col-md-3 mb-3">
                    <select class="form-select js-example-basic-multiple" id="sort" name="sort"
                        aria-label="Default select example"
                        data-placeholder="<?= get_label('select_sort_by', 'Select Sort By') ?>" data-allow-clear="true">
                        <option></option>
                        <option value="newest" <?= request()->sort && request()->sort == 'newest' ? 'selected' : '' ?>>
                            <?= get_label('newest', 'Newest') ?></option>
                        <option value="oldest" <?= request()->sort && request()->sort == 'oldest' ? 'selected' : '' ?>>
                            <?= get_label('oldest', 'Oldest') ?></option>
                        <option value="recently-updated"
                            <?= request()->sort && request()->sort == 'recently-updated' ? 'selected' : '' ?>>
                            <?= get_label('most_recently_updated', 'Most recently updated') ?></option>
                        <option value="earliest-updated"
                            <?= request()->sort && request()->sort == 'earliest-updated' ? 'selected' : '' ?>>
                            <?= get_label('least_recently_updated', 'Least recently updated') ?></option>
                    </select>
                </div>

           <div class="col-md-4 mb-3">
            <select class="form-select" id="interview_status" name="status"
                aria-label="Default select example"
                data-placeholder="{{ get_label('filter_by_statuses', 'Filter by statuses') }}"
                data-allow-clear="true">
                <option value="">-- Select Status --</option>
                <option value="scheduled">Scheduled</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>




            </div>
            {{ $slot }}
            <div class="table-responsive text-nowrap">
                <input type="hidden" id="data_type" value="interviews">
                {{-- <input type="hidden" id="data_reload" value="0"> --}}
                <table id="table" data-toggle="table" data-url="{{ route('interviews.list') }}"
                    data-icons-prefix="bx" data-icons="icons" data-show-refresh="true" data-total-field="total"
                    data-data-field="rows" data-page-list="[5, 10, 20, 50, 100]" data-search="true"
                    data-side-pagination="server" data-pagination="true" data-sort-name="id" data-sort-order="desc"
                    data-mobile-responsive="true" data-query-params="queryParams">
                    <thead>
                        <tr>
                            <th data-checkbox="true"></th>
                            <th data-field="id" data-sortable="true">{{ get_label('id', 'ID') }}</th>
                            <th data-field="candidate" data-sortable="true">{{ get_label('candidate', 'Candidate') }}
                            </th>
                            <th data-field="interviewer">{{ get_label('interviewer', 'Interviewer') }}</th>
                            <th data-field="round" data-escap="false">{{ get_label('round', 'Round') }}</th>
                            <th data-field="scheduled_at" data-sortable="true">
                                {{ get_label('scheduled_at', 'Scheduled At') }}</th>
                            <th data-field="status" data-sortable="true">{{ get_label('status', 'Status') }}</th>
                            <th data-field="location" data-sortable="true">{{ get_label('location', 'Location') }}</th>
                            <th data-field="mode" data-sortable="true">{{ get_label('mode', 'Mode') }}</th>
                            <th data-field="created_at" data-sortable="true">
                                {{ get_label('created_at', 'Created at') }}</th>
                            <th data-field="updated_at" data-sortable="true">
                                {{ get_label('updated_at', 'Updated at') }}</th>
                            <th data-field="actions">{{ get_label('actions', 'Actions') }}</th>
                        </tr>
                    </thead>
                </table>
            </div>

        </div>
    </div>
@else
    <?php $type = 'Interview'; ?>
    <x-empty-state-card :type="$type" />
@endif
