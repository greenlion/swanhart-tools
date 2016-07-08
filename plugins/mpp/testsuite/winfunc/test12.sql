SELECT depname,salary, row_number() OVER () as ss FROM empsalary;
SELECT depname,salary, row_number() OVER (partition by depname order by salary desc) as ss FROM empsalary;
