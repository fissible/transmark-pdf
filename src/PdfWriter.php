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
        private readonly ?Options $options = null,
    ) {
    }

    public function write(Document $document): string
    {
        $html = $this->htmlWriter->write($document);

        $options = $this->options ?? new Options();

        // Preserve caller intent for remote loading; leave explicit opt-in intact.
        if (! $options->isRemoteEnabled()) {
            $options->set('isRemoteEnabled', false);
        }
        $options->set('isJavascriptEnabled', false);
        $options->set('isPhpEnabled', false);

        // Disallow remote resource fetches (SSRF hardening): PdfWriter's
        // input is a converted Document, not trusted arbitrary HTML.

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($this->paperSize, $this->paperOrientation);
        $dompdf->render();

        return $dompdf->output();
    }
}
