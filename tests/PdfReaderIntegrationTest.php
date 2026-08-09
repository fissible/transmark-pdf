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
        $content = $recovered->content();

        // Structure survives, with the exact recovered text pinned so a
        // paragraph-boundary regression (e.g. two entries silently merging)
        // is caught, not just "some Paragraphs exist somewhere."
        self::assertCount(7, $content);
        $expectedText = [
            '1. Definitions',
            '2. Term of Agreement',
            '2.1. Initial Term',
            '2.2. Renewal',
            '2.2.1. Automatic renewal',
            '2.2.1.1. Written notice',
            '3. Termination',
        ];
        foreach ($content as $index => $block) {
            self::assertInstanceOf(Paragraph::class, $block);
            self::assertSame($expectedText[$index], $block->inlines()[0]->content());
        }

        // Numbering does NOT survive - confirmed loss, not a TODO. Every
        // recovered paragraph's numbering() must be null; the original
        // fixture's numeric labels ("1.", "2.1.", etc.) appear only as
        // plain leading text within the paragraph content, if at all.
        foreach ($content as $block) {
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
