/*  Flexviews for MySQL 
    Copyright 2008 Justin Swanhart

    FlexViews is free software: you can redistribute it and/or modify
    it under the terms of the Lesser GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    FlexViews is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with FlexViews in the file COPYING, and the Lesser extension to
    the GPL (the LGPL) in COPYING.LESSER.
    If not, see <http://www.gnu.org/licenses/>.
*/

USE flexviews;

\. ./procs/add_expr.sql
\. ./procs/add_table.sql
\. ./procs/create_mvlog.sql
\. ./procs/create_child_views.sql
\. ./procs/create.sql
\. ./procs/delta.sql
\. ./procs/disable.sql
\. ./procs/drop_mvlog.sql
\. ./procs/enable.sql
\. ./procs/get_from.sql
\. ./procs/get_grouping_list.sql
\. ./procs/get_id.sql
\. ./procs/get_keys.sql
\. ./procs/get_select.sql
\. ./procs/get_sql.sql
\. ./procs/get_trigger_body.sql
\. ./procs/get_where.sql
\. ./procs/is_enabled.sql
\. ./procs/mvlog_autoclean.sql
\. ./procs/refresh_complete.sql
\. ./procs/refresh.sql
\. ./procs/remove_expr.sql
\. ./procs/remove_table.sql
\. ./procs/rename.sql
\. ./procs/set_definition.sql
\. ./procs/signal.sql
\. ./procs/star_transform.sql
\. ./procs/uow.sql
\. ./procs/get_setting.sql
\. ./procs/fvrand.sql
\. ./procs/refresh_all.sql

#\. ./events/apply.sql
#\. ./events/compute.sql

\. ./procs/get_percentile.sql

REPLACE into flexviews.mview_settings values ('mvlog_db', 'flexviews');
set @uow_id = NULL;

-- Flexviews requires a signal table to communicate with the external binlog consumer
call flexviews.create_mvlog('flexviews', 'mview_signal');
ALTER TABLE flexviews.flexviews_mview_signal add key(signal_id);

SELECT 'If you see no errors, then installation was successful.' as message;
