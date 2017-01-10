<?php

/* Our class name should follow the directory structure of our Observer.php model, starting from the namespace, replacing directory separators with underscores. The directory of ousr Observer.php is following:
  app/code/local/Mage/ProductLogUpdate/Model/Observer.php */

class Gracious_ProdImport_Model_Observer {
// Magento passes a Varien_Event_Observer object as the first parameter of dispatched events.
	public function logUpdate(Varien_Event_Observer $observer) {
// Retrieve the product being updated from the event observer
		$product = $observer->getEvent()->getProduct();
		
		
		
		if ($product instanceof Mage_Catalog_Model_Product) {
			$config = $this->loadConfig();
			foreach($config['rules'] as $ruleId =>$rule){
				if($this->assertRuleCriteria($rule,$product)){
					$this->runRuleHandler($rule,$product);
				}
			}
			
		}
	
		
// Write a new line to var/log/product-updates.log
		$name = $product->getName();
		$sku = $product->getSku();

		Mage::log("{$name} {$product->getTypeId()}({$sku}) updated", null, 'product-updates.log');
	}
	
	protected function loadConfig(){
		return include __DIR__.'/../etc/config.php';
	}
	
	protected function runRuleHandler($rule,$product){
		if(!isset($rule['run'])){
			return false;
		}
		
		$handler =$rule['run'];
		if(!is_array($handler)){
			$handler=explode('::',$handler);
		}
		
		$instance = new $handler[0]();
		call_user_func([$instance,$handler[1]],$product);
		
		
	}
	
	protected function assertRuleCriteria($rule,$product){
		if(!isset($rule['criteria'])){
			return true;
		}
		$criteria = $rule['criteria'];
		foreach($criteria as $key => $values){
			switch($key){
				case 'category_id':
					$categoryIds = $product->getCategoryIds();
					$found=false;
					foreach($values as $catId){
						if(in_array($catId, $categoryIds)){
							$found=true;
						}
					}
					if(!$found){
						return false;
					}
					break;
				case 'type':
					$typeId = $product->getTypeId();
					if(!in_array($typeId, $values)){
						return false;
					}
					break;
			}
		}
		return true;
	}
	
public function createProduct()
    {
	 Mage::log("WORKS!");
        $sku = 'AL108';
        $title = 'my test product';
        $description = 'this is a description about the product...';

        $product = new Mage_Catalog_Model_Product();

        $product->setSku($sku.'-grouped');
        $product->setAttributeSetId(4); // put your attribute set id here.
        $product->setTypeId('grouped');
        $product->setName($title);
        $product->setCategoryIds(array(3)); // put your category ids here
        $product->setWebsiteIDs(array(1));// put your website ids here
        $product->setDescription($description);
        $product->setShortDescription($description);
        $product->setPrice(1000);
        $product->setWeight(200);
        $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
        $product->setStatus(1);
        $product->setTaxClassId(0); 
        $product->setStockData(array(
                                    'is_in_stock'             => 1,
                                    'manage_stock'            => 0,
                                    'use_config_manage_stock' => 1
                                    ));

        try {
// Save the grouped product.
            $product->save();       
						echo '<pre>';
						print_r($product);
						echo '</pre>';
            $group_product_id = $product->getId();

// You need to create an array which contains the associate product ids.
            $simpleProductId[0] = 1483;
            $simpleProductId[1] = 1484;
            $simpleProductId[2] = 1485;
                $simpleProductId[3] = 1486;
            $simpleProductId[4] = 1487;

            $products_links = Mage::getModel('catalog/product_link_api');

// Get grouped product id.

// Map each associate product with the grouped product.
            foreach($simpleProductId as $id){
                $products_links->assign ("grouped",$group_product_id,$id);
            }

        } catch (Exception $ex) {
            echo $ex->getMessage();
        }

    }	

}
