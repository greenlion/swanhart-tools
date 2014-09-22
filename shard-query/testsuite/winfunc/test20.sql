SELECT depname, empno, salary, NTH_VALUE(sum(salary),1) OVER () ss FROM empsalary group by depname, empno;
