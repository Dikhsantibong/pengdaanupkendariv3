<?php

namespace App\Services;

use App\Enums\FinanceSection;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Str;

/**
 * Reads the delivery and payment requirements out of a procurement's own
 * contract.
 *
 * Every contract states them as a lettered list right after a fixed phrase:
 * the SPK "… dilengkapi dokumen sebagai berikut:", the Surat Perjanjian
 * "… harus dilengkapi dengan:" for delivery, and "… dengan melampirkan:" /
 * "… dengan dilampiri:" for payment, the Lampiran Surat Pesanan included.
 * What is read is the contract as drawn for this procurement, edits and all,
 * so the checklist matches the paper that is signed.
 */
class ContractRequirementReader
{
    /**
     * The phrases a list of requirements follows, per section.
     *
     * @var array<string, array<int, string>>
     */
    public const MARKERS = [
        'penyerahan' => ['dilengkapi dokumen sebagai berikut', 'harus dilengkapi dengan'],
        'pembayaran' => ['melampirkan', 'dilampiri'],
    ];

    /**
     * The document holding the contract articles, per contract format.
     *
     * @var array<string, string>
     */
    public const CONTRACT_DOCUMENTS = [
        'SPK' => 'spk',
        'PJ' => 'spk',
        'SPPL' => 'lampiran-sp',
    ];

    /**
     * The longest a requirement name may be (checklist_items.name), long
     * enough for the wordiest item a contract has.
     */
    private const NAME_LENGTH = 500;

    /**
     * The longest its reference may be (checklist_items.description).
     */
    private const DESCRIPTION_LENGTH = 255;

    /**
     * The contract document of a procurement, if one has been drawn.
     */
    public function contractDocument(Procurement $procurement): ?ProcurementDocument
    {
        $code = self::CONTRACT_DOCUMENTS[$procurement->contractNumberFormat->code ?? ''] ?? null;

        if ($code === null) {
            return null;
        }

        $typeId = DocumentType::query()->where('code', $code)->value('id');

        if ($typeId === null) {
            return null;
        }

        $procurement->loadMissing('documents');

        $document = $procurement->documentFor((int) $typeId);

        return $document !== null && trim((string) $document->rendered_body) !== '' ? $document : null;
    }

    /**
     * The requirements the contract lists, per section.
     *
     * A section whose list cannot be found is left out, so the caller can
     * fall back to the template's requirements for it.
     *
     * @return array<string, list<array{name: string, is_optional: bool, description: string}>>
     */
    public function read(Procurement $procurement): array
    {
        $document = $this->contractDocument($procurement);

        if ($document === null) {
            return [];
        }

        $sections = [];

        foreach (FinanceSection::cases() as $section) {
            $items = $this->requirementsIn(
                (string) $document->rendered_body,
                self::MARKERS[$section->value],
                // Kept even once retired (withTrashed), so always there.
                $document->documentType->nameFor($procurement),
            );

            if ($items !== null) {
                $sections[$section->value] = $items;
            }
        }

        return $sections;
    }

    /**
     * The first lettered list that directly follows one of the phrases.
     *
     * "Directly" matters: the same phrase also appears in running text (the
     * Lampiran SP asks for papers "dengan melampirkan copy Surat Pesanan"
     * mid-sentence), and only a list opening inside the same paragraph or
     * cell as the phrase is the list it introduces.
     *
     * @param  array<int, string>  $markers
     * @return list<array{name: string, is_optional: bool, description: string}>|null
     */
    public function requirementsIn(string $html, array $markers, string $contractName): ?array
    {
        foreach ($markers as $marker) {
            $offset = 0;

            while (($position = mb_stripos($html, $marker, $offset)) !== false) {
                $offset = $position + mb_strlen($marker);
                $tail = mb_substr($html, $offset);
                $listStart = mb_stripos($tail, '<ol');

                if ($listStart === false) {
                    break;
                }

                $between = mb_substr($tail, 0, $listStart);

                // The list belongs to another paragraph or cell.
                if (preg_match('#</(li|td|p|div)>#i', $between) === 1 || mb_strlen(strip_tags($between)) > 40) {
                    continue;
                }

                $items = $this->listItems(mb_substr($tail, $listStart));

                if ($items === []) {
                    continue;
                }

                $reference = $this->reference(mb_substr($html, 0, $position), $contractName);

                return array_map(
                    fn (string $item, int $index): array => [
                        'name' => Str::limit($item, self::NAME_LENGTH - 3),
                        'is_optional' => $this->isConditional($item),
                        'description' => Str::limit(
                            trim($reference.' huruf '.$this->letter($index)),
                            self::DESCRIPTION_LENGTH - 3,
                        ),
                    ],
                    $items,
                    array_keys($items),
                );
            }
        }

        return null;
    }

