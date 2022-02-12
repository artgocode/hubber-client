<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class HubberOffersDownload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hubber:offers:download';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to download an offers file with offers updates';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $hubberUrl = env('HUBBER_EXPORTER_URL');

        // Get head from hubber
        try {
            $headResponse = Http::head($hubberUrl);
            $this->info('Hubber`s xml updates endpoint head returned success.');
        } catch (\Throwable $th) {
            $this->error('An error occurred while getting Hubber`s xml updates endpoint head:');
            $this->error($th->getMessage());
            $this->error("Request url: {$hubberUrl}");
            return 1;
        }

        // Check last modified time and compare to last export file
        $lastModifiedHeader = $headResponse->header('last-modified');
        // Convert time to Carbon and change UTC tz to 'Europe/Kiev'
        $kievTimeCarbon = Carbon::createFromTimeString($lastModifiedHeader)->setTimezone('Europe/Kiev');
        $lastModifiedTimestamp = $kievTimeCarbon->timestamp;

        // Get latest export file if there are some
        if ($lastExportFile = collect(Storage::files('exports'))->sort()->last(null, '')) {
            // Grab timestamp from file ...
            $timestampFromExportFile = str($lastExportFile)->between('export_', '.xml')->value();
        } else {
            // ... or set timestamp to empty string
            $timestampFromExportFile = '';
        }

        // Check if file has been updated
        if ($timestampFromExportFile === (string) $lastModifiedTimestamp) {
            $this->warn("File has not been updated since {$kievTimeCarbon}");
            $this->warn("TZ: {$kievTimeCarbon->tzName}; TimeStamp: {$lastModifiedTimestamp}");
            $this->warn('Nothing to download. Exiting...');
            return 1;
        } else {
            $this->info('Updates are available');
            $this->info('Getting updates xml file from Hubber...');
        }

        // Get file
        try {
            $response = Http::get($hubberUrl);
            $this->info('Connected to Hubber successfully');
        } catch (\Throwable $th) {
            $this->error('An error occurred while connecting to Hubber');
            $this->error($th->getMessage());
            return 1;
        }

        // Compose a new file name
        $storagePath = 'exports';
        $newFileName = 'export' . '_' . $lastModifiedTimestamp . '.xml';
        $filePath = "{$storagePath}/{$newFileName}";

        // Save file to storage
        try {
            Storage::disk('local')->put($filePath, $response);
            $this->info('File "' . $newFileName . '" has been saved successfully');
        } catch (\Throwable $th) {
            $this->error('An error occurred while saving file content to the storage');
            $this->error($th->getMessage());
            return 1;
        }

        return 0;
    }
}
