<?php
	// Introduce script.
	echo "<meta charset='utf-8'>";
	echo "Script '".__FILE__."' running...<hr>";
	
	// Prepare magento for script execution.
	// !hidden!
	
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
		$geeignetfuerkabeldurchmesser = $product->getGeeignetfuerkabeldurchmesser();		// Geeignet für Durchmesser.
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
			"sku" => $sku,								// LC-EL-000-XX
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
			"stecker" => $steckerText, 					// Eurostecker, Atom
			"kabeldurchmesser" => $kabeldurchmesser,	// Durchmesser des Kabels.
			"geeignetfuerkabeldurchmesser" => $geeignetfuerkabeldurchmesser,	// Passt zu Durchmesser.
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
				// Zubehör: Leuchte. ✔
				if($zubehoerElem['kategorie'] == 'Leuchte'){ 	
				
				
					// GARTENPOLLER SPEZIELL PRÜFEN:
						// Wenn EU Stecker an der Leuchte, dann allen Artikeln mit EU Steckdose zuordnen!				
						
				
				
					// Wenn zwei Leuchten kein Leuchtmittel haben:
					/*if($austauschbaresLeuchtmittel === TRUE && $zubehoerElem['austauschbaresLeuchtmittel'] === TRUE){
						
						// ... dann muss die Farbe übereinstimmen:
						if(
							($farbEndung == '0' && $zubehoerElem['farbe'] == '0') ||
							($farbEndung == 'WW' && ($zubehoerElem['farbe'] == 'WW' || $zubehoerElem['farbe'] == '0')) ||
							($farbEndung == 'W' && ($zubehoerElem['farbe'] == 'W' || $zubehoerElem['farbe'] == '0')) ||
							($farbEndung == 'B' && ($zubehoerElem['farbe'] == 'B' || $zubehoerElem['farbe'] == '0'))
						){
							// ... und es müssen sich ein System gleichen, oder ein Kabelsystem oder eine sonstige Zuordnung:
							if($lampensystemGleich == TRUE || $kabelsystemePassend == TRUE || count($sonstigeZuordnungenUeberschneidungen) > 0){
								echo "Ist Zubehör, weil der Zubehör-Artikel zur Leuchte mit austauschbarem Leuchtmittel eine Leuchte mit
								austauschbarem Leuchtmittel ist, das System gleich ist und die Lichtfarbe passt!";
								$istZubehoer = TRUE; 
							}
							// ... und es soll auf den Abstrahlwinkel geachtet werden was keinen Sinn macht bei Leuchten ohne Lampe.
						}
					}*/
				
					/*if(
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
					}*/
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
			}
			 
			// -------------------------------------------------------------------------------------------------------------------
			// - Lampe (Leuchtmittel) 
			// -------------------------------------------------------------------------------------------------------------------
			else if($hauptKategorie == 'Lampe'){ 
				
				// Zubehör: Leuchte.
				if($zubehoerElem['kategorie'] == 'Leuchte' && $zubehoerElem['austauschbaresLeuchtmittel'] == TRUE && $sockelPasstZuFassungen == TRUE){  
					$istZubehoer = TRUE;
					echo "\nLampe($sku) -> Fassung({$zubehoerElem['sku']}) [Weil es eine Lampe eine Leuchte mit austauschbarem Leuchtmittel ist und der Sockel zur Fassung passt!]\n";
				}
				// Zubehör: Vorschaltgerät.
				else if($zubehoerElem['kategorie'] == 'Vorschaltgerät'){
					// Betriebsspannung der Lampe zur Ausgangsspannung des Vorschaltgeräts.
					if(($betriebsspannung).trim() == ($ausgangsspannung).trim()){
						$istZubehoer = TRUE;
						echo "\nLampe($sku) -> Vorschaltgerät({$zubehoerElem['sku']}) [Weil die Betriebsspannung der Lampe zur Ausgangsspannung des Vorschaltgeräts passt.]\n";
					}
				}
				// Zubehör: Fassung.
				else if($zubehoerElem['kategorie'] == 'Fassung'){ 	
					if($sockelPasstZuFassungen == TRUE){
						$istZubehoer = TRUE;
						echo "\nLampe($sku) -> Fassung({$zubehoerElem['sku']}) [Weil der Sockel zur Fassung passt!]\n";
					}
				}
				// Kabel möglich am Ende Fassung, daher zuordnen bei sockelPasstZuFassungen.
				else if($zubehoerElem['kategorie'] == "Kabel"){
					if($sockelPasstZuFassungen == TRUE){
						$istZubehoer = TRUE;
						echo "\nLampe($sku) -> Kabel({$zubehoerElem['sku']}) [Weil der Sockel zur Fassung passt!]\n";
					}
				}
			}
			// -------------------------------------------------------------------------------------------------------------------
			// - Kabel 
			// -------------------------------------------------------------------------------------------------------------------
			else if($hauptKategorie == 'Kabel'){
				// Zubehör: Leuchte.
				if($zubehoerElem['kategorie'] == 'Leuchte'){ // ✔
					// @Stecker decken!!!!
					if($kabelsystemePassend == TRUE){
						$istZubehoer = TRUE;
						echo "\nKabel($sku) -> Kabel({$zubehoerElem['sku']}) [Weil das Kabelsystem übereinstimmt!]\n";
					}
				}
				// Zubehör: Kabel.
				else if($zubehoerElem['kategorie'] == 'Kabel'){  // ✔
					// Kabel auf Kabel wenn System gleich oder Adrigkeit deckt.
					if($kabelsystemePassend == TRUE ){
						$istZubehoer = TRUE;
						echo "\nKabel($sku) -> Kabel({$zubehoerElem['sku']}) [Weil das Kabelsystem übereinstimmt!]\n";
					}else if(in_array('2-Adrig', $kabelsystemeTexte) || in_array('3-Adrig', $kabelsystemeTexte)){
						if(in_array('2-Adrig', $zubehoerElem['kabelsysteme']) || in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
							$istZubehoer = TRUE;
							echo "\nKabel($sku) -> Kabel({$zubehoerElem['sku']}) [Weil die Adrigkeit übereinstimmt!]\n";
						}
					}
				}
				// Zubehör: Vorschaltgerät.
				else if($zubehoerElem['kategorie'] == 'Vorschaltgerät' ){ // ✔
					// Kabelsystem stimmt überein.
					if($kabelsystemePassend == TRUE ){ 
						$istZubehoer = TRUE;
						echo "\nKabel($sku) -> Vorschaltgerät({$zubehoerElem['sku']}) [Weil das Kabelsystem übereinstimmt!]\n";
					}
				}
				// Zubehör: Muffen.
				else if($zubehoerElem['kategorie'] == 'Muffe'){
					// Durchmesser des Kabels muss zum Durchmesser der Muffen passsen!
					if(($geeignetfuerkabeldurchmesser).trim() != ""){
						$splitParts = split('-', $geeignetfuerkabeldurchmesser);
						
						$a = intval($splitParts[0]);
						$b = intval($splitParts[1]);
						
						if($kabeldurchmesser > $a && $kabeldurchmesser < $b){
							$istZubehoer = TRUE;
							echo "\nKabel($sku) -> Vorschaltgerät({$zubehoerElem['sku']}) [Weil der Durchmesser des Kabels zum 'geeigneten' Durchmesser der Muffe passt!]\n";
						}
					}
				}
				
				// Wenn Kabel Fassung hat und diese zum Sockel der Lampe passt dann zuordnen!
				//Todo here.
				// Zubehör: Lampe.
				else if($zubehoerElem['kategorie'] == 'Fassung'){ // ✔
				
					// WENN DAS KABELSYSTEM PASST ZUORDNEN:
					if($kabelsystemePassend == TRUE){
						$istZubehoer = TRUE;
						echo "\nKabel($sku) -> Vorschaltgerät({$zubehoerElem['sku']}) [Weil das Kabelsystem übereinstimmt!]\n";
					}
				}
			}
			
			// -------------------------------------------------------------------------------------------------------------------
			// - Vorschaltgerät 
			// -------------------------------------------------------------------------------------------------------------------
			else if($hauptKategorie == 'Vorschaltgerät'){
				
				// GARTENPOLLER SPEZIELL PRÜFEN:
					// Wenn EU Stecker haben, dann allen Artikeln mit EU Steckdose zuordnen!
						
				
				// Zubehör: Leuchte.
				if($zubehoerElem['kategorie'] == 'Leuchte' && $kabelsystemGleich == TRUE){ // ✔
					// ODER ADRIGKEIT MATCHED.
					// ADRIGKEIT NUR WENN AUSGANGSSPANNUNG ZUR Betriebsspannung PASST!
					
					// @ Steckerabfrage hier!!!
					$istZubehoer = TRUE;
				}
				// Zubehör: Kabel.
				else if($zubehoerElem['kategorie'] == 'kabel'){  // ✔
				
					
					// Kabelsystem und/oder Adrigkeit muss sich decken!
					
					
					if($kabelsystemePassend == TRUE && in_array('2-Adrig', $kabelsystemeTexte) || in_array('3-Adrig', $kabelsystemeTexte)){
						if(in_array('2-Adrig', $zubehoerElem['kabelsysteme']) || in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
							echo "Ist Zubehör, weil der Zubehör-Artikel zum Kabel ein Kabel ist und sich das System und die Adrigkeit gleicht!";
							$istZubehoer = TRUE;
						}
					}
				}
				// // Zubehör: Muffe.
				else if($zubehoerElem['kategorie'] == 'Muffe'){
					// Wenn der Kabeldurchmesser passt.
					
				}
			}
			// -------------------------------------------------------------------------------------------------------------------
			// - Muffe 
			// -------------------------------------------------------------------------------------------------------------------
			else if($hauptKategorie == 'Muffe'){
				
				if($zubehoerElem['kategorie'] == 'Kabel'){ // ✔
					// DURCHMESSER MUSS PASSEN! (Adrigkeit muss ebenfalls passen)
					echo "Ist Zubehör, weil zur Muffe ein Kabel gehört, wenn sich das System gleicht!";
					$istZubehoer = TRUE;
				}
			}
			// -------------------------------------------------------------------------------------------------------------------
			// - Fassung 
			// -------------------------------------------------------------------------------------------------------------------
			else if($hauptKategorie == 'Fassung'){
				
				
				// Lampen hinzufügen, wenn Fassung zu Sockel passt!!!
				
				// Der Zubehör-Artikel ist eine Fassung und die Fassung ist identisch mit der des Artikels.
				if($zubehoerElem['kategorie'] == "Fassung"){ // ✔
				
					// Sockel -> Fassung
				
					if($sockelPasstZuFassungen === TRUE){
						echo "Ist Zubehör, weil der Zubehör-Artikel zur Leuchte mit austauschbarem Leuchtmittel eine Leuchte mit
							austauschbarem Leuchtmittel ist, das System gleich ist und die Lichtfarbe passt!";
							$istZubehoer = TRUE;			
					}

				}
				// Der Zubehör-Artikel ist ein Kabel und das System gleich ist.
				else if($zubehoerElem['kategorie'] == "Kabel" ){ // ✔
				
					// Wenn kein System beim Kabel, dann Adrigkeit mit Fassung abgleichen
				
				
					echo "Ist Zubehör, weil zur Fassung ein Kabel gehört, wenn sich das System der beiden gleicht!";
					$istZubehoer = TRUE;
				}
				
				// Leuchte wenn: Sockel -> Fassung 
				// Vorschaltgerät wenn: Betriebsspannung von der Fassung gleich der Ausgangsspannung vom Vorschaltgerät!
			}
			
			// Wenn sich der Artikel als passendes Zubehör herausstellt, füge ihm den Zubehör-Daten hinzu.
			if($istZubehoer == TRUE){
				$zubehoerDaten[$zubehoerIdx] = array('position' => $zubehoerElem['position']);
			}
		}
		
		
		// @Todo: Um Änderungen zu erkennen, speichere erst alle ID's der geänderten Zubehörartikel 
		// zwischen und vergleiche diese dann mit den aktuellen eingetragenen Zubehör-Artikeln.
		// Nur wenn diese sich unterscheiden, führe die folgende Funktion zum speichern aus: 
		// Update: Das Folgende scheint selbstständig zu prüfen ob es Änderungen gibt...
		Mage::getResourceModel('catalog/product_link')->saveProductLinks(
			$product, $zubehoerDaten, Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED
		);
		
		//echo "</fieldset>";
	}
	
	// ------------------------------------------------------------------------------------------------------------------	
	
	// Measure time.
	$time = round((microtime(TRUE) - $start), 2);
	echo "<hr>Success! Script took <b>$time</b> seconds to execute!<hr>";
?>
