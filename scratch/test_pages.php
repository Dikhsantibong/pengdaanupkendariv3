<?php
require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$doc = App\Models\ProcurementDocument::find(98);
$renderer = app(App\Services\DocumentPdfRenderer::class);

foreach ([10, 9.5, 9.0, 8.5, 8.0, 7.8, 7.5] as $fs) {
    foreach (['10mm 15mm', '12mm 15mm', '15mm 15mm'] as $margin) {
        $seeder = new Database\Seeders\SpplDocumentTemplateSeeder();
        $ref = new ReflectionClass($seeder);
        $m = $ref->getMethod('syaratUmum');
        $m->setAccessible(true);
        $body = $m->invoke($seeder, 'BARANG');
        
        $body = "<style>@page { size: A4 portrait; margin: {$margin}; }</style>\n" . $body;
        $body = preg_replace('/font-size:\s*[\d.]+pt;/', "font-size: {$fs}pt;", $body);
        $body = preg_replace('/<table[^>]*sidebar-logo[\s\S]*?<\/table>/is', '<div style="text-align: center; margin-bottom: 6px;"><img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height: 36px; width: auto; display: inline-block;"></div>', $body);
        $body = preg_replace('/(<div[^>]*>\s*<div[^>]*>\s*H\.\s*LAIN-LAIN[\s\S]*?<\/div>\s*<\/div>)\s*<table[^>]*>[\s\S]*?<\/table>/is', '$1', $body);

        $doc->rendered_body = $body;
        $pdf = $renderer->render($doc);
        preg_match_all('/\/Type\s*\/Page\b/', $pdf, $matches);
        if (count($matches[0]) === 2) {
            echo "SUCCESS: FS: {$fs}pt, Margin: {$margin} => 2 PAGES!\n";
        }
    }
}
