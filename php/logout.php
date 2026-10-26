<?php
//======================================================================
// LOGOUT
//======================================================================

include_once (realpath(dirname(__FILE__).'/path.php'));

session_start();
   
if(session_destroy()) {
  header("Location: " . BASE_URL);
}

?>