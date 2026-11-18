<?php
/**
 * Create the database and tables for the login system
 *
 * @author    Joshua Connor <connorj4@southernct.edu>
 * @copyright 2026 
 * @date      2026-02-24
 * @version   1.0
 */

error_reporting(-1);
ini_set('display_errors', 'On');

// End session if one has been created during development //
session_start();

echo "<pre>";
print_r($_SESSION);
echo "</pre>";
$_SESSION = array();
session_destroy();

/* the first connection to the database */
DEFINE('DB_HOST', "localhost");
DEFINE('DB_USER', "root");
DEFINE('DB_PASSWORD', ""); //Note: this should be your root password

// Establish the initial database connection
try {
  $db_connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD)
    OR die("Connection failed: " . $db_connection->connect_error);
} catch (Exception $e) {
  echo 'Caught exception: ',  $e->getMessage(), nl2br("\r\n");
}

/* Check if database is there or will create it */
$create_stmt = "CREATE DATABASE IF NOT EXISTS login_db";

/* Check if database drop was sucessful */
if(mysqli_query($db_connection, $create_stmt)) {
	echo nl2br("Database was successfully created.\r\n");
} else {
	echo "Error dropping database: " . mysqli_error() . nl2br("\r\n");
}
$prep_stmt = $db_connection -> prepare($create_stmt);
$prep_stmt->execute();
$prep_stmt->close();

/* Change to the created database */
$db_connection->select_db("login_db");

/* Drop all tables for clean install */
$db_connection->query('SET foreign_key_checks = 0');
if ($result = $db_connection->query("SHOW TABLES")) {
  while($row = $result->fetch_array(MYSQLI_NUM)) {
    $db_connection->query('DROP TABLE IF EXISTS '.$row[0]);
	}
	echo "Tables removed successfully." . nl2br("\r\n");
} else {
	echo "No tables were removed." . nl2br("\r\n");
}
$db_connection->query('SET foreign_key_checks = 1');


/* Salt used for seasoning */
$salt = 'authentication';

//-----------------------------------------------------
// Create Database Tables
//-----------------------------------------------------
echo "Table creation started." . nl2br("\r\n");

/* Below Are Tables That Have Only Primary Keys */
/* ------------------------------------------ */

/* Role */
$create_roles = $db_connection->prepare(
	"CREATE OR REPLACE TABLE Roles(
        role_id int NOT NULL AUTO_INCREMENT,
        role_type varchar(255) NOT NULL,
        PRIMARY KEY(role_id));");
$create_roles->execute();
$create_roles->close();

/* Contacts */
$create_contacts = $db_connection->prepare(
	"CREATE OR REPLACE TABLE Contacts(
        contact_id int NOT NULL AUTO_INCREMENT,
        last_name varchar(255) NOT NULL,
        first_name varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        phone varchar(255) NOT NULL,
        street_1 varchar(255),
        street_2 varchar(255),
        city varchar(255),
        state_code varchar(255),
        post_code int(5),
        updated timestamp,
        PRIMARY KEY(contact_id));");
$create_contacts->execute();
$create_contacts->close();

/*Authors*/
$create_authors = $db_connection->prepare(
        "CREATE OR REPLACE TABLE Authors(
        author_id int NOT NULL AUTO_INCREMENT,
        first_name varchar(255) NOT NULL,
        last_name varchar(255) NOT NULL,
        PRIMARY KEY(author_id));");
$create_authors->execute();
$create_authors->close();

/*Category*/
$create_category = $db_connection->prepare(
        "CREATE OR REPLACE TABLE Category(
        category_id int NOT NULL AUTO_INCREMENT,
        category_name varchar(255) NOT NULL,
        category_description varchar(255) NOT NULL,
        PRIMARY KEY (category_id));");
$create_category->execute();
$create_category->close();

/*Publisher*/
$create_publisher = $db_connection->prepare(
        "CREATE OR REPLACE TABLE Publishers(
        pub_id int NOT NULL AUTO_INCREMENT,
        publisher_name varchar(255) NOT NULL,
        publisher_address varchar(255),
        PRIMARY KEY (pub_id));");
$create_publisher->execute();
$create_publisher->close();

/* Below Are Tables That Have Foreign Keys */
/* ------------------------------------------ */

