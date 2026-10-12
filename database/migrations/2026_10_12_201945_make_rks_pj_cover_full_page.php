<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Stretch the RKS cover frame over the whole first page. Only the cover
 * rules change, so any other edits to the template are kept.
 */
return new class extends Migration
{
    /**
     * The cover rules before and after.
     *
     * @var array<string, string>
     */
    private const RULES = [
        '.rks .cover { border: 5px double #1f2f6b; padding: 30px 24px 40px; text-align: center; font-weight: bold; }' => '.rks .cover { border: 5px double #1f2f6b; padding: 34px 24px 0; height: 238mm; text-align: center; font-weight: bold; }',
        '.rks .cover .org { font-size: 14pt; margin-top: 30px; line-height: 1.5; }' => '.rks .cover .org { font-size: 15pt; margin-top: 48px; line-height: 1.5; }',
        '.rks .cover .doc { font-size: 11pt; margin-top: 46px; line-height: 1.5; }' => '.rks .cover .doc { font-size: 11.5pt; margin-top: 70px; line-height: 1.5; }',
        '.rks .cover .meta { margin: 40px auto 0; width: auto; }' => '.rks .cover .meta { margin: 56px auto 0; width: auto; }',
        '.rks .cover .for { margin-top: 60px; font-size: 11pt; }' => '.rks .cover .for { margin-top: 100px; font-size: 11.5pt; }',
        '.rks .cover .foot { margin-top: 90px; font-size: 14pt; line-height: 1.5; }' => '.rks .cover .foot { margin-top: 150px; font-size: 15pt; line-height: 1.5; }',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $templates = DB::table('document_templates')
            ->whereNull('deleted_at')
            ->where('body', 'like', '%.rks .cover {%')
            ->get(['id', 'body']);

        foreach ($templates as $template) {
            $body = str_replace(array_keys(self::RULES), array_values(self::RULES), (string) $template->body);

            if ($body !== $template->body) {
                DB::table('document_templates')->where('id', $template->id)->update([
                    'body' => $body,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
