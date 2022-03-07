<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Offers updates file url
    |--------------------------------------------------------------------------
    |
    | Set offers file url
    |
    */

    'offers_url' => env('HUBBER_OFFERS_URL'),

    /*
    |--------------------------------------------------------------------------
    | Export files storage path
    |--------------------------------------------------------------------------
    |
    | This folder will be used to manage your Hubber's export files
    | The final path will be like this
    |
    | '<project_path>/storage/app/<export_xml_files_folder>
    |
    */

    'export_xml_files_folder' => 'exports',
];
