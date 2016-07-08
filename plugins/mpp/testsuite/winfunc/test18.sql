SELECT depname, empno, salary, LAST_VALUE(salary) OVER () ss FROM empsalary;