    /**
     * The text of each top-level item of the list a fragment opens with.
     *
     * @return list<string>
     */
    protected function listItems(string $fragment): array
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div>'.$fragment.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $list = $dom->getElementsByTagName('ol')->item(0);

        if (! $list instanceof DOMElement) {
            return [];
        }

        $items = [];

        foreach ($list->childNodes as $child) {
            if (! $child instanceof DOMElement || strtolower($child->nodeName) !== 'li') {
                continue;
            }

            $copy = $child->cloneNode(true);

            // A sub-list belongs to its item's explanation, not its name.
            if ($copy instanceof DOMElement) {
                foreach (iterator_to_array($copy->getElementsByTagName('ol')) as $nested) {
                    $nested->parentNode?->removeChild($nested);
                }
            }

            $text = trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', (string) $copy->textContent)));
            $text = rtrim($text, ' ;.,:');

            if ($text !== '') {
                $items[] = $text;
            }
        }

        return $items;
    }

    /**
     * Where in the contract the list stands: its article and paragraph, or
     * the lettered part of a Surat Pesanan appendix.
     */
    protected function reference(string $before, string $contractName): string
    {
        // Read from the layout, not the wording: an article's text often
        // cites other articles ("… Pasal 6 ayat (2) …"). The heading of an
        // article and the number cell of a paragraph are what number them.
        if (preg_match_all('#class="pasal"[^>]*>\s*<div[^>]*>\s*Pasal\s+(\d+)#iu', $before, $articles, PREG_OFFSET_CAPTURE) > 0) {
            // The nearest article heading before the list.
            [$number, $at] = $articles[1][count($articles[1]) - 1];
            $reference = 'Pasal '.$number;
            $sinceArticle = substr($before, $at);

            if (preg_match_all('#<td[^>]*class="no"[^>]*>\s*\((\d+)\)\s*</td>#iu', $sinceArticle, $paragraphs) > 0) {
                $reference .= ' ayat ('.end($paragraphs[1]).')';
            }

            return $contractName.' '.$reference;
        }

        $text = html_entity_decode(strip_tags((string) preg_replace('#<(/?)(div|td|tr|li|p)\b[^>]*>#i', ' ', $before)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // A Surat Pesanan appendix is lettered by part: "D. PEMBAYARAN".
        if (preg_match_all('/\b([A-Z])\.\s+[A-Z]{4,}/u', $text, $parts) > 0) {
            return $contractName.' Bagian '.end($parts[1]);
        }

        return $contractName;
    }

    /**
     * Whether the contract asks for the paper only in some cases.
     */
    protected function isConditional(string $item): bool
    {
        // Only a closing remark makes the paper conditional: "(jika ada)" in
        // the middle of an item qualifies a detail, not the paper itself.
        return preg_match('/\((apabila ada|jika ada|bila ada|untuk [^)]*)\)\s*$/iu', $item) === 1;
    }

    /**
     * The letter of a list position: a, b, … z, aa, ab, …
     */
    protected function letter(int $index): string
    {
        $letter = '';

        do {
            $letter = chr(ord('a') + ($index % 26)).$letter;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return $letter;
    }
}
