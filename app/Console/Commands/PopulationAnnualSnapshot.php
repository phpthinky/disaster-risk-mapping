<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Models\PopulationData;
use App\Models\PopulationDataArchive;
use Illuminate\Console\Command;

/**
 * Takes a deliberate "annual snapshot" of every barangay's current
 * population figures and stores them in population_data_archive with
 * snapshot_type = 'annual'.
 *
 * Meant to run once a year (Dec 31 or Jan 1) via the scheduler, and can
 * also be triggered manually:
 *   php artisan population:annual-snapshot
 *   php artisan population:annual-snapshot --barangay=5
 */
class PopulationAnnualSnapshot extends Command
{
    protected $signature = 'population:annual-snapshot
                            {--barangay= : Only snapshot one barangay (by ID)}';

    protected $description = 'Save an annual population snapshot for all (or one) barangay.';

    public function handle(): int
    {
        $query = Barangay::query();

        if ($this->option('barangay')) {
            $query->where('id', (int) $this->option('barangay'));
        }

        $barangays = $query->orderBy('name')->get();

        if ($barangays->isEmpty()) {
            $this->warn('No barangays found.');
            return self::FAILURE;
        }

        $year = now()->year;
        $count = 0;

        foreach ($barangays as $barangay) {
            $record = PopulationData::where('barangay_id', $barangay->id)->latest()->first();

            if (! $record) {
                $this->line("  <comment>Skip</comment>  {$barangay->name} — no population_data record yet.");
                continue;
            }

            PopulationDataArchive::create([
                'original_id'       => $record->id,
                'barangay_id'       => $barangay->id,
                'total_population'  => $record->total_population ?? 0,
                'households'        => $record->households ?? 0,
                'elderly_count'     => $record->elderly_count ?? 0,
                'children_count'    => $record->children_count ?? 0,
                'pwd_count'         => $record->pwd_count ?? 0,
                'ips_count'         => $record->ips_count ?? 0,
                'solo_parent_count' => $record->solo_parent_count ?? 0,
                'widow_count'       => $record->widow_count ?? 0,
                'data_date'         => now()->toDateString(),
                'archived_by'       => 'cron_annual',
                'change_type'       => 'ANNUAL',
                'snapshot_type'     => 'annual',
            ]);

            $this->line("  <info>Saved</info>  {$barangay->name} — {$year} annual snapshot.");
            $count++;
        }

        $this->info("Annual snapshot complete: {$count} barangay(s) saved.");
        return self::SUCCESS;
    }
}
