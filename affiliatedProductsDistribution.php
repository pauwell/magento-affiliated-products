<?php
	// Introduce script.
	echo "<meta charset='utf-8'>";
	echo "Script '".__FILE__."' running...<hr>";
	
	// Prepare magento for script execution.
	ini_set("memory_limit","512M");
	date_default_timezone_set("Europe/Berlin");
	define('MAGENTO_ROOT', '/var/www/vhosts/rs213855.rs.hosteurope.de/dev3_new');
	$compilerConfig = MAGENTO_ROOT . '/includes/config.php';
	if(file_exists($compilerConfig)){ include $compilerConfig; }
	$mageFilename = MAGENTO_ROOT . '/app/Mage.php';
	require_once($mageFilename);
	Mage::init();
	Mage::app()->getStore()->setConfig('catalog/frontend/flat_catalog_product', 0);
	Mage::app()->getCacheInstance()->banUse('translate');	
	
	// Start execution-time measuring.
	$start = microtime(TRUE);
	
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
		
		// Ist ein Set.
		if(substr($_product->getSku(), 0, 3) == "SET"){
			continue;
		}
		
		//if($productId != 5027 && $productId != 3940 && $productId > 10) continue;
		
		$product = Mage::getModel('catalog/product')->setStoreId(0)->load($productId);	// Richtiger Artikel.
		
		// Eckdaten.
		$lampensystem = explode(',', $product->getLampensystem()); 						// Kategorien auslesen.
		$farbEndung = end(explode('-', $product->getSku())); 							// Farbendung auslesen (W, WW, B).
		$farbEndung = $farbEndung != 'W' && $farbEndung != 'WW' && $farbEndung != 'B' ? '0' : $farbEndung;
		$sockel = $product->getSockel();												// Lampensockel (E27, GU10).
		$fassungen = $product->getFassung();											// Lampenfassung (E27, GU10)
		$isZubehoerBerechtigt = $product->getAttributeText('zubehoer_berechtigt');		// Ist das Produkt ein Zubehör-Artikel.
		if($isZubehoerBerechtigt == FALSE) continue;									// Überspringen falls kein Zubehör-Artikel.
		
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
				
				if(stripos("-".$katElem->getName(), "lamp")){
					$hauptKategorie = "Lampe"; 
				}else if(stripos("-".$katElem->getName(), "leucht")){
					$hauptKategorie = "Leuchte";
					$position = $lichtstrom;
				}else if(stripos("-".$katElem->getName(), "kabel")){
					$hauptKategorie = "Kabel";
					$position = $kabelLaenge;
				}else if(stripos("-".$katElem->getName(), "vorschalt")){
					$hauptKategorie = "Vorschaltgerät";
					$position = $ausgangsleistung;
				}else if(stripos("-".$katElem->getName(), "fassung")){
					$hauptKategorie = "Fassung";
					$position = $ausgangsleistung; // @ Andere Positionierung?
				}else if(stripos("-".$katElem->getName(), "landing")){
					$hauptKategorie = "Landingpages";
				}
			}
		}
		if(empty($hauptKategorie)){ $hauptKategorie = "Sonstiges"; }
		
		// Überspringe den Artikel wenn er in der Kategorie Landingpages ist oder eine falsche Artikelnummer hat.
		if($hauptKategorie == "Landingpages" || substr($product->getSku(), 0, 1) == 'V' ){
			continue; 
		}
		
		// Speichere die Werte in den neuen Zubehör-Artikeln.
		$neueZubehoerArtikel[$productId] = Array(
			"system" => $lampensystem,				// Das System des Artikels als Zahlwert.
			"position" => $position,				// Position innerhalb des Zubehör Registers.
			"farbe" => $farbEndung,					// Weiß, Warmweiß, Blau, Normal
			"sockel" => $sockel,					// E27, GU10
			"fassungen" => $fassungen,				// E27, GU10
			"kategorie" => $hauptKategorie			// Kategorie (Leuchte, Zubehör, Lampe etc)
		);
		
		// Debug print.
		echo "<h3>Artikel ".$product->getSku()." mit ID [$productId] als Zubehör hinzugefügt</h3>";
		echo "<pre>".print_r($neueZubehoerArtikel[$productId], 1)."</pre>";
		echo "<hr>";
	}
	echo "<hr>";
	
	// -- Alle Artikel durchgehen und die gesammelten Positionen als Zubehör hinzufügen ---------------------------------
	foreach($collection as $productIndex => $_product){
		
		// Artikel aus der DB holen.
		if($_product === "") continue;
		$productId = $_product->getId(); // Richtige ID.
		
		if(substr($_product->getSku(), 0, 3) == "SET"){
			continue;
		}
	
		//if($productId != 5027 && $productId != 3940 && $productId > 10) continue;
		
		$product = Mage::getModel('catalog/product')->setStoreId(0)->load($productId);	// Richtiger Artikel.
		
		// Eckdaten.
		$lampensystem = explode(',', $product->getLampensystem()); 						// Kategorien auslesen.
		$farbEndung = end(explode('-', $product->getSku())); 							// Farbendung auslesen (W, WW, B).
		$farbEndung = $farbEndung != 'W' && $farbEndung != 'WW' && $farbEndung != 'B' ? '0' : $farbEndung; 
		$sockel = $product->getSockel();												// Lampensockel (E27, GU10).
		$fassungen = $product->getFassung();											// Lampenfassungen (E27, GU10).
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
				if(stripos("-".$katElem->getName(), "lamp")){
					$hauptKategorie = "Lampe"; 
				}else if(stripos("-".$katElem->getName(), "leucht")){
					$hauptKategorie = "Leuchte";
				}else if(stripos("-".$katElem->getName(), "kabel")){
					$hauptKategorie = "Kabel";
				}else if(stripos("-".$katElem->getName(), "vorschalt")){
					$hauptKategorie = "Vorschaltgerät";
					$position = $ausgangsleistung;
				}else if(stripos("-".$katElem->getName(), "fassung")){
					$hauptKategorie = "Fassung";
					$position = $ausgangsleistung; // @ Andere Positionierung?
				}
			}
		}
		if(empty($hauptKategorie)){ $hauptKategorie = "Sonstiges"; }
		
		//echo "<fieldset><legend><b>Test ".$product->getSku()." [$productId], $hauptKategorie, $farbEndung</b></legend>";
		echo "<h3>Prüfe Artikel ".$product->getSku()." [$productId]! Vorgang zu ".intval($productIndex / 3870 * 100) ."% abgeschlossen!</h3>\n";
		
		// Alle Zubehörartikel durchgehen.
		$zubehoerDaten = array();
		foreach($neueZubehoerArtikel as $zubehoerIdx => $zubehoerElem){
			
			// Überspringe wenn es der selbe Artikel ist.
			if($productId == $zubehoerIdx){ 
				continue;
			}
			
			/*echo "<p>Aktueller Artikel: $productId, Zubehör-Anwärter: $zubehoerIdx</p>";
			echo "<p><b>Systeme des aktuellen Artikels: </b>";
			var_dump($lampensystem);
			echo "</p><p><b>Systeme des aktuellen Zubehörs: </b>";
			var_dump($zubehoerElem['system']);
			echo "</p>";*/
			
			// Unterschiede zwischen den Systemen des Ausgangs-Artikels und denen des 'Zubehöranwärters'.
			$systemUnterschiede = array_merge(array_diff($lampensystem, $zubehoerElem['system']), array_diff($zubehoerElem['system'], $lampensystem));
			$anzahlSystemUnterschiede = count($systemUnterschiede);
			$keinSystemGleich = $anzahlSystemUnterschiede >= (count($lampensystem) + count($zubehoerElem['system']));
			$alleSystemeGleich = ($lampensystem == $zubehoerElem['system']);
			$mancheSystemeGleich = $anzahlSystemUnterschiede < (count($lampensystem) + count($zubehoerElem['system']));
			
			// Zuordnung der Zubehör-Artikel zu den Ausgangs-Artikeln. 
			$istZubehoer = FALSE;
			
			/* [Leuchte] 
				Zuordnungsregeln:
					1) Leuchte 		-> Wenn alle Systeme identisch sind. 
					2) Lampe 		-> Wenn sich die Sockel gleichen.
					1) Kabel 		-> Wenn mindestens 1 System identisch ist, Farbe egal. 
					1) Vorschlt.	-> Wenn mindestens 1 System identisch ist, Farbe egal. */
			if($hauptKategorie == 'Leuchte'){	
					
				// Wenn der Zubehör-Artikel eine Leuchte ist und sich alle Systeme gleichen.
				if($zubehoerElem['kategorie'] == 'Leuchte' && $alleSystemeGleich){
					if( ($farbEndung == '0' && $zubehoerElem['farbe'] == '0') ||
						($farbEndung == 'WW' && ($zubehoerElem['farbe'] == 'WW' || $zubehoerElem['farbe'] == '0')) ||
						($farbEndung == 'W' && ($zubehoerElem['farbe'] == 'W' || $zubehoerElem['farbe'] == '0')) ||
						($farbEndung == 'B' && ($zubehoerElem['farbe'] == 'B' || $zubehoerElem['farbe'] == '0'))){
						$istZubehoer = TRUE;
						echo "<p><b>Zubehör hinzugefügt mit Leuchte <-> Leuchte [Alle Systeme gleich]</b></p>";
						
					}
				} 
				// Wenn der Zubehör-Artikel eine Lampe ist .
				else if($zubehoerElem['kategorie'] == 'Lampe'){
					// @ Die Lampe wird der Leuchte zugeordnet, wenn die Sockel btw Fassungen übereinstimmen? ;)
				}	
				// Wenn der Zubehör-Artikel ein Vorschaltgerät ist und sich mindestens ein System gleicht.
				else if($zubehoerElem['kategorie'] == 'Vorschaltgerät' && $mancheSystemeGleich){
					$istZubehoer = TRUE;
				}
				// Wenn der Zubehör-Artikel ein Kabel ist und sich mindestens ein System gleicht:
				else if($zubehoerElem['kategorie'] == 'Kabel' && $mancheSystemeGleich){
					$istZubehoer = TRUE;
					echo "<p><b>Zubehör hinzugefügt mit Leuchte <-> Kabel [Mind. 1 System gleich]</b></p>";
				}
			}
			
			/* [Lampe] 
				Zuordnungsregeln:
					1) Leuchte 		-> Wenn mindestens 1 System identisch ist, Farbe egal. 
					2) Lampe 		-> Wenn sich die Sockel gleichen.
					1) Kabel 		-> Wenn mindestens 1 System identisch ist, Farbe egal. 
					1) Vorschlt.	-> Wenn mindestens 1 System identisch ist, Farbe egal. */
			else if($hauptKategorie == 'Lampe'){ 
		
				// Der Zubehör-Artikel ist ebenfalls eine Lampe und die Sockel sind identisch:
				if($zubehoerElem['kategorie'] == 'Lampe' && $sockel == $zubehoerElem['sockel']){
					//$istZubehoer = TRUE;
					//echo "<p><b>Zubehör hinzugefügt mit Lampe <-> Lampe [Sockel sind identisch]</b></p>";
					// None? ...
				}
				// @Todo: Der Zubehör-Artikel ist eine Leuchte und wird deshalb übersprungen.
				else if($zubehoerElem['kategorie'] == 'Leuchte'){
					// @ Todo... 

				}
				// Der Zubehör-Artikel ist eine Fassung und passt zum Sockel der Lampe:
				else if($zubehoerElem['kategorie'] == 'Fassung'){
					
					$sockelText = $product->getResource()->getAttribute('sockel')->getSource()->getOptionText($sockel);
					$fassungsTexte = $product->getResource()->getAttribute('fassung')->getSource()->getOptionText($zubehoerElem['fassungen']);
					
					if(is_array($fassungsTexte) === FALSE){
						if($fassungsTexte == $sockelText){
							echo "Found => $fassungsTexte == $sockelText";
							$istZubehoer = TRUE;
						}
					}else{
						foreach($fassungsTexte as $e){
							if($e == $sockelText){
								echo "Found => $e == $sockelText";
								$istZubehoer = TRUE;
							}
						}
					}
					
					echo "<p><b>Zubehör hinzugefügt mit Lampe <-> Fassung [Sockel passt zur Fassung]</b></p>";
				}
				// Der Zubehör-Artikel ist ein Vorschaltgerät und wird deshalb übersprungen.
				else if($zubehoerElem['kategorie'] == 'Vorschaltgerät'){
					//$istZubehoer = FALSE;
				}
				// Der Zubehör-Artikel ist eine Kabel und mindestens 1 System ist identisch.
				else if($zubehoerElem['kategorie'] == 'Kabel' && $mancheSystemeGleich == TRUE){
					$istZubehoer = TRUE;
				}
			}
			
			/* [Kabel] 
				Zuordnungsregeln:
					1) Leuchte 		-> Wenn mindestens 1 System identisch ist, Farbe egal. 
					2) Lampe 		-> Überspringen.
					1) Kabel 		-> Wenn mindestens 1 System identisch ist, Farbe egal. 
					1) Vorschlt.	-> Wenn mindestens 1 System identisch ist, Farbe egal. */
			else if($hauptKategorie == 'Kabel'){
				
				// Der Zubehör-Artikel ist eine Leuchte und mindestens 1 System ist identisch.
				if($zubehoerElem['kategorie'] == 'Leuchte' && $mancheSystemeGleich == TRUE){
					$istZubehoer = TRUE;
					echo "<p><b>Zubehör hinzugefügt für Kabel <-> Leuchte [Mindestens 1 System gleich]</b></p>";
				}
				// Der Zubehör-Artikel ist eine Lampe und wird deshalb übersprungen.
				else if($zubehoerElem['kategorie'] == 'Lampe'){
					$istZubehoer = FALSE;
					echo "<p><b>Zubehör NICHT hinzugefügt für Kabel <-> Lampe</b></p>";
				}
				// Der Zubehör-Artikel ist ebenfalls ein Kabel und mindestens 1 System ist identisch.
				else if($zubehoerElem['kategorie'] == 'Kabel' && $mancheSystemeGleich == TRUE){
					$istZubehoer = TRUE;
					echo "<p><b>Zubehör hinzugefügt für Kabel <-> Kabel [Mindestens 1 System gleich]</b></p>";
				}
				// Der Zubehör-Artikel ist ein Vorschaltgerät und mindestens 1 System ist identisch.
				else if($zubehoerElem['kategorie'] == 'Vorschaltgerät' && $mancheSystemeGleich == TRUE){
					$istZubehoer = TRUE;
					echo "<p><b>Zubehör hinzugefügt für Kabel <-> Vorschaltgerät [Mindestens 1 System gleich]</b></p>";
				}
			}
			
			/* [Vorschaltgerät] 
				Zuordnungsregeln:
					1) Leuchte 		-> Wenn mindestens 1 System identisch ist, Farbe egal. 
					2) Lampe 		-> Überspringen.
					1) Kabel 		-> Wenn mindestens 1 System identisch ist, Farbe egal. 
					1) Vorschlt.	-> Wenn mindestens 1 System identisch ist, Farbe egal. */
			else if($hauptKategorie == 'Vorschaltgerät'){
				
				// Der Zubehör-Artikel ist eine Leuchte und mindestens 1 System ist identisch.
				if($zubehoerElem['kategorie'] == 'Leuchte' && $mancheSystemeGleich == TRUE){
					$istZubehoer = TRUE;
					echo "<p><b>Zubehör hinzugefügt für Vorschaltgerät <-> Leuchte [Mind. 1 System gleich]</b></p>";
				}
				// Der Zubehör-Artikel ist eine Lampe und wird deshalb übersprungen.
				else if($zubehoerElem['kategorie'] == 'Lampe' && $mancheSystemeGleich == TRUE){
					$istZubehoer = FALSE;
				}
				// Der Zubehör-Artikel ist ein Kabel und mindestens 1 System ist gleich.
				else if($zubehoerElem['kategorie'] == 'kabel'){
					
				}
				// Der Zubehör-Artikel ist ein Vorschaltgerät und alle Systeme sind identisch.
				else if($zubehoerElem['kategorie'] == 'Vorschaltgerät' && $alleSystemeGleich == TRUE){
					$istZubehoer = TRUE;
				}
			}
			/* [Fassung] 
				Zuordnungsregeln: */
			else if($hauptKategorie == 'Fassungen'){
				// ...
			}
			
			// Wenn sich der Artikel als passendes Zubehör herausstellt, füge ihm den Zubehör-Daten hinzu.
			if($istZubehoer === TRUE){
				$zubehoerDaten[$zubehoerIdx] = array('position' => $zubehoerElem['position']);
			}
			
			/*echo "<p><b>Unterschiede: </b>";
			var_dump($systemUnterschiede);
			echo "<p><b>Kein System gleich: </b>";
			var_dump($keinSystemGleich);
			echo "<p><b>Alle Systeme gleich: </b>";
			var_dump($alleSystemeGleich);
			echo "<p><b>Manche Systeme gleich: </b>";
			var_dump($mancheSystemeGleich);
			echo "</p><p>Sockel gefunden: ";
			var_dump($sockel);
			echo "</p><p>Fassungen gefunden: "; 
			var_dump($fassungen);
			echo "</p><p>Wurde zugeordnet => ";
			var_dump($istZubehoer);
			echo "</p><hr>";*/
		}
		
		// @Todo: Um Änderungen zu erkennen, speichere erst alle ID's der geänderten Zubehörartikel 
		// zwischen und vergleiche diese dann mit den aktuellen eingetragenen Zubehör-Artikeln.
		// Nur wenn diese sich unterscheiden, führe die folgende Funktion zum speichern aus: 
		Mage::getResourceModel('catalog/product_link')->saveProductLinks(
			$product, $zubehoerDaten, Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED
		);
		
		echo "</fieldset>";
	}
	
	// ------------------------------------------------------------------------------------------------------------------	
	
	// Measure time.
	$time = round((microtime(TRUE) - $start), 2);
	echo "<hr>Success! Script took <b>$time</b> seconds to execute!<hr>";
?>
