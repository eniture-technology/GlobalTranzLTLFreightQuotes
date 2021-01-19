<?php


namespace Eniture\GlobalTranzLTLFreightQuotes\Model\Source;


class ShippingService
{
    public function toOptionArray()
    {
        return ['shippingService' =>
                    ['value' => '1', 'label' => 'Standard LTL Freight Services'],
                    ['value' => '2', 'label' => 'Final Mile Services'],
                ];
    }
}