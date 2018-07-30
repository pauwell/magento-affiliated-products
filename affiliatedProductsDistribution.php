<?php

	echo "<meta charset='utf-8'>";

	// Prepare magento for script execution.
	echo "Script '".__FILE__."' running...<hr>";
	
	ini_set("memory_limit","512M");
	date_default_timezone_set("Europe/Berlin");
	//define('MAGENTO_ROOT', '?????????????????????????????');
	$compilerConfig = MAGENTO_ROOT . '/includes/config.php';
	if(file_exists($compilerConfig)){ include $compilerConfig; }
	$mageFilename = MAGENTO_ROOT . '/app/Mage.php';
	require_once($mageFilename);
	Mage::init();
	Mage::app()->getStore()->setConfig('catalog/frontend/flat_catalog_product', 0);
	Mage::app()->getCacheInstance()->banUse('translate');	
	
	// Start execution-time measuring
	$start = microtime(true);
	
	// Sammlung aller Artikel.
	$collection = Mage::getModel('catalog/product')
		->getCollection()
		->addAttributeToSelect('up_sell_product_grid_table')
		->load();
	
	
	// -- Script content -----------------------------------------------------------------
	
	// Alle Artikel durchgehen und deren Position und Typ speichern:
	$neueZubehoerArtikel = array();
	foreach($collection as $_product){
		// Artikel aus der DB holen.
		if($_product === "") return;
		$productId = $_product->getId(); 		// Richtige ID.		
		if($productId > '50') return;// XXXXXXXXX
		$product = Mage::getModel('catalog/product')->setStoreId(0)->load($productId);	// Richtiger Artikel.
		
		// Eckdaten.
		$lampensystem = explode(',', $product->getLampensystem()); 						// Kategorien auslesen.
		$farbEndung = end(explode('-', $product->getSku())); 							// Farbendung auslesen (W, WW, B).
		$isZubehoerBerechtigt = $product->getAttributeText('zubehoer_berechtigt');		// Ist das Produkt ein Zubehör-Artikel.
		
		// Position des Artikels berechnen, basierend auf der Kategorie.
		$preis = number_format($product->getPrice(), 0);
		$lichtstrom = $product->getLichtstrom();										// Lichtstrom messen
		$kabelLaenge= $product->getKabellaenge() * 10;									// Länge des Kabels (x10). 
		$ausgangsleistung = $product->getAusgangsleistungVgeraet();						// Watt Ausgangsleistung.
		
		// Kategorien auslesen.
		$kategorien = Mage::getModel('catalog/category')->getCollection()->addAttributeToSelect('name');
		$kategorienIds = $_product->getCategoryIds();
		$position = 0;
		
		echo "<h3>Artikel $productId als Zubehör</h3>";
		
		foreach($lampensystem as $sys){
			if(empty($sys)) continue;
			
			foreach($kategorienIds as $kategorieId){		
				foreach($kategorien as $id => $kategorie){
					if($kategorieId != $id) continue;
						
					// Berechne Position je nach Kategorietyp.
					$kategorieName = $kategorie->getName();
					$position = number_format($product->getPrice(), 0);
					if($kategorieName == "Leuchtmittel"){ $position = $product->getLichtstrom(); }
					else if($kategorieName == "Kabel"){ $position = $product->getKabellaenge(); }
					else if($kategorieName == "Vorschaltgeraet"){ $position = $product->getAusgangsleistungVgeraet(); }
						
					// Speichere die extrahierten Positionen in der Liste. Form: Artikel['794']['LED Leuchten']['WW'] = 31 (ID).
					echo "<p>Artikel[$sys][$kategorieName][$farbEndung] = $productId</p>";
					$neueZubehoerArtikel[$sys][$kategorieName][$farbEndung] = $productId;
				}
			}
		}
	}
	
	// -- End script content -------------------------------------------------------------
	
	// Measure time.
	$time = round((microtime(true) - $start), 2);
	echo "<hr>Success! Script took <b>$time</b> seconds to execute!<hr>";
?>
