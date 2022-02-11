<?php

namespace App\Console\Commands;

use SimpleXMLElement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class HubberExportsCompareLatest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hubber:exports:compare:latest';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compare two latest updates files';

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

        // Check for files to compare
        $exportFiles = collect(Storage::files('exports'));
        if($exportFiles->count() < 2) {
            $this->line('There are no files to compare. Exiting ...');
            return 0;
        }

        // Get latest two files
        $updatesFiles = $exportFiles->sortDesc()->take(2)->sort();
        $offers1 = $this->fromFile('app/'.$updatesFiles->first());
        $offers2 = $this->fromFile('app/'.$updatesFiles->last());

        // Check for additions and deletions
        // Get additions
        $additions = $offers2->diffKeys($offers1);
        if ($additions->count()) {
            $this->warn('Additions were made: ' . $additions->count());
        }
        $additionKeys = $additions->keys()->values()->toArray();

        // Get deletions
        $deletions = $offers1->diffKeys($offers2);
        if ($deletions->count()) {
            $this->warn('Deletions were made: ' . $deletions->count());
        }
        $deletionKeys = $deletions->keys()->values()->toArray();
        // dd($additionKeys, $deletionKeys);

        // Compare two versions for changes
        $offerIds = $offers2->except($additionKeys, $deletionKeys)->keys()->sort(); // Get only relevant keys for both collections

        // dd($offerIds->count());

        foreach ($offerIds as $id) {
            // $this->line(str_repeat('*', 15));

            $prevOffer = $offers1[$id];
            $currOffer = $offers2[$id];

            // Check Availability
            $prev = $prevOffer['available'];
            $curr = $currOffer['available'];

            if ($prev && $curr) {
                // $this->line("Offer is still available");
            } else if (!$prev && !$curr) {
                // $this->line("Offer is still unavailable");
            } else if ($prev && !$curr) {
                $this->error("Offer is now unavailable");
            } else {
                $this->info("Offer is now available");
            }

            // Check Price
            $pricePrev = $prevOffer['price'];
            $priceCurr = $currOffer['price'];

            if ($pricePrev === $priceCurr) {
                // $this->line("The price is still the same");
            } else if ($pricePrev > $priceCurr) {
                $this->info("Price has been decreased");
            } else {
                $this->error("Price has been increased");
            }

            // Check Name
            $namePrev = $prevOffer['name'];
            $nameCurr = $currOffer['name'];

            if ($namePrev === $nameCurr) {
                // $this->line("The offer name is still the same");
            } else {
                $this->error("The offer name has been changed!");
            }

            // Check Description
            $key = 'description';
            $descriptionPrev = $prevOffer[$key];
            $descriptionCurr = $currOffer[$key];

            if ($descriptionPrev === $descriptionCurr) {
                // $this->line("The offer {$key} is still the same");
            } else {
                $this->error("The offer {$key} has been changed!");
            }

            // Check Profit Commission Coefficient
            $key = 'profit_commission';
            $profCommPrev = $prevOffer[$key];
            $profCommCurr = $currOffer[$key];

            if ($profCommPrev === $profCommCurr) {
                // $this->line("Profit commission is still the same");
            } else if ($profCommPrev > $profCommCurr) {
                $this->error("Profit commission has been decreased");
            } else {
                $this->info("Profit commission has been increased");
            }
        }

        return 0;
    }

    function fromFile($path): \Illuminate\Support\Collection
    {
        // Parse previous XML file
        $fileName = storage_path($path);
        $this->line('Start parsing export file: ' . $path);
        $xml = simplexml_load_file($fileName);

        // Get total offers
        $xmlOffers = $xml->shop->offers->offer;
        $totalOffers = $xmlOffers->count();
        $this->line("Total offers: {$totalOffers}");

        // Serialize to collection
        $offers = $this->offersFromXML($xmlOffers);
        $offers->sortKeys();

        // Get total available / unavailable offers
        $available = $offers->pluck('available');
        $totalAvailable = $available->filter()->count();
        $this->line('Total available offers: ' . $totalAvailable);
        $this->line('Total unavailable offers: ' . $totalOffers - $totalAvailable);

        return $offers;
    }

    function categoriesFromXML($categories): \Illuminate\Support\Collection
    {
        $categoriesArray = collect();

        foreach ($categories->category as $category) {
            $newCategory = [];
            $categoryId = '';

            $newCategory['name'] = (string) $category;

            foreach ($category->attributes() as $attrName => $attrValue) {
                if ('id' === (string) $attrName) {
                    $categoryId = (string) $attrValue;
                }

                $newCategory[(string)$attrName] = (string)$attrValue;
            }

            $categoriesArray[$categoryId] = collect($newCategory);
        }

        return $categoriesArray;
    }

    function offersFromXML(SimpleXMLElement $offers): \Illuminate\Support\Collection
    {
        $offersArray = collect();

        foreach ($offers as $offer) {
            $newOffer = [];

            $newOffer['id'] = (string) $offer->attributes()->id;
            $newOffer['available'] = filter_var($offer->attributes()->available, FILTER_VALIDATE_BOOLEAN);
            $newOffer['price'] = (float) $offer->price;
            $newOffer['oldprice'] = (float) $offer->oldprice;
            $newOffer['currencyId'] = (string) $offer->currencyId;
            $newOffer['categoryId'] = (int) $offer->categoryId;
            $newOffer['pictures'] = collect((array) $offer->picture)->sort()->values()->toArray();
            $newOffer['name'] = (string) $offer->name;
            $newOffer['profit_commission'] = (float) $offer->profit_commission;
            $newOffer['profit'] = (float) $offer->profit;
            $newOffer['vendor'] = (string) $offer->vendor;
            $newOffer['vendorCode'] = (string) $offer->vendorCode;
            $newOffer['description'] = (string) $offer->description;

            // Parse parameters
            foreach ($offer->param as $param) {
                $newParam = [];

                if ($paramId = $param->attributes()->id) {
                    $newParam['id'] = (int) $paramId;
                }

                if ($valueid = $param->attributes()->valueid) {
                    $newParam['valueid'] = (int) $valueid;
                }

                $newParam['name'] = (string) $param->attributes()->name;
                $newParam['valuetext'] = (string) $param;

                // Add new parameter to parameters array
                $newOffer['params'][] = $newParam;
            }

            // Add parameters array to offers array
            $offersArray[$newOffer['id']] = $newOffer;
        }

        return $offersArray;
    }
}
