<?php

class Gracious_ProdImport_IndexController extends Mage_Core_Controller_Front_Action {
	public function attrsAction() {
		$url = 'https://www.ontwerpeencase.nl/api/product_list.json?email=suraj@graciousstudios.nl&password=gracious123';
		$list = json_decode(file_get_contents($url));
		$attrs = [
			'type' => [],
			'manufacturer' => [],
			'model' => [],
			'color' => [],
		];
		foreach ($list as $cat) {
			$brand = $cat->brand;
			$attrs['manufacturer'][$brand] = $brand;
			$modelKey = md5(strtolower(trim($cat->phone_model)));
			$attrs['model'][$modelKey] = $cat->phone_model;
			foreach ($cat->versions as $version) {
				$versionKey = md5(strtolower(trim($version->type)));
				$attrs['type'][$versionKey] = $version->type;
				foreach ($version->colors as $color) {
					$colorKey = md5(strtolower(trim($color->color)));

					$attrs['color'][$colorKey] = $color->color;
				}
			}
		}
		foreach ($attrs as $attr => $vals) {
			foreach ($vals as $val) {
				$id = $this->getOrCreateAttributeValueId($attr, $val);
				echo '<pre>';
				print_r([$attr, $val, $id]);
				echo '</pre>';
			}
		}
	}

	public function createsimplesAction() {
		$url = 'https://www.ontwerpeencase.nl/api/product_list.json?email=suraj@graciousstudios.nl&password=gracious123';
		$list = json_decode(file_get_contents($url));
		foreach ($list as $cat) {
			$brand = $cat->brand;
			$model = $cat->phone_model;
			foreach ($cat->versions as $version) {
				$type = $version->type;
				$price = $version->price->EUR;

				foreach ($version->colors as $color) {
					$simpleProduct = Mage::getModel('catalog/product');
					$existing=$simpleProduct->loadByAttribute('sku',$color->SKU_Magento);
					if($existing){
						print_r('skipping :'.$color->SKU_Magento);
						continue;
					}
					
					$colorvalue = $color->color;
					$simple = $this->createSimpleProduct([
						'name' => $cat->product_name . ' ' . $type . ' ' . $colorvalue,
						'sku' => $color->SKU_Magento,
						'price' => $price,
						'attributes' => [
							'color' => $colorvalue,
							'manufacturer' => $brand,
							'model' => $model,
							'type' => $type
						]
					]);
					$simple->save();
				}
			}
		}
	}

	function assigncategoriesAction() {
		
		$query = [
			['field' => 'attribute_set_id', 'cond' => ['eq' => 11]],
			['field' => 'type_id', 'cond' => ['eq' => 'configurable']],
		];
		
		$attrs=['type'];
 
		$configurables= $this->loadSimplesByQuery($query);
		foreach($configurables as $prod){
			$cat = [];
			$fail=false;
			foreach($attrs as $attr){
				$val=$prod->getData($attr);
				if(!$val){
					$fail=true;
					break;
				}
				$cat[]=$prod->getAttributeText($attr);
			}
			if(!$fail){
				$category = $this->createCategory(136, $cat);
				$ids=$prod->getCategoryIds();
				$ids[]=$category->getId();
				$prod->setCategoryIds(array_unique($ids));
				$prod->save();
			}
		}
		
	}

	function createCategory($parentId, $names) {
		$path = [];
		foreach ($names as $name) {
		$category = Mage::getModel('catalog/category');
			$path[] = $name;
			$existing=$category->getResourceCollection()
				->addFieldToFilter('parent_id',$parentId)
				->addFieldToFilter('name',$name);
			if(count($existing)){
				foreach($existing as $ex){
					$parentId=$ex->getId();
					$category = $ex;
				}
				continue;
			}
			$category->setName($name);
			$category->setUrlKey(implode('/', $path));
			$category->setIsActive(1);
			$category->setDisplayMode('PRODUCTS');
			$category->setIsAnchor(1); //for active anchor
			$category->setStoreId(Mage::app()->getStore()->getId());
			$parentCategory = Mage::getModel('catalog/category')->load($parentId);
			$category->setPath($parentCategory->getPath());
			$category->save();
			
			$parentId = $category->getId();
		}
		return $category;
	}

