<?php
session_start();
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '.env');
$dotenv->load();

$client_id = $_ENV['gauthkey'];
$redirect_uri = 'https://coral-newt-292863.hostingersite.com/callback.php';
$scope = urlencode('https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email');

$auth_url = "https://accounts.google.com/o/oauth2/v2/auth?"
          . "response_type=code"
          . "&client_id=$client_id"
          . "&redirect_uri=$redirect_uri"
          . "&scope=$scope"
          . "&access_type=offline"
          . "&prompt=consent";

header('Location: ' . $auth_url);
exit;