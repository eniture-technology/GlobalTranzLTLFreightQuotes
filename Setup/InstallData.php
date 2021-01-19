<?php
namespace Eniture\GlobalTranzLTLFreightQuotes\Setup;

use Eniture\GlobalTranzLTLFreightQuotes\App\State;
use Eniture\GlobalTranzLTLFreightQuotes\Cron\PlanUpgrade;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Eav\Model\Config;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * @codeCoverageIgnore
 */
class InstallData implements InstallDataInterface
{
    /**
     * EAV setup factory
     *
     * @var EavSetupFactory
     */
    private $eavSetupFactory;
    /**
     * @var Tables to use
     */
    private $tableNames;
    /**
     * @var Attributes to create
     */
    private $attrNames;
    /**
     * @var Customer Session
     */
    private $customerSession;
    /**
     * @var DB Connection
     */
    private $connection;
    /**
     * @var Magento Version
     */
    private $mageVersion;
    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;
    /**
     * @var CollectionFactory
     */
    public $collectionFactory;
    /**
     * @var ProductFactory
     */
    public $productLoader;
    /**
     * @var ResourceConnection
     */
    public $resource;
    /**
     * @var State
     */
    private $state;
    /**
     * @var Curl
     */
    private $curl;
    /**
     * @var ConfigInterface
     */
    private $resourceConfig;
    /**
     * @var PlanUpgrade
     */
    private $planUpgrade;
    /**
     * @var Config
     */
    private $eavConfig;

    /**
     * InstallData constructor.
     * @param EavSetupFactory $eavSetupFactory
     * @param State $state
     * @param ProductMetadataInterface $productMetadata
     * @param CollectionFactory $collectionFactory
     * @param ProductFactory $productLoader
     * @param ResourceConnection $resource
     * @param Curl $curl
     * @param ConfigInterface $resourceConfig
     * @param PlanUpgrade $planUpgrade
     * @param Config $eavConfig
     */
    public function __construct(
        EavSetupFactory $eavSetupFactory,
        State $state,
        ProductMetadataInterface $productMetadata,
        CollectionFactory $collectionFactory,
        ProductFactory $productLoader,
        ResourceConnection $resource,
        Curl $curl,
        ConfigInterface $resourceConfig,
        PlanUpgrade $planUpgrade,
        Config $eavConfig
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->state = $state;
        $this->productMetadata = $productMetadata;
        $this->collectionFactory = $collectionFactory;
        $this->productLoader = $productLoader;
        $this->resource = $resource;
        $this->connection = $resource->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $this->mageVersion = $this->productMetadata->getVersion();
        $this->curl = $curl;
        $this->resourceConfig = $resourceConfig;
        $this->planUpgrade = $planUpgrade;
        $this->eavConfig = $eavConfig;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @throws LocalizedException
     */
    public function install(
        ModuleDataSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $this->state->validateAreaCode();
        // Check plan info of current module
        $this->planUpgrade->execute();
        $installer = $setup;
        $installer->startSetup();
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $this->removeOldNameSpace($installer);
        $this->getTableNames();
        $this->attrNames();
        $this->createOrderDetailAttr($installer);

        $this->addGlobalTranzLTLAttributes($installer, $eavSetup);
        $this->createGlobalTranzLTLWarehouseTable($installer);
        $this->createEnitureModulesTable($installer);
        $this->updateProductDimensionalAttr($installer, $eavSetup);
        $this->checkLTLExistanceColumForEnModules($installer);
        $this->checkISLDColumForWarehouse($installer);
        $installer->endSetup();
    }

    /**
     *
     */
    public function getTableNames()
    {
        $this->tableNames = [
            'eav_attribute' => $this->resource->getTableName('eav_attribute'),
            'EnitureModules' => $this->resource->getTableName('EnitureModules'),
            'eav_attribute_option' => $this->resource->getTableName('eav_attribute_option'),
            'eav_attribute_option_value' => $this->resource->getTableName('eav_attribute_option_value')
        ];
    }

    /**
     *
     */
    public function attrNames()
    {
        $dimAttr = [
            'length' => 'length',
            'width'  => 'width',
            'height' => 'height',
        ];
    }

    /**
     * @param $installer
     */
    public function createOrderDetailAttr($installer)
    {
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'order_detail_data',
            [
                'type' => Table::TYPE_TEXT,
                'nullable' => true,
                'default' => '',
                'comment' => 'Order Detail Widget Data'
            ]
        );
    }

