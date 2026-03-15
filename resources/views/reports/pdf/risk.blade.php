@extends('layouts.pdf')

@section('title', 'Barangay Risk Analysis Report')

@section('content')
@forelse($zones as $barangayName => $barangayZones)
    <div class="section-header">{{ $barangayName }}</div>
    <table>
        <thead>
            <tr>
                <th>Hazard Type</th>
                <th>Risk Level</th>
                <th class="text-right">Area (km²)</th>
                <th class="text-right">Affected Pop.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($barangayZones as $zone)
            @php
                $badgeMap = [
                    'danger'    => ['High Susceptible', 'Prone', 'PEIS VIII'],
                    'warning'   => ['Moderate Susceptible', 'PEIS VII'],
                    'info'      => ['Low Susceptible', 'Generally Susceptible', 'General Inundation'],
                    'success'   => ['Not Susceptible'],
                ];
                $badgeClass = 'badge-secondary';
                foreach ($badgeMap as $cls => $keywords) {
                    foreach ($keywords as $kw) {
                        if (str_contains($zone->risk_level, $kw)) { $badgeClass = 'badge-' . $cls; break 2; }
                    }
                }
            @endphp
            <tr>
                <td>{{ $zone->hazardType->name ?? '—' }}</td>
                <td><span class="{{ $badgeClass }}">{{ $zone->risk_level }}</span></td>
                <td class="text-right">{{ number_format($zone->area_km2, 2) }}</td>
                <td class="text-right">{{ number_format($zone->affected_population) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
@empty
    <p style="color:#6b7280; text-align:center; margin-top:20px;">No hazard zones to display.</p>
@endforelse
@endsection
