<?php
//Includes
include($GLOBALS['config']['private_folder'].'/classes/class.timeline.php');
//Variables

$timeline = new timeline($dbConnection);

//$timeline = $timeline->fetchPublicTimeline();
$route = $GLOBALS['url_loc'][2];

if(isset($route)){
    switch($route){
        case "fetchPublicTimeline":
            $endpointResponse = $timeline->fetchPublicTimeline()['results'];
            break;
        default:
            echo "Endpoint does not exist";
            break;
    }
} else {
    echo "Endpoint not specified";
}
    
?>
