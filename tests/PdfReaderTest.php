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
}
