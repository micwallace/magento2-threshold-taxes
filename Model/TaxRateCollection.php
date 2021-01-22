<?php

namespace WallaceIT\ThresholdTaxes\Model;

use Magento\Tax\Api\Data\TaxRateInterface as TaxRate;

class TaxRateCollection extends \Magento\Tax\Model\TaxRateCollection {

    protected function createTaxRateCollectionItem(TaxRate $taxRate)
    {
        $collectionItem =  parent::createTaxRateCollectionItem($taxRate);

        $collectionItem->setFromTotal($taxRate->getFromTotal());
        $collectionItem->setToTotal($taxRate->getToTotal());

        return $collectionItem;
    }
}
