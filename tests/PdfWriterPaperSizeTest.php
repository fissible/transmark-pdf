<?php

declare(strict_types=1);

namespace Fissible\Transmark\Pdf\Tests;

use Fissible\Transmark\Document;
use Fissible\Transmark\Nodes\Block\Paragraph;
use Fissible\Transmark\Nodes\Inline\Text;
use Fissible\Transmark\Pdf\PdfWriter;
use PHPUnit\Framework\TestCase;

final class PdfWriterPaperSizeTest extends TestCase
{
    private function document(): Document
    {
        return new Document([
            new Paragraph([new Text('Hello World')]),
        ]);
    }

    private function mediaBox(string $pdf): string
    {
        self::assertMatchesRegularExpression('/\/MediaBox\s*\[[^\]]+\]/', $pdf);
        preg_match('/\/MediaBox\s*(\[[^\]]+\])/', $pdf, $matches);

        return $matches[1];
    }

    public function test_default_paper_size_and_orientation_is_letter_portrait(): void
    {
        $pdf = (new PdfWriter())->write($this->document());

        self::assertSame('[0.000 0.000 612.000 792.000]', $this->mediaBox($pdf));
    }

    public function test_letter_landscape_swaps_media_box_dimensions(): void
    {
        $pdf = (new PdfWriter(paperSize: 'letter', paperOrientation: 'landscape'))->write($this->document());

        self::assertSame('[0.000 0.000 792.000 612.000]', $this->mediaBox($pdf));
    }

    public function test_a4_portrait_produces_a_different_media_box_than_letter(): void
    {
        $pdf = (new PdfWriter(paperSize: 'A4', paperOrientation: 'portrait'))->write($this->document());

        self::assertSame('[0.000 0.000 595.280 841.890]', $this->mediaBox($pdf));
    }

    public function test_a4_landscape_swaps_media_box_dimensions(): void
    {
        $pdf = (new PdfWriter(paperSize: 'A4', paperOrientation: 'landscape'))->write($this->document());

        self::assertSame('[0.000 0.000 841.890 595.280]', $this->mediaBox($pdf));
    }

    public function test_legal_portrait_produces_a_different_media_box_than_letter(): void
    {
        $pdf = (new PdfWriter(paperSize: 'legal', paperOrientation: 'portrait'))->write($this->document());

        self::assertSame('[0.000 0.000 612.000 1008.000]', $this->mediaBox($pdf));
    }

    public function test_invalid_paper_size_and_orientation_silently_falls_back_to_letter_portrait(): void
    {
        $pdf = (new PdfWriter(paperSize: 'not-a-size', paperOrientation: 'sideways'))->write($this->document());

        self::assertSame('[0.000 0.000 612.000 792.000]', $this->mediaBox($pdf));
    }
}
