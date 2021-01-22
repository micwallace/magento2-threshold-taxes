<?php

namespace WallaceIT\ThresholdTaxes\Plugin\Model\Calculation\Rate;

use Magento\Tax\Api\Data\TaxRateInterface;

class Converter {

    /**
     * @param \Magento\Tax\Model\Calculation\Rate\Converter $subject
     * @param TaxRateInterface $taxRate
     * @param $formData
     * @return TaxRateInterface
     */
    public function afterPopulateTaxRateData(
        \Magento\Tax\Model\Calculation\Rate\Converter $subject,
        $taxRate,
        $formData
    ){
        if (isset($formData['from_total'])){
            $taxRate->setFromTotal($formData['from_total']);
        }

        if (isset($formData['to_total'])){
            $taxRate->setToTotal($formData['to_total']);
        }

        return $taxRate;
    }

    /**
     * @param \Magento\Tax\Model\Calculation\Rate\Converter $subject
     * @param $taxRateFormData
     * @param TaxRateInterface $taxRate
     * @param bool $returnNumericLogic
     * @return mixed
     */
    public function afterCreateArrayFromServiceObject(
        \Magento\Tax\Model\Calculation\Rate\Converter $subject,
        $taxRateFormData,
        TaxRateInterface $taxRate,
        $returnNumericLogic = false
    ){
        $taxRateFormData['from_total'] = $taxRate->getFromTotal();
        $taxRateFormData['to_total'] = $taxRate->getToTotal();

        return $taxRateFormData;
    }
}
