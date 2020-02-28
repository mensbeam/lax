<?php
/** @license MIT
 * Copyright 2018 J. King et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Lax\Parser;

use JKingWeb\Lax\Date;

trait Construct {
    /** Trims plain text and collapses whitespace */
    protected function trimText(string $text): string {
        return trim(preg_replace("<\s{2,}>s", " ", $text));
    }

    /** Takes an HTML string as input and returns a sanitized version of that string
     * 
     * The $outputHtml parameter, when false, outputs only the plain-text content of the sanitized HTML
     */
    protected function sanitizeString(string $markup, bool $outputHtml = true): string {
        if (!preg_match("/<\S/", $markup)) {
            // if the string does not appear to actually contain markup besides entities, we can skip most of the sanitization
            return $outputHtml ? $markup : $this->trimText(html_entity_decode($markup, \ENT_QUOTES | \ENT_HTML5, "UTF-8"));
        } else {
            return "OOK!";
        }
    }

    /** Resolves a relative URL against a base URL */
    protected function resolveUrl(string $url, string $base = null): string {
        $base = $base ?? "";
        return \Sabre\Uri\resolve($base, $url);
    }

    /** Tests whether a string is a valid e-mail address
     * 
     * Accepts IDN hosts and (with PHP 7.1 and above) Unicode localparts
     */
    protected function validateMail(string $addr): bool {
        $out = preg_match("/^(.+?)@([^@]+)$/", $addr, $match);
        if (!$out) {
            return false;
        }
        $local = $match[1];
        $domain = $match[2];
        // PHP's filter_var does not accept IDN hosts, so we have to perform an IDNA transformat first
        $domain = idn_to_ascii($domain, \IDNA_NONTRANSITIONAL_TO_ASCII, \INTL_IDNA_VARIANT_UTS46); // settings for IDNA2008 algorithm (I think)
        if ($domain===false) {
            return false;
        }
        $addr = "$local@$domain";
         // PHP 7.1 and above have the constant defined FIXME: Review if removing support for PHP 7.0
        $flags = defined("\FILTER_FLAG_EMAIL_UNICODE") ?  \FILTER_FLAG_EMAIL_UNICODE : 0;
        return (bool) filter_var($addr, \FILTER_VALIDATE_EMAIL, $flags);
    }

    protected function parseDate(string $date): ?Date {
        $out = null;
        $date = $this->trimText($date);
        if (!strlen($date)) {
            return $out;
        }
        $tz = new \DateTimeZone("UTC");
        foreach (Date::$supportedFormats as $format) {
            $out = Date::createFromFormat($format, $date, $tz);
            if ($out) {
                break;
            }
        }
        return $out ?: null;
    }
}