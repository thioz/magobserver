<?php

class Gracious_ProdImport_Syncer_CaseSync {
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
