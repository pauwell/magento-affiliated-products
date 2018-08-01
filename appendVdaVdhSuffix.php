<?php 
	ini_set("memory_limit","512M");
	date_default_timezone_set("Europe/Berlin");
	define('MAGENTO_ROOT', 'xxx/xxx/xxx');

	$compilerConfig = MAGENTO_ROOT . '/includes/config.php';
	if(file_exists($compilerConfig)){ include $compilerConfig; }
	$mageFilename = MAGENTO_ROOT . '/app/Mage.php';
	require_once $mageFilename;

	Mage::init();
	Mage::app()->getStore()->setConfig('catalog/frontend/flat_catalog_product', 0);
	Mage::app()->getCacheInstance()->banUse('translate');	
	
	// Get product collection.
	$collection = Mage::getModel('catalog/product')
		->getCollection()
		->addAttributeToSelect('sku_vda')
		->addAttributeToSelect('sku_vdh')
		->addAttributeToSelect('vda_id')
		->addAttributeToSelect('vdh_id')
		->load();	
	
	echo "Script 'vda_vdh_sku_add_suffix' running...<br>";
	
	// Loop each product.
	foreach($collection as $_product){
		if($_product !== ""){			
			// Get products.
			$product = Mage::getModel('catalog/product')->setStoreId(0)->load($_product->getId());
			
			// Vda.
			$sku_vda = $product->getSkuVda();								
			$vda_suffix = end((explode('-', $sku_vda)));					
			$dropdown = $product->getResource()->getAttribute('vda_id');	
			if($vda_suffix == "A" || $vda_suffix == "FBA" || $vda_suffix == "H" || $vda_suffix == "Q"){
				// A, FBA, H, Q
				$option_id = $dropdown->getSource()->getOptionId($vda_suffix);
				$product->setData('vda_id',$option_id)->getResource()->saveAttribute($product, 'vda_id');
			}else if($sku_vda == "" && $vda_suffix == ""){
				// Vollständig leer.
				$product->setData('vda_id',null)->getResource()->saveAttribute($product, 'vda_id');
			}else if($sku_vda != "" ){
				// Nichts.
				$option_id = $dropdown->getSource()->getOptionId('Nichts');
				$product->setData('vda_id', $option_id)->getResource()->saveAttribute($product, 'vda_id');
			}

			// Vdh.
			$sku_vdh = $product->getSkuVdh();								
			$vdh_suffix = end((explode('-', $sku_vdh)));					
			$dropdown = $product->getResource()->getAttribute('vdh_id');	
			if($vdh_suffix == "A" || $vdh_suffix == "FBA" || $vdh_suffix == "H" || $vdh_suffix == "Q"){
				// A, FBA, H, Q
				$option_id = $dropdown->getSource()->getOptionId($vdh_suffix);
				$product->setData('vdh_id',$option_id)->getResource()->saveAttribute($product, 'vdh_id');
			}else if($sku_vdh == "" && $vdh_suffix == ""){
				// Vollständig leer.
				$product->setData('vdh_id',null)->getResource()->saveAttribute($product, 'vdh_id');
			}else if($sku_vdh != "" ){
				// Nichts.
				$option_id = $dropdown->getSource()->getOptionId('Nichts');
				$product->setData('vdh_id', $option_id)->getResource()->saveAttribute($product, 'vdh_id');
			}
		}
	}
	
	// -------------------------------------------------------------------------------------------------------------
	echo "Success!";
?>
