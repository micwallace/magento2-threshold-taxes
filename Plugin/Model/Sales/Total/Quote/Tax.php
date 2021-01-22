<?php

namespace WallaceIT\ThresholdTaxes\Plugin\Model\Sales\Total\Quote;

use Magento\Framework\Registry;

class Tax {

    /**
     * @var Registry
     */
    private $registry;

    public function __construct(
        Registry $registry
    )
    {
        $this->registry = $registry;
    }


    public function beforeCollect(
        \Magento\Tax\Model\Sales\Total\Quote\Tax $subject,
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment,
        \Magento\Quote\Model\Quote\Address\Total $total
    ){
        $this->registry->register(\WallaceIT\ThresholdTaxes\Model\Calculation::THRESHOLD_TAX_APPLY, true);

        return [$quote, $shippingAssignment, $total];
    }

    public function afterCollect(
        \Magento\Tax\Model\Sales\Total\Quote\Tax $subject,
        $result
    ){
        $this->registry->unregister(\WallaceIT\ThresholdTaxes\Model\Calculation::THRESHOLD_TAX_APPLY);

        return $result;
    }

}
