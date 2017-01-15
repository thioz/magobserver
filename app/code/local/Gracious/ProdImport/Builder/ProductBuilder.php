<?php

class Gracious_ProdImport_Builder_ProductBuilder {

	protected $configurators = [
		'sku' => 'setSku',
		'type' => 'setTypeId',
		'name' => 'setName',
		'stock' => ['handler' => 'setStockData'],
		'images' => ['handler' => 'setImages'],
		'price' => 'setPrice',
		'weight' => 'setWeight',
		'taxclass' => 'setTaxClassId',
		'description' => 'setDescription',
		'attribute_set' => 'setAttributeSetId',
		'short_description' => 'setShortDescription',
		'status' => 'setStatus',
		'attributes' => ['handler' => 'setProductAtributes'],
		'visibility' => 'setVisibility',
		'websiteids' => 'setWebsiteIDs',
	];

	function build($config) {
		$product = new Mage_Catalog_Model_Product();
		return $this->configureProduct($product, $config);
	}

	function configureProduct($product, $config) {
		foreach ($config as $key => $value) {
			if (isset($this->configurators[$key])) {
				$configurator = $this->configurators[$key];
				$this->runConfigurator($product, $configurator, $value, $config);
			}
		}
		return $product;
	}

	function runConfigurator($product, $configurator, $value, $config) {
		if (is_string($configurator)) {
			call_user_func([$product, $configurator], $this->parseValue($value, $config));
		}
		else {
			if (is_array($configurator) && isset($configurator['handler'])) {
				call_user_func([$this, $configurator['handler']], $product, $value, $config);
			}
		}
	}

	function parseValue($value, $config) {
		$vars = $config;
		$attrs = isset($config['attributes']) ? $config['attributes'] : [];
		foreach ($attrs as $attrkey => $attrval) {
			$vars['attr_' . $attrkey] = $attrval;
		}
		foreach ($vars as $varkey => $varval) {
			$value = str_replace('{' . $varkey . '}', $varval, $value);
		}
		return $value;
	}

	function setImages($product, $values, $config) {
		if (!is_array($values)) {
			$values = array(
				'thumbnail' => $values,
				'small_image' => $values,
				'image' => $values,
			);
		}

		foreach ($values as $imageType => $filePath) {

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

	function setStockData($product, $values, $config) {
		$stockData = $values;
		$product->setStockData($stockData);
	}

	function setProductAtributes($product, $values, $config) {
		foreach ($values as $name => $value) {
			$attr = Mage::getModel('catalog/product')->getResource()->getAttribute($name);
			if ($attr) {
				$id = $attr->getSource()->getOptionId($value);

				$product->setData($name, $id);
			}
		}
	}

}
