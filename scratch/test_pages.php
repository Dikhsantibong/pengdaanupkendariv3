<?php
require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$doc = App\Models\ProcurementDocument::find(98);
$body = $doc->rendered_body;
$body = preg_replace('/(<div[^>]*>\s*<div[^>]*>\s*H\.\s*LAIN-LAIN[\s\S]*?<\/div>\s*<\/div>)\s*<table[^>]*>[\s\S]*?<\/table>/is', '$1', $body);
$body = preg_replace('/<section style="([^"]*?)font-size:\s*[\d.]+pt;?([^"]*?)">/i', '<section style="$1font-size: 10pt;$2">', $body);
$doc->rendered_body = $body;

$dompdf = new Dompdf\Dompdf();
$dompdf->loadHtml(app(App\Services\DocumentGenerator::class)->printableHtml($doc, 'Lampiran'));
$dompdf->render();

$cpdf = $dompdf->getCanvas()->get_cpdf();
foreach ($cpdf->objects as $id => $obj) {
    if (isset($obj['t']) && $obj['t'] === 'contents') {
        $c = gzuncompress($obj['c']);
        echo "=== CONTENT OBJECT {$id} ===\n";
        // Find visible text
        preg_match_all('/\((.*?)\)\s*Tj/s', $c, $matches);
        echo implode(' ', array_slice($matches[1], 0, 15)) . "...\n";
    }
}
