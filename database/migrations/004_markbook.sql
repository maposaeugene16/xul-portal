USE mavambo_school;

ALTER TABLE assessment_marks
  ADD COLUMN mark_status ENUM('Scored','Absent','Exempt') NOT NULL DEFAULT 'Scored' AFTER mark;
