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

SET SQL_MODE = STRICT_TRANS_TABLES;

CREATE DATABASE IF NOT EXISTS flexviews;

GRANT ALL
   ON flexviews.*
   TO flexviews@localhost 
IDENTIFIED BY 'flexdemo';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, RELOAD, PROCESS, FILE, REFERENCES, INDEX, ALTER, SUPER, CREATE TEMPORARY TABLES, EXECUTE, REPLICATION SLAVE, REPLICATION CLIENT 
   ON *.*
   TO flexviews@localhost;

USE flexviews;

\. schema/schema.sql
