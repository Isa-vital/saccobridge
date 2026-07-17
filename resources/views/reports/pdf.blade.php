<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        .meta { color: #555; margin-bottom: 16px; }
        h2 { font-size: 13px; margin: 14px 0 6px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background: #f0f0f0; }
        td.num, th.num { text-align: right; }
        tfoot td { font-weight: bold; background: #fafafa; }
        .footer { position: fixed; bottom: 0; font-size: 9px; color: #888; }
    </style>
</head>
<body>
    <h1>{{ $tenant ? $tenant . ' — ' : '' }}{{ $title }}</h1>
    <div class="meta">{{ $meta }} · Generated {{ now()->format('Y-m-d H:i') }} · SaccoBridge</div>

    @foreach ($tables as $table)
        <h2>{{ $table['title'] }}</h2>
        <table>
            <thead>
                <tr>
                    @foreach ($table['columns'] as $column)
                        <th>{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($table['rows'] as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($table['columns']) }}">No data.</td></tr>
                @endforelse
            </tbody>
            @isset ($table['footer'])
                <tfoot>
                    <tr>
                        @foreach ($table['footer'] as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            @endisset
        </table>
    @endforeach

    <div class="footer">Produced by SaccoBridge for UMRA Tier 4 reporting.</div>
</body>
</html>
