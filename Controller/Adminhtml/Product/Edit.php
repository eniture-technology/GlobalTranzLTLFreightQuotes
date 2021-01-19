<?php

namespace Eniture\GlobalTranzLTLFreightQuotes\Controller\Adminhtml\Product;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Catalog\Controller\Adminhtml\Product\Builder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Shipping\Model\Config;
use Psr\Log\LoggerInterface;

class Edit extends \Magento\Catalog\Controller\Adminhtml\Product\Edit
{
    protected $_publicActions = ['edit'];
    protected $resultPageFactory;
    private $logger;
    private $authSession;
    private $connection;
    protected $scopeConfig;
    private $shipconfig;
    private $resource;

    public function __construct(
        Context $context,
        Builder $productBuilder,
        PageFactory $resultPageFactory,
        LoggerInterface $logger,
        Session $authSession,
        ResourceConnection $resource,
        Config $shipconfig
    ) {
        parent::__construct($context, $productBuilder, $resultPageFactory);
        $this->resultPageFactory = $resultPageFactory;
        $this->logger = $logger;
        $this->authSession = $authSession;
        $this->resource = $resource;
        $this->connection = $resource->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $this->shipconfig = $shipconfig;
    }

    public function execute()
    {
        $activeCarriers = array_keys($this->shipconfig->getActiveCarriers());
        $enitureModules = [];

        foreach ($activeCarriers as $carrierCode) {
            $enCarrier = substr($carrierCode, 0, 2);
            if ($enCarrier == 'EN') {
                array_push($enitureModules, $carrierCode);
            }
        }

        if (count($enitureModules) == 0) {
            return parent::execute();
        }
        $activeModuleList = implode("','", $enitureModules);

        $enitureTableName = $this->resource->getTableName('EnitureModules');
        $this->varifyModuleEntry($enitureTableName);

        $eavTableName = $this->resource->getTableName('eav_attribute');
        $this->validateSourceModel($activeModuleList, $enitureTableName, $eavTableName, $enitureModules);
        return parent::execute();
    }

    /**
     * This function validate entry of this module in databebase
     */
    public function varifyModuleEntry($enitureTableName)
    {
        $haveEntry = $this->connection->fetchOne("select count(*) as count From " . $enitureTableName . " Where Module_Name = 'ENGlobalTranzLTL'");
        if ($haveEntry == 0) {
            $insquery = "INSERT INTO " . $enitureTableName . " (Module_Name, Module_Script, Dropship_Field_Name, Dropship_Source)
                        VALUES ('ENGlobalTranzLTL', 'Eniture_GlobalTranzLTLFreight','en_dropship_location', 'Eniture\GlobalTranzLTLFreightQuotes\Model\Source\DropshipOptions')";
            $this->connection->query($insquery);
        }
    }

    /**
     * this function update source model if required
     * @param $activeModuleList
     */
    public function validateSourceModel($activeModuleList, $enitureTableName, $eavTableName, $enitureModules)
    {
        $modulesCountDb = $this->connection->fetchOne("select count(*) as count From " . $enitureTableName . " WHERE Module_Name NOT IN ('" . $activeModuleList . "')");

        if ($modulesCountDb > 0) {
            $delQuery = "DELETE FROM " . $enitureTableName . " WHERE Module_Name NOT IN ('" . $activeModuleList . "')";
            $this->connection->query($delQuery);

            $ltlExist = $this->connection->fetchOne("Select count(*) as count
                                From " . $enitureTableName . "
                                Where is_LTL = 1");

            if (!$ltlExist) {
                $this->connection->query("DELETE FROM $eavTableName WHERE attribute_code='en_freight_class'");
            }


            $sourceExist = $this->connection->fetchOne("Select count(*) as count
                                From " . $enitureTableName . " em
                                Where
                                (
                                    Select count(*)
                                    From " . $eavTableName . " ea
                                    where ea.attribute_code = 'en_dropship_location'
                                    AND ea.source_model = em.Dropship_Source
                                )> 0");

            if (!$sourceExist) {
                $dropshipSource = $this->connection->fetchOne("SELECT Dropship_Source FROM " . $enitureTableName . " WHERE Module_Name = '" . $enitureModules[0] . "'");
                $updateQuery = "UPDATE " . $eavTableName . " SET source_model = '" . $dropshipSource . "' WHERE attribute_code = 'en_dropship_location'";
                $this->connection->query($updateQuery);
            }
        }
    }
}
