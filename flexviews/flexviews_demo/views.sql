
call flexviews.CREATE('demo',
                      'mv_company_sales_items_monthly',
                      'INCREMENTAL'
                      );

select @mvid := last_insert_id();
call flexviews.add_table(@mvid, 'demo', 'customers', 'c', NULL);
call flexviews.add_table(@mvid, 'demo', 'orders', 'o', 'USING(CustomerId)');
call flexviews.add_table(@mvid, 'demo', 'order_details', 'od', 'USING(OrderId)');
call flexviews.add_table(@mvid, 'demo', 'products', 'p', 'USING(ProductId)');

call flexviews.add_expr(@mvid, 'GROUP', 'c.CompanyName', 'CompanyName');
call flexviews.add_expr(@mvid, 'GROUP', 'p.ProductName', 'ProductName');
call flexviews.add_expr(@mvid, 'GROUP', 'o.OrderDate', 'OrderDate');
call flexviews.add_expr(@mvid, 'SUM', 'od.UnitPrice * od.Quantity', 'TotalSalesDollars');
call flexviews.add_expr(@mvid, 'COUNT', '*', 'cnt');


call flexviews.enable(@mvid);
-- ----------------------------------------------------

call flexviews.CREATE('demo',
                      'mv_company_sales_items_yearly',
                      'INCREMENTAL'
                      );

select @mvid := last_insert_id();

call flexviews.add_table(@mvid, 'demo', 'customers',  'c', NULL);
call flexviews.add_table(@mvid, 'demo', 'orders', 'o', 'USING(CustomerId)');
call flexviews.add_table(@mvid, 'demo', 'order_details', 'od', 'USING(OrderId)');
call flexviews.add_table(@mvid, 'demo', 'products', 'p', 'USING(ProductId)');

call flexviews.add_expr(@mvid, 'GROUP', 'c.CompanyName', 'CompanyName');
call flexviews.add_expr(@mvid, 'GROUP', 'p.ProductName', 'ProductName');
call flexviews.add_expr(@mvid, 'GROUP', 'YEAR(o.OrderDate)', 'OrderYear');
call flexviews.add_expr(@mvid, 'SUM', 'od.UnitPrice * od.Quantity', 'TotalSalesDollars');
call flexviews.add_expr(@mvid, 'COUNT', '*', 'cnt');

call flexviews.enable(@mvid);

-- ----------------------------------------------------

call flexviews.CREATE('demo',
                      'mv_company_sales_items_total',
                      'INCREMENTAL'
                      );

select @mvid := last_insert_id();

call flexviews.add_table(@mvid, 'demo','customers', 'c', NULL);
call flexviews.add_table(@mvid, 'demo','orders', 'o', 'USING(CustomerId)');
call flexviews.add_table(@mvid, 'demo','order_details', 'od', 'USING(OrderId)');
call flexviews.add_table(@mvid, 'demo','products', 'p', 'USING(ProductId)');
call flexviews.add_expr(@mvid, 'GROUP', 'c.CompanyName', 'CompanyName');
call flexviews.add_expr(@mvid, 'GROUP', 'p.ProductName', 'ProductName');
call flexviews.add_expr(@mvid, 'SUM', 'od.UnitPrice * od.Quantity', 'TotalSalesDollars');
call flexviews.add_expr(@mvid, 'COUNT', '*', 'cnt');

call flexviews.enable(@mvid);

-- ----------------------------------------------------

call flexviews.CREATE('demo',
                      'mv_company_sales_total',
                      'INCREMENTAL'
                      );

select @mvid := last_insert_id();

call flexviews.add_table(@mvid, 'demo','customers',  'c', NULL);
call flexviews.add_table(@mvid, 'demo','orders',  'o', 'USING(CustomerId)');
call flexviews.add_table(@mvid, 'demo','order_details',  'od', 'USING(OrderId)');
call flexviews.add_table(@mvid, 'demo','products',  'p', 'USING(ProductId)');
call flexviews.add_expr(@mvid, 'GROUP', 'c.CompanyName', 'CompanyName');
call flexviews.add_expr(@mvid, 'SUM', 'od.UnitPrice * od.Quantity', 'TotalSalesDollars');
call flexviews.add_expr(@mvid, 'COUNT', '*', 'cnt');

call flexviews.enable(@mvid);



