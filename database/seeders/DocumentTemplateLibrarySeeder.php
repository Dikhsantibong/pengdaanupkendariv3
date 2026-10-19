<?php

namespace Database\Seeders;

/**
 * Every default document template in the library.
 *
 * The wording lives in resources/document-templates; see LibraryTemplateSeeder.
 * The TOR is always the shipped outline (points and sub points only, the
 * narrative typed in by hand), even where it was changed before.
 */
class DocumentTemplateLibrarySeeder extends LibraryTemplateSeeder
{
    /**
     * @var array<int, string>
     */
    protected array $forced = ['umum.tor'];
}
