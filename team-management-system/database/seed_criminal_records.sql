-- ============================================================
-- اسکریپت seed برای تست نوتیف تمدید گواهی سوء پیشینه
-- ============================================================
-- این اسکریپت:
-- ۱) ستون criminal_record_issue_date را اضافه می‌کند (اگر نباشد)
-- ۲) برای دو نفر اول از افراد فعال، تاریخ صدور نمونه ثبت می‌کند
-- ============================================================

-- ۱) اضافه کردن ستون (اگر از قبل نباشد)
ALTER TABLE `personnel`
  ADD COLUMN IF NOT EXISTS `criminal_record_issue_date` DATE DEFAULT NULL AFTER `languages`;

ALTER TABLE `personnel_history`
  ADD COLUMN IF NOT EXISTS `criminal_record_issue_date` DATE DEFAULT NULL;

-- ۲) مشاهده دو نفر اول از افراد فعال (برای بررسی قبل از اجرای UPDATE)
SELECT id, first_name, last_name, national_id, category_type_id
FROM personnel
WHERE personnel_status <> 'dismissed'
ORDER BY id
LIMIT 5;

-- ۳) ثبت تاریخ صدور نمونه برای دو نفر اول
-- نفر اول: گواهی ۸ ماه پیش (منقضی شده)
-- نفر دوم: گواهی ۵ ماه و ۱۵ روز پیش (۱۵ روز تا انقضا)
UPDATE `personnel`
SET `criminal_record_issue_date` = DATE_SUB(CURDATE(), INTERVAL 8 MONTH)
WHERE id = (
    SELECT id FROM (
        SELECT id FROM personnel
        WHERE personnel_status <> 'dismissed'
        ORDER BY id LIMIT 1
    ) AS tmp
);

UPDATE `personnel`
SET `criminal_record_issue_date` = DATE_SUB(CURDATE(), INTERVAL 5 MONTH) - INTERVAL 15 DAY
WHERE id = (
    SELECT id FROM (
        SELECT id FROM personnel
        WHERE personnel_status <> 'dismissed'
        ORDER BY id LIMIT 1 OFFSET 1
    ) AS tmp
);

-- ۴) نمایش نتیجه برای تأیید
SELECT
    p.id,
    CONCAT(p.first_name, ' ', p.last_name) AS full_name,
    p.national_id,
    ct.category_name AS unit,
    p.criminal_record_issue_date AS issue_date,
    DATE_ADD(p.criminal_record_issue_date, INTERVAL 6 MONTH) AS expiry_date,
    DATEDIFF(DATE_ADD(p.criminal_record_issue_date, INTERVAL 6 MONTH), CURDATE()) AS days_left,
    CASE
        WHEN DATEDIFF(DATE_ADD(p.criminal_record_issue_date, INTERVAL 6 MONTH), CURDATE()) < 0 THEN 'expired'
        WHEN DATEDIFF(DATE_ADD(p.criminal_record_issue_date, INTERVAL 6 MONTH), CURDATE()) <= 30 THEN 'warning'
        ELSE 'ok'
    END AS status
FROM personnel p
JOIN category_types ct ON ct.id = p.category_type_id
WHERE p.criminal_record_issue_date IS NOT NULL
ORDER BY days_left ASC;
