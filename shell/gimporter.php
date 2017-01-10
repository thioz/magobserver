<?php
require_once 'abstract.php';
 
class Gracious_Shell_Gimporter extends Mage_Shell_Abstract
{
    protected $_argname = array();
 
    public function __construct() {
        parent::__construct();
 
        // Time limit to infinity
        set_time_limit(0);     
 
        // Get command line argument named "argname"
        // Accepts multiple values (comma separated)
        if($this->getArg('argname')) {
            $this->_argname = array_merge(
                $this->_argname,
                array_map(
                    'trim',
                    explode(',', $this->getArg('argname'))
                )
            );
        }
    }
 
    // Shell script point of entry
    public function run() {
		       $sku = 'AL108223233';
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
 
            $group_product_id = $product->getId();

// You need to create an array which contains the associate product ids.
            $simpleProductId[0] = 2;

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
 
    // Usage instructions
    public function usageHelp()
    {
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