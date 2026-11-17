-- =====================================================================
-- 5단계(서명 문항 · 설문별 자동 파기) 마이그레이션
-- schema.sql + schema_phase2~4.sql 적용 후 실행한다.
-- 추가 전용(ADD IF NOT EXISTS / 같은 정의로 MODIFY / NULL 만 채우는 UPDATE)이라
-- 여러 번 실행해도 안전하다 (MariaDB 10.2+).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 서명 문항 — 손글씨 서명(canvas PNG)을 받는 유형. 세 메뉴 공통.
-- 서명 PNG 는 sodium secretbox 로 암호화한 base64 를 survey_answer.value 에
-- 넣는다(평문 PNG 는 DB·디스크 어디에도 남지 않는다). 200KB PNG 의 암호문
-- base64 는 TEXT(64KB)를 넘으므로 MEDIUMTEXT 로 넓힌다.
-- ---------------------------------------------------------------------
ALTER TABLE survey_question
  MODIFY COLUMN qtype ENUM('choice','text','note','phone','signature') NOT NULL DEFAULT 'choice';

ALTER TABLE survey_answer
  MODIFY COLUMN value MEDIUMTEXT NOT NULL;

-- ---------------------------------------------------------------------
-- 설문별 자동 파기
--   retention_until : 파기예정일. 이 날이 지나면(다음 날부터) 웹크론
--                     /cron/purge 가 응답·답·서명을 지운다.
--                     기본값 = 종료일 + 90일, 종료일이 없으면 작성일 + 1년.
--   purged_at       : 파기 실행 시각 (NULL = 아직 파기 전). 파기된 건은 참여 불가.
--   purged_summary  : 파기 직전 집계 수치(JSON) — 총인원·참여 수·객관식 보기별
--                     응답수·문항별 응답수. 개인 응답은 남기지 않는다.
-- ---------------------------------------------------------------------
ALTER TABLE survey
  ADD COLUMN IF NOT EXISTS retention_until DATE NULL DEFAULT NULL AFTER is_open,
  ADD COLUMN IF NOT EXISTS purged_at       DATETIME NULL DEFAULT NULL AFTER retention_until,
  ADD COLUMN IF NOT EXISTS purged_summary  MEDIUMTEXT NULL AFTER purged_at,
  ADD INDEX IF NOT EXISTS ix_survey_retention (purged_at, retention_until);

-- 기존 건에 기본 파기예정일을 채운다 (이미 값이 있으면 건드리지 않음).
-- 기본값이 이미 지났더라도 적용일 + 30일보다 앞당기지 않는다 — 적용 직후 첫 크론에
-- 예고 없이 지워지지 않고, 대시보드 30일 경고 카드로 먼저 알린 뒤 파기되게.
UPDATE survey
   SET retention_until = GREATEST(
         COALESCE(DATE_ADD(ends_on, INTERVAL 90 DAY), DATE_ADD(DATE(created_at), INTERVAL 1 YEAR)),
         DATE_ADD(CURDATE(), INTERVAL 30 DAY))
 WHERE retention_until IS NULL;
