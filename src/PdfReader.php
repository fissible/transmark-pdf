<?php

declare(strict_types=1);

namespace Fissible\Transmark\Pdf;

use Fissible\Transmark\Contracts\ReaderInterface;
use Fissible\Transmark\Document;
use Fissible\Transmark\Nodes\Block\Heading;
use Fissible\Transmark\Nodes\Block\ListItem;
use Fissible\Transmark\Nodes\Block\ListNode;
use Fissible\Transmark\Nodes\Block\Paragraph;
use Fissible\Transmark\Nodes\Inline\Text;
use Fissible\Transmark\Pdf\Exception\PdfParseException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;

final class PdfReader implements ReaderInterface
{
    public function read(string $content): Document
    {
        $config = new Config();
        $config->setDataTmFontInfoHasToBeIncluded(true);

        try {
            $pdf = (new Parser([], $config))->parseContent($content);
            $entries = $this->collectEntries($pdf);
        } catch (\Throwable $exception) {
            throw new PdfParseException(
                'Unable to parse PDF content: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        if ($entries === []) {
            throw new PdfParseException(
                'No extractable text was found in the PDF (it may be a scanned/image-only PDF, which is out of scope).',
            );
        }

        $entries = $this->mergeOrderedListMarkers($entries);
        $baseline = $this->baselineFontSize($entries);
        $groups = $this->groupIntoBlocks($entries, $baseline);

        $blocks = [];
        $pendingListItems = [];

        foreach ($groups as $group) {
            if ($group['isOrderedListItem']) {
                $pendingListItems[] = new ListItem([new Paragraph([new Text($group['text'])])]);
                continue;
            }

            if ($pendingListItems !== []) {
                $blocks[] = new ListNode(ListNode::TYPE_ORDERED, $pendingListItems);
                $pendingListItems = [];
            }

            $blocks[] = $group['tier'] > 0
                ? new Heading($group['tier'], [new Text($group['text'])])
                : new Paragraph([new Text($group['text'])]);
        }

        if ($pendingListItems !== []) {
            $blocks[] = new ListNode(ListNode::TYPE_ORDERED, $pendingListItems);
        }

        return new Document($blocks);
    }

    /**
     * @return array<int, array{page: int, x: float, y: float, fontSize: float, text: string}>
     */
    private function collectEntries(\Smalot\PdfParser\Document $pdf): array
    {
        $entries = [];

        foreach ($pdf->getPages() as $pageNumber => $page) {
            $pageEntries = [];

            foreach ($page->getDataTm() as $item) {
                [$tm, $text, , $fontSize] = $item;

                if (trim($text) === '') {
                    continue;
                }

                $pageEntries[] = [
                    'page' => (int) $pageNumber,
                    'x' => (float) $tm[4],
                    'y' => (float) $tm[5],
                    'fontSize' => (float) $fontSize,
                    'text' => $text,
                ];
            }

            usort($pageEntries, static function (array $a, array $b): int {
                return $b['y'] <=> $a['y'] ?: $a['x'] <=> $b['x'];
            });

            array_push($entries, ...$pageEntries);
        }

        return $entries;
    }

    /**
     * The baseline is the font size with the most total character weight
     * (not run count - a handful of short headings can otherwise outnumber
     * one long body paragraph), smallest size wins ties. When that
     * char-weighted candidate is itself much larger than the smallest font
     * size present, it's more likely to be a title/heading-heavy document
     * than genuine body text, so fall back to the smallest size - real body
     * text is essentially never dramatically larger than the smallest text
     * on the page (occasional smaller footnotes/page numbers aside, which
     * char-weighting already discounts as a minority of the total text).
     */
    private function baselineFontSize(array $entries): float
    {
        $charCounts = [];
        $smallest = null;

        foreach ($entries as $entry) {
            $key = number_format($entry['fontSize'], 2, '.', '');
            $charCounts[$key] = ($charCounts[$key] ?? 0) + mb_strlen($entry['text']);

            if ($smallest === null || $entry['fontSize'] < $smallest) {
                $smallest = $entry['fontSize'];
            }
        }

        $maxCount = max($charCounts);
        $candidates = array_map(
            static fn (string $key): float => (float) $key,
            array_keys($charCounts, $maxCount, true),
        );
        $candidate = min($candidates);

        if ($smallest !== null && $smallest > 0.0 && $candidate / $smallest >= 1.4) {
            return $smallest;
        }

        return $candidate;
    }

    private function tierFor(float $fontSize, float $baseline): int
    {
        if ($baseline <= 0.0) {
            return 0;
        }

        $ratio = $fontSize / $baseline;

        return match (true) {
            $ratio >= 1.75 => 1,
            $ratio >= 1.4 => 2,
            $ratio >= 1.15 => 3,
            default => 0,
        };
    }

    /**
     * @param array<int, array{page: int, x: float, y: float, fontSize: float, text: string, isOrderedListItem?: bool}> $entries
     * @return array<int, array{tier: int, isOrderedListItem: bool, text: string}>
     */
    private function groupIntoBlocks(array $entries, float $baseline): array
    {
        $groups = [];
        $current = null;
        $previous = null;

        foreach ($entries as $entry) {
            $tier = $this->tierFor($entry['fontSize'], $baseline);
            $isListItem = $entry['isOrderedListItem'] ?? false;
            // A list item is always its own block regardless of gap (handled
            // by $isListItem above) - but its OWN continuation runs (a
            // wrapped second line, or a second same-line run from inline
            // formatting) must NOT also force a break just because the
            // block-in-progress happens to be a list item; that would
            // fragment and truncate the item's own text.
            $isNewBlock = $current === null
                || $tier !== $current['tier']
                || $isListItem
                || $previous['page'] !== $entry['page']
                || ($previous['y'] - $entry['y']) > 1.5 * $entry['fontSize'];

            if ($isNewBlock) {
                if ($current !== null) {
                    $groups[] = $current;
                }
                $current = [
                    'tier' => $tier,
                    'isOrderedListItem' => $isListItem,
                    'text' => $entry['text'],
                ];
            } else {
                // Runs on the same physical line (e.g. split by inline
                // formatting) already carry any needed spacing in their own
                // text; only a genuine new line (a wrapped continuation)
                // needs a space inserted at the join.
                $sameLine = abs($previous['y'] - $entry['y']) < 0.01;
                $current['text'] .= ($sameLine ? '' : ' ').$entry['text'];
            }

            $previous = $entry;
        }

        if ($current !== null) {
            $groups[] = $current;
        }

        return $groups;
    }

    /**
     * @param array<int, array{page: int, x: float, y: float, fontSize: float, text: string}> $entries
     * @return array<int, array{page: int, x: float, y: float, fontSize: float, text: string, isOrderedListItem: bool}>
     */
    private function mergeOrderedListMarkers(array $entries): array
    {
        $merged = [];
        $count = count($entries);

        for ($i = 0; $i < $count; $i++) {
            $entry = $entries[$i];
            $next = $entries[$i + 1] ?? null;

            $isMarker = $next !== null
                && $entry['page'] === $next['page']
                && abs($entry['y'] - $next['y']) < 0.01
                && $entry['x'] < $next['x']
                && preg_match('/^\d+[.)]$/', trim($entry['text'])) === 1;

            if ($isMarker) {
                $merged[] = [...$next, 'isOrderedListItem' => true];
                $i++;
                continue;
            }

            $merged[] = [...$entry, 'isOrderedListItem' => false];
        }

        return $merged;
    }
}
