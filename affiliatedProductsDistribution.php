<?php
	// Introduce script.
	echo "<meta charset='utf-8'>";
	echo "Script '".__FILE__."' running...<hr>";
	ini_set("memory_limit","512M");
	date_default_timezone_set("Europe/Berlin");
	define('MAGENTO_ROOT', 'XXX');
	$compilerConfig = MAGENTO_ROOT . '/includes/config.php';
	if(file_exists($compilerConfig)){ include $compilerConfig; }
	$mageFilename = MAGENTO_ROOT . '/app/Mage.php';
	require_once($mageFilename);
	Mage::init();
	Mage::app()->getStore()->setConfig('catalog/frontend/flat_catalog_product', 0);
	Mage::app()->getCacheInstance()->banUse('translate');	
	$start = microtime(true);
	
	// Sammlung aller Artikel.
	$collection = Mage::getModel('catalog/product')
		->getCollection()
		->addAttributeToSelect('up_sell_product_grid_table')
		->load();
	
	// -- Alle Artikel durchgehen und deren Position und Typ speichern --------------------------------------------------
	$neueZubehoerArtikel = array();
	foreach($collection as $_product){
		
		// Artikel aus der DB holen.
		if($_product === "") continue;
		$productId = $_product->getId(); 												// Richtige ID.															
		$product = Mage::getModel('catalog/product')->setStoreId(0)->load($productId);	// Richtiger Artikel.
		
		// Eckdaten.
		$lampensystem = explode(',', $product->getLampensystem()); 						// Kategorien auslesen.
		$farbEndung = end(explode('-', $product->getSku())); 							// Farbendung auslesen (W, WW, B).
		$farbEndung = $farbEndung != 'W' && $farbEndung != 'WW' && $farbEndung != 'B' ? '0' : $farbEndung; 
		$isZubehoerBerechtigt = $product->getAttributeText('zubehoer_berechtigt');		// Ist das Produkt ein Zubehör-Artikel.
		if($isZubehoerBerechtigt == false) continue;									// Überspringen falls kein Zubehör-Artikel.
		
		// Position des Artikels berechnen, basierend auf der Kategorie.
		$preis = number_format($product->getPrice(), 0);								// Setze die Position gleich dem Preis.
		$lichtstrom = $product->getLichtstrom();										// Lichtstrom messen
		$kabelLaenge= $product->getKabellaenge() * 10;									// Länge des Kabels (x10). 
		$ausgangsleistung = $product->getAusgangsleistungVgeraet();						// Watt Ausgangsleistung.
		$position = $preis;
		
		// Kategorien auslesen und konvertieren.
		$kategorien = Mage::getModel('catalog/category')->getCollection()->addAttributeToSelect('name');
		$kategorienIds = $_product->getCategoryIds();
		$hauptKategorie = "";
		foreach($kategorien as $katIdx => $katElem){
			if(empty($hauptKategorie) && in_array($katElem->getId(), $kategorienIds)){
				if(stripos("-".$katElem->getName(), "leucht")){
					$hauptKategorie = "Leuchte";
					$position = $lichtstrom;
				}else if(stripos("-".$katElem->getName(), "lamp")){
					$hauptKategorie = "Lampen"; 
				}else if(stripos("-".$katElem->getName(), "kabel")){
					$hauptKategorie = "Kabel";
					$position = $kabelLaenge;
				}else if(stripos("-".$katElem->getName(), "vorschalt")){
					$hauptKategorie = "Vorschaltgerät";
					$position = $ausgangsleistung;
				}
			}
		}
		if(empty($hauptKategorie)){ $hauptKategorie = "Sonstiges"; }
		
		// Speichere die Werte in den neuen Zubehör-Artikeln.
		$neueZubehoerArtikel[$productId] = Array(
			"system" => $lampensystem,				// Das System des Artikels als Zahlwert.
			"position" => $position,				// Position innerhalb des Zubehör Registers.
			"farbe" => $farbEndung,					// Weiß, Warmweiß, Blau, Normal
			"Kategorie" => $hauptKategorie			// Kategorie (Leuchte, Zubehör, Lampe etc)
		);
		
		// Debug print.
		echo "<h3>Artikel ".$product->getSku()." mit ID [$productId] als Zubehör hinzugefügt</h3>";
		echo "<pre>".print_r($neueZubehoerArtikel[$productId], 1)."</pre>";
		echo "<hr>";
	}
	echo "<hr>";
	
	// -- Alle Artikel durchgehen und die gesammelten Positionen als Zubehör hinzufügen ---------------------------------
	foreach($collection as $_product){
		
		// Artikel aus der DB holen.
		if($_product === "") continue;
		$productId = $_product->getId(); // Richtige ID.
		$product = Mage::getModel('catalog/product')->setStoreId(0)->load($productId);	// Richtiger Artikel.
		
		// Eckdaten.
		$lampensystem = explode(',', $product->getLampensystem()); 						// Kategorien auslesen.
		$farbEndung = end(explode('-', $product->getSku())); 							// Farbendung auslesen (W, WW, B).
		$farbEndung = $farbEndung != 'W' && $farbEndung != 'WW' && $farbEndung != 'B' ? '0' : $farbEndung; 
		$isZubehoerBerechtigt = $product->getAttributeText('zubehoer_berechtigt');		// Ist das Produkt ein Zubehör-Artikel.
		
		// Artikeleigenschaften sammeln.
		$preis = number_format($product->getPrice(), 0);								// Setze die Position gleich dem Preis.
		$lichtstrom = $product->getLichtstrom();										// Lichtstrom messen
		$kabelLaenge= $product->getKabellaenge() * 10;									// Länge des Kabels (x10). 
		$ausgangsleistung = $product->getAusgangsleistungVgeraet();						// Watt Ausgangsleistung.
		
		// Kategorien auslesen und konvertieren.
		$kategorien = Mage::getModel('catalog/category')->getCollection()->addAttributeToSelect('name');
		$kategorienIds = $_product->getCategoryIds();
		$hauptKategorie = "";

		foreach($kategorien as $katIdx => $katElem){
			if(empty($hauptKategorie) && in_array($katElem->getId(), $kategorienIds)){
				if(stripos("-".$katElem->getName(), "leucht")){
					$hauptKategorie = "Leuchte";
				}else if(stripos("-".$katElem->getName(), "lamp")){
					$hauptKategorie = "Lampen"; 
				}else if(stripos("-".$katElem->getName(), "kabel")){
					$hauptKategorie = "Kabel";
				}else if(stripos("-".$katElem->getName(), "vorschalt")){
					$hauptKategorie = "Vorschaltgerät";
					$position = $ausgangsleistung;
				}
			}
		}
		if(empty($hauptKategorie)){ $hauptKategorie = "Sonstiges"; }
		
		echo "<fieldset><legend><b>Test ".$product->getSku()." [$productId], $hauptKategorie, $farbEndung</b></legend>";
		
		// Alle Zubehörartikel durchgehen.
		$zubehoerDaten = array();
		foreach($neueZubehoerArtikel as $zubehoerIdx => $zubehoerElem){
			
			// Wenn beides Leuchten sind:
			if($hauptKategorie == 'Leuchte' && $zubehoerElem['Kategorie'] == 'Leuchte'){
				
				$alleKategorienGleich = count(array_diff($zubehoerElem['system'], $lampensystem)) == 0;
				if($alleKategorienGleich == true){
					$zubehoerDaten[$zubehoerIdx] = array('position' => $zubehoerElem['position']);
				}	
			}else{
				// Ansonsten einfach passend hinzugefügen:
				if($farbEndung == $zubehoerElem['farbe'] && count(array_diff($zubehoerElem['system'], $lampensystem)) == 0){
					$zubehoerDaten[$zubehoerIdx] = array('position' => $zubehoerElem['position']);
				}				
			}
		}
		
		// Save to product if there are changes.
		Mage::getResourceModel('catalog/product_link')->saveProductLinks(
			$product, $zubehoerDaten, Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED
		);
		
		echo "</fieldset>";
	}
	
	// ------------------------------------------------------------------------------------------------------------------	
	
	// Measure time.
	$time = round((microtime(true) - $start), 2);
	echo "<hr>Success! Script took <b>$time</b> seconds to execute!<hr>";
?>
