<?php
/*
	Un simple exemple pour l'intégration avec l'interface de shopping
	Pour recevoir des paiements directes (SCI) - www.edrahem.com
	*/
	
define("SHOP_ID","IDENTIFIANT DE VOTRE COMMERCE");
define("CLE_PRIVE", "LA CLE PRIVE");

$commandId = 5; // Numéro de la commande
$note = base64_encode("Achat d'un iPhone 4"); // Commentaire sur la commande
$montant = 5000; // 5000 DA

// Facultatifs
$date_created = date("Y-m-d");
$utilisateur = "edrahem";
	
$arHash = array(
	SHOP_ID,
	$commandId,
	$montant,
	$note
);

$hash = base64_encode(hash_hmac("sha512", implode(":", $arHash), CLE_PRIVE, TRUE));
?>
<form name="my_form" method="GET" action="https://www.edrahem.com/sci/">
<input type="hidden" name="shop_id" value="<?php echo SHOP_ID;?>">
<input type="hidden" name="command_id" value="<?php echo $commandId;?>">
<input type="hidden" name="montant" value="<?php echo $montant;?>">
<input type="hidden" name="hash" value="<?php echo $hash;?>">
<input type="hidden" name="note" value="<?php echo $note;?>">
<!-- Facultatifs début -->
<input type="hidden" name="autre" value="date_created utilisateur">
<input type="hidden" name="date_created" value="<?php echo $date_created;?>">
<input type="hidden" name="utilisateur" value="<?php echo $utilisateur;?>">
<!-- Facultatifs fin -->
<input type="submit" name="payer" value="maintenant" />
</form>