/*  Uncomment CREATE DATABASE section below if your hosting provider allows creating the database through the installer instead of control panel or if you are using a local setup */


/* CREATE DATABASE test;

use test;
*/

CREATE TABLE users (
	id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY, 
	firstname VARCHAR(30) NOT NULL,
	lastname VARCHAR(30) NOT NULL,
	email VARCHAR(50) NOT NULL,
	age INT(3),
	location VARCHAR(50),
	date TIMESTAMP
);