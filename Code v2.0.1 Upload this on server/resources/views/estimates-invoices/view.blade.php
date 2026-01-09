@php
use App\Models\Tax; // Adjust the namespace and path according to your application's structure
@endphp
@extends('layout') <!-- Assuming you have a layout file -->
@section('title')
<?= $estimate_invoice->type == 'estimate' ? get_label('view_estimate', 'View estimate') : get_label('view_invoice', 'View invoice') ?>
@endsection
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between mb-2 mt-4" id="section-not-to-print">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1">
                    <li class="breadcrumb-item">
                        <a href="{{url('home')}}"><?= get_label('home', 'Home') ?></a>
                    </li>
                    <li class="breadcrumb-item active">
                        <a href="{{ url('estimates-invoices') }}"><?= get_label('etimates_invoices', 'Estimates/Invoices') ?></a>
                    </li>
                    <li class="breadcrumb-item active">
                        <?= $estimate_invoice->type == 'estimate' ? get_label('view_estimate', 'View estimate') : get_label('view_invoice', 'View invoice') ?>
                    </li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{ url('estimates-invoices/pdf/' . $estimate_invoice->id) }}" class="ms-2">
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="{{ get_label('view_pdf', 'View PDF') }}">
                    <i class="bx bx-file"></i>
                </button>
            </a>
            <a href="{{url('estimates-invoices')}}"><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title=" <?= get_label('etimates_invoices', 'Estiamtes/Invoices') ?>"><i class="bx bx-list-ul"></i></button></a>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div id='section-to-print'>
                <!-- Row for logo and date time -->
                <div class="row mb-4">
                    <div class="col-md-4 text-start">
                        <img src="{{asset($general_settings['full_logo'])}}" alt="" width="200px" />
                    </div>

                    <div class="col-md-8 text-end">
                        <p>
                            <?php
                            $timezone = config('app.timezone');
                            $currentTime = now()->tz($timezone);
                            echo '<span class="text-muted">' . $currentTime->format($php_date_format . ' H:i:s') . '</span>';
                            ?>
                        </p>
                    </div>
                </div>
                <div class="row">
                    <!-- Company details -->
                    <div class="col-md-4">
                        <p class="no-margin-p">
                            <strong>{{ $general_settings['company_title'] }}</strong>
                        </p>
                        <!-- Company address and contact details -->
                        @if($company_info['companyAddress'])
                        <p class="no-margin-p">{{ $company_info['companyAddress'] }}</p>
                        @endif
                        @php
                        $addressParts = [
                        $company_info['companyCity'] ?? '',
                        $company_info['companyState'] ?? '',
                        $company_info['companyCountry'] ?? '',
                        $company_info['companyZip'] ?? '',
                        ];
                        $addressParts = array_filter($addressParts); // Remove empty values
                        $city_state_country_zip = implode(', ', $addressParts);
                        @endphp
                        @if($city_state_country_zip)
                        <p class="no-margin-p">{{ $city_state_country_zip }}</p>
                        @endif
                        @if($company_info['companyPhone'])
                        <p class="no-margin-p">{{get_label('phone','Phone')}}: {{ $company_info['companyPhone'] }}</p>
                        @endif
                        @if($company_info['companyEmail'])
                        <p class="no-margin-p">{{get_label('email','Email')}}: {{ $company_info['companyEmail'] }}</p>
                        @endif
                        @if($company_info['companyWebsite'])
                        <p class="no-margin-p">{{get_label('website','Website')}}: {{ $company_info['companyWebsite'] }}</p>
                        @endif
                        @if($company_info['companyVatNumber'])
                        <p class="no-margin-p">{{get_label('vat_number','VAT Number')}}: {{ $company_info['companyVatNumber'] }}</p>
                        @endif
                    </div>

                    <!-- Billing details -->
                    <div class="col-md-4">
                        <strong>{{ get_label('billing_details', 'Billing details') }}</strong>
                        <hr>
                        @if($estimate_invoice->name)
                        <p class="no-margin-p"><strong>{{ $estimate_invoice->name }}</strong></p>
                        @endif
                        @if($estimate_invoice->address)
                        <p class="no-margin-p">{{ $estimate_invoice->address }}</p>
                        @endif
                        @if($city_state_country_zip)
                        <p class="no-margin-p">{{ $city_state_country_zip }}</p>
                        @endif
                        @if($estimate_invoice->phone)
                        <p class="no-margin-p">{{ $estimate_invoice->phone }}</p>
                        @endif
                    </div>

                    <!-- Estimate details -->
                    <div class="col-md-4">
                        <strong>{{ get_label('estimate_details', 'Estimate details') }}</strong>
                        <hr>
                        <p class="no-margin-p"><strong>{{ get_label('estimate_no', 'Estimate No.') }}:</strong> #{{ $estimate_invoice->type == 'estimate' ? get_label('estimate_id_prefix', 'ESTMT-') : get_label('invoice_id_prefix', 'INVC-') }} {{$estimate_invoice->id}}</p>
                        <p class="no-margin-p"><strong>{{ get_label('from_date', 'From date') }}:</strong> {{$estimate_invoice->from_date}}</p>
                        <p class="no-margin-p"><strong>{{ get_label('to_date', 'To date') }}:</strong> {{$estimate_invoice->to_date}}</p>
                        <p class="no-margin-p"><strong>{{ get_label('status', 'Status') }}:</strong> <?= $estimate_invoice->status ?></p>
                    </div>
                </div>
                <hr>
                <!-- Display Items -->
                <div class="row mt-4 mx-1">
                    <div class="col-md-12">
                        <h5><?= $estimate_invoice->type == 'estimate' ? get_label('estimate_summary', 'Estimate summary') : get_label('invoice_summary', 'Invoice summary') ?></h5>
                        @if(count($estimate_invoice->items) > 0)
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ get_label('product_service', 'Product/Service') }}</th>
                                    <th>{{ get_label('quantity', 'Quantity') }}</th>
                                    <th>{{ get_label('unit', 'Unit') }}</th>
                                    <th>{{ get_label('rate', 'Rate') }}</th>
                                    <th>{{ get_label('tax', 'Tax') }}</th>
                                    <th>{{ get_label('amount', 'Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                $count = 0;
                                @endphp
                                @foreach($estimate_invoice->items as $item)
                                @php
                                $count++;
                                @endphp
                                <tr>
                                    <td>{{ $count }}</td>
                                    <td>{{ $item->title }} <br><span class="text-muted">{{ $item->description }}</span></td> <!-- Assuming 'title' is the attribute containing product/service name -->
                                    <td>{{ $item->pivot->qty }}</td>
                                    <td>
                                        {{ $item->pivot->unit_id ? $item->unit->title : '-' }}
                                    </td>
                                    <td>{{ format_currency($item->pivot->rate) }}</td>
                                    <td>
                                        {{ $item->pivot->tax_id ? Tax::find($item->pivot->tax_id)->title .' - '. get_tax_data($item->pivot->tax_id, $item->pivot->rate * $item->pivot->qty,1)['dispTax'] : '-' }}
                                    </td>
                                    <td>{{ format_currency($item->pivot->amount) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @else
                        <p><?= get_label('no_items_found', 'No items found') ?></p>
                        @endif
                    </div>
                </div>
                @if ($estimate_invoice->type == 'invoice')
                <div class="row mt-4 mx-1">
                    <div class="col-md-12">
                        <h5><?= get_label('payment_summary', 'Payment summary') ?></h5>
                        @if(count($estimate_invoice->payments) > 0)
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ get_label('id', 'ID') }}</th>
                                    <th>{{ get_label('amount', 'Amount') }}</th>
                                    <th>{{ get_label('payment_method', 'Payment method') }}</th>
                                    <th>{{ get_label('note', 'Note') }}</th>
                                    <th>{{ get_label('payment_date', 'Payment date') }}</th>
                                    <th>{{ get_label('amount_left', 'Amount left') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                $i = 1;
                                @endphp
                                @foreach($estimate_invoice->payments as $payment)
                                @php
                                // Get the total paid amount for the invoice
                                $paid_amount = $estimate_invoice->payments->where('id', '<=', $payment->id)->sum('amount');
                                    // Calculate the amount left
                                    $amount_left = $estimate_invoice->final_total - $paid_amount;
                                    @endphp
                                    <tr>
                                        <td>{{ $i }}</td>
                                        <td>{{ $payment->id }}</td>
                                        <td>{{ format_currency($payment->amount) }}</td>
                                        <td>{{ $payment->paymentMethod->title ?? '-' }}</td>
                                        <td>{{ $payment->note ?? '-' }}</td>
                                        <td>{{ format_date($payment->payment_date) }}</td>
                                        <td>{{ format_currency($amount_left) }}</td>
                                    </tr>
                                    @php
                                    $i++;
                                    @endphp
                                    @endforeach
                            </tbody>
                        </table>
                        @else
                        <p><?= get_label('no_payments_found_invoice', 'No payments found for this invoice.') ?></p>
                        @endif
                    </div>
                </div>
                @endif
                <div class="row mt-4 mx-1">
                    <div class="col-md-6">
                    </div>
                    <div class="col-md-6">
                        <!-- Net Payable -->
                        <div class="text-end mt-4">
                            <div class="invoice-detail-item">
                                <div class="invoice-detail-name"><?= get_label('sub_total', 'Sub total') ?></div>
                                <div class="invoice-detail-value">{{ format_currency($estimate_invoice->total) }}</div>
                            </div>
                            <div class="invoice-detail-item">
                                <div class="invoice-detail-name"><?= get_label('tax', 'Tax') ?></div>
                                <div class="invoice-detail-value">{{ format_currency($estimate_invoice->tax_amount) }}</div>
                            </div>
                            <div class="invoice-detail-item">
                                <div class="invoice-detail-name"><?= get_label('final_total', 'Final total') ?></div>
                                <div class="invoice-detail-value">{{ format_currency($estimate_invoice->final_total) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Note -->
                <div class="row mt-4 mx-1">
                    <div class="col-md-6">
                        @if ($estimate_invoice->note)
                        <h5><?= get_label('note', 'Note') ?></h5>
                        <p>{{ $estimate_invoice->note ?: '-' }}</p>
                        @endif
                        @if ($estimate_invoice->personal_note)
                        <h5><?= get_label('personal_note', 'Personal note') ?></h5>
                        <p>{{ $estimate_invoice->personal_note ?: '-' }}</p>
                        @endif
                    </div>
                </div>
                <div class="row mt-5">
                    <div class="col-md-6 text-start">
                        <span class="text-muted">
                            <strong><?= get_label('created_at', 'Created at') ?>:</strong>
                            {{ format_date($estimate_invoice->created_at,true) }}
                        </span>
                    </div>
                    <div class="col-md-6 text-end">
                        <span class="text-muted">
                            <strong><?= get_label('last_updated_at', 'Last updated at') ?>:</strong>
                            {{ format_date($estimate_invoice->updated_at,true) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection