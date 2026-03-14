<?php

namespace App\Exports;

use App\Models\Household;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class HouseholdExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private ?int $barangayId = null) {}

    public function query()
    {
        $query = Household::with('barangay')->orderBy('barangay_id')->orderBy('household_head');

        if ($this->barangayId) {
            $query->where('barangay_id', $this->barangayId);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID', 'Barangay', 'HH ID', 'Household Head', 'Sex', 'Age',
            'Gender', 'Zone / Sitio', 'IP / Non-IP', 'House Type',
            'Family Members', 'PWD', 'Seniors (60+)', 'Children',
            'Infants (0-2)', 'Pregnant', 'Preparedness Kit',
            'Educational Attainment', 'Latitude', 'Longitude', 'Created At',
        ];
    }

    public function map($household): array
    {
        return [
            $household->id,
            $household->barangay->name ?? '—',
            $household->hh_id ?? '—',
            $household->household_head,
            $household->sex,
            $household->age,
            $household->gender,
            $household->sitio_purok_zone ?? '—',
            $household->ip_non_ip ?? '—',
            $household->house_type ?? '—',
            $household->family_members,
            $household->pwd_count,
            $household->senior_count,
            ($household->minor_count + $household->child_count),
            $household->infant_count,
            $household->pregnant_count,
            $household->preparedness_kit ? 'Yes' : 'No',
            $household->educational_attainment ?? '—',
            $household->latitude,
            $household->longitude,
            $household->created_at?->format('Y-m-d H:i'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
