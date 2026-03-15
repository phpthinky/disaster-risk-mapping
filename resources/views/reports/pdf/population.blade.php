@extends('layouts.pdf')

@section('title', 'Population Summary Report')

@section('content')
<table>
    <thead>
        <tr>
            <th>Barangay</th>
            <th class="text-right">Total Pop.</th>
            <th class="text-right">Households</th>
            <th class="text-right">PWD</th>
            <th class="text-right">Senior (60+)</th>
            <th class="text-right">Infant (0–2)</th>
            <th class="text-right">Pregnant</th>
            <th class="text-right">IP</th>
            <th class="text-right">At-Risk</th>
        </tr>
    </thead>
    <tbody>
        @foreach($barangays as $b)
        <tr>
            <td>{{ $b->name }}</td>
            <td class="text-right">{{ number_format($b->population) }}</td>
            <td class="text-right">{{ number_format($b->household_count) }}</td>
            <td class="text-right">{{ number_format($b->pwd_count) }}</td>
            <td class="text-right">{{ number_format($b->senior_count) }}</td>
            <td class="text-right">{{ number_format($b->infant_count) }}</td>
            <td class="text-right">{{ number_format($b->pregnant_count) }}</td>
            <td class="text-right">{{ number_format($b->ip_count) }}</td>
            <td class="text-right">{{ number_format($b->at_risk_count) }}</td>
        </tr>
        @endforeach
    </tbody>
    @if($barangays->isNotEmpty())
    <tfoot>
        <tr>
            <td><strong>TOTAL</strong></td>
            <td class="text-right">{{ number_format($barangays->sum('population')) }}</td>
            <td class="text-right">{{ number_format($barangays->sum('household_count')) }}</td>
            <td class="text-right">{{ number_format($barangays->sum('pwd_count')) }}</td>
            <td class="text-right">{{ number_format($barangays->sum('senior_count')) }}</td>
            <td class="text-right">{{ number_format($barangays->sum('infant_count')) }}</td>
            <td class="text-right">{{ number_format($barangays->sum('pregnant_count')) }}</td>
            <td class="text-right">{{ number_format($barangays->sum('ip_count')) }}</td>
            <td class="text-right">{{ number_format($barangays->sum('at_risk_count')) }}</td>
        </tr>
    </tfoot>
    @endif
</table>
@endsection
