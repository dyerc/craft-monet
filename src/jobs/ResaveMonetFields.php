<?php

namespace dyerc\monet\jobs;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\console\Application as ConsoleApplication;
use craft\db\Paginator;
use craft\elements\Asset;
use craft\elements\db\ElementQuery;
use craft\queue\BaseJob;
use dyerc\monet\fields\MonetField;
use dyerc\monet\Monet;

class ResaveMonetFields extends BaseJob
{
    /**
     * @const The number of assets to return in a single paginated query
     */
    const ASSET_QUERY_PAGE_SIZE = 100;

    public $criteria;

    public $fieldId;

    public $force = false;

    /**
     * Process and re-save all Monet fields based on assets matching $criteria
     *
     * @param $queue
     * @return void
     */
    public function execute($queue)
    {
        Craft::$app->getElements()->invalidateCachesForElementType(Asset::class);

        /** @var ElementQuery $query */
        $query = Asset::find();
        if (!empty($this->criteria)) {
            Craft::configure($query, $this->criteria);
        }

        // Process results in paginated batches to limit memory usage
        $paginator = new Paginator($query, [
            'pageSize' => self::ASSET_QUERY_PAGE_SIZE,
        ]);

        $currentElement = 0;
        $totalElements = $paginator->getTotalResults();

        while ($currentElement < $totalElements) {
            $elements = $paginator->getPageResults();

            /** @var ElementInterface $element */
            foreach ($elements as $element) {
                $currentElement++;

                $layout = $element->getFieldLayout();
                if ($layout !== null) {
                    $fields = $layout->getFields();

                    /** @var  $field Field */
                    foreach ($fields as $field) {
                        if ($field instanceof MonetField) {
                            try {
                                Monet::$plugin->service->generate($field, $element);
                            } catch (\Exception $e) {
                                Craft::error($e->getMessage(), __METHOD__);
                                if (Craft::$app instanceof ConsoleApplication) {
                                    echo '[Monet Error]: '
                                        . $e->getMessage()
                                        . ' while processing '
                                        . $currentElement . '/' . $totalElements
                                        . ' - processing asset: ' . $element->title
                                        . ' from field: ' . $field->handle . PHP_EOL;
                                }
                            }
                        }
                    }
                }

                $this->setProgress($queue, $currentElement / $totalElements);
            }

            $paginator->currentPage++;
        }
    }
}