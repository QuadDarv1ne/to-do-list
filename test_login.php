<?php
// test_login.php

require_once 'vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

// Create a mock session
$session = new Session(new MockArraySessionStorage());
$session->start();

// Create a mock request
$request = Request::create('/login', 'POST', [
    'email' => 'admin@example.com',
    'password' => 'test123',
    '_csrf_token' => 'test_token'
]);
$request->setSession($session);

echo "Request created successfully\n";
echo "Email: " . $request->request->get('email') . "\n";
echo "Password: " . $request->request->get('password') . "\n";