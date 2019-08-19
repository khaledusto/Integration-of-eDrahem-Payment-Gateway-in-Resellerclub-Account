<?php
/*
	Un simple exemple pour l'intégration avec l'interface de shopping
	Pour recevoir des paiements directes (SCI) - www.edrahem.com
	*/


//------------------------------------------------------------
//------------------	CONFIGURATION	----------------------
//------------------------------------------------------------

define("EDRAHEM_ACTIVE_PAIEMENT", 1); // 1 - Pour activer les paiements  0 - Pour désactiver les paiements via eDrahem 
define("EDRAHEM_METHOD", "POST"); // Méthode à utiliser pour recevoir les données depuis eDrahem (POST ou GET)

// LOGS DES TRANSACTIONS
define("EDRAHEM_ACTIVE_LOG", 0); // 1- Pour activer les LOGS, 0 - Pour disactiver les LOGS des transactions
define("EDRAHEM_LOG_FILE", ""); // Le fichier ou enregistrer les LOGS des transactions - A laisser vide si vous ne voulez pas de logs
define("EDRAHEM_LOG_EMAIL", ""); // Email pour recevoir les LOGS des transactions en cas d'erreur (Laisser vide si vous voulez pas l'utiliser)

// IDENTIFIANTS DE COMMERCE
define("EDRAHEM_IDENTIFIANT", "IDENTIFIANT DE VOTRE COMMERCE"); // Identifiant de votre commerce
define("EDRAHEM_CLE_PRIVE", "CLE PRIVE DE VOTRE API"); // Clé privé de votre API

//------------------------------------------------------------

if(EDRAHEM_ACTIVE_PAIEMENT == 1) {

	$method = (EDRAHEM_METHOD == 'POST') ? $_POST : $_GET;

	if (isset($method["ED_STATUT"]) && isset($method["ED_HASH"]))
	{
		$err = false;
		$message = '';
		
		// Les logs de transactions
		if(EDRAHEM_ACTIVE_LOG == 1) {
			$log_text = 
			"--------------------------------------------------------\n" .
			"PAIEMENT ID		" . $method['ED_PAIEMENT_ID'] . "\n" .
			"COMPTE PAYEE		" . $method['ED_PAYEE_COMPTE'] . "\n" .
			"COMPTE PAYEUR		" . $method['ED_PAYEUR_COMPTE'] . "\n" .
			"COMMANDE ID		" . $method['ED_COMMAND_ID'] . "\n" .
			"MONTANT			" . $method['ED_MONTANT'] . "\n" .
			"REFERENCE			" . $method['ED_REFERENCE'] . "\n" .
			"STATUT				" . $method['ED_STATUT'] . "\n" .
			"DATE				" . $method['ED_DATE'] . "\n" .
			"AUTRE				" . $method['ED_AUTRE'] . "\n" .
			"NOTE				" . base64_decode($method['ED_NOTE']) . "\n" .
			"BALANCE AVANT		" . $method['ED_BALANCE_AVANT'] . "\n" .
			"BALANCE APRES		" . $method['ED_BALANCE_APRES'] . "\n" .
			"SHOP ID			" . $method['ED_SHOP_ID'] . "\n" .
			"HASH				" . $method['ED_HASH'] . "\n\n";
			
			// Facultatifs - Valeurs envoyées depuis le formulaire optionnellement
			/*
			$log_text .= 
			"--------------------------------------------------------\n" .
			"DATE CREATED		" . $method['date_created'] . "\n" .
			"Utilisateur		" . $method['utilisateur'] . "\n\n";
			*/
			
			$log_file = EDRAHEM_LOG_FILE;
			
			if (!empty($log_file))
			{
				file_put_contents($_SERVER['DOCUMENT_ROOT'] . $log_file, $log_text, FILE_APPEND);
			}
		}
		
		// Vérification de HASH

		$arHash_v2 = array(
			EDRAHEM_IDENTIFIANT,
			$method['ED_PAIEMENT_ID'],
			$method['ED_COMMAND_ID'],
			$method['ED_REFERENCE'],
			$method['ED_MONTANT'],
			$method['ED_PAYEE_COMPTE'],
			$method['ED_PAYEUR_COMPTE'],
			$method['ED_NOTE'],
			$method['ED_STATUS'],
			$method['ED_DATE'],
		);
		$hash_v2 = base64_encode(hash_hmac("sha512", implode(":", $arHash_v2), EDRAHEM_CLE_PRIVE, TRUE));

		if ($method['ED_HASH'] != $hash_v2)
		{
			$message .= " - Le HASH ne correspond pas\n";
			$err = true;
		}
		
		if (!$err)
		{

			$command_id = preg_replace('/[^a-zA-Z0-9_-]/', '', substr($method['ED_COMMAND_ID'], 0, 100));
			
			// Récupérer le montant inscrit dans votre base de donnée
			// A adapter selon votre base de donnée
			$query = mysqli_query("SELECT * FROM votre_table WHERE command_id=".$method['ED_COMMAND_ID']);
			$bdd = mysqli_fetch_assoc($query);
			
			$montant_achat = number_format($bdd['montant'], 2, '.', '');

			// Vérification du montant
			if ($method['ED_MONTANT'] != $montant_achat)
			{
				$message .= " - Mauvais montant\n";
				$err = true;
			}
			
			// Vérifier si elle n'a pas était traité avant
			// La colonne "status" de votre base de donnée doit être vide ou ne contient pas "COMPLETE" ou "ICOMPLETE" à son état initial
			$status_bdd = $bdd["status"];
			
			if($status_bdd == "COMPLETE" || $status_bdd == "INCOMPLETE") {
				$message .= " - Transaction déjà traitée \n";
				$err = true;
			}

			// L'état de la transaction
			if (!$err)
			{
				switch ($method['ED_STATUT'])
				{
					case 'COMPLETE':
						$status = "COMPLETE";
						$comment = 'Transaction reçue avec succès';
						break;
						
					default:
						$status = "INCOMPLETE";
						$comment = 'La transaction n\'est pas complète';
						$message .= ' - La transaction n\'est pas complète' . "\n";
						$err = true;
						break;
				}
				
				
				// MARQUER LA TRANSACTION COMME COMPLETE OU INCOMPLETE SELON LA VALEUR RECU
				mysqli_query("UPDATE votre_table SET status = '$status' WHERE command_id=".$method['ED_COMMAND_ID']);
				
				// TRAITER LA COMMANDE
				if($status == "COMPLETE") {
					// LA TRANSACTION EST COMPLETE
					// DELIVERER LE PRODUIT ACHETE
				} else {
					// IL Y A UNE ERREUR AVEC LA TRANSACTION
					// INVITER L'ACHETEUR A CONTACTER LE SUPPORT POUR RESOUDRE LE PROBLEME
				}

				
			}
		}
		
		if ($err)
		{
			$to = EDRAHEM_LOG_EMAIL;

			if (!empty($to) && EDRAHEM_ACTIVE_LOG == 1)
			{
				$message = "Impossible d'effectuer le paiement par le système eDrahem pour les raisons suivantes:\n\n" . $message . "\n" . $log_text;
				$headers = "From: no-reply@" . $_SERVER['HTTP_HOST'] . "\r\n" . 
				"Content-type: text/plain; charset=utf-8 \r\n";
				mail($to, 'Erreur de paiement', $message, $headers);
			}
		}
	} else {
		header("HTTP/1.1 404 Not Found");
		exit;
	}

} else {
	header("HTTP/1.1 404 Not Found");
	exit;
}
?>