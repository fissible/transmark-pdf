<?php

declare(strict_types=1);

namespace Fissible\Transmark\Pdf\Tests;

use Fissible\Transmark\Contracts\WriterInterface;
use Fissible\Transmark\Document;
use Fissible\Transmark\Nodes\Block\Paragraph;
use Fissible\Transmark\Nodes\Inline\Text;
use Fissible\Transmark\Pdf\PdfWriter;
use PHPUnit\Framework\TestCase;

final class PdfWriterTest extends TestCase
{
    public function test_write_implements_writer_interface_contract(): void
    {
        self::assertInstanceOf(WriterInterface::class, new PdfWriter());
    }

    public function test_write_returns_bytes_with_a_valid_pdf_header_and_trailer(): void
    {
        $document = new Document([
            new Paragraph([new Text('Hello World')]),
        ]);

        $pdf = (new PdfWriter())->write($document);

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringContainsString('%%EOF', $pdf);
        self::assertGreaterThan(1000, strlen($pdf));
    }

    public function test_write_accepts_an_empty_document_without_throwing(): void
    {
        $pdf = (new PdfWriter())->write(new Document([]));

        self::assertStringStartsWith('%PDF-', $pdf);
    }
}
