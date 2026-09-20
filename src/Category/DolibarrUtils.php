<?php

declare(strict_types=1);

namespace App\Category;

/**
 * Faithful ports of the Dolibarr helpers the categories API depends on
 * (functions.lib.php / security.lib.php): sanitizeVal() modes used by
 * _checkValForAPI/_checkValExtrafieldsForAPI plus SQL identifier escaping.
 */
final class DolibarrUtils
{
    /**
     * Port of sanitizeVal() for the modes used by _checkValForAPI.
     */
    public function sanitizeVal(mixed $out, string $check): mixed
    {
        if ($out === null) {
            $out = '';
        }
        switch ($check) {
            case 'none':
            case 'password':
                break;
            case 'int':
                if (!is_numeric($out)) {
                    $out = '';
                }
                break;
            case 'email':
                $out = filter_var((string) $out, FILTER_SANITIZE_EMAIL);
                break;
            case 'aZ09':
                if (!is_array($out)) {
                    $out = trim((string) $out);
                    if (preg_match('/[^a-z0-9_\-\.]+/i', (string) $out)) {
                        $out = '';
                    }
                }
                break;
            case 'alpha':
            case 'alphanohtml':
                if (!is_array($out)) {
                    $out = trim((string) $out);
                    do {
                        $oldstringtoclean = $out;
                        $out = $this->stringNohtmltag($out, 0);
                        $out = preg_replace('/\\\([0-9xu])/', '/\1', (string) $out);
                        $out = str_ireplace(
                            [
                                '../', '..\\', '&#38', '&#0000038', '&#x26', '&quot', '"', '&#34', '&#0000034',
                                '&#x22', '&#47', '&#0000047', '&#x2F', '&#92', '&#0000092', '&#x5C',
                            ],
                            '',
                            (string) $out,
                        );
                    } while ($oldstringtoclean != $out);
                }
                break;
            case 'nohtml':
                $out = $this->stringNohtmltag((string) $out, 0);
                break;
            case 'restricthtmlnolink':
            case 'restricthtml':
            case 'restricthtmlallowclass':
            case 'restricthtmlallowiframe':
            case 'restricthtmlallowlinkscript':
            case 'restricthtmlallowunvalid':
                $out = $this->htmlWithNoJs((string) $out);
                break;
            default:
                $out = $this->sanitizeVal($out, 'alphanohtml');
                break;
        }

        return $out;
    }

    /**
     * Port of dol_string_nohtmltag() — strip html tags, keep line feeds.
     */
    public function stringNohtmltag(string $stringtoclean, int $removelinefeed = 1): string
    {
        $temp = str_replace('&', '&amp;', $stringtoclean);
        $temp = strip_tags($temp);
        // Replace CR and LF when requested
        if ($removelinefeed) {
            $temp = str_replace(["\r\n", "\r", "\n"], ' ', $temp);
        }
        $temp = str_replace('&amp;', '&', $temp);

        return trim($temp);
    }

    /**
     * Approximate port of dol_htmlwithnojs() — strip javascript / dangerous
     * attributes from html (used by the restricthtml* sanitize modes).
     */
    public function htmlWithNoJs(string $stringtoclean): string
    {
        // Remove script/style and event handler attributes and javascript: links
        $out = $stringtoclean;
        do {
            $old = $out;
            $out = preg_replace('/<\/(script|iframe|form)/i', '</defhandled', $out);
            $out = preg_replace('/<(script|iframe|form)[^>]*>/i', '', $out);
            $out = preg_replace('/\son\w+\s*=\s*"[^"]*"/i', '', $out);
            $out = preg_replace("/\\son\\w+\\s*=\\s*'[^']*'/i", '', $out);
            $out = preg_replace('/\son\w+\s*=\s*[^\s>]+/i', '', $out);
            $out = preg_replace('/javascript\s*:/i', 'javascriptsanitized:', $out);
        } while ($old != $out);

        return $out;
    }

    /** Sanitize an SQL identifier (table or column name), port of $db->sanitize(). */
    public function sanitizeIdentifier(string $identifier): string
    {
        return (string) preg_replace('/[^a-zA-Z0-9_\.]/', '', $identifier);
    }
}
