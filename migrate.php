<?php

require_once('config.inc.php');

function query($query) {
	global $conn;

	$result = mysql_query($query, $conn);
	if (!$result) {
		if (mysql_error($conn) != 'Query was empty') {
			echo "SQL Error: " . mysql_error($conn) . "\n";
			mysql_query('ROLLBACK', $conn);
			mysql_close($conn);
			exit;
		}
	}
	return $result;
}

// Establish connection to database
$conn = mysql_connect(DATABASE_SERVER, DATABASE_USER, DATABASE_PASS);

if (!$conn) {
	echo "Error connecting to database: " . mysql_error($conn);
	exit;
}

// The database we are connecting to might not exist. If so lets create it.
if(mysql_num_rows(query("SHOW databases LIKE '" . DATABASE_NAME . "'"))==0) {
    query('CREATE DATABASE `' . DATABASE_NAME . '` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;');
}	

// Then lets connect to it
mysql_select_db(DATABASE_NAME, $conn);

// If the migrations table doesn't exist, lets create it.
if(mysql_num_rows(query("SHOW TABLES LIKE 'migrations'"))==0) {
    query('CREATE TABLE `migrations` (`version` int(11) NOT NULL,`file` text NOT NULL,`date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=latin1; ');
}	

$migrated = Array();
$latest_version = 0;

// Lets query the migrations table to get a list of files already migrated. 
$result = query('select * from migrations', $conn);

while($row=mysql_fetch_array($result)) {
	$migrated[$row['file']] = true;
	if ($row['version'] > $latest_version) {
		$latest_version = $row['version']; // Set the version to be the latest.
	}
}

// Find all the migration files in the directory and return the sorted.#
$files = array();
$dir = opendir(SQL_FOLDER);
while ($file = readdir($dir)) {
	if (($file != '.') && ($file != '..')) {
		$file_info = explode('__', $file);
		if ((isset($file_info[1])) && (is_numeric($file_info[0]))){
			$files[] = $file;
		} else {
			echo "File name error: " . $file . ". The format should be versin__name (double underscore)";	
		}
	}
}
  
asort($files);

// Check no files have the same version
$previous_file = '';
foreach ($files as $file) {
	$current_file_info = explode('__', $file);
	$previous_file_info = explode('__', $previous_file);
	if ($previous_file_info[0] == $current_file_info[0]) {
		echo $file . " and " . $previous_file . " have the same version";
		exit;
	}
    $previous_file = $file;
}

$new_count = 0;
// Lets now import the sql from all the files
foreach ($files as $file) {
    $file_info = explode('__', $file);
	
	// Do we allow out of order file versions?
	if (OUT_OF_ORDER == true) {
		if (isset($migrated[$file])) {
			 continue;
		}
	} else {
		if ($file_info[0] <= $latest_version) {
			if (!isset($migrated[$file])) {
				echo "File " . $file . " is older than version " . $latest_version . " but hasn't been migrated";
				exit;
			}
		  continue;
		}
	}
	
	// Read the content of the SQL file
	$sql_file = file_get_contents(SQL_FOLDER . $file);
	// Split the entries by the ; char (might be an issue if that char is inside the sql query (into insert data);
	$sql_entries = explode(';', $sql_file);
	
	echo "Process file: " . $file . " (" . count($sql_entries) . " changes.\n";
	
    query('BEGIN');
	// Loop through the entries of the file. skipping any that are blank (the ; split will cause a [0] to exist on a file with no content)
	for ($i = 0; $i < count($sql_entries); $i++) {
		if (!empty($sql_entries[$i])) {
			query($sql_entries[$i]);
		}
	}
    query('COMMIT');

    $latest_version = $file_info[0];
    $new_count++;

    // As this was successful, lets write this file to the database.
    query("INSERT INTO `migrations` (`version`, `file`) VALUES ('" . $file_info[0] . "', '" . $file . "')");
	
}

// Have we made any changes?
if ($new_count > 0) {
	echo $new_count . " files successfully processed.\n";
}

mysql_close($conn);

