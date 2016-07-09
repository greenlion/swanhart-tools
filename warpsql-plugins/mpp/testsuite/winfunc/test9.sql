SELECT depname,salary, stddev(salary) OVER (partition by depname order by salary) as ss FROM empsalary;
