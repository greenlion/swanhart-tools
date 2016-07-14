Name:		doo-framework
Version:	1.4.1
Release:	1%{?dist}
Summary:	PHP MVC Framework
#Group:		
License:	BSD
URL:		http://www.doophp.com
Source:		doo-%{version}.tar
BuildRoot:	%{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)
BuildArch:	noarch
Requires:	php
Requires:	php-cli
Requires:	php-pdo
Requires:	php-mysql
Requires:	php-pgsql
Requires:	php-mbstring
Patch0: 	doophp-1.4.1-autoload.patch
Packager:	Alex Hurd <hurdad@gmail.com>

%description
High performance open source PHP framework

%prep
%setup -q -n doophp
%patch0 -p1
%build

%install
%{__rm} -rf %{buildroot}
%{__install} -d %{buildroot}%{_datadir}/
%{__cp} -av dooframework %{buildroot}%{_datadir}/

%clean
rm -rf %{buildroot}

%post

%postun

%files
%defattr(-,root,root,-)
%doc INSTALL.txt LICENSE.txt CHANGELOG.txt
%{_datadir}/dooframework/


%changelog
