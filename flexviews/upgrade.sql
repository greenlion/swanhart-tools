use flexviews;
-- This is the only MyISAM table in Flexviews.  It is
-- a table that keeps track of the last refresh step
-- for a view for monitoring purposes
CREATE TABLE IF NOT EXISTS flexviews.refresh_step_info(
  mview_id int(11) not null PRIMARY KEY,
  last_step varchar(255) not null,
  last_step_at TIMESTAMP NOT NULL
) DEFAULT CHARSET=UTF8
ENGINE=MYISAM;

\. install_procs.inc

