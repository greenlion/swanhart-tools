DROP EVENT IF EXISTS flexviews.compute_views;

CREATE EVENT IF NOT EXISTS flexviews.compute_views
ON SCHEDULE EVERY '5' MINUTE
DO CALL flexviews.refresh_all('COMPUTE',flexviews.uow_from_dtime(now()));
