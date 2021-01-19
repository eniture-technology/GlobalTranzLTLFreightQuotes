<?php


namespace Eniture\GlobalTranzLTLFreightQuotes\Model\Source;


class FinalMileServices
{
    public function toOptionArray()
    {
        return ['finalMileServices' =>
                    ['value' => '1', 'label' => 'Threshold'],
                    ['value' => '2', 'label' => 'Room of Choice'],
                    ['value' => '3', 'label' => 'Premium'],
                ];
    }
}