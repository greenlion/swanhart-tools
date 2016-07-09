SELECT depname, empno, salary, ntile(3) OVER (PARTITION BY depname ORDER by salary) ss FROM empsalary;
