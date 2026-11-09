# Stations DATABASE

駅・路線情報を管理するマスターデータベース。

駅データは駅・路線情報のマスターデータを使用する。

## Table List

| Table | Description |
|---|---|
| `m_station` | 駅情報 |
| `m_line` | 路線情報 |
| `m_station_join` | 駅同士の関連情報 |

---

## m_station

駅情報を管理する。

### Columns

| Column | Type | NULL | Default | Description |
|---|---|---|---|---|
| `station_cd` | INT | NO | `0` | 駅コード |
| `station_g_cd` | INT | NO | `0` | 駅グループコード |
| `station_name` | VARCHAR(256) | NO | `''` | 駅名 |
| `station_name_k` | VARCHAR(256) | YES | NULL | 駅名（カナ） |
| `station_name_r` | VARCHAR(256) | YES | NULL | 駅名（ローマ字） |
| `line_cd` | INT | NO | `0` | 路線コード |
| `pref_cd` | SMALLINT | YES | `0` | 都道府県コード |
| `post` | VARCHAR(32) | YES | NULL | 郵便番号 |
| `address` | VARCHAR(1024) | YES | NULL | 住所 |
| `lon` | DOUBLE | YES | `0` | 経度 |
| `lat` | DOUBLE | YES | `0` | 緯度 |
| `open_ymd` | TEXT | YES | NULL | 開業日 |
| `close_ymd` | TEXT | YES | NULL | 廃止日 |
| `e_status` | SMALLINT | YES | `0` | 駅状態 |
| `e_sort` | INT | YES | `0` | 表示順 |

### Constraints

- PRIMARY KEY：`station_cd`
- INDEX：`station_g_cd`（`m_station_station_g_cd`）
- INDEX：`line_cd`（`m_station_line_cd`）
- INDEX：`pref_cd`（`m_station_pref_cd`）
- INDEX：`e_sort`（`m_station_e_sort`）

### Station Group

`station_g_cd` は駅グループを識別するコード。

同じ駅グループコードを持つ駅データをグループとして扱う。

### Location

駅の位置情報を経度 `lon`、緯度 `lat` で管理する。

座標は距離検索などに使用する。

### Line Relationship

`line_cd` によって所属する路線を識別する。

路線情報は `m_line.line_cd` に対応する。

DB上の外部キー制約は設定されていない。

---

## m_line

路線情報を管理する。

### Columns

| Column | Type | NULL | Default | Description |
|---|---|---|---|---|
| `line_cd` | INT | NO | `0` | 路線コード |
| `company_cd` | INT | NO | `0` | 事業者コード |
| `line_name` | VARCHAR(256) | NO | `''` | 路線名 |
| `line_name_k` | VARCHAR(256) | YES | NULL | 路線名（カナ） |
| `line_name_h` | VARCHAR(256) | YES | NULL | 路線名（ひらがな） |
| `line_color_c` | VARCHAR(8) | YES | NULL | 路線カラー（カラーコード） |
| `line_color_t` | VARCHAR(32) | YES | NULL | 路線カラー名 |
| `line_type` | VARCHAR(10) | YES | NULL | 路線種別 |
| `lon` | DOUBLE | YES | `0` | 経度 |
| `lat` | DOUBLE | YES | `0` | 緯度 |
| `zoom` | SMALLINT | YES | `0` | 地図表示倍率 |
| `e_status` | SMALLINT | YES | `0` | 路線状態 |
| `e_sort` | INT | YES | `0` | 表示順 |

### Constraints

- PRIMARY KEY：`line_cd`
- INDEX：`company_cd`（`m_line_company_cd`）
- INDEX：`e_sort`（`m_line_e_sort`）

### Station Relationship

`m_station.line_cd` と `m_line.line_cd` によって駅と路線を関連付ける。

DB上の外部キー制約は設定されていない。

---

## m_station_join

駅同士の関連情報を管理する。

### Columns

| Column | Type | NULL | Default | Description |
|---|---|---|---|---|
| `line_cd` | INT | NO | `0` | 路線コード |
| `station_cd1` | INT | NO | `0` | 駅コード1 |
| `station_cd2` | INT | NO | `0` | 駅コード2 |

### Constraints

- PRIMARY KEY：`line_cd`, `station_cd1`, `station_cd2`

外部キー制約および追加インデックスは設定されていない。

---

## Table Relationships

```text
m_line
  │
  │ line_cd
  │
  └── m_station
         │
         │ station_cd
         │
         └── m_station_join
```

駅と路線の関連は `line_cd` によって管理する。

`m_station_join` は駅コードを使用して駅同士の関連情報を保持する。

各テーブル間の関連は論理的なものであり、SQL上の外部キー制約は設定されていない。

## Database Settings

各テーブルの設定は以下のとおり。

- ストレージエンジン：InnoDB
- 文字コード：utf8mb4
- 照合順序：utf8mb4_unicode_ci