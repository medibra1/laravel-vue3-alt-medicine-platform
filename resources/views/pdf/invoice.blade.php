<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 20px; margin: 0 0 4px; color: #0f766e; }
        .muted { color: #6b7280; }
        .head { width: 100%; margin-bottom: 28px; }
        .head td { vertical-align: top; }
        .right { text-align: right; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.lines th { text-align: left; font-size: 10px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #d1d5db; padding: 6px 4px; }
        table.lines td { padding: 8px 4px; border-bottom: 1px solid #eef0f3; }
        .totals { width: 45%; margin-left: 55%; margin-top: 16px; border-collapse: collapse; }
        .totals td { padding: 5px 4px; }
        .totals .grand td { font-weight: bold; font-size: 14px; border-top: 1px solid #d1d5db; }
        .badge { display: inline-block; padding: 3px 8px; border: 1px solid #0f766e; color: #0f766e; font-size: 10px; text-transform: uppercase; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <h1>{{ $invoice->center->name }}</h1>
                <div class="muted">
                    {{ $invoice->center->address }}<br>
                    {{ $invoice->center->city }}<br>
                    {{ $invoice->center->phone }} {{ $invoice->center->email }}
                </div>
            </td>
            <td class="right">
                <div style="font-size: 16px; font-weight: bold;">{{ __('Facture') }} {{ $invoice->number }}</div>
                <div class="muted">{{ __('Date') }} : {{ $invoice->issued_on->format('d/m/Y') }}</div>
                <div style="margin-top: 8px;"><span class="badge">{{ __('invoice_status.'.$invoice->status()) }}</span></div>
            </td>
        </tr>
    </table>

    <div class="muted">{{ __('Patient') }}</div>
    <div style="font-size: 14px; font-weight: bold;">{{ $invoice->patient->first_name }} {{ $invoice->patient->last_name }}</div>

    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('Désignation') }}</th>
                <th class="right">{{ __('Qté') }}</th>
                <th class="right">{{ __('Prix unitaire') }}</th>
                <th class="right">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td>{{ $line->label }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format((float) $line->quantity, 2, ',', ' '), '0'), ',') }}</td>
                    <td class="right">{{ number_format((float) $line->unit_price, 2, ',', ' ') }}</td>
                    <td class="right">{{ number_format((float) $line->total, 2, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>{{ __('Total') }}</td><td class="right">{{ number_format((float) $invoice->total, 2, ',', ' ') }} {{ $invoice->currency }}</td></tr>
        @foreach ($invoice->creditNotes as $creditNote)
            <tr><td>{{ __('Avoir') }} {{ $creditNote->number }}</td><td class="right">-{{ number_format((float) $creditNote->amount, 2, ',', ' ') }} {{ $invoice->currency }}</td></tr>
        @endforeach
        <tr><td>{{ __('Déjà réglé') }}</td><td class="right">{{ number_format((float) $invoice->paid_total, 2, ',', ' ') }} {{ $invoice->currency }}</td></tr>
        <tr class="grand"><td>{{ __('Reste à payer') }}</td><td class="right">{{ number_format($invoice->balance(), 2, ',', ' ') }} {{ $invoice->currency }}</td></tr>
    </table>

    @if ($invoice->payments->isNotEmpty())
        <p class="muted" style="margin-top: 28px;">{{ __('Paiements reçus') }}</p>
        <table class="lines">
            @foreach ($invoice->payments as $payment)
                <tr>
                    <td>{{ $payment->paid_on->format('d/m/Y') }}</td>
                    <td>{{ __('payment_method.'.$payment->method) }}</td>
                    <td>{{ $payment->reference }}</td>
                    <td class="right">{{ number_format((float) $payment->amount, 2, ',', ' ') }} {{ $invoice->currency }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($invoice->notes)
        <p style="margin-top: 24px;">{{ $invoice->notes }}</p>
    @endif
</body>
</html>
