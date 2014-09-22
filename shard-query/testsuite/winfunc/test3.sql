SELECT salary, sum(salary) OVER () as ss FROM empsalary;
