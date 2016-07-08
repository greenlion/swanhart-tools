SELECT depname,salary, sum(salary) OVER (partition by depname order by salary) as ss FROM empsalary;
