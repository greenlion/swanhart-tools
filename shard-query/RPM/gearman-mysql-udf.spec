Name:		gearman-mysql-udf
Version:	0.5
Release:	2%{?dist}
Summary:	Gearman MySQL UDF Libraries
Group:		System Environment/Libraries
License:	GPLv2
URL:		https://launchpad.net/gearman-mysql-udf
Source:	%{name}-%{version}.tar.gz
BuildRoot:	%{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)
Packager:	Alex Hurd <hurdad@gmail.com>

%description
These functions allow you to run Gearman client jobs from within MySQL. This is built on the libgearman C library.	

%prep
%setup -q

%build
%configure --libdir=%{_libdir}/mysql/plugin/
make %{?_smp_mflags}

%install
rm -rf %{buildroot}
make install DESTDIR=%{buildroot}

%clean
rm -rf %{buildroot}

%post
ldconfig

%postun
ldconfig

%files
%defattr(-,root,root,-)
%{_libdir}/mysql/plugin/*.la
%{_libdir}/mysql/plugin/*.so*

%changelog

