SELECT depname,salary, variance(salary) OVER (partition by depname order by salary) as ss FROM empsalary;
