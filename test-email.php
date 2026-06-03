<?php
require_once __DIR__ . '/vendor/autoload.php';

$to_email = 'printzton88@gmail.com'; // Change to your email

$email = new \SendGrid\Mail\Mail();
$email->setFrom(getenv('SENDGRID_FROM_EMAIL'), getenv('SENDGRID_FROM_NAME'));
$email->setSubject('SendGrid Test — IS351');
$email->addTo($to_email);
$email->addContent('text/html', '<h1>SendGrid is working!</h1>');

$sendgrid = new \SendGrid(getenv('SENDGRID_API_KEY'));

try {
    $response = $sendgrid->send($email);
    echo 'Status: ' . $response->statusCode() . '<br>';
    echo 'Body: ' . $response->body() . '<br>';
    echo 'Headers: ' . print_r($response->headers(), true);
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}