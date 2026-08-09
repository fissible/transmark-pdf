<?php

declare(strict_types=1);

namespace Fissible\Transmark\Pdf\Tests;

use Fissible\Transmark\Contracts\ReaderInterface;
use Fissible\Transmark\Document;
use Fissible\Transmark\Nodes\Block\Heading;
use Fissible\Transmark\Nodes\Block\ListItem;
use Fissible\Transmark\Nodes\Block\ListNode;
use Fissible\Transmark\Nodes\Block\Paragraph;
use Fissible\Transmark\Nodes\Inline\Strong;
use Fissible\Transmark\Nodes\Inline\Text;
use Fissible\Transmark\Pdf\Exception\PdfParseException;
use Fissible\Transmark\Pdf\PdfReader;
use Fissible\Transmark\Pdf\PdfWriter;
use PHPUnit\Framework\TestCase;

final class PdfReaderTest extends TestCase
{
    public function test_read_implements_reader_interface_contract(): void
    {
        self::assertInstanceOf(ReaderInterface::class, new PdfReader());
    }

    public function test_reads_a_single_paragraph_written_by_pdf_writer(): void
    {
        $original = new Document([
            new Paragraph([new Text('Hello world')]),
        ]);
        $pdf = (new PdfWriter())->write($original);

        $document = (new PdfReader())->read($pdf);

        $content = $document->content();
        self::assertCount(1, $content);
        self::assertInstanceOf(Paragraph::class, $content[0]);
        self::assertSame('Hello world', $content[0]->inlines()[0]->content());
    }

    public function test_throws_on_a_document_with_no_extractable_text(): void
    {
        $pdf = (new PdfWriter())->write(new Document([]));

        $this->expectException(PdfParseException::class);

        (new PdfReader())->read($pdf);
    }

    public function test_throws_on_content_that_is_not_a_pdf(): void
    {
        $this->expectException(PdfParseException::class);

        (new PdfReader())->read('this is not a PDF at all');
    }

    public function test_classifies_larger_text_as_headings_by_font_size_ratio(): void
    {
        $original = new Document([
            new Heading(1, [new Text('Top Level Heading')]),
            new Paragraph([new Text('Body text at normal size.')]),
            new Heading(2, [new Text('A Sub Heading')]),
        ]);
        $pdf = (new PdfWriter())->write($original);

        $content = (new PdfReader())->read($pdf)->content();

        self::assertInstanceOf(Heading::class, $content[0]);
        self::assertSame(1, $content[0]->level());
        self::assertSame('Top Level Heading', $content[0]->inlines()[0]->content());

        self::assertInstanceOf(Paragraph::class, $content[1]);

        self::assertInstanceOf(Heading::class, $content[2]);
        self::assertSame(2, $content[2]->level());
    }

    public function test_wrapped_paragraph_lines_merge_into_a_single_paragraph(): void
    {
        $longText = str_repeat('word ', 40);
        $original = new Document([
            new Paragraph([new Text($longText)]),
        ]);
        $pdf = (new PdfWriter())->write($original);

        $content = (new PdfReader())->read($pdf)->content();

        // Regardless of how many visual lines dompdf wrapped this into,
        // it must merge back into exactly one Paragraph.
        self::assertCount(1, $content);
        self::assertInstanceOf(Paragraph::class, $content[0]);
    }

    public function test_consecutive_paragraphs_remain_distinct_blocks(): void
    {
        $original = new Document([
            new Paragraph([new Text('First paragraph.')]),
            new Paragraph([new Text('Second paragraph.')]),
        ]);
        $pdf = (new PdfWriter())->write($original);

        $content = (new PdfReader())->read($pdf)->content();

        self::assertCount(2, $content);
        self::assertSame('First paragraph.', $content[0]->inlines()[0]->content());
        self::assertSame('Second paragraph.', $content[1]->inlines()[0]->content());
    }

