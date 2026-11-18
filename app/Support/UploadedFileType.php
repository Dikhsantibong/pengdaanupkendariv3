<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What an uploaded file really is, read from its content and never from the
 * name or type the browser reported: a file whose content is a PDF but is
 * named "evil.html" is stored as .pdf and served as a PDF, never as a page
 * of this site.
 *
 * Files are served back with a type from a short list (PDF and images open
 * in the browser; anything else, or a type recorded before, only downloads)
 * and with nosniff, so the browser never guesses another type.
 */
class UploadedFileType
{
    /**
     * The types a browser may show in place.
     *
     * @var list<string>
     */
    public const INLINE = ['application/pdf', 'image/jpeg', 'image/png'];

    /**
     * The extension to store the file under, from its content.
     */
    public static function extension(UploadedFile $file): string
    {
        $extension = strtolower((string) $file->extension());

        return $extension === '' ? 'bin' : $extension;
    }

    /**
     * The file's type, from its content.
     */
    public static function mime(UploadedFile $file): string
    {
        return $file->getMimeType() ?? 'application/octet-stream';
    }

    /**
     * Whether a stored type may be shown in the browser.
     */
    public static function isInline(?string $mime): bool
    {
        return in_array(strtolower((string) $mime), self::INLINE, true);
    }

    /**
     * The headers a stored file is served with.
     *
     * @return array<string, string>
     */
    public static function headers(?string $mime): array
    {
        return [
            'Content-Type' => self::isInline($mime) ? strtolower((string) $mime) : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    /**
     * How a stored file opens: in place when its type allows it and that
     * is asked for, otherwise as a download.
     */
    public static function disposition(?string $mime, bool $inline = true): string
    {
        return $inline && self::isInline($mime) ? 'inline' : 'attachment';
    }

    /** What a downloaded upload's name starts with, so it is told apart from a generated document. */
    public const DOWNLOAD_PREFIX = 'UNGGAHAN - ';

    /**
     * The name an uploaded file is saved under when downloaded: its own,
     * marked as the upload it is.
     */
    public static function downloadName(string $fileName): string
    {
        return str_starts_with($fileName, self::DOWNLOAD_PREFIX) ? $fileName : self::DOWNLOAD_PREFIX.$fileName;
    }

    /**
     * Serve a stored upload: shown in the browser's own tab (a PDF renders
     * there) unless a download is asked for (`?download=1`) or its type
     * cannot be shown, in which case it downloads under its marked name.
     */
    public static function respond(Filesystem $disk, string $path, string $fileName, ?string $mime, bool $inline = true): StreamedResponse
    {
        $disposition = self::disposition($mime, $inline);

        return $disk->response(
            $path,
            $disposition === 'attachment' ? self::downloadName($fileName) : $fileName,
            self::headers($mime),
            $disposition,
        );
    }
}
