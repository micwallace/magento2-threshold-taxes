<?php

namespace WallaceIT\ThresholdTaxes\Model\ResourceModel;

class Calculation extends \Magento\Tax\Model\ResourceModel\Calculation {

    /**
     * @var \Magento\Framework\Registry
     */
    private $_registry;

    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        \Magento\Tax\Helper\Data $taxData,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Registry $registry,
        $connectionName = null
    )
    {
        $this->_registry = $registry;

        parent::__construct($context, $taxData, $storeManager, $connectionName);
    }

    /**
     * Returns tax rates for request - either pereforms SELECT from DB, or returns already cached result
     * Notice that productClassId due to optimization can be array of ids
     *
     * @param \Magento\Framework\DataObject $request
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Zend_Db_Select_Exception
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function _getRates($request)
    {
        // Extract params that influence our SELECT statement and use them to create cache key
        $storeId = $this->_storeManager->getStore($request->getStore())->getId();
        $customerClassId = $request->getCustomerClassId();
        $countryId = $request->getCountryId();
        $regionId = $request->getRegionId();
        $postcode = $request->getPostcode();

        // Process productClassId as it can be array or usual value. Form best key for cache.
        $productClassId = $request->getProductClassId();
        $ids = is_array($productClassId) ? $productClassId : [$productClassId];
        foreach ($ids as $key => $val) {
            $ids[$key] = (int)$val; // Make it integer for equal cache keys even in case of null/false/0 values
        }
        $ids = array_unique($ids);
        sort($ids);
        $productClassKey = implode(',', $ids);

        // Form cache key and either get data from cache or from DB
        $cacheKey = implode(
            '|',
            [$storeId, $customerClassId, $productClassKey, $countryId, $regionId, $postcode]
        );

        if (!isset($this->_ratesCache[$cacheKey])) {
            // Make SELECT and get data
            $select = $this->getConnection()->select();
            $select->from(
                ['main_table' => $this->getMainTable()],
                [
                    'tax_calculation_rate_id',
                    'tax_calculation_rule_id',
                    'customer_tax_class_id',
                    'product_tax_class_id'
                ]
            )->where(
                'customer_tax_class_id = ?',
                (int)$customerClassId
            );
            if ($productClassId) {
                $select->where('product_tax_class_id IN (?)', $productClassId);
            }
            $ifnullTitleValue = $this->getConnection()->getCheckSql(
                'title_table.value IS NULL',
                'rate.code',
                'title_table.value'
            );
            $ruleTableAliasName = $this->getConnection()->quoteIdentifier('rule.tax_calculation_rule_id');
            $select->join(
                ['rule' => $this->getTable('tax_calculation_rule')],
                $ruleTableAliasName . ' = main_table.tax_calculation_rule_id',
                ['rule.priority', 'rule.position', 'rule.calculate_subtotal']
            )->join(
                ['rate' => $this->getTable('tax_calculation_rate')],
                'rate.tax_calculation_rate_id = main_table.tax_calculation_rate_id',
                [
                    'value' => 'rate.rate',
                    'rate.tax_country_id',
                    'rate.tax_region_id',
                    'rate.tax_postcode',
                    'rate.tax_calculation_rate_id',
                    'rate.code',
                    'rate.from_total', // WallaceIT: Add from and to values onto data
                    'rate.to_total'
                ]
            )->joinLeft(
                ['title_table' => $this->getTable('tax_calculation_rate_title')],
                "rate.tax_calculation_rate_id = title_table.tax_calculation_rate_id " .
                "AND title_table.store_id = '{$storeId}'",
                ['title' => $ifnullTitleValue]
            )->where(
                'rate.tax_country_id = ?',
                $countryId
            )->where(
                "rate.tax_region_id IN(?)",
                [0, (int)$regionId]
            );
            $postcodeIsNumeric = is_numeric($postcode);
            $postcodeIsRange = false;
            $originalPostcode = null;
            if (is_string($postcode) && preg_match('/^(.+)-(.+)$/', $postcode, $matches)) {
                if ($countryId == self::USA_COUNTRY_CODE && is_numeric($matches[2]) && strlen($matches[2]) == 4) {
                    $postcodeIsNumeric = true;
                    $originalPostcode = $postcode;
                    $postcode = $matches[1];
                } else {
                    $postcodeIsRange = true;
                    $zipFrom = $matches[1];
                    $zipTo = $matches[2];
                }
            }

            if ($postcodeIsNumeric || $postcodeIsRange) {
                $selectClone = clone $select;
                $selectClone->where('rate.zip_is_range IS NOT NULL');
            }
            $select->where('rate.zip_is_range IS NULL');

            if ($postcode != '*' || $postcodeIsRange) {
                $select->where(
                    "rate.tax_postcode IS NULL OR rate.tax_postcode IN('*', '', ?)",
                    $postcodeIsRange ? $postcode : $this->_createSearchPostCodeTemplates($postcode, $originalPostcode)
                );
                if ($postcodeIsNumeric) {
                    $selectClone->where('? BETWEEN rate.zip_from AND rate.zip_to', $postcode);
                } elseif ($postcodeIsRange) {
                    $selectClone->where('rate.zip_from >= ?', $zipFrom)
                        ->where('rate.zip_to <= ?', $zipTo);
                }
            }

            /**
             * @see ZF-7592 issue http://framework.zend.com/issues/browse/ZF-7592
             */
            if ($postcodeIsNumeric || $postcodeIsRange) {
                $select = $this->getConnection()->select()->union(
                    ['(' . $select . ')', '(' . $selectClone . ')']
                );
            }

            $select->order(
                'priority ' . \Magento\Framework\DB\Select::SQL_ASC
            )->order(
                'tax_calculation_rule_id ' . \Magento\Framework\DB\Select::SQL_ASC
            )->order(
                'tax_country_id ' . \Magento\Framework\DB\Select::SQL_DESC
            )->order(
                'tax_region_id ' . \Magento\Framework\DB\Select::SQL_DESC
            )->order(
                'tax_postcode ' . \Magento\Framework\DB\Select::SQL_DESC
            )->order(
                'value ' . \Magento\Framework\DB\Select::SQL_DESC
            );

            $fetchResult = $this->getConnection()->fetchAll($select);
            $filteredRates = [];
            if ($fetchResult) {
                foreach ($fetchResult as $rate) {
                    if (!isset($filteredRates[$rate['tax_calculation_rate_id']])) {
                        $filteredRates[$rate['tax_calculation_rate_id']] = $rate;
                    }
                }
            }
            $this->_ratesCache[$cacheKey] = array_values($filteredRates);
        }

        // WallaceIT: Filter out rates not in threshold
        //$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        //$logger = $objectManager->get('Psr\Log\LoggerInterface');

        $subtotal = $this->_registry->registry(\WallaceIT\ThresholdTaxes\Model\Calculation::THRESHOLD_TAX_APPLY);

        if ($subtotal) {
            $rates = $this->_ratesCache[$cacheKey];

            foreach ($rates as $key => $rate) {

                if (!$rate['from_total'] && !$rate['to_total'])
                    continue;

                if ($subtotal < $rate['from_total'] || $subtotal > $rate['to_total']) {
                    unset($rates[$key]);
                }
            }

            return $rates;
        }

        return $this->_ratesCache[$cacheKey];
    }

}
