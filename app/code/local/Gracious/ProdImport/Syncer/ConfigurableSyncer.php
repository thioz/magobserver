<?php

class Gracious_ProdImport_Syncer_ConfigurableSyncer extends Gracious_ProdImport_Syncer_CaseSync {
	
	function addSimpleProduct($configProduct, $product) {
		$configProduct->setCanSaveConfigurableAttributes(true);
		$configurableAttributesData = $configProduct->getTypeInstance()->getConfigurableAttributesAsArray();
		$configurableProductsData = array();
		$used = $configProduct->getTypeInstance()->getUsedProducts();
		$used[] = $product;
		$attrkeys = [];
		foreach ($configurableAttributesData as $key => $attrData) {
			$attrkeys[$attrData['attribute_code']] = $key;
		}
		foreach ($used as $usedProd) {
			$simpleProduct = Mage::getModel('catalog/product')->load($usedProd->getId());
			$configurableProductsData[$simpleProduct->getId()] = [];
			foreach ($attrkeys as $code => $key) {
				
				$attr = $this->getAttribute($code);
				$simpleData=[
					'label' => $simpleProduct->getAttributeText($code),
					'attribute_id' => $attr->getId(),
					'value_index' => $this->getValueIdByAttribute($code, $simpleProduct->getAttributeText($code)),
					'is_percent' => 0,
					'pricing_value' => $simpleProduct->getPrice(),
				];
				$configurableProductsData[$simpleProduct->getId()][] = $simpleData;
				$configurableAttributesData[$key]['values'][] = $simpleData;
			}
		}
		$configProduct->setConfigurableProductsData($configurableProductsData);
		$configProduct->setConfigurableAttributesData($configurableAttributesData);
		$configProduct->save();
		
	}

	function getChildObjectsIds($configProduct) {
		return Mage::getModel('catalog/product_type_configurable')
				->getUsedProducts(null, $configProduct);
	}
	 

	function makeSimpleData($simpleProduct, $attributeName) {
		$attr = $this->getAttribute($attributeName);
 
		return array(
			'label' => $simpleProduct->getAttributeText($attributeName),
			'attribute_id' => $attr->getId(),
			'value_index' => $simpleProduct->getData($attributeName),
			'is_percent' => 0,
			'pricing_value' => $simpleProduct->getPrice(),
		);
	}

}
