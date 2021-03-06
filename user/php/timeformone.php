<!-- Licensed under the BSD. See License.txt for full text.  -->

<?php
/*
 backend/timeformoneprocess.php
------------------------------
Processes information on submit of form from timeformone.php
- Checks for error
- If no errors, processes information and puts it into the database
- If there is an error, redirects back to the same url with errors
*/

// Access to global variables
require_once('../../global/include.php');

$errno1 = false;
$errno2 = false;

// Select all time slot id's
$result = $db->query("SELECT slot_id FROM time_slots WHERE student_available = 't' ORDER BY start_time");
$affected_rows = mysqli_num_rows($result);

// array to hold checked slots
$selected_slots = array();

// array to hold slots inbetween two checked slots
$slotsInbetween = array();

// create an array of the values posted from previous page
$posted_values = substr($_POST['posted_values'], 0, -1);
$posted_values = explode (',', $posted_values);

// loop through all slot_ids
for ($i = 0; $i < $affected_rows; $i++) {
    $row = $result->fetch_assoc();
    $slot_id = $row['slot_id'];

    // if a slot_if is posted add it to array
    if (in_array ('ts'.$slot_id, $posted_values)) {
        array_push($selected_slots, "$slot_id");
    }

    // if a slot id is posted, add it to array
    //if (isset($_POST['ts'.$slot_id])) {
        //array_push($selected_slots, "$slot_id");
    //}
}

// If array size isn't two, then array
if (sizeof($selected_slots) != 2) {
    $errno1 = true;
}

$result = $db->query("SELECT slot_id FROM time_slots WHERE student_available = 't' ORDER BY start_time");
$affected_rows = mysqli_num_rows($result);

// array to hold slots inbetween and including the two checked slots
$slotsInbetween = array();
$startCollecting = false;

// Only check for amount of time if 2 slots are selected
if ($errno1 == false) {
    for ($i = 0; $i < $affected_rows; $i++) {
        $row = $result->fetch_assoc();
        $slot_id = $row['slot_id'];

        if ($slot_id == $selected_slots[0]) {
            $startCollecting = true;
        }

        if ($startCollecting) {
            array_push($slotsInbetween, "$slot_id");
        }

        if ($slot_id == $selected_slots[1]) {
            $startCollecting = false;
        }

    }


    //Create a string with all the selected time slots' ids (to be used in the following query)
    $values = "(";
    foreach ($slotsInbetween as $id) {
        $values .= "$id, ";
    }

    $query = "SELECT (sum(time_to_sec(TS.end_time) - time_to_sec(TS.start_time)))/60 as total_minutes FROM time_slots TS WHERE TS.slot_id in ".$values;
    $query = substr($query, 0, -2);
    $query .= ")";

    // Queries the database for the total number of minutes selected
    $result = $db->query($query);
    $row = $result->fetch_assoc();
    $total_min = $row['total_minutes'];

    if ($total_min < 720) {
        $errno2 = true;
    }
}


// If no errors, put information into database
if (($errno1 != true && $errno2 != true)) {

    // Delete old student arrival and departure values
    $db->query("DELETE FROM `student_arrivals` WHERE `student_id`=".$_SESSION['student_id']);
    $db->query("DELETE FROM `student_departures` WHERE `student_id`=".$_SESSION['student_id']);

    // Insert new student arrival and departure values
    $db->query("INSERT INTO `student_arrivals` (`student_id`, `slot_id`) VALUES ('".$_SESSION['student_id']."', '".$selected_slots[0]."')");
    $db->query("INSERT INTO `student_departures` (`student_id`, `slot_id`) VALUES ('".$_SESSION['student_id']."', '".$selected_slots[1]."')");

    // Creates a values string holding the student's available times
    $values = "";
    foreach ($slotsInbetween as $id) {
        $values .= "(".$_SESSION['student_id'].",$id), ";
    }

    // Delete old student availability times
    $db->query("DELETE FROM `student_availability` WHERE `student_id` =".$_SESSION['student_id']);

    // Insert new student availability times
    $query = "INSERT INTO `student_availability` (`student_id`, `slot_id`) VALUES ".$values;
    $query = substr($query, 0, -2);
    $db->query($query);

    $db->query("UPDATE `students` SET `times_complete`='t' WHERE `student_id`=".$_SESSION['student_id']);

    echo "true";

}

// Else, send back to same url with error added
else {

    // Errors to send back
    $response = "";

    // If the following error numbers exist then they are to be added to the URL to be displayed on register.php
    if ($errno1 == true) {
        $response .= "err1,";
    }

    if ($errno2 == true) {
        $response .= "err2,";
    }

    $response = substr($response, 0, -1);

    // Send back the appropriate errors
    echo $response;
}

?>
