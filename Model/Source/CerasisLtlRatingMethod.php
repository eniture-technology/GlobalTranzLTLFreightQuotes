<?php

namespace Eniture\GlobalTranzLTLFreightQuotes\Model\Source;

class CerasisLtlRatingMethod
{
    public function toOptionArray()
    {
        return ['ratingMethod' => ['value' => '1', 'label' => 'Cheapest'],
            ['value' => '2', 'label' => 'Cheapest Options'],
            ['value' => '3', 'label' => 'Average'],
        ];
    }
}