	public function createconfigsAction() {



		$confAttr = ['model' => ['useprice' => true], 'color' => ['useprice' => false]];
		$staticAttrs = ['type', 'manufacturer'];

		$query = [
			['field' => 'attribute_set_id', 'cond' => ['eq' => 11]],
			['field' => 'type_id', 'cond' => ['eq' => 'simple']],
		];

		$simples = $this->loadSimplesByQuery($query);

		$variants = [];
		foreach ($simples as $simple) {
			$key = [];
			$labels = [];
			foreach ($staticAttrs as $attr) {

				$val = $simple->getData($attr);
				$key[] = $val;
				$labels[$attr] = $simple->getAttributeText($attr);
			}
			$vkey = implode('/', $key);
			if (!isset($variants[$vkey])) {
				$variants[$vkey] = ['label' => $labels, 'ids' => []];
			}
			$variants[$vkey]['ids'][] = $simple->getId();
		}


		foreach ($variants as $key => $variant) {

			$sku = $this->createSku('', $variant['label']);

			if (count($variant['ids']) == 0) {
				continue;
			}
			try {
				$confprod = $this->createConfigurableProduct($confAttr, $variant['label'], $sku, $variant['ids']);
				if ($confprod) {
					$confprod->setName(implode(' ', $variant['label']));
					$confprod->save();
				}
			}
			catch (Exception $e) {
				echo '<pre>';
				print_r($e->getMessage());
				echo '</pre>';
			}
		}
	}

	function getAttrList($statics, &$res = null) {
		if (!$res) {
			$res = [];
		}
		$staticAttr = array_shift($statics);
		if (!$staticAttr) {
			return $res;
		}

		$attr = Mage::getModel('catalog/product')->getResource()->getAttribute($staticAttr);
		$options = $attr->getSource()->getAllOptions(false);
		foreach ($options as $option) {
			if ($option['label'] == '*') {
				continue;
			}
			if (!isset($res[$staticAttr][$option['value']])) {
				$res[$staticAttr][$option['value']] = ['label' => $option['label'], 'children' => []];
			}

			$this->getAttrList($statics, $res[$staticAttr][$option['value']]['children']);
		}
		return $res;
	}

	function getVariationList($attrlist, $attrs, &$items = [], $prevkeys = null) {

		$t = array_shift($attrlist);
		if (!$t) {
			return $items;
		}

		foreach ($attrs[$t] as $id => $val) {
			if ($prevkeys) {
				$key = implode('/', $prevkeys);
				$pval = $items[$key];

				$key = implode('/', array_merge($prevkeys, [$id]));
				$items[$key] = $pval;
				$items[$key][] = $val['label'];
			}
			else {
				$key = $id;
				$items[$key] = [$val['label']];
			}





			$this->getVariationList($attrlist, $val['children'], $items, explode('/', $key));
		}
		return $items;
	}

	function walkTree($tree, &$items = [], $prevkeys = null) {

		foreach ($tree as $t => $nodes) {
			foreach ($nodes as $id => $nodevals) {
				$path = [];
				$val = [];
				if ($prevkeys) {
					$path = $prevkeys;
					$oldpath = implode('/', $path);
					$val = $items[$oldpath];
				}

				$path[] = $id;
				$val[] = $nodevals['label'];
				$items[implode('/', $path)] = $val;
				$this->walkTree($nodevals['children'], $items, $path);
			}
		}
		return $items;
	}

