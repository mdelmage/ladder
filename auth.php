<?php
error_reporting(E_ALL);

$rsp = ["response_type" => "in_channel", "text" => ""];
$code = $_GET["code"];

// fill in your URLs here
$redirect = "https://shittykeming.com/elo/auth.php";
$oath_url = "https://slack.com/api/oauth.access";

// fill in your client/secret pair here
$client = "";
$secret = "";

$data = array("client_id" => $client,
    "client_secret" => $secret,
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
$result = file_get_contents($oath_url, false, $context);

if ($result === FALSE) {
    echo "Uh oh! Something went wrong.";
}
else {
    $rsp = json_decode($result, true);
    echo "Welcome to the ladder, " . $rsp["team_name"] . "!";
}
?>
