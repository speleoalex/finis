<?php
require_once "../../src/FINIS.php";
$FINIS = new FINIS(array("src_application"=> "."));
//TODO: addExtension dovrebbe fare in modo che "../fncommerce/" diventi parte del codice di FINIS senza però copiarlo, sections e misc vengano copiate qui, include, modules devono invece diventare parte del codice di finis senza però copiarle
$FINIS->addExtension("../../fncommerce/"); 
$FINIS->finis();