	function walkTreeList($list, $tree, &$items = [], $val = [], $prevkeys = null) {

		$t = array_shift($list);

		if (!$t) {
			$oldpath = implode('/', $prevkeys);
			$items[$oldpath] = $val;
		}
		else {

			foreach ($tree[$t] as $id => $nodevals) {
				$newvals = $val;
				if ($prevkeys) {
					$path = $prevkeys;
				}
				else {
					$path = [];
				}
				$newvals[$t] = $nodevals['label'];
				$path[] = $id;
				$this->walkTreeList($list, $nodevals['children'], $items, $newvals, $path);
			}
		}

		return $items;
	}

	public function indexAction() {


		Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);



		$products = [
			[
				'name' => 'product_5',
				'sku' => 'product_5',
				'price' => 23,
				'attributes' => [
					'color' => 'black',
					'manufacturer' => 'Samsung',
					'model' => 's7',
					'type' => 'walletcase'
				]
			],
			[
				'name' => 'product_6',
				'sku' => 'product_6',
				'price' => 22,
				'attributes' => [
					'color' => 'green',
					'manufacturer' => 'Samsung',
					'model' => 's7',
					'type' => 'hardcase'
				]
			]
		];

		$simpleIds = [];

//		foreach ($products as $productdata) {
//			$simple = $this->createSimpleProduct($productdata);
//			$simple->save();
//			$simpleIds[] = $simple->getId();
//		}


		$ids = [];

		//$this->createConfigurableProduct(['color'],['manufacturer'=>'Nokia','model'=>'3210','type'=>'hardcase'], $sku,$ids);
		echo '<pre>';
		print_r($sku);
		echo '</pre>';

