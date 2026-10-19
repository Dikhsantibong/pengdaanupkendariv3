<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * The kop of the official TOR form, put on a TOR someone has already typed
 * into: PT PLN NUSANTARA POWER, INTEGRATED MANAGEMENT SYSTEM and TERM OF
 * REFERENCE (TOR) beside Nomor Dokumen, Revisi, Tanggal Terbit and Halaman.
 */
class TorKop
{
    /**
     * The form number, the same on every TOR.
     */
    public const FORM_NUMBER = 'FMKP – 167 – 9.1.1.2.e.a.c - 001';

    /**
     * The body with the form's kop in place of the one it has, its typed text
     * left as it is.
     */
    public static function apply(string $body, CarbonInterface $issueDate): string
    {
        $kop = self::markup($issueDate);

        // The current kop, or the old one: a table with the logo and title
        // around a nested table of the document data.
        $current = '#<table class="header">\s*<tr>\s*<td class="logo".*?</table>#s';
        $old = '#<table class="header">.*?</table>\s*</td>\s*</tr>\s*</table>#s';

        $replaced = preg_match($current, $body) === 1
            ? preg_replace($current, $kop, $body, 1)
            : preg_replace($old, $kop, $body, 1);

        $body = $replaced ?? $body;

        return str_contains($body, '.header .company')
            ? $body
            : (preg_replace('#</style>#', self::STYLE."\n</style>", $body, 1) ?? $body);
    }

    /**
     * The kop's stylesheet, for a TOR drawn before the kop changed.
     */
    protected const STYLE = '.header td { border: 1px solid #000; vertical-align: middle; padding: 4px 6px; }'
        .' .header .logo { width: 22%; text-align: center; padding: 6px; }'
        .' .header .logo img { max-height: 48px; max-width: 140px; display: block; margin: 0 auto; }'
        .' .header .company { width: 37%; text-align: center; font-weight: bold; }'
        .' .header .company.tor { font-size: 11pt; }'
        .' .header .label { width: 16%; border-right: 0; }'
        .' .header .value { width: 25%; border-left: 0; }';

    /**
     * The kop itself.
     */
    protected static function markup(CarbonInterface $issueDate): string
    {
        return '<table class="header">'
            .'<tr><td class="logo" rowspan="4"><img src="/logo/sidebar-logo.png" alt="PT PLN NUSANTARA POWER"></td>'
            .'<td class="company">PT PLN NUSANTARA POWER</td><td class="label">Nomor Dokumen</td><td class="value">: '.self::FORM_NUMBER.'</td></tr>'
            .'<tr><td class="company">INTEGRATED MANAGEMENT SYSTEM</td><td class="label">Revisi</td><td class="value">: 00</td></tr>'
            .'<tr><td class="company tor" rowspan="2">TERM OF REFERENCE (TOR)</td><td class="label">Tanggal Terbit</td><td class="value">: '.$issueDate->format('d - m - Y').'</td></tr>'
            .'<tr><td class="label">Halaman</td><td class="value">: </td></tr>'
            .'</table>';
    }
}
