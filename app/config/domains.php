<?php
// CORS
$allowedOrigins = [
  'http://localhost:8090',
  'https://standupcomedy.github.io',
];

$allowedServices = [
  'demo',
  'standup',
];

$serviceRedirectProduction = [
  'standup' => 'https://standupcomedy.github.io/',
];

$serviceRedirectLocal = [
  'standup' => 'http://localhost:8090/',
];
