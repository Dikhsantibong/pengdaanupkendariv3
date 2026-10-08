<?php

use App\Models\ChecklistItem;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$items = ChecklistItem::with('documentTypes')->get();
foreach ($items as $i) {
    echo $i->stage->value.' | '.$i->name.' => ';
    foreach ($i->documentTypes as $d) {
        echo $d->name.' (upload_only: '.($d->upload_only ? '1' : '0').') ';
    }
    echo PHP_EOL;
}
