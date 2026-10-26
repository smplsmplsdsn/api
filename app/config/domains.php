<?php
// CORS
$allowedOrigins = [
  'http://localhost:8090',
  'https://standupspot.com',
];

$allowedServices = [
  'demo',
  'standup',
  'stations',
];

$serviceRedirectProduction = [
  'standup' => 'https://standupspot.com/?view=mypage',
];

$serviceRedirectLocal = [
  'standup' => 'http://localhost:8090/?view=mypage',
];

$serviceNames = [
  'demo' => 'Demo',
  'standup' => 'Standup Spot',
  'stations' => 'Station',
];