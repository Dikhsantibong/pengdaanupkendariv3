<?php

namespace Database\Seeders;

/**
 * The templates used only by PJ contracts: the RKS for services and the
 * Surat Perjanjian.
 *
 * The wording lives in resources/document-templates; see LibraryTemplateSeeder.
 */
class PjTemplateSeeder extends LibraryTemplateSeeder
{
    protected array $groups = ['pj'];
}
