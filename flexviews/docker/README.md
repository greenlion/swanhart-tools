# Flexviews (dockerized)

This is a docker-compose setting for trying flexviews with demo data.

## How to use this project

#### Start FlexCDC and MySQL docker containers

You only need to install following softwares

-   docker
-   docker-compose
-   GNU make

```bash
cd flexviews/docker
make up # 1. Start two docker containers "flexviews" and "flexviews-mysql" 2. Install flexviews API to flexviews schema 3. Register demo MV configuration using flexviews API
```

#### MySQL server can be accessed by following command

```bash
mysql -h 127.0.0.1 -P 3307 -u root -ppassword
```

#### Flexviews API usage

```sql
-- Get mv id
SELECT flexviews.get_id('demo', 'mv_company_sales_items_monthly');
-- Get select sql
SELECT flexviews.get_sql(flexviews.get_id('demo', 'mv_company_sales_items_monthly'));
-- Refresh MV
CALL flexviews.refresh(flexviews.get_id('demo', 'mv_company_sales_items_monthly'), 'BOTH', null);
-- Refresh all views
CALL flexviews.refresh_all('BOTH', null);
```
