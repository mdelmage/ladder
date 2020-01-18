<?php
//error_reporting(E_ALL);
//   _       _ _   
//  (_)     (_) |  
//   _ _ __  _| |_ 
//  | | '_ \| | __|
//  | | | | | | |_ 
//  |_|_| |_|_|\__|
//                 
//  (ASCII art by http://patorjk.com/software/taag/)        

// Modfiy these to match your database credentials
$dbname = "";
$dbuser = "";
$dbpass = "";
$dbhost = 'localhost';
$varchar = 'VARCHAR(40)';

// Elo ranking parameters
$default_elo = 1200;
$k = 32;
$max_streak = 10;
$max_streak_count = 10;

// Add your Slack app's signing secret here
$signing_secret = "";

$command_name = "/ladder";
$bot_name = "ladder";
$version = "0.8.6";

$win_thesaurus =
array("beat",
"p6ed",
"tied",
"matched",
"split",
"rocked",
"sandbagged",
"punished",
"ambushed",
"overestimated",
"upset",
"whooped",
"defeated",
"bamboozled",
"crushed",
"surprised",
"outlasted",
"smashed",
"wrecked",
"massacred",
"walloped",
"pummeled",
"destroyed",
"devastated",
"annihilated",
"obliterated",
"smote",
"withstood",
"sebastianed",
"survived",
"endured",
"decimated",
"dispatched",
"terminated",
"liquidated",
"neutralized",
"eliminated",
"finished",
"vanquished",
"thrashed",
"trounced",
"routed",
"waxed",
"bested",
"overcame",
"steamrolled",
"hammered",
"drubbed",
"lambasted",
"slammed",
"humbled",
"overpowered",
"conquered",
"smoked");

$emoji = array();
$emoji_default = "◻️";

$font_size = 12;
$font = 'fonts/lato_2.007/Lato-Regular.ttf';

$ladder_print_len_default = 10;
$ladder_print_len_max = 20;

$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);

// Slack expects a JSON response, so set up the common stuff here
header('Content-Type: application/json');
$rsp = ["response_type" => "in_channel", "text" => ""];

// Could be the first invocation; set up tables in the DB as required
$instance_id = $_POST["team_id"] . "_" . $_POST["channel_id"];
$team_id = $_POST["team_id"];
$user_id = $_POST["user_id"];
$response_url = $_POST["response_url"];
init_instance();


//    __                  _   _                 
//   / _|                | | (_)                
//  | |_ _   _ _ __   ___| |_ _  ___  _ __  ___ 
//  |  _| | | | '_ \ / __| __| |/ _ \| '_ \/ __|
//  | | | |_| | | | | (__| |_| | (_) | | | \__ \
//  |_|  \__,_|_| |_|\___|\__|_|\___/|_| |_|___/
//                                              

function calculate_text_box($font_size, $font_angle, $font_file, $text) { 
  $box   = imagettfbbox($font_size, $font_angle, $font_file, $text); 
  if( !$box ) 
    return false; 
  $min_x = min( array($box[0], $box[2], $box[4], $box[6]) ); 
  $max_x = max( array($box[0], $box[2], $box[4], $box[6]) ); 
  $min_y = min( array($box[1], $box[3], $box[5], $box[7]) ); 
  $max_y = max( array($box[1], $box[3], $box[5], $box[7]) ); 
  $width  = ( $max_x - $min_x ); 
  $height = ( $max_y - $min_y ); 
  $left   = abs( $min_x ) + $width; 
  $top    = abs( $min_y ) + $height; 
  // to calculate the exact bounding box i write the text in a large image 
  $img     = @imagecreatetruecolor( $width << 2, $height << 2 ); 
  $white   =  imagecolorallocate( $img, 255, 255, 255 ); 
  $black   =  imagecolorallocate( $img, 0, 0, 0 ); 
  imagefilledrectangle($img, 0, 0, imagesx($img), imagesy($img), $black); 
  // for sure the text is completely in the image! 
  imagettftext( $img, $font_size, 
                $font_angle, $left, $top, 
                $white, $font_file, $text); 
  // start scanning (0=> black => empty) 
  $rleft  = $w4 = $width<<2; 
  $rright = 0; 
  $rbottom   = 0; 
  $rtop = $h4 = $height<<2; 
  for( $x = 0; $x < $w4; $x++ ) 
    for( $y = 0; $y < $h4; $y++ ) 
      if( imagecolorat( $img, $x, $y ) ){ 
        $rleft   = min( $rleft, $x ); 
        $rright  = max( $rright, $x ); 
        $rtop    = min( $rtop, $y ); 
        $rbottom = max( $rbottom, $y ); 
      } 
  // destroy img and serve the result 
  imagedestroy( $img ); 
  return array( "left"   => $left - $rleft, 
                "top"    => $top  - $rtop, 
                "width"  => $rright - $rleft + 1, 
                "height" => $rbottom - $rtop + 1 ); 
} 

function validate_slack_request()
{
    global $signing_secret;

    $validation_version = "v0";
    $request_time = $_SERVER['HTTP_X_SLACK_REQUEST_TIMESTAMP'];
    $signature = $_SERVER['HTTP_X_SLACK_SIGNATURE'];

    // Check if request was within the last 5 minutes
    $recent_time = (abs(time() - $request_time) < (60 * 5));

    // Now hash the request with our secret
    $validation_str = $validation_version . ":" . $request_time . ":";
    foreach($_REQUEST as $key => $value) {
        $validation_str .= $key . "=" . urlencode($value) . "&";
    }
    $validation_str = substr($validation_str, 0, -1);
    $hash = $validation_version . "=" . hash_hmac("sha256", $validation_str, $signing_secret);

    return ($recent_time && ($hash == $signature));
}

