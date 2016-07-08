SELECT depname,salary, max(salary) OVER (partition by depname order by salary) as ss FROM empsalary;