/* Users */
$create_users = $db_connection->prepare(
	"CREATE OR REPLACE TABLE Users(
        user_id int NOT NULL AUTO_INCREMENT,
        role_id int NOT NULL,
        contact_id int NOT NULL,
	    creation_date timestamp,
	    PRIMARY KEY(user_id),
        FOREIGN KEY(role_id) REFERENCES Roles(role_id),
        FOREIGN KEY(contact_id) REFERENCES Contacts(contact_id));");
$create_users->execute();
$create_users->close();

/* Credentials */
$create_credentials = $db_connection->prepare(
	"CREATE OR REPLACE TABLE Credentials(
        username varchar(255) NOT NULL,
	    user_id int NOT NULL,
        password_salted varchar(255) NOT NULL,
	    PRIMARY KEY(username),
        FOREIGN KEY(user_id) REFERENCES Users(user_id));");
$create_credentials->execute();
$create_credentials->close();

/*Book*/
$create_books = $db_connection->prepare(
        "CREATE OR REPLACE TABLE Books(
        book_id int NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        isbn varchar(20) NOT NULL,
        book_volume varchar(20) NOT NULL,
        publication_year year NOT NULL,
        pub_id int NOT NULL,
        PRIMARY KEY(book_id),
        FOREIGN KEY(pub_id) REFERENCES Publishers(pub_id));");
$create_books->execute();
$create_books->close();

/*Book Author*/
/*Composite Key*/
$create_bookAuthors = $db_connection->prepare(
        "CREATE OR REPLACE TABLE BookAuthors(
        author_id int NOT NULL AUTO_INCREMENT,
        book_id int NOT NULL,
        PRIMARY KEY(author_id,book_id),
        FOREIGN KEY(author_id) REFERENCES Authors(author_id),
        FOREIGN KEY(book_id) REFERENCES Books(book_id))");
$create_bookAuthors->execute();
$create_bookAuthors->close();

/*Book Category*/
/*Composite Key*/
$create_bookCategories = $db_connection->prepare(
        "CREATE OR REPLACE TABLE BookCategories(
        category_id int NOT NULL AUTO_INCREMENT,
        book_id int NOT NULL,
        PRIMARY KEY(category_id,book_id),
        FOREIGN KEY(category_id) REFERENCES Category(category_id),
        FOREIGN KEY(book_id) REFERENCES Books(book_id))");
$create_bookCategories->execute();
$create_bookCategories->close();

/*Book Copy*/
$create_bookCopies = $db_connection->prepare(
        "CREATE OR REPLACE TABLE BookCopies(
        copy_id int NOT NULL AUTO_INCREMENT,
        barcode int NOT NULL UNIQUE,
        book_id int NOT NULL,
        PRIMARY KEY(copy_id),
        FOREIGN KEY(book_id) REFERENCES Books(book_id))");
$create_bookCopies->execute();
$create_bookCopies->close();

/*Loan */
$create_loans = $db_connection->prepare(
        "CREATE OR REPLACE TABLE Loans(
        loan_id int NOT NULL AUTO_INCREMENT,
        user_id int NOT NULL,
        copy_id int NOT NULL,
        checkout_date date NOT NULL,
        due_date date NOT NULL,
        return_date date,
        loan_status varchar(20),
        fine decimal(8,2) DEFAULT(0),
        PRIMARY KEY(loan_id),
        FOREIGN KEY(user_id) REFERENCES Users(user_id),
        FOREIGN KEY(copy_id) REFERENCES BookCopies(copy_id))");
$create_loans->execute();
$create_loans->close();

/*Payment */
$create_payments = $db_connection->prepare(
        "CREATE OR REPLACE TABLE Payments(
        payment_id int NOT NULL AUTO_INCREMENT,
        loan_id int NOT NULL,
        payment_day date,
        payment_method varchar(20),
        amount_paid decimal(8,2) DEFAULT(0),
        PRIMARY KEY(payment_id),
        FOREIGN KEY(loan_id) REFERENCES Loans(loan_id))");
$create_payments->execute();
$create_payments->close();

/*Reservations */
$create_reservations = $db_connection->prepare(
        "CREATE OR REPLACE TABLE Reservations(
        reservation_id int NOT NULL AUTO_INCREMENT,
        user_id int NOT NULL,
        book_id int NOT NULL,
        reservation_date date NOT NULL,
        expire_date date NOT NULL,
        reservation_status varchar(20),
        PRIMARY KEY(reservation_id),
        FOREIGN KEY(user_id) REFERENCES Users(user_id),
        FOREIGN KEY(book_id) REFERENCES Books(book_id))");
