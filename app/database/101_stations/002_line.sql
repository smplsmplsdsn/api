
CREATE TABLE IF NOT EXISTS m_line (
  line_cd INT NOT NULL DEFAULT 0,
  company_cd INT NOT NULL DEFAULT 0,
  line_name VARCHAR(256) NOT NULL DEFAULT '',
  line_name_k VARCHAR(256) DEFAULT NULL,
  line_name_h VARCHAR(256) DEFAULT NULL,
  line_color_c VARCHAR(8) DEFAULT NULL,
  line_color_t VARCHAR(32) DEFAULT NULL,
  line_type VARCHAR(10) DEFAULT NULL,
  lon DOUBLE DEFAULT 0,
  lat DOUBLE DEFAULT 0,
  zoom SMALLINT DEFAULT 0,
  e_status SMALLINT DEFAULT 0,
  e_sort INT DEFAULT 0,

  PRIMARY KEY (line_cd),
  KEY m_line_company_cd (company_cd),
  KEY m_line_e_sort (e_sort)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
