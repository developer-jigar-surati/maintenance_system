<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR generation for receipts and gate passes.
 *
 * SVG output specifically: it needs no imagick extension and embeds cleanly in
 * both HTML and the dompdf-rendered documents.
 */
class QrCode
{
    public static function svg(string $data, int $size = 120): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));

        return $writer->writeString($data);
    }

    /** Data URI form, for use in an <img src>. */
    public static function dataUri(string $data, int $size = 120): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($data, $size));
    }
}
