<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A signatory's name prints in capitals under every signature, while the
 * same name in the body text stays as written.
 */
class SignatoryNamesTest extends TestCase
{
    public function test_names_under_signatures_print_in_capitals(): void
    {
        $upper = fn (string $placeholder): string => '<span style="text-transform: uppercase;">{{'.$placeholder.'}}</span>';

        $signatures = [
            'sppl/ba-negosiasi.html' => ['nama_direktur', 'pic_pelaksana'],
            'sppl/surat-pesanan.html' => ['nama_direktur', 'nama_manager'],
            'kontrak/spk.html' => ['nama_direktur', 'nama_manager'],
            'kontrak/delivery-order.html' => ['nama_direktur', 'nama_manager'],
            'umum/tor.html' => ['nama_manager'],
            'umum/hpe.html' => ['direksi_pekerjaan', 'nama_manager'],
            'umum/upb.html' => ['direksi_pekerjaan', 'pic_perencana'],
        ];

        foreach ($signatures as $file => $placeholders) {
            $template = (string) file_get_contents(resource_path('document-templates/'.$file));

            foreach ($placeholders as $placeholder) {
                $this->assertStringContainsString($upper($placeholder), $template, "{$file}: {$placeholder}");
            }
        }

        // In the body the Direksi Pekerjaan stays as written.
        $tor = (string) file_get_contents(resource_path('document-templates/umum/tor.html'));
        $this->assertStringContainsString('arahan dari <b>{{direksi_pekerjaan}}</b>', $tor);
        $this->assertStringNotContainsString($upper('direksi_pekerjaan'), $tor);
    }

    public function test_every_name_under_a_signature_in_every_template_prints_in_capitals(): void
    {
        $up = '<span style="text-transform: uppercase;">';
        // A name left blank to be typed in, or one that relied only on the
        // template's own .upper class (which the visual editor may drop).
        $unguarded = [
            '/<td class="name fill">(?!'.preg_quote($up, '/').')/',
            '/<td(?: colspan="2")? class="name(?: upper)?"><span class="fill">/',
            '/<span class="name">\( \.+ \)/',
            '/<td class="upper">\{\{nama_(direktur|manager)/',
            '/<b class="upper">\{\{pic_perencana\}\}/',
            '/PIHAK KEDUA<br><span class="upper">\{\{/',
            '/<u>\(\s?Nama Jelas\s?\)<\/u>/',
        ];

        foreach (File::allFiles(resource_path('document-templates')) as $file) {
            if ($file->getExtension() !== 'html') {
                continue;
            }

            foreach ($unguarded as $pattern) {
                $this->assertDoesNotMatchRegularExpression($pattern, $file->getContents(), $file->getRelativePathname());
            }
        }

        // The partner heading its signature, and a blank name typed in later.
        foreach (['sppl/surat-pesanan.html', 'kontrak/delivery-order.html', 'kontrak/spk.html', 'sppl/ba-negosiasi.html'] as $file) {
            $this->assertStringContainsString($up.'{{nama_mitra}}</span>', File::get(resource_path('document-templates/'.$file)), $file);
        }

        $this->assertStringContainsString('<td class="name fill">'.$up.'( Nama Jelas )</span></td>', File::get(resource_path('document-templates/standar/csms.html')));
    }
}
