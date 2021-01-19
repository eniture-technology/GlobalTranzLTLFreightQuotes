<?php


namespace Eniture\GlobalTranzLTLFreightQuotes\Model\Source;


class OrderShipDays
{
    public function toOptionArray()
    {
        return ['shipDays' =>
            ['value' => '1', 'label' => 'Monday'],
            ['value' => '2', 'label' => 'Tuesday'],
            ['value' => '3', 'label' => 'Wednesday'],
            ['value' => '4', 'label' => 'Thursday'],
            ['value' => '5', 'label' => 'Friday'],
        ];
    }
}