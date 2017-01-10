<?php


class Gracious_ProdImport_Model_Observer {
	// Magento passes a Varien_Event_Observer object as the first parameter of dispatched events.
	public function logUpdate(Varien_Event_Observer $observer) {
		// Retrieve the product being updated from the event observer
		$product = $observer->getEvent()->getProduct();

		if ($product instanceof Mage_Catalog_Model_Product) {
			$config = $this->loadConfig();
			foreach ($config['rules'] as $ruleId => $rule) {
				if ($this->assertRuleCriteria($rule, $product)) {
					$this->runRuleHandler($rule, $product);
				}
			}
		}

	}

	protected function loadConfig() {
		return include __DIR__ . '/../etc/config.php';
	}

	protected function runRuleHandler($rule, $product) {
		if (!isset($rule['run'])) {
			return false;
		}

		$handler = $rule['run'];
		if (!is_array($handler)) {
			$handler = explode('::', $handler);
		}
		
		// create an instance 
		$instance = new $handler[0]();
		
		//call the sync handler
		return call_user_func([$instance, $handler[1]], $product);
	}

	protected function assertRuleCriteria($rule, $product) {
		if (!isset($rule['criteria'])) {
			return true;
		}
		$criteria = $rule['criteria'];
		foreach ($criteria as $key => $values) {
			switch ($key) {
				case 'category_id':
					$categoryIds = $product->getCategoryIds();
					$found = false;
					foreach ($values as $catId) {
						if (in_array($catId, $categoryIds)) {
							$found = true;
						}
					}
					if (!$found) {
						return false;
					}
					break;
				case 'type':
					$typeId = $product->getTypeId();
					if (!in_array($typeId, $values)) {
						return false;
					}
					break;
			}
		}
		return true;
	}

}