function retrieve_slack_emoji()
{
    global $mysqli;
    global $team_id;
    global $emoji;
    global $bot_name;

    $sql = "SELECT * from " . $team_id . " LIMIT 1;";
    $result = $mysqli->query($sql);
    if ($result->num_rows == 0) {
        return;
    }
    $row = mysqli_fetch_assoc($result);
    $token = $row["token"];

    $url = "https://slack.com/api/emoji.list?token=" . $token;

    // use key 'http' even if you send the request to https://...
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'GET',
            'content' => ""
        )
    );

    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $rsp = json_decode($result, true);

    if ($rsp["ok"] == true) {
        // Clear and rebuild the emoji list
        $emoji = array();

        foreach($rsp["emoji"] as $key => $value) {
            if (starts_with($key, $bot_name . "_")) {
                $emoji[] = $key;
            }
        }
    }

    // Push back to the database
    $sql = "UPDATE " . $team_id . " SET emoji='" . implode(" ", $emoji) . "' WHERE 1;";
    $result = $mysqli->query($sql);
}

function retrieve_slack_username($user_id)
{

    global $mysqli;
    global $team_id;
    global $emoji;
    global $bot_name;

    $sql = "SELECT * from " . $team_id . " LIMIT 1;";
    $result = $mysqli->query($sql);
    if ($result->num_rows == 0) {
        return;
    }
    $row = mysqli_fetch_assoc($result);
    $token = $row["token"];

    $url = "https://slack.com/api/users.info?token=" . $token . "&user=" . $user_id;

    // use key 'http' even if you send the request to https://...
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'GET',
            'content' => ""
        )
    );

    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $rsp = json_decode($result, true);

    if ($rsp["ok"] == true) {
        set_notification_text("success: " . $rsp["user"]["real_name"]);
        return $url . ": API success";
    }
    else {
        set_notification_text("failure: " . implode(" ", $rsp));
        return $url . ": API failure";
    }
}

function slack_emoji($text)
{
    global $emoji;
    global $team_id;
    global $emoji_default;

    // TODO: workaround until we get emoji tokens on all workspaces
    if ($team_id == "TDT7CKK3R" || in_array($text, $emoji)) {
        return ":" . $text . ":";
    }
    else {
        return $emoji_default;
    }
}

function probability($rating1, $rating2)
{
  return 1.0 * 1.0 / (1 + 1.0 * pow(10, 1.0 * ($rating1 - $rating2) / 400));
}

function get_elo_delta($elo_winner, $elo_loser)
{  
   global $k;

    // Calculate the Winning Probability of Player A
    $p_winner = probability($elo_loser, $elo_winner);
 
    return (int)(round($k * (1 - $p_winner)));
}

function get_elo_delta_for_matches($player_a, $player_b, $wins_losses)
{
    global $k;
    global $max_streak;
    global $max_streak_count;

    $streak_capped = false;
    $running_elo_a = $player_a["elo"];
    $running_elo_b = $player_b["elo"];
    $wins_a = 0;
    $wins_b = 0;

    // Alternate win/loss reporting
    $player_a_won = true;

    foreach (array_slice($wins_losses, 0, $max_streak_count) as $streak) {
        if ($streak > $max_streak) {
            $streak_capped = true;
        }

        for ($i = 0; $i < min($streak, $max_streak); $i++) {
            $elo_delta = get_elo_delta($running_elo_a, $running_elo_b);
            $elo_delta_reverse = get_elo_delta($running_elo_b, $running_elo_a);
            $match_rating = abs(abs($k - $elo_delta) - $elo_delta) / $k;

            if ($player_a_won) {
                $wins_a++;
                $running_elo_a += $elo_delta;
                $running_elo_b -= $elo_delta;
            }
            else {
                $wins_b++;
                $running_elo_b += $elo_delta_reverse;
                $running_elo_a -= $elo_delta_reverse;
            }
        }
        $player_a_won = !$player_a_won;
    }

    if ($streak_capped || (sizeof($wins_losses) > $max_streak_count)) {
        add_response_section_context("Matches are limited to " . $max_streak_count . " 'streaks', with up to " . $max_streak . " games per streak.");
    }

    return ($running_elo_a - $player_a["elo"]);
}

function set_notification_text($text)
{
    global $rsp;

    // Most responses happen in the "blocks" section, but mobile and preview
    // notifications come from the "text" section. Set that here.
    $rsp["text"] = $text;
}

function respond_private($text = NULL)
{
    global $rsp;
    $rsp["response_type"] = "ephemeral";

    if ($text) {
        $rsp["text"] = $text;
    }
}

function fixed_width_string($text, $pixels)
{
    // TL;DR: Slack is dumb.
    // It has no built-in table support.
    // Fixed-width (code blocks) is nice but does not support emoji or
    // inline replacement of @mentions.
    // So, use GD to measure strings, and pad them with HAIR SPACE (U+200A).

    // Resolution and font size that HAIR SPACE (U+200A) is a single pixel wide
    // Use with str_repeat to build pixel-perfect tables and fields
    $hair_space = " "; // U+200A

    global $font;
    global $font_size;

    $bbox = calculate_text_box($font_size, 0, $font, $text . "|");
    $text2 = $text . str_repeat($hair_space, max(0, $pixels - $bbox["width"] - 10));

    $width = 0;

    while ($width < $pixels) {
        $text2 .= $hair_space;
        $bbox = calculate_text_box($font_size, 0, $font, $text2 . "|");
        $width = $bbox["width"] - $bbox["left"];
    }

    return $text2;
}

