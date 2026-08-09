# fissible/transmark-pdf

PDF export for [fissible/transmark](https://github.com/fissible/transmark): `PdfWriter` composes `HtmlWriter` output with [dompdf/dompdf](https://github.com/dompdf/dompdf) (pure-PHP, LGPL-2.1) to produce PDF bytes — no system binaries, no `ext-gd`.

## Why a separate package?

`fissible/transmark` stays dependency-free at its core. A consumer who only needs DOCX → HTML never pays for a PDF rendering engine. A consumer who wants DOCX → HTML → PDF requires **this one package** — `fissible/transmark` and `dompdf/dompdf` both come along transitively via Composer, so there's one `composer require`, not two separate integrations to wire up by hand.

## Requirements

- PHP ^8.2
- ext-dom, ext-mbstring, ext-zip

## Installation

```bash
composer require fissible/transmark-pdf
```

## Usage

```php
use Fissible\Transmark\Pdf\PdfWriter;
use Fissible\Transmark\Readers\DocxReader;
use Fissible\Transmark\Pdf\PdfReader;

$docxBytes = file_get_contents('agreement.docx');
$document = (new DocxReader())->read($docxBytes);

$pdfBytes = (new PdfWriter())->write($document);
$recovered = (new PdfReader())->read($pdfBytes);

file_put_contents('agreement.pdf', $pdfBytes);
```

Pass custom dompdf options when needed:

```php
use Dompdf\Options;

$options = new Options();
$options->set('defaultFont', 'Helvetica');

$pdfBytes = (new PdfWriter(options: $options))->write($document);
```

`PdfWriter` implements `Fissible\Transmark\Contracts\WriterInterface`, the same contract `HtmlWriter`, `DocxWriter`, and `MarkdownWriter` implement — it's a drop-in alongside any other `transmark` writer.

### PDF → Document reader

`PdfReader` is a best-effort PDF reader that recovers a canonical `Document` from PDF bytes using layout heuristics. It throws `Fissible\Transmark\Pdf\Exception\PdfParseException` when the content isn't readable at all — no extractable text (e.g. a scanned/image-only PDF), or content the underlying parser rejects as corrupt — rather than silently returning an empty or partial `Document`.

Limitations to treat as accepted by design:
- Headings and paragraph boundaries are inferred from font-size and spacing, not guaranteed
  from semantic document structure.
- Inline emphasis/bold and table reconstruction are not recovered.
- Consecutive unordered-list items merge into a single run-on paragraph rather than staying
  separate list items — dompdf (and most PDF generators) render bullet markers as vector
  shapes, not text, so there is no signal distinguishing a new bullet item from a wrapped
  continuation line of the same paragraph. Ordered (numbered) lists are recovered correctly,
  since numeric markers do render as real text.
- Legal-outline `NumberingRef` structures do not round-trip through PDF text extraction.
- Multi-column layouts are not supported — text is read in per-page top-to-bottom, then
  left-to-right order, which interleaves columns incorrectly.
- Non-Latin/non-Latin-1 text (e.g. CJK) does not survive a `PdfWriter` → `PdfReader` round
  trip today — this is a `dompdf`/`PdfWriter` default-font limitation (no CJK glyphs
  embedded), not a `PdfReader` defect; text that never rendered correctly in the PDF can't be
  read back correctly either.

### Paper size and orientation

```php
$writer = new PdfWriter(paperSize: 'A4', paperOrientation: 'landscape');
```

Accepts any paper size/orientation string [dompdf's `setPaper()`](https://github.com/dompdf/dompdf/wiki/Usage) supports.

> **Note:** `PdfWriter` does not validate `paperSize`/`paperOrientation`. An invalid value
> (e.g. `paperSize: 'not-a-size'`) does not throw — dompdf silently falls back to
> letter/portrait. Pass values dompdf recognizes.

### Runtime note

`PdfWriter` relies on dompdf's font cache under `vendor/dompdf/dompdf/lib/fonts/` on first
render for some font metadata. If that directory is read-only, rendering may trigger runtime
errors or slower fallback behavior.

## License

MIT
