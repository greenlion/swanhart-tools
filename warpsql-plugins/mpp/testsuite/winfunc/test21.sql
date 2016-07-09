SELECT NTH_VALUE(round(sum(salary),2),1) OVER () ss FROM empsalary group by depname, empno;
