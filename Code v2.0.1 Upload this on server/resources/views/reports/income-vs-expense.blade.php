@extends('layout')
@section('title')
    {{ get_label('income_vs_expense_report', 'Income vs Expense Report') }} - {{ get_label('reports', 'Reports') }}
@endsection
@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between mt-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-style1">
                        <li class="breadcrumb-item">
                            <a href="{{ route('home.index') }}">{{ get_label('home', 'Home') }}</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="#">{{ get_label('reports', 'Reports') }}</a>
                        </li>
                        <li class="breadcrumb-item active">
                            {{ get_label('income_vs_expense', 'Income vs Expense') }}
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
        <!-- Summary Cards -->
        <div class="d-flex mb-4 flex-wrap gap-3">
            <div class="card flex-grow-1 border-0 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <i class="bx bx-wallet fs-2 text-success me-3"></i>
                    <div>
                        <h6 class="card-title mb-1">{{ get_label('total_income', 'Total Income') }}</h6>
                        <p class="card-text mb-0" id="total_income">{{ get_label('loading', 'Loading...') }}</p>
                    </div>
                </div>
            </div>
            <div class="card flex-grow-1 border-0 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <i class="bx bx-credit-card fs-2 text-danger me-3"></i>
                    <div>
                        <h6 class="card-title mb-1">{{ get_label('total_expense', 'Total Expense') }}</h6>
                        <p class="card-text mb-0" id="total_expenses">{{ get_label('loading', 'Loading...') }}</p>
                    </div>
                </div>
            </div>
            <div class="card flex-grow-1 border-0 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <i class="bx bx-bar-chart fs-2 text-primary me-3"></i>
                    <div>
                        <h6 class="card-title mb-1">{{ get_label('profit_or_loss', 'Profit or Loss') }}</h6>
                        <p class="card-text mb-0" id="profit_or_loss">{{ get_label('loading', 'Loading...') }}</p>
                    </div>
                </div>
            </div>
        </div>
        <input type="hidden" id="filter_date_range_from">
        <input type="hidden" id="filter_date_range_to">
        <!-- Filters -->
        <div class="row mb-3">
            <div class="col-md-6 mb-md-0 mb-2">
                <input type="text" id="filter_date_range" class="form-control" placeholder="<?= get_label('date_between', 'Date Between') ?>" autocomplete="off">
            </div>
              <div class="col-md-6 d-flex align-items-center justify-content-md-end mb-md-0 mb-2">
                <button class="btn btn-primary" id="export_button" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="{{ get_label('export_income_expense_report', 'Export Income vs Expense Report') }}">
                <i class="bx bx-export"></i>
            </button>
            </div>
        </div>
        <!-- Invoices Table -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">{{ get_label('invoices', 'Invoices') }}</h5>
                <table class="table" id="invoices_table">
                    <thead>
                        <tr>
                            <th>{{ get_label('id', 'ID') }}</th>
                            <th>{{ get_label('date_range','Date Range') }}</th>
                            <th>{{ get_label('amount', 'Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Expenses Table -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">{{ get_label('expenses', 'Expenses') }}</h5>
                <table class="table" id="expenses_table">
                    <thead>
                        <tr>
                            <th>{{ get_label('id', 'ID') }}</th>
                            <th>{{ get_label('title' ,'Title') }}</th>
                            <th>{{ get_label('amount', 'Amount') }}</th>
                            <th>{{ get_label('date', 'Date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<script>var export_income_vs_expense_url = "{{ route('reports.export-income-vs-expense-report') }}";</script>
<script src="{{ asset('assets/js/pages/income-vs-expense-report.js') }}"></script>
@endsection
