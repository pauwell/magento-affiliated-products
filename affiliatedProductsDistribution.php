<?php
	// xxxxx = Sensitive data removed.

	// Introduce script.
	echo "<meta charset='utf-8'>";
	echo "Script '".__FILE__."' running...<hr>";
	
	// Prepare magento for script execution.
	ini_set("memory_limit","xxxxx");
	date_default_timezone_set("xxxxx");
	define('MAGENTO_ROOT', 'xxxxx');
	$compilerConfig = MAGENTO_ROOT . '/xxxxx';
	if(file_exists($compilerConfig)){ include $compilerConfig; }
	$mageFilename = MAGENTO_ROOT . 'xxxxx';
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
		
		// Ist kein Standardartikel.
		if(substr($_product->getSku(), 0, 3) == "SET" || strpos($_product->getSku(), 'x') !== FALSE || strpos($_product->getSku(), 'V') !== FALSE){
			continue;
		}
		
		$product = Mage::getModel('catalog/product')->setStoreId(0)->load($productId);	// Richtiger Artikel.
		
		// Eckdaten.
		$kabelsysteme = $product->getKabelsystem();						// 2-Adrig, Nemo, Spider, UK-Stecker.
		$lampensystem = $product->getAttributeText('lampensysteme'); 	// Atria, Badu, Bodes, Cus, Tipo.
		$sonstigeZuordnungen = $product->getLampensystem();				// Distanzstück, Erdspießleuchte.
		
		$farbEndung = end(explode('-', $product->getSku())); 							// Farbendung auslesen (W, WW, B).
		$farbEndung = $farbEndung != 'W' && $farbEndung != 'WW' && $farbEndung != 'B' ? '0' : $farbEndung;
		$sockel = $product->getSockel();												// Lampensockel (E27, GU10).
		$fassungen = $product->getFassung();											// Lampenfassung (E27, GU10)
		$anschluss = $product->getAnschluss();											// Anschluss (2 Kabeladern, 3 Kabeladern)
		$stecker = $product->getStecker();												// (Eurostecker, Atom)
		$betriebsspannung = $product->getBetriebsspannung();							// 230V AC, 12V DC
		$ausgangsspannung = $product->getAusgangsspannung();							// 24V DC, 12V DC
		$austauschbaresLeuchtmittel = $product->getAttributeText('austauschbares_leuchtmittel') === 'Ja' ? TRUE : FALSE; // Ist Austauschbar?
		$isZubehoerBerechtigt = $product->getAttributeText('zubehoer_berechtigt');		// Ist das Produkt ein Zubehör-Artikel.
		if($isZubehoerBerechtigt !== 'Yes') continue;									// Überspringen falls kein Zubehör-Artikel.
		
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
					if(is_numeric($position) === FALSE){
						//echo "<b>Changed Position from $position to ";
						$position = strtok($lichtstrom, '/');
						//echo "$position</b>";
					}
				}else if(stripos("-".$katElem->getName(), "kabel")){
					$hauptKategorie = "Kabel";
					$position = $kabelLaenge;
				}else if(stripos("-".$katElem->getName(), "vorschalt")){
					$hauptKategorie = "Vorschaltgerät";
					$position = $ausgangsleistung;
				}else if(stripos("-".$katElem->getName(), "fassung")){
					$hauptKategorie = "Fassung";
					$position = $ausgangsleistung; // @ Andere Positionierung?
				}else if(stripos("-".$katElem->getName(), "muffe")){
					$hauptKategorie = "Muffe";
					$position = $ausgangsleistung; // @ Andere Positionierung?
				}else if(stripos("-".$katElem->getName(), "landing")){
					$hauptKategorie = "Landingpages";
				}else if(stripos("-", $katElem->getName(), "sonstiges")){
					$hauptKategorie = "Sonstiges";	// @ Andere Positionierung?
				}
			}
		}
		if(empty($hauptKategorie)){ $hauptKategorie = "Sonstiges"; }
		
		// Überspringe den Artikel wenn er in der Kategorie Landingpages ist oder eine falsche Artikelnummer hat.
		if($hauptKategorie == "Landingpages" || substr($product->getSku(), 0, 1) == 'V' ){
			continue; 
		}
		
		// Konvertiere die Werte von der Id zum richtigen Wert.
		$kabelsystemeTexte = $product->getResource()->getAttribute('kabelsystem')->getSource()->getOptionText($kabelsysteme);
		$sonstigeZuordnungenTexte = $product->getResource()->getAttribute('lampensystem')->getSource()->getOptionText($sonstigeZuordnungen);
		$sockelText = $product->getResource()->getAttribute('sockel')->getSource()->getOptionText($sockel);
		$fassungenTexte = $product->getResource()->getAttribute('fassung')->getSource()->getOptionText($fassungen);
		$anschlussText = $product->getResource()->getAttribute('anschluss')->getSource()->getOptionText($anschluss);
		$betriebsspannungText = $product->getResource()->getAttribute('betriebsspannung')->getSource()->getOptionText($betriebsspannung);
		$ausgangsspannungText = $product->getResource()->getAttribute('ausgangsspannung')->getSource()->getOptionText($ausgangsspannung);
		$steckerText = $product->getResource()->getAttribute('stecker')->getSource()->getOptionText($stecker);
		 
		// Konvertiere zu array auch wenn es nur ein Element gibt.
		if(is_array($kabelsystemeTexte) == FALSE){ $kabelsystemeTexte = Array($kabelsystemeTexte); }
		if(is_array($sonstigeZuordnungenTexte) == FALSE){ $sonstigeZuordnungenTexte = Array($sonstigeZuordnungenTexte); }
		if(is_array($fassungenTexte) == FALSE){ $fassungenTexte = Array($fassungenTexte); }
					
		if(strpos($betriebsspannungText, '(') != FALSE){
			$tmp = explode('(', $betriebsspannungText);
			$betriebsspannungText = ($tmp[0]).trim();
		}
		if(strpos($ausgangsspannungText, '(') != FALSE){
			$tmp = explode('(', $ausgangsspannungText);
			$ausgangsspannungText = ($tmp[0]).trim();
		}
					
		// Speichere die Werte in den neuen Zubehör-Artikeln.
		$neueZubehoerArtikel[$productId] = Array(
			"sku" => $product->getSku(),				// LC-EL-000-XX
			"lampensystem" => $lampensystem,			// Atria, Badu, Bodes, Cus, Tipo
			"kabelsysteme" => $kabelsystemeTexte,		// 2-Adrig, Nemo, Spider, UK-Stecker.
			"sonstigeZuordnungen" => $sonstigeZuordnungenTexte,// Distanzstück, Erdspießleuchte.
			"position" => $position,					// Position innerhalb des Zubehör Registers.
			"farbe" => $farbEndung,						// Weiß, Warmweiß, Blau, Normal
			"sockel" => $sockelText,					// E27, GU10
			"fassungen" => $fassungenTexte,				// E27, GU10
			"anschluss" => $anschlussText, 				// 2 Adrig, 3 Adrig
			"betriebsspannung" => $betriebsspannungText,// 230V AC, 12V DC
			"ausgangsspannung" => $ausgangsspannungText,// 24V DC, 12V DC
			"austauschbaresLeuchtmittel" => $austauschbaresLeuchtmittel,	// Austauschbar?
			"stecker" => $steckerText, 						// Eurostecker, Atom
			"kategorie" => $hauptKategorie				// Kategorie (Leuchte, Zubehör, Lampe etc)
		);
		
		// Debug print.
		echo "\n\n<h3>Artikel ".$product->getSku()." mit ID [$productId] als Zubehör hinzugefügt</h3>\n";
		echo "<pre>".print_r($neueZubehoerArtikel[$productId], 1)."</pre>";
		echo "<hr>";
	}
	
	// -- Alle Artikel durchgehen und die gesammelten Positionen als Zubehör hinzufügen ---------------------------------
	foreach($collection as $productIndex => $_product){
		
		// Artikel aus der DB holen.
		if($_product === "") continue;
		$productId = $_product->getId(); // Richtige ID.
		
		// Ist kein Standardartikel.
		if(substr($_product->getSku(), 0, 3) == "SET" || strpos($_product->getSku(), 'x') !== FALSE || strpos($_product->getSku(), 'V') !== FALSE){
			continue;
		}

		$product = Mage::getModel('catalog/product')->setStoreId(0)->load($productId);	// Richtiger Artikel.
		
		// Eckdaten.
		$kabelsysteme = $product->getKabelsystem();						// 2-Adrig, Nemo, Spider, UK-Stecker.
		$lampensystem = $product->getAttributeText('lampensysteme'); 	// Atria, Badu, Bodes, Cus, Tipo.
		$sonstigeZuordnungen = $product->getLampensystem();				// Distanzstück, Erdspießleuchte.

		$farbEndung = end(explode('-', $product->getSku())); 							// Farbendung auslesen (W, WW, B).
		$farbEndung = $farbEndung != 'W' && $farbEndung != 'WW' && $farbEndung != 'B' ? '0' : $farbEndung; 
		$sockel = $product->getSockel();												// Lampensockel (E27, GU10).
		$fassungen = $product->getFassung();											// Lampenfassungen (E27, GU10).
		$anschluss = $product->getAnschluss();											// Anschluss (2 Kabeladern, 3 Kabeladern)
		$stecker = $product->getStecker();												// (Eurostecker, Atom)
		$betriebsspannung = $product->getBetriebsspannung();							// 230V AC, 12V DC
		$ausgangsspannung = $product->getAusgangsspannung();							// 24V DC, 12V DC
		$austauschbaresLeuchtmittel = $product->getAttributeText('austauschbares_leuchtmittel') === 'Ja' ? TRUE : FALSE; // Ist Austauschbar?
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
				}else if(stripos("-".$katElem->getName(), "muffe")){
					$hauptKategorie = "Muffe";
					//$position = $ausgangsleistung; // @ Andere Positionierung?
				}else if(stripos("-".$katElem->getName(), "fassung")){
					$hauptKategorie = "Fassung";
					//$position = $ausgangsleistung; // @ Andere Positionierung?
				}
			}
		}
		if(empty($hauptKategorie)){ $hauptKategorie = "Sonstiges"; }
		
		// Konvertiere die Werte von der Id zum richtigen Wert.
		$kabelsystemeTexte = $product->getResource()->getAttribute('kabelsystem')->getSource()->getOptionText($kabelsysteme);
		$sonstigeZuordnungenTexte = $product->getResource()->getAttribute('lampensystem')->getSource()->getOptionText($sonstigeZuordnungen);
		$sockelText = $product->getResource()->getAttribute('sockel')->getSource()->getOptionText($sockel);
		$fassungenTexte = $product->getResource()->getAttribute('fassung')->getSource()->getOptionText($fassungen);
		$anschlussText = $product->getResource()->getAttribute('anschluss')->getSource()->getOptionText($anschluss);
		$betriebsspannungText = $product->getResource()->getAttribute('betriebsspannung')->getSource()->getOptionText($betriebsspannung);
		$ausgangsspannungText = $product->getResource()->getAttribute('ausgangsspannung')->getSource()->getOptionText($ausgangsspannung);
		$steckerText = $product->getResource()->getAttribute('stecker')->getSource()->getOptionText($stecker);

		// Konvertiere zu array auch wenn es nur ein Element gibt.
		if(is_array($kabelsystemeTexte) == FALSE){ $kabelsystemeTexte = Array($kabelsystemeTexte); }
		if(is_array($sonstigeZuordnungenTexte) == FALSE){ $sonstigeZuordnungenTexte = Array($sonstigeZuordnungenTexte); }
		if(is_array($fassungenTexte) == FALSE){ $fassungenTexte = Array($fassungenTexte); }
		
		if(strpos($betriebsspannungText, '(') != FALSE){
			$tmp = explode('(', $betriebsspannungText);
			$betriebsspannungText = ($tmp[0]).trim();
		}
		if(strpos($ausgangsspannungText, '(') != FALSE){
			$tmp = explode('(', $ausgangsspannungText);
			$ausgangsspannungText = ($tmp[0]).trim();
		}
		
		echo "Zubehörzuordnung für Artikel ".$product->getSku()." [$productId]!\tVorgang zu ".intval($productIndex / 7072 * 100) ."% abgeschlossen!\n";
		
		
		// Alle Zubehörartikel durchgehen.
		$zubehoerDaten = array();
		foreach($neueZubehoerArtikel as $zubehoerIdx => $zubehoerElem){
			
			echo "hi";
			var_dump($productId);
			var_dump($zubehoerIdx);

			// Überspringe wenn es der selbe Artikel ist.
			if($productId == $zubehoerIdx){ 
				continue;
			}

			// Auswertungen vorab: Unterschiede zwischen den Systemen des Ausgangs-Artikels und denen des 'Zubehöranwärters'.
			$lampensystemGleich = trim($lampensystem) != "" && trim($zubehoerElem['lampensystem']) != "" && $lampensystem == $zubehoerElem['lampensystem'];		
			
			// @ Das folgende echo wird einfach ignoriert. Gott weiß warum, php suckt.
			echo "Vergleiche $lampensystem mit " . $zubehoerElem['lampensystem'] . " => " . $lampensystemGleich == TRUE ? "true" : "false" . "!\n";
			$kabelsystemeUeberschneidungen = array_intersect($kabelsystemeTexte, $zubehoerElem['kabelsysteme']);
			$fassungenUeberschneidungen = array_intersect($fassungenTexte, $zubehoerElem['fassungen']);
			$sonstigeZuordnungenUeberschneidungen = array_intersect($sonstigeZuordnungenTexte, $zubehoerElem['sonstigeZuordnungen']);
			$sockelPasstZuFassungen = in_array($fassungenTexte, $zubehoerElem['sockel']) || in_array($zubehoerElem['fassungen'], $sockel);	
			$kabelsystemePassend = count($kabelsystemeUeberschneidungen) > 0;
			
			// Debug print:
			echo "Beginne vergleich zwischen => \nAusgangsartikel [$productId] ".$product->getSku()." mit Zubehöranwärter [$zubehoerIdx]".$zubehoerElem['sku']."\n";
			echo "Lampensysteme: $lampensystem => ". $zubehoerElem['lampensystem']."\n";
			echo "Kabelsysteme:\n\t\t[". join(", ", $kabelsystemeTexte) . "]\n\t\t[" . join(", ", $zubehoerElem['kabelsysteme'])."]\n";
			echo "Sonstige Zuordnungen:\n\t\t[". join(", ", $sonstigeZuordnungenTexte) . "]\n\t\t[" . join(", ", $zubehoerElem['sonstigeZuordnungen'])."]\n";
			echo "Position: $position => ". $zubehoerElem['position'] . "\n";
			echo "Farbe: $farbEndung => ". $zubehoerElem['farbe'] . "\n";
			echo "Sockel: $sockel => ". $zubehoerElem['sockel'] . "\n";
			echo "Fassungen:\n\t\t[". join(", ", $fassungenTexte) . "]\n\t\t[" . join(", ", $zubehoerElem['fassungen'])."]\n";
			echo "Anschluss: $anschlussText => ". $zubehoerElem['anschluss'] . "\n";
			echo "Betriebsspannung: $betriebsspannungText => ". $zubehoerElem['betriebsspannung'] . "\n";
			echo "Ausgangsspannung: $ausgangsspannungText => ". $zubehoerElem['ausgangsspannung'] . "\n";
			echo "Austauschbares Leuchtmittel: ". ($austauschbaresLeuchtmittel ? 'True' : 'False') ."=> ". ($zubehoerElem['austauschbaresLeuchtmittel'] ? 'True' : 'False') . "\n";
			echo "Stecker: $steckerText => ". $zubehoerElem['stecker'] . "\n";
			echo "Kategorie: $hauptKategorie => ". $zubehoerElem['kategorie'] . "\n";
			echo "\n----------------------------------------------------------------------------------------\n";
	
			// Zuordnung der Zubehör-Artikel zu den Ausgangs-Artikeln. 
			$istZubehoer = FALSE;
			
			// -------------------------------------------------------------------------------------------------------------------
			// - Artikel: Leuchte ✔
			// -------------------------------------------------------------------------------------------------------------------
			if($hauptKategorie == 'Leuchte'){	
					echo "GOT: FASSUNG";
				// Zubehör: Leuchte. ✔
				if($zubehoerElem['kategorie'] == 'Leuchte'){ 	
				
					if(
						$austauschbaresLeuchtmittel === TRUE && 
						$zubehoerElem['austauschbaresLeuchtmittel'] === TRUE &&
						($lampensystemGleich === TRUE || $sockelPasstZuFassungen === TRUE || count($fassungenUeberschneidungen) > 0 || count($sonstigeZuordnungenUeberschneidungen) > 0) &&
						(
							($farbEndung == '0' && $zubehoerElem['farbe'] == '0') ||
							($farbEndung == 'WW' && ($zubehoerElem['farbe'] == 'WW' || $zubehoerElem['farbe'] == '0')) ||
							($farbEndung == 'W' && ($zubehoerElem['farbe'] == 'W' || $zubehoerElem['farbe'] == '0')) ||
							($farbEndung == 'B' && ($zubehoerElem['farbe'] == 'B' || $zubehoerElem['farbe'] == '0'))
						)){
						echo "Ist Zubehör, weil der Zubehör-Artikel zur Leuchte mit austauschbarem Leuchtmittel eine Leuchte mit
								austauschbarem Leuchtmittel ist, das System gleich ist und die Lichtfarbe passt!";
						$istZubehoer = TRUE; 
					}
				} 
				// Zubehör: Lampe. ✔
				else if($zubehoerElem['kategorie'] == 'Lampe'){
					if($sockelPasstZuFassungen == TRUE && $austauschbaresLeuchtmittel == TRUE){
						echo "Ist Zubehör, weil der Zubehör-Artikel zur Leuchte mit austauschbarem Leuchtmittel eine Lampe ist und der Sockel auf eine der Fassungen passt!";
						$istZubehoer = TRUE;
					}
				}	
				// Zubehör: Vorschaltgerät. ✔
				else if($zubehoerElem['kategorie'] == 'Vorschaltgerät'){
					if($betriebsspannungText == $zubehoerElem['ausgangsspannung']){					
						if($kabelsystemePassend == TRUE){
							$istZubehoer = TRUE;
							echo "Ist Zubehör, weil der Zubehör-Artikel zur Leuchte ein Vorschaltgerät ist, die Ausgangsspannung zur Betriebsspannung passt und sich das Kabelsystem deckt!";
						}else{
							if(in_array('2-Adrig', $kabelsystemeTexte)){
								if(in_array('2-Adrig', $zubehoerElem['kabelsysteme']) || in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
									echo "Ist Zubehör, weil der Zubehör-Artikel zur Leuchte ein Vorschaltgerät ist, die Ausgangsspannung zur Betriebsspannung passt und der Artikel 2-Adrig und das Zubehör 2/3-Adrig!";
									$istZubehoer = TRUE;
								}
							}else if(in_array('3-Adrig', $kabelsystemeTexte)){
								if(in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
									echo "Ist Zubehör, weil der Zubehör-Artikel zur Leuchte ein Vorschaltgerät ist, die Ausgangsspannung zur Betriebsspannung passt und der Artikel 3-Adrig und das Zubehör 3-Adrig!";
									$istZubehoer = TRUE;
								}
							}else{
								echo "KEIN Zubehör, weil das Vorschaltgerät nicht per Spannung oder System zur Leuchte passt.";
							}
						}
					}
				}
				// Zubehör: Kabel. ✔
				else if($zubehoerElem['kategorie'] == 'Kabel'){ 
					if($kabelsystemePassend == TRUE){
						echo "Ist Zubehör, weil der Zubehör-Artikel zur Leuchte ein Kabel ist und sich das Kabelsystem deckt!";
						$istZubehoer = TRUE;
					}else{
						if(in_array('2-Adrig', $kabelsystemeTexte)){
							if(in_array('2-Adrig', $zubehoerElem['kabelsysteme']) || in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
								echo "Ist Zubehör, weil der Zubehör-Artikel zur Leuchte ein Kabel ist und der Artikel 2-Adrig und das Zubehör 2/3-Adrig!";
								$istZubehoer = TRUE;
							}
						}else if(in_array('3-Adrig', $kabelsystemeTexte)){
							if(in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
								echo "Ist Zubehör, weil der Zubehör-Artikel zur Leuchte ein Kabel ist und der Artikel 3-Adrig und das Zubehör 3-Adrig!";
								$istZubehoer = TRUE;
							}
						}else{
							echo "KEIN Zubehör, weil weder das Kabelsystem noch die Adrigkeit zur Leuchte passt!";
						}
					}
				}
				// Zubehör: Muffe, Fassung. ✔
				else if($zubehoerElem['kategorie'] == 'Muffe' || $zubehoerElem['kategorie'] == 'Fassung'){ 
					echo "KEIN Zubehör, weil eine Muffe oder Fassung nicht zur Leuchte passt!";
					$istZubehoer = FALSE;
				}
			}
			 
			// -------------------------------------------------------------------------------------------------------------------
			// - Lampe (Leuchtmittel) 
			// -------------------------------------------------------------------------------------------------------------------
			else if($hauptKategorie == 'Lampe'){ 
				
				// Zubehör: Leuchte.
				if($zubehoerElem['kategorie'] == 'Leuchte' && $zubehoerElem['austauschbaresLeuchtmittel'] == TRUE && $sockelPasstZuFassungen == TRUE){  
					echo "Ist Zubehör, weil der Zubehör-Artikel zur Lampe eine Leuchte mit austauschbarem Leuchtmittel ist und der Sockel zur Fassung passt!";	
					$istZubehoer = TRUE;
				}
				// Zubehör: Vorschaltgerät.
				else if($zubehoerElem['kategorie'] == 'Vorschaltgerät'){
					if($lampensystemGleich == TRUE){
						echo "Ist Zubehör, weil der Zubehör-Artikel zur Lampe ein Vorschaltgerät ist und sich das Lampensystem gleicht!";
						$istZubehoer = TRUE;
					}else if($lampensystem.trim() == "" || $zubehoerElem['lampensystem'].trim() == ""){
						if(in_array('2-Adrig', $kabelsystemeTexte)){
							if(in_array('2-Adrig', $zubehoerElem['kabelsysteme']) || in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
								echo "Ist Zubehör, weil der Zubehör-Artikel zur Lampe ein Vorschaltgerät ist, beide kein System haben und der Artikel 2-Adrig und das Zubehör 2/3-Adrig!";
								$istZubehoer = TRUE;
							}
						}else if(in_array('3-Adrig', $kabelsystemeTexte)){
							if(in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
								echo "Ist Zubehör, weil der Zubehör-Artikel zur Lampe ein Vorschaltgerät ist, beide kein System haben und der Artikel 3-Adrig und das Zubehör 3-Adrig!";
								$istZubehoer = TRUE;
							}
						}
					}
				}
				// Zubehör: Fassung.
				else if($zubehoerElem['kategorie'] == 'Fassung' && $sockelPasstZuFassungen == TRUE){ 
					echo "Ist Zubehör, weil der Zubehör-Artikel zur Lampe eine Fassung ist und der Sockel zur Fassung passt!";	
					$istZubehoer = TRUE;
				}
				// Zubehör: Lampe, Kabel, Muffe. 
				else if($zubehoerElem['kategorie'] == 'Lampe' || $zubehoerElem['kategorie'] == 'Kabel' || $zubehoerElem['kategorie'] == 'Muffe'){  // ✔
					// Skip.
					echo "KEIN Zubehör, weil Lampen, Kabel und Muffen nicht zu Lampen gehören!";
					$istZubehoer = FALSE;
				}
			}
			
			// -------------------------------------------------------------------------------------------------------------------
			// - Kabel 
			// -------------------------------------------------------------------------------------------------------------------
			else if($hauptKategorie == 'Kabel'){
				// Zubehör: Leuchte.
				if($zubehoerElem['kategorie'] == 'Leuchte'){ // ✔
				
					// @Stecker decken!!!!
					if($lampensystemGleich == TRUE){
						echo "Ist Zubehör, weil der Zubehör-Artikel zum Kabel eine Leuchte ist und sich das System gleicht!";
						$istZubehoer = TRUE;
					}else if($lampensystem.trim() == "" || $zubehoerElem['lampensystem'].trim() == ""){
						if(in_array('2-Adrig', $kabelsystemeTexte) || in_array('3-Adrig', $kabelsystemeTexte)){
							if(in_array('2-Adrig', $zubehoerElem['kabelsysteme']) || in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
								echo "Ist Zubehör, weil der Zubehör-Artikel zum Kabel ein Kabel ist und sich das System und die Adrigkeit gleicht!";
								$istZubehoer = TRUE;
							}
						}
					}
					
					
				}
				// Zubehör: Kabel.
				else if($zubehoerElem['kategorie'] == 'Kabel'){  // ✔
					if($lampensystemGleich == TRUE && in_array('2-Adrig', $kabelsystemeTexte) || in_array('3-Adrig', $kabelsystemeTexte)){
						if(in_array('2-Adrig', $zubehoerElem['kabelsysteme']) || in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
							echo "Ist Zubehör, weil der Zubehör-Artikel zum Kabel ein Kabel ist und sich das System und die Adrigkeit gleicht!";
							$istZubehoer = TRUE;
						}
					}
				}
				// Zubehör: Vorschaltgerät.
				else if($zubehoerElem['kategorie'] == 'Vorschaltgerät' ){ // ✔
					// Lampensystem stimmt überein.
					if($lampensystemGleich == TRUE){
						echo "Ist Zubehör, weil der Zubehör-Artikel zum Kabel ein Vorschaltgerät ist und sich das System gleicht!";
						$istZubehoer = TRUE;
					}
				}
				// Zubehör: Muffen.
				else if($zubehoerElem['kategorie'] == 'Muffe'){
					if($lampensystemGleich == TRUE){
						$istZubehoer = TRUE;
					}else if($lampensystem.trim() == "" && $zubehoerElem['lampensystem'].trim() == ""){
						// An der Stelle hier eig noch den Durchmesser abfragen, nur wenn dieser passt ist es Zubehör.
						// Gutes Beispiel: LC-SS-136 [380] ~> `Optik & Maße` -> `Durchmesser` fehlt.
						// Kabeldurchmesser existiert als Wert.	
						$istZubehoer = FALSE;
					}
				}
				// Zubehör: Lampe.
				else if($zubehoerElem['kategorie'] == 'Lampe' || $zubehoerElem['kategorie'] == 'Fassung'){ // ✔
					$istZubehoer = FALSE;
					echo "KEIN Zubehör, weil eine Lampe keinem Kabel zugeordnet wird!";
				}
			}
			
			// -------------------------------------------------------------------------------------------------------------------
			// - Vorschaltgerät 
			// -------------------------------------------------------------------------------------------------------------------
			else if($hauptKategorie == 'Vorschaltgerät'){
				
				// Zubehör: Leuchte.
				if($zubehoerElem['kategorie'] == 'Leuchte' && $lampensystemGleich == TRUE){ // ✔
					$istZubehoer = TRUE;
				}
				// Zubehör: Kabel.
				else if($zubehoerElem['kategorie'] == 'kabel'){  // ✔
					if($lampensystemGleich == TRUE && in_array('2-Adrig', $kabelsystemeTexte) || in_array('3-Adrig', $kabelsystemeTexte)){
						if(in_array('2-Adrig', $zubehoerElem['kabelsysteme']) || in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
							echo "Ist Zubehör, weil der Zubehör-Artikel zum Kabel ein Kabel ist und sich das System und die Adrigkeit gleicht!";
							$istZubehoer = TRUE;
						}
					}
				}
				// Zubehör: Vorschaltgerät.
				else if($zubehoerElem['kategorie'] == 'Vorschaltgerät' && $lampensystemGleich == TRUE){ // ✔
					echo "Der Zubehör-Artikel zum Vorschaltgerät";
					$istZubehoer = TRUE;
				}
				// // Zubehör: Muffe.
				else if($zubehoerElem['kategorie'] == 'Muffe'){
					// Wenn sich das System gleicht. Wenn kein System gewählt, dann Durchmesser abgleichen?
				}
				// Der Zubehör-Artikel ist eine Lampe, Muffe oder Fassung und wird deshalb übersprungen.
				else if($zubehoerElem['kategorie'] == 'Lampe' || $zubehoerElem['kategorie'] == 'Fassung' ){ // ✔
					echo "Der Zubehör-Artikel zum Vorschaltgerät ist eine Lampe, Muffe oder Fassung und wird deshalb übersprungen!";
					$istZubehoer = FALSE;
				}
				
			}
			// -------------------------------------------------------------------------------------------------------------------
			// - Muffe 
			// -------------------------------------------------------------------------------------------------------------------
			else if($hauptKategorie == 'Muffe'){
				
				// Der Zubehör-Artikel ist ein Kabel und mindestens 1 System ist gleich.
				if($zubehoerElem['kategorie'] == 'Kabel' && $lampensystemGleich == TRUE){ // ✔
					echo "Ist Zubehör, weil zur Muffe ein Kabel gehört, wenn sich das System gleicht!";
					$istZubehoer = TRUE;
				}else{
					echo "Kein Zubehör, weil zur Muffe keine Leuchten, Lampen, Vorschaltgeräte andere Muffen oder Fassungen gehören!";
					$istZubehoer = FALSE;
				}
			}
			// -------------------------------------------------------------------------------------------------------------------
			// - Fassung 
			// -------------------------------------------------------------------------------------------------------------------
			else if($hauptKategorie == 'Fassung'){
				echo "GOT: FASSUNG";
				// Der Zubehör-Artikel ist eine Fassung und die Fassung ist identisch mit der des Artikels.
				if($zubehoerElem['kategorie'] == "Fassung"){ // ✔
				
					// Kontrolliere LC-EL-087. Dieser bekommt Zubehör reingeshoved obwohl beide KEIN austrauschbares Leuchtmittel haben.
					if(	($lampensystemGleich === TRUE || $sockelPasstZuFassungen === TRUE) && 
						$austauschbaresLeuchtmittel === TRUE && $zubehoerElem['austauschbaresLeuchtmittel'] === TRUE &&
						($farbEndung == '0' && $zubehoerElem['farbe'] == '0') ||
						($farbEndung == 'WW' && ($zubehoerElem['farbe'] == 'WW' || $zubehoerElem['farbe'] == '0')) ||
						($farbEndung == 'W' && ($zubehoerElem['farbe'] == 'W' || $zubehoerElem['farbe'] == '0')) ||
						($farbEndung == 'B' && ($zubehoerElem['farbe'] == 'B' || $zubehoerElem['farbe'] == '0')) ){
						echo "Ist Zubehör, weil der Zubehör-Artikel zur Leuchte mit austauschbarem Leuchtmittel eine Leuchte mit
								austauschbarem Leuchtmittel ist, das System gleich ist und die Lichtfarbe passt!";
							$istZubehoer = TRUE;			
					}

				}
				// Der Zubehör-Artikel ist ein Kabel und das System gleich ist.
				else if($zubehoerElem['kategorie'] == "Kabel" && $lampensystemGleich == TRUE){ // ✔
					echo "Ist Zubehör, weil zur Fassung ein Kabel gehört, wenn sich das System der beiden gleicht!";
					$istZubehoer = TRUE;
				}
				// Der Zubehör-Artikel ist ein Vorschaltgerät, Leuchte, Lampe oder Muffe.
				else if($zubehoerElem['kategorie'] == "Lampe" || 
						$zubehoerElem['kategorie'] == "Leuchte" || 
						$zubehoerElem['kategorie'] == "Muffe" || 
						$zubehoerElem['kategorie'] == "Vorschaltgerät"){ // ✔
					echo "Kein Zubehör, weil zum Kabel keine Leuchten, Lampen, Vorschaltgeräte oder Muffen gehören!";
					$istZubehoer = FALSE;
				}
			}
			// -------------------------------------------------------------------------------------------------------------------
			// - Sonstiges 
			// -------------------------------------------------------------------------------------------------------------------
			else{
				// Das folgende gilt momentan für Erdspieße: 
				if(count($sonstigeZuordnungenUeberschneidungen) > 0){
					echo "Ist Zubehör, weil der Artikel zur Kategorie 'Sonstiges' gehört und sich mindestens eine Zuordnung deckt!";
					$istZubehoer = TRUE;
				} 
			}
			 
			// Wenn sich der Artikel als passendes Zubehör herausstellt, füge ihm den Zubehör-Daten hinzu.
			if($istZubehoer == TRUE){
				$zubehoerDaten[$zubehoerIdx] = array('position' => $zubehoerElem['position']);
			}
		}
		
		
		// @Todo: Um Änderungen zu erkennen, speichere erst alle ID's der geänderten Zubehörartikel 
		// zwischen und vergleiche diese dann mit den aktuellen eingetragenen Zubehör-Artikeln.
		// Nur wenn diese sich unterscheiden, führe die folgende Funktion zum speichern aus: 
		Mage::getResourceModel('catalog/product_link')->saveProductLinks(
			$product, $zubehoerDaten, Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED
		);
	}
	
	// ------------------------------------------------------------------------------------------------------------------	
	
	// Measure time.
	$time = round((microtime(TRUE) - $start), 2);
	echo "<hr>Success! Script took <b>$time</b> seconds to execute!<hr>";
?>