    /**
     * @param $installer
     * @param $eavSetup
     * @throws LocalizedException
     */
    public function addGlobalTranzLTLAttributes(
        $installer,
        $eavSetup
    ) {
        $attributes = $this->attrNames;
        if ($this->mageVersion < '2.2.5' || $this->mageVersion > '2.3.2') {
            $count = 71;
            foreach ($attributes as $attr) {
                $isExist = $this->eavConfig
                    ->getAttribute('catalog_product', 'en_' . $attr . '')->getAttributeId();
                if ($isExist == null) {
                    $this->getAttributeArray(
                        $eavSetup,
                        'en_' . $attr,
                        'decimal',
                        ucfirst($attr),
                        'text',
                        '',
                        $count,
                        'validate-number validate-greater-than-zero'
                    );
                }
                $count++;
            }
        }

        $isLTLCheckExist = $this->connection->fetchOne("select count(*) as count From " . $this->tableNames['eav_attribute'] . " where attribute_code = 'en_ltl_check'");

        if ($isLTLCheckExist == 0) {
            $this->getAttributeArray($eavSetup, 'en_ltl_check', 'int', 'Ship Via LTL Freight', 'select', 'Magento\Eav\Model\Entity\Attribute\Source\Boolean', 77);
        }

        $isFreightClassExist = $this->connection->fetchOne("select count(*) as count From " . $this->tableNames['eav_attribute'] . " where attribute_code = 'speed_freight_class'");

        if ($isFreightClassExist == 0) {
            $this->getAttributeArray($eavSetup, 'en_freight_class', 'int', 'Freight Class', 'select', 'Eniture\GlobalTranzLTLFreightQuotes\Model\Source\GlobalTranzLTLFreightClass', 78);
        } else {
            $dataArr = [
                'attribute_code' => 'speed_freight_class',
                'source_model' => 'Eniture\GlobalTranzLTLFreightQuotes\Model\Source\GlobalTranzLTLFreightClass',
            ];
            $this->connection->update($this->tableNames['eav_attribute'], $dataArr, "attribute_code='speed_freight_class'");

            $isDensityExist = $this->connection->fetchOne("Select count(*) as count
                                            From " . $this->tableNames['eav_attribute_option_value'] . " ov
                                            Where ov.value = 'Density Based'
                                            AND (
                                                    select count(*)
                                                    From " . $this->tableNames['eav_attribute_option'] . " ao
                                                    Where ao.option_id = ov.option_id
                                                    AND (
                                                        select count(*)
                                                        From " . $this->tableNames['eav_attribute'] . " ea
                                                        Where ea.attribute_id = ao.attribute_id
                                                        AND ea.attribute_code = 'speed_freight_class'
                                                            ) > 0
                                            ) > 0");

            if ($isDensityExist == 0) {
                $query1 = "INSERT INTO " . $this->tableNames['eav_attribute_option'] . " (attribute_id, sort_order)
                                Select attribute_id, 20
                                From " . $this->tableNames['eav_attribute'] . " ea
                                Where ea.attribute_code = 'speed_freight_class'";
                $this->connection->query($query1);

                $query2 = "INSERT INTO " . $this->tableNames['eav_attribute_option_value'] . " (value_id, option_id, store_id, value)
                                Select option_id, option_id, 0, 'Density Based'
                                From " . $this->tableNames['eav_attribute_option'] . " op
                                Where
                                ( select count(*)
                                    from " . $this->tableNames['eav_attribute'] . " e
                                    Where e.attribute_code = 'speed_freight_class'
                                )>0
                                AND op.sort_order = 20";
                $this->connection->query($query2);
            }
        }

        $isendropshipExist = $this->eavConfig->getAttribute('catalog_product', 'en_dropship')->getAttributeId();

        if ($isendropshipExist == null) {
            $this->getAttributeArray(
                $eavSetup,
                'en_dropship',
                'int',
                'Enable Drop Ship',
                'select',
                'Magento\Eav\Model\Entity\Attribute\Source\Boolean',
                74
            );
        }

        $isdropshipExist = $this->eavConfig->getAttribute('catalog_product', 'en_dropship_location')->getAttributeId();

        if ($isdropshipExist == null) {
            $this->getAttributeArray(
                $eavSetup,
                'en_dropship_location',
                'int',
                'Drop Ship Location',
                'select',
                'Eniture\GlobalTranzLTLFreightQuotes\Model\Source\DropshipOptions',
                75
            );
        } else {
            $dataArr = [
                'source_model' => 'Eniture\GlobalTranzLTLFreightQuotes\Model\Source\DropshipOptions',
            ];
            $this->connection
                ->update($this->tableNames['eav_attribute'], $dataArr, "attribute_code = 'en_dropship_location'");
        }

        $isHazmatExist = $this->eavConfig->getAttribute('catalog_product', 'en_hazmat')->getAttributeId();

        if ($isHazmatExist == null) {
            $this->getAttributeArray(
                $eavSetup,
                'en_hazmat',
                'int',
                'Hazardous Material',
                'select',
                'Magento\Eav\Model\Entity\Attribute\Source\Boolean',
                76
            );
        }

        $installer->endSetup();
    }

    /**
     * @param $eavSetup
     * @param $code
     * @param $type
     * @param $label
     * @param $input
     * @param $source
     * @param $order
     * @param string $frontend_class
     * @return mixed
     */
    public function getAttributeArray(
        $eavSetup,
        $code,
        $type,
        $label,
        $input,
        $source,
        $order,
        $frontend_class = ''
    ) {
        $attrArr = $eavSetup->addAttribute(
            Product::ENTITY,
            $code,
            [
                'group' => 'Product Details',
                'type' => $type,
                'backend' => '',
                'frontend_class' => $frontend_class,
                'label' => $label,
                'input' => $input,
                'class' => '',
                'source' => $source,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'required' => false,
                'visible_on_front' => false,
                'is_configurable' => true,
                'sort_order' => $order,
                'user_defined' => true,
                'default' => '0'
            ]
        );

        return $attrArr;
    }

    /**
     * @param $installer
     */
    public function createGlobalTranzLTLWarehouseTable($installer)
    {
        $tableName = $installer->getTable('warehouse');
        if ($installer->getConnection()->isTableExists($tableName) != true) {
            $table = $installer->getConnection()
                ->newTable($tableName)
                ->addColumn('warehouse_id', Table::TYPE_INTEGER, null, [
                    'identity' => true,
                    'unsigned' => true,
                    'nullable' => false,
                    'primary' => true,
                ], 'Id')
                ->addColumn('city', Table::TYPE_TEXT, 30, [
                    'nullable' => false,
                ], 'city')
                ->addColumn('state', Table::TYPE_TEXT, 10, [
                    'nullable' => false,
                ], 'state')
                ->addColumn('zip', Table::TYPE_TEXT, 10, [
                    'nullable' => false,
                ], 'zip')
                ->addColumn('country', Table::TYPE_TEXT, 10, [
                    'nullable' => false,
                ], 'country')
                ->addColumn('location', Table::TYPE_TEXT, 10, [
                    'nullable' => false,
                ], 'location')
                ->addColumn('nickname', Table::TYPE_TEXT, 40, [
                    'nullable' => false,
                ], 'nickname')
                ->addColumn(
                    'in_store',
                    Table::TYPE_TEXT,
                    512,
                    [],
                    'in store pick up'
                )
                ->addColumn(
                    'local_delivery',
                    Table::TYPE_TEXT,
                    512,
                    [],
                    'local delivery'
                );
            $installer->getConnection()->createTable($table);
        }
        $installer->endSetup();
    }

    /**
     * @param $setup
     */
    public function createEnitureModulesTable($installer)
    {
        $moduleTableName = $installer->getTable('enituremodules');
        // Check if the table already exists
        if ($installer->getConnection()->isTableExists($moduleTableName) != true) {
            $table = $installer->getConnection()
                ->newTable($moduleTableName)
                ->addColumn('module_id', Table::TYPE_INTEGER, null, [
                    'identity' => true,
                    'unsigned' => true,
                    'nullable' => false,
                    'primary' => true,
                ], 'id')
                ->addColumn('module_name', Table::TYPE_TEXT, 200, [
                    'nullable' => false,
                ], 'module_name')
                ->addColumn('module_script', Table::TYPE_TEXT, 200, [
                    'nullable' => false,
                ], 'module_script')
                ->addColumn('dropship_field_name', Table::TYPE_TEXT, 200, [
                    'nullable' => false,
                ], 'dropship_field_name')
                ->addColumn('dropship_source', Table::TYPE_TEXT, 200, [
                    'nullable' => false,
                ], 'dropship_source');
            $installer->getConnection()->createTable($table);
        }

        $newModuleName = 'ENGlobalTranzLTL';
        $scriptName = 'Eniture_GlobalTranzLtlFreightQuotes';
        $isNewModuleExist = $this->connection->fetchOne(
            "SELECT count(*) AS count FROM " . $moduleTableName . " WHERE module_name = '" . $newModuleName . "'"
        );
        if ($isNewModuleExist == 0) {
            $insertDataArr = [
                'module_name' => $newModuleName,
                'module_script' => $scriptName,
                'dropship_field_name' => 'en_dropship_location',
                'dropship_source' => 'Eniture\GlobalTranzLTLFreightQuotes\Model\Source\DropshipOptions'
            ];
            $this->connection->insert($moduleTableName, $insertDataArr);
        }

        $installer->endSetup();
    }

    /**
     * @param $installer
     * @param $eavSetup
     */
    public function updateProductDimensionalAttr($installer, $eavSetup)
    {
        $lengthChange = $widthChange = $heightChange = false;

        if ($this->mageVersion > '2.2.4') {
            $productCollection = $this->collectionFactory->create()->addAttributeToSelect('*');
            foreach ($productCollection as $_product) {
                $product = $this->productLoader->create()->load($_product->getEntityId());

                $savedEnLength = $_product->getData('en_length');
                $savedEnWidth = $_product->getData('en_width');
                $savedEnHeight = $_product->getData('en_height');

                if (isset($savedEnLength) && $savedEnLength) {
                    $product->setData('ts_dimensions_length', $savedEnLength)->getResource()->saveAttribute($product, 'ts_dimensions_length');
                    $lengthChange = true;
                }

                if (isset($savedEnWidth) && $savedEnWidth) {
                    $product->setData('ts_dimensions_width', $savedEnWidth)->getResource()->saveAttribute($product, 'ts_dimensions_width');
                    $widthChange = true;
                }

                if (isset($savedEnHeight) && $savedEnHeight) {
                    $product->setData('ts_dimensions_height', $savedEnHeight)->getResource()->saveAttribute($product, 'ts_dimensions_height');
                    $heightChange = true;
                }
            }
        }

        $this->removeEnitureAttr($installer, $lengthChange, $widthChange, $heightChange, $eavSetup);
    }

    /**
     * @param $installer
     * @param $lengthChange
     * @param $widthChange
     * @param $heightChange
     * @param $eavSetup
     */
    public function removeEnitureAttr(
        $installer,
        $lengthChange,
        $widthChange,
        $heightChange,
        $eavSetup
    ) {
        if ($lengthChange == true) {
            $eavSetup->removeAttribute(Product::ENTITY, 'en_length');
        }

        if ($widthChange == true) {
            $eavSetup->removeAttribute(Product::ENTITY, 'en_width');
        }

        if ($heightChange == true) {
            $eavSetup->removeAttribute(Product::ENTITY, 'en_height');
        }
    }

    /**
     * @param $installer
     */
    public function checkLTLExistanceColumForEnModules($installer)
    {
        $tableName = $installer->getTable('enituremodules');

        if ($installer->getConnection()->isTableExists($tableName) == true) {
            if ($installer->getConnection()->tableColumnExists($tableName, 'is_ltl') === false) {
                $installer->getConnection()->addColumn($tableName, 'is_ltl', [
                    'type' => Table::TYPE_BOOLEAN,
                    'comment' => 'module type'
                ]);
            }
        }

        $this->connection->update($tableName, ['is_ltl' => 1], "module_name = 'ENGlobalTranzLTL'");
        $installer->endSetup();
    }

    /**
     * Add column to eniture modules table
     * @param $installer
     */
    private function checkISLDColumForWarehouse($installer)
    {
        $tableName = $installer->getTable('warehouse');
        if ($installer->getConnection()->isTableExists($tableName) == true) {
            if ($installer->getConnection()->tableColumnExists($tableName, 'in_store') === false &&
                $installer->getConnection()->tableColumnExists($tableName, 'local_delivery') === false) {
                $columns = [
                    'in_store' => [
                        'type' => Table::TYPE_TEXT,
                        'comment' => 'in store pick up'
                    ],
                    'local_delivery' => [
                        'type' => Table::TYPE_TEXT,
                        'comment' => 'local delivery'
                    ]

                ];
                $connection = $installer->getConnection();
                foreach ($columns as $name => $definition) {
                    $connection->addColumn($tableName, $name, $definition);
                }
            }
        }
        $installer->endSetup();
    }

    /**
     * This function will remove old namespaces from database so that
     * the new code works fine without any extra naming entries.
     * @param $installer
     */
    public function removeOldNameSpace($installer)
    {
        $setupTable = $installer->getTable('setup_module');
        $enitureTable = $installer->getTable('EnitureModules');
        if ($this->connection->isTableExists($setupTable) && $this->connection->isTableExists($enitureTable)) {
            $this->connection->query("DELETE FROM " . $setupTable . " WHERE module = 'Eniture_GlobalTranzLTLFreight'");
            $this->connection->query("DELETE FROM " . $enitureTable . " WHERE Module_Script = 'Eniture_GlobalTranzLtlFreight'");
        }
        //print_r('Old data has been removed');
    }
}
