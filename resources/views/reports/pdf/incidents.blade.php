@extends('layouts.pdf')

@section('title', 'Incident Summary Report')

@section('content')
<table>
    <thead>
        <tr>
            <th>Title</th>
            <th>Hazard Type</th>
            <th>Status</th>
            <th>Date</th>
            <th class="text-right">Barangays</th>
            <th class="text-right">Households</th>
            <th class="text-right">Population</th>
        </tr>
    </thead>
    <tbody>
        @forelse($incidents as $inc)
        @php
            $badgeClass = match($inc->status) {
                'ongoing'    => 'badge-danger',
                'monitoring' => 'badge-warning',
                'resolved'   => 'badge-success',
                default      => 'badge-secondary',
            };
        @endphp
        <tr>
            <td>{{ $inc->title }}</td>
            <td>{{ $inc->hazardType->name ?? '—' }}</td>
            <td><span class="{{ $badgeClass }}">{{ ucfirst($inc->status) }}</span></td>
            <td>{{ $inc->incident_date?->format('Y-m-d') }}</td>
            <td class="text-right">{{ $inc->affectedAreas->count() }}</td>
            <td class="text-right">{{ number_format($inc->affectedAreas->sum('affected_households')) }}</td>
            <td class="text-right">{{ number_format($inc->affectedAreas->sum('affected_population')) }}</td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center; color:#6b7280; padding:12px;">No incidents to display.</td></tr>
        @endforelse
    </tbody>
    @if($incidents->isNotEmpty())
    <tfoot>
        <tr>
            <td colspan="4"><strong>TOTALS</strong></td>
            <td class="text-right">{{ $incidents->sum(fn($i) => $i->affectedAreas->count()) }}</td>
            <td class="text-right">{{ number_format($incidents->sum(fn($i) => $i->affectedAreas->sum('affected_households'))) }}</td>
            <td class="text-right">{{ number_format($incidents->sum(fn($i) => $i->affectedAreas->sum('affected_population'))) }}</td>
        </tr>
    </tfoot>
    @endif
</table>
@endsection