		$sku = $this->createSku('confprod', ['manufacturer' => 'Nokia']);
		$simples = $this->loadSimplesByAttributes(['manufacturer' => 'Nokia']);
		foreach ($simples as $simple) {
			$ids[] = $simple->getId();
		}
		$this->createConfigurableProduct(['model' => ['useprice' => false], 'color' => ['useprice' => false], 'type' => ['useprice' => true]], ['manufacturer' => 'Nokia'], $sku, $ids);
	}

	function loadSimplesByQuery($q) {
		$collection = Mage::getModel('catalog/product')->getResourceCollection()
			->addAttributeToSelect('*');

		foreach ($q as $cond) {
			if (is_array($cond) && isset($cond['field'])) {
				$collection->addFieldToFilter($cond['field'], $cond['cond']);
			}
			else {
				$collection->addFieldToFilter($cond);
			}
		}

		return $collection;
	}

	function loadSimplesByAttributes($attrs) {
		$collection = Mage::getModel('catalog/product')->getResourceCollection()
			->addAttributeToSelect('*');
		$cond = array();

		foreach ($attrs as $attr => $val) {
			$vals = is_array($val) ? $val : [$val];
			foreach ($vals as $aval) {
				$optionId = $this->getOrCreateAttributeValueId($attr, $aval);
				$cond[] = array('attribute' => $attr, 'eq' => $optionId);
			}
			$collection->addFieldToFilter($cond);
		}

		return $collection;
	}

	function loadConfigProduct($productId) {
		$product = Mage::getModel('catalog/product')->load($productId);
		$confData = $product->getTypeInstance()->getConfigurableAttributesAsArray();
		$used = $product->getTypeInstance()->getUsedProducts();
		echo '<pre>';
		print_r($used);
		echo '</pre>';
	}

	function createSku($name, $attrs) {
		$basesku = '';
		if ($name != '') {
			$basesku = md5($name) . '-';
		}
		return $basesku . strtolower(implode('-', $attrs));
	}

	function createConfigurableProduct($usedAttributes, $attributes, $sku, $productIds) {

		$existing = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
		$setConfAttrs = true;
		if ($existing) {
			$configProduct = $existing;
			$setConfAttrs = false;
		}
		else {
			$configProduct = Mage::getModel('catalog/product');
		}
		$lowestprice = null;
		foreach ($productIds as $productId) {
			$product = Mage::getModel('catalog/product')->load($productId);
			$price = $product->getPrice();
			if ($lowestprice === null || $price < $lowestprice) {
				$lowestprice = $price;
			}
		}

		try {
			$configProduct
				->setWebsiteIds(array(1)) //website ID the product is assigned to, as an array
				->setAttributeSetId(11) //ID of a attribute set named 'default'
				->setTypeId('configurable') //product type
				->setCreatedAt(strtotime('now')) //product creation time
				->setSku($sku) //SKU
				->setName('test config product96') //product name
				->setWeight(4.0000)
				->setStatus(1) //product status (1 - enabled, 2 - disabled)
				->setTaxClassId(2) //tax class (0 - none, 1 - default, 2 - taxable, 4 - shipping)
				->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH) //catalog and search visibility
				->setPrice($lowestprice) //price in form 11.22
				->setMetaTitle('test meta title 2')
				->setMetaKeyword('test meta keyword 2')
				->setMetaDescription('test meta description 2')
				->setDescription('This is a long description')
				->setShortDescription('This is a short description')
				->setMediaGallery(array('images' => array(), 'values' => array())) //media gallery initialization
				->setCategoryIds(array(3, 10)) //assign product to categories
			;
			/**/
			/** assigning associated product to configurable */
			/**/
			$usedAttributeIds = [];
			foreach ($usedAttributes as $code => $opt) {
				$attr = Mage::getModel('catalog/product')->getResource()->getAttribute($code);
				$usedAttributeIds[$code] = $attr->getId();
			}


			foreach ($attributes as $code => $value) {
				$optionId = $this->getOrCreateAttributeValueId($code, trim($value));

				$configProduct->setData($code, $optionId);
			}
			if ($setConfAttrs) {
				$configProduct->getTypeInstance()->setUsedProductAttributeIds($usedAttributeIds); //attribute ID of attribute 'color' in my store
			}
			$configurableAttributesData = $configProduct->getTypeInstance()->getConfigurableAttributesAsArray();
			$confAttrIndex = [];
			foreach ($configurableAttributesData as $i => $confAttr) {
				$confAttrIndex[$confAttr['attribute_id']] = $i;
			}
			$configProduct->setCanSaveConfigurableAttributes(true);

			$configurableProductsData = array();

			foreach ($productIds as $productId) {
				$product = Mage::getModel('catalog/product')->load($productId);
				$configurableProductsData[$productId] = [];
				foreach ($usedAttributeIds as $code => $id) {
					$attrIndex = $confAttrIndex[$id];
					$attrOpts = $usedAttributes[$code];

					$price = $attrOpts['useprice'] ? $product->getPrice() - $lowestprice : 0;

					$configurableAttributesData[$attrIndex]['values'][] = [
						'label' => $product->getAttributeText($code),
						'value_index' => $product->getData($code),
						'is_percent' => 0,
						'pricing_value' => $price,
					];
					$configurableProductsData[$productId][] = [
						'label' => $product->getAttributeText($code),
						'attribute_id' => $id,
						'value_index' => $product->getData($code),
						'is_percent' => 0,
						'pricing_value' => $price,
					];
				}
			}
			
			$configProduct->setConfigurableProductsData($configurableProductsData);
			$configProduct->setConfigurableAttributesData($configurableAttributesData);
			$configProduct->save();
			return $configProduct;
		}
		catch (Exception $e) {
			Mage::log($e->getMessage());
			echo $e->getMessage();
		}
	}

	function createSimpleProduct($productData) {

		$simpleProduct = Mage::getModel('catalog/product');
		try {



			$simpleProduct
				->setWebsiteIds(array(1)) //website ID the product is assigned to, as an array
				->setAttributeSetId(11) //ID of a attribute set named 'default'
				->setTypeId('simple') //product type
				->setCreatedAt(strtotime('now')) //product creation time
				->setSku($productData['sku']) //SKU
				->setName($productData['name']) //product name
				->setWeight(4.0000)
				->setStatus(1) //product status (1 - enabled, 2 - disabled)
				->setTaxClassId(2) //tax class (0 - none, 1 - default, 2 - taxable, 4 - shipping)
				->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH) //catalog and search visibility
				->setPrice($productData['price']) //price in form 11.22
				->setMetaTitle('test meta title 2')
				->setMetaKeyword('test meta keyword 2')
				->setMetaDescription('test meta description 2')
				->setDescription('This is a long description')
				->setShortDescription('This is a short description')
				->setMediaGallery(array('images' => array(), 'values' => array())) //media gallery initialization
				->setStockData(array(
					'use_config_manage_stock' => 0, //'Use config settings' checkbox
					'manage_stock' => 1, //manage stock
					'min_sale_qty' => 1, //Minimum Qty Allowed in Shopping Cart
					'max_sale_qty' => 2, //Maximum Qty Allowed in Shopping Cart
					'is_in_stock' => 1, //Stock Availability
					'qty' => 999 //qty
					)
				)
				->setCategoryIds(array(3, 10)); //assign product to categories

			foreach ($productData['attributes'] as $code => $value) {
				$valueId = $this->getOrCreateAttributeValueId($code, $value);
			 
				$simpleProduct->setData($code, $valueId);
			}

			return $simpleProduct;

			//$simpleProduct->save();
		}
		catch (\Exception $e) {
			print_r($e->getMessage());
		}
	}

	function getOrCreateAttributeValueId($attributeName, $value) {
		$attr = Mage::getModel('catalog/product')->getResource()->getAttribute($attributeName);
		if ($attr) {
			$optionId = $attr->getSource()->getOptionId($value);
			if (!$optionId) {
				$installer = new Mage_Eav_Model_Entity_Setup('core_setup');
				$installer->startSetup();
				$tst = $installer->addAttributeOption([
					'attribute_id' => $attr->getId(),
					'value' => [[$value, $value]]
				]);
				echo '<pre>';
				print_r($tst);
				echo '</pre>';
				$installer->endSetup();
				$attr = Mage::getModel('catalog/product')->getResource()->getAttribute($attributeName);

				return $attr->getSource()->getOptionId($value);
			}
			return $attr->getSource()->getOptionId($value);
		}
	}

}

