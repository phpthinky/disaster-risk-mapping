<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>@yield('title', 'Report') — Sablayan DRRM</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            color: #1a1a2e;
            background: #fff;
        }
        .page-header {
            border-bottom: 2px solid #1a1a2e;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }
        .page-header h1 {
            font-size: 13pt;
            font-weight: bold;
            color: #1a1a2e;
        }
        .page-header .meta {
            font-size: 8pt;
            color: #555;
            margin-top: 2px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }
        thead th {
            background: #1a1a2e;
            color: #fff;
            padding: 5px 8px;
            text-align: left;
            font-size: 8.5pt;
            font-weight: bold;
        }
        thead th.text-right { text-align: right; }
        tbody td {
            padding: 4px 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 8.5pt;
            vertical-align: top;
        }
        tbody tr:nth-child(even) td { background: #f9fafb; }
        tfoot td {
            padding: 5px 8px;
            background: #e5e7eb;
            font-weight: bold;
            font-size: 8.5pt;
            border-top: 2px solid #1a1a2e;
        }
        .text-right { text-align: right; }
        .section-header {
            background: #f1f5f9;
            border-left: 4px solid #1a1a2e;
            padding: 4px 8px;
            font-weight: bold;
            font-size: 9pt;
            margin-top: 10px;
            margin-bottom: 4px;
        }
        .badge-danger   { background: #dc2626; color: #fff; padding: 1px 5px; border-radius: 3px; font-size: 7.5pt; }
        .badge-warning  { background: #d97706; color: #fff; padding: 1px 5px; border-radius: 3px; font-size: 7.5pt; }
        .badge-info     { background: #0891b2; color: #fff; padding: 1px 5px; border-radius: 3px; font-size: 7.5pt; }
        .badge-success  { background: #16a34a; color: #fff; padding: 1px 5px; border-radius: 3px; font-size: 7.5pt; }
        .badge-secondary{ background: #6b7280; color: #fff; padding: 1px 5px; border-radius: 3px; font-size: 7.5pt; }
        .page-footer {
            margin-top: 16px;
            padding-top: 6px;
            border-top: 1px solid #d1d5db;
            font-size: 7.5pt;
            color: #9ca3af;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>
    <div class="page-header">
        <h1>@yield('title', 'Report')</h1>
        <div class="meta">
            Municipality of Sablayan — DRRM Office &nbsp;|&nbsp;
            Generated: {{ now()->format('F j, Y  H:i') }}
            @hasSection('filter-note')
                &nbsp;|&nbsp; @yield('filter-note')
            @endif
        </div>
    </div>

    @yield('content')

    <div class="page-footer">
        <span>Sablayan Disaster Risk Reduction &amp; Management</span>
        <span>Confidential — For Official Use Only</span>
    </div>
</body>
</html>
