
CREATE TABLE IF NOT EXISTS m_station_join (
  line_cd INT NOT NULL DEFAULT 0,
  station_cd1 INT NOT NULL DEFAULT 0,
  station_cd2 INT NOT NULL DEFAULT 0,

  PRIMARY KEY (line_cd, station_cd1, station_cd2)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