class MagProdQuery {

	protected $wheres = [];

	function where($field, $op = null, $val = null, $bool = 'and') {
		if ($field instanceof Closure) {
			$subq = new MagProdQuery();
			$field($subq);
			$this->wheres[] = ['type' => 'sub', 'query' => $subq, 'bool' => $bool];
			return $this;
		}
		$this->wheres[] = ['type' => 'simple', 'field' => $field, 'op' => $op, 'val' => $val, 'bool' => $bool];
		return $this;
	}

	function orWhere($field, $op = null, $val = null) {
		return $this->where($field, $op, $val, 'or');
	}

	function whereAttr($attr, $op, $val) {
		$this->wheres[] = ['type' => 'attr', 'attr' => $attr, 'op' => $op, 'val' => $val, 'bool' => 'and'];
		return $this;
	}

	function get() {
		$collection = Mage::getModel('catalog/product')->getResourceCollection()
			->addAttributeToSelect('*');
		foreach ($this->wheres as $where) {
			if ($where['type'] == 'attr') {

				try {
					$attr = Mage::getModel('catalog/product')->getResource()->getAttribute($where['attr']);
					$collection->addFieldToFilter([['attribute' => $where['attr'], 'eq' => $attr->getSource()->getOptionId($where['val'])]]);
				}
				catch (Exception $ex) {
					echo '<pre>';
					print_r($ex->getMessage());
					echo '</pre>';
				}
			}
			else {
				$collection->addFieldToFilter($where['field'], ['eq' => $where['val']]);
			}
		}
		return $collection;
	}

}
