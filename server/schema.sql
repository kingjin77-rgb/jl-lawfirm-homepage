-- 등기 진행 조회 — 표 구조
--
-- 원칙 하나. 이 데이터베이스에는 세대주의 이름과 생년월일을 넣지 않는다.
-- 조회는 "입력한 값이 맞는지" 확인만 하면 되므로 단방향 해시로 충분하다.
-- 서버가 뚫려도 이름과 생년월일 원본은 나오지 않는다.
--
-- 가비아 phpMyAdmin 에서 이 파일을 그대로 실행하면 된다.

SET NAMES utf8mb4;

-- 단지 --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS complexes (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name      VARCHAR(120) NOT NULL,
  is_open   TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0이면 조회를 닫는다. 종료된 단지에 쓴다',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 세대 --------------------------------------------------------------
-- vhash = SHA-256( VERIFY_SALT | 이름 | 생년월일6자리 )
-- 관리자 화면의 "서버 업로드본 내보내기" 가 같은 방식으로 만들어 준다.
CREATE TABLE IF NOT EXISTS households (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  complex_id  INT UNSIGNED NOT NULL,
  dong        VARCHAR(10) NOT NULL,
  ho          VARCHAR(10) NOT NULL,
  vhash       CHAR(64) NOT NULL COMMENT '본인확인용 단방향 해시. 이름·생년월일 원본은 저장하지 않는다',
  step        VARCHAR(40) NOT NULL DEFAULT '접수',
  memo        VARCHAR(500) NOT NULL DEFAULT '' COMMENT '고객에게 그대로 보여주는 문장. 내부 메모를 적지 말 것',
  step_at     DATE DEFAULT NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_unit (complex_id, dong, ho),
  KEY idx_lookup (complex_id, dong, ho, vhash),
  CONSTRAINT fk_hh_complex FOREIGN KEY (complex_id) REFERENCES complexes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 조회 시도 기록 ------------------------------------------------------
-- 이름과 생년월일은 찍어서 맞힐 수 있는 조합이다.
-- 그래서 같은 곳에서 계속 두드리면 막는다. 옛 시스템에는 이 장치가 없었다.
CREATE TABLE IF NOT EXISTS lookup_log (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_hash    CHAR(64) NOT NULL COMMENT 'IP 도 개인정보다. 해시로만 남긴다',
  complex_id INT UNSIGNED DEFAULT NULL,
  ok         TINYINT(1) NOT NULL,
  at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ip_at (ip_hash, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
