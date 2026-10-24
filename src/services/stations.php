<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/domains.php';

function sendError(string $message, int $status = 400): void {

  http_response_code($status);

  echo json_encode([
    'success' => false,
    'error' => $message,
  ], JSON_UNESCAPED_UNICODE);

  exit;
}

// GETのみ対応
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  header('Allow: GET');
  sendError('許可されていないリクエストです。', 405);
}

// リファラー制限
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$refererOrigin = '';

if ($referer !== '') {
  $parsedUrl = parse_url($referer);

  if (isset($parsedUrl['scheme'], $parsedUrl['host'])) {
    $refererOrigin = $parsedUrl['scheme']
      . '://'
      . $parsedUrl['host'];

    if (isset($parsedUrl['port'])) {
      $refererOrigin .= ':' . $parsedUrl['port'];
    }
  }
}

if ($refererOrigin === '' || !in_array($refererOrigin, $allowedOrigins, true)) {
  sendError('アクセスが許可されていません。', 403);
}

// パラメータの存在と数値を確認
foreach (['lat', 'lng', 'distance'] as $key) {

  if (
    !isset($_GET[$key]) ||
    !is_scalar($_GET[$key]) ||
    !is_numeric($_GET[$key])
  ) {
    sendError("パラメータが不正です: {$key}", 400);
  }
}

$lat = (float) $_GET['lat'];
$lng = (float) $_GET['lng'];
$distance = (float) $_GET['distance'];

// パラメータの範囲を確認
if (
  !is_finite($lat) ||
  !is_finite($lng) ||
  !is_finite($distance) ||
  $lat < -90 ||
  $lat > 90 ||
  $lng < -180 ||
  $lng > 180 ||
  $distance <= 0 ||
  $distance > 20000000
) {
  sendError('パラメータの範囲が不正です。', 400);
}

// 検索範囲の矩形を計算
// 緯度1度あたり約111.32kmとして候補を絞り込む
$latDelta = $distance / 111320;

$minLat = max(-90, $lat - $latDelta);
$maxLat = min(90, $lat + $latDelta);

$cosLat = cos(deg2rad($lat));

$lngDelta = $cosLat > 0.000001
  ? min(180, $distance / (111320 * $cosLat))
  : 180;

$minLng = max(-180, $lng - $lngDelta);
$maxLng = min(180, $lng + $lngDelta);

try {

  $pdo = createStationsDatabaseConnection();

  $stmt = $pdo->prepare('
    WITH station_distances AS (
      SELECT
        s.station_cd,
        s.station_g_cd,
        s.station_name,
        s.lon,
        s.lat,
        ST_Distance_Sphere(
          POINT(s.lon, s.lat),
          POINT(:center_lng, :center_lat)
        ) AS distance

      FROM m_station AS s

      WHERE s.station_g_cd > 0
      AND s.lat BETWEEN :min_lat AND :max_lat
      AND s.lon BETWEEN :min_lng AND :max_lng
      AND s.lat BETWEEN -90 AND 90
      AND s.lon BETWEEN -180 AND 180
    ),

    ranked_stations AS (
      SELECT
        station_cd,
        station_g_cd,
        station_name,
        distance,

        ROW_NUMBER() OVER (
          PARTITION BY station_g_cd
          ORDER BY distance ASC, station_cd ASC
        ) AS row_num

      FROM station_distances

      WHERE distance <= :distance
    ),

    nearby_groups AS (
      SELECT
        station_g_cd,
        station_name,
        distance

      FROM ranked_stations

      WHERE row_num = 1
    )

    SELECT
      ng.station_g_cd,
      ng.station_name AS name,
      ROUND(ng.distance) AS distance,
      l.line_name

    FROM nearby_groups AS ng

    INNER JOIN m_station AS s
      ON s.station_g_cd = ng.station_g_cd

    INNER JOIN m_line AS l
      ON l.line_cd = s.line_cd

    ORDER BY
      ng.distance ASC,
      ng.station_g_cd ASC,
      l.line_name ASC
  ');

  $stmt->execute([
    'center_lng' => $lng,
    'center_lat' => $lat,
    'min_lat' => $minLat,
    'max_lat' => $maxLat,
    'min_lng' => $minLng,
    'max_lng' => $maxLng,
    'distance' => $distance,
  ]);

  $stations = [];

  while ($row = $stmt->fetch()) {

    $stationGroupCode = (int) $row['station_g_cd'];

    if (!isset($stations[$stationGroupCode])) {
      $stations[$stationGroupCode] = [
        'name' => $row['name'],
        'distance' => (int) $row['distance'],
        'lines' => [],
      ];
    }

    $lineName = $row['line_name'];

    if (!in_array(
      $lineName,
      $stations[$stationGroupCode]['lines'],
      true
    )) {
      $stations[$stationGroupCode]['lines'][] = $lineName;
    }
  }

  echo json_encode(
    array_values($stations),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
  );

} catch (Throwable $e) {

  error_log(
    'Stations API error: ' . $e->getMessage()
  );

  sendError('サーバー内部でエラーが発生しました。', 500);
}