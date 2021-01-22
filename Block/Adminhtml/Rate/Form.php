<?php

namespace WallaceIT\ThresholdTaxes\Block\Adminhtml\Rate;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tax\Controller\RegistryConstants;

class Form extends \Magento\Tax\Block\Adminhtml\Rate\Form {

    protected function _prepareForm()
    {
        parent::_prepareForm();

        $taxRateId = $this->_coreRegistry->registry(RegistryConstants::CURRENT_TAX_RATE_ID);

        try {
            if ($taxRateId) {
                $taxRateDataObject = $this->_taxRateRepository->get($taxRateId);
            }
            // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
        } catch (NoSuchEntityException $e) {
            //tax rate not found//
        }

        $sessionFormValues = (array)$this->_coreRegistry->registry(RegistryConstants::CURRENT_TAX_RATE_FORM_DATA);
        $formData = isset($taxRateDataObject)
            ? $this->_taxRateConverter->createArrayFromServiceObject($taxRateDataObject)
            : [];
        $formData = array_merge($formData, $sessionFormValues);

        $fieldset = $this->getForm()->getElement('base_fieldset');

        $fieldset->addField(
            'from_total',
            'text',
            [
                'name' => 'from_total',
                'label' => __('From Subtotal'),
                'title' => __('From Subtotal'),
                'required' => false,
                'class' => 'validate-not-negative-number',
                'value' => isset($formData['from_total']) ? $formData['from_total'] : 0.00
            ]
        );

        $fieldset->addField(
            'to_total',
            'text',
            [
                'name' => 'to_total',
                'label' => __('To Subtotal'),
                'title' => __('To Subtotal'),
                'required' => false,
                'class' => 'validate-not-negative-number',
                'value' => isset($formData['to_total']) ? $formData['to_total'] : 0.00,
                'note' => _('Set both from and to totals to 0 to disable threshold condition')
            ]
        );

        return $this;
    }
}
