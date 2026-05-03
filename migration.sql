-- ============================================================
-- RUN THIS SQL FIRST before uploading the PHP files
-- ============================================================

-- Step 1: Add requirements_configured column to course_requirements
ALTER TABLE course_requirements
ADD COLUMN requirements_configured TINYINT(1) NOT NULL DEFAULT 0
COMMENT '0 = admin assigned but signatory has not configured yet; 1 = signatory configured, visible to students';

-- Step 2: Fix existing data
-- Rows that already have document_type_id filled in = already configured by signatory → set to 1
UPDATE course_requirements
SET requirements_configured = 1
WHERE document_type_id IS NOT NULL AND TRIM(document_type_id) != '';

-- Rows with no document_type_id = admin-only slots not yet touched by signatory → stay 0
-- (no action needed, DEFAULT 0 already handles this)

-- ============================================================
-- VERIFY after running (optional check query):
-- SELECT c.course_name, u.full_name, cr.requirement_id,
--        cr.document_type_id, cr.requirements_configured
-- FROM course_requirements cr
-- JOIN courses c ON cr.course_id = c.id
-- JOIN users u ON cr.signatory_id = u.id
-- ORDER BY cr.requirements_configured DESC, c.course_name;
-- ============================================================
