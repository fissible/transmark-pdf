<?php

declare(strict_types=1);

namespace Fissible\Transmark\Pdf\Exception;

/**
 * Thrown when PDF content cannot be read reliably: no extractable text
 * (scanned/image-only PDF), corrupted content that the underlying parser
 * rejects, or insufficient layout signal for reconstruction.
 */
final class PdfParseException extends \RuntimeException
{
}
