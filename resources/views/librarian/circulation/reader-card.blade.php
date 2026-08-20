{{-- Standalone printable reader card (86 × 54 mm, ID-1 / bank-card stock).
     Deliberately does NOT extend layouts.librarian: the page must contain
     nothing but the card when sent to a card printer.

     The code payload is the opaque reader barcode only; it contains no PII. --}}
<!DOCTYPE html>
<html lang="{{ in_array(app()->getLocale(), ['kk', 'ru', 'en'], true) ? app()->getLocale() : 'ru' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('librarian.circulation.reader_card') }} — {{ $profile->ticket_number }}</title>
    <style>
        :root {
            --card-width: 86mm;
            --card-height: 54mm;
            --ink: #000;
            --muted: #444;
            --brand: #001f3f;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            background: #e9eaec;
            color: var(--ink);
            font-family: 'Helvetica Neue', Arial, 'Liberation Sans', sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .toolbar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 18px 12px;
        }

        .toolbar__btn {
            appearance: none;
            border: 1px solid var(--brand);
            background: var(--brand);
            color: #fff;
            border-radius: 6px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }

        .toolbar__btn--ghost {
            background: #fff;
            color: var(--brand);
            border-color: #c4c6cf;
        }

        .sheet {
            display: flex;
            justify-content: center;
            padding: 0 12px 32px;
        }

        .card {
            width: var(--card-width);
            height: var(--card-height);
            padding: 3mm 3.6mm;
            background: #fff;
            color: var(--ink);
            border: 1px solid #b9bcc2;
            border-radius: 2mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
        }

        .card__brand {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            border-bottom: 0.4mm solid var(--ink);
            padding-bottom: 1mm;
        }

        .card__brand-name {
            font-size: 4mm;
            font-weight: 800;
            letter-spacing: 0.35mm;
            line-height: 1;
        }

        .card__brand-note {
            font-size: 2mm;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.15mm;
        }

        .card__holder {
            margin-top: 1.6mm;
        }

        .card__name {
            font-size: 3.6mm;
            font-weight: 700;
            line-height: 1.15;
            max-height: 9mm;
            overflow: hidden;
        }

        .card__meta {
            margin-top: 0.8mm;
            font-size: 2.2mm;
            color: var(--muted);
            line-height: 1.3;
        }

        .card__code-caption {
            font-size: 1.9mm;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12mm;
            color: var(--muted);
            line-height: 1;
        }

        .card__barcode {
            font-family: 'Courier New', 'DejaVu Sans Mono', monospace;
            font-size: 6.6mm;
            font-weight: 700;
            letter-spacing: 0.7mm;
            line-height: 1.05;
            white-space: nowrap;
            overflow: hidden;
        }
        .card__codes { display:grid; grid-template-columns:1fr 15mm; gap:2mm; align-items:end; }
        .card__machine svg { display:block; width:100%; height:12mm; }
        .card__qr svg { display:block; width:15mm; height:15mm; }

        .card__footer {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 2mm;
            border-top: 0.25mm solid #999;
            padding-top: 0.9mm;
            font-size: 2mm;
            color: var(--muted);
            line-height: 1.2;
        }

        .card__ticket {
            font-family: 'Courier New', 'DejaVu Sans Mono', monospace;
            font-weight: 700;
            font-size: 2.5mm;
            color: var(--ink);
            white-space: nowrap;
        }

        .card__scan-note {
            flex: 1;
            overflow: hidden;
        }

        @media print {
            /* 94 × 62 mm stock minus a 4 mm margin leaves the 86 × 54 mm area. */
            @page {
                size: 94mm 62mm;
                margin: 4mm;
            }

            html, body {
                background: #fff;
                width: auto;
                height: auto;
            }

            .toolbar,
            .no-print {
                display: none !important;
            }

            .sheet {
                padding: 0;
                display: block;
            }

            .card {
                width: auto;
                height: auto;
                border: 0;
                border-radius: 0;
                padding: 0;
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button class="toolbar__btn" type="button" onclick="window.print()">{{ __('librarian.copies.print') }}</button>
        <a class="toolbar__btn toolbar__btn--ghost" href="{{ route('librarian.circulation.issue') }}">{{ __('common.actions.back') }}</a>
    </div>

    <div class="sheet">
        <div class="card">
            <div>
                <div class="card__brand">
                    <span class="card__brand-name">Kazakh University of Technology and Business named after K. Kulazhanov</span>
                    <span class="card__brand-note">{{ __('librarian.circulation.reader_card') }}</span>
                </div>

                <div class="card__holder">
                    <div class="card__name">{{ $reader->name }}</div>
                    <div class="card__meta">
                        {{ __('librarian.circulation.reader_categories.'.$profile->category) }}
                        @if ($reader->department) · {{ $reader->department }} @endif
                    </div>
                </div>
            </div>

            <div class="card__codes">
                <div class="card__machine"><div class="card__code-caption">{{ __('librarian.copies.fields.barcode') }}</div>{!! $code128Svg !!}<div class="card__ticket">{{ $profile->barcode ?: $profile->ticket_number }}</div></div>
                <div class="card__qr">{!! $qrSvg !!}</div>
            </div>

            <div class="card__footer">
                <span class="card__scan-note">{{ __('librarian.circulation.reader_card_scan_note') }}</span>
                <span class="card__ticket">{{ $profile->ticket_number }}</span>
            </div>
        </div>
    </div>
</body>
</html>
