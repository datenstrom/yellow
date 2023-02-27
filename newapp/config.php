<?php

/**
 * Configuration for database connection
 *
 */
 
 $host		= "apps.lifeofpablo.com";
 $username	= "survey111";
 $password	= "survey11";
 $dbname	= "pabsapp";
 $dsn		= "mysql:host=$host;dbname=$dbname"; // will use later
 $options	= array(
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
				);