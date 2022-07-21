<?php

namespace dyerc\monet\services;

use ColorThief\ColorThief;
use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\helpers\ImageTransforms;
use craft\helpers\ElementHelper;
use craft\base\Field;
use craft\helpers\Image;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\image\Raster;
use dyerc\monet\fields\MonetField;
use dyerc\monet\models\Placeholder;

class MonetService extends Component {
    const PLACEHOLDER_WIDTH = 16;
    const PLACEHOLDER_QUALITY = 40;

    const PREPROCESS_WIDTH = 300;
    const PREPROCESS_QUALITY = 80;

    public function generate(Field $field, ElementInterface $asset): void
    {
        /** @var Asset $asset */
        if ($asset instanceof Asset && $field instanceof MonetField) {
            // Only operate on image assets
            if ($this->canProcessAsset($asset)) {
                if ($asset->focalPoint) {
                    $position = $asset->getFocalPoint();
                } else {
                    $position = 'center-center';
                }

                $aspectRatio = $asset->width / $asset->height;

                $model = new Placeholder();
                $tempPath = $this->preprocessAsset($asset, $aspectRatio, $position);

                if ($field->generateMicroPreview) {
                    $model->microPreview = $this->generateMicroPreview($tempPath, $aspectRatio, $position);
                }

                if ($field->generateColourPalette) {
                    $model->colourPalette = $this->generateColourPalette($tempPath);
                }

                $asset->setFieldValue($field->handle, $field->serializeValue($model));
                $table = $asset->getContentTable();
                $column = ElementHelper::fieldColumnFromField($field);

                $data = Json::encode($field->serializeValue($asset->getFieldValue($field->handle), $asset));

                Craft::$app->db->createCommand()
                    ->update($table, [
                        $column => $data
                    ], [
                        'elementId' => $asset->getId(),
                    ], [], false
                    )->execute();
            }
        }
    }

    public function generateMicroPreview(string $tempPath, float $aspectRatio, $position): string
    {
        Craft::beginProfile('generateMicroPreview', __METHOD__);
        Craft::info('Generating micro placeholder image for asset', __METHOD__);

        $result = '';
        $width = self::PLACEHOLDER_WIDTH;
        $height = (int)($width / $aspectRatio);

        $placeholderPath = $this->resizeImageFromPath($tempPath, $width, $height, self::PLACEHOLDER_QUALITY, $position);

        if (!empty($placeholderPath)) {
            $result = base64_encode(file_get_contents($placeholderPath));
            unlink($placeholderPath);
        }

        Craft::endProfile('generateMicroPreview', __METHOD__);

        return $result;
    }

    /**
     * @param string $tempPath
     * @return array
     *
     * Return an array of hex colours present in the image
     */
    public function generateColourPalette(string $tempPath): array
    {
        Craft::beginProfile('generateColourPalette', __METHOD__);
        Craft::info('Generate colour palette from image', __METHOD__);

        $colours = [];

        if (!empty($tempPath)) {
            try {
                $palette = ColorThief::getPalette($tempPath, 5);
            } catch (\Exception $e) {
                Craft::error($e->getMessage(), __METHOD__);
                return [];
            }

            foreach ($palette as $colour) {
                $colours[] = sprintf('#%02x%02x%02x', $colour[0], $colour[1], $colour[2]);
            }
        }

        Craft::endProfile('generateColourPalette', __METHOD__);

        return $colours;
    }

    private function canProcessAsset(Asset $asset): bool
    {
        $formats = Craft::$app->getImages()->getSupportedImageFormats();

        // TODO: Enable SVG support
        // $formats[] = 'svg';

        $isFormatSupported = in_array(strtolower($asset->extension), $formats);

        return $isFormatSupported && $asset->height > 0;
    }

    /**
     * @param Asset $asset
     * @param float $aspectRatio
     * @param $position
     * @return string
     *
     * Transform to a convenient size to perform further processing, return path to preprocessed version
     */
    private function preprocessAsset(Asset $asset, float $aspectRatio, $position): string
    {
        Craft::beginProfile('preprocessAsset', __METHOD__);
        Craft::info('Preprocess asset before analysis and placeholder generation', __METHOD__);

        $width = self::PREPROCESS_WIDTH;
        $height = (int)($width / $aspectRatio);

        if (Image::canManipulateAsImage($asset->getExtension())) {
            $imageSource = ImageTransforms::getLocalImageSource($asset);
            $tempPath = $this->resizeImageFromPath($imageSource, $width, $height, self::PREPROCESS_QUALITY, $position);
        } else {
            $tempPath = '';
        }

        Craft::endProfile('preprocessAsset', __METHOD__);

        return $tempPath;
    }

    /**
     * @param string $filePath
     * @param int $width
     * @param int $height
     * @param int $quality
     * @param $position
     * @return string
     *
     * Transform an image given params and return path to temporary image
     */
    private function resizeImageFromPath(string $filePath, int $width, int $height, int $quality, $position): string
    {
        $images = Craft::$app->getImages();
        $pathInfo = pathinfo($filePath);

        try {
            // TODO: Enable SVG support
            // StringHelper::toLowerCase($pathInfo['extension']) === 'svg'

            /** @var Raster $image */
            $image = $images->loadImage($filePath);
        } catch (\Throwable $e) {
            Craft::error('Error loading image: ' . $e->getMessage(), __METHOD__);
            return '';
        }

        if ($image instanceof Raster) {
            $image->setQuality($quality);
        }

        // Resize the image
        $image->scaleAndCrop($width, $height, true, $position);

        // Strip any EXIF data from the image before trying to save it
        $imagineImage = $image->getImagineImage();
        if ($imagineImage) {
            $imagineImage->strip();
        }

        // Save the image out to a temp file, then return its contents
        $tempFilename = uniqid(pathinfo($pathInfo['filename'], PATHINFO_FILENAME), true) . '.jpg';
        $outputFilePath = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . $tempFilename;
        clearstatcache(true, $outputFilePath);

        try {
            $image->saveAs($outputFilePath);
        } catch (\Throwable $e) {
            Craft::error('Error resizing to temporary image: ' . $e->getMessage(), __METHOD__);
        }

        return $outputFilePath;
    }
}