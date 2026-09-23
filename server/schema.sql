-- 등기 진행 조회 — 표 구조
--
-- 원칙 하나. 이 데이터베이스에는 세대주의 이름·생년월일·연락처·주소·계좌를 넣지 않는다.
-- 조회는 "입력한 값이 맞는지" 확인만 하면 되므로 단방향 해시로 충분하다.
-- 서버가 뚫려도 원본은 나오지 않는다.
--
-- 가비아 phpMyAdmin 에서 이 파일을 그대로 실행하면 된다.

SET NAMES utf8mb4;

-- 단지 --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS complexes (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(120) NOT NULL,
  is_open    TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0이면 조회를 닫는다. 등기가 모두 끝난 단지에 쓴다',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 세대 --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS households (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  complex_id  INT UNSIGNED NOT NULL,
  dong        VARCHAR(10) NOT NULL,
  ho          VARCHAR(10) NOT NULL,
  step        VARCHAR(40) NOT NULL DEFAULT '접수 전',
  step_at     DATE DEFAULT NULL,
  memo        VARCHAR(500) NOT NULL DEFAULT '' COMMENT '고객에게 그대로 보이는 문장. 내부 메모를 적지 말 것',

  cost_total  BIGINT NOT NULL DEFAULT 0,
  cost_paid   BIGINT NOT NULL DEFAULT 0,
  cost_diff   BIGINT NOT NULL DEFAULT 0 COMMENT '양수면 돌려드릴 금액, 음수면 더 받을 금액',
  paid_at     DATE DEFAULT NULL,
  cost_items  TEXT COMMENT '등기비용 명세 [{k:"취득세",v:0},...] JSON. 합계만 주면 항목을 묻는 전화가 온다',

  has_lack    TINYINT(1) NOT NULL DEFAULT 0 COMMENT '미비서류가 있는지만. 서류 이름은 memo 로 안내한다',
  cert_sent   TINYINT(1) NOT NULL DEFAULT 0,
  has_poa     TINYINT(1) NOT NULL DEFAULT 0,

  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_unit (complex_id, dong, ho),
  CONSTRAINT fk_hh_complex FOREIGN KEY (complex_id) REFERENCES complexes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 본인확인 열쇠 --------------------------------------------------------
-- 공동명의는 두 사람 모두 조회할 수 있어야 한다. 그래서 세대 하나에 열쇠가 여럿이다.
-- vhash = SHA-256( verify_salt | norm(이름) | 생년월일6자리 )
--   norm = NFC 로 모으고, 공백을 모두 빼고, 영문은 대문자로
-- 직원 화면의 「서버 업로드본」이 같은 방식으로 만든다.
CREATE TABLE IF NOT EXISTS household_keys (
  household_id BIGINT UNSIGNED NOT NULL,
  vhash        CHAR(64) NOT NULL,
  PRIMARY KEY (household_id, vhash),
  KEY idx_vhash (vhash),
  CONSTRAINT fk_key_hh FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 조회 시도 기록 ------------------------------------------------------
-- 이름과 생년월일은 찍어서 맞힐 수 있는 조합이다.
-- 같은 곳에서 계속 두드리면 막는다. 옛 시스템에는 이 장치가 없었다.
-- 여러 곳에서 나눠 두드려도 막히게 세대(단지·동·호)별 실패도 센다.
-- 30일 지난 기록은 lookup.php 가 가끔 지운다.
CREATE TABLE IF NOT EXISTS lookup_log (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_hash    CHAR(64) NOT NULL COMMENT 'IP 도 개인정보다. 해시로만 남긴다',
  complex_id INT UNSIGNED DEFAULT NULL,
  dong       VARCHAR(10) NOT NULL DEFAULT '',
  ho         VARCHAR(10) NOT NULL DEFAULT '',
  ok         TINYINT(1) NOT NULL,
  at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ip_at (ip_hash, at),
  KEY idx_cx_at (complex_id, at),
  KEY idx_unit_at (complex_id, dong, ho, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 이 파일을 예전 판으로 이미 실행했다면 위 CREATE 는 건너뛰어진다. 아래 세 줄을 한 번만 실행한다.
-- ALTER TABLE lookup_log ADD COLUMN dong VARCHAR(10) NOT NULL DEFAULT '' AFTER complex_id;
-- ALTER TABLE lookup_log ADD COLUMN ho   VARCHAR(10) NOT NULL DEFAULT '' AFTER dong;
-- ALTER TABLE lookup_log ADD KEY idx_unit_at (complex_id, dong, ho, at);

-- 업로드 이력 --------------------------------------------------------
CREATE TABLE IF NOT EXISTS upload_log (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  complex_id INT UNSIGNED NOT NULL,
  n_insert   INT UNSIGNED NOT NULL DEFAULT 0,
  n_update   INT UNSIGNED NOT NULL DEFAULT 0,
  at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_at (at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
