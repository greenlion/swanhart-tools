SELECT depname, empno, salary, FIRST_VALUE(salary) OVER () ss FROM empsalary;
