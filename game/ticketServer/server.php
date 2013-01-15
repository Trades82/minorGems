<?php



global $ts_version;
$ts_version = "1";



// edit settings.php to change server' settings
include( "settings.php" );




// no end-user settings below this point


// for use in readable base-32 encoding
// elimates 0/O and 1/I
global $readableBase32DigitArray;
$readableBase32DigitArray =
    array( "2", "3", "4", "5", "6", "7", "8", "9",
           "A", "B", "C", "D", "E", "F", "G", "H", "J", "K", "L", "M",
           "N", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z" );



// no caching
//header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', FALSE);
header('Pragma: no-cache'); 



// enable verbose error reporting to detect uninitialized variables
error_reporting( E_ALL );



// page layout for web-based setup
$setup_header = "
<HTML>
<HEAD><TITLE>Ticket Permissions Server Web-based setup</TITLE></HEAD>
<BODY BGCOLOR=#FFFFFF TEXT=#000000 LINK=#0000FF VLINK=#FF0000>

<CENTER>
<TABLE WIDTH=75% BORDER=0 CELLSPACING=0 CELLPADDING=1>
<TR><TD BGCOLOR=#000000>
<TABLE WIDTH=100% BORDER=0 CELLSPACING=0 CELLPADDING=10>
<TR><TD BGCOLOR=#EEEEEE>";

$setup_footer = "
</TD></TR></TABLE>
</TD></TR></TABLE>
</CENTER>
</BODY></HTML>";






// ensure that magic quotes are OFF
// we hand-filter all _REQUEST data with regexs before submitting it to the DB
if( get_magic_quotes_gpc() ) {
    // force magic quotes to be removed
    $_GET     = array_map( 'ts_stripslashes_deep', $_GET );
    $_POST    = array_map( 'ts_stripslashes_deep', $_POST );
    $_REQUEST = array_map( 'ts_stripslashes_deep', $_REQUEST );
    $_COOKIE  = array_map( 'ts_stripslashes_deep', $_COOKIE );
    }
    





// all calls need to connect to DB, so do it once here
ts_connectToDatabase();

// close connection down below (before function declarations)


// testing:
//sleep( 5 );


// general processing whenver server.php is accessed directly




// grab POST/GET variables
$action = ts_requestFilter( "action", "/[A-Z_]+/i" );

$debug = ts_requestFilter( "debug", "/[01]/" );

$remoteIP = "";
if( isset( $_SERVER[ "REMOTE_ADDR" ] ) ) {
    $remoteIP = $_SERVER[ "REMOTE_ADDR" ];
    }




if( $action == "version" ) {
    global $ts_version;
    echo "$ts_version";
    }
else if( $action == "show_log" ) {
    ts_showLog();
    }
else if( $action == "clear_log" ) {
    ts_clearLog();
    }
else if( $action == "sell_ticket" ) {
    ts_sellTicket();
    }
else if( $action == "block_ticket_id" ) {
    ts_blockTicketID();
    }
else if( $action == "delete_ticket_id" ) {
    ts_deleteTicketID();
    }
else if( $action == "show_downloads" ) {
    ts_showDownloads();
    }
else if( $action == "download" ) {
    ts_download();
    }
else if( $action == "get_ticket_id" ) {
    ts_getTicketID();
    }
else if( $action == "show_data" ) {
    ts_showData();
    }
else if( $action == "show_detail" ) {
    ts_showDetail();
    }
else if( $action == "edit_ticket" ) {
    ts_editTicket();
    }
else if( $action == "send_group_email" ) {
    ts_sendGroupEmail();
    }
else if( $action == "send_single_email" ) {
    ts_sendSingleEmail();
    }
else if( $action == "send_all_note" ) {
    ts_sendAllNote();
    }
else if( $action == "send_all_file_note" ) {
    ts_sendAllFileNote();
    }
else if( $action == "email_opt_in" ) {
    ts_emailOptIn();
    }
else if( $action == "logout" ) {
    ts_logout();
    }
else if( $action == "ts_setup" ) {
    global $setup_header, $setup_footer;
    echo $setup_header; 

    echo "<H2>Ticket Server Web-based Setup</H2>";

    echo "Creating tables:<BR>";

    echo "<CENTER><TABLE BORDER=0 CELLSPACING=0 CELLPADDING=1>
          <TR><TD BGCOLOR=#000000>
          <TABLE BORDER=0 CELLSPACING=0 CELLPADDING=5>
          <TR><TD BGCOLOR=#FFFFFF>";

    ts_setupDatabase();

    echo "</TD></TR></TABLE></TD></TR></TABLE></CENTER><BR><BR>";
    
    echo $setup_footer;
    }
else if( preg_match( "/server\.php/", $_SERVER[ "SCRIPT_NAME" ] ) ) {
    // server.php has been called without an action parameter

    // the preg_match ensures that server.php was called directly and
    // not just included by another script
    
    // quick (and incomplete) test to see if we should show instructions
    global $tableNamePrefix;
    
    // check if our "games" table exists
    $tableName = $tableNamePrefix . "tickets";
    
    $exists = ts_doesTableExist( $tableName );
        
    if( $exists  ) {
        echo "Ticket Server database setup and ready";
        }
    else {
        // start the setup procedure

        global $setup_header, $setup_footer;
        echo $setup_header; 

        echo "<H2>Ticket Server Web-based Setup</H2>";
    
        echo "Ticket Server will walk you through a " .
            "brief setup process.<BR><BR>";
        
        echo "Step 1: ".
            "<A HREF=\"server.php?action=ts_setup\">".
            "create the database tables</A>";

        echo $setup_footer;
        }
    }



// done processing
// only function declarations below

ts_closeDatabase();







/**
 * Creates the database tables needed by seedBlogs.
 */
function ts_setupDatabase() {
    global $tableNamePrefix;

    $tableName = $tableNamePrefix . "log";
    if( ! ts_doesTableExist( $tableName ) ) {

        // this table contains general info about the server
        // use INNODB engine so table can be locked
        $query =
            "CREATE TABLE $tableName(" .
            "entry TEXT NOT NULL, ".
            "entry_time DATETIME NOT NULL );";

        $result = ts_queryDatabase( $query );

        echo "<B>$tableName</B> table created<BR>";
        }
    else {
        echo "<B>$tableName</B> table already exists<BR>";
        }

    
    
    $tableName = $tableNamePrefix . "tickets";
    if( ! ts_doesTableExist( $tableName ) ) {

        // this table contains general info about each ticket
        $query =
            "CREATE TABLE $tableName(" .
            "ticket_id VARCHAR(255) NOT NULL PRIMARY KEY," .
            "sale_date DATETIME NOT NULL," .
            "last_download_date DATETIME NOT NULL," .
            "name TEXT NOT NULL, ".
            "email CHAR(255) NOT NULL," .
            "order_number CHAR(255) NOT NULL," .
            "tag CHAR(255) NOT NULL," .
            "email_sent TINYINT NOT NULL," .
            "blocked TINYINT NOT NULL," .
            "download_count INT NOT NULL, ".
            "email_opt_in TINYINT NOT NULL );";

        $result = ts_queryDatabase( $query );

        echo "<B>$tableName</B> table created<BR>";
        }
    else {
        echo "<B>$tableName</B> table already exists<BR>";
        }


    
    
    $tableName = $tableNamePrefix . "downloads";
    if( ! ts_doesTableExist( $tableName ) ) {

        // this table contains information about each download that occurred
        $query =
            "CREATE TABLE $tableName(" .
            "ticket_id CHAR(10) NOT NULL," .
            "download_date DATETIME NOT NULL," .
            "file_name TEXT NOT NULL," .
            "blocked TINYINT NOT NULL," .
            "ip_address CHAR(255) NOT NULL," .
            "PRIMARY KEY( ticket_id, download_date ) );";
                
        $result = ts_queryDatabase( $query );
        
        echo "<B>$tableName</B> table created<BR>";
        }
    else {
        echo "<B>$tableName</B> table already exists<BR>";
        }
    }



function ts_showLog() {
    ts_checkPassword( "show_log" );

     echo "[<a href=\"server.php?action=show_data" .
         "\">Main</a>]<br><br><br>";
    
    global $tableNamePrefix;

    $query = "SELECT * FROM $tableNamePrefix"."log ".
        "ORDER BY entry_time DESC;";
    $result = ts_queryDatabase( $query );

    $numRows = mysql_numrows( $result );



    echo "<a href=\"server.php?action=clear_log\">".
        "Clear log</a>";
        
    echo "<hr>";
        
    echo "$numRows log entries:<br><br><br>\n";
        

    for( $i=0; $i<$numRows; $i++ ) {
        $time = mysql_result( $result, $i, "entry_time" );
        $entry = mysql_result( $result, $i, "entry" );

        echo "<b>$time</b>:<br>$entry<hr>\n";
        }
    }



function ts_clearLog() {
    ts_checkPassword( "clear_log" );

     echo "[<a href=\"server.php?action=show_data" .
         "\">Main</a>]<br><br><br>";
    
    global $tableNamePrefix;

    $query = "DELETE FROM $tableNamePrefix"."log;";
    $result = ts_queryDatabase( $query );
    
    if( $result ) {
        echo "Log cleared.";
        }
    else {
        echo "DELETE operation failed?";
        }
    }







function ts_sellTicket() {
    global $tableNamePrefix, $fastspringPrivateKeys, $remoteIP;
    global $ticketIDLength, $ticketGenerationSecret;


    $tags = ts_requestFilter( "tags", "/[A-Z0-9_,-]+/i" );
    if( $tags == "" ) {
        // no tag set?
        // default to first one
        $arrayKeys = array_keys( $fastspringPrivateKeys );
        $tags = $arrayKeys[ 0 ];
        }
    
    $separateTags = preg_split( "/,/", $tags );


    $privateKey = "";
    $tag = "";
    
    for( $t=0; $t<count( $separateTags ); $t++ ) {
        if( array_key_exists( $separateTags[ $t ],
                              $fastspringPrivateKeys  ) ) {

            $tag = $separateTags[ $t ];
            
            $privateKey = $fastspringPrivateKeys[ $tag ];
            }
        }
    

    // no need to pass these through a regex, because they aren't passed
    // anywhere, and we're not sure what characters security_data
    // might contain anyway

    $security_data = $_REQUEST[ "security_data" ];
    $security_hash = $_REQUEST[ "security_hash" ];

    $string_to_hash = $security_data . $privateKey;
    
    $correct_hash = md5( $string_to_hash );
    

    if( $correct_hash != $security_hash ) {

        
        ts_log( "Ticket sale security check failed, from $remoteIP, ".
                "data = \"$security_data\", hash = \"$security_hash\",".
                "looking for hash = \"$correct_hash\"," .
                "(data hashed = \"$string_to_hash\")" );
        
        return;  /* FAILED CHECK */
        }


    $name = ts_requestFilter( "name", "/[A-Z0-9.' -]+/i" );

    // some names have ' in them
    // need to escape this for use in DB query
    $name = mysql_real_escape_string( $name );
    

        
    $email = ts_requestFilter( "email", "/[A-Z0-9._%+-]+@[A-Z0-9.-]+/i" );

    $order_number = ts_requestFilter( "reference", "/[A-Z0-9-]+/i" );


    // these allow convenient linking back to main server site after
    // a manual order goes through
    $manual = ts_requestFilter( "manual", "/[01]/", "0" );

    
    // this allows manual ticket creation to override email opt-in
    // defaults to on if not specified
    $email_opt_in = ts_requestFilter( "email_opt_in", "/[01]/", "1" );



    // if manual, check password and display return-to-main link
    if( $manual == 1 ) {
        ts_checkPassword( "sell_ticket" );
        
        // not returning generated ticket to FastSpring
        // (manual order from ticket server interface)
        // okay to show convenient HTML link back to main.
        echo "[<a href=\"server.php?".
            "action=show_data" .
            "\">Main</a>]<br><br><br>";
        }

    
    

    
    $found_unused_id = 0;
    $salt = 0;
    
    
    while( ! $found_unused_id ) {

        
        
        $ticket_id = "";

        // repeat hashing new rand values, mixed with our secret
        // for security, until we have generated enough digits.
        while( strlen( $ticket_id ) < $ticketIDLength ) {

            $randVal = rand();
            
            $hash_bin =
                ts_hmac_sha1_raw( $ticketGenerationSecret,
                                  $name . uniqid( "$randVal"."$salt", true ) );

            
            $hash_base32 = ts_readableBase32Encode( $hash_bin );

            $digitsLeft = $ticketIDLength - strlen( $ticket_id );

            $ticket_id = $ticket_id . substr( $hash_base32, 0, $digitsLeft );
            }

        
        // break into "-" separated chunks of 5 digits
        $ticket_id_chunks = str_split( $ticket_id, 5 );

        $ticket_id = implode( "-", $ticket_id_chunks );
        
        

        /*
"ticket_id VARCHAR(255) NOT NULL PRIMARY KEY," .
            "sale_date DATETIME NOT NULL," .
            "last_download_date DATETIME NOT NULL," .
            "name TEXT NOT NULL, ".
            "email CHAR(255) NOT NULL," .
            "order_number CHAR(255) NOT NULL," .
            "tag CHAR(255) NOT NULL," .
            "email_sent TINYINT NOT NULL," .
            "blocked TINYINT NOT NULL," .
            "download_count INT, ".
            "email_opt_in TINYINT NOT NULL );";
         */


        // opt-in to emails by default
        $query = "INSERT INTO $tableNamePrefix". "tickets VALUES ( " .
            "'$ticket_id', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, ".
            "'$name', '$email', '$order_number', '$tag', '0', '0', '0', " .
            "'$email_opt_in' );";


        $result = mysql_query( $query );

        if( $result ) {
            $found_unused_id = 1;

            ts_log( "Ticket $ticket_id created by $remoteIP" );

            
            echo "$ticket_id";
            }
        else {
            global $debug;
            if( $debug == 1 ) {
                echo "Duplicate ids?  Error:  " . mysql_error() ."<br>";
                }
            // try again
            $salt += 1;
            }
        }

    }





function ts_getTicketID() {
    global $tableNamePrefix, $sharedEncryptionSecret;

    $email = ts_requestFilter( "email", "/[A-Z0-9._%+-]+@[A-Z0-9.-]+/i" );

    $query = "SELECT ticket_id FROM $tableNamePrefix"."tickets ".
        "WHERE email = '$email' AND blocked = '0';";
    $result = ts_queryDatabase( $query );

    $numRows = mysql_numrows( $result );

    // could be more than one with this email
    // return first only
    if( $numRows > 0 ) {
        $ticket_id = mysql_result( $result, 0, "ticket_id" );
        }
    else {
        echo "DENIED";
        return;
        }


    
    // remove hyphens
    $ticket_id = implode( preg_split( "/-/", $ticket_id ) );

    $ticket_id_bits = ts_readableBase32DecodeToBitString( $ticket_id );

    $ticketLengthBits = strlen( $ticket_id_bits );


    // generate enough bits by hashing shared secret repeatedly
    $hexToMixBits = "";

    $runningSecret = sha1( $sharedEncryptionSecret );
    while( strlen( $hexToMixBits ) < $ticketLengthBits ) {

        $newBits = ts_hexDecodeToBitString( $runningSecret );

        $hexToMixBits = $hexToMixBits . $newBits;

        $runningSecret = sha1( $runningSecret );
        }

    // trim down to bits that we need
    $hexToMixBits = substr( $hexToMixBits, 0, $ticketLengthBits );

    $mixBits = str_split( $hexToMixBits );
    $ticketBits = str_split( $ticket_id_bits );

    // bitwise xor
    $i = 0;
    foreach( $mixBits as $bit ) {
        if( $bit == "1" ) {
            if( $ticket_id_bits[$i] == "1" ) {
                
                $ticketBits[$i] = "0";
                }
            else {
                $ticketBits[$i] = "1";
                }
            }
        $i++;
        }

    $ticket_id_bits = implode( $ticketBits );

    $encrypted_ticket_id =
        ts_readableBase32EncodeFromBitString( $ticket_id_bits );

    echo "$encrypted_ticket_id";
    }




function ts_editTicket() {

    ts_checkPassword( "edit_ticket" );
    global $tableNamePrefix, $remoteIP;


    $ticket_id = ts_requestFilter( "ticket_id", "/[A-HJ-NP-Z2-9\-]+/i" );

    $ticket_id = strtoupper( $ticket_id );
    
    
    $name = ts_requestFilter( "name", "/[A-Z0-9.' -]+/i" );

    // some names have ' in them
    // need to escape this for use in DB query
    $name = mysql_real_escape_string( $name );
    
    
    $email = ts_requestFilter( "email", "/[A-Z0-9._%+-]+@[A-Z0-9.-]+/i" );

    $order_number = ts_requestFilter( "reference", "/[A-Z0-9-]+/i" );

    $tag = ts_requestFilter( "tag", "/[A-Z0-9_-]+/i" );

    $email_opt_in = ts_requestFilter( "email_opt_in", "/[01]/", "1" );
    


    $query = "UPDATE $tableNamePrefix". "tickets SET " .
        "name = '$name', email = '$email', ".
        "order_number = '$order_number', tag = '$tag', " .
        "email_opt_in = '$email_opt_in' " .
        "WHERE ticket_id = '$ticket_id';";
    

    $result = mysql_query( $query );

    if( $result ) {
        ts_log( "$ticket_id data changed by $remoteIP" );
        echo "Update of $ticket_id succeeded<br><br>";
        
        ts_showDetail();
        }
    else {
        ts_log( "$ticket_id data change failed for $remoteIP" );

        echo "Update of $ticket_id failed";
        }
    }



function ts_blockTicketID() {
    ts_checkPassword( "block_ticket_id" );


    global $tableNamePrefix;

    $ticket_id = ts_requestFilter( "ticket_id", "/[A-HJ-NP-Z2-9\-]+/i" );

    $ticket_id = strtoupper( $ticket_id );
    

    $blocked = ts_requestFilter( "blocked", "/[01]/", "0" );
    
    
    global $remoteIP;

    

    
    $query = "SELECT * FROM $tableNamePrefix"."tickets ".
        "WHERE ticket_id = '$ticket_id';";
    $result = ts_queryDatabase( $query );

    $numRows = mysql_numrows( $result );

    if( $numRows == 1 ) {

        
        $query = "UPDATE $tableNamePrefix"."tickets SET " .
            "blocked = '$blocked' " .
            "WHERE ticket_id = '$ticket_id';";
        
        $result = ts_queryDatabase( $query );

        
        ts_log( "$ticket_id block changed to $blocked by $remoteIP" );

        ts_showData();
        }
    else {
        ts_log( "$ticket_id not found for $remoteIP" );

        echo "$ticket_id not found";
        }    
    }



function ts_deleteTicketID() {
    ts_checkPassword( "delete_ticket_id" );

    global $tableNamePrefix, $remoteIP;

    $ticket_id = ts_requestFilter( "ticket_id", "/[A-HJ-NP-Z2-9\-]+/i" );

    $ticket_id = strtoupper( $ticket_id );
    

    $query = "DELETE FROM $tableNamePrefix"."tickets ".
        "WHERE ticket_id = '$ticket_id';";
    $result = ts_queryDatabase( $query );
    
    if( $result ) {
        ts_log( "$ticket_id deleted by $remoteIP" );

        echo "$ticket_id deleted.<hr>";

        // don't check password again here
        ts_showData( false );
        }
    else {
        ts_log( "$ticket_id delete failed for $remoteIP" );

        echo "DELETE operation failed?";
        }
    }



function ts_downloadAllowed() {

    $ticket_id = ts_requestFilter( "ticket_id", "/[A-HJ-NP-Z2-9\-]+/i" );
    
    $ticket_id = strtoupper( $ticket_id );    
    
    global $tableNamePrefix, $remoteIP;


    global $header, $footer;


    
    $query = "SELECT * FROM $tableNamePrefix"."tickets ".
        "WHERE ticket_id = '$ticket_id';";
    $result = ts_queryDatabase( $query );

    $numRows = mysql_numrows( $result );

    if( $numRows == 1 ) {
        
        $row = mysql_fetch_array( $result, MYSQL_ASSOC );

        $blocked = $row[ "blocked" ];

        $tag = $row[ "tag" ];



        date_default_timezone_set( "America/New_York" );
        

        $currentTimestamp = time();

        $allowedTimestamp;

        $downloadReady = 1;
        
        
        global $allowedDownloadDates;
        
        $allowedTimestamp = strtotime( $allowedDownloadDates[ $tag ] );


        
        if( $currentTimestamp < $allowedTimestamp ) {

            eval( $header );
            
            $allowedDateString = date( "l, F j, Y", $allowedTimestamp );
            echo "Your download will be available on ".
                "<b>$allowedDateString</b> (New York Time)<br>\n";

            $d = $allowedTimestamp - $currentTimestamp;

            $hours = (int)( $d / 3600 );

            $seconds = (int)( $d % 3600 );
            $minutes = (int)( $seconds / 60 );
            $seconds = (int)( $seconds % 60 );
            
            $days = (int)( $hours / 24 );
            $hours = (int)( $hours % 24 );
            

            echo "(That's in $days days, $hours hours, ".
                "$minutes minutes, and $seconds seconds)<br>\n";

            $currentDateString = date( "l, F j, Y [g:i a]",
                                       $currentTimestamp );

            echo "Current New York time: $currentDateString<br>\n";

            eval( $footer );
            
            $downloadReady = 0;
            }
        
            
        
        // format as in    Sunday, July 7, 2005 [4:52 pm]
        //$dateString = date( "l, F j, Y [g:i a]", $timestamp );

        if( !$blocked ){
            $blocked = !$downloadReady;
            }

        if( !$blocked ) {
            
            ts_log( "$ticket_id permitted to download by $remoteIP" );
            
            return 1;
            }
        else {
            
            if( $downloadReady ) {
                eval( $header );
                echo "Your download access is currently blocked";
                eval( $footer );

                ts_log( "$ticket_id denied to download by ".
                        "$remoteIP (blocked)" );
                }
            else {
                ts_log( "$ticket_id denied to download by ".
                        "$remoteIP (too early)" );
                }
            
            return 0;
            }
        }
    eval( $header );
    echo "Your ticket number was not found";
    eval( $footer );
    
    ts_log( "$ticket_id denied to download by $remoteIP (not found)" );

    return 0;
    }


function ts_printLink( $inFileName, $inTicketID ) {
    echo "<a href=\"server.php?action=download&ticket_id=$inTicketID&" .
        "file_name=$inFileName\">$inFileName</a>";
    }



function ts_showDownloads() {
    $ticket_id = ts_requestFilter( "ticket_id", "/[A-HJ-NP-Z2-9\-]+/i" );

    $ticket_id = strtoupper( $ticket_id );    
    
    global $tableNamePrefix, $remoteIP;

    
    if( ts_downloadAllowed() ) {
        global $fileList, $fileDescriptions, $fileListHeader, $footer;


        eval( $fileListHeader );
        
        echo "<center><table border=1 cellpadding=4>";
        
        for( $i=0; $i<count( $fileList ); $i++ ) {
            echo "<tr><td>";
            ts_printLink( $fileList[$i], $ticket_id );
            echo "</td>";
            $des = $fileDescriptions[$i];
            echo "<td>$des</td></tr>";
            }
        echo "</table></center>";



        // show opt in or opt out link?
        $query = "SELECT * FROM $tableNamePrefix"."tickets ".
            "WHERE ticket_id = '$ticket_id';";
        $result = ts_queryDatabase( $query );

        $numRows = mysql_numrows( $result );

        if( $numRows == 1 ) {
        
            $row = mysql_fetch_array( $result, MYSQL_ASSOC );

            $email_opt_in = $row[ "email_opt_in" ];

            echo "<br><br>";
            
            if( $email_opt_in == '1' ) {
                echo "[<a href=\"server.php?action=email_opt_in&in=0&".
                    "ticket_id=$ticket_id\">Opt Out</a>] of email updates.";
                }
            else {
                echo "[<a href=\"server.php?action=email_opt_in&in=1&".
                    "ticket_id=$ticket_id\">Opt In</a>] to email updates.";
                }
            }
        
        
        eval( $footer );
        }
    }




function ts_download() {
    $ticket_id = ts_requestFilter( "ticket_id", "/[A-HJ-NP-Z2-9\-]+/i" );
    
    $ticket_id = strtoupper( $ticket_id );    

    $file_name = ts_requestFilter( "file_name", "/[A-Z0-9_.-]+/i" );

    
    global $tableNamePrefix, $remoteIP;

    
        
    $blocked = ! ts_downloadAllowed();
    

    
    
    $query = "SELECT * FROM $tableNamePrefix"."tickets ".
        "WHERE ticket_id = '$ticket_id';";
    $result = ts_queryDatabase( $query );

    $numRows = mysql_numrows( $result );

    if( $numRows == 1 ) {
        
        $row = mysql_fetch_array( $result, MYSQL_ASSOC );


        // catalog blocked runs, too
        $download_count = $row[ "download_count" ];
        
        $download_count ++;
        
        
        $query = "UPDATE $tableNamePrefix"."tickets SET " .
            "last_download_date = CURRENT_TIMESTAMP, " .
            "download_count = '$download_count' " .
            "WHERE ticket_id = '$ticket_id';";
            

        $result = ts_queryDatabase( $query );
        
        
        $query = "INSERT INTO $tableNamePrefix". "downloads VALUES ( " .
            "'$ticket_id', CURRENT_TIMESTAMP, '$file_name', ".
            "'$blocked', '$remoteIP' );";
        
        $result = mysql_query( $query );

        
        if( !$blocked ) {
            global $downloadFilePath;
            
            $result = ts_send_file( $downloadFilePath . $file_name );

            if( ! $result ) {
                global $header, $footer;

                eval( $header );
                
                echo "File not found.";
                
                eval( $footer );
                }
            
            return;
            }
        else {
            return;
            }
        }
    }




function ts_emailOptIn() {
    $ticket_id = ts_requestFilter( "ticket_id", "/[A-HJ-NP-Z2-9\-]+/i" );
    
    $ticket_id = strtoupper( $ticket_id );    

    $in = ts_requestFilter( "in", "/[01]/", "1" );
    
    
    global $tableNamePrefix, $remoteIP;

    
    if( ts_downloadAllowed() ) {
        global $header, $footer;


        eval( $header );


        $query = "UPDATE $tableNamePrefix"."tickets ".
            "SET email_opt_in='$in' WHERE ticket_id = '$ticket_id';";

        $result = ts_queryDatabase( $query );

        echo "Email updates for your download ticket are currently ";

        if( $in == 1 ) {
            echo "<b>on</b><br><br>\n";
            }
        else {
            echo "<b>off</b><br><br>\n";
            }
        

        // show opt in or opt out link?
        $query = "SELECT * FROM $tableNamePrefix"."tickets ".
            "WHERE ticket_id = '$ticket_id';";
        $result = ts_queryDatabase( $query );

        $numRows = mysql_numrows( $result );

        if( $numRows == 1 ) {
        
            $row = mysql_fetch_array( $result, MYSQL_ASSOC );

            $email_opt_in = $row[ "email_opt_in" ];

            
            
            if( $email_opt_in == '1' ) {
                echo "[<a href=\"server.php?action=email_opt_in&in=0&".
                    "ticket_id=$ticket_id\">Opt Out</a>] of email updates.";
                }
            else {
                echo "[<a href=\"server.php?action=email_opt_in&in=1&".
                    "ticket_id=$ticket_id\">Opt In</a>] to email updates.";
                }
            }

        echo "<br><br>";
        
        echo "[<a href=\"server.php?action=show_downloads&".
            "ticket_id=$ticket_id\">Return</a>] to your download page.";
        
        eval( $footer );
        }
    }



function ts_logout() {

    ts_clearPasswordCookie();

    echo "Logged out";
    }




function ts_showData( $checkPassword = true ) {
    // these are global so they work in embeded function call below
    global $skip, $search, $order_by;

    if( $checkPassword ) {
        ts_checkPassword( "show_data" );
        }
    
    global $tableNamePrefix, $remoteIP;
    

    echo "<table width='100%' border=0><tr>".
        "<td>[<a href=\"server.php?action=show_data" .
            "\">Main</a>]</td>".
        "<td align=right>[<a href=\"server.php?action=logout" .
            "\">Logout</a>]</td>".
        "</tr></table><br><br><br>";




    $skip = ts_requestFilter( "skip", "/[0-9]+/", 0 );
    
    global $ticketsPerPage;
    
    $search = ts_requestFilter( "search", "/[A-Z0-9_@. -]+/i" );

    $order_by = ts_requestFilter( "order_by", "/[A-Z_]+/i",
                                  "last_download_date" );
    
    $keywordClause = "";
    $searchDisplay = "";
    
    if( $search != "" ) {
        

        $keywordClause = "WHERE ( name LIKE '%$search%' " .
            "OR email LIKE '%$search%' ".
            "OR ticket_id LIKE '%$search%' ".
            "OR tag LIKE '%$search%' ) ";

        $searchDisplay = " matching <b>$search</b>";
        }
    

    

    // first, count results
    $query = "SELECT COUNT(*) FROM $tableNamePrefix"."tickets $keywordClause;";

    $result = ts_queryDatabase( $query );
    $totalTickets = mysql_result( $result, 0, 0 );


    $orderDir = "DESC";

    if( $order_by == "name" || $order_by == "email" ) {
        $orderDir = "ASC";
        }
    
             
    $query = "SELECT * FROM $tableNamePrefix"."tickets $keywordClause".
        "ORDER BY $order_by $orderDir ".
        "LIMIT $skip, $ticketsPerPage;";
    $result = ts_queryDatabase( $query );
    
    $numRows = mysql_numrows( $result );

    $startSkip = $skip + 1;
    
    $endSkip = $startSkip + $ticketsPerPage - 1;

    if( $endSkip > $totalTickets ) {
        $endSkip = $totalTickets;
        }



        // form for searching tickets
?>
        <hr>
            <FORM ACTION="server.php" METHOD="post">
    <INPUT TYPE="hidden" NAME="action" VALUE="show_data">
    <INPUT TYPE="hidden" NAME="order_by" VALUE="<?php echo $order_by;?>">
    <INPUT TYPE="text" MAXLENGTH=40 SIZE=20 NAME="search"
             VALUE="<?php echo $search;?>">
    <INPUT TYPE="Submit" VALUE="Search">
    </FORM>
        <hr>
<?php

    

    
    echo "$totalTickets active tickets". $searchDisplay .
        " (showing $startSkip - $endSkip):<br>\n";

    
    $nextSkip = $skip + $ticketsPerPage;

    $prevSkip = $skip - $ticketsPerPage;
    
    if( $prevSkip >= 0 ) {
        echo "[<a href=\"server.php?action=show_data" .
            "&skip=$prevSkip&search=$search&order_by=$order_by\">".
            "Previous Page</a>] ";
        }
    if( $nextSkip < $totalTickets ) {
        echo "[<a href=\"server.php?action=show_data" .
            "&skip=$nextSkip&search=$search&order_by=$order_by\">".
            "Next Page</a>]";
        }

    echo "<br><br>";
    
    echo "<table border=1 cellpadding=5>\n";

    function orderLink( $inOrderBy, $inLinkText ) {
        global $skip, $search, $order_by;
        if( $inOrderBy == $order_by ) {
            // already displaying this order, don't show link
            return "<b>$inLinkText</b>";
            }

        // else show a link to switch to this order
        return "<a href=\"server.php?action=show_data" .
            "&search=$search&skip=$skip&order_by=$inOrderBy\">$inLinkText</a>";
        }

    
    echo "<tr>\n";    
    echo "<tr><td>Ticket ID</td>\n";
    echo "<td>".orderLink( "name", "Name" )."</td>\n";
    echo "<td>".orderLink( "email", "Email" )."</td>\n";
    echo "<td>Sent?</td>\n";
    echo "<td>Blocked?</td>\n";
    echo "<td>".orderLink( "sale_date", "Created" )."</td>\n";
    echo "<td>Test</td>\n";
    echo "<td>".orderLink( "last_download_date", "Last DL" )."</td>\n";
    echo "<td>".orderLink( "download_count", "DL Count" )."</td>\n";
    echo "</tr>\n";


    for( $i=0; $i<$numRows; $i++ ) {
        $ticket_id = mysql_result( $result, $i, "ticket_id" );
        $sale_date = mysql_result( $result, $i, "sale_date" );
        $lastDL = mysql_result( $result, $i, "last_download_date" );
        $count = mysql_result( $result, $i, "download_count" );
        $name = mysql_result( $result, $i, "name" );
        $email = mysql_result( $result, $i, "email" );
        $tag = mysql_result( $result, $i, "tag" );
        $blocked = mysql_result( $result, $i, "blocked" );
        $sent = mysql_result( $result, $i, "email_sent" );

        $block_toggle = "";
        
        if( $blocked ) {
            $blocked = "BLOCKED";
            $block_toggle = "<a href=\"server.php?action=block_ticket_id&".
                "blocked=0&ticket_id=$ticket_id\">unblock</a>";
            
            }
        else {
            $blocked = "";
            $block_toggle = "<a href=\"server.php?action=block_ticket_id&".
                "blocked=1&ticket_id=$ticket_id\">block</a>";
            
            }

        $sent_toggle = "";

        if( $sent ) {
            $sent_toggle = "X";
            }
        

        
        echo "<tr>\n";
        
        echo "<td><b>$ticket_id</b> ($tag) ";
        echo "[<a href=\"server.php?action=show_detail" .
            "&ticket_id=$ticket_id\">detail</a>]</td>\n";
        echo "<td>$name</td>\n";
        echo "<td>$email</td>\n";
        echo "<td align=center>$sent_toggle</td>\n";
        echo "<td align=right>$blocked [$block_toggle]</td>\n";
        echo "<td>$sale_date</td> ";
        echo "<td>[<a href=\"server.php?action=show_downloads".
            "&ticket_id=$ticket_id\">run test</a>]</td>";
        echo "<td>$lastDL</td>";
        echo "<td>$count DLs</td>";
        
        echo "</tr>\n";
        }
    echo "</table>";


    echo "<hr>";

        // put forms in a table
    echo "<center><table border=1 cellpadding=10><tr>\n";
    


    // fake a security hashes to include in form
    global $fastspringPrivateKeys;
    
    $data = "abc";

    
    // form for force-creating a new id
?>
        <td>
        Create new Ticket:<br>
            <FORM ACTION="server.php" METHOD="post">
    <INPUT TYPE="hidden" NAME="security_data" VALUE="<?php echo $data;?>">
    <INPUT TYPE="hidden" NAME="action" VALUE="sell_ticket">
    <INPUT TYPE="hidden" NAME="manual" VALUE="1">
             Email:
    <INPUT TYPE="text" MAXLENGTH=40 SIZE=20 NAME="email"><br>
    Name:
    <INPUT TYPE="text" MAXLENGTH=40 SIZE=20 NAME="name"><br>
    Order #:
    <INPUT TYPE="text" MAXLENGTH=40 SIZE=20 NAME="reference"><br>
    Tag:
    <SELECT NAME="tags">
<?php

    // auto-gen a drop-down list of available tags
    global $allowedDownloadDates;
    
    foreach( $allowedDownloadDates as $tag => $date ){
        echo "<OPTION VALUE=\"$tag\">$tag</OPTION>";
        }
?>
    </SELECT><br>
    Fake security hash:
    <SELECT NAME="security_hash">
<?php

    // auto-gen a drop-down list of hashes for available tags
    global $allowedDownloadDates;

    foreach( $allowedDownloadDates as $tag => $date ){
        $string_to_hash = $data . $fastspringPrivateKeys[$tag];
        
        $hash = md5( $string_to_hash );

        echo "<OPTION VALUE=\"$hash\">$tag</OPTION>";
        }
?>
    </SELECT><br>

    <INPUT TYPE="checkbox" NAME="email_opt_in" VALUE=0>
          Force email opt-out<br>
          
    <INPUT TYPE="Submit" VALUE="Generate">
    </FORM>
        </td>
<?php




    // form for sending out download emails
?>
        <td>
        Send download emails:<br>
            <FORM ACTION="server.php" METHOD="post">
    <INPUT TYPE="hidden" NAME="action" VALUE="send_group_email">
    Tag:
    <SELECT NAME="tag">
<?php

    // auto-gen a drop-down list of available tags
    global $allowedDownloadDates;
    
    foreach( $allowedDownloadDates as $tag => $date ){
        echo "<OPTION VALUE=\"$tag\">$tag</OPTION>";
        }
?>
    </SELECT><br>
    Batch size:      
    <INPUT TYPE="text" MAXLENGTH=10 SIZE=10 NAME="batch_size" VALUE="10"><br>
    <INPUT TYPE="checkbox" NAME="confirm" VALUE=1> Confirm<br>      
    <INPUT TYPE="Submit" VALUE="Send">
    </FORM>
        </td>
<?php

    echo "</tr></table></center>\n";

    
    
    echo "<hr>";

?>
    <FORM ACTION="server.php" METHOD="post">
    <INPUT TYPE="hidden" NAME="action" VALUE="send_all_note">
    Subject:
    <INPUT TYPE="text" MAXLENGTH=80 SIZE=40 NAME="message_subject"><br>
    Tag:
    <SELECT NAME="tag">
<?php
    // auto-gen ALL tags for batches

    $query = "SELECT COUNT(*) FROM $tableNamePrefix"."tickets ".
         "WHERE blocked = '0' AND email_opt_in = '1';";
    $result = ts_queryDatabase( $query );
    $totalTickets = mysql_result( $result, 0, 0 );

    $numToSkip = 0;
    global $emailMaxBatchSize;
    
    while( $totalTickets > 0 ) {
        echo "<OPTION VALUE=\"ALL_BATCH_$numToSkip\">".
            "ALL_BATCH_$numToSkip</OPTION>";
        $totalTickets -= $emailMaxBatchSize;
        $numToSkip += $emailMaxBatchSize;
        }
    
         
    // auto-gen a drop-down list of available tags
    global $allowedDownloadDates;
    
    foreach( $allowedDownloadDates as $tag => $date ){
        echo "<OPTION VALUE=\"$tag\">$tag</OPTION>";
        }
?>
    </SELECT><br>
     Message:<br>
     (<b>#DOWNLOAD_LINK#</b> will be replaced with individual's link)<br>
         <TEXTAREA NAME="message_text" COLS=50 ROWS=10></TEXTAREA><br>
    <INPUT TYPE="checkbox" NAME="confirm" VALUE=1> Confirm<br>      
    <INPUT TYPE="Submit" VALUE="Send">
    </FORM>
    <hr>
<?php


?>
    <FORM ACTION="server.php" METHOD="post">
    <INPUT TYPE="hidden" NAME="action" VALUE="send_all_file_note">
    Subject:
    <INPUT TYPE="text" MAXLENGTH=80 SIZE=40 NAME="message_subject"><br>
    File Downloaded:
    <SELECT NAME="file_name">
<?php

    // auto-gen a drop-down list of files that have ever been downloaded
    
	$query = "SELECT DISTINCT file_name FROM $tableNamePrefix"."downloads;";
    $result = ts_queryDatabase( $query );
    
    $numRows = mysql_numrows( $result );

	for( $i=0; $i<$numRows; $i++ ) {
        $file_name = mysql_result( $result, $i, "file_name" );
        echo "<OPTION VALUE=\"$file_name\">$file_name</OPTION>";
        }
?>
    </SELECT><br>
     Message:<br>
         <TEXTAREA NAME="message_text" COLS=50 ROWS=10></TEXTAREA><br>
    <INPUT TYPE="checkbox" NAME="confirm" VALUE=1> Confirm<br>      
    <INPUT TYPE="Submit" VALUE="Send">
    </FORM>
    <hr>
<?php



    
    echo "<a href=\"server.php?action=show_log\">".
        "Show log</a>";
    echo "<hr>";
    echo "Generated for $remoteIP\n";

    }



function ts_showDetail() {
    ts_checkPassword( "show_detail" );

    echo "[<a href=\"server.php?action=show_data" .
         "\">Main</a>]<br><br><br>";
    
    global $tableNamePrefix;
    

    $ticket_id = ts_requestFilter( "ticket_id", "/[A-HJ-NP-Z2-9\-]+/i" );

    $ticket_id = strtoupper( $ticket_id );


    // form for sending out download emails
?>
        <hr>
        Send download email:<br>
            <FORM ACTION="server.php" METHOD="post">
    <INPUT TYPE="hidden" NAME="action" VALUE="send_single_email">
    <INPUT TYPE="hidden" NAME="ticket_id" VALUE="<?php echo $ticket_id;?>">
    <INPUT TYPE="checkbox" NAME="confirm" VALUE=1> Confirm<br>      
    <INPUT TYPE="Submit" VALUE="Send">
    </FORM>
        <hr>
<?php

            
    $query = "SELECT * FROM $tableNamePrefix"."tickets ".
            "WHERE ticket_id = '$ticket_id';";
    $result = ts_queryDatabase( $query );
    
    $numRows = mysql_numrows( $result );

    $row = mysql_fetch_array( $result, MYSQL_ASSOC );

    $email = $row[ "email" ];
    $name = $row[ "name" ];
    $order_number = $row[ "order_number" ];
    $tag = $row[ "tag" ];
    $email_opt_in = $row[ "email_opt_in" ];
    $optOutChecked = "";
    if( ! $email_opt_in ) {
        $optOutChecked = "checked";
        }
    
    // form for editing ticket data
?>
        <hr>
        Edit ticket:<br>
            <FORM ACTION="server.php" METHOD="post">
    <INPUT TYPE="hidden" NAME="action" VALUE="edit_ticket">
    <INPUT TYPE="hidden" NAME="ticket_id" VALUE="<?php echo $ticket_id;?>">
    Email:
    <INPUT TYPE="text" MAXLENGTH=40 SIZE=20 NAME="email"
            VALUE="<?php echo $email;?>"><br>
    Name:
    <INPUT TYPE="text" MAXLENGTH=40 SIZE=20 NAME="name"
            VALUE="<?php echo $name;?>"><br>
    Order #:
    <INPUT TYPE="text" MAXLENGTH=40 SIZE=40 NAME="reference"
            VALUE="<?php echo $order_number;?>"><br>
    Tag:
    <INPUT TYPE="text" MAXLENGTH=40 SIZE=20 NAME="tag"
            VALUE="<?php echo $tag;?>"><br>
    <INPUT TYPE="checkbox" NAME="email_opt_in" VALUE="0"
             <?php echo $optOutChecked;?> >
          Email opt-out<br>
    <INPUT TYPE="Submit" VALUE="Update">
    </FORM>
        <hr>
<?php
            
    
    
    
    $query = "SELECT * FROM $tableNamePrefix"."downloads ".
        "WHERE ticket_id = '$ticket_id' ORDER BY download_date DESC;";
    $result = ts_queryDatabase( $query );

    $numRows = mysql_numrows( $result );

    echo "$numRows downloads for $ticket_id:";

    echo " [<a href=\"server.php?action=delete_ticket_id" .
        "&ticket_id=$ticket_id\">DELETE this id</a>]";
    
    echo "<br><br><br>\n";
        

    for( $i=0; $i<$numRows; $i++ ) {
        $date = mysql_result( $result, $i, "download_date" );
        $ipAddress = mysql_result( $result, $i, "ip_address" );
        $file_name = mysql_result( $result, $i, "file_name" );

        $blocked = mysql_result( $result, $i, "blocked" );

        if( $blocked ) {
            $blocked = "BLOCKED";
            }
        else {
            $blocked = "";
            }
        
        echo "<b>$date</b>: $ipAddress ($file_name) $blocked<hr>\n";
        }
    }



function ts_sendGroupEmail() {
    ts_checkPassword( "send_group_email" );

    
    echo "[<a href=\"server.php?action=show_data" .
         "\">Main</a>]<br><br><br>";
    
    global $tableNamePrefix;

    $confirm = ts_requestFilter( "confirm", "/[01]/" );
    
    if( $confirm != 1 ) {
        echo "You must check the Confirm box to send emails\n";
        return;
        }
    

    $batch_size = ts_requestFilter( "batch_size", "/[0-9]+/", 0 );
    

    $tag = ts_requestFilter( "tag", "/[A-Z0-9_-]+/i" );

    $batchClause = "";
    if( $batch_size > 0 ) {
        $batchClause = " LIMIT 0, $batch_size ";
        }
    
    

    $query = "SELECT * FROM $tableNamePrefix"."tickets ".
        "WHERE tag = '$tag' AND email_sent = '0' AND blocked = '0' ".
        "ORDER BY sale_date ASC $batchClause;";

    ts_sendEmail_q( $query );
    }



function ts_sendSingleEmail() {
    ts_checkPassword( "send_group_email" );

    
    echo "[<a href=\"server.php?action=show_data" .
         "\">Main</a>]<br><br><br>";
    
    global $tableNamePrefix;
 
    $confirm = ts_requestFilter( "confirm", "/[01]/" );
    
    if( $confirm != 1 ) {
        echo "You must check the Confirm box to send emails\n";
        return;
        }

    $ticket_id = ts_requestFilter( "ticket_id", "/[A-HJ-NP-Z2-9\-]+/i" );

    $ticket_id = strtoupper( $ticket_id );

    $query = "SELECT * FROM $tableNamePrefix"."tickets ".
        "WHERE ticket_id = '$ticket_id';";
    ts_sendEmail_q( $query );
    }



// sends download emails for every result in a SQL query
function ts_sendEmail_q( $inQuery ) {
    global $tableNamePrefix;
    
    $result = ts_queryDatabase( $inQuery );
    
    $numRows = mysql_numrows( $result );

    echo "Based on query, sending $numRows emails:<br><br><br>\n";

    for( $i=0; $i<$numRows; $i++ ) {
        $ticket_id = mysql_result( $result, $i, "ticket_id" );
        $name = mysql_result( $result, $i, "name" );
        $email = mysql_result( $result, $i, "email" );

        echo "[$i] Sending email to $email for ticket $ticket_id ... ";
        
        $emailResult = ts_sendEmail_p( $ticket_id, $name, $email );

        if( $emailResult ) {
            echo "SUCCESS";

            $queryB = "UPDATE $tableNamePrefix"."tickets SET " .
                "email_sent = '1' " .
                "WHERE ticket_id = '$ticket_id';";
        
            $resultB = ts_queryDatabase( $queryB );
            }
        else {
            echo "FAILURE";
            }
        echo "<br><br>\n";
        flush();
        }
    }



// sends a download email for a ticket
function ts_sendEmail( $inTickeID ) {

    global $tableNamePrefix;
    
    $query = "SELECT * FROM $tableNamePrefix"."tickets ".
        "WHERE ticket_id = '$ticket_id';";
    $result = ts_queryDatabase( $query );

    $numRows = mysql_numrows( $result );

    if( $numRows == 1 ) {
        
        $row = mysql_fetch_array( $result, MYSQL_ASSOC );

        $email = $row[ "email" ];
        $name = $row[ "name" ];

        return ts_sendEmail_p( $inTickeID, $name, $email );
        }
    return 0;
    }



// sends a download email for a ticket
function ts_sendEmail_p( $inTickeID, $inName, $inEmail ) {
        
    
    global $siteName, $fullServerURL, $mainSiteURL, $siteEmailAddress,
           $extraEmailMessage;
    
    //$mailHeaders = "From: $siteEmailAddress";
    $mailHeaders = "From: $siteEmailAddress";

    $downloadURL = $fullServerURL.
        "?action=show_downloads&ticket_id=$inTickeID";

    $mailSubject = "Your [$siteName] download is ready";
    
    $mailBody = "$inName:\n\n".
        "$extraEmailMessage".
        "Your can now access your download at:\n\n".
        "  $downloadURL\n\n".
        "You can also access your download manually by ".
        "entering your ticket $inTickeID here:\n\n".
        "  $mainSiteURL\n\n";
    

    /*
    echo "\n<br>Sending mail to: $inEmail<br>\n";
    echo "Subject: $mailSubject<br>\n";
    echo "Headers: $mailHeaders<br>\n";
    echo "Body: <br>\n<pre>$mailBody</pre><br>\n";
    */
    
    $result = mail( $inEmail,
                    $mailSubject,
                    $mailBody,
                    $mailHeaders );
    return $result;
    }



function ts_sendAllNote() {
    ts_checkPassword( "send_all_note" );

    
    echo "[<a href=\"server.php?action=show_data" .
         "\">Main</a>]<br><br><br>";
    
    global $tableNamePrefix;

    $confirm = ts_requestFilter( "confirm", "/[01]/" );
    
    if( $confirm != 1 ) {
        echo "You must check the Confirm box to send emails\n";
        return;
        }
    

    // pass subject and body through without regex filter
    // these are put into emails and not put in the database
    $message_subject = "";
    if( isset( $_REQUEST[ "message_subject" ] ) ) {
        $message_subject = $_REQUEST[ "message_subject" ];
        }
    

    $message_text = "";
    if( isset( $_REQUEST[ "message_text" ] ) ) {
        $message_text = $_REQUEST[ "message_text" ];
        }
    
    
    $tag = ts_requestFilter( "tag", "/[A-Z0-9_-]+/i" );


    
    $query = "SELECT * FROM $tableNamePrefix"."tickets ".
        "WHERE tag = '$tag' AND blocked = '0' AND email_opt_in = '1' ".
        "ORDER BY sale_date ASC;";


    $tagParts = preg_split( "/_/", $tag );

    if( count( $tagParts ) == 3 ) {
        
        if( $tagParts[0] == "ALL" && $tagParts[1] == "BATCH" ) {

            // Only send one batch now, according to tag
            $numToSkip = $tagParts[2];

            global $emailMaxBatchSize;
            
            $query = "SELECT * FROM $tableNamePrefix"."tickets ".
                "WHERE blocked = '0' AND email_opt_in = '1' ".
                "ORDER BY sale_date ASC LIMIT $numToSkip, $emailMaxBatchSize;";
            }
        }
    
    
    // show opt-out URL at bottom of email
    ts_sendNote_q( $query, $message_subject, $message_text, 1 );
    }



function ts_sendAllFileNote() {
    ts_checkPassword( "send_all_note" );

    
    echo "[<a href=\"server.php?action=show_data" .
         "\">Main</a>]<br><br><br>";
    
    global $tableNamePrefix;

    $confirm = ts_requestFilter( "confirm", "/[01]/" );
    
    if( $confirm != 1 ) {
        echo "You must check the Confirm box to send emails\n";
        return;
        }
    

    // don't regex filter subject and body (destined for emails, not DB)
    $message_subject = "";
    if( isset( $_REQUEST[ "message_subject" ] ) ) {
        $message_subject = $_REQUEST[ "message_subject" ];
        }
    

    $message_text = "";
    if( isset( $_REQUEST[ "message_text" ] ) ) {
        $message_text = $_REQUEST[ "message_text" ];
        }
    
    

    $file_name = ts_requestFilter( "file_name", "/[A-Z0-9_.-]+/i" );

    	
    $query = "SELECT DISTINCT email, name FROM $tableNamePrefix"."downloads ".
        "LEFT JOIN $tableNamePrefix"."tickets ON ".
        "$tableNamePrefix"."downloads.ticket_id = ".
        "$tableNamePrefix"."tickets.ticket_id ".
        "WHERE file_name='$file_name';";
/*
    $query = "SELECT * FROM $tableNamePrefix"."tickets ".
        "WHERE tag = '$tag' AND blocked = '0' AND email_opt_in = '1';";
*/
    ts_sendNote_q( $query, $message_subject, $message_text, 0 );
    }



// sends note emails for every result in a SQL query
function ts_sendNote_q( $inQuery, $message_subject, $message_text,
                        $inShowOptOutLink ) {
    global $tableNamePrefix, $fullServerURL;
    
    $result = ts_queryDatabase( $inQuery );
    
    $numRows = mysql_numrows( $result );

    echo "Query is:<br>$inQuery<br><br>";

    echo "Based on query, sending $numRows emails:<br><br><br>\n";

    for( $i=0; $i<$numRows; $i++ ) {
        $name = mysql_result( $result, $i, "name" );
        $email = mysql_result( $result, $i, "email" );
        $ticket_id = mysql_result( $result, $i, "ticket_id" );

        echo "[$i] Sending note to $email ... ";

        $custom_message_text = $message_text;

        if( $inShowOptOutLink ) {
            $custom_message_text = $message_text .
                "\n\n" .
                "-----\n" .
                "You can opt out of future email updates by clicking the " .
                "following link:\n" .
                "  $fullServerURL?action=email_opt_in&in=0&" .
                "ticket_id=$ticket_id" .
                "\n\n";
            }
        $custom_link = "$fullServerURL?action=show_downloads&" .
            "ticket_id=$ticket_id";

        $custom_message_text =
            preg_replace( '/#DOWNLOAD_LINK#/', $custom_link,
                          $custom_message_text );

        $emailResult = ts_sendNote_p( $message_subject, $custom_message_text,
                                      $name, $email );

        if( $emailResult ) {
            echo "SUCCESS";
            }
        else {
            echo "FAILURE";
            }
        echo "<br><br>\n";
        flush();
        }
    }


// sends a note email to a specific name address
function ts_sendNote_p( $message_subject, $message_text, $inName, $inEmail ) {
        
    
    global $siteName, $fullServerURL, $mainSiteURL, $siteEmailAddress;
    //$mailHeaders = "From: $siteEmailAddress";
    $mailHeaders = "From: $siteEmailAddress";


    $mailSubject = $message_subject;
    
    $mailBody = "$inName:\n\n". $message_text ."\n\n";
    

    /*
    echo "\n<br>Sending mail to: $inEmail<br>\n";
    echo "Subject: $mailSubject<br>\n";
    echo "Headers: $mailHeaders<br>\n";
    echo "Body: <br>\n<pre>$mailBody</pre><br>\n";
    */
    
    $result = mail( $inEmail,
                    $mailSubject,
                    $mailBody,
                    $mailHeaders );
    return $result;
    }





$ts_mysqlLink;


// general-purpose functions down here, many copied from seedBlogs

/**
 * Connects to the database according to the database variables.
 */  
function ts_connectToDatabase() {
    global $databaseServer,
        $databaseUsername, $databasePassword, $databaseName,
        $ts_mysqlLink;
    
    
    $ts_mysqlLink =
        mysql_connect( $databaseServer, $databaseUsername, $databasePassword )
        or ts_operationError( "Could not connect to database server: " .
                              mysql_error() );
    
    mysql_select_db( $databaseName )
        or ts_operationError( "Could not select $databaseName database: " .
                              mysql_error() );
    }


 
/**
 * Closes the database connection.
 */
function ts_closeDatabase() {
    global $ts_mysqlLink;
    
    mysql_close( $ts_mysqlLink );
    }



/**
 * Queries the database, and dies with an error message on failure.
 *
 * @param $inQueryString the SQL query string.
 *
 * @return a result handle that can be passed to other mysql functions.
 */
function ts_queryDatabase( $inQueryString ) {
    global $ts_mysqlLink;
    
    if( gettype( $ts_mysqlLink ) != "resource" ) {
        // not a valid mysql link?
        ts_connectToDatabase();
        }
    
    $result = mysql_query( $inQueryString );
    
    if( $result == FALSE ) {

        $errorNumber = mysql_errno();
        
        // server lost or gone?
        if( $errorNumber == 2006 ||
            $errorNumber == 2013 ||
            // access denied?
            $errorNumber == 1044 ||
            $errorNumber == 1045 ||
            // no db selected?
            $errorNumber == 1046 ) {

            // connect again?
            ts_closeDatabase();
            ts_connectToDatabase();

            $result = mysql_query( $inQueryString, $ts_mysqlLink )
                or ts_operationError(
                    "Database query failed:<BR>$inQueryString<BR><BR>" .
                    mysql_error() );
            }
        else {
            // some other error (we're still connected, so we can
            // add log messages to database
            ts_fatalError( "Database query failed:<BR>$inQueryString<BR><BR>" .
                           mysql_error() );
            }
        }

    return $result;
    }



/**
 * Checks whether a table exists in the currently-connected database.
 *
 * @param $inTableName the name of the table to look for.
 *
 * @return 1 if the table exists, or 0 if not.
 */
function ts_doesTableExist( $inTableName ) {
    // check if our table exists
    $tableExists = 0;
    
    $query = "SHOW TABLES";
    $result = ts_queryDatabase( $query );

    $numRows = mysql_numrows( $result );


    for( $i=0; $i<$numRows && ! $tableExists; $i++ ) {

        $tableName = mysql_result( $result, $i, 0 );
        
        if( $tableName == $inTableName ) {
            $tableExists = 1;
            }
        }
    return $tableExists;
    }



function ts_log( $message ) {
    global $enableLog, $tableNamePrefix;

    if( $enableLog ) {
        $slashedMessage = mysql_real_escape_string( $message );
    
        $query = "INSERT INTO $tableNamePrefix"."log VALUES ( " .
            "'$slashedMessage', CURRENT_TIMESTAMP );";
        $result = ts_queryDatabase( $query );
        }
    }



/**
 * Displays the error page and dies.
 *
 * @param $message the error message to display on the error page.
 */
function ts_fatalError( $message ) {
    //global $errorMessage;

    // set the variable that is displayed inside error.php
    //$errorMessage = $message;
    
    //include_once( "error.php" );

    // for now, just print error message
    $logMessage = "Fatal error:  $message";
    
    echo( $logMessage );

    ts_log( $logMessage );
    
    die();
    }



/**
 * Displays the operation error message and dies.
 *
 * @param $message the error message to display.
 */
function ts_operationError( $message ) {
    
    // for now, just print error message
    echo( "ERROR:  $message" );
    die();
    }


/**
 * Recursively applies the addslashes function to arrays of arrays.
 * This effectively forces magic_quote escaping behavior, eliminating
 * a slew of possible database security issues. 
 *
 * @inValue the value or array to addslashes to.
 *
 * @return the value or array with slashes added.
 */
function ts_addslashes_deep( $inValue ) {
    return
        ( is_array( $inValue )
          ? array_map( 'ts_addslashes_deep', $inValue )
          : addslashes( $inValue ) );
    }



/**
 * Recursively applies the stripslashes function to arrays of arrays.
 * This effectively disables magic_quote escaping behavior. 
 *
 * @inValue the value or array to stripslashes from.
 *
 * @return the value or array with slashes removed.
 */
function ts_stripslashes_deep( $inValue ) {
    return
        ( is_array( $inValue )
          ? array_map( 'ts_stripslashes_deep', $inValue )
          : stripslashes( $inValue ) );
    }



/**
 * Filters a $_REQUEST variable using a regex match.
 *
 * Returns "" (or specified default value) if there is no match.
 */
function ts_requestFilter( $inRequestVariable, $inRegex, $inDefault = "" ) {
    if( ! isset( $_REQUEST[ $inRequestVariable ] ) ) {
        return $inDefault;
        }
    
    $numMatches = preg_match( $inRegex,
                              $_REQUEST[ $inRequestVariable ], $matches );

    if( $numMatches != 1 ) {
        return $inDefault;
        }
        
    return $matches[0];
    }



// this function checks the password directly from a request variable
// or via hash from a cookie.
//
// It then sets a new cookie for the next request.
//
// This avoids storing the password itself in the cookie, so a stale cookie
// (cached by a browser) can't be used to figure out the password and log in
// later. 
function ts_checkPassword( $inFunctionName ) {
    $password = "";
    $password_hash = "";

    $badCookie = false;
    
    
    global $accessPasswords, $tableNamePrefix, $remoteIP, $enableYubikey,
        $passwordHashingPepper;

    $cookieName = $tableNamePrefix . "cookie_password_hash";

    $passwordSent = false;
    
    if( isset( $_REQUEST[ "password" ] ) ) {
        $passwordSent = true;
        
        $password = ts_hmac_sha1( $passwordHashingPepper,
                                  $_REQUEST[ "password" ] );

        // generate a new hash cookie from this password
        $newSalt = time();
        $newHash = md5( $newSalt . $password );
        
        $password_hash = $newSalt . "_" . $newHash;
        }
    else if( isset( $_COOKIE[ $cookieName ] ) ) {
        $password_hash = $_COOKIE[ $cookieName ];
        
        // check that it's a good hash
        
        $hashParts = preg_split( "/_/", $password_hash );

        // default, to show in log message on failure
        // gets replaced if cookie contains a good hash
        $password = "(bad cookie:  $password_hash)";

        $badCookie = true;
        
        if( count( $hashParts ) == 2 ) {
            
            $salt = $hashParts[0];
            $hash = $hashParts[1];

            foreach( $accessPasswords as $truePassword ) {    
                $trueHash = md5( $salt . $truePassword );
            
                if( $trueHash == $hash ) {
                    $password = $truePassword;
                    $badCookie = false;
                    }
                }
            
            }
        }
    else {
        // no request variable, no cookie
        // cookie probably expired
        $badCookie = true;
        $password_hash = "(no cookie.  expired?)";
        }
    
        
    
    if( ! in_array( $password, $accessPasswords ) ) {

        if( ! $badCookie ) {
            
            echo "Incorrect password.";

            cd_log( "Failed $inFunctionName access with password:  ".
                    "$password" );
            }
        else {
            echo "Session expired.";
                
            cd_log( "Failed $inFunctionName access with bad cookie:  ".
                    "$password_hash" );
            }
        
        die();
        }
    else {
        
        if( $passwordSent && $enableYubikey ) {
            global $yubikeyIDs, $yubicoClientID, $yubicoSecretKey,
                $serverSecretKey;
            
            $yubikey = $_REQUEST[ "yubikey" ];

            $index = array_search( $password, $accessPasswords );
            $yubikeyIDList = preg_split( "/:/", $yubikeyIDs[ $index ] );

            $providedID = substr( $yubikey, 0, 12 );

            if( ! in_array( $providedID, $yubikeyIDList ) ) {
                echo "Provided Yubikey does not match ID for this password.";
                die();
                }
            
            
            $nonce = ts_hmac_sha1( $serverSecretKey, uniqid() );
            
            $callURL =
                "http://api2.yubico.com/wsapi/2.0/verify?id=$yubicoClientID".
                "&otp=$yubikey&nonce=$nonce";
            
            $result = trim( file_get_contents( $callURL ) );

            $resultLines = preg_split( "/\s+/", $result );

            sort( $resultLines );

            $resultPairs = array();

            $messageToSignParts = array();
            
            foreach( $resultLines as $line ) {
                // careful here, because = is used in base-64 encoding
                // replace first = in a line (the key/value separator)
                // with #
                
                $lineToParse = preg_replace( '/=/', '#', $line, 1 );

                // now split on # instead of =
                $parts = preg_split( "/#/", $lineToParse );

                $resultPairs[$parts[0]] = $parts[1];

                if( $parts[0] != "h" ) {
                    // include all but signature in message to sign
                    $messageToSignParts[] = $line;
                    }
                }
            $messageToSign = implode( "&", $messageToSignParts );

            $trueSig =
                base64_encode(
                    hash_hmac( 'sha1',
                               $messageToSign,
                               // need to pass in raw key
                               base64_decode( $yubicoSecretKey ),
                               true) );
            
            if( $trueSig != $resultPairs["h"] ) {
                echo "Yubikey authentication failed.<br>";
                echo "Bad signature from authentication server<br>";
                die();
                }

            $status = $resultPairs["status"];
            if( $status != "OK" ) {
                echo "Yubikey authentication failed: $status";
                die();
                }

            }
        
        // set cookie again, renewing it, expires in 24 hours
        $expireTime = time() + 60 * 60 * 24;
    
        setcookie( $cookieName, $password_hash, $expireTime, "/" );
        }
    }
 



function ts_clearPasswordCookie() {
    global $tableNamePrefix;

    $cookieName = $tableNamePrefix . "cookie_password_hash";

    // expire 24 hours ago (to avoid timezone issues)
    $expireTime = time() - 60 * 60 * 24;

    setcookie( $cookieName, "", $expireTime, "/" );
    }
 
 



// found here:
// http://php.net/manual/en/function.fpassthru.php

function ts_send_file( $path ) {
    session_write_close();
    //ob_end_clean();
    
    if( !is_file( $path ) || connection_status() != 0 ) {
        return( FALSE );
        }
    

    //to prevent long file from getting cut off from     //max_execution_time

    set_time_limit( 0 );

    $name = basename( $path );

    //filenames in IE containing dots will screw up the
    //filename unless we add this

    // sometimes user agent is not set!
    if( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
        
        if( strstr( $_SERVER['HTTP_USER_AGENT'], "MSIE" ) ) {
            $name =
                preg_replace('/\./', '%2e',
                             $name, substr_count($name, '.') - 1);
            }
        }
    
    
    //required, or it might try to send the serving
    //document instead of the file

    header("Cache-Control: ");
    header("Pragma: ");
    header("Content-Type: application/octet-stream");
    header("Content-Length: " .(string)(filesize($path)) );
    header('Content-Disposition: attachment; filename="'.$name.'"');
    header("Content-Transfer-Encoding: binary\n");

    if( $file = fopen( $path, 'rb' ) ) {
        while( ( !feof( $file ) )
               && ( connection_status() == 0 ) ) {
            print( fread( $file, 1024*8 ) );
            flush();
            }
        fclose($file);
        }
    return( (connection_status() == 0 ) and !connection_aborted() );
    }




function ts_hmac_sha1( $inKey, $inData ) {
    return hash_hmac( "sha1", 
                      $inData, $inKey );
    } 

 
function ts_hmac_sha1_raw( $inKey, $inData ) {
    return hash_hmac( "sha1", 
                      $inData, $inKey, true );
    } 


 
// convert a binary string into a "readable" base-32 encoding
function ts_readableBase32Encode( $inBinaryString ) {
    global $readableBase32DigitArray;
    
    $binaryDigits = str_split( $inBinaryString );

    // string of 0s and 1s
    $binString = "";
    
    foreach( $binaryDigits as $digit ) {
        $binDigitString = decbin( ord( $digit ) );

        // pad with 0s
        $binDigitString =
            substr( "00000000", 0, 8 - strlen( $binDigitString ) ) .
            $binDigitString;

        $binString = $binString . $binDigitString;
        }

    // now have full string of 0s and 1s for $inBinaryString

    return ts_readableBase32EncodeFromBitString( $inBinaryString );
    } 




// encodes a string of 0s and 1s into an ASCII readable-base32 string 
function ts_readableBase32EncodeFromBitString( $inBitString ) {
    global $readableBase32DigitArray;


    // chunks of 5 bits
    $chunksOfFive = str_split( $inBitString, 5 );

    $encodedString = "";
    foreach( $chunksOfFive as $chunk ) {
        $index = bindec( $chunk );

        $encodedString = $encodedString . $readableBase32DigitArray[ $index ];
        }
    
    return $encodedString;
    }
 


// decodes an ASCII readable-base32 string into a string of 0s and 1s 
function ts_readableBase32DecodeToBitString( $inBase32String ) {
    global $readableBase32DigitArray;
    
    $digits = str_split( $inBase32String );

    $bitString = "";

    foreach( $digits as $digit ) {
        $index = array_search( $digit, $readableBase32DigitArray );

        $binDigitString = decbin( $index );

        // pad with 0s
        $binDigitString =
            substr( "00000", 0, 5 - strlen( $binDigitString ) ) .
            $binDigitString;

        $bitString = $bitString . $binDigitString;
        }

    return $bitString;
    }
 
 
 
// decodes a ASCII hex string into an array of 0s and 1s 
function ts_hexDecodeToBitString( $inHexString ) {
        global $readableBase32DigitArray;
    
    $digits = str_split( $inHexString );

    $bitString = "";

    foreach( $digits as $digit ) {
        $index = hexdec( $digit );

        $binDigitString = decbin( $index );

        // pad with 0s
        $binDigitString =
            substr( "0000", 0, 4 - strlen( $binDigitString ) ) .
            $binDigitString;

        $bitString = $bitString . $binDigitString;
        }

    return $bitString;
    }
 


 
?>
