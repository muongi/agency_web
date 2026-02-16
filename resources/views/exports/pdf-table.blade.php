<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 20px;
        }
        h1 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #333;
        }
        .meta {
            font-size: 9px;
            color: #666;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #f0f0f0;
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
            font-weight: bold;
            font-size: 9px;
        }
        td {
            border: 1px solid #ddd;
            padding: 5px;
            font-size: 8px;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .filters {
            margin-bottom: 10px;
            font-size: 8px;
            color: #666;
        }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    
    <div class="meta">
        <div>Exported on: {{ $exportDate }}</div>
        @if(!empty($filters))
            <div class="filters">
                Filters:
                @foreach($filters as $key => $value)
                    @if($value)
                        <strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}@if(!$loop->last), @endif
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @if(count($data) > 0)
                @foreach($data as $row)
                    <tr>
                        @foreach($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="{{ count($headers) }}" style="text-align: center; padding: 20px;">
                        No data available
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    <div style="margin-top: 20px; font-size: 8px; color: #666; text-align: center;">
        Total Records: {{ count($data) }}
    </div>
</body>
</html>

