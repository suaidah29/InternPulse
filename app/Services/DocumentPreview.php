<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DocumentPreview
{
    public static function show(string $path, string $name, bool $download = false): Response
    {
        $disk = Storage::disk('local');
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        if ($download) {
            return $disk->download($path, $name, $headers);
        }
        $mime = $disk->mimeType($path);
        if (in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
            return $disk->response($path, $name, $headers + ['Content-Type' => $mime, 'Content-Disposition' => 'inline']);
        }
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'docx') {
            return response()->view('management.file-preview', ['name' => $name, 'paragraphs' => null], 200, $headers);
        }
        $temporary = tempnam(sys_get_temp_dir(), 'internpulse-preview-');
        if ($temporary === false) {
            abort(500);
        }
        $zip = $temporary.'.zip';
        try {
            if (! copy($disk->path($path), $zip)) {
                throw new \RuntimeException('Unable to prepare document.');
            }
            $archive = new \PharData($zip);
            $entry = $archive['word/document.xml'];
            if ($entry->getSize() > 5 * 1024 * 1024) {
                throw new \RuntimeException('Document is too large to preview.');
            }
            $xml = $entry->getContent();
            $dom = new DOMDocument;
            if (! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                throw new \RuntimeException('Document cannot be read.');
            }
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $paragraphs = [];
            foreach ($xpath->query('//w:body//w:p') as $paragraph) {
                $line = '';
                foreach ($xpath->query('.//w:t | .//w:tab | .//w:br', $paragraph) as $node) {
                    $line .= match ($node->localName) { 'tab' => "\t", 'br' => "\n", default => $node->textContent };
                }
                $paragraphs[] = $line;
            }
        } catch (Throwable $error) {
            $paragraphs = null;
        } finally {
            @unlink($zip);
            @unlink($temporary);
        }

        return response()->view('management.file-preview', compact('name', 'paragraphs'), 200, $headers);
    }
}
