<?php
// CORS
$allowedOrigins = [
  'http://localhost:8090',
  'https://standupspot.com',
];

$allowedServices = [
  'demo',
  'standup',
];

$serviceRedirectProduction = [
  'standup' => 'https://standupspot.com/',
];

$serviceRedirectLocal = [
  'standup' => 'http://localhost:8090/',
];
