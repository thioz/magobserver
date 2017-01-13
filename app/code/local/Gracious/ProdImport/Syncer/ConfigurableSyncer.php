<?php

class Gracious_ProdImport_Syncer_ConfigurableSyncer extends Gracious_ProdImport_Syncer_CaseSync {
	function addSimpleProduct($configProduct, $simpleObject, $attributeName) {
		$configProduct->setCanSaveConfigurableAttributes(true);
		$configurableAttributesData = $configProduct->getTypeInstance()->getConfigurableAttributesAsArray();
		$childs=$this->getChildObjectsIds($configProduct);
		$configurableProductsData = array();

		foreach($childs as $child){

			$simple = Mage::getModel('catalog/product')->load($child->getId());
			$simpleProductsData=$this->makeSimpleData($simple, 'color');
			$configurableAttributesData[0]['values'][] = $simpleProductsData;			
			$configurableProductsData[$child->getId()] = $simpleProductsData;
		}
		$configurableProductsData[$simpleObject->getId()] = $this->makeSimpleData($simpleObject, 'color');
		$configurableAttributesData[0]['values'][] =  $this->makeSimpleData($simpleObject, 'color');		
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
