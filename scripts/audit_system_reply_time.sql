-- ITFlow - how much billable time did system-generated replies invent?
--
-- Run these BEFORE deciding whether to correct historical data. Nothing here
-- writes; the UPDATE at the bottom is commented out on purpose.
--
-- Background: until this fix, replies ITFlow wrote itself booked time worked -
-- one minute for assignment / priority / merge / close / invoice / quote /
-- schedule / task reopen, and the task's full completion estimate for task
-- completion. That time is in ticket totals, technician and client time
-- reports, project totals and anything already invoiced.

-- 1. Total invented time, by reply kind.
SELECT
    CASE
        WHEN ticket_reply LIKE 'Completed Task - %'       THEN 'Task completed (estimate booked)'
        WHEN ticket_reply LIKE 'Undo Completed Task - %'  THEN 'Task reopened'
        WHEN ticket_reply LIKE 'Ticket closed.'           THEN 'Ticket closed'
        WHEN ticket_reply LIKE 'Created invoice %'        THEN 'Invoice created'
        WHEN ticket_reply LIKE 'Created quote %'          THEN 'Quote created'
        WHEN ticket_reply LIKE 'Ticket %merged into%'     THEN 'Merged'
        WHEN ticket_reply LIKE '%updated the priority from%' THEN 'Priority changed'
        ELSE 'Other'
    END AS reply_kind,
    COUNT(*) AS replies,
    SEC_TO_TIME(SUM(TIME_TO_SEC(ticket_reply_time_worked))) AS total_time
FROM ticket_replies
WHERE ticket_reply_type = 'Internal'
  AND ticket_reply_archived_at IS NULL
  AND TIME_TO_SEC(ticket_reply_time_worked) > 0
  AND (
        ticket_reply LIKE 'Completed Task - %'
     OR ticket_reply LIKE 'Undo Completed Task - %'
     OR ticket_reply = 'Ticket closed.'
     OR ticket_reply LIKE 'Created invoice %'
     OR ticket_reply LIKE 'Created quote %'
     OR ticket_reply LIKE 'Ticket %merged into%'
     OR ticket_reply LIKE '%updated the priority from%'
  )
GROUP BY reply_kind
ORDER BY SUM(TIME_TO_SEC(ticket_reply_time_worked)) DESC;

-- 2. The same rows per client, so you can see whose totals moved.
SELECT client_name,
       COUNT(*) AS replies,
       SEC_TO_TIME(SUM(TIME_TO_SEC(ticket_reply_time_worked))) AS total_time
FROM ticket_replies
LEFT JOIN tickets ON ticket_id = ticket_reply_ticket_id
LEFT JOIN clients ON client_id = ticket_client_id
WHERE ticket_reply_type = 'Internal'
  AND ticket_reply_archived_at IS NULL
  AND TIME_TO_SEC(ticket_reply_time_worked) > 0
  AND (ticket_reply LIKE 'Completed Task - %' OR ticket_reply LIKE 'Undo Completed Task - %')
GROUP BY client_name
ORDER BY SUM(TIME_TO_SEC(ticket_reply_time_worked)) DESC;

-- 3. Correction, if you want it. NOT shipped as a migration: these rows are
--    editable in the UI, so some of them may carry time a technician put there
--    deliberately, and anything already invoiced should not move underneath the
--    invoice. Review the output above first, back up, then run by hand.
--
-- UPDATE ticket_replies
-- SET ticket_reply_time_worked = '00:00:00'
-- WHERE ticket_reply_type = 'Internal'
--   AND (ticket_reply LIKE 'Completed Task - %' OR ticket_reply LIKE 'Undo Completed Task - %');
