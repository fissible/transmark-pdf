<?php

declare(strict_types=1);

namespace Fissible\Transmark\Pdf\Tests;

use Fissible\Transmark\Pdf\PdfWriter;
use Fissible\Transmark\Readers\DocxReader;
use Fissible\Transmark\Writers\HtmlWriter;
use PHPUnit\Framework\TestCase;
use Smalot\PdfParser\Parser;

final class PdfWriterIntegrationTest extends TestCase
{
    public function test_legal_outline_docx_fixture_converts_to_pdf_through_the_full_pipeline(): void
    {
        $document = (new DocxReader())->read($this->fixtureDocx('legal-outline'));
        $html = (new HtmlWriter())->write($document);

        $pdf = (new PdfWriter())->write($document);

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringContainsString('%%EOF', $pdf);
        $text = (new Parser())->parseContent($pdf)->getText();
        self::assertStringContainsString('Definitions', $text);
        self::assertStringContainsString('Termination', $text);
        // Sanity check that the HTML this PDF was built from actually
        // carries the legal-outline flat-paragraph rendering strategy.
        self::assertSame(7, substr_count($html, '<p class="numbered-paragraph legal-level-'));
    }

    private function fixtureDocx(string $name): string
    {
        $fixturePath = __DIR__.'/fixtures/'.$name;
        $documentXml = file_get_contents($fixturePath.'/document.xml');
        $numberingXml = file_get_contents($fixturePath.'/numbering.xml');
        self::assertIsString($documentXml);
        self::assertIsString($numberingXml);

        return $this->docx([
            'word/document.xml' => $documentXml,
            'word/numbering.xml' => $numberingXml,
        ]);
    }

    /**
     * @param array<string, string> $parts
     */
    private function docx(array $parts): string
    {
        $path = tempnam(sys_get_temp_dir(), 'transmark-pdf-integration-test-');
        self::assertIsString($path);

        try {
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($path, \ZipArchive::OVERWRITE));

            foreach ($parts as $partPath => $contents) {
                self::assertTrue($zip->addFromString($partPath, $contents));
            }

            self::assertTrue($zip->close());
            $bytes = file_get_contents($path);
            self::assertIsString($bytes);

            return $bytes;
        } finally {
            @unlink($path);
        }
    }
}
