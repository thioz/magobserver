<?php

class Gracious_ProdImport_Syncer_CaseSync {

	protected $defaultProductData = [
		'sku' => '443gfgfg',
		'price' => 19.000,
		'weight' => 1,
		'name' => 'fdfdfddsd',
		'color' => 6,
		'material' => 23,
		'attribute_set_id' => 9,
		'category_ids' => [5],
		'visibility' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
		'is_in_stock'=>1,
		'qty'=>199,
		'stock' => array(
			'is_in_stock' => 1,
			'qty' => 999,
			'manage_stock' => 0,
			'use_config_manage_stock' => 1
		),
		
	];

	/**
	 * 
	 * @param Mage_Catalog_Model_Product $product
	 */
	function syncProduct($product) {
		$type = $product->getTypeId();
		if ($type == 'simple') {
			$groupedParentsIds = Mage::getResourceSingleton('catalog/product_link')
				->getParentIdsByChild($product->getId(), Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED);

			if (count($groupedParentsIds) > 0) {
				foreach ($groupedParentsIds as $parentId) {
					$parent = Mage::getModel('catalog/product')->load($parentId);
					$this->sendUpdatedProduct($parent);
				}
			}
		}
		if ($type == 'grouped') {
			$this->sendUpdatedProduct($product);
		}
	}

	function createSimpleProduct($data) {
		$data+=$this->defaultProductData;

		$product = new Mage_Catalog_Model_Product();
		$product->setData($data);
		
		$product->setTypeId('simple');
        $product->setPriceCalculation(false);
        $product->setStatus(1);
        $product->setWebsiteIDs(array(1));// put your website ids here
        $product->setTaxClassId(0); 
        $product->setCategoryIds($data['category_ids']);
		echo '<pre>';
		print_r($data);
		echo '</pre>';
				$product->save();

		return $product;

//
//        $product->setSku($sku);
//        $product->setAttributeSetId(9); // put your attribute set id here.
//        $product->setName($title);
//        $product->setCategoryIds(array(3)); // put your category ids here
//        $product->setDescription($description);
//        $product->setShortDescription($description);
//        $product->setPrice(200);
//        $product->setWeight(100);
//        $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
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
	}

	function addImageToProduct($product, $path) {
		$mediaArray = array(
			'thumbnail' => $path,
			'small_image' => $path,
			'image' => $path,
		);

// Remove unset images, add image to gallery if exists
		$importDir = Mage::getBaseDir('media') . DS . 'import/';

		foreach ($mediaArray as $imageType => $fileName) {
			$filePath = $importDir . $fileName;
			if (file_exists($filePath)) {
				try {
					$product->addImageToMediaGallery($filePath, $imageType, false);
				}
				catch (Exception $e) {
					echo $e->getMessage();
				}
			}
			else {
				echo "Product does not have an image or the path is incorrect. Path was: {$filePath}<br/>";
			}
		}
	}

	protected function sendUpdatedProduct($product) {
		$products = $product->getTypeInstance(true)->getAssociatedProducts($product);
		$maindata = $this->getProductData($product);
		$maindata['children'] = [];
		foreach ($products as $simpleProduct) {
			$maindata['children'][] = $this->getProductData($simpleProduct);
		}
		echo '<pre>';
		print_r($maindata);
		echo '</pre>';
	}

	function getValueIdByAttribute($attribute, $value) {
		$attr = $this->getAttribute($attribute);
		if ($attr) {
			return $attr->getSource()->getOptionId($value);
		}
	}

 
	function addAttributeValueOption($attr, $value, $label) {
		$installer = new Mage_Eav_Model_Entity_Setup('core_setup');
		$installer->startSetup();

		$attribute = Mage::getModel('eav/entity_attribute')->loadByCode('catalog_product', $attr);
		if ($attribute->getId() && $attribute->getFrontendInput() == 'select') {
			$installer->addAttributeOption([
				'attribute_id' => $attribute->getId(),
				'value' => [[$value, $label]]
			]);
		}
		$installer->endSetup();
	}

	function getAttribute($name) {
		$attr = Mage::getModel('catalog/product')->getResource()->getAttribute($name);
		return $attr;
	}

	function getProductData($product) {
		$data = [
			'name' => $product->getName(),
			'sku' => $product->getSku(),
			'price' => $product->getPrice(),
			'description' => $product->getDescription(),
			'thumbnail' => $product->getData('thumbnail'),
			'image' => $product->getData('image'),
		];
		return $data;
	}

}
