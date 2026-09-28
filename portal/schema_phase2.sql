-- =====================================================================
-- 2단계(직원 관리자) 마이그레이션 — schema.sql 적용 후 한 번 실행한다.
-- 기존 데이터를 건드리지 않는 추가 전용(ADD/CREATE IF NOT EXISTS)이라
-- 여러 번 실행해도 안전하다 (MariaDB 10.2+).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 설정 키-값 — 기본 정보 관리(상호·대표자·사업자번호·연락처·주소 등).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS setting (
  name       VARCHAR(60) NOT NULL,
  value      TEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 진행 8단계 완료 표시 — 기존 엑셀은 날짜 없이 「완료」만 적는 칸이 많다.
-- 완료 판정은 (날짜 있음 OR done=1). 날짜는 있으면 함께 보여준다.
-- ---------------------------------------------------------------------
ALTER TABLE progress
  ADD COLUMN IF NOT EXISTS step1_done TINYINT(1) NOT NULL DEFAULT 0 AFTER step8_date,
  ADD COLUMN IF NOT EXISTS step2_done TINYINT(1) NOT NULL DEFAULT 0 AFTER step1_done,
  ADD COLUMN IF NOT EXISTS step3_done TINYINT(1) NOT NULL DEFAULT 0 AFTER step2_done,
  ADD COLUMN IF NOT EXISTS step4_done TINYINT(1) NOT NULL DEFAULT 0 AFTER step3_done,
  ADD COLUMN IF NOT EXISTS step5_done TINYINT(1) NOT NULL DEFAULT 0 AFTER step4_done,
  ADD COLUMN IF NOT EXISTS step6_done TINYINT(1) NOT NULL DEFAULT 0 AFTER step5_done,
  ADD COLUMN IF NOT EXISTS step7_done TINYINT(1) NOT NULL DEFAULT 0 AFTER step6_done,
  ADD COLUMN IF NOT EXISTS step8_done TINYINT(1) NOT NULL DEFAULT 0 AFTER step7_done;

-- 이미 날짜가 든 행은 done 도 1 로 맞춰 준다 (판정은 OR 라 없어도 되지만
-- 목록 집계 쿼리를 단순하게 하려고 정합을 맞춘다).
UPDATE progress SET step1_done = 1 WHERE step1_date IS NOT NULL AND step1_done = 0;
UPDATE progress SET step2_done = 1 WHERE step2_date IS NOT NULL AND step2_done = 0;
UPDATE progress SET step3_done = 1 WHERE step3_date IS NOT NULL AND step3_done = 0;
UPDATE progress SET step4_done = 1 WHERE step4_date IS NOT NULL AND step4_done = 0;
UPDATE progress SET step5_done = 1 WHERE step5_date IS NOT NULL AND step5_done = 0;
UPDATE progress SET step6_done = 1 WHERE step6_date IS NOT NULL AND step6_done = 0;
UPDATE progress SET step7_done = 1 WHERE step7_date IS NOT NULL AND step7_done = 0;
UPDATE progress SET step8_done = 1 WHERE step8_date IS NOT NULL AND step8_done = 0;

-- ---------------------------------------------------------------------
-- 입금일 — 상세 ④입금확인과 엑셀 34열(입금일)의 저장처.
-- ---------------------------------------------------------------------
ALTER TABLE cost
  ADD COLUMN IF NOT EXISTS paid_date DATE NULL AFTER total;
