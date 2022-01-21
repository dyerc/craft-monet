<?php
/**
 * Monet plugin for Craft CMS 3.x
 *
 * Generate blurred previews of assets as a field
 *
 * @link      https://cdyer.co.uk
 * @copyright Copyright (c) 2022 Chris Dyer
 */

namespace dyerc\monet;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use dyerc\monet\fields\MonetField;
use dyerc\monet\services\MonetService;
use yii\base\Event;

/**
 * Class Monet
 *
 * @author    Chris Dyer
 * @package   Monet
 * @since     1.0.0
 *
 * @property MonetService $service
 *
 */
class Monet extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var Monet
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';

    /**
     * @var bool
     */
    public $hasCpSettings = false;

    /**
     * @var bool
     */
    public $hasCpSection = false;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $this->setComponents([
            'service' => MonetService::class
        ]);

        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = MonetField::class;
            }
        );

        Craft::info(
            Craft::t(
                'monet',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }
}
