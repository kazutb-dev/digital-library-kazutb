{{-- Standalone printable copy label (62 × 40 mm), with local Code 128 and QR. --}}
<!DOCTYPE html>
<html lang="{{ in_array(app()->getLocale(), ['kk', 'ru', 'en'], true) ? app()->getLocale() : 'ru' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('librarian.copies.label') }} — {{ $copy->inventory_number }}</title>
    <style>
        :root {
            --label-width: 62mm;
            --label-height: 40mm;
            --ink: #000;
            --muted: #444;
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
            border: 1px solid #001f3f;
            background: #001f3f;
            color: #fff;
            border-radius: 6px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .toolbar__btn--ghost {
            background: #fff;
            color: #001f3f;
            border-color: #c4c6cf;
        }

        .sheet {
            display: flex;
            justify-content: center;
            padding: 0 12px 32px;
        }

        .label {
            width: var(--label-width);
            height: var(--label-height);
            padding: 2.2mm 2.6mm;
            background: #fff;
            color: var(--ink);
            border: 1px solid #b9bcc2;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
        }

        .label__brand {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            border-bottom: 0.4mm solid var(--ink);
            padding-bottom: 0.8mm;
        }

        .label__brand-name {
            font-size: 3.4mm;
            font-weight: 800;
            letter-spacing: 0.35mm;
            line-height: 1;
        }

        .label__brand-note {
            font-size: 1.9mm;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.15mm;
        }

        .label__title {
            margin-top: 1.2mm;
            font-size: 2.5mm;
            font-weight: 700;
            line-height: 1.15;
            max-height: 6mm;
            overflow: hidden;
        }

        .label__author {
            margin-top: 0.6mm;
            font-size: 2.1mm;
            color: var(--muted);
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .label__codes {
            margin-top: 1mm;
            display: grid;
            grid-template-columns: 1fr 12mm;
            align-items: end;
            gap: 1mm;
        }
        .label__machine svg { display:block; width:100%; height:10mm; }
        .label__qr svg { display:block; width:12mm; height:12mm; }

        .label__code-caption {
            font-size: 1.8mm;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12mm;
            color: var(--muted);
            line-height: 1;
        }

        .label__inventory {
            font-family: 'Courier New', 'DejaVu Sans Mono', monospace;
            font-size: 6.2mm;
            font-weight: 700;
            letter-spacing: 0.5mm;
            line-height: 1.05;
            white-space: nowrap;
            overflow: hidden;
        }

        .label__barcode {
            font-family: 'Courier New', 'DejaVu Sans Mono', monospace;
            font-size: 3.6mm;
            font-weight: 700;
            letter-spacing: 0.45mm;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
        }

        .label__footer {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1.5mm;
            border-top: 0.25mm solid #999;
            padding-top: 0.7mm;
            font-size: 1.9mm;
            color: var(--muted);
            line-height: 1.15;
        }

        .label__shelf {
            font-family: 'Courier New', 'DejaVu Sans Mono', monospace;
            font-weight: 700;
            font-size: 2.3mm;
            color: var(--ink);
            white-space: nowrap;
        }

        .label__scan-note {
            flex: 1;
            overflow: hidden;
        }

        @media print {
            /* 70 × 48 mm stock minus a 4 mm margin leaves the 62 × 40 mm print area. */
            @page {
                size: 70mm 48mm;
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

            .label {
                width: auto;
                height: auto;
                border: 0;
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
        <a class="toolbar__btn toolbar__btn--ghost" href="{{ route('librarian.copies.show', $copy) }}">{{ __('common.actions.back') }}</a>
    </div>

    <div class="sheet">
        <div class="label">
            <div>
                <div class="label__brand">
                    <span class="label__brand-name">KazUTB</span>
                    <span class="label__brand-note">{{ __('librarian.copies.label') }}</span>
                </div>

                <div class="label__title">{{ \Illuminate\Support\Str::limit($copy->bibliographicRecord?->title ?? '—', 70) }}</div>
                <div class="label__author">{{ $copy->bibliographicRecord?->primary_author ?: '—' }}</div>
            </div>

            <div class="label__codes">
                <div class="label__machine">{!! $code128Svg !!}<div class="label__barcode">{{ $copy->barcode ?: $copy->inventory_number }}</div></div>
                <div class="label__qr">{!! $qrSvg !!}</div>
            </div>

            <div class="label__footer">
                <span class="label__scan-note">{{ __('librarian.circulation.copy_code_placeholder') }}</span>
                @if ($copy->shelf_location)
                    <span class="label__shelf">{{ $copy->shelf_location }}</span>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
