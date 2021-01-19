<?php
/**
 * GlobalTranz
 *
 * @package EnableCity
 * @author Eniture
 * @license https://eniture.com
 */

namespace Eniture\GlobalTranzLTLFreightQuotes\Model\Checkout\Block\Cart;

use Magento\Checkout\Block\Cart\LayoutProcessor;
use Magento\Checkout\Block\Checkout\AttributeMerger;
use Magento\Directory\Model\ResourceModel\Country\Collection as CountryCollection;
use Magento\Directory\Model\ResourceModel\Region\Collection;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Checkout cart shipping block plugin
 */
class Shipping extends LayoutProcessor
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param AttributeMerger $merger
     * @param CountryCollection $countryCollection
     * @param Collection $regionCollection
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        AttributeMerger $merger,
        CountryCollection $countryCollection,
        Collection $regionCollection
    ) {
        $this->scopeConfig = $scopeConfig;
        parent::__construct($merger, $countryCollection, $regionCollection);
    }

    /**
     * Show City in Shipping Estimation
     *
     * @return bool
     * @codeCoverageIgnore
     */
    protected function isCityActive()
    {
        return true;
    }
}
