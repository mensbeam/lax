<?php
/** @license MIT
 * Copyright 2018 J. King et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace MensBeam\Lax;

/** {@inheritDoc} */
class MimeType extends \MensBeam\Mime\MimeType {
    protected const LOOSE_TYPE_PATTERN = '<^[\t\r\n ]*([^;\t\r\n ]+)(?:$|[\t\r\n ]*(?:;.*)?)$>is';
    protected const ATOM_TYPE_PATTERN = '<^\s*(|text|x?html)\s*$>i';

    protected static $mime;

    /** Parses a MIME type, accepting types without a subtype */
    public static function parseLoose(string $type, ?Url $url = null): ?self {
        if ($normalized = self::parse($type)) {
            return $normalized;
        } elseif (preg_match(self::LOOSE_TYPE_PATTERN, $type, $match) && preg_match(self::TOKEN_PATTERN, $match[1])) {
            $type = strtolower($match[1]);
            $type = ['document' => "text", 'executable' => "application"][$type] ?? $type;
            return new self($type);
        } elseif ($url && (strlen($url->getScheme()) && !$url->isUrn())) {
            $file = substr($url->getPath(), (int) strrpos($url->getPath(), "/"));
            $ext = strrpos($file, ".");
            if ($ext !== false) {
                $ext = substr($file, $ext + 1);
                if (strlen($ext)) {
                    $type = (self::$mime ?? (self::$mime = new \Mimey\MimeTypes))->getMimeType($ext);
                    if (!is_null($type)) {
                        return self::parse($type);
                    }
                }
            }
        } elseif ($url && $url->getScheme() === "data") {
            $data = $url->getPath();
            $candidate = substr($data, 0, (int) strpos($data, ","));
            return self::parseLoose($candidate) ?? self::parse("text/plain");
        }
        return null;
    }

    /** Parses an Atom content type, which may be either a MIME type or the strings "text", "html", or "xhtml"
     *
     * If the supplied type is invalid "unknown/unknown" is returned
     */
    public static function parseAtom(string $type): self {
        if (preg_match(self::ATOM_TYPE_PATTERN, $type, $match)) {
            $type = ['' => "text/plain", 'text' => "text/plain", 'html' => "text/html", 'xhtml' => "application/xhtml+xml"][strtolower($match[1])] ?? null;
            assert(!is_null($type));
        }
        return self::parse($type) ?? self::parse("unknown/unknown");
    }

    protected function essence(): string {
        return $this->type.(strlen($this->subtype ?? "") ? "/".$this->subtype : "");
    }
}
