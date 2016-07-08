SELECT depname,salary, count(salary) OVER (partition by depname order by salary) as ss FROM empsalary;
