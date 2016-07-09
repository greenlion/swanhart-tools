SELECT salary, sum(salary) OVER (ORDER BY salary) as ss FROM empsalary;
