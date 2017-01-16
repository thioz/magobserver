<?php

require_once 'abstract.php';

class Gracious_Shell_Gimporter extends Mage_Shell_Abstract {

	protected $_argname = array();

	public function __construct() {
		parent::__construct();

		// Time limit to infinity
		set_time_limit(0);

		// Get command line argument named "argname"
		// Accepts multiple values (comma separated)
		if ($this->getArg('argname')) {
			$this->_argname = array_merge(
				$this->_argname, array_map(
					'trim', explode(',', $this->getArg('argname'))
				)
			);
		}
	}

	// Shell script point of entry
	public function run() {
		$sync = new Gracious_ProdImport_Syncer_CaseSync();
		$sync = new Gracious_ProdImport_Syncer_ConfigurableSyncer();
		$builder = new Gracious_ProdImport_Builder_ProductBuilder();

		$p = $builder->build([
			'type' => 'simple',
			'attributes' => [
				'color' => 'green',
				'material' => 'silver',
				'size' => 'm'
			],
			'images'=>Mage::getBaseDir('media') . DS . 'import/'.'c5zya47b2yt8_800.jpg',
			'price' => 130,
			'name' => 'ring2-{attr_material}-{attr_color}',
			'stock'=>[
				'use_config_manage_stock'=>0,
				'manage_stock'=>1,
				'is_in_stock'=>1,
				'qty'=>100,
			],
			'description' => 'poep',
			'short_description' => 'poep',
			'taxclass' => 0,
			'status' => 1,
			'weight' => 0,
			'attribute_set' => 9,
			'sku' => 'ring2-{attr_material}-{attr_color}',
			'websiteids' => [1],
			'visibility' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH
		]);

		//$p->save();
		$configProduct = Mage::getModel('catalog/product')->load(54);
		//$sync->addImageToProduct($configProduct, Mage::getBaseDir('media') . DS . 'import/'.'c5zya47b2yt8_800.jpg');
		$sync->addSimpleProduct($configProduct, $p);
		die();
		//$sync->addSimpleProduct($configProduct, $simpleProduct, 'color');
		//$configProduct->getTypeInstance()->setUsedProductAttributeIds(array($attr->getId()));
		$configurableAttributesData = $configProduct->getTypeInstance()->getConfigurableAttributesAsArray();
		$configurableProductsData = array();
		$used = $configProduct->getTypeInstance()->getUsedProducts();
		$used[] = $p;
		$attrkeys = [];
		foreach ($configurableAttributesData as $key => $attrData) {
			$attrkeys[$attrData['attribute_code']] = $key;
		}
		foreach ($used as $usedProd) {
			$simpleProduct = Mage::getModel('catalog/product')->load($usedProd->getId());
			$configurableProductsData[$simpleProduct->getId()] = [];
			foreach ($attrkeys as $code => $key) {
				$attr = $sync->getAttribute($code);
				$configurableProductsData[$simpleProduct->getId()][] = [
					'label' => $simpleProduct->getAttributeText($code),
					'attribute_id' => $attr->getId(),
					'value_index' => $sync->getValueIdByAttribute($code, $simpleProduct->getAttributeText($code)),
					'is_percent' => 0,
					'pricing_value' => $simpleProduct->getPrice(),
				];
				$configurableAttributesData[$key]['values'][] = [
					'label' => $simpleProduct->getAttributeText($code),
					'attribute_id' => $attr->getId(),
					'value_index' => $sync->getValueIdByAttribute($code, $simpleProduct->getAttributeText($code)),
					'is_percent' => 0,
					'pricing_value' => $simpleProduct->getPrice(),
				];
			}
		}
		$configProduct->setConfigurableProductsData($configurableProductsData);
		$configProduct->setConfigurableAttributesData($configurableAttributesData);
		$configProduct->save();
		echo '</pre>';
		//$sync->createSimpleProduct([]);
		die();
		$attr = $sync->getAttribute('color');
		$simpleProduct = Mage::getModel('catalog/product')->load(18);



		echo '<pre>';
		print_r($simpleProduct->getData());
		echo '</pre>';
		die();
		$configProduct = Mage::getModel('catalog/product')->load(28);
		//$sync->addSimpleProduct($configProduct, $simpleProduct, 'color');
		//$configProduct->getTypeInstance()->setUsedProductAttributeIds(array($attr->getId()));
		$configurableAttributesData = $configProduct->getTypeInstance()->getConfigurableAttributesAsArray();
		$configProduct->setCanSaveConfigurableAttributes(true);
		$childProducts = Mage::getModel('catalog/product_type_configurable')
			->getUsedProducts(null, $configProduct);
		echo '<pre>';
		print_r($configurableAttributesData);
		echo '</pre>';
		die();

		$simpleProductsData = array(
				'label'         => $simpleProduct->getAttributeText('color'),
				'attribute_id'  => $attr->getId(),
				'value_index'   => $sync->getValueIdByAttribute('color', 'green'),
				'is_percent'    => 0,
				'pricing_value' => $simpleProduct->getPrice(),
		);


		$configurableProductsData = array();
		$configurableProductsData[$simpleProduct->getId()] = $simpleProductsData;
		$configurableAttributesData[0]['values'][] = $simpleProductsData;

//		die();
		$configProduct->setConfigurableProductsData($configurableProductsData);
		$configProduct->setConfigurableAttributesData($configurableAttributesData);
		echo '<pre>';
		print_r($configProduct->getData('configurable_products_data'));
		echo '</pre>';
		die();
		$configProduct->save();


//		 $sku = 'trui-blue';
//        $title = 'bla dikke trui';
//        $description = 'this is a description about the product...';
//
//        $product = new Mage_Catalog_Model_Product();
//
//        $product->setSku($sku);
//        $product->setAttributeSetId(9); // put your attribute set id here.
//        $product->setTypeId('simple');
//        $product->setName($title);
//        $product->setCategoryIds(array(3)); // put your category ids here
//        $product->setWebsiteIDs(array(1));// put your website ids here
//        $product->setDescription($description);
//        $product->setShortDescription($description);
//        $product->setPrice(200);
//        $product->setWeight(100);
//        $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
//        $product->setStatus(1);
//        $product->setTaxClassId(0); 
//        $product->setStockData(array(
//                                    'is_in_stock'             => 1,
//                                    'qty'             => 999,
//                                    'manage_stock'            => 0,
//                                    'use_config_manage_stock' => 1
//                                    ));
//				$attr = Mage::getModel('catalog/product')->getResource()->getAttribute('color')->getSource()->getOptionId('red');
//				$product->setData('color', $attr);
//        try {
//// Save the grouped product.
//            $product->save();       
// 
//            $group_product_id = 15;
//
//// You need to create an array which contains the associate product ids.
//            $products_links = Mage::getModel('catalog/product_link_api');
//
//// Get grouped product id.
//
//// Map each associate product with the grouped product.
//            
//            $products_links->assign ("grouped",$product->getId(),$group_product_id);
//            
//
//        } catch (Exception $ex) {
//            echo $ex->getMessage();
//        }
	}

	// Usage instructions
	public function usageHelp() {
		return <<<USAGE
Usage:  php -f scriptname.php -- [options]
 
  --argname <argvalue>       Argument description
 
  help                   This help
 
USAGE;
	}

}

// Instantiate
$shell = new Gracious_Shell_Gimporter();

// Initiate script
$shell->run();
