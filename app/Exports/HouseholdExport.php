<?php

namespace App\Exports;

use App\Models\Household;
use Rap2hpoutre\FastExcel\FastExcel;

class HouseholdExport
{
    public function __construct(private ?int $barangayId = null) {}

    public function download(string $filename = 'households.xlsx')
    {
        $query = Household::with('barangay')->orderBy('barangay_id')->orderBy('household_head');

        if ($this->barangayId) {
            $query->where('barangay_id', $this->barangayId);
        }

        $rows = $query->get()->map(fn($h) => [
            'ID'                     => $h->id,
            'Barangay'               => $h->barangay->name ?? '—',
            'HH ID'                  => $h->hh_id ?? '—',
            'Household Head'         => $h->household_head,
            'Sex'                    => $h->sex,
            'Age'                    => $h->age,
            'Gender'                 => $h->gender,
            'Zone / Sitio'           => $h->sitio_purok_zone ?? '—',
            'IP / Non-IP'            => $h->ip_non_ip ?? '—',
            'House Type'             => $h->house_type ?? '—',
            'Family Members'         => $h->family_members,
            'PWD'                    => $h->pwd_count,
            'Seniors (60+)'          => $h->senior_count,
            'Children'               => ($h->minor_count + $h->child_count),
            'Infants (0-2)'          => $h->infant_count,
            'Pregnant'               => $h->pregnant_count,
            'Preparedness Kit'       => $h->preparedness_kit ? 'Yes' : 'No',
            'Educational Attainment' => $h->educational_attainment ?? '—',
            'Latitude'               => $h->latitude,
            'Longitude'              => $h->longitude,
            'Created At'             => $h->created_at?->format('Y-m-d H:i'),
        ]);

        return (new FastExcel($rows))->download($filename);
    }
}
