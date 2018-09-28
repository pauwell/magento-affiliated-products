<?php
	// Introduce script.
	echo "<meta charset='utf-8'>";
	echo "Script '".__FILE__."' running...<hr>";
	
	// Prepare magento for script execution.
	// xxx
	
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
		
		$sku = $_product->getSku();														// SKU
		$farbEndung = end(explode('-', $product->getSku())); 							// Farbendung auslesen (W, WW, B).
		$farbEndung = $farbEndung != 'W' && $farbEndung != 'WW' && $farbEndung != 'B' ? '0' : $farbEndung;
		$sockel = $product->getSockel();												// Lampensockel (E27, GU10).
		$fassungen = $product->getFassung();											// Lampenfassung (E27, GU10)
		$anschluss = $product->getAnschluss();											// Anschluss (2 Kabeladern, 3 Kabeladern)
		$stecker = $product->getStecker();												// (Eurostecker, Atom)
		$kabeldurchmesser = $product->getKabeldurchmesser();							// Durchmesser (mm)
		$geeignetfuerkabeldurchmesser = $product->getGeeignetfuerkabeldurchmesser();	// Geeignet für Durchmesser.
		$betriebsspannung = $product->getBetriebsspannung();							// 230V AC, 12V DC
		$ausgangsspannung = $product->getAusgangsspannung();							// 24V DC, 12V DC
		$austauschbaresLeuchtmittel = $product->getAttributeText('austauschbares_leuchtmittel') === 'Ja' ? TRUE : FALSE; // Ist Austauschbar?
		$isZubehoerBerechtigt = $product->getAttributeText('zubehoer_berechtigt');		// Ist das Produkt ein Zubehör-Artikel.
		if($isZubehoerBerechtigt !== 'Yes') continue;									// Überspringen falls kein Zubehör-Artikel.
		
		// Position des Artikels berechnen, basierend auf der Kategorie.
		$preis = number_format($product->getPrice(), 0);								// Setze die Position gleich dem Preis.
		$lichtstrom = $product->getLichtstrom().trim() == "" ? "0" : str_replace("/", "", $product->getLichtstrom());			// Lichtstrom messen
		$abstrahlwinkel = $product->getAbstrahlwinkel().trim() == "" ? "0" : $product->getAbstrahlwinkel();	// Abstrahlwinkel der Leuchte
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
						$position = strtok($lichtstrom, '/');
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
				}/*else if(stripos("-", $katElem->getName(), "sonstiges")){
					$hauptKategorie = "Sonstiges";	// @ Andere Positionierung?
				}*/
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
		
		// Hat der Artikel mehr Kabelsysteme gesetzt als nur die Adrigkeit.
		$kabelsystemIstGesetzt = FALSE;
		foreach($kabelsystemeTexte as $kabSys){
			if($kabSys != "2-Adrig" && $kabSys != "3-Adrig"){
				$kabelsystemIstGesetzt = TRUE;
			}
		}
					
		if(strpos($betriebsspannungText, '(') != FALSE){
			$tmp = explode('(', $betriebsspannungText);
			$betriebsspannungText = ($tmp[0]).trim();
		}
		if(strpos($ausgangsspannungText, '(') != FALSE){
			$tmp = explode('(', $ausgangsspannungText);
			$ausgangsspannungText = ($tmp[0]).trim();
		}
		
		if($kabelsystemIstGesetzt == TRUE){
			if($betriebsspannungText.trim() != ""){
				// Hat Betriebsspannung.
				if($ausgangsspannungText.trim() == ""){
					// An dieser Stelle die Spannung des gesetzten Kabelsystems auslesen und der Ausgangsspannung zuweisen.
					$neu = explode('(', end($kabelsystemeTexte));
					$neu = $neu[1];
					$neu = substr($neu, 0, strlen($neu) - 1);
					//echo "Neue Ausgangsspannung => $neu";
					$ausgangsspannungText = $neu;
				}
			}else{
				
				// An dieser Stelle die Spannung des gesetzten Kabelsystems auslesen und der Betriebsspannung zuweisen.
				$neu = explode('(', end($kabelsystemeTexte));
				$neu = $neu[1];
				$neu = substr($neu, 0, strlen($neu) - 1);
				//echo "Neue Betriebsspannung => $neu";
				$betriebsspannungText = $neu;
			}
		}
		
					
		// Speichere die Werte in den neuen Zubehör-Artikeln.
		$neueZubehoerArtikel[$productId] = Array(
			"sku" => $sku,								// LC-EL-000-XX
			"lampensystem" => $lampensystem,			// Atria, Badu, Bodes, Cus, Tipo
			"kabelsysteme" => $kabelsystemeTexte,		// 2-Adrig, Nemo, Spider, UK-Stecker.
			"kabelsystemIstGesetzt" => $kabelsystemIstGesetzt, // True wenn ein System außer 2-/3-Adrig gesetzt ist.
			"sonstigeZuordnungen" => $sonstigeZuordnungenTexte,// Distanzstück, Erdspießleuchte.
			"position" => $position,					// Position innerhalb des Zubehör Registers.
			"farbe" => $farbEndung,						// Weiß, Warmweiß, Blau, Normal
			"sockel" => $sockelText,					// E27, GU10
			"fassungen" => $fassungenTexte,				// E27, GU10
			"anschluss" => $anschlussText, 				// 2 Adrig, 3 Adrig
			"betriebsspannung" => $betriebsspannungText,// 230V AC, 12V DC
			"lichtstrom" => $lichtstrom,				// Lichtstrom
			"abstrahlwinkel" => $abstrahlwinkel,		// Abstrahlwinkel
			"ausgangsspannung" => $ausgangsspannungText,// 24V DC, 12V DC
			"austauschbaresLeuchtmittel" => $austauschbaresLeuchtmittel,	// Austauschbar?
			"stecker" => $steckerText, 					// Eurostecker, Atom
			"kabeldurchmesser" => $kabeldurchmesser,	// Durchmesser des Kabels.
			"geeignetfuerkabeldurchmesser" => $geeignetfuerkabeldurchmesser,	// Passt zu Durchmesser.
			"kategorie" => $hauptKategorie				// Kategorie (Leuchte, Zubehör, Lampe etc)
		);
		
		// Debug print.
		/*echo "\n\n<h3>Artikel ".$product->getSku()." mit ID [$productId] als Zubehör hinzugefügt</h3>\n";
		echo "<pre>".print_r($neueZubehoerArtikel[$productId], 1)."</pre>";
		echo "<hr>";*/
	}
	
	// -- Alle Artikel durchgehen und die gesammelten Positionen als Zubehör hinzufügen ---------------------------------
	foreach($collection as $productIndex => $_product){
		
		// Artikel aus der DB holen.
		if($_product === "") continue;
		$productId = $_product->getId(); // Richtige ID.
		
		// Ist kein Standardartikel.
		if(/*substr($_product->getSku(), 0, 3) == "SET" || */strpos($_product->getSku(), 'x') !== FALSE || strpos($_product->getSku(), 'V') !== FALSE){
			continue;
		}			
		
		$product = Mage::getModel('catalog/product')->setStoreId(0)->load($productId);	// Richtiger Artikel.
		
		
		// Eckdaten.
		$sku = $_product->getSku();										// SKU
		$kabelsysteme = $product->getKabelsystem();						// 2-Adrig, Nemo, Spider, UK-Stecker.
		$lampensystem = $product->getAttributeText('lampensysteme'); 	// Atria, Badu, Bodes, Cus, Tipo.
		$sonstigeZuordnungen = $product->getLampensystem();				// Distanzstück, Erdspießleuchte.

		$farbEndung = end(explode('-', $product->getSku())); 							// Farbendung auslesen (W, WW, B).
		$farbEndung = $farbEndung != 'W' && $farbEndung != 'WW' && $farbEndung != 'B' ? '0' : $farbEndung; 
		$sockel = $product->getSockel();												// Lampensockel (E27, GU10).
		$fassungen = $product->getFassung();											// Lampenfassungen (E27, GU10).
		$anschluss = $product->getAnschluss();											// Anschluss (2 Kabeladern, 3 Kabeladern)
		$stecker = $product->getStecker();												// (Eurostecker, Atom)
		$kabeldurchmesser = $product->getKabeldurchmesser();							// Durchmesser (mm)
		$geeignetfuerkabeldurchmesser = $product->getGeeignetfuerkabeldurchmesser();	// Geeignet für Durchmesser.
		$betriebsspannung = $product->getBetriebsspannung();							// 230V AC, 12V DC
		$ausgangsspannung = $product->getAusgangsspannung();							// 24V DC, 12V DC
		$austauschbaresLeuchtmittel = $product->getAttributeText('austauschbares_leuchtmittel') === 'Ja' ? TRUE : FALSE; // Ist Austauschbar?
		$isZubehoerBerechtigt = $product->getAttributeText('zubehoer_berechtigt');		// Ist das Produkt ein Zubehör-Artikel.
		 
		// Artikeleigenschaften sammeln.
		$preis = number_format($product->getPrice(), 0);								// Setze die Position gleich dem Preis.
		$lichtstrom = $product->getLichtstrom().trim() == "" ? "0" : str_replace("/", "", $product->getLichtstrom());			// Lichtstrom messen
		$abstrahlwinkel = $product->getAbstrahlwinkel().trim() == "" ? "0" : $product->getAbstrahlwinkel();	// Abstrahlwinkel der Leuchte
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
		
		// Hat der Artikel mehr Kabelsysteme gesetzt als nur die Adrigkeit.
		$kabelsystemIstGesetzt = FALSE;
		foreach($kabelsystemeTexte as $kabSys){
			if($kabSys != "2-Adrig" && $kabSys != "3-Adrig" && $kabSys != false){
				$kabelsystemIstGesetzt = TRUE;
			}
		}
		
		if(strpos($betriebsspannungText, '(') != FALSE){
			$tmp = explode('(', $betriebsspannungText);
			$betriebsspannungText = ($tmp[0]).trim();
		}
		if(strpos($ausgangsspannungText, '(') != FALSE){
			$tmp = explode('(', $ausgangsspannungText);
			$ausgangsspannungText = ($tmp[0]).trim();
		}
		
		// Alle Zubehörartikel durchgehen.
		$zubehoerDaten = array();
		foreach($neueZubehoerArtikel as $zubehoerIdx => $zubehoerElem){

			// Überspringe wenn es der selbe Artikel ist.
			if($productId == $zubehoerIdx){  
				continue;
			}
			
			// Auswertungen vorab: Unterschiede zwischen den Systemen des Ausgangs-Artikels und denen des 'Zubehöranwärters'.
			$lampensystemGleich = trim($lampensystem) != "" && trim($zubehoerElem['lampensystem']) != "" && $lampensystem == $zubehoerElem['lampensystem'];		
			
			//echo "Vergleiche $lampensystem mit " . $zubehoerElem['lampensystem'] . " => " . $lampensystemGleich == TRUE ? "true" : "false" . "!\n";
			$kabelsystemeUeberschneidungen = array_intersect($kabelsystemeTexte, $zubehoerElem['kabelsysteme']);
			$fassungenUeberschneidungen = array_intersect($fassungenTexte, $zubehoerElem['fassungen']);
			$sonstigeZuordnungenUeberschneidungen = array_intersect($sonstigeZuordnungenTexte, $zubehoerElem['sonstigeZuordnungen']);
			//$sockelPasstZuFassungen = ($sockelText != FALSE || $zubehoerElem["sockel"] != FALSE) && (in_array($zubehoerElem['sockel'], $fassungenTexte) || in_array($sockelText, $zubehoerElem['fassungen']));	
			// REWRITE:
			$sockelPasstZuFassungen = (
				$sockelText != FALSE && 
				$zubehoerDaten['fassungen'][0] != FALSE &&
				in_array($sockelText, $zubehoerElem['fassungen']) != FALSE
			)||(
				$fassungenTexte[0] != FALSE && 
				$zubehoerElem['sockel'] != FALSE &&
				in_array($zubehoerElem['sockel'], $fassungenTexte) != FALSE
			);
			
			$kabelsystemePassend = count($kabelsystemeUeberschneidungen) > 0;
			
			// Zuordnung der Zubehör-Artikel zu den Ausgangs-Artikeln. 
			$istZubehoer = FALSE;

			// Überspringe SIRIS und SABIK Leuchten als Zubehör für THOR Artikel:
			if((in_array('Thor (24V DC)', $kabelsystemeTexte) == TRUE || in_array('Thor (24V DC)', $zubehoerElem['kabelsysteme']) == TRUE) &&
			($zubehoerElem['lampensystem'] == "SABIK" ||  $zubehoerElem['lampensystem'] == "SIRIS")){
				continue;
			}
			
			// zugeordnet wenn der Sockel/Fassung des einen, zur Sockel/Fassung des Anderen passt.
			if($sockelPasstZuFassungen == TRUE){
				
				// Wenn sich der Artikel als passendes Zubehör herausstellt, füge ihm den Zubehör-Daten hinzu.
				$zubehoerDaten[$zubehoerIdx] = array('position' => $zubehoerElem['position']);
			}
			
			// ACHTUNG! Könnte Probleme geben, weil die Artikel zum Teil nur über das System zugeordnet werden.
			if($kabelsystemIstGesetzt == TRUE){
					
				echo "$productId -> Kabelsystem gesetzt!\n";
					
				if($betriebsspannungText.trim() != ""){
					// Hat Betriebsspannung.
					if($ausgangsspannungText.trim() == ""){
						// An dieser Stelle die Spannung des gesetzten Kabelsystems auslesen und der Ausgangsspannung zuweisen.
						$neu = explode('(', end($kabelsystemeTexte));
						$neu = $neu[1];
						$neu = substr($neu, 0, strlen($neu) - 1);
						echo "Neue Ausgangsspannung => $neu";
						$ausgangsspannungText = $neu;
					}
				}else{
					
					// An dieser Stelle die Spannung des gesetzten Kabelsystems auslesen und der Betriebsspannung zuweisen.
					$neu = explode('(', end($kabelsystemeTexte));
					$neu = $neu[1];
					$neu = substr($neu, 0, strlen($neu) - 1);
					echo "Neue Betriebsspannung => $neu";
					$betriebsspannungText = $neu;
				}
			}
			
			// -------------------------------------------------------------------------------------------------------------------
			// - Artikel: Leuchte
			// -------------------------------------------------------------------------------------------------------------------
			if($hauptKategorie == 'Leuchte'){	
				
				// Zubehör: Leuchte.
				if($zubehoerElem['kategorie'] == 'Leuchte'){ // ✔
				
					if($lampensystemGleich == TRUE){
						if($austauschbaresLeuchtmittel === FALSE){
							if(($farbEndung == '0' && $zubehoerElem['farbe'] == '0') ||
								($farbEndung == 'WW' && ($zubehoerElem['farbe'] == 'WW' || $zubehoerElem['farbe'] == '0')) ||
								($farbEndung == 'W' && ($zubehoerElem['farbe'] == 'W' || $zubehoerElem['farbe'] == '0')) ||
								($farbEndung == 'B' && ($zubehoerElem['farbe'] == 'B' || $zubehoerElem['farbe'] == '0'))){
								$istZubehoer = TRUE;
								//echo "\Leuchte($sku) -> Leuchte({$zubehoerElem['sku']}) [Weil es eine Leuchte mit festem Leuchtmittel ist und Systeme gesetzt sind und passen!]";
							}
						}else if($austauschbaresLeuchtmittel == TRUE){
							// Vergleiche Lichtstrom und Abstrahlwinkel wenn diese nicht null sind.
							$istZubehoer = ($lichtstrom == $zubehoerElem['lichtstrom'] && $abstrahlwinkel == $zubehoerElem['abstrahlwinkel']);
						}
					}
				
					// @Todo: Gartenpoller: Wenn EU Stecker an der Leuchte, dann allen Artikeln mit EU Steckdose zuordnen!							

				} 
				// Zubehör: Lampe. ✔
				else if($zubehoerElem['kategorie'] == 'Lampe'){
					
					// @Bug: Ist das Folgende korrekt?
					if($sockelPasstZuFassungen == TRUE /*&& $austauschbaresLeuchtmittel == TRUE*/){
						//echo "\nIst Zubehör, weil der Zubehör-Artikel zur Leuchte mit austauschbarem Leuchtmittel eine Lampe ist und der Sockel auf eine der Fassungen passt!";
						$istZubehoer = TRUE;
					}
				}	
				// Zubehör: Vorschaltgerät. ✔
				else if($zubehoerElem['kategorie'] == 'Vorschaltgerät'){ // ✔
				
					if($betriebsspannungText == $zubehoerElem['ausgangsspannung'] || ($ausgangsspannungText.trim() != "" && $ausgangsspannungText == $zubehoerElem['ausgangsspannung']) ){					
						
						if($kabelsystemePassend == TRUE){
							$istZubehoer = TRUE;
							//echo "\nIst Zubehör, weil der Zubehör-Artikel zur Leuchte ein Vorschaltgerät ist, die Ausgangsspannung zur Betriebsspannung passt oder die Ausgangsspannung zur Ausgangsspannung wenn diese gesetzt ist!";
						}else{
							if(in_array('2-Adrig', $kabelsystemeTexte)){
								if(in_array('2-Adrig', $zubehoerElem['kabelsysteme']) || in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
									//echo "\nIst Zubehör, weil der Zubehör-Artikel zur Leuchte ein Vorschaltgerät ist, die Ausgangsspannung zur Betriebsspannung passt oder die Ausgangsspannung zur Ausgangsspannung wenn diese gesetzt ist und der Artikel 2-Adrig und das Zubehör 2/3-Adrig!";
									$istZubehoer = TRUE;
								}
							}else if(in_array('3-Adrig', $kabelsystemeTexte)){
								if(in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
									//echo "\nIst Zubehör, weil der Zubehör-Artikel zur Leuchte ein Vorschaltgerät ist, die Ausgangsspannung zur Betriebsspannung passt oder die Ausgangsspannung zur Ausgangsspannung wenn diese gesetzt ist und der Artikel 3-Adrig und das Zubehör 3-Adrig!";
									$istZubehoer = TRUE;
								}
							}else{
								//echo "\nKEIN Zubehör, weil das Vorschaltgerät nicht per Spannung oder System zur Leuchte passt.";
							}
						}
					}
				}
				// Zubehör: Kabel. ✔
				else if($zubehoerElem['kategorie'] == 'Kabel'){ 
				
					if($kabelsystemIstGesetzt == TRUE && $zubehoerElem['kabelsystemIstGesetzt'] == TRUE){
						if($kabelsystemePassend == TRUE){
							//echo "\nIst Zubehör, weil der Zubehör-Artikel zur Leuchte ein Kabel ist und sich das Kabelsystem deckt!";
							$istZubehoer = TRUE;
						}
					}else{
						if(in_array('2-Adrig', $kabelsystemeTexte)){
							if(in_array('2-Adrig', $zubehoerElem['kabelsysteme']) || in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
								//echo "\nIst Zubehör, weil der Zubehör-Artikel zur Leuchte ein Kabel ist und der Artikel 2-Adrig und das Zubehör 2/3-Adrig!";
								$istZubehoer = TRUE;
							}
						}else if(in_array('3-Adrig', $kabelsystemeTexte)){
							if(in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
								//echo "\nIst Zubehör, weil der Zubehör-Artikel zur Leuchte ein Kabel ist und der Artikel 3-Adrig und das Zubehör 3-Adrig!";
								$istZubehoer = TRUE;
							}
						}else{
							//echo "\nKEIN Zubehör, weil weder das Kabelsystem noch die Adrigkeit zur Leuchte passt!";
						}
					}
					
					// Wenn als Zubehör definiert wurde, vergleiche Spannung ein letztes mal.
					if($istZubehoer == TRUE){
						if($betriebsspannungText != $zubehoerElem['ausgangsspannung'] &&
							$ausgangsspannungText != $zubehoerElem['betriebsspannung']){
							$istZubehoer = FALSE;
							echo "AUSGANGS-/BETRIEBSSPANNUNG WEICHT AB!\n";
						}
					}
				}
				// Zubehör: Muffe.
				else if($zubehoerElem['kategorie'] == "Muffe"){
					
					// Durchmesser des Leuchtenkabels muss zum Durchmesser der Muffen passsen!
					if($kabelsystemIstGesetzt == FALSE && ($zubehoerElem['geeignetfuerkabeldurchmesser']).trim() != "" && $kabeldurchmesser != NULL){
						$splitParts = explode('-', $zubehoerElem['geeignetfuerkabeldurchmesser']);
						
						$a = intval($splitParts[0]);
						$b = intval($splitParts[1]);
						
						if($kabeldurchmesser >= $a && $kabeldurchmesser <= $b){
							$istZubehoer = TRUE;
							//echo "\nKabel($sku) -> Muffe({$zubehoerElem['sku']}) [Weil der Durchmesser des Kabels zum 'geeigneten' Durchmesser der Muffe passt!]";
						}
					}
				}
				// Zubehör: Sonstiges.
				else if($zubehoerElem['kategorie'] == "Sonstiges"){
					// Wenn es entweder sonstige Überschneidungen gibt, oder die Kabelsysteme gesetzt sind und sich mindestens eins deckt.
					if($sonstigeZuordnungenUeberschneidungen[0] != FALSE || ($kabelsystemIstGesetzt == TRUE && $zubehoerElem['kabelsystemIstGesetzt'] == TRUE && $kabelsystemePassend == TRUE)){
						$istZubehoer = TRUE;
					}
				}
			}
			// -------------------------------------------------------------------------------------------------------------------
			// - Lampe (Leuchtmittel) 
			// -------------------------------------------------------------------------------------------------------------------
			else if($hauptKategorie == 'Lampe'){ 
				// Zubehör: Leuchte.
				if($zubehoerElem['kategorie'] == 'Leuchte' && $sockelPasstZuFassungen == TRUE){  
					$istZubehoer = TRUE;
					//echo "\nLampe($sku) -> Leuchte({$zubehoerElem['sku']}) [Weil es eine Lampe eine Leuchte mit austauschbarem Leuchtmittel ist und der Sockel zur Fassung passt!]";
				}
				// Zubehör: Lampe
				else if($zubehoerElem['kategorie'] == 'Lampe'){
					if($sockelPasstZuFassungen == TRUE){
						$istZubehoer = TRUE;
					}
				}
				// Zubehör: Vorschaltgerät.
				else if($zubehoerElem['kategorie'] == 'Vorschaltgerät'){
					if(($betriebsspannung).trim() == ($ausgangsspannung).trim()){
						$istZubehoer = TRUE;
						//echo "\nLampe($sku) -> Vorschaltgerät({$zubehoerElem['sku']}) [Weil die Betriebsspannung der Lampe zur Ausgangsspannung des Vorschaltgeräts passt.]";
					}
				}
				// Zubehör: Fassung.
				else if($zubehoerElem['kategorie'] == 'Fassung'){ 	
					if($sockelPasstZuFassungen == TRUE){
						$istZubehoer = TRUE;
						//echo "\nLampe($sku) -> Fassung({$zubehoerElem['sku']}) [Weil der Sockel zur Fassung passt!]";
					}
				}
				// Kabel möglich am Ende Fassung, daher zuordnen bei sockelPasstZuFassungen.
				else if($zubehoerElem['kategorie'] == "Kabel"){
					if($sockelPasstZuFassungen == TRUE){
						$istZubehoer = TRUE;
						//echo "\nLampe($sku) -> Kabel({$zubehoerElem['sku']}) [Weil der Sockel zur Fassung passt!]";
					}
				}
				// Zubehör: Sonstiges.
				else if($zubehoerElem['kategorie'] == "Sonstiges"){
					
					if($lampensystemGleich == TRUE || ($kabelsystemIstGesetzt == TRUE && $kabelsystemePassend == TRUE)){
						$istZubehoer = TRUE;
					}
				}
			}
			// -------------------------------------------------------------------------------------------------------------------
			// - Kabel 
			// -------------------------------------------------------------------------------------------------------------------
			else if($hauptKategorie == 'Kabel'){
				
				// Zubehör: Leuchte.
				if($zubehoerElem['kategorie'] == 'Leuchte'){ // ✔
					
					if(($kabelsystemIstGesetzt == TRUE && $kabelsystemePassend == TRUE) || $lampensystemGleich == TRUE){
						$istZubehoer = TRUE;
						//echo "\nKabel($sku) -> Leuchte({$zubehoerElem['sku']}) [Weil das Kabelsystem übereinstimmt!]";
					}else if(($kabelsystemIstGesetzt == FALSE || $zubehoerElem['kabelsysteme'] == FALSE) && in_array('2-Adrig', $kabelsystemeTexte)){
						if(in_array('2-Adrig', $zubehoerElem['kabelsysteme']) || in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
							//echo "Ist Zubehör, weil der Zubehör-Artikel zur Leuchte ein Kabel ist und der Artikel 2-Adrig und das Zubehör 2/3-Adrig!";
							$istZubehoer = TRUE;
						}
					}else if(($kabelsystemIstGesetzt == FALSE || $zubehoerElem['kabelsysteme'] == FALSE) && in_array('3-Adrig', $kabelsystemeTexte)){
						if(in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
							//echo "Ist Zubehör, weil der Zubehör-Artikel zur Leuchte ein Kabel ist und der Artikel 3-Adrig und das Zubehör 3-Adrig!";
							$istZubehoer = TRUE;
						}
					}
				} 
				// Zubehör: Lampe.
				else if($zubehoerElem['kategorie'] == 'Lampe'){ // ✔
					// Wenn Kabel Fassung hat und diese zum Sockel der Lampe passt dann zuordnen!
					if(count($fassungenTexte) > 0 && $sockelPasstZuFassungen == TRUE){
						$istZubehoer = TRUE;
						//echo "\nKabel($sku) -> Lampe({$zubehoerElem['sku']}) [Weil das Kabel eine Fassung hat, die zum Sockel passt!]";
					}
				}
				// Zubehör: Kabel.
				else if($zubehoerElem['kategorie'] == 'Kabel'){  // ✔
				
					// Kabel auf Kabel wenn System gleich oder Adrigkeit deckt.
					if($kabelsystemIstGesetzt == TRUE && $kabelsystemePassend == TRUE ){
						$istZubehoer = TRUE;
						//echo "\nKabel($sku) -> Kabel({$zubehoerElem['sku']}) [Weil das Kabelsystem übereinstimmt!]\n";
					}else if($kabelsystemIstGesetzt == FALSE && in_array('2-Adrig', $kabelsystemeTexte)){
						if(in_array('2-Adrig', $zubehoerElem['kabelsysteme']) || in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
							//echo "Ist Zubehör, weil der Zubehör-Artikel zur Leuchte ein Kabel ist und der Artikel 2-Adrig und das Zubehör 2/3-Adrig!";
							$istZubehoer = TRUE;
						}
					}else if($kabelsystemIstGesetzt == FALSE && in_array('3-Adrig', $kabelsystemeTexte)){
						if(in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
							//echo "Ist Zubehör, weil der Zubehör-Artikel zur Leuchte ein Kabel ist und der Artikel 3-Adrig und das Zubehör 3-Adrig!";
							$istZubehoer = TRUE;
						}
					}
				}
				// Zubehör: Vorschaltgerät.
				else if($zubehoerElem['kategorie'] == 'Vorschaltgerät' ){ // ✔
					// Kabelsystem stimmt überein.
					if(count($kabelsystemeTexte) > 0 && $kabelsystemIstGesetzt == TRUE && $kabelsystemePassend == TRUE ){ 
						$istZubehoer = TRUE;
						//echo "\nKabel($sku) -> Vorschaltgerät({$zubehoerElem['sku']}) [Weil das Kabelsystem übereinstimmt!]\n";
					}else if($kabelsystemIstGesetzt == FALSE && in_array('2-Adrig', $kabelsystemeTexte)){
						if(in_array('2-Adrig', $zubehoerElem['kabelsysteme']) || in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
							//echo "Ist Zubehör, weil der Zubehör-Artikel zur Leuchte ein Kabel ist und der Artikel 2-Adrig und das Zubehör 2/3-Adrig!";
							$istZubehoer = TRUE;
						}
					}else if($kabelsystemIstGesetzt == FALSE && in_array('3-Adrig', $kabelsystemeTexte)){
						if(in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
							//echo "Ist Zubehör, weil der Zubehör-Artikel zur Leuchte ein Kabel ist und der Artikel 3-Adrig und das Zubehör 3-Adrig!";
							$istZubehoer = TRUE;
						}
					}
				}
				// Zubehör: Muffen.
				else if($zubehoerElem['kategorie'] == 'Muffe'){ // ✔
					
					// Durchmesser des Kabels muss zum Durchmesser der Muffen passsen!
					if(($zubehoerElem['geeignetfuerkabeldurchmesser']).trim() != "" && $kabeldurchmesser != NULL){
						$splitParts = explode('-', $zubehoerElem['geeignetfuerkabeldurchmesser']);
						
						$a = intval($splitParts[0]);
						$b = intval($splitParts[1]);
						
						if($kabeldurchmesser >= $a && $kabeldurchmesser <= $b){
							$istZubehoer = TRUE;
							//echo "\nKabel($sku) -> Muffe({$zubehoerElem['sku']}) [Weil der Durchmesser des Kabels zum 'geeigneten' Durchmesser der Muffe passt!]";
						}
					}
				}
				// Zubehör: Lampe.
				else if($zubehoerElem['kategorie'] == 'Fassung'){ // ✔

					// Wenn das Kabelsystem gesetzt ist und passt:
					if($kabelsystemIstGesetzt == TRUE && $zubehoerElem['kabelsystemIstGesetzt'] == TRUE && $kabelsystemePassend == TRUE){
						$istZubehoer = TRUE;
						//echo "\nKabel($sku) -> Fassung({$zubehoerElem['sku']}) [Weil das Kabelsystem übereinstimmt!]";
					}
				}
				// Zubehör: Sonstiges.
				else if($zubehoerElem['kategorie'] == "Sonstiges"){
					if($kabelsystemIstGesetzt == TRUE && $kabelsystemePassend == TRUE){
						$istZubehoer = TRUE;
						//echo "\nKabel($sku) -> Sonstiges({$zubehoerElem['sku']}) [Weil das Kabelsystem gesetzt ist und übereinstimmt!]";
					}
				}
			}
			// -------------------------------------------------------------------------------------------------------------------
			// - Vorschaltgerät 
			// -------------------------------------------------------------------------------------------------------------------
			else if($hauptKategorie == 'Vorschaltgerät'){
				
				// Zubehör: Leuchte.
				if($zubehoerElem['kategorie'] == 'Leuchte'){ // ✔
				
					// Wenn die Ausgangsspannung zur Betriebsspannung passt und das Kabelsystem gesetzt ist oder die Adrigkeit passt.
					if($ausgangsspannungText == $zubehoerElem['betriebsspannung']){
						if($kabelsystemIstGesetzt == TRUE && $kabelsystemePassend == TRUE){
							$istZubehoer = TRUE;
							//echo "\nVorschaltgerät($sku) -> Leuchte({$zubehoerElem['sku']}) [Weil die Betriebsspannung zur Ausgangsspannung passt und das Kabelsystem/Adrigkeit übereinstimmt!]";
						}
						else if($kabelsystemIstGesetzt == FALSE && in_array('2-Adrig', $kabelsystemeTexte)){
						if(in_array('2-Adrig', $zubehoerElem['kabelsysteme']) || in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
							$istZubehoer = TRUE;
							//echo "Ist Zubehör, weil der Zubehör-Artikel zum Vorschaltgerät eine Leuchte ist und der Artikel 2-Adrig und das Zubehör 2/3-Adrig!";
						}
						}else if($kabelsystemIstGesetzt == FALSE && in_array('3-Adrig', $kabelsystemeTexte)){
							if(in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
								$istZubehoer = TRUE;
								//echo "Ist Zubehör, weil der Zubehör-Artikel um Vorschaltgerät eine Leuchte ist und der Artikel 3-Adrig und das Zubehör 3-Adrig!";								
							}
						}
					}
				}
				// Zubehör: Kabel.
				else if($zubehoerElem['kategorie'] == 'Kabel'){  // ✔
				
					// Kabelsystem und/oder Adrigkeit muss sich decken!
					if($kabelsystemIstGesetzt == TRUE && $kabelsystemePassend == TRUE){
						$istZubehoer = TRUE;
						//echo "\nVorschaltgerät($sku) -> Kabel({$zubehoerElem['sku']}) [Weil die Betriebsspannung zur Ausgangsspannung passt und das Kabelsystem/Adrigkeit übereinstimmt!]";
					}
					else if($kabelsystemIstGesetzt == FALSE && in_array('2-Adrig', $kabelsystemeTexte)){
						if(in_array('2-Adrig', $zubehoerElem['kabelsysteme']) || in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
							// Spannung abfragen!
							$istZubehoer = TRUE;
							//echo "Ist Zubehör, weil der Zubehör-Artikel zum Vorschaltgerät ein Kabel ist und der Artikel 2-Adrig und das Zubehör 2/3-Adrig!";
						}
					}else if($kabelsystemIstGesetzt == FALSE && in_array('3-Adrig', $kabelsystemeTexte)){
						if(in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
							// Spannung abfragen!
							$istZubehoer = TRUE;
							//echo "Ist Zubehör, weil der Zubehör-Artikel zum Vorschaltgerät ein Kabel ist und der Artikel 3-Adrig und das Zubehör 3-Adrig!";								
						}
					}
					
				}
				// Zubehör: Muffen.
				else if($zubehoerElem['kategorie'] == 'Muffe'){ // ✔
					
					// Durchmesser des Vorschaltgerätekabels muss zum Durchmesser der Muffen passsen!
					if(($zubehoerElem['geeignetfuerkabeldurchmesser']).trim() != "" && $kabeldurchmesser != NULL){
						$splitParts = explode('-', $zubehoerElem['geeignetfuerkabeldurchmesser']);
						
						$a = intval($splitParts[0]);
						$b = intval($splitParts[1]);
						
						if($kabeldurchmesser >= $a && $kabeldurchmesser <= $b){
							$istZubehoer = TRUE;
							//echo "\nKabel($sku) -> Muffe({$zubehoerElem['sku']}) [Weil der Durchmesser des Vorschaltgerätekabels zum 'geeigneten' Durchmesser der Muffe passt!]";
						}
					}
				}
				// Zubehör: Vorschaltgerät.
				else if($zubehoerElem['kategorie'] == 'Vorschaltgerät'){ 
					if($kabelsystemIstGesetzt == TRUE && $kabelsystemePassend == TRUE){
						$istZubehoer = TRUE;
					}
				}
				// Zubehör: Sonstiges.
				else if($zubehoerElem['kategorie'] == "Sonstiges"){
					
					if($lampensystemGleich == TRUE || ($kabelsystemIstGesetzt == TRUE && $kabelsystemePassend == TRUE)){
						$istZubehoer = TRUE;
					}
				}
			}
			// -------------------------------------------------------------------------------------------------------------------
			// - Muffe 
			// -------------------------------------------------------------------------------------------------------------------
			else if($hauptKategorie == 'Muffe'){	
				if($zubehoerElem['kategorie'] == 'Kabel'){ // ✔
					
					// Muffe auf Kabel, wenn der Durchmesser passt und die Adrigkeit.
					if(($geeignetfuerkabeldurchmesser).trim() != "" && ($zubehoerElem['kabeldurchmesser']).trim() != ""){
						$splitParts = explode('-', $geeignetfuerkabeldurchmesser);
						
						$a = intval($splitParts[0]);
						$b = intval($splitParts[1]);
						
						if($zubehoerElem['kabeldurchmesser'] >= $a && $zubehoerElem['kabeldurchmesser'] <= $b){
							$istZubehoer = TRUE;
							//echo "\nMuffe($sku) -> Kabel({$zubehoerElem['sku']}) [Weil der Durchmesser des Kabels zum 'geeigneten' Durchmesser der Muffe passt!]";
						}
					}
				} 
			}
			// -------------------------------------------------------------------------------------------------------------------
			// - Fassung 
			// -------------------------------------------------------------------------------------------------------------------
			else if($hauptKategorie == 'Fassung'){
				
				// Lampen hinzufügen, wenn die Fassung zum Sockel passt!!!
				if($zubehoerElem['kategorie'] == 'Lampe'){ // ✔
					if(count($fassungenTexte) > 0 && $sockelPasstZuFassungen == TRUE){
						$istZubehoer = TRUE;
						//echo "\nFassung($sku) -> Lampe({$zubehoerElem['sku']}) [Weil der Sockel zur Fassung passt!]\n";
					}
				}
				// Der Zubehör-Artikel ist eine Fassung und die Fassung ist identisch mit der des Artikels.
				else if($zubehoerElem['kategorie'] == "Fassung"){ // ✔

					// Sockel -> Fassung
					if(count($fassungenTexte) > 0 && ($sockelPasstZuFassungen === TRUE || count($fassungenUeberschneidungen) > 0)){
						//echo "Ist Zubehör, weil der Zubehör-Artikel zur Fassung eine Fassung ist, das System gleich ist und die Lichtfarbe passt!";
						$istZubehoer = TRUE;		
					}

				}
				// Zubehör: Leuchte.
				else if($zubehoerElem['kategorie'] == "Leuchte"){ // ✔
					// Leuchte wenn: Sockel -> Fassung 
					if(count($fassungenTexte) > 0 && $sockelPasstZuFassungen == TRUE){
						$istZubehoer = TRUE;
						//echo "\nFassung($sku) -> Leuchte({$zubehoerElem['sku']}) [Weil der Sockel zur Fassung passt!]\n";
					}
				}			
				// Der Zubehör-Artikel ist ein Kabel und das System gleich ist.
				else if($zubehoerElem['kategorie'] == "Kabel" ){ // ✔
				
					// Wenn das Kabel einen Sockel oder Fassung hat die zur Fassung passt.
					if((count($fassungenTexte) > 0 || count($zubehoerElem['fassungen']) > 0) && $sockelPasstZuFassungen === TRUE){
						$istZubehoer = TRUE;
						//echo "\nFassung($sku) -> Kabel({$zubehoerElem['sku']}) [Weil der Sockel zur Fassung passt!]";	
					}
				}
				// Zubehör: Vorschaltgerät.
				else if($zubehoerElem['kategorie'] == "Vorschaltgerät"){ // ✔
					// Vorschaltgerät wenn: Betriebsspannung von der Fassung gleich der Ausgangsspannung vom Vorschaltgerät!
					if($zubehoerElem['ausgangsspannung'] == $betriebsspannungText){
						$istZubehoer = TRUE;
						//echo "\nFassung($sku) -> Vorschaltgerät({$zubehoerElem['sku']}) [Betriebsspannung von der Fassung gleich der Ausgangsspannung vom Vorschaltgerät!]";
					}
				}	
			}
			// -------------------------------------------------------------------------------------------------------------------
			// - Sonstiges 
			// -------------------------------------------------------------------------------------------------------------------
			else if($hauptKategorie == "Sonstiges"){

				// Zubehör: Sonstiges.
				if($zubehoerElem['kategorie'] == "Sonstiges"){
					if($lampensystemGleich == TRUE || ($kabelsystemIstGesetzt == TRUE && $kabelsystemePassend == TRUE)){
						$istZubehoer = TRUE;
					}
				}
			}
			
			// Wenn sich der Artikel als passendes Zubehör herausstellt, füge ihm den Zubehör-Daten hinzu.
			if($istZubehoer == TRUE){
				$zubehoerDaten[$zubehoerIdx] = array('position' => $zubehoerElem['position']);
			}
		}
		
		
		// Speichere Änderungen in die Datenbank.
		Mage::getResourceModel('catalog/product_link')->saveProductLinks(
			$product, $zubehoerDaten, Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED
		);
	}
	
	// ------------------------------------------------------------------------------------------------------------------	
	
	// Measure time.
	$time_end = microtime(true) - $start;
	$time = round($time_end, 2);
	echo "<hr>Success! Script took <b>$time</b> seconds to execute!<hr>";
	
?>
