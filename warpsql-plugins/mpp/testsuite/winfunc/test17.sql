SELECT depname, empno, salary, ntile(3) OVER () ss FROM empsalary;
