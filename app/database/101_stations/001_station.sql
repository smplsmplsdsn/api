
CREATE TABLE IF NOT EXISTS m_station (
  station_cd INT NOT NULL DEFAULT 0,
  station_g_cd INT NOT NULL DEFAULT 0,
  station_name VARCHAR(256) NOT NULL DEFAULT '',
  station_name_k VARCHAR(256) DEFAULT NULL,
  station_name_r VARCHAR(256) DEFAULT NULL,
  line_cd INT NOT NULL DEFAULT 0,
  pref_cd SMALLINT DEFAULT 0,
  post VARCHAR(32) DEFAULT NULL,
  address VARCHAR(1024) DEFAULT NULL,
  lon DOUBLE DEFAULT 0,
  lat DOUBLE DEFAULT 0,
  open_ymd TEXT DEFAULT NULL,
  close_ymd TEXT DEFAULT NULL,
  e_status SMALLINT DEFAULT 0,
  e_sort INT DEFAULT 0,

  PRIMARY KEY (station_cd),
  KEY m_station_station_g_cd (station_g_cd),
  KEY m_station_line_cd (line_cd),
  KEY m_station_pref_cd (pref_cd),
  KEY m_station_e_sort (e_sort)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