function escaped_name($player)
{
    return "<@" . $player["id"] . ">";
}
function starts_with($haystack, $needle)
{
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

function contains($haystack, $haystack)
{
    return (strpos($haystack, $haystack) !== false);
}

function looks_like_user_id($user_string)
{
    return (starts_with($user_string, "<@") || (strtolower($user_string) === "i") || (strtolower($user_string) === "me"));
}

function mention_to_user_id($mention_string)
{
    global $user_id;

    if (strtolower($mention_string) === "i" || strtolower($mention_string) === "me") {
        $user = $user_id;
    }
    else {
        // Slack user mention strings take the form of "<@UID|name>", and we want UID.
        preg_match("/\w+/", substr($mention_string, 2), $matches);
        $user = $matches[0];
    }

    return $user;
}

function mention_to_user_name($mention_string)
{
    // Slack user mention strings take the form of "<@UID|name>", and we want the latter portion (name).
    preg_match("/\|[ \w]+/", substr($mention_string, 2), $matches);
    if (sizeof($matches) > 0) {
        $name = substr($matches[0], 1);
    }
    else {
        $name = mention_to_user_id($mention_string);
    }

    return $name;
}

function count_matches()
{
    global $mysqli;
    global $instance_id;

    $sql = "SELECT COUNT(*) AS num_matches from " . $instance_id . "_matches";
    $result = $mysqli->query($sql);
    $row = mysqli_fetch_assoc($result);
    return $row["num_matches"];
}

function count_players()
{
    global $mysqli;
    global $instance_id;

    $sql = "SELECT COUNT(*) AS num_players from " . $instance_id . "_users";
    $result = $mysqli->query($sql);
    $row = mysqli_fetch_assoc($result);
    return $row["num_players"];
}

function init_instance()
{
  global $mysqli;
  global $varchar;
  global $team_id;
  global $instance_id;
  global $emoji;

  $sql = "SELECT 1 from " . $instance_id . "_matches LIMIT 1";
  $result = $mysqli->query($sql);
  if ($result->num_rows == 0) {
      $sql = "CREATE TABLE " . $instance_id . "_matches ( `id` INT NOT NULL AUTO_INCREMENT , `match_date` DATETIME NOT NULL , `winner_id` " . $varchar . " NOT NULL , `loser_id` " . $varchar . " NOT NULL , `winner2_id` " . $varchar . " NULL , `loser2_id` " . $varchar . " NULL , `winner_elo` INT NULL, `loser_elo` INT NULL, `winner2_elo` INT NULL, `loser2_elo` INT NULL, PRIMARY KEY (`id`))";
      $mysqli->query($sql);
  }

  $sql = "SELECT 1 from " . $instance_id . "_users LIMIT 1";
  $result = $mysqli->query($sql);
  if ($result->num_rows == 0) {
      $sql = "CREATE TABLE " . $instance_id . "_users ( `id` " . $varchar . " NOT NULL, `elo` INT NOT NULL , `wins` INT NOT NULL , `losses` INT NOT NULL , `dwins` INT NOT NULL , `dlosses` INT NOT NULL , `deactivated` INT NULL, PRIMARY KEY (`id`))";
      $mysqli->query($sql);
  }

  // Shim for 'deactivated' column
  $sql = "SELECT * FROM information_schema.COLUMNS WHERE COLUMN_NAME = 'deactivated' AND TABLE_NAME = '" . $instance_id . "_users' AND TABLE_SCHEMA = '" . $dbname . "'";
  $result = $mysqli->query($sql);
  if ($result->num_rows == 0) {
      $sql = "ALTER TABLE " . $instance_id . "_users ADD `deactivated` INT NULL AFTER `dlosses`;";
      $mysqli->query($sql);
  }

  $sql = "SELECT 1 from " . $instance_id . "_ratings LIMIT 1";
  $result = $mysqli->query($sql);
  if ($result->num_rows == 0) {
      $sql = "CREATE TABLE " . $instance_id . "_ratings ( `id` INT NOT NULL AUTO_INCREMENT , `rating_date` DATETIME NOT NULL , `player_id` " . $varchar . " NOT NULL , `elo` INT NOT NULL, PRIMARY KEY (`id`))";
      $mysqli->query($sql);
  }

  $sql = "SELECT emoji from " . $team_id . " LIMIT 1";
  $result = $mysqli->query($sql);
  if ($result->num_rows > 0) {
      $row = mysqli_fetch_assoc($result);
      $emoji = explode(" ", $row["emoji"]);
  }
}

function init_user($uid)
{
    $user["id"] = $uid;
    return $user;
}

function update_user($user)
{
  global $mysqli;
  global $default_elo;
  global $instance_id;

  $sql = "SELECT * from " . $instance_id . "_users WHERE id = '" . $user["id"] . "'";
  $result = $mysqli->query($sql);

  if ($result->num_rows == 0) {
    $sql = "INSERT into " . $instance_id . "_users (id, elo, wins, losses, dwins, dlosses) VALUES ('" . $user["id"] . "', " . $default_elo . ", 0, 0, 0, 0)";
    $mysqli->query($sql);
  }

  $sql = "SELECT * from " . $instance_id . "_users WHERE id = '" . $user["id"] . "'";
  $result = $mysqli->query($sql);
  $row = mysqli_fetch_assoc($result);

  $user["name"] = "Bob Smith";
  $user["elo"] = $row["elo"];
  $user["wins"] = $row["wins"];
  $user["losses"] = $row["losses"];
  $user["dwins"] = $row["dwins"];
  $user["dlosses"] = $row["dlosses"];
  $user["deactivated"] = false;

  if ($row["deactivated"] != NULL) {
      $user["deactivated"] = ($row["deactivated"] > 0);
  }

  return $user;
}

function record_match($winner, $loser)
{
  global $mysqli;
  global $instance_id;

  $elo_delta = get_elo_delta($winner["elo"], $loser["elo"]);

  $sql = "INSERT into " . $instance_id . "_matches (match_date, winner_id, loser_id, winner_elo, loser_elo) VALUES (NOW(), '" . $winner["id"] . "', '" . $loser["id"] . "', " . ($winner["elo"] + $elo_delta) . ", " . ($loser["elo"] - $elo_delta) . ");";
  $mysqli->query($sql);

  // Boost the winner's rating
  $sql = "UPDATE " . $instance_id . "_users SET elo = " . ($winner["elo"] + $elo_delta) . ", wins = " . ($winner["wins"] + 1) . " WHERE id = '" . $winner["id"] . "';";
  $mysqli->query($sql);

  // Decrease the loser's rating
  $sql = "UPDATE " . $instance_id . "_users SET elo = " . ($loser["elo"] - $elo_delta) . ", losses = " . ($loser["losses"] + 1) . " WHERE id = '" . $loser["id"] . "';";
  $mysqli->query($sql);

  // Record both ratings for potential graphing
  $sql = "INSERT into " . $instance_id . "_ratings (rating_date, player_id, elo) VALUES (NOW(), '" . $winner["id"] . "', " . ($winner["elo"]  + $elo_delta) . ");";
  $mysqli->query($sql);
  $sql = "INSERT into " . $instance_id . "_ratings (rating_date, player_id, elo) VALUES (NOW(), '" . $loser["id"] . "', " . ($loser["elo"] - $elo_delta) . ");";
  $mysqli->query($sql);
}

function record_doubles_match($winner_a, $winner_b, $loser_a, $loser_b)
{
  global $mysqli;
  global $instance_id;

  // Calculate the average ratings and only provide a half-effect ratings change
  $elo_delta = (int)(get_elo_delta(($winner_a["elo"] + $winner_b["elo"]) / 2, ($loser_a["elo"] + $loser_b["elo"]) / 2) / 2);
  
  $sql = "INSERT into " . $instance_id . "_matches (match_date, winner_id, loser_id, winner2_id, loser2_id, winner_elo, loser_elo, winner2_elo, loser2_elo) VALUES (NOW(), '" . $winner_a["id"] . "', '" . $loser_a["id"] . "', '" . $winner_b["id"] . "', '" . $loser_b["id"]. "', " . ($winner_a["elo"] + $elo_delta) . ", " . ($loser_a["elo"] - $elo_delta) . ", " . ($winner_b["elo"] + $elo_delta) . ", " . ($loser_b["elo"] - $elo_delta) . ");";
  $mysqli->query($sql);

  // Boost the winners' ratings
  $sql = "UPDATE " . $instance_id . "_users SET elo = " . ($winner_a["elo"] + $elo_delta) . ", dwins = " . ($winner_a["dwins"] + 1) . " WHERE id = '" . $winner_a["id"] . "';";
  $mysqli->query($sql);
  
  $sql = "UPDATE " . $instance_id . "_users SET elo = " . ($winner_b["elo"] + $elo_delta) . ", dwins = " . ($winner_b["dwins"] + 1) . " WHERE id = '" . $winner_b["id"] . "';";
  $mysqli->query($sql);

  // Decrease the losers' ratings
  $sql = "UPDATE " . $instance_id . "_users SET elo = " . ($loser_a["elo"] - $elo_delta) . ", dlosses = " . ($loser_a["dlosses"] + 1) . " WHERE id = '" . $loser_a["id"] . "';";
  $mysqli->query($sql);

  $sql = "UPDATE " . $instance_id . "_users SET elo = " . ($loser_b["elo"] - $elo_delta) . ", dlosses = " . ($loser_b["dlosses"] + 1) . " WHERE id = '" . $loser_b["id"] . "';";
  $mysqli->query($sql);   

  // Record all four ratings for potential graphing
  $sql = "INSERT into " . $instance_id . "_ratings (rating_date, player_id, elo) VALUES (NOW(), '" . $winner_a["id"] . "', " . ($winner_a["elo"]  + $elo_delta) . ");";
  $mysqli->query($sql);
  $sql = "INSERT into " . $instance_id . "_ratings (rating_date, player_id, elo) VALUES (NOW(), '" . $loser_a["id"] . "', " . ($loser_a["elo"] - $elo_delta) . ");";
  $mysqli->query($sql);
  $sql = "INSERT into " . $instance_id . "_ratings (rating_date, player_id, elo) VALUES (NOW(), '" . $winner_b["id"] . "', " . ($winner_b["elo"]  + $elo_delta) . ");";
  $mysqli->query($sql);
  $sql = "INSERT into " . $instance_id . "_ratings (rating_date, player_id, elo) VALUES (NOW(), '" . $loser_b["id"] . "', " . ($loser_b["elo"] - $elo_delta) . ");";
  $mysqli->query($sql);
}
function singles_match($player_a, $player_b, $wins_losses)
{
    global $max_streak;
    global $max_streak_count;
    $old_elo = $player_a["elo"];
    $streak_capped = false;

    // Alternate win/loss reporting
    $player_a_won = true;

    foreach (array_slice($wins_losses, 0, $max_streak_count) as $streak) {
        if ($streak > $max_streak) {
            $streak_capped = true;
        }

        for ($i = 0; $i < min($streak, $max_streak); $i++) {
            if ($player_a_won) {
                record_match($player_a, $player_b);
            }
            else {
                record_match($player_b, $player_a);
            }

            $player_a = update_user($player_a);
            $player_b = update_user($player_b);
        }
        $player_a_won = !$player_a_won;
    }

    if ($streak_capped || (sizeof($wins_losses) > $max_streak_count)) {
        add_response_section_context("Matches are limited to " . $max_streak_count . " 'streaks', with up to " . $max_streak . " games per streak.");
    }

    $new_elo = $player_a["elo"];
    $elo_delta = abs($old_elo - $new_elo);
    if ($new_elo > $old_elo) {
        return escaped_name($player_a) . " ← " . $elo_delta . " ← " . escaped_name($player_b);
    }
    else {
        return escaped_name($player_a) . " → " . $elo_delta . " → " . escaped_name($player_b);
    }
}

function doubles_match($player_a, $player_b, $player_c, $player_d, $wins_losses)
{
    global $max_streak;
    global $max_streak_count;
    $old_elo = $player_a["elo"];
    $streak_capped = false;

    // Alternate win/loss reporting
    $player_a_won = true;

    foreach (array_slice($wins_losses, 0, $max_streak_count) as $streak) {
        if ($streak > $max_streak) {
            $streak_capped = true;
        }

        for ($i = 0; $i < min($streak, $max_streak); $i++) {
            if ($player_a_won) {
                record_doubles_match($player_a, $player_b, $player_c, $player_d);
            }
            else {
                record_doubles_match($player_c, $player_d, $player_a, $player_b);
            }

            $player_a = update_user($player_a);
            $player_b = update_user($player_b);
            $player_c = update_user($player_c);
            $player_d = update_user($player_d);
        }
        $player_a_won = !$player_a_won;
    }

    if ($streak_capped || (sizeof($wins_losses) > $max_streak_count)) {
        add_response_section_context("Matches are limited to " . $max_streak_count . " 'streaks', with up to " . $max_streak . " games per streak.");
    }

    $new_elo = $player_a["elo"];
    $elo_delta = abs($old_elo - $new_elo);
    if ($new_elo > $old_elo) {
        return escaped_name($player_a) . " ← " . $elo_delta . " ← " . escaped_name($player_c) . "\r\n" . escaped_name($player_b) . " ← " . $elo_delta . " ← " . escaped_name($player_d);
    }
    else {
        return escaped_name($player_a) . " → " . $elo_delta . " → " . escaped_name($player_c) . "\r\n" . escaped_name($player_b) . " → " . $elo_delta . " → " . escaped_name($player_d);
    }
}

function explode_scores($score_string)
{
    if (preg_match("/^[[:digit:]]+-[[:digit:]]+(-[[:digit:]])*/", $score_string, $matches)) {
        return explode("-", $matches[0]);
    }
    else {
        return array();
    }
}

function add_response_section_context($text)
{
    global $rsp;
    $rsp["blocks"][] = ["type" => "context", "elements" => [["type" => "mrkdwn", "text" => $text]]];
}

function add_response_section_fancy_text($text)
{
    global $rsp;
    $rsp["blocks"][] = ["type" => "section", "text" => ["type" => "mrkdwn", "text" => $text]];
}

function add_response_section_plain_text($text)
{
    global $rsp;
    $rsp["blocks"][] = ["type" => "section", "text" => ["type" => "plain_text", "text" => $text]];
}

function add_response_section_divider()
{
    global $rsp;
    $rsp["blocks"][] = ["type" => "divider"];
}

function add_response_section_image($url, $caption)
{
    global $rsp;
    $rsp["blocks"][] = ["type" => "image", "title" => ["type" => "plain_text", "text" => $caption, "emoji" => true], "image_url" => $url, "alt_text" => $caption];
}

function format_preview($player_a, $player_b, $wins_losses, $wins, $losses, $wins_losses_str)
{
    $elo = get_elo_delta_for_matches($player_a, $player_b, $wins_losses);

    $text = fixed_width_string($elo, 100) .
            fixed_width_string($wins_losses_str, 100) .
            fixed_width_string(-1 * $elo, 30);

    if (($elo < 0) && ($wins > $losses)) {
        $text .= ":exclamation:";
    }
    $text .= "\r\n";

    return $text;
}

function command_preview($token_a, $token_b, $wins_losses)
{
    global $k;
    global $max_streak;
    global $max_streak_count;

    // Calculate who the players are.
    $uid_a = mention_to_user_id($token_a);
    $uid_b = mention_to_user_id($token_b);

    if ($uid_a == $uid_b) {
        respond_private("Yeah, no. :no_entry:");
    }
    else {
        $player_a = init_user($uid_a);
        $player_b = init_user($uid_b);
        $player_a = update_user($player_a);
        $player_b = update_user($player_b);

        command_users(array($player_a, $player_b));

        $elo_delta = get_elo_delta($player_a["elo"], $player_b["elo"]);
        $match_rating = abs(abs($k - $elo_delta) - $elo_delta) / $k;

        $text = "Category: ";
        if ($match_rating < 0.1) {
            $text .= "*Fair Fight*";
        }
        else if ($match_rating < 0.2) {
            $text .= "*Slight Mismatch*";
        }
        else if ($match_rating < 0.4) {
            $text .= "*Risky*";
        }
        else if ($match_rating < 0.6) {
            $text .= "*Extremely Mismatched*";
        }
        else if ($match_rating < 0.8) {
            $text .= "*Ambush*";
        }
        else {
            $text .= "*LOL*";
        }
        $text .= "\r\n\r\n";

        if (sizeof($wins_losses)) {
            $streak_capped = false;
            $running_elo_a = $player_a["elo"];
            $running_elo_b = $player_b["elo"];
            $wins_a = 0;
            $wins_b = 0;

            // Alternate win/loss reporting
            $player_a_won = true;

            foreach (array_slice($wins_losses, 0, $max_streak_count) as $streak) {
                if ($streak > $max_streak) {
                    $streak_capped = true;
                }

                for ($i = 0; $i < min($streak, $max_streak); $i++) {
                    $elo_delta = get_elo_delta($running_elo_a, $running_elo_b);
                    $elo_delta_reverse = get_elo_delta($running_elo_b, $running_elo_a);
                    $match_rating = abs(abs($k - $elo_delta) - $elo_delta) / $k;

                    if ($player_a_won) {
                        $wins_a++;
                        $running_elo_a += $elo_delta;
                        $running_elo_b -= $elo_delta;
                    }
                    else {
                        $wins_b++;
                        $running_elo_b += $elo_delta_reverse;
                        $running_elo_a -= $elo_delta_reverse;
                    }
                }
                $player_a_won = !$player_a_won;
            }

            if ($streak_capped || (sizeof($wins_losses) > $max_streak_count)) {
                add_response_section_context("Matches are limited to " . $max_streak_count . " 'streaks', with up to " . $max_streak . " games per streak.");
            }

            if (($running_elo_a - $player_a["elo"]) > 0) {
                $text .= "*" . escaped_name($player_a) . "* would take " . ($running_elo_a - $player_a["elo"]) . " Elo from " . escaped_name($player_b);
                
                if ($wins_b >= $wins_a) {
                    $text .= ", even after going " . $wins_a . "-" . $wins_b . " overall!\r\n";
                }
                else {
                    $text .= ".\r\n";
                }
            }
            else if (($running_elo_b - $player_b["elo"]) > 0) {
                $text .= "*" . escaped_name($player_b) . "* would take " . ($running_elo_b - $player_b["elo"]) . " Elo from " . escaped_name($player_a);

                if ($wins_a >= $wins_b) {
                    $text .= ", even after going " . $wins_b . "-" . $wins_a . " overall!\r\n";
                }
                else {
                    $text .= ".\r\n";
                }
            }
            else {
                $text .= "No Elo exchanged after " . ($wins_a + $wins_b) . " games!\r\n";
            }

            set_notification_text(escaped_name($player_a) . " vs. " . escaped_name($player_b));
            add_response_section_fancy_text($text);
        }
        else {
            $text .= fixed_width_string("Player A", 100) . fixed_width_string("Result", 100) . "Player B\r\n";
            add_response_section_fancy_text($text);
            add_response_section_divider();

            $text = format_preview($player_a, $player_b, array(1, 0), 1, 0, "1-0");
            $text .= format_preview($player_a, $player_b, array(0, 1), 0, 1, "0-1");
            $text .= "\r\n";

            $text .= format_preview($player_a, $player_b, array(2, 0), 2, 0, "2-0");
            $text .= format_preview($player_a, $player_b, array(1, 1), 1, 1, "1-1");
            $text .= format_preview($player_a, $player_b, array(0, 2), 0, 2, "0-2");
            $text .= "\r\n";

            $text .= format_preview($player_a, $player_b, array(3, 0), 3, 0, "3-0");
            $text .= format_preview($player_a, $player_b, array(2, 1), 2, 1, "2-1");
            $text .= format_preview($player_a, $player_b, array(1, 1, 1), 2, 1, "1-1-1");
            $text .= format_preview($player_a, $player_b, array(1, 2), 1, 2, "1-2");
            $text .= format_preview($player_a, $player_b, array(0, 3), 0, 3, "0-3");
            $text .= "\r\n";
/*
            add_response_section_fancy_text($text);
            $text .= escaped_name($player_a) . " would earn " . $elo_delta . " Elo.\r\n";
            $text .= escaped_name($player_b) . " would earn " . abs($k - $elo_delta) . " Elo.\r\n";
*/
            set_notification_text(escaped_name($player_a) . " vs. " . escaped_name($player_b));
            add_response_section_fancy_text($text);
        }
    }
}

function sql_list($sql)
{
    global $mysqli;
    global $command_name;
    global $ladder_print_len_max;

    $text = "";
    $players = array();

    $result = $mysqli->query($sql);

    if ($result->num_rows > $ladder_print_len_max) {
        $context = "Output limited to " . $ladder_print_len_max . " players";
        
        add_response_section_context($context);
    }

    $text = slack_emoji("ladder_blank") . \
        fixed_width_string(" Elo", 60) . \
        fixed_width_string("W", 35) . \
        fixed_width_string("L", 35) .
        "Player\r\n";

    add_response_section_fancy_text($text);
    add_response_section_divider();

    if ($result->num_rows == 0) {
        return;
    }

    $text = "";
    $list_length = min($ladder_print_len_max, $result->num_rows);
    for ($i = 0; $i < $list_length; $i++) {
        $row = mysqli_fetch_assoc($result);
        $player = init_user($row["id"]);
        $player = update_user($player);
        if ($player["deactivated"]) {
            $elo_medallion = slack_emoji("ladder_rip");
        }
        else {
            $elo_medallion = slack_emoji("ladder_" . (floor($player["elo"] / 50) * 50));
        }

        if (($list_length == 1) || !($player["deactivated"])) {
            $text .= $elo_medallion . \
                fixed_width_string(" " + $player["elo"], 60) . \
                fixed_width_string($player["wins"] + $player["dwins"], 35) . \
                fixed_width_string($player["losses"] + $player["dlosses"], 35) . \
                escaped_name($player) . "\r\n";
        }
    }
    add_response_section_fancy_text($text);
}

function command_top($n)
{
    global $instance_id;
    global $team_id;

    $where_clause = "WHERE (wins + dwins + losses + dlosses) > 0 AND deactivated is NULL";
    $sql = "SELECT id, elo from " . $instance_id . "_users " . $where_clause . " ORDER BY elo DESC LIMIT " . $n;
    sql_list($sql);
}

function command_bottom($n)
{
    global $instance_id;
    global $team_id;

    $where_clause = "WHERE (wins + dwins + losses + dlosses) > 0 AND deactivated is NULL";
    $sql = "SELECT * FROM (SELECT id, elo from " . $instance_id . "_users " . $where_clause . " ORDER BY elo LIMIT " . $n . ") result ORDER BY elo DESC";
    sql_list($sql);
}

function command_users($users)
{
    global $instance_id;

    $users_str = "id = '" . $users[0]["id"] . "'";
    for ($i = 1; $i < count($users); $i++) {
        $users_str .= " OR id = '" . $users[$i]["id"] . "'";
    }


    $sql = "SELECT * from " . $instance_id . "_users WHERE " . $users_str . " ORDER BY ELO DESC";
    sql_list($sql);
}

function cleanup()
{
    global $mysqli;
    $mysqli->close();
}

//                                                 _ 
//                                                | |
//    ___ ___  _ __ ___  _ __ ___   __ _ _ __   __| |
//   / __/ _ \| '_ ` _ \| '_ ` _ \ / _` | '_ \ / _` |
//  | (_| (_) | | | | | | | | | | | (_| | | | | (_| |
//   \___\___/|_| |_| |_|_| |_| |_|\__,_|_| |_|\__,_|
//                                                   
//                                                   
//                                     _             
//                                    (_)            
//   _ __  _ __ ___   ___ ___  ___ ___ _ _ __   __ _ 
//  | '_ \| '__/ _ \ / __/ _ \/ __/ __| | '_ \ / _` |
//  | |_) | | | (_) | (_|  __/\__ \__ \ | | | | (_| |
//  | .__/|_|  \___/ \___\___||___/___/_|_| |_|\__, |
//  | |                                         __/ |
//  |_|                                        |___/ 
//                                                   

// Figure out a few things about the incoming command:
// 1) Workspace (Team ID)
// 2) Channel
// 3) User
// 4) Command
$command = htmlspecialchars_decode($_POST["text"]);

// Strip multiple whitespace, then tokenize
$command = rtrim(preg_replace('/\s+/', ' ', $command));
$tokens = explode(" ", $command);

// Verify that this request actually came from Slack
if (!validate_slack_request()) {
    exit;
}
//else if (contains($command, "UFQSPSL4F")) {
//    add_response_section_fancy_text("Nah, that game was bullshit.");
//}
// Sometimes Slack will call us up to see if our SSL cert is still valid.
// https://images-na.ssl-images-amazon.com/images/I/51p67GdR7EL._SY355_.jpg
else if (array_key_exists("ssl_check", $_POST)) {
    set_notification_text("Hi Slack! Our SSL certificate is just fine, thanks.");
}
// Singles match(es)
else if (sizeof($tokens) >= 3 &&
    looks_like_user_id($tokens[0]) &&
    in_array(strtolower($tokens[1]), $win_thesaurus) &&
    looks_like_user_id($tokens[2])) {

    // Calculate who the players are.
    $uid_a = mention_to_user_id($tokens[0]);
    $uid_b = mention_to_user_id($tokens[2]);

    if ($uid_a == $uid_b) {
        respond_private("Okay buddy, sure. 🙄");
    }
    else {
        // This is an array of alternating win/loss streaks, and the streak lengths.
        // Example: array(1, 3, 2, 3) represents:
        // Player A beating Player B, then
        // Player B beating Player A three times, then
        // Player A beating Player B twice, then
        // Player B beating Player A three times.
        $wins_losses = array(1);

        // Process multiple wins and losses, if requested
        if (sizeof($tokens) >= 4 && contains($tokens[3], "-")) {
            $wins_losses = explode_scores($tokens[3]);
        }

        if (sizeof($wins_losses)) {
            $player_a = init_user($uid_a);
            $player_b = init_user($uid_b);
            $player_a = update_user($player_a);
            $player_b = update_user($player_b);

            $result = singles_match($player_a, $player_b, $wins_losses);
            set_notification_text($result);
            add_response_section_fancy_text($result);
            command_users(array($player_a, $player_b));
        }
        else {
            respond_private("I couldn't understand your score report. :dizzy_face:");
        }
    }
}// Doubles match(es)
else if (sizeof($tokens) >= 7 &&
    looks_like_user_id($tokens[0]) &&
    strtolower($tokens[1]) === "and" &&
    looks_like_user_id($tokens[2]) &&
    in_array(strtolower($tokens[3]), $win_thesaurus) &&
    looks_like_user_id($tokens[4]) &&
    strtolower($tokens[5]) === "and" &&
    looks_like_user_id($tokens[6])) {

    // Calculate who the players are.
    $uid_a = mention_to_user_id($tokens[0]);
    $uid_b = mention_to_user_id($tokens[2]);
    $uid_c = mention_to_user_id($tokens[4]);
    $uid_d = mention_to_user_id($tokens[6]);

    if ($uid_a == $uid_b ||
        $uid_a == $uid_c ||
        $uid_a == $uid_d ||
        $uid_b == $uid_c ||
        $uid_b == $uid_d ||
        $uid_c == $uid_d) {
        respond_private("Uh, what kind of teams are those? :no_good:");
    }
    else {
        // This is an array of alternating win/loss streaks, and the streak lengths.
        // Example: array(1, 3, 2, 3) represents:
        // Player A beating Player B, then
        // Player B beating Player A three times, then
        // Player A beating Player B twice, then
        // Player B beating Player A three times.
        $wins_losses = array(1);

        // Process multiple wins and losses, if requested
        if (sizeof($tokens) >= 8 && contains($tokens[7], "-")) {
            $wins_losses = explode_scores($tokens[7]);
        }

        if (sizeof($wins_losses)) {
            $player_a = init_user($uid_a);
            $player_b = init_user($uid_b);
            $player_c = init_user($uid_c);
            $player_d = init_user($uid_d);
            $player_a = update_user($player_a);
            $player_b = update_user($player_b);
            $player_c = update_user($player_c);
            $player_d = update_user($player_d);

            $result = doubles_match($player_a, $player_b, $player_c, $player_d, $wins_losses);
            set_notification_text($result);
            add_response_section_fancy_text($result);
            command_users(array($player_a, $player_b, $player_c, $player_d));
        }
        else {
            respond_private("I couldn't understand your score report. :dizzy_face:");
        }
    }
}
// Match preview (Style C)
else if (sizeof($tokens) >= 4 &&
    looks_like_user_id($tokens[0]) &&
    starts_with(strtolower($tokens[1]), "vs") &&
    looks_like_user_id($tokens[2])) {

    $wins_losses = explode_scores($tokens[3]);

    if (sizeof($wins_losses)) {
        command_preview($tokens[0], $tokens[2], $wins_losses);
    }
    else {
        respond_private("I couldn't understand your score report. :dizzy_face:");
    }
}
// Match preview (Style A)
else if (sizeof($tokens) >= 3 &&
    looks_like_user_id($tokens[0]) &&
    starts_with(strtolower($tokens[1]), "vs") &&
    looks_like_user_id($tokens[2])) {

    command_preview($tokens[0], $tokens[2], NULL);
}
// Match preview (Style B)
else if (sizeof($tokens) >= 4 &&
    strtolower($tokens[0]) === "preview" &&
    looks_like_user_id($tokens[1]) &&
    starts_with(strtolower($tokens[2]), "vs") &&
    looks_like_user_id($tokens[3])) {

    command_preview($tokens[1], $tokens[3], NULL);
}
// Command of the form "@player"
else if (sizeof($tokens) == 1 && looks_like_user_id($tokens[0])) {
    // Get the player info.
    $uid = mention_to_user_id($tokens[0]);
    $user = init_user($uid);
    $user = update_user($user);

    set_notification_text(escaped_name($user) . " summary:");
    command_users(array($user));
    /*
    $text = escaped_name($user) . "\r\n";
    $text .= $user["elo"] . "\r\n";
    $text .= ($user["wins"] + $user["dwins"]) . "-";
    $text .= ($user["losses"] + $user["dlosses"]) . " overall\r\n";
    $text .= $user["wins"] . "-" . $user["losses"] . " singles\r\n";
    $text .= $user["dwins"] . "-" . $user["dlosses"] . " doubles\r\n";

    set_notification_text($text);
    */
}
// Whole ladder
else if (sizeof($tokens) >= 1 &&
    strtolower($tokens[0]) === "all") {
    set_notification_text("You were listed by " . $command_name);
    command_top($ladder_print_len_max + 1);
}// Top ranked players
else if (sizeof($tokens) >= 1 &&
    strtolower($tokens[0]) === "top") {

    $n = $ladder_print_len_default;
    if (sizeof($tokens) >= 2) {
        $n = $tokens[1];
    }

    set_notification_text("You were listed by " . $command_name);
    command_top($n);
}// Bottom ranked players
else if (sizeof($tokens) >= 1 &&
    strtolower($tokens[0]) === "bottom") {

    $n = $ladder_print_len_default;
    if (sizeof($tokens) >= 2) {
        $n = max(1, min($tokens[1], $ladder_print_len_max));

        if ($n != $tokens[1]) {
            $context = "Output limited to " . $n;
            if ($n == 1) {
                $context .= " player";
            }
            else {
                $context .= " players";
            }
            add_response_section_context($context);
        }
    }
    set_notification_text("You were listed by " . $command_name);
    command_bottom($n);
}
// Update/show custom emoji
else if (sizeof($tokens) >= 1 &&
    strtolower($tokens[0]) === "emoji") {
    retrieve_slack_emoji();
    sort($emoji);

    $text = "Custom emoji: ";
    foreach($emoji as $key => $value) {
        $text .= ":" . $value . ": ";
    }
    add_response_section_fancy_text($text);
    respond_private();
}
// Test API
else if (sizeof($tokens) >= 1 &&
    strtolower($tokens[0]) === "apitest") {
    $username = retrieve_slack_username($user_id);

    add_response_section_fancy_text("hi!");
    
    // use key 'http' even if you send the request to https://...
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/json\r\n",
            'method'  => "POST",
            'content' => json_encode($rsp)
        )
    );
    $context  = stream_context_create($options);
    $result = file_get_contents($response_url, false, $context);

    add_response_section_fancy_text("hi there!");

    // use key 'http' even if you send the request to https://...
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/json\r\n",
            'method'  => "POST",
            'content' => json_encode($rsp)
        )
    );
    $context  = stream_context_create($options);
    $result = file_get_contents($response_url, false, $context);

    // Bail out early
    //cleanup();
    //return;
}
// Blank command or bad syntax, print usage
else {
    $match_count = count_matches();
    $player_count = count_players();
    $context = $bot_name . " v" . $version . ", beep boop\r\n";
    $context .= $player_count . " players, " . $match_count . " matches";

    $text .= "Usage:\r\n";
    $text .= "`" . $command_name . " [command]`\r\n";

    add_response_section_context($context);
    add_response_section_fancy_text($text);
    add_response_section_divider();

    $text = "A few commands you can try:\r\n";
    $text .= "• top\r\n";
    $text .= "• bottom\r\n";
    $text .= "• *@player*\r\n";
    $text .= "• *@player_a* vs *@player_b*\r\n";
    $text .= "• *@player_a* beat *@player_b*\r\n";
    $text .= "• *@player_a* and *@player_b* beat *@player_c* and *@player_d*\r\n";
    $text .= "\r\n";
    $text .= "You may use 'I' and 'me' instead of your own *@mention*.";
    
    add_response_section_fancy_text($text . "\r\n" . $current_time . "\r\n" . $request_time);
    add_response_section_divider();
    respond_private();
}
echo json_encode($rsp);
cleanup();
?>
