-- =====================================================================
-- 개발용 시드 — 전부 가짜 데이터. 운영 DB 에 절대 넣지 않는다.
-- 직원 비밀번호는 tools/make_staff.php 로 따로 만든다(여기 없음).
-- 주소·계좌 암호화 필드는 키가 설정마다 다르므로 시드에 넣지 않는다
-- (화면에서 직접 등록해 시험한다).
-- =====================================================================

SET NAMES utf8mb4;

-- 단지
INSERT INTO complex (id, name, exposed, bank_name, bank_account, bank_holder, sort)
VALUES (1, '테스트 스타힐스', 1, '국민은행', '000000-00-000000', '법무법인 제이엘', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 세대 101동 101호 — 단독 명의, 전 단계 완료, 비용 전체 + 미입금 잔액(차액 음수)
INSERT INTO household (id, complex_id, dong, ho) VALUES (1, 1, '101', '101')
ON DUPLICATE KEY UPDATE complex_id = VALUES(complex_id);

INSERT INTO owner (household_id, name, birth6, phone, is_primary)
SELECT 1, '홍길동', '900101', '010-0000-0000', 1
WHERE NOT EXISTS (SELECT 1 FROM owner WHERE household_id = 1 AND name = '홍길동');

INSERT INTO progress (household_id,
  step1_date, step2_date, step3_date, step4_date,
  step5_date, step6_date, step7_date, step8_date, missing_docs)
VALUES (1,
  '2026-07-01', '2026-07-10', '2026-07-15', '2026-07-20',
  '2026-08-01', '2026-08-10', '2026-09-05', '2026-09-20', NULL)
ON DUPLICATE KEY UPDATE step8_date = VALUES(step8_date);

INSERT INTO cost (household_id,
  acq_tax, bond_transfer, bond_mortgage, stamp_tax, cert_stamp, via_stamp,
  trust_cancel, cert_fees, fee, vat, etc_amt, total, paid_amount, diff)
VALUES (1,
  4200000, 380000, 120000, 150000, 15000, 5000,
  30000, 20000, 550000, 55000, 10000, 5535000, 5000000, -535000)
ON DUPLICATE KEY UPDATE total = VALUES(total), paid_amount = VALUES(paid_amount), diff = VALUES(diff);

-- 세대 101동 102호 — 공동명의(김철수+이영희), 중간 단계, 미비서류 있음
INSERT INTO household (id, complex_id, dong, ho) VALUES (2, 1, '101', '102')
ON DUPLICATE KEY UPDATE complex_id = VALUES(complex_id);

INSERT INTO owner (household_id, name, birth6, phone, is_primary)
SELECT 2, '김철수', '850315', '010-0000-0001', 1
WHERE NOT EXISTS (SELECT 1 FROM owner WHERE household_id = 2 AND name = '김철수');
INSERT INTO owner (household_id, name, birth6, phone, is_primary)
SELECT 2, '이영희', '870722', '010-0000-0002', 0
WHERE NOT EXISTS (SELECT 1 FROM owner WHERE household_id = 2 AND name = '이영희');

INSERT INTO progress (household_id,
  step1_date, step2_date, step3_date, missing_docs)
VALUES (2, '2026-08-15', '2026-08-28', '2026-09-10', '초본 1통')
ON DUPLICATE KEY UPDATE missing_docs = VALUES(missing_docs);

INSERT INTO cost (household_id,
  acq_tax, bond_transfer, fee, vat, total, paid_amount, diff)
VALUES (2, 3800000, 340000, 550000, 55000, 4745000, 4745000, 0)
ON DUPLICATE KEY UPDATE total = VALUES(total), paid_amount = VALUES(paid_amount), diff = VALUES(diff);

-- FAQ 예시 (0건이어도 화면은 동작하지만, 아코디언 확인용으로 넣는다)
INSERT INTO faq (category, question, answer, sort, visible)
SELECT '등기', '권리증은 언제 받게 되나요?',
       '등기소 접수 후 통상 50일 이상 걸립니다. 완료되면 등록하신 주소로 보내드립니다.', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM faq WHERE question = '권리증은 언제 받게 되나요?');
INSERT INTO faq (category, question, answer, sort, visible)
SELECT '채권환불', '채권 환불은 언제 되나요?',
       '등기 완료 후 환불 계좌로 순차 입금됩니다. 계좌를 먼저 등록해 주십시오.', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM faq WHERE question = '채권 환불은 언제 되나요?');
