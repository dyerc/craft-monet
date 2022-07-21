<?php

namespace dyerc\monet\models;

use craft\base\Model;
use craft\elements\Asset;
use craft\helpers\Template;
use Twig\Markup;

class Placeholder extends Model
{
    /**
     * @var int
     */
    public int $assetWidth = 0;

    /**
     * @var int
     */
    public int $assetHeight = 0;

    /**
     * @var string
     */
    public string $microPreview;

    /**
     * @var array
     */
    public array $colourPalette;

    /**
     * Alias for blurredDataUrl
     *
     * @param int $blurFactor
     * @return Markup|null
     */
    public function dataUrl(int $blurFactor = 20): ?Markup
    {
        return $this->blurredDataUrl($blurFactor);
    }

    /**
     * Return the micro preview as an SVG blurring the micro preview if it exists
     *
     * @param int $blurFactor
     * @return Markup|null
     */
    public function blurredDataUrl(int $blurFactor): ?Markup
    {
        $header = 'data:image/svg+xml;base64,';
        $placeholder = $this->rawDataUrl();

        $width = $this->assetWidth;
        $height = $this->assetHeight;

        $svg = <<<XML
<svg viewBox="0 0 $width $height" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
    <defs>
      <filter id="filter">
        <feGaussianBlur stdDeviation="$blurFactor" in="SourceGraphic"/>
        <feColorMatrix type="matrix" values="1 0 0 0 0, 0 1 0 0 0, 0 0 1 0 0, 0 0 0 9 0" />
        <feComposite in2="SourceGraphic" operator="in" />
      </filter>
    </defs>
    <image filter="url(#filter)" x="0" y="0" width="$width" height="$height" xlink:href="$placeholder" />
</svg>
XML;

        return Template::raw($header . rawurlencode(base64_encode($svg)));
    }

    /**
     * Return the micro preview as a base 64 encoded jpeg if it exists
     *
     * @return Markup|null
     */
    public function rawDataUrl(): ?Markup
    {
        if ($this->microPreview) {
            $header = 'data:image/jpeg;base64,';

            return Template::raw($header . rawurlencode($this->microPreview));
        } else {
            return null;
        }
    }

    /**
     * @return array|null
     */
    public function colorPalette(): ?array
    {
        return $this->colourPalette;
    }

    /**
     * @param $fallback
     * @return string|null
     */
    public function primaryColor($fallback = null): ?string
    {
        if (!empty($this->colourPalette)) {
            return $this->colourPalette[0];
        } else {
            return $fallback;
        }
    }

    /**
     * @param $fallback
     * @return string|null
     */
    public function secondaryColor($fallback = null): ?string
    {
        if (!empty($this->colourPalette) && count($this->colourPalette) >= 2) {
            return $this->colourPalette[1];
        } else {
            return $fallback;
        }
    }
}