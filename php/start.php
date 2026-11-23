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

/* Second test user (role 2), for testing that loans/reservations are kept separate per user */
$contact_id = 4;
$last_name = "Jones";
$first_name = "Bob";
$email = "bobjones@example.com";
$phone = "222-333-4444";
$street_1 = "321 Pine St";
$street_2 = "";
$city = "Newtown";
$state_code = "CT";
$post_code = 11111;
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

$user_id = 4;
$role_id = 2; // regular user, same as janesmith
$contact_id = 4;
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

$user_id = 4;
$username = "bobjones";
$password_salted = crypt("SCSU2025", $salt); // same password as janesmith
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
(7, 'Ernest', 'Hemingway'),
(8, 'J.R.R.', 'Tolkien');");

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
        (5,'The Hobbit', '978-0-0609-0005-5', 1, 1937, 5),
        (6,'The Great Alone', '978-0-7432-0006-6',1,2018, 4),
        (7,'Animal Farm','978-0-0609-0007-7',1,1945,2),
        (8,'Good Omens','978-0-0609-0008-8',1,1990,3),
        (9,'The Old Man and the Sea','978-0-0609-0009-9',1,1952,1);");

//*****************************/
// INSERT BOOK-AUTHORS//
//*****************************/
$db_connection -> query(
        "INSERT INTO BookAuthors (author_id, book_id) VALUES
        (1,1),  -- Fitzgerald: The Great Gatsby
        (1,2),  -- Fitzgerald: Tender Is The Night
        (3,3),  -- Lee: To Kill a Mockingbird
        (4,4),  -- Orwell: 1984
        (8,5),  -- Tolkien: The Hobbit
        (2,6),  -- Hannah: The Great Alone
        (4,7),  -- Orwell: Animal Farm
        (5,8),  -- Pratchett: Good Omens
        (6,8),  -- Gaiman: Good Omens (two authors)
        (7,9);");  // Hemingway: The Old Man and the Sea
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

//*****************************/
// INSERT BOOK-CATEGORIES (a book can be in more than one category)
//*****************************/
$db_connection -> query(
        "INSERT INTO BookCategories (category_id, book_id) VALUES
        (4,1),  -- Romance: The Great Gatsby
        (4,2),  -- Romance: Tender Is The Night
        (6,3),  -- Historical Fiction: To Kill a Mockingbird
        (1,4),  -- Sci-Fy: 1984
        (2,5),  -- Fantasy: The Hobbit
        (9,5),  -- Children's Literature: The Hobbit (two categories)
        (6,6),  -- Historical Fiction: The Great Alone
        (2,7),  -- Fantasy: Animal Farm
        (2,8);");  // Fantasy: Good Omens
                   // The Old Man and the Sea has no category: none of the 10 fit it

//*****************************/
// SAMPLE BOOKS 10-50 (test data, so the library has 50 books and pagination shows)
// Same tables and order as above: authors, books, book-authors, copies, categories.
// ISBNs are made up and numbered to match the book_id.
//*****************************/
$db_connection -> query(
        "INSERT INTO Authors (author_id, first_name, last_name) VALUES
        (9,'Aldous','Huxley'), (10,'Ray','Bradbury'), (11,'John','Steinbeck'),
        (12,'J.D.','Salinger'), (13,'William','Golding'), (14,'Toni','Morrison'),
        (15,'Margaret','Atwood'), (16,'Agatha','Christie'), (17,'Frank','Herbert'),
        (18,'Isaac','Asimov'), (19,'Cormac','McCarthy'), (20,'Kurt','Vonnegut'),
        (21,'E.B.','White'), (22,'Delia','Owens'), (23,'Suzanne','Collins');");

$db_connection -> query(
        "INSERT INTO Books (book_id, title, isbn, book_volume, publication_year, pub_id) VALUES
        (10,'The Sun Also Rises','978-0-0000-0010-0',1,1926,1),
        (11,'A Farewell to Arms','978-0-0000-0011-0',1,1929,1),
        (12,'For Whom the Bell Tolls','978-0-0000-0012-0',1,1940,1),
        (13,'This Side of Paradise','978-0-0000-0013-0',1,1920,1),
        (14,'Go Set a Watchman','978-0-0000-0014-0',1,2015,3),
        (15,'The Nightingale','978-0-0000-0015-0',1,2015,4),
        (16,'Firefly Lane','978-0-0000-0016-0',1,2008,4),
        (17,'The Four Winds','978-0-0000-0017-0',1,2021,4),
        (18,'Homage to Catalonia','978-0-0000-0018-0',1,1938,2),
        (19,'Down and Out in Paris and London','978-0-0000-0019-0',1,1933,2),
        (20,'The Colour of Magic','978-0-0000-0020-0',1,1983,3),
        (21,'Mort','978-0-0000-0021-0',1,1987,3),
        (22,'Guards! Guards!','978-0-0000-0022-0',1,1989,3),
        (23,'American Gods','978-0-0000-0023-0',1,2001,3),
        (24,'Coraline','978-0-0000-0024-0',1,2002,3),
        (25,'Neverwhere','978-0-0000-0025-0',1,1996,3),
        (26,'The Fellowship of the Ring','978-0-0000-0026-0',1,1954,5),
        (27,'The Two Towers','978-0-0000-0027-0',2,1954,5),
        (28,'The Return of the King','978-0-0000-0028-0',3,1955,5),
        (29,'Brave New World','978-0-0000-0029-0',1,1932,3),
        (30,'Fahrenheit 451','978-0-0000-0030-0',1,1953,2),
        (31,'The Martian Chronicles','978-0-0000-0031-0',1,1950,2),
        (32,'Of Mice and Men','978-0-0000-0032-0',1,1937,2),
        (33,'The Grapes of Wrath','978-0-0000-0033-0',1,1939,2),
        (34,'East of Eden','978-0-0000-0034-0',1,1952,2),
        (35,'The Catcher in the Rye','978-0-0000-0035-0',1,1951,1),
        (36,'Lord of the Flies','978-0-0000-0036-0',1,1954,2),
        (37,'Beloved','978-0-0000-0037-0',1,1987,2),
        (38,'The Handmaid''s Tale','978-0-0000-0038-0',1,1985,2),
        (39,'Murder on the Orient Express','978-0-0000-0039-0',1,1934,3),
        (40,'And Then There Were None','978-0-0000-0040-0',1,1939,3),
        (41,'Dune','978-0-0000-0041-0',1,1965,2),
        (42,'Foundation','978-0-0000-0042-0',1,1951,2),
        (43,'I, Robot','978-0-0000-0043-0',1,1950,2),
        (44,'The Road','978-0-0000-0044-0',1,2006,2),
        (45,'Slaughterhouse-Five','978-0-0000-0045-0',1,1969,2),
        (46,'Charlotte''s Web','978-0-0000-0046-0',1,1952,3),
        (47,'Where the Crawdads Sing','978-0-0000-0047-0',1,2018,2),
        (48,'The Hunger Games','978-0-0000-0048-0',1,2008,4),
        (49,'Catching Fire','978-0-0000-0049-0',2,2009,4),
        (50,'Mockingjay','978-0-0000-0050-0',3,2010,4);");

/* (author_id, book_id) pairs, grouped by author */
$db_connection -> query(
        "INSERT INTO BookAuthors (author_id, book_id) VALUES
        (7,10), (7,11), (7,12),            -- Hemingway
        (1,13),                            -- Fitzgerald
        (3,14),                            -- Lee
        (2,15), (2,16), (2,17),            -- Hannah
        (4,18), (4,19),                    -- Orwell
        (5,20), (5,21), (5,22),            -- Pratchett
        (6,23), (6,24), (6,25),            -- Gaiman
        (8,26), (8,27), (8,28),            -- Tolkien
        (9,29),                            -- Huxley
        (10,30), (10,31),                  -- Bradbury
        (11,32), (11,33), (11,34),         -- Steinbeck
        (12,35), (13,36), (14,37), (15,38),-- Salinger, Golding, Morrison, Atwood
        (16,39), (16,40),                  -- Christie
        (17,41),                           -- Herbert
        (18,42), (18,43),                  -- Asimov
        (19,44), (20,45), (21,46), (22,47),-- McCarthy, Vonnegut, White, Owens
        (23,48), (23,49), (23,50);");      // Collins

/* Most books get 2 copies; a few get 1, so 'No copies available' is easy to test.
   (copy_id, barcode, book_id). Barcodes continue from 100014. */
$db_connection -> query(
        "INSERT INTO BookCopies (copy_id, barcode, book_id) VALUES
        (15,100015,10), (16,100016,10), (17,100017,11), (18,100018,11), (19,100019,12),
        (20,100020,13), (21,100021,13), (22,100022,14), (23,100023,15), (24,100024,15),
        (25,100025,16), (26,100026,16), (27,100027,17), (28,100028,17), (29,100029,18),
        (30,100030,19), (31,100031,19), (32,100032,20), (33,100033,20), (34,100034,21),
        (35,100035,21), (36,100036,22), (37,100037,23), (38,100038,23), (39,100039,24),
        (40,100040,24), (41,100041,25), (42,100042,26), (43,100043,26), (44,100044,27),
        (45,100045,27), (46,100046,28), (47,100047,28), (48,100048,29), (49,100049,29),
        (50,100050,30), (51,100051,30), (52,100052,31), (53,100053,32), (54,100054,32),
        (55,100055,33), (56,100056,33), (57,100057,34), (58,100058,35), (59,100059,35),
        (60,100060,36), (61,100061,36), (62,100062,37), (63,100063,38), (64,100064,38),
        (65,100065,39), (66,100066,39), (67,100067,40), (68,100068,40), (69,100069,41),
        (70,100070,41), (71,100071,42), (72,100072,42), (73,100073,43), (74,100074,44),
        (75,100075,44), (76,100076,45), (77,100077,46), (78,100078,46), (79,100079,47),
        (80,100080,47), (81,100081,48), (82,100082,48), (83,100083,49), (84,100084,49),
        (85,100085,50), (86,100086,50);");

/* (category_id, book_id). 1 Sci-Fy, 2 Fantasy, 3 Mystery, 4 Romance, 5 Thriller,
   6 Historical Fiction, 7 Non-Fiction, 8 Math, 9 Children's Literature, 10 Horror.
   The Catcher in the Rye (35) has none, because none of the 10 fit it. */
$db_connection -> query(
        "INSERT INTO BookCategories (category_id, book_id) VALUES
        (4,10), (4,11), (6,11), (6,12), (4,13), (6,14), (6,15), (4,16), (6,17),
        (7,18), (7,19),                                  -- Orwell's non-fiction
        (2,20), (2,21), (2,22), (3,22), (2,23),
        (2,24), (9,24), (10,24),                         -- Coraline: 3 categories
        (2,25), (2,26), (2,27), (2,28),                  -- incl. The Lord of the Rings
        (1,29), (1,30), (1,31), (6,32), (6,33), (6,34),
        (5,36), (6,37), (10,37), (1,38), (3,39), (3,40), (5,40),
        (1,41), (1,42), (1,43), (1,44), (1,45), (6,45), (9,46),
        (3,47), (4,47),
        (1,48), (5,48), (1,49), (5,49), (1,50), (5,50);");  // The Hunger Games trilogy

/* Status Display */
echo nl2br("The database tables were successfully populated.\r\n");
/* Return to homepage after 5 seconds */
header( "refresh:10;url=/csc235_login" );

/* ALWAYS CLOSE THE DB CONNECTION */
$db_connection->close();

?>
