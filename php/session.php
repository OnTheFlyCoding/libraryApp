<?php

//======================================================================
// SESSIONS
//======================================================================

  include_once (realpath(dirname(__FILE__).'/path.php'));
  include_once (ROOT_PATH . '/php/config.php');

  session_start();
  
  $user_check = $_SESSION['login_user'];
  // Check user and get roll session from database

  $select_user = $db_connection->prepare(
    /* Need to update this section FROM table */
    //"SELECT user_id,username,role_id FROM user WHERE username = ?"
    "SELECT 
        u.user_id,
        u.role_id,
        c.first_name,
        cr.username,
        r.role_type
    FROM Users u
    INNER JOIN Contacts c ON u.contact_id = c.contact_id
    INNER JOIN Credentials cr ON u.user_id = cr.user_id
    INNER JOIN Roles r ON u.role_id = r.role_id
    WHERE cr.username = ?");
  $select_user->bind_param("s", $user_check);
  $select_user->execute();
  $select_user->bind_result($user_id, $user_role, $first_name, $username, $user_type);
  $select_user->fetch();

  # session information
  $_SESSION['user_id'] = $user_id;
  $_SESSION['user_role'] = $user_role;
  $_SESSION['user_first'] = $first_name;
  $_SESSION['user_name'] = $username;
  $_SESSION['user_type'] = $user_type;
  
  // check if first_name and user_type are set, if not go to login page
  // This is to prevent users from accessing pages without logging in
  // or if there is no session
  // You can add more session checks as needed
   if(!isset($user_id)){
    header("location: " . BASE_URL . "failed.html");
  }
  if(!isset($first_name)){
    header("location: " . BASE_URL . "failed.html");
  }
  if(!isset($user_type)){
    header("location: " . BASE_URL . "failed.html");
  }

  $select_user->close();
  // Close the mysql connection
  //mysqli_close($db_connection); 

?>
