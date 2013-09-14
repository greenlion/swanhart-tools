Name:		shard-query
Version:	2.0.0
Release:	1%{?dist}
Summary:	Shard-Query is a distributed parallel query engine for MySQL
Group:		Applications/Internet
License:	BSD
URL:		https://code.google.com/p/shard-query/
BuildRoot:	%{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)
BuildArch:	noarch
Requires:	gearmand
Requires:	php
Requires:	php-cli
Requires:	php-mysql
Requires:	php-pear
Packager:	Alex Hurd <hurdad@gmail.com>

%description
Shard-Query is a distributed parallel query engine for MySQL

%package ui
Summary:	%{name} UI package
Group:		Applications/Internet
Requires:	%{name} = %{version}
Requires:	doo-framework
Requires:	httpd
Requires:   cronie

%description ui
UI files for %{name}.

%prep
if [ -d shard-query-read-only ]; then
    rm -rf shard-query-read-only
fi
svn export http://shard-query.googlecode.com/svn/trunk/ shard-query-read-only

echo -e "*/2 * * * *\tshard-query\tcd %{_datadir}/%{name}/ui/ && php cli.php check_workers" > shard-query.crontab
echo -e "*/2 * * * *\tshard-query\tcd %{_datadir}/%{name}/ui/ && php cli.php check_job_servers" >> shard-query.crontab

%{__cat} <<EOF >shard-query.conf
#
#  %{summary}
#
Alias /shard-query %{_datadir}/%{name}/ui
<Directory "%{_datadir}/shard-query/ui">
	AllowOverride All
</Directory>
EOF

%build

%install
%{__rm} -rf %{buildroot}
%{__mkdir} -p %{buildroot}/%{_sysconfdir}/httpd/conf.d/
%{__install} -d %{buildroot}%{_datadir}/%{name}/
%{__cp} -av shard-query-read-only/bin %{buildroot}%{_datadir}/%{name}/
%{__cp} -av shard-query-read-only/doc %{buildroot}%{_datadir}/%{name}/
%{__cp} -av shard-query-read-only/include %{buildroot}%{_datadir}/%{name}/
%{__cp} -av shard-query-read-only/proxy %{buildroot}%{_datadir}/%{name}/
%{__cp} -av shard-query-read-only/tools %{buildroot}%{_datadir}/%{name}/
%{__cp} -av shard-query-read-only/setup.php %{buildroot}%{_datadir}/%{name}/
%{__cp} -av shard-query-read-only/shard_query.sql %{buildroot}%{_datadir}/%{name}/
%{__cp} -av shard-query-read-only/bootstrap.ini.example %{buildroot}%{_datadir}/%{name}/
%{__cp} -av shard-query-read-only/install_db.php %{buildroot}%{_datadir}/%{name}/
%{__install} -d %{buildroot}%{_datadir}/%{name}/ui/
%{__cp} -av shard-query-read-only/ui/global %{buildroot}%{_datadir}/%{name}/ui/
%{__cp} -av shard-query-read-only/ui/protected %{buildroot}%{_datadir}/%{name}/ui/
%{__cp} -av shard-query-read-only/ui/cli.php %{buildroot}%{_datadir}/%{name}/ui/
%{__cp} -av shard-query-read-only/ui/index.php %{buildroot}%{_datadir}/%{name}/ui/
%{__cp} -av shard-query-read-only/ui/.htaccess %{buildroot}%{_datadir}/%{name}/ui/

%{__install} -Dp -m0644 %{name}.conf %{buildroot}%{_sysconfdir}/httpd/conf.d/%{name}.conf
%{__install} -Dp -m0644 shard-query.crontab %{buildroot}%{_sysconfdir}/cron.d/shard-query

%clean
rm -rf %{buildroot}

%pre
if ! /usr/bin/id shard-query &>/dev/null; then
    /usr/sbin/useradd -r -d %{_datadir}/shard-query/ -s /bin/sh -c "shard-query" shard-query || \
        %logmsg "Unexpected error adding user \"shard-query\". Aborting installation."
fi

%post

%postun
if [ $1 -eq 0 ]; then
    /usr/sbin/userdel shard-query || %logmsg "User \"shard-query\" could not be deleted."
fi

%files
%defattr(-,root,root,-)
%{_datadir}/%{name}/bin/
%{_datadir}/%{name}/doc/
%{_datadir}/%{name}/include/
%{_datadir}/%{name}/proxy/
%{_datadir}/%{name}/tools/
%{_datadir}/%{name}/setup.php
%{_datadir}/%{name}/shard_query.sql
%{_datadir}/%{name}/bootstrap.ini.example
%{_datadir}/%{name}/install_db.php

%files ui
%defattr(-,root,root,-)
%{_datadir}/%{name}/ui/
%config(noreplace) %{_sysconfdir}/httpd/conf.d/shard-query.conf
%config %{_sysconfdir}/cron.d/shard-query
%defattr(-,shard-query,root,-)
%{_datadir}/%{name}/ui/protected/log/
%{_datadir}/%{name}/ui/protected/var/


%changelog
