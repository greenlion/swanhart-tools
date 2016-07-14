SELECT depname, empno, salary, sum(salary) OVER (PARTITION BY depname ORDER by salary rows between 1 following and 1 following) ss FROM empsalary;
