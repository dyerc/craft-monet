<?php

namespace dyerc\monet\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\Asset;
use craft\helpers\Json;
use dyerc\monet\Monet;
use dyerc\monet\Models\Placeholder;
use yii\db\Schema;

class MonetField extends Field
{
    /**
     * @var bool
     */
    public $generateMicroPreview;

    /**
     * @var bool
     */
    public $generateColourPalette;

    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('monet', 'Monet');
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getContentColumnType()
    {
        return Schema::TYPE_TEXT;
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue($value, ElementInterface $asset = null)
    {
        if ($value instanceof Placeholder) {
            return $value;
        }

        if (is_string($value) && !empty($value)) {
            $value = Json::decodeIfJson($value);
        }

        if (!is_array($value)) {
            $value = [];
        }

        if ($asset instanceof Asset) {
            $value['assetWidth'] = $asset->width;
            $value['assetHeight'] = $asset->height;
        }

        return new Placeholder($value);
    }

    /**
     * @inheritdoc
     */
    public function afterElementSave(ElementInterface $element, bool $isNew)
    {
        parent::afterElementSave($element, $isNew);

        if ($element instanceof Asset && $this->handle !== null) {
            Monet::getInstance()->service->generate($this, $element);
        }
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml():? string
    {
        $view = Craft::$app->getView();

        return $view->renderTemplate('monet/field/settings', [
            'field' => $this
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        if ($element instanceof Asset && $this->handle !== null) {
            /** @var Asset $element */
            try {
                return Craft::$app->getView()->renderTemplate('monet/field/preview', [
                    'field' => $this,
                    'model' => $value
                ]);
            } catch (\Twig\Error\LoaderError|\yii\base\Exception $e) {
                Craft::error($e->getMessage(), __METHOD__);
            }
        }
    }
}