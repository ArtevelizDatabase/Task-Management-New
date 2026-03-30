-- Recommended indexes from `audit.md`
-- Apply carefully in your target DB/environment (test on staging first).

-- EAV: UNIQUE (task_id, field_id) is applied by migration `2026_03_28_100000_task_values_unique_task_field.php` (dedupe + uq_task_values_task_field).
-- Skip adding a redundant non-unique index on the same columns if that migration has run.

-- EAV lookups (optional if no UNIQUE migration yet)
-- ALTER TABLE `tb_task_values`
--   ADD INDEX `idx_task_values_task_field` (`task_id`, `field_id`);

ALTER TABLE `tb_task_values`
  ADD INDEX `idx_task_values_field_value` (`field_id`, `value`(191));

-- Submission existence checks (setor)
ALTER TABLE `tb_submissions`
  ADD UNIQUE KEY `uniq_submissions_task` (`task_id`);

-- Common scoping
ALTER TABLE `tb_task`
  ADD INDEX `idx_task_user_deleted` (`user_id`, `deleted_at`);

ALTER TABLE `tb_task`
  ADD INDEX `idx_task_account_deleted` (`account_id`, `deleted_at`);

