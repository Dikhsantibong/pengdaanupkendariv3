<?php

namespace Tests\Feature\Procurements;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\DocumentGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LiveDocumentFieldsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Generate a document from a one-off template.
     *
     * @return array{0: Procurement, 1: ProcurementDocument}
     */
    protected function generate(string $body, array $procurement = []): array
    {
        $type = DocumentType::factory()->create();
        DocumentTemplate::factory()->create(['document_type_id' => $type->id, 'body' => $body]);

        $procurement = Procurement::factory()->create($procurement);
        $document = app(DocumentGenerator::class)
            ->generate($procurement, $type, User::factory()->administrator()->create());

        return [$procurement, $document];
    }

    public function test_values_in_running_text_become_live_fields_but_markup_stays_plain(): void
    {
        [, $document] = $this->generate(
            '<style>.x{content:"{{nama_mitra}}"}</style><p title="{{nama_mitra}}">Kepada {{nama_mitra}} — {{tanggal_dokumen}}</p>',
            ['partner_name' => 'PT Maju'],
        );

        $body = $document->rendered_body;

        $this->assertStringContainsString('content:"PT Maju"', $body);
        $this->assertStringContainsString('title="PT Maju"', $body);
        $this->assertStringContainsString('<span data-field="nama_mitra" data-value="PT Maju">PT Maju</span>', $body);
        // The issue date stays as issued.
        $this->assertStringNotContainsString('data-field="tanggal_dokumen"', $body);
    }

    public function test_an_unedited_document_follows_any_change_to_the_procurement(): void
    {
        [$procurement, $document] = $this->generate(
            '<p>Bank {{nama_bank}} rekening {{nomor_rekening}}</p>',
            ['bank_name' => 'BANK MANDIRI'],
        );

        // As a checklist input would: a plain model save, no controller sync.
        $procurement->forceFill(['bank_name' => 'BANK BRI', 'bank_account_number' => '1300013209310'])->save();

        $body = $document->refresh()->rendered_body;
        $this->assertStringContainsString('BANK BRI', $body);
        $this->assertStringContainsString('1300013209310', $body);
        $this->assertStringNotContainsString('BANK MANDIRI', $body);
    }

    public function test_an_edited_document_refreshes_untouched_fields_and_keeps_hand_edits(): void
    {
        [$procurement, $document] = $this->generate(
            '<p>Mitra {{nama_mitra}}, direktur {{nama_direktur}}</p>',
            ['partner_name' => 'PT Lama', 'partner_director_name' => 'Budi'],
        );

        $generator = app(DocumentGenerator::class);
        $edited = str_replace('>Budi<', '>Budi Santoso, S.T.<', $document->rendered_body).'<p>Catatan manual.</p>';
        $generator->saveEdit($document, User::factory()->administrator()->create(), $document->title, $edited);

        $procurement->update(['partner_name' => 'PT Baru', 'partner_director_name' => 'Andi']);

        $body = $document->refresh()->rendered_body;
        $this->assertStringContainsString('PT Baru', $body);
        $this->assertStringContainsString('Budi Santoso, S.T.', $body);
        $this->assertStringNotContainsString('Andi', $body);
        $this->assertStringContainsString('Catatan manual.', $body);
        $this->assertSame(1, $document->revision);
    }

    public function test_a_signed_document_no_longer_follows_the_data(): void
    {
        [$procurement, $document] = $this->generate('<p>Mitra {{nama_mitra}}</p>', ['partner_name' => 'PT Lama']);

        $document->signedUploads()->create([
            'path' => 'signed/ttd.pdf',
            'file_name' => 'ttd.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
            'uploaded_by' => User::factory()->create()->id,
        ]);

        $procurement->update(['partner_name' => 'PT Baru']);
        app(DocumentGenerator::class)->refreshDocument($document->refresh());

        $this->assertStringContainsString('PT Lama', $document->refresh()->rendered_body);
        $this->assertStringNotContainsString('PT Baru', $document->rendered_body);
    }

    public function test_the_editor_always_opens_with_the_latest_data(): void
    {
        [$procurement, $document] = $this->generate('<p>{{nama_mitra}}</p>', ['partner_name' => 'PT Lama']);

        // Changed without any model event, e.g. straight in the database.
        Procurement::query()->whereKey($procurement->id)->toBase()->update(['partner_name' => 'PT Baru']);

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('procurements.documents.edit', [$procurement, $document]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('document.body', fn (string $body): bool => str_contains($body, 'PT Baru')));
    }
}
