<?php

declare(strict_types=1);

namespace Fissible\Transmark\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use Fissible\Transmark\Contracts\WriterInterface;
use Fissible\Transmark\Document;
use Fissible\Transmark\Writers\HtmlWriter;

final class PdfWriter implements WriterInterface
{
    public function __construct(
        private readonly HtmlWriter $htmlWriter = new HtmlWriter(),
        private readonly string $paperSize = 'letter',
        private readonly string $paperOrientation = 'portrait',
    ) {
    }

    public function write(Document $document): string
    {
        $html = $this->htmlWriter->write($document);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        // Disallow remote resource fetches (SSRF hardening): PdfWriter's
        // input is a converted Document, not trusted arbitrary HTML.
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($this->paperSize, $this->paperOrientation);
        $dompdf->render();

        return $dompdf->output();
    }
}
