# fissible/transmark-pdf

PDF export for [fissible/transmark](https://github.com/fissible/transmark): `PdfWriter` composes `HtmlWriter` output with [dompdf/dompdf](https://github.com/dompdf/dompdf) (pure-PHP, LGPL-2.1) to produce PDF bytes — no system binaries, no `ext-gd`.

## Why a separate package?

`fissible/transmark` stays dependency-free at its core. A consumer who only needs DOCX → HTML never pays for a PDF rendering engine. A consumer who wants DOCX → HTML → PDF requires **this one package** — `fissible/transmark` and `dompdf/dompdf` both come along transitively via Composer, so there's one `composer require`, not two separate integrations to wire up by hand.

## Requirements

- PHP ^8.2
- ext-dom, ext-mbstring

## Installation

```bash
composer require fissible/transmark-pdf
```

## Usage

```php
use Fissible\Transmark\Pdf\PdfWriter;
use Fissible\Transmark\Readers\DocxReader;

$docxBytes = file_get_contents('agreement.docx');
$document = (new DocxReader())->read($docxBytes);

$pdfBytes = (new PdfWriter())->write($document);

file_put_contents('agreement.pdf', $pdfBytes);
```

`PdfWriter` implements `Fissible\Transmark\Contracts\WriterInterface`, the same contract `HtmlWriter`, `DocxWriter`, and `MarkdownWriter` implement — it's a drop-in alongside any other `transmark` writer.

### Paper size and orientation

```php
$writer = new PdfWriter(paperSize: 'A4', paperOrientation: 'landscape');
```

Accepts any paper size/orientation string [dompdf's `setPaper()`](https://github.com/dompdf/dompdf/wiki/Usage) supports.

> **Note:** `PdfWriter` does not validate `paperSize`/`paperOrientation`. An invalid value
> (e.g. `paperSize: 'not-a-size'`) does not throw — dompdf silently falls back to
> letter/portrait. Pass values dompdf recognizes.

## License

MIT
