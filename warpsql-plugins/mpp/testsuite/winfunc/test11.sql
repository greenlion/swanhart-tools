SELECT depname,salary, rank() OVER () as ss FROM empsalary;
SELECT depname,salary, rank() OVER (partition by depname order by salary desc) as ss FROM empsalary;
