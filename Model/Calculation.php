<?php

namespace WallaceIT\ThresholdTaxes\Model;

use Magento\Customer\Api\AccountManagementInterface as CustomerAccountManagement;
use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Magento\Customer\Api\GroupManagementInterface as CustomerGroupManagement;
use Magento\Customer\Api\GroupRepositoryInterface as CustomerGroupRepository;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Tax\Api\TaxClassRepositoryInterface;
use Magento\Tax\Model\Config;

class Calculation extends \Magento\Tax\Model\Calculation {

    public const THRESHOLD_TAX_APPLY = "THRESHOLD_TAX_APPLY";

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        Config $taxConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Tax\Model\ResourceModel\TaxClass\CollectionFactory $classesFactory,
        \Magento\Tax\Model\ResourceModel\Calculation $resource,
        CustomerAccountManagement $customerAccountManagement,
        CustomerGroupManagement $customerGroupManagement,
        CustomerGroupRepository $customerGroupRepository,
        CustomerRepository $customerRepository,
        PriceCurrencyInterface $priceCurrency,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        TaxClassRepositoryInterface $taxClassRepository,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    )
    {
        $this->checkoutSession = $checkoutSession;

        parent::__construct($context, $registry, $scopeConfig, $taxConfig, $storeManager, $customerSession, $customerFactory, $classesFactory, $resource, $customerAccountManagement, $customerGroupManagement, $customerGroupRepository, $customerRepository, $priceCurrency, $searchCriteriaBuilder, $filterBuilder, $taxClassRepository, $resourceCollection, $data);
    }

    protected function _getRequestCacheKey($request)
    {
        $cacheKey = parent::_getRequestCacheKey($request);

        if (!$this->_registry->registry(self::THRESHOLD_TAX_APPLY))
            return $cacheKey;

        $quote = $this->checkoutSession->getQuote();

        if ($quote->hasItems()){
            $cacheKey .= '|'.$quote->getShippingAddress()->getSubtotalWithDiscount();
        }

        return $cacheKey;
    }

}