    public function test_reads_an_ordered_list(): void
    {
        $original = new Document([
            new ListNode(
                ListNode::TYPE_ORDERED,
                [
                    new ListItem([new Paragraph([new Text('First item')])]),
                    new ListItem([new Paragraph([new Text('Second item')])]),
                ],
            ),
        ]);
        $pdf = (new PdfWriter())->write($original);

        $content = (new PdfReader())->read($pdf)->content();

        self::assertCount(1, $content);
        self::assertInstanceOf(ListNode::class, $content[0]);
        self::assertSame(ListNode::TYPE_ORDERED, $content[0]->type());

        $items = $content[0]->items();
        self::assertCount(2, $items);
        self::assertSame('First item', $items[0]->content()[0]->inlines()[0]->content());
        self::assertSame('Second item', $items[1]->content()[0]->inlines()[0]->content());
    }

    public function test_ordered_list_marker_text_does_not_leak_into_item_text(): void
    {
        $original = new Document([
            new ListNode(ListNode::TYPE_ORDERED, [
                new ListItem([new Paragraph([new Text('Clean item text')])]),
            ]),
        ]);
        $pdf = (new PdfWriter())->write($original);

        $item = (new PdfReader())->read($pdf)->content()[0]->items()[0];

        self::assertSame('Clean item text', $item->content()[0]->inlines()[0]->content());
        self::assertStringNotContainsString('1.', $item->content()[0]->inlines()[0]->content());
    }

    public function test_unordered_list_items_merge_into_a_single_run_on_paragraph(): void
    {
        // Documented v1 limitation (see Global Constraints), verified against
        // real captured data, not assumed: dompdf renders bullet markers as
        // vector shapes, not text, so item lines are indistinguishable from
        // wrapped continuation lines of a single paragraph - same gap, same
        // x. groupIntoBlocks() has no signal to split them, so they merge
        // into one Paragraph rather than staying separate. This asserts the
        // real, validated outcome, not the more benign "stays separate but
        // loses list styling" outcome an untested assumption would suggest.
        $original = new Document([
            new ListNode(ListNode::TYPE_UNORDERED, [
                new ListItem([new Paragraph([new Text('Bullet one')])]),
                new ListItem([new Paragraph([new Text('Bullet two')])]),
            ]),
        ]);
        $pdf = (new PdfWriter())->write($original);

        $content = (new PdfReader())->read($pdf)->content();

        foreach ($content as $block) {
            self::assertNotInstanceOf(ListNode::class, $block);
        }
        self::assertCount(1, $content);
        self::assertSame('Bullet one Bullet two', $content[0]->inlines()[0]->content());
    }

    public function test_bold_and_italic_formatting_is_not_recovered(): void
    {
        // Documented v1 non-goal: smalot's Font class has no bold/italic API.
        $original = new Document([
            new Paragraph([
                new Strong([new Text('bold text')]),
            ]),
        ]);
        $pdf = (new PdfWriter())->write($original);

        $inlines = (new PdfReader())->read($pdf)->content()[0]->inlines();

        self::assertInstanceOf(Text::class, $inlines[0]);
        self::assertSame('bold text', $inlines[0]->content());
    }

    public function test_never_emits_a_numbering_ref(): void
    {
        $original = new Document([
            new Paragraph([new Text('Plain paragraph, no numbering.')]),
        ]);
        $pdf = (new PdfWriter())->write($original);

        $paragraph = (new PdfReader())->read($pdf)->content()[0];

        self::assertNull($paragraph->numbering());
    }

    public function test_paragraphs_do_not_merge_across_a_page_boundary(): void
    {
        $blocks = [];
        for ($i = 1; $i <= 60; $i++) {
            $blocks[] = new Paragraph([new Text(
                "Paragraph number {$i} with some extra words to take up space than any other.",
            )]);
        }
        $pdf = (new PdfWriter())->write(new Document($blocks));

        $content = (new PdfReader())->read($pdf)->content();

        self::assertCount(60, $content, 'A paragraph was lost or merged across a page boundary.');
        foreach ($content as $index => $block) {
            $expected = 'Paragraph number '.($index + 1).' with some extra words to take up space than any other.';
            self::assertSame($expected, $block->inlines()[0]->content());
        }
    }

