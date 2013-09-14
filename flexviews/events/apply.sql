DROP EVENT IF EXISTS flexviews.apply_views;

CREATE EVENT IF NOT EXISTS flexviews.apply_views
ON SCHEDULE EVERY '10' MINUTE
DO CALL flexviews.refresh_all('APPLY',flexviews.uow_from_dtime(now()));
