<?php

namespace App\Support;

/**
 * A rendered template as a Word document (.doc), for papers someone outside
 * finishes by hand: Word opens it in print layout on A4, ready to be given a
 * letterhead, completed and signed.
 */
class WordDocument
{
    /**
     * The Word document of a rendered template body.
     *
     * The template's own <style> blocks are moved into the head, where Word
     * reads them, and the live fields of the system are written as plain
     * text.
     */
    public static function fromHtml(string $body, string $title): string
    {
        $styles = '';

        $body = (string) preg_replace_callback('#<style\b[^>]*>(.*?)</style>#is', function (array $matches) use (&$styles): string {
            $styles .= $matches[1]."\n";

            return '';
        }, $body);

        // A live field (<span data-field data-value>) is only the value it shows.
        do {
            $body = (string) preg_replace('#<span\b[^>]*\bdata-field="[^"]*"[^>]*>((?:(?!<span\b).)*?)</span>#is', '$1', $body, -1, $count);
        } while ($count > 0);

        $title = e($title);

        return <<<HTML
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <title>{$title}</title>
        <!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->
        <style>
        @page WordSection1 { size: 21cm 29.7cm; margin: 2cm 2cm 2cm 2.5cm; }
        div.WordSection1 { page: WordSection1; }
        body { font-family: Arial, sans-serif; font-size: 11pt; line-height: 1.35; }
        p { margin: 0 0 6pt; }
        table { border-collapse: collapse; }
        {$styles}
        </style>
        </head>
        <body><div class="WordSection1">{$body}</div></body>
        </html>
        HTML;
    }
}
