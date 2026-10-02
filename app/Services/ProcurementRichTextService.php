<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\File;

final class ProcurementRichTextService
{
    private HTMLPurifier $purifier;

    public function __construct()
    {
        $config = HTMLPurifier_Config::createDefault();
        $serializerPath = storage_path('framework/cache/htmlpurifier');
        File::ensureDirectoryExists($serializerPath);
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set(
            'HTML.Allowed',
            'p,br,strong,b,em,i,u,h2,h3,h4,ul,ol,li,blockquote,a[href|title]'
        );
        $config->set('URI.AllowedSchemes', [
            'http' => true,
            'https' => true,
            'mailto' => true,
        ]);
        $config->set('Attr.EnableID', false);
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('Cache.SerializerPath', $serializerPath);

        $this->purifier = new HTMLPurifier($config);
    }

    public function sanitizeForStorage(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $clean = trim($this->purifier->purify($value));

        $meaningfulText = preg_replace(
            '/[\s\x{00a0}]+/u',
            '',
            html_entity_decode(strip_tags($clean), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );
        if ($meaningfulText === '') {
            return null;
        }

        return $clean === '' ? null : $clean;
    }

    /**
     * Safely render both new rich descriptions and historical plain text.
     */
    public function render(?string $value): HtmlString
    {
        $clean = $this->sanitizeForStorage($value) ?? '';
        if ($clean === '') {
            return new HtmlString('');
        }

        if ($clean === strip_tags($clean)) {
            $plain = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return new HtmlString(nl2br(e($plain)));
        }

        return new HtmlString($clean);
    }
}
