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
        } catch (\Throwable $exception) {
            throw new PdfParseException(
                'Unable to parse PDF content: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        $entries = $this->collectEntries($pdf);

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
     * @return array<int, array{x: float, y: float, fontSize: float, text: string}>
     */
    private function collectEntries(\Smalot\PdfParser\Document $pdf): array
    {
        $entries = [];

        foreach ($pdf->getPages() as $page) {
            $pageEntries = [];

            foreach ($page->getDataTm() as $item) {
                [$tm, $text, , $fontSize] = $item;

                if (trim($text) === '') {
                    continue;
                }

                $pageEntries[] = [
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

    private function baselineFontSize(array $entries): float
    {
        $counts = [];
        foreach ($entries as $entry) {
            $key = number_format($entry['fontSize'], 2);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $maxCount = max($counts);
        $candidates = array_map(
            static fn (string $key): float => (float) $key,
            array_keys($counts, $maxCount, true),
        );

        return min($candidates);
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
     * @param array<int, array{x: float, y: float, fontSize: float, text: string, isOrderedListItem?: bool}> $entries
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
            $isNewBlock = $current === null
                || $tier !== $current['tier']
                || $isListItem
                || $current['isOrderedListItem']
                || ($previous !== null && ($previous['y'] - $entry['y']) > 1.5 * $entry['fontSize']);

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
                $current['text'] .= ' '.$entry['text'];
            }

            $previous = $entry;
        }

        if ($current !== null) {
            $groups[] = $current;
        }

        return $groups;
    }

    /**
     * @param array<int, array{x: float, y: float, fontSize: float, text: string}> $entries
     * @return array<int, array{x: float, y: float, fontSize: float, text: string, isOrderedListItem: bool}>
     */
    private function mergeOrderedListMarkers(array $entries): array
    {
        $merged = [];
        $count = count($entries);

        for ($i = 0; $i < $count; $i++) {
            $entry = $entries[$i];
            $next = $entries[$i + 1] ?? null;

            $isMarker = $next !== null
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