$create_reservations->execute();
$create_reservations->close();
/* Status Display */
echo nl2br("The database tables were successfully created.\r\n");


//-----------------------------------------------------
// Populate Tables of Database
//-----------------------------------------------------

/* Roles */
$insert_role = $db_connection->prepare(
	"INSERT INTO Roles
    	(role_id, role_type) VALUES(?,?);");
$insert_role->bind_param(
        "is",
         $role_id, $role_title);

$role_id = 1;
$role_title = "administrator";
$insert_role->execute();

$role_id = 2;
$role_title = "user";
$insert_role->execute();

$role_id = 3;
$role_title = "guest";
$insert_role->execute();

$insert_role->close();

/* Contacts */
$insert_contacts = $db_connection->prepare(
	"INSERT INTO Contacts
		(contact_id, last_name, first_name, email, phone, street_1, street_2, city, state_code, post_code, updated) VALUES(?,?,?,?,?,?,?,?,?,?,?);");
$insert_contacts->bind_param(
        "issssssssis",
        $contact_id, $last_name, $first_name, $email, $phone,
        $street_1, $street_2, $city, $state_code, $post_code, $updated);
$contact_id = 1;
$last_name = "Doe";
$first_name = "John";
$email = "johndoe@example.com";
$phone = "123-456-7890";
$street_1 = "123 Main St";
$street_2 = "";
$city = "Anytown";
$state_code = "CA";
$post_code = 12345;
$updated = date("Y-m-d H:i:s");
$insert_contacts->execute();

$contact_id = 2;
$last_name = "Smith";
$first_name = "Jane";
$email = "janesmith@example.com";
$phone = "987-654-3210";
$street_1 = "456 Elm St";
$street_2 = "Apt 2";
$city = "Othertown";
$state_code = "NY";
$post_code = 54321;
$updated = date("Y-m-d H:i:s");
$insert_contacts->execute();

$contact_id = 3;
$last_name = "Brown";
$first_name = "Charlie";
$email = "charliebrown@example.com";
$phone = "555-555-5555";
$street_1 = "789 Oak St";
$street_2 = "";
$city = "Sometown";
$state_code = "TX";
$post_code = 67890;
$updated = date("Y-m-d H:i:s");
$insert_contacts->execute();

$insert_contacts->close();

/* Users */
$insert_users = $db_connection->prepare(
	"INSERT INTO Users
		(user_id, role_id, contact_id, creation_date) VALUES(?,?,?,?);");
$insert_users->bind_param(
        "iiis",
        $user_id, $role_id, $contact_id, $creation_date);
$user_id = 1;
$role_id = 1;
$contact_id = 1;
$creation_date = date("Y-m-d H:i:s");
$insert_users->execute();

$user_id = 2;
$role_id = 2;
$contact_id = 2;
$creation_date = date("Y-m-d H:i:s");
$insert_users->execute();

$user_id = 3;
$role_id = 3;
$contact_id = 3;
$creation_date = date("Y-m-d H:i:s");
$insert_users->execute();

$insert_users->close();



/* Credentials */
$insert_credentials = $db_connection->prepare(
	"INSERT INTO Credentials
		(username, user_id, password_salted) VALUES(?,?,?);");
$insert_credentials->bind_param("sis", $username, $user_id, $password_salted);
$username = "johndoe";
$user_id = 1;
$password_salted = crypt("SCSU2024", $salt);
//$password_salted = password_hash("password123", PASSWORD_DEFAULT);
$insert_credentials->execute();

$user_id = 2;
$username = "janesmith";
$password_salted = crypt("SCSU2025", $salt);
//$password_salted = password_hash("mypassword", PASSWORD_DEFAULT);
$insert_credentials->execute();

$user_id = 3;
$username = "charliebrown";
$password_salted = crypt("SCSU2026", $salt);
//$password_salted = password_hash("charlie123", PASSWORD_DEFAULT);
$insert_credentials->execute();
//End query
$insert_credentials->close();
/*
 Insert Publisher Data
 */
$db_connection -> query(
        "INSERT INTO Publishers (pub_id, publisher_name,publisher_address) VALUES
(1, 'Scribner', '123 Main St , New York, NY 10001'),
(2, 'Penguin', '456 Oak St, New York, NY 10002'),
(3, 'HarperCollins', '789 Pine St, New York, NY 10003'),
(4, 'St. Martin''s Press', '101 Elm St, New York, NY 10004'),
(5, 'George Allen & Unwin', '101 Oxford Street, London, UK W1D 1AA');");

/*Author */
$insert_author = $db_connection->prepare(
        "INSERT INTO Authors
        (author_id, first_name, last_name) VALUES(?,?,?);");
$insert_author->bind_param("iss",$author_id,$author_firstname,$author_lastname);


$author_id = 1;
$author_firstname = "F. Scott";
$author_lastname = "Fitzgerald";
$insert_author->execute();
//*****************************/
// INSERT AUTHORS
//*****************************/
$db_connection -> query(
        "INSERT INTO authors (author_id, first_name, last_name) VALUES
(2, 'Kristin', 'Hannah'),
(3, 'Harper', 'Lee'),
(4, 'George', 'Orwell'),
(5, 'Terry', 'Pratchett'),
(6, 'Neil', 'Gaiman'),
(7, 'Ernest', 'Hemingway');");

$insert_author->close();
//*****************************/
// INSERT CATEGORY
//*****************************/
$insert_category = $db_connection->prepare(
        "INSERT INTO Category
        (category_id,category_name,category_description) VALUES(?,?,?)");
$insert_category->bind_param("iss",$category_id,$category_name,$category_desc);
/*REMEMBER TO ADD IN DATA */
$category_id = 1;
$category_name = "Sci-Fy";
$category_desc = "Science fiction books that explore futuristic concepts, advanced technology, and imaginative worlds.";
$insert_category->execute();
$insert_category->close();

$db_connection -> query(
        "INSERT INTO Category (category_id, category_name, category_description) VALUES
        (2,'Fantasy','Stories featuring magical elements, mythical creatures, or imaginary worlds'),
        (3,'Mystery','Stories centered around solving crimes, puzzles, or unexplained events'),
        (4,'Romance','Stories focused on relationships, love, and emotional connections'),
        (5,'Thriller','Fast-paced stories filled with suspense, tension, and high stakes'),
        (6,'Historical Fiction','Fictional stories set during real historical periods or events'),
        (7,'Non-Fiction','Works based on real people, facts, events, or experiences'),
        (8,'Math','Books covering mathematical concepts, theories, and problem-solving techniques'),
        (9,'Children''s Literature','Books written primarily for young readers and learners'),
        (10,'Horror','Stories intended to create fear, suspense, or a sense of unease');");

//*****************************/
// INSERT BOOKS
//*****************************/
$db_connection -> query(
        "INSERT INTO Books (book_id, title, isbn, book_volume, publication_year, pub_id) VALUES
    (1,'The Great Gatsby', '978-0-7432-0001-1', 1, 1925, 1),
        (2,'Tender Is The Night', '978-0-7432-0002-2',1,1934, 1),
        (3,'To Kill a Mockingbird', '978-0-0609-0003-3', 1, 1960, 3),
        (4,'1984', '978-0-7432-0004-4', 1, 1949, 2),
        (5,'The Hobbit', '978-0-0609-0005-5', 1, 1937, 4),
        (6,'The Great Alone', '978-0-7432-0006-6',1,2018, 4),
        (7,'Animal Farm','978-0-0609-0007-7',1,1945,2),
        (8,'Good Omens','978-0-0609-0008-8',1,1990,3),
        (9,'The Good Man and the sea','978-0-0609-0009-9',1,1952,1);");

//*****************************/
// INSERT BOOK-AUTHORS//
//*****************************/
$db_connection -> query(
        "INSERT INTO BookAuthors (author_id, book_id) VALUES
        (1,1),
        (1,2),
        (3,3),
        (2,4),
        (4,5),
        (4,6),
        (2,7),
        (3,8),
        (1,9);");
$db_connection -> query(
        "INSERT INTO BookCopies (copy_id, barcode, book_id) VALUES
        (1, 100001,1),
        (2, 100002,1),
        (3, 100003,2),
        (4, 100004,2),
        (5, 100005,3),
        (6, 100006,3),
        (7, 100007,4),
        (8, 100008,4),
        (9, 100009,5),
        (10, 100010,6),
        (11, 100011,6),
        (12, 100012,7),
        (13, 100013,8),
        (14, 100014,8);");
/* Status Display */
echo nl2br("The database tables were successfully populated.\r\n");
/* Return to homepage after 5 seconds */
header( "refresh:10;url=/csc235_login" );

/* ALWAYS CLOSE THE DB CONNECTION */
$db_connection->close();

?>
