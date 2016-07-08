SELECT salary, lead(salary,1,99) OVER (ORDER BY salary) as ss FROM empsalary;
