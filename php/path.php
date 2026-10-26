<?php
//======================================================================
// DATABASE CONNECTION
//======================================================================

$directory = "/csc235_login"; // Change to your project folder

/* Define base url path */
DEFINE("BASE_URL", $directory);
DEFINE("ROOT_PATH", $_SERVER["DOCUMENT_ROOT"] . $directory);
DEFINE("ROOT_SRC_PATH", $_SERVER["DOCUMENT_ROOT"] . $directory . "/php");
DEFINE("SRC_PATH", $directory . "/php");
?>