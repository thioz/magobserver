<?php
class Gracious_ProdImport_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
       $product = Mage::getModel('catalog/product')->load(14);
			 echo '<pre>';
			 print_r($product->getData());
			 echo '</pre>';
			 die();
//		$attr = Mage::getModel('catalog/product')->getResource()->getAttribute('color')->getSource()->getOptionId('purple');
//		$product->setData('color', $attr);
//		$product->save();
//		
			
			$sync = new Gracious_ProdImport_Syncer_CaseSync();
			$sync->addAttributeValueOption('color', 'green','Green');
			

    }
}