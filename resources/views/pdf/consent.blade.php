<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $template->title }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1c3250;
        }

        h1 {
            font-size: 18px;
            margin-bottom: 4px;
        }

        .meta {
            color: #555;
            margin-bottom: 24px;
        }

        .content {
            white-space: pre-wrap;
            line-height: 1.5;
            margin-bottom: 32px;
        }

        .signature-block {
            border-top: 1px solid #ccc;
            padding-top: 16px;
        }

        .signature-image {
            max-width: 260px;
            max-height: 120px;
            display: block;
            margin-top: 8px;
        }

        table.info {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        table.info td {
            padding: 4px 0;
            vertical-align: top;
        }

        table.info td:first-child {
            width: 160px;
            color: #555;
        }
    </style>
</head>
<body>
    <h1>{{ $template->title }}</h1>
    <p class="meta">{{ __('pdf.patient') }} : {{ $patient->first_name }} {{ $patient->last_name }}</p>

    <div class="content">{{ $consent->content_snapshot }}</div>

    <div class="signature-block">
        <table class="info">
            <tr>
                <td>{{ __('pdf.signer') }}</td>
                <td>{{ $consent->signer_name }}</td>
            </tr>
            <tr>
                <td>{{ __('pdf.accepted_on') }}</td>
                <td>{{ $consent->accepted_at->translatedFormat(__('formats.datetime')) }}</td>
            </tr>
            <tr>
                <td>{{ __('pdf.document_version') }}</td>
                <td>v{{ $consent->version }}</td>
            </tr>
        </table>

        @if($consent->signature_svg)
            <img class="signature-image" src="{{ $consent->signature_svg }}" alt="Signature">
        @endif
    </div>
</body>
</html>
