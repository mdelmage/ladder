<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="add ladder app">    <title>Add ladder!</title>    
    <link rel="stylesheet" href="https://unpkg.com/purecss@1.0.1/build/pure-min.css" integrity="sha384-" crossorigin="anonymous">
    
    <!--[if lte IE 8]>
        <link rel="stylesheet" href="https://unpkg.com/purecss@1.0.1/build/grids-responsive-old-ie-min.css">
    <![endif]-->
    <!--[if gt IE 8]><!-->
        <link rel="stylesheet" href="https://unpkg.com/purecss@1.0.1/build/grids-responsive-min.css">
    <!--<![endif]-->
    
    
        <!--[if lte IE 8]>
            <link rel="stylesheet" href="https://shittykeming.com/css/layouts/blog-old-ie.css">
        <![endif]-->
        <!--[if gt IE 8]><!-->
            <link rel="stylesheet" href="https://shittykeming.com/css/layouts/blog.css">
        <!--<![endif]-->
<link rel="icon" 
      type="image/png" 
      href="https://shittykeming.com/img/ladder_icon.png">
</head>
<body>

<div id="layout" class="pure-g">
    <div class="sidebar pure-u-1 pure-u-md-1-4">
        <div class="header">
            <h1 class="brand-title">ladder: A Slack App</h1>
            <h2 class="brand-tagline">Victory. Defeat. Elo.</h2>
            <img width="128" height="128" alt="ladder logo" class="post-avatar" src="https://shittykeming.com/img/ladder.png">
        </div>
    </div>

    <div class="content pure-u-1 pure-u-md-3-4">
        <div>
            <!-- A wrapper for all the blog posts -->
            <div class="posts">
                <section class="post">
                    <header class="post-header">
                        <h3 class="post-title">
<?php
//error_reporting(E_ALL);

// Modfiy these to match your database credentials
$dbname = '';
$dbuser = '';
$dbpass = '';
$dbhost = 'localhost';

// fill in your client/secret pair here
$client_id = "";
$client_secret = "";


$rsp = ["response_type" => "in_channel", "text" => ""];
$code = $_GET["code"];
$redirect = "https://shittykeming.com/elo/auth.php";

$url = "https://slack.com/api/oauth.v2.access";
$data = array("client_id" => $client_id,
    "client_secret" => $client_secret,
    "code" => $code,
    "redirect_uri" => $redirect);

// use key 'http' even if you send the request to https://...
$options = array(
    'http' => array(
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data)
    )
);
$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);
$rsp = json_decode($result, true);

if ($result === FALSE || $rsp["ok"] != true) {
    echo "Uh oh! Something went wrong.";
}
else {
    echo "Welcome to the ladder, " . $rsp["team"]["name"] . "!";

    // Save our team-specific token, overwriting any previous copies
    $mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
    $team_id = $rsp["team"]["id"];
    $token = $rsp["access_token"];

    $sql = "SELECT 1 from " . $team_id . " LIMIT 1";
    $result = $mysqli->query($sql);
    if ($result->num_rows == 0) {
        $sql = "CREATE TABLE " . $team_id . "(`token` VARCHAR(256) NOT NULL, `emoji` VARCHAR(1024) NOT NULL, `team_name` VARCHAR(1024) NOT NULL, PRIMARY KEY (`token`))";
        $mysqli->query($sql);

        $sql = "INSERT INTO " . $team_id . "(`token`, `emoji`, `team_name`) VALUES ('" . $token . "', ' ', '" . $rsp["team_name"] . "');";
        $mysqli->query($sql);
    }
    else {
        $sql = "UPDATE " . $team_id . " SET token='" . $token . "' WHERE 1;";
        $mysqli->query($sql);
    }
}
?></h3>
                    </header>

                    <div class="post-description">
                        <p>
                        Join us on the ladder and see who's the best. <a href="https://shittykeming.com/elo">Let's get started!</a></p>
                    </div>
                </section>
            </div>

            <div class="footer">
                <div class="pure-menu pure-menu-horizontal">
                    <ul>
                        <li class="pure-menu-item"><a href="https://shittykeming.com/elo/about.html" class="pure-menu-link">About</a></li>
                        <li class="pure-menu-item"><a href="https://shittykeming.com/elo" class="pure-menu-link">Feed</a></li>
                        <li class="pure-menu-item"><a href="https://shittykeming.com/elo/privacy.html" class="pure-menu-link">Privacy Policy</a></li>
                        <li class="pure-menu-item"><a href="http://github.com/mdelmage/ladder/" class="pure-menu-link">GitHub</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>


</body>
</html>