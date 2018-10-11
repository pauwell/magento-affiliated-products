<?php
	// Starte script.
	fwrite(STDOUT, "Script '".__FILE__."' running...\n");
	
	// Einstellungen hier (versteckt):
	// xxx

	// Starte Zeitmessung.
	$start = microtime(TRUE);
	
	// Sammlung aller Artikel.
	$collection = Mage::getModel('catalog/product')
		->getCollection()
		->addAttributeToSelect('up_sell_product_grid_table')
		->load();
		
	// Gesamtanzahl der Artikel.
	$countTotalProducts = $collection->getLastItem()->getId();
	
	// -- Alle Artikel durchgehen und deren Position und Typ speichern --------------------------------------------------
	$neueZubehoerArtikel = array();
	foreach($collection as $_product){
		
		// Artikel aus der DB holen.
		if($_product === "") continue;
		$productId = $_product->getId();														

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
		$fassungen = $product->getFassung();											// Lampenfassung (E27, GU10).
		$anschluss = $product->getAnschluss();											// Anschluss (2 Kabeladern, 3 Kabeladern)
		$stecker = $product->getStecker();												// (Eurostecker, Atom)
		$kabeldurchmesser = $product->getKabeldurchmesser();							// Durchmesser (mm)
		$geeignetfuerkabeldurchmesser = $product->getGeeignetfuerkabeldurchmesser();	// Geeignet für Durchmesser.
		$bauform = $product->getAttributeText('bauform');								// Bauform.
		$geeignetFuerBauform = $product->getGeeignetFuerBauform();						// Geeignet für Bauform. 
		$betriebsspannung = $product->getBetriebsspannung();							// 230V AC, 12V DC
		$ausgangsspannung = $product->getAusgangsspannung();							// 24V DC, 12V DC
		$austauschbaresLeuchtmittel = $product->getAttributeText('austauschbares_leuchtmittel') === 'Ja' ? TRUE : FALSE; // Ist Austauschbar?
		$isZubehoerBerechtigt = $product->getAttributeText('zubehoer_berechtigt');		// Ist das Produkt ein Zubehör-Artikel.
		if($isZubehoerBerechtigt !== 'Yes') continue;									// Überspringen falls kein Zubehör-Artikel.
		
		// Position des Artikels berechnen, basierend auf der Kategorie.
		$preis = number_format($product->getPrice(), 0);																// Setze die Position gleich dem Preis.
		$lichtstrom = $product->getLichtstrom().trim() == "" ? "0" : str_replace("/", "", $product->getLichtstrom());	// Lichtstrom messen
		$abstrahlwinkel = $product->getAbstrahlwinkel().trim() == "" ? "0" : $product->getAbstrahlwinkel();				// Abstrahlwinkel der Leuchte
		$kabelLaenge= $product->getKabellaenge() * 10;																	// Länge des Kabels (x10).
		$ausgangsleistung = $product->getAusgangsleistungVgeraet();														// Watt Ausgangsleistung.
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
				}else if(stripos("-" . $katElem->getName(), "muffe")){
					$hauptKategorie = "Muffe";
					$position = $ausgangsleistung; // @ Andere Positionierung?
				}else if(stripos("-" . $katElem->getName(), "landing")){
					$hauptKategorie = "Landingpages";
				}
			}
		}
		if(empty($hauptKategorie)){ $hauptKategorie = "Sonstiges"; }
		
		// Überspringe den Artikel wenn er in der Kategorie Landingpages ist oder eine falsche Artikelnummer hat.
		if($hauptKategorie == "Landingpages" || substr($product->getSku(), 0, 1) == 'V' ){
			continue; 
		}
		
		// Sichere alle Kategorien.
		$alleKategorien = Array();
		foreach($kategorien as $katIdx => $katElem){
			if(in_array($katElem->getId(), $kategorienIds)){
				array_push($alleKategorien, $katElem->getName());
			}
		}
		
		// Konvertiere die Werte von der Id zum richtigen Wert.
		$kabelsystemeTexte = $product->getResource()->getAttribute('kabelsystem')->getSource()->getOptionText($kabelsysteme);
		$sonstigeZuordnungenTexte = $product->getResource()->getAttribute('lampensystem')->getSource()->getOptionText($sonstigeZuordnungen);
		$sockelText = $product->getResource()->getAttribute('sockel')->getSource()->getOptionText($sockel);
		$fassungenTexte = $product->getResource()->getAttribute('fassung')->getSource()->getOptionText($fassungen);
		$anschlussText = $product->getResource()->getAttribute('anschluss')->getSource()->getOptionText($anschluss);
		$geeignetFuerBauformTexte = $product->getResource()->getAttribute('geeignet_fuer_bauform')->getSource()->getOptionText($geeignetFuerBauform);
		$betriebsspannungText = $product->getResource()->getAttribute('betriebsspannung')->getSource()->getOptionText($betriebsspannung);
		$ausgangsspannungText = $product->getResource()->getAttribute('ausgangsspannung')->getSource()->getOptionText($ausgangsspannung);
		$steckerText = $product->getResource()->getAttribute('stecker')->getSource()->getOptionText($stecker);
		
		// Konvertiere zu array auch wenn es nur ein Element gibt.
		if(is_array($kabelsystemeTexte) == FALSE){ $kabelsystemeTexte = Array($kabelsystemeTexte); }
		if(is_array($sonstigeZuordnungenTexte) == FALSE){ $sonstigeZuordnungenTexte = Array($sonstigeZuordnungenTexte); }
		if(is_array($fassungenTexte) == FALSE){ $fassungenTexte = Array($fassungenTexte); }
		if(is_array($geeignetFuerBauformTexte) == FALSE){ $geeignetFuerBauformTexte = Array($geeignetFuerBauformTexte); }
		
		// Hat der Artikel mehr Kabelsysteme gesetzt als nur die Adrigkeit.
		$kabelsystemIstGesetzt = FALSE;
		foreach($kabelsystemeTexte as $kabSys){
			if($kabSys != "2-Adrig" && $kabSys != "3-Adrig" && $kabSys != false){
				$kabelsystemIstGesetzt = TRUE;
			}
		}
					
		if(strpos($betriebsspannungText, '(') != FALSE){
			$tmp = explode('(', $betriebsspannungText);
			$betriebsspannungText = trim($tmp[0]);
		}
		if(strpos($ausgangsspannungText, '(') != FALSE){
			$tmp = explode('(', $ausgangsspannungText);
			$ausgangsspannungText = trim($tmp[0]);
		}
		
		if($kabelsystemIstGesetzt == TRUE){
			if($betriebsspannungText.trim() != ""){
				if($ausgangsspannungText.trim() == ""){
					// An dieser Stelle die Spannung des gesetzten Kabelsystems auslesen und der Ausgangsspannung zuweisen.
					$neu = explode('(', end($kabelsystemeTexte));
					$neu = $neu[1];
					$neu = substr($neu, 0, strlen($neu) - 1);
					$ausgangsspannungText = trim($neu);
				}
			}else{
				
				// An dieser Stelle die Spannung des gesetzten Kabelsystems auslesen und der Betriebsspannung zuweisen.
				$neu = explode('(', end($kabelsystemeTexte));
				$neu = $neu[1];
				$neu = substr($neu, 0, strlen($neu) - 1);
				$betriebsspannungText = trim($neu);
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
			"geeignetFuerBauformen" => $geeignetFuerBauformTexte, // Passt zu Bauform (MR16..)
			"bauform" => $bauform,						// Bauform.
			"kategorie" => $hauptKategorie,				// Kategorie (Leuchte, Zubehör, Lampe etc)
			"alleKategorien" => $alleKategorien			// Alle Kategorien ungefiltert.
		);
		
		
		// Fortschrittsanzeige.
		system("clear");
		fwrite(STDOUT, "Gathering affiliated products.\n");
		$progress = intval(($productId/$countTotalProducts)*100);
		fwrite(STDOUT, "[ At id: " . $productId . "\t-\t Progress: " . $progress . "%]\n");
		$progressBar = "|";
		for($i=0; $i<100/3; ++$i){
			if($i <= ($progress/3)){ $progressBar .= "-"; }
			else{ $progressBar .= " "; }
		}
		$progressBar .= "|";
		fwrite(STDOUT, $progressBar);
	}
	
	// -- Alle Artikel durchgehen und die gesammelten Positionen als Zubehör hinzufügen ---------------------------------
	foreach($collection as $productIndex => $_product){
		
		// Artikel aus der DB holen.
		if($_product === "") continue;
		$productId = $_product->getId(); // Richtige ID.
		
		// Ist kein Standardartikel.
		if(strpos($_product->getSku(), 'V') !== FALSE){
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
		$bauform = $product->getAttributeText('bauform');								// Bauform.
		$geeignetFuerBauform = $product->getGeeignetFuerBauform();						// Geeignet für Bauform. 
		$betriebsspannung = $product->getBetriebsspannung();							// 230V AC, 12V DC
		$ausgangsspannung = $product->getAusgangsspannung();							// 24V DC, 12V DC 
		$austauschbaresLeuchtmittel = $product->getAttributeText('austauschbares_leuchtmittel') === 'Ja' ? TRUE : FALSE; // Ist Austauschbar?
		$isZubehoerBerechtigt = $product->getAttributeText('zubehoer_berechtigt');		// Ist das Produkt ein Zubehör-Artikel.
		 
		// Artikeleigenschaften sammeln.
		$preis = number_format($product->getPrice(), 0);								// Setze die Position gleich dem Preis.
		$lichtstrom = $product->getLichtstrom().trim() == "" ? "0" : str_replace("/", "", $product->getLichtstrom());	// Lichtstrom messen
		$abstrahlwinkel = $product->getAbstrahlwinkel().trim() == "" ? "0" : $product->getAbstrahlwinkel();				// Abstrahlwinkel der Leuchte
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
					//$position = $ausgangsleistung; // @ TODO
				}else if(stripos("-".$katElem->getName(), "fassung")){
					$hauptKategorie = "Fassung";
					//$position = $ausgangsleistung; // @ TODO
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
		$geeignetFuerBauformTexte = $product->getResource()->getAttribute('geeignet_fuer_bauform')->getSource()->getOptionText($geeignetFuerBauform);
		$betriebsspannungText = $product->getResource()->getAttribute('betriebsspannung')->getSource()->getOptionText($betriebsspannung);
		$ausgangsspannungText = $product->getResource()->getAttribute('ausgangsspannung')->getSource()->getOptionText($ausgangsspannung);
		$steckerText = $product->getResource()->getAttribute('stecker')->getSource()->getOptionText($stecker);

		// Konvertiere zu array auch wenn es nur ein Element gibt.
		if(is_array($kabelsystemeTexte) == FALSE){ $kabelsystemeTexte = Array($kabelsystemeTexte); }
		if(is_array($sonstigeZuordnungenTexte) == FALSE){ $sonstigeZuordnungenTexte = Array($sonstigeZuordnungenTexte); }
		if(is_array($fassungenTexte) == FALSE){ $fassungenTexte = Array($fassungenTexte); }
		if(is_array($geeignetFuerBauformTexte) == FALSE){ $geeignetFuerBauformTexte = Array($geeignetFuerBauformTexte); }
		
		// Hat der Artikel Kabelsysteme gesetzt?
		$kabelsystemIstGesetzt = FALSE;
		foreach($kabelsystemeTexte as $kabSys){
			if($kabSys != "2-Adrig" && $kabSys != "3-Adrig" && $kabSys != false){
				$kabelsystemIstGesetzt = TRUE;
			}
		}
		
		if(strpos($betriebsspannungText, '(') != FALSE){
			$tmp = explode('(', $betriebsspannungText);
			$betriebsspannungText = trim($tmp[0]);
		}
		if(strpos($ausgangsspannungText, '(') != FALSE){
			$tmp = explode('(', $ausgangsspannungText);
			$ausgangsspannungText = trim($tmp[0]);
		}
		
		// Alle Zubehörartikel durchgehen.
		$zubehoerDaten = array();
		foreach($neueZubehoerArtikel as $zubehoerIdx => $zubehoerElem){

			// Überspringe wenn es der selbe Artikel ist.
			if($productId == $zubehoerIdx){  
				continue;
			}
			
			$lampensystemGleich = trim($lampensystem) != "" && trim($zubehoerElem['lampensystem']) != "" && $lampensystem == $zubehoerElem['lampensystem'];			
			$kabelsystemeUeberschneidungen = array_intersect($kabelsystemeTexte, $zubehoerElem['kabelsysteme']);
			$fassungenUeberschneidungen = array_intersect($fassungenTexte, $zubehoerElem['fassungen']);
			$sonstigeZuordnungenUeberschneidungen = array_intersect($sonstigeZuordnungenTexte, $zubehoerElem['sonstigeZuordnungen']);
			
			$bauformenPassen = (
				$bauform != FALSE && 
				$zubehoerElem['geeignetFuerBauformen'][0] != FALSE && 
				in_array($bauform, $zubehoerElem['geeignetFuerBauformen']) != FALSE
			)||(
				$zubehoerElem['bauform'] != FALSE &&
				$geeignetFuerBauformTexte[0] != FALSE &&
				in_array($zubehoerElem['bauform'], $geeignetFuerBauformTexte) != FALSE
			);
			
			$sockelPasstZuFassungen = (
				$sockelText != FALSE && 
				$zubehoerElem['fassungen'][0] != FALSE &&
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

			// Checken ob der Sockel zur Fassung passt.
			if($sockelPasstZuFassungen == TRUE){
				
				// Wenn sich der Artikel als passendes Zubehör herausstellt, füge ihm den Zubehör-Daten hinzu.
				$zubehoerDaten[$zubehoerIdx] = array('position' => $zubehoerElem['position']);
			}
			

			// Wenn das Kabelsystem gesetzt ist, dann gleiche Ausgangs/Betriebsspannung an.
			if($kabelsystemIstGesetzt == TRUE){
				if(trim($betriebsspannungText) != ""){
					if(trim($ausgangsspannungText) == ""){
						$neu = explode('(', end($kabelsystemeTexte));
						$neu = $neu[1];
						$neu = substr($neu, 0, strlen($neu) - 1);
						$ausgangsspannungText = $neu;
					}
				}else{
					$neu = explode('(', end($kabelsystemeTexte));
					$neu = $neu[1];
					$neu = substr($neu, 0, strlen($neu) - 1);
					$betriebsspannungText = $neu;
				}
			}
		
			// Whitespaces abschneiden.
			if(is_string($ausgangsspannungText) == TRUE){ $ausgangsspannungText = trim($ausgangsspannungText); }
			if(is_string($betriebsspannungText) == TRUE){ $betriebsspannungText = trim($betriebsspannungText); }		
			
			// -------------------------------------------------------------------------------------------------------------------
			// - Hauptkategorie: Leuchte
			// -------------------------------------------------------------------------------------------------------------------
			if($hauptKategorie == 'Leuchte'){	
				
				// Zubehör: Leuchte.
				if($zubehoerElem['kategorie'] == 'Leuchte'){
					if($lampensystemGleich == TRUE){
						if($austauschbaresLeuchtmittel == FALSE){
							if(($farbEndung == '0' && $zubehoerElem['farbe'] == '0') ||
								($farbEndung == 'WW' && ($zubehoerElem['farbe'] == 'WW' || $zubehoerElem['farbe'] == '0')) ||
								($farbEndung == 'W' && ($zubehoerElem['farbe'] == 'W' || $zubehoerElem['farbe'] == '0')) ||
								($farbEndung == 'B' && ($zubehoerElem['farbe'] == 'B' || $zubehoerElem['farbe'] == '0'))){
								$istZubehoer = TRUE;
							}
						}else if($austauschbaresLeuchtmittel == TRUE){
							$istZubehoer = ($lichtstrom == $zubehoerElem['lichtstrom'] && $abstrahlwinkel == $zubehoerElem['abstrahlwinkel']);
						}
					}
				} 
				// Zubehör: Lampe.
				else if($zubehoerElem['kategorie'] == 'Lampe'){
					if($sockelPasstZuFassungen == TRUE){
						$istZubehoer = TRUE;
					}
				}	
				// Zubehör: Vorschaltgerät.
				else if($zubehoerElem['kategorie'] == 'Vorschaltgerät'){
					if(trim($zubehoerElem['betriebsspannung'] != "" && trim($zubehoerElem['ausgangsspannung']) != "")){
						if($kabelsystemIstGesetzt == TRUE && $kabelsystemePassend == TRUE){
							$istZubehoer = TRUE;
						}
					}

					if((trim($betriebsspannungText) != "" && $betriebsspannungText == $zubehoerElem['ausgangsspannung']) || 
					   (trim($ausgangsspannungText) != "" && $ausgangsspannungText == $zubehoerElem['betriebsspannung'])){					
						if($kabelsystemIstGesetzt == TRUE && $kabelsystemePassend == TRUE){
							$istZubehoer = TRUE;
						}
					}
				}
				// Zubehör: Kabel.
				else if($zubehoerElem['kategorie'] == 'Kabel'){ 
				
					if($betriebsspannungText == $zubehoerElem['ausgangsspannung'] || $ausgangsspannungText == $zubehoerElem['betriebsspannung']){
						if($kabelsystemIstGesetzt == TRUE && $zubehoerElem['kabelsystemIstGesetzt'] == TRUE){
							if($kabelsystemePassend == TRUE){
								$istZubehoer = TRUE;
							}
						}else{
							if(in_array('2-Adrig', $kabelsystemeTexte)){
								if(in_array('2-Adrig', $zubehoerElem['kabelsysteme']) || in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
									$istZubehoer = TRUE;
								}
							}else if(in_array('3-Adrig', $kabelsystemeTexte)){
								if(in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
									$istZubehoer = TRUE;
								}
							}
						}		
					}
				}
				// Zubehör: Muffe.
				else if($zubehoerElem['kategorie'] == "Muffe"){
					if($kabelsystemIstGesetzt == FALSE && ($zubehoerElem['geeignetfuerkabeldurchmesser']).trim() != "" && $kabeldurchmesser != NULL){
						$splitParts = explode('-', $zubehoerElem['geeignetfuerkabeldurchmesser']);
						
						$a = intval($splitParts[0]);
						$b = intval($splitParts[1]);
						
						if($kabeldurchmesser >= $a && $kabeldurchmesser <= $b){
							$istZubehoer = TRUE;
						}
					}
				}
				// Zubehör: Sonstiges.
				else if($zubehoerElem['kategorie'] == "Sonstiges"){
					if($sonstigeZuordnungenUeberschneidungen[0] != FALSE || ($kabelsystemIstGesetzt == TRUE && $zubehoerElem['kabelsystemIstGesetzt'] == TRUE && $kabelsystemePassend == TRUE)){
						$istZubehoer = TRUE;
					}
					if($lampensystemGleich == TRUE){
						$istZubehoer = TRUE;
					}
				}
			}
			// -------------------------------------------------------------------------------------------------------------------
			// - Hauptkategorie: Lampe (Leuchtmittel) 
			// -------------------------------------------------------------------------------------------------------------------
			else if($hauptKategorie == 'Lampe'){ 
			
				// Zubehör: Leuchte.
				if($zubehoerElem['kategorie'] == 'Leuchte' && $sockelPasstZuFassungen == TRUE){ 
					if(in_array("Zubehör", $zubehoerElem['alleKategorien']) == TRUE){
						$istZubehoer = TRUE;
					}
				}
				// Zubehör: Lampe
				else if($zubehoerElem['kategorie'] == 'Lampe'){
					if($sockelPasstZuFassungen == TRUE){
						$istZubehoer = TRUE;
					}
				}
				// Zubehör: Vorschaltgerät.
				else if($zubehoerElem['kategorie'] == 'Vorschaltgerät'){
					if($betriebsspannungText == $zubehoerElem['ausgangsspannung'] || $ausgangsspannungText == $zubehoerElem['betriebsspannung']){
						
						// XXX Bei Lampen nur Vorschaltgeräte ohne Kabelsystem ins Zubehör.
						// 10.10.2018
						if($zubehoerElem['kabelsystemIstGesetzt'] == FALSE){
							$istZubehoer = TRUE;
						}
					}
				}
				// Zubehör: Fassung.
				else if($zubehoerElem['kategorie'] == 'Fassung'){ 	
					if($sockelPasstZuFassungen == TRUE){
						$istZubehoer = TRUE;
					}
				}
				// Zubehör: Kabel.
				else if($zubehoerElem['kategorie'] == "Kabel"){
					if($sockelPasstZuFassungen == TRUE){
						$istZubehoer = TRUE;
					}
				}
				// Zubehör: Sonstiges.
				else if($zubehoerElem['kategorie'] == "Sonstiges"){
					if($lampensystemGleich == TRUE || ($kabelsystemIstGesetzt == TRUE && $kabelsystemePassend == TRUE) ||
						$sockelPasstZuFassungen == TRUE || $bauformenPassen == TRUE){
						$istZubehoer = TRUE;
					}
				}
			}
			// -------------------------------------------------------------------------------------------------------------------
			// - Hauptkategorie: Kabel 
			// -------------------------------------------------------------------------------------------------------------------
			else if($hauptKategorie == 'Kabel'){
				
				// Zubehör: Lampe.
				if($zubehoerElem['kategorie'] == 'Lampe'){
					if(count($fassungenTexte) > 0 && $sockelPasstZuFassungen == TRUE){
						$istZubehoer = TRUE;
					}
				}
				// Zubehör: Kabel.
				else if($zubehoerElem['kategorie'] == 'Kabel'){
					if($kabelsystemIstGesetzt == TRUE  && $zubehoerElem['kabelsystemIstGesetzt'] && $kabelsystemePassend == TRUE ){
						$istZubehoer = TRUE;
					}
					else if($kabelsystemIstGesetzt == FALSE && in_array('2-Adrig', $kabelsystemeTexte)){
						if(in_array('2-Adrig', $zubehoerElem['kabelsysteme']) || in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
							$istZubehoer = TRUE;
						}
					}else if($kabelsystemIstGesetzt == FALSE && in_array('3-Adrig', $kabelsystemeTexte)){
						if(in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
							$istZubehoer = TRUE;
						}
					}
					if($kabelsystemIstGesetzt == FALSE && $zubehoerElem['kabelsystemIstGesetzt'] == FALSE){
						$istZubehoer = TRUE;
					}
				}
				// Zubehör: Vorschaltgerät.
				else if($zubehoerElem['kategorie'] == 'Vorschaltgerät' ){
					if($kabelsystemIstGesetzt == TRUE && $zubehoerElem['kabelsystemIstGesetzt'] == TRUE && $kabelsystemePassend == TRUE){
						$istZubehoer = TRUE;
					}else if($kabelsystemIstGesetzt == FALSE){
						if($zubehoerElem['ausgangsspannung'] == $betriebsspannungText || $zubehoerElem['betriebsspannung'] == $ausgangsspannungText){
							if(count($kabelsystemeTexte) > 0 && $kabelsystemIstGesetzt == TRUE && $kabelsystemePassend == TRUE ){ 
								$istZubehoer = TRUE;
							}else if($kabelsystemIstGesetzt == FALSE && $zubehoerElem['kabelsystemIstGesetzt'] == FALSE && in_array('2-Adrig', $kabelsystemeTexte)){
								if(in_array('2-Adrig', $zubehoerElem['kabelsysteme']) || in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
									$istZubehoer = TRUE;
								}
							}else if($kabelsystemIstGesetzt == FALSE && $zubehoerElem['kabelsystemIstGesetzt'] == FALSE && in_array('3-Adrig', $kabelsystemeTexte)){
								if(in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
									$istZubehoer = TRUE;
								}
							}
						}
					}
				}
				// Zubehör: Muffen.
				else if($zubehoerElem['kategorie'] == 'Muffe'){
					if(($zubehoerElem['geeignetfuerkabeldurchmesser']).trim() != "" && $kabeldurchmesser != NULL){
						$splitParts = explode('-', $zubehoerElem['geeignetfuerkabeldurchmesser']);
						
						$a = intval($splitParts[0]);
						$b = intval($splitParts[1]);
						
						if($kabeldurchmesser >= $a && $kabeldurchmesser <= $b){
							$istZubehoer = TRUE;
						}
					}
				}
				// Zubehör: Fassung.
				else if($zubehoerElem['kategorie'] == 'Fassung'){
					if($kabelsystemIstGesetzt == TRUE && $zubehoerElem['kabelsystemIstGesetzt'] == TRUE && $kabelsystemePassend == TRUE){
						$istZubehoer = TRUE;
					}
				}
				// Zubehör: Sonstiges.
				else if($zubehoerElem['kategorie'] == "Sonstiges"){
					if($kabelsystemIstGesetzt == TRUE && $zubehoerElem['kabelsystemIstGesetzt'] == TRUE && $kabelsystemePassend == TRUE){
						$istZubehoer = TRUE;
					}
				}
			}
			// -------------------------------------------------------------------------------------------------------------------
			// - Hauptkategorie: Vorschaltgerät 
			// -------------------------------------------------------------------------------------------------------------------
			else if($hauptKategorie == 'Vorschaltgerät'){
				
				// Zubehör: Leuchte.
				if($zubehoerElem['kategorie'] == 'Leuchte'){
					// ...
				}
				// Zubehör: Leuchtmittel
				else if($zubehoerElem['kategorie'] == 'Lampe'){
					// ...
				}
				// Zubehör: Kabel.
				else if($zubehoerElem['kategorie'] == 'Kabel'){
					if($zubehoerElem['ausgangsspannung'] == $betriebsspannungText || $zubehoerElem['betriebsspannung'] == $ausgangsspannungText){
						if($kabelsystemIstGesetzt == TRUE && $kabelsystemePassend == TRUE){
							$istZubehoer = TRUE;
						}
						else if($kabelsystemIstGesetzt == FALSE && in_array('2-Adrig', $kabelsystemeTexte)){
							if(in_array('2-Adrig', $zubehoerElem['kabelsysteme']) || in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
								$istZubehoer = TRUE;
							}
						}else if($kabelsystemIstGesetzt == FALSE && in_array('3-Adrig', $kabelsystemeTexte)){
							if(in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
								$istZubehoer = TRUE;							
							}
						}
					}
				}
				// Zubehör: Muffen.
				else if($zubehoerElem['kategorie'] == 'Muffe'){
					if(($zubehoerElem['geeignetfuerkabeldurchmesser']).trim() != "" && $kabeldurchmesser != NULL){
						$splitParts = explode('-', $zubehoerElem['geeignetfuerkabeldurchmesser']);
						
						$a = intval($splitParts[0]);
						$b = intval($splitParts[1]);
						
						if($kabeldurchmesser >= $a && $kabeldurchmesser <= $b){
							$istZubehoer = TRUE;
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
			// - Hauptkategorie: Muffe 
			// -------------------------------------------------------------------------------------------------------------------
			else if($hauptKategorie == 'Muffe'){	
			
				// Zubehör: Kabel.
				if($zubehoerElem['kategorie'] == 'Kabel'){
					if(($geeignetfuerkabeldurchmesser).trim() != "" && ($zubehoerElem['kabeldurchmesser']).trim() != ""){
						$splitParts = explode('-', $geeignetfuerkabeldurchmesser);
						
						$a = intval($splitParts[0]);
						$b = intval($splitParts[1]);
						
						if($zubehoerElem['kabeldurchmesser'] >= $a && $zubehoerElem['kabeldurchmesser'] <= $b){
							$istZubehoer = TRUE;
						}
					}
				} 
			}
			// -------------------------------------------------------------------------------------------------------------------
			// - Hauptkategorie: Fassung 
			// -------------------------------------------------------------------------------------------------------------------
			else if($hauptKategorie == 'Fassung'){
				
				// Zubehör: Leuchte.
				if($zubehoerElem['kategorie'] == "Leuchte"){
					if(count($fassungenTexte) > 0 && $sockelPasstZuFassungen == TRUE){
						$istZubehoer = TRUE;
					}
				}	
				// Zubehör: Lampe.
				else if($zubehoerElem['kategorie'] == 'Lampe'){
					if(count($fassungenTexte) > 0 && $sockelPasstZuFassungen == TRUE){
						$istZubehoer = TRUE;
					}
				}
				// Zubehör: Fassung.
				else if($zubehoerElem['kategorie'] == "Fassung"){
					if(count($fassungenTexte) > 0 && $sockelPasstZuFassungen === TRUE){
						$istZubehoer = TRUE;		
					}
				}		
				// Zubehör: Kabel.
				else if($zubehoerElem['kategorie'] == "Kabel" ){ 
					if((count($fassungenTexte) > 0 || count($zubehoerElem['fassungen']) > 0) && $sockelPasstZuFassungen === TRUE){
						$istZubehoer = TRUE;
					}
					if(($kabelsystemIstGesetzt == TRUE && $kabelsystemePassend == TRUE)){
						$istZubehoer = TRUE;
					}
					if((count($fassungenTexte) == 0 || count($zubehoerElem['fassungen'] == 0) 
						&& ($kabelsystemIstGesetzt == FALSE && $zubehoerElem['kabelsystemIstGesetzt'] == FALSE))){
						
						if(in_array('2-Adrig', $kabelsystemeTexte)){
							if(in_array('2-Adrig', $zubehoerElem['kabelsysteme']) || in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
								$istZubehoer = TRUE;
							}
						}else if(in_array('3-Adrig', $kabelsystemeTexte)){
							if(in_array('3-Adrig', $zubehoerElem['kabelsysteme'])){
								$istZubehoer = TRUE;
							}
						}
					}
				}
				// Zubehör: Vorschaltgerät.
				else if($zubehoerElem['kategorie'] == "Vorschaltgerät"){ 
					if($zubehoerElem['ausgangsspannung'] == $betriebsspannungText){
						$istZubehoer = TRUE;
					}
				}
				// Zubehör: Sonstiges.
				else if($zubehoerElem['kategorie'] == "Sonstiges"){
					if(in_array('MR16', $zubehoerElem['geeignetFuerBauformen']) == TRUE){
						if($sockel == "GU5.3" || in_array("GU5.3", $fassungenTexte)){
							$istZubehoer = TRUE;
						}
					}
					if(in_array('PAR16', $zubehoerElem['geeignetFuerBauformen']) == TRUE){
						if($sockel == "GU10" || in_array("GU10", $fassungenTexte)){
							$istZubehoer = TRUE;
						}
					}
				}
			}
			// -------------------------------------------------------------------------------------------------------------------
			// - Hauptkategorie: Sonstiges 
			// -------------------------------------------------------------------------------------------------------------------
			else if($hauptKategorie == "Sonstiges"){
				
				// Zubehör: Lampen.
				if($zubehoerElem['kategorie'] == 'Lampe'){ 
					if($lampensystemGleich == TRUE){
						$istZubehoer = TRUE;
					}
					if(in_array('MR16', $geeignetFuerBauformTexte) == TRUE){
						if($zubehoerElem['sockel'] == "GU5.3" || in_array("GU5.3", $zubehoerElem['fassungen'])){
							$istZubehoer = TRUE;
						}
					}
					if(in_array('PAR16', $geeignetFuerBauformTexte) == TRUE){
						if($zubehoerElem['sockel'] == "GU10" || in_array("GU10", $zubehoerElem['fassungen'])){
							$istZubehoer = TRUE;
						}
					}
					
				}
				// Zubehör: Leuchten.
				else if($zubehoerElem['kategorie'] == 'Leuchte'){
					if($sonstigeZuordnungenUeberschneidungen[0] != FALSE || ($kabelsystemIstGesetzt == TRUE && $zubehoerElem['kabelsystemIstGesetzt'] == TRUE && $kabelsystemePassend == TRUE)){
						$istZubehoer = TRUE;
					}
				}
				// Zubehör: Fassungen.
				else if($zubehoerElem['kategorie'] == 'Fassung'){
					if(in_array('MR16', $geeignetFuerBauformTexte) == TRUE){
						if($zubehoerElem['sockel'] == "GU5.3" || in_array("GU5.3", $zubehoerElem['fassungen'])){
							$istZubehoer = TRUE;
						}
					}
					if(in_array('PAR16', $geeignetFuerBauformTexte) == TRUE){
						if($zubehoerElem['sockel'] == "GU10" || in_array("GU10", $zubehoerElem['fassungen'])){
							$istZubehoer = TRUE;
						}
					}
				}
				// Zubehör: Sonstiges.
				else if($zubehoerElem['kategorie'] == "Sonstiges"){
					if($lampensystemGleich == TRUE || ($kabelsystemIstGesetzt == TRUE && $kabelsystemePassend == TRUE)){
						$istZubehoer = TRUE;
					}
				}
			}
			
			// Wenn sich der Artikel als passendes Zubehör herausstellt, füge ihm den Zubehör-Daten hinzu.
			if($istZubehoer == TRUE){
				$zubehoerDaten[$zubehoerIdx] = array('position' => $zubehoerElem['position']);
			}else{
				if(array_key_exists($zubehoerIdx, $zubehoerDaten) == TRUE){
					unset($zubehoerDaten[$zubehoerIdx]);
				}
			}
		}
		
		// Fortschrittsanzeige.
		system("clear");
		fwrite(STDOUT, "Gathering affiliated products.\n");
		fwrite(STDOUT, "[ Finished\t-\t Progress: 100%]\n");
		fwrite(STDOUT, "Add affiliated to corresponding Products.\n");
		$progress = intval(($productId/$countTotalProducts)*100);
		fwrite(STDOUT, "[ At id: " . $productId . "\t-\t Progress: " . $progress . "%]\n");
		$progressBar = "|";
		for($i=0; $i<100/3; ++$i){
			if($i <= ($progress/3)){ $progressBar .= "-"; }
			else{ $progressBar .= " "; }
		}
		$progressBar .= "|";
		fwrite(STDOUT, $progressBar);
	
		// Speichere Änderungen in die Datenbank.
		Mage::getResourceModel('catalog/product_link')->saveProductLinks(
			$product, $zubehoerDaten, Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED
		);
	}
	
	// ------------------------------------------------------------------------------------------------------------------	
	
	// Zeitmessung.
	$time_end = microtime(true) - $start;
	$time = round($time_end, 2);
	fwrite(STDOUT, "\n\nSuccess! Script took ". intval($time/60) ."m ". intval($time % 60) ."s seconds to execute!");
?>
