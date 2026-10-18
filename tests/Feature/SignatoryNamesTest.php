<?php

namespace Tests\Feature;

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
            'umum/hpe.html' => ['direksi_pekerjaan', 'pic_perencana'],
            'pj/hpe.html' => ['direksi_pekerjaan', 'nama_manager'],
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
}
