<?php

declare(strict_types=1);

namespace Fissible\Transmark\Pdf\Tests;

use Fissible\Transmark\Nodes\Block\Paragraph;
use Fissible\Transmark\Pdf\PdfReader;
use Fissible\Transmark\Pdf\PdfWriter;
use Fissible\Transmark\Readers\DocxReader;
use PHPUnit\Framework\TestCase;

final class PdfReaderIntegrationTest extends TestCase
{
    public function test_legal_outline_docx_round_trips_through_pdf_with_structure_but_not_numbering(): void
    {
        $original = (new DocxReader())->read($this->fixtureDocx('legal-outline'));
        $pdf = (new PdfWriter())->write($original);

        $recovered = (new PdfReader())->read($pdf);

        // Structure survives: the fixture's paragraphs come back as paragraphs.
        self::assertNotEmpty($recovered->content());
        foreach ($recovered->content() as $block) {
            self::assertInstanceOf(Paragraph::class, $block);
        }

        // Numbering does NOT survive - confirmed loss, not a TODO. Every
        // recovered paragraph's numbering() must be null; the original
        // fixture's numeric labels ("1.", "2.1.", etc.) appear only as
        // plain leading text within the paragraph content, if at all.
        foreach ($recovered->content() as $block) {
            self::assertNull($block->numbering());
        }
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
        $path = tempnam(sys_get_temp_dir(), 'transmark-pdf-reader-integration-test-');
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
