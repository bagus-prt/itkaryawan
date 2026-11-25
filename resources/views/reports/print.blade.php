<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        @page {
            size: A4 {{ $landscape ? 'landscape' : 'portrait' }};
            margin: 15mm 14mm 17mm;
            @bottom-right { content: "Halaman " counter(page) " / " counter(pages); font-size: 9pt; color: #475366; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f6f8fb; color: #182433; font: 10pt/1.4 Arial, sans-serif; }
        .toolbar { padding: 12px 20px; display: flex; justify-content: center; background: #182433; }
        .toolbar button { border: 0; border-radius: 4px; padding: 9px 15px; background: #066fd1; color: #fff; font: inherit; cursor: pointer; }
        .paper { max-width: {{ $landscape ? '297' : '210' }}mm; min-height: 180mm; padding: 15mm 14mm; margin: 18px auto; background: #fff; }
        header { border-bottom: 2px solid #183858; padding-bottom: 10px; text-align: center; }
        .branding { display: flex; justify-content: flex-end; align-items: center; height: 13mm; margin-bottom: 7px; }
        .branding img { display: block; width: 38mm; height: auto; }
        h1 { margin: 0; font-size: 16pt; line-height: 1.2; text-transform: uppercase; }
        .period { margin: 5px 0 0; color: #475366; }
        .metadata { margin: 12px 0; overflow-wrap: anywhere; }
        .metadata p { margin: 0; }
        header + table { margin-top: 12px; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        th, td { border: 1px solid #bcc5d0; padding: 5px 7px; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
        th { font-weight: 700; background: #e9f0f9; }
        tr { break-inside: avoid; }
        @media print {
            body { background: #fff; print-color-adjust: exact; }
            .toolbar { display: none; }
            .paper { max-width: none; min-height: 0; padding: 0; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Cetak / Simpan PDF</button></div>
    <main class="paper">
        <header>
            <div class="branding">
                <img src="{{ \Illuminate\Support\Facades\Vite::asset('pertamina_patra.webp') }}" alt="Pertamina Patra Niaga">
            </div>
            <h1>{{ $title }}</h1>
            <p class="period">{{ $period }}</p>
        </header>
        @if ($activeFilters)
            <div class="metadata">
                <p>{{ implode(' · ', $activeFilters) }}</p>
            </div>
        @endif
        <table>
            <thead>
                <tr>
                    @foreach ($columns as $label)
                        <th scope="col">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell ?? '—' }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columns) }}">Belum ada data yang sesuai dengan filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </main>
</body>
</html>
