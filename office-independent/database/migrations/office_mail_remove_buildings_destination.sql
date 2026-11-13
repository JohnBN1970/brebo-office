-- BREBO Office: one-time migration after backup; run once, not on every deployment.
START TRANSACTION;
INSERT INTO office_mail_board_moves (message_id,from_module,to_module,operator_id,created_at)
SELECT message_id,'gebouwen','nieuw',0,UTC_TIMESTAMP()
FROM office_mail_board_routes WHERE module_key='gebouwen';
UPDATE office_mail_board_routes
SET module_key='nieuw',changed_by_operator_id=NULL,updated_at=UTC_TIMESTAMP()
WHERE module_key='gebouwen';
COMMIT;
