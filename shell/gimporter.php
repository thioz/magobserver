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

	function createConfigProduct($data) {
		$prod = Mage::getModel('catalog/product');
		$prod->setWebsiteIds([1])
			->setAttributeSetId(11) // Ex: 100
			->setTypeId('configurable')
			->setCreatedAt(strtotime('now'))
			->setUpdatedAt(strtotime('now'))
			->setSku($data['sku']) // Ex: 'foo_bar'
			->setName($data['name']) // Ex: 'Foo Bar'
			->setWeight(1) // Ex: 11.22
			->setStatus(1)
			->setTaxClassId(2)
			->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
			->setPrice(0) // Ex: 19.49
			->setCategoryIds([3]) // Ex: 19.49
			->setDescription($data['description']) // Ex: 'Description Here!'
			->setShortDescription($data['name']) // Ex: 'Foo Name'
			->setStockData([
				'use_config_manage_stock' => 0,
				'manage_stock' => 1,
				'is_in_stock' => 1,
				'qty' => 1,
		]);

		return $prod;
	}

	// Shell script point of entry
	public function run() {
		$sync = new Gracious_ProdImport_Syncer_CaseSync();
		$sync = new Gracious_ProdImport_Syncer_ConfigurableSyncer();
		$builder = new Gracious_ProdImport_Builder_ProductBuilder();

//		$configurableProductsData = [];
//		$prod = Mage::getModel('catalog/product')->load(71);
//		$stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($prod);
//		
//		
//		print_r($stock->getData());
//		die();

		$attributeIds = [
			'color' => $sync->getAttribute('color')->getId(),
			'type' => $sync->getAttribute('type')->getId(),
		];
//		$attrKeys = [];
//		
//		$configurableAttributesData = $prod->getTypeInstance()->getConfigurableAttributesAsArray();		
//		foreach($configurableAttributesData as $key => $attribute){
//			$attrKeys[$attribute['attribute_code']]  = $key;
//		}
//		
//		$simpleIds = [70,71];
//		foreach($simpleIds as $simpleId){
//			
//			$simpleProduct = Mage::getModel('catalog/product')->load($simpleId);
//			foreach($attributeIds as $code => $attrId){
//				$simpleProductsData = array(
//						'label'         => $simpleProduct->getAttributeText($code),
//						'attribute_id'  => $attrId,
//						'value_index'   => (int) $simpleProduct->getData($code),
//						'is_percent'    => 0,
//						'pricing_value' => $simpleProduct->getPrice(),
//				);
//				$key=$attrKeys[$code];
//				$configurableAttributesData[$key]['values'][] = $simpleProductsData;
//				$configurableProductsData[$simpleProduct->getId()]=$simpleProductsData;
//
//			}
//		}
//		$prod->setConfigurableProductsData($configurableProductsData);
//		$prod->setConfigurableAttributesData($configurableAttributesData);
//		$prod->setCanSaveConfigurableAttributes(true);
//		$prod->save();
		//print_r($configurableAttributesData);
//		foreach ($used as $usedProd) {
//			$simpleProduct = Mage::getModel('catalog/product')->load($usedProd->getId());
//			$configurableProductsData[$simpleProduct->getId()] = [];
//			foreach ($attrkeys as $code => $key) {
//
//				$attr = $this->getAttribute($code);
//				$simpleData = [
//					'label' => $simpleProduct->getAttributeText($code),
//					'attribute_id' => $attr->getId(),
//					'value_index' => $this->getValueIdByAttribute($code, $simpleProduct->getAttributeText($code)),
//					'is_percent' => 0,
//					'pricing_value' => $simpleProduct->getPrice(),
//				];
//				$configurableProductsData[$simpleProduct->getId()][] = $simpleData;
//			}
//		}
		$url = 'https://www.ontwerpeencase.nl/api/product_list.json?email=suraj@graciousstudios.nl&password=gracious123';
		$list = json_decode(file_get_contents($url));

		foreach ($list as $cat) {
			$brand = $cat->brand;
			$name = $cat->product_name;

			$brandOptionId = $sync->getOrCreateAttributeValueOption('phone_brand', $brand);

			$model = $cat->phone_model;
			$modelOptionId = $sync->getOrCreateAttributeValueOption('phone_model', $model);

			$attributeKeys = [];

			$configurableProduct = $this->createConfigProduct([
				'sku' => 'prod_' . rand(1, 1949),
				'name' => $name,
				'description' => 'prod '
			]);

			$configurableProduct->setAttributeSetId(13);

			$configurableProduct->setPrice(0);
			$configurableProduct->setCategoryIds([3, 6]);
			$configurableProduct->setData('phone_model', $modelOptionId);
			$configurableProduct->setData('phone_brand', $brandOptionId);

			$configurableProduct->getTypeInstance()->setUsedProductAttributeIds(array_values($attributeIds));

			$configurableAttributesData = $configurableProduct->getTypeInstance()->getConfigurableAttributesAsArray();
			foreach ($configurableAttributesData as $key => $attribute) {
				$attributeKeys[$attribute['attribute_code']] = $key;
			}
			$configurableProductsData = array();

			foreach ($cat->versions as $version) {
				$type = $version->type;
				$typeOptionId = $sync->getOrCreateAttributeValueOption('type', $type);
				$price = $version->price->EUR;




				foreach ($version->colors as $colorProduct) {

					$color = $colorProduct->color;
					$colorOptionId = $sync->getOrCreateAttributeValueOption('color', $color);
					$sku = $colorProduct->SKU_Magento;

					$simpleProduct = $sync->getProductByAttribute('sku', $sku);
					if (!$simpleProduct) {

						$simpleProduct = new Mage_Catalog_Model_Product();
						$data = [
							'price' => $price,
							'weight' => 1,
							'name' => $name . '-' . $color,
							'attribute_set_id' => 13,
							'visibility' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
							'is_in_stock' => 1,
							'qty' => 199,
						];
						$simpleProduct->setData($data);
						$simpleProduct->setTypeId('simple');
						$simpleProduct->setPriceCalculation(false);
						$simpleProduct->setStatus(1);
						$simpleProduct->setWebsiteIDs(array(1)); // put your website ids here
						$simpleProduct->setTaxClassId(0);
						$simpleProduct->setSku($sku);
						$simpleProduct->setStockData([
							'use_config_manage_stock' => 1,
							'manage_stock' => 1,
							'is_in_stock' => 1,
							'qty' => 999,
						]);
						// add attributes 
						$attributes = [
							'color' => $colorOptionId,
							'type' => $typeOptionId,
						];
						foreach ($attributes as $code => $valueId) {
							$simpleProduct->setData($code, $valueId);
						}
						$simpleProduct->setData('phone_model', $modelOptionId);
						$simpleProduct->setData('phone_brand', $brandOptionId);

						$simpleProduct->save();

						foreach ($attributes as $code => $valueId) {
							$simpleProductsData = array(
								'label' => $simpleProduct->getAttributeText($code),
								'attribute_id' => $attributeIds[$code],
								'value_index' => $valueId,
								'is_percent' => 0,
								'pricing_value' => $simpleProduct->getPrice(),
							);

							$key = $attributeKeys[$code];
							$configurableAttributesData[$key]['values'][] = $simpleProductsData;
							$configurableProductsData[$simpleProduct->getId()] = $simpleProductsData;
						}
					}
				}
			}
			$configurableProduct->setConfigurableProductsData($configurableProductsData);
			$configurableProduct->setConfigurableAttributesData($configurableAttributesData);
			$configurableProduct->setCanSaveConfigurableAttributes(true);
			$configurableProduct->save();
			die();
		}

		die();


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
			'label' => $simpleProduct->getAttributeText('color'),
			'attribute_id' => $attr->getId(),
			'value_index' => $sync->getValueIdByAttribute('color', 'green'),
			'is_percent' => 0,
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
