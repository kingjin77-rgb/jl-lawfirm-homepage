-- =====================================================================
-- 3단계(설문·등기접수·위임장 엔진) 마이그레이션
-- schema.sql + schema_phase2.sql 적용 후 실행한다.
-- 추가 전용(CREATE/ADD IF NOT EXISTS)이라 여러 번 실행해도 안전하다.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 설문 본체 — 세 메뉴(설문/등기접수/위임장)가 type 컬럼 하나로 같은
-- 엔진을 쓴다. 기간(starts_on/ends_on)과 핸드폰 확인은 등기접수용이지만
-- 컬럼은 공용으로 둔다(설문·위임장은 NULL/0).
--   type: survey=설문 · accept=등기접수 · attorney=위임장
-- 배너이미지는 파일 업로드 대신 자리만(banner_note) 둔다.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS survey (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  type           ENUM('survey','accept','attorney') NOT NULL DEFAULT 'survey',
  title          VARCHAR(200) NOT NULL,
  organizer      VARCHAR(100) NOT NULL DEFAULT '',   -- 주최
  description    TEXT NULL,                          -- 추가설명 (손님 폼 상단)
  banner_note    VARCHAR(300) NOT NULL DEFAULT '',   -- 배너이미지 자리 (파일 업로드 생략)
  starts_on      DATE NULL,                          -- 등기접수: 시작일
  ends_on        DATE NULL,                          -- 등기접수: 종료일
  phone_required TINYINT(1) NOT NULL DEFAULT 0,      -- 등기접수: 핸드폰 확인 필수
  is_open        TINYINT(1) NOT NULL DEFAULT 1,      -- 진행/마감 (기간 없는 유형용)
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_survey_type (type, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 참여대상자 — 아파트(단지) 단위 선택. 대상 단지의 전 세대가 모수(총인원).
CREATE TABLE IF NOT EXISTS survey_target (
  survey_id  INT UNSIGNED NOT NULL,
  complex_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (survey_id, complex_id),
  CONSTRAINT fk_starget_survey  FOREIGN KEY (survey_id)  REFERENCES survey(id)  ON DELETE CASCADE,
  CONSTRAINT fk_starget_complex FOREIGN KEY (complex_id) REFERENCES complex(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 문항 — 객관식(choice, 보기 JSON 배열) / 주관식(text) / 설명글(note) /
-- 핸드폰(phone, 등기접수용). 순서는 sort.
CREATE TABLE IF NOT EXISTS survey_question (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  survey_id    INT UNSIGNED NOT NULL,
  qtype        ENUM('choice','text','note','phone') NOT NULL DEFAULT 'choice',
  title        VARCHAR(500) NOT NULL,
  options_json TEXT NULL,                            -- 객관식 보기 ["…","…"]
  required     TINYINT(1) NOT NULL DEFAULT 0,
  sort         INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_squestion (survey_id, sort, id),
  CONSTRAINT fk_squestion_survey FOREIGN KEY (survey_id) REFERENCES survey(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 응답 — 세대당 1건(중복 제출 방지, 다시 제출하면 수정).
-- phone 은 「핸드폰 확인 필수」(등기접수) 응답자의 연락처.
CREATE TABLE IF NOT EXISTS survey_response (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  survey_id    INT UNSIGNED NOT NULL,
  household_id INT UNSIGNED NOT NULL,
  owner_name   VARCHAR(50) NOT NULL DEFAULT '',      -- 제출한 명의인
  phone        VARCHAR(20) NOT NULL DEFAULT '',
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sresponse (survey_id, household_id),
  KEY ix_sresponse_household (household_id),
  CONSTRAINT fk_sresponse_survey    FOREIGN KEY (survey_id)    REFERENCES survey(id)    ON DELETE CASCADE,
  CONSTRAINT fk_sresponse_household FOREIGN KEY (household_id) REFERENCES household(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 답 — 문항당 1행. 객관식은 고른 보기 문구, 주관식/핸드폰은 입력값.
CREATE TABLE IF NOT EXISTS survey_answer (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  response_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  value       TEXT NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sanswer (response_id, question_id),
  KEY ix_sanswer_question (question_id),
  CONSTRAINT fk_sanswer_response FOREIGN KEY (response_id) REFERENCES survey_response(id) ON DELETE CASCADE,
  CONSTRAINT fk_sanswer_question FOREIGN KEY (question_id) REFERENCES survey_question(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 참여자 SMS — 기존 sms_log 를 그대로 쓰되, 어느 설문의 어떤 대상
-- (참여 done / 불참 pending / 전체 all)인지 표시하는 컬럼을 붙인다.
-- ---------------------------------------------------------------------
ALTER TABLE sms_log
  ADD COLUMN IF NOT EXISTS survey_id INT UNSIGNED NULL AFTER complex_id,
  ADD COLUMN IF NOT EXISTS audience  VARCHAR(10) NOT NULL DEFAULT '' AFTER survey_id;

-- ---------------------------------------------------------------------
-- 권리증 발송일 — 등기진행 엑셀 38열(발송일)의 저장처.
-- ---------------------------------------------------------------------
ALTER TABLE progress
  ADD COLUMN IF NOT EXISTS sent_date DATE NULL AFTER step8_done;

-- ---------------------------------------------------------------------
-- 운영 정보·팝업 — setting 키-값으로 저장한다 (schema_phase2 의 setting 표).
--   운영 정보: ops_hours(상담 가능 시간) · ops_lunch(점심시간) ·
--              ops_holiday(휴무 안내) · ops_notice(손님 홈 안내문)
--   팝업:      popup_enabled(0/1) · popup_title · popup_body ·
--              popup_start(YYYY-MM-DD) · popup_end(YYYY-MM-DD)
-- 표 추가 없음. 개발 시드는 seed.dev.sql 대신 관리자 화면에서 넣는다.
-- ---------------------------------------------------------------------
