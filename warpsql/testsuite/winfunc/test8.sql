SELECT depname,salary, count(distinct salary) OVER (partition by depname order by salary) as ss FROM empsalary;