    public function test_a_wrapped_ordered_list_item_recovers_as_a_single_unfragmented_item(): void
    {
        $longItemText = 'First item which is quite long so that it wraps onto more than one '
            .'rendered line in the output PDF because it keeps going and going.';
        $original = new Document([
            new ListNode(ListNode::TYPE_ORDERED, [
                new ListItem([new Paragraph([new Text($longItemText)])]),
                new ListItem([new Paragraph([new Text('Second item which is short.')])]),
            ]),
        ]);
        $pdf = (new PdfWriter())->write($original);

        $content = (new PdfReader())->read($pdf)->content();

        self::assertCount(1, $content, 'A list item wrapped onto a second line and fragmented into a stray paragraph.');
        self::assertInstanceOf(ListNode::class, $content[0]);

        $items = $content[0]->items();
        self::assertCount(2, $items);
        self::assertSame($longItemText, $items[0]->content()[0]->inlines()[0]->content());
        self::assertSame('Second item which is short.', $items[1]->content()[0]->inlines()[0]->content());
    }

    public function test_an_ordered_list_item_with_inline_formatting_recovers_full_text(): void
    {
        $original = new Document([
            new ListNode(ListNode::TYPE_ORDERED, [
                new ListItem([new Paragraph([
                    new Text('Pay the '),
                    new Strong([new Text('full')]),
                    new Text(' amount.'),
                ])]),
                new ListItem([new Paragraph([new Text('Second plain item.')])]),
            ]),
        ]);
        $pdf = (new PdfWriter())->write($original);

        $content = (new PdfReader())->read($pdf)->content();

        self::assertCount(1, $content, 'A list item with inline formatting was truncated and split into a stray paragraph.');
        self::assertInstanceOf(ListNode::class, $content[0]);

        $items = $content[0]->items();
        self::assertCount(2, $items);
        self::assertSame('Pay the full amount.', $items[0]->content()[0]->inlines()[0]->content());
        self::assertSame('Second plain item.', $items[1]->content()[0]->inlines()[0]->content());
    }

    public function test_inline_formatting_mid_paragraph_does_not_introduce_double_spaces(): void
    {
        $original = new Document([
            new Paragraph([
                new Text('This is '),
                new Strong([new Text('bold')]),
                new Text(' text after.'),
            ]),
        ]);
        $pdf = (new PdfWriter())->write($original);

        $content = (new PdfReader())->read($pdf)->content();

        self::assertSame('This is bold text after.', $content[0]->inlines()[0]->content());
    }

    public function test_headings_are_still_classified_correctly_when_they_outnumber_body_text(): void
    {
        $original = new Document([
            new Heading(1, [new Text('Heading Alpha')]),
            new Heading(1, [new Text('Heading Beta')]),
            new Heading(1, [new Text('Heading Gamma')]),
            new Paragraph([new Text('Lonely body paragraph.')]),
        ]);
        $pdf = (new PdfWriter())->write($original);

        $content = (new PdfReader())->read($pdf)->content();

        self::assertInstanceOf(Heading::class, $content[0]);
        self::assertInstanceOf(Heading::class, $content[1]);
        self::assertInstanceOf(Heading::class, $content[2]);
        self::assertInstanceOf(Paragraph::class, $content[3]);
        self::assertSame('Lonely body paragraph.', $content[3]->inlines()[0]->content());
    }

    public function test_throws_a_pdf_parse_exception_not_a_third_party_exception_when_pages_cannot_be_read(): void
    {
        // Valid enough for Parser::parseContent() to succeed (real xref,
        // real trailer), but /Root points at an object that is not a
        // Catalog/Pages object at all, so Smalot\PdfParser\Document::getPages()
        // throws its own Smalot\PdfParser\Exception\MissingCatalogException.
        // That must never leak past this reader's own public API.
        $pdf = $this->missingCatalogPdf();

        $this->expectException(PdfParseException::class);

        (new PdfReader())->read($pdf);
    }

    private function missingCatalogPdf(): string
    {
        $header = "%PDF-1.4\n";
        $object = "1 0 obj\n<< /Type /Font /Subtype /Type1 >>\nendobj\n";
        $objectOffset = strlen($header);

        $body = $header.$object;
        $xrefOffset = strlen($body);

        $xref = "xref\n0 2\n0000000000 65535 f \n".sprintf('%010d', $objectOffset)." 00000 n \n";
        $trailer = "trailer\n<< /Size 2 /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF";

        return $body.$xref.$trailer;
    }
}
