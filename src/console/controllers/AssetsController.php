<?php

namespace dyerc\monet\console\controllers;

use Craft;
use craft\base\Volume;
use craft\console\Controller;
use craft\errors\SiteNotFoundException;
use craft\helpers\App;
use craft\queue\QueueInterface;
use dyerc\monet\fields\MonetField;
use dyerc\monet\jobs\ResaveMonetFields;
use yii\queue\redis\Queue as RedisQueue;

class AssetsController extends Controller {
    public $force = false;

    public function actionGenerate()
    {
        echo "Creating image placeholders...".PHP_EOL;

        $this->resaveAllVolumeAssets();
        $this->runCraftQueue();
    }

    private function runCraftQueue() {
        App::maxPowerCaptain();
        $queue = Craft::$app->getQueue();

        if ($queue instanceof QueueInterface) {
            $queue->run();
        } elseif ($queue instanceof RedisQueue) {
            $queue->run(false);
        }
    }

    private function resaveAllVolumeAssets($fieldId = null, $force = false)
    {
        $volumes = Craft::$app->getVolumes()->getAllVolumes();
        foreach ($volumes as $volume) {
            if (is_subclass_of($volume, Volume::class)) {
                /** @var Volume $volume */
                $this->resaveVolumeAssets($volume, $fieldId, $force);
            }
        }
    }

    private function resaveVolumeAssets(Volume $volume, $fieldId = null, $force = false)
    {
        $processVolume = false;
        $fieldLayout = $volume->getFieldLayout();

        // Only process field layouts with a Monet field
        if ($fieldLayout) {
            $fields = $fieldLayout->getFields();

            foreach ($fields as $field) {
                if ($field instanceof MonetField) {
                    $processVolume = true;
                }
            }
        }

        if ($processVolume) {
            try {
                $siteId = Craft::$app->getSites()->getPrimarySite()->id;
            } catch (SiteNotFoundException $e) {
                $siteId = 0;
            }

            $queue = Craft::$app->getQueue();

            $queue->push(new ResaveMonetFields([
                'criteria' => [
                    'siteId' => $siteId,
                    'volumeId' => $volume->id,
                    'status' => null,
                    'enabledForSite' => false
                ],
                'fieldId' => $fieldId,
                'force' => $force
            ]));
        }
    }


}