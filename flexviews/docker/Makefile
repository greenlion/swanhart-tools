up:
	cp ../consumer/consumer.ini.example ../consumer/consumer.ini
	sed -i 's/\/usr\/local\/mysql\/bin\/mysqlbinlog/\/usr\/bin\/mysqlbinlog/' ../consumer/consumer.ini
	sed -i 's/host=127.0.0.1/host=flexviews-mysql/' ../consumer/consumer.ini
	sed -i 's/password=/password=password/' ../consumer/consumer.ini
	mkdir flexviews-mysql-initdb
	cp ../flexviews_demo/demo.sql flexviews-mysql-initdb/
	docker-compose up -d
	docker exec flexviews bash -c "cd .. && mysql -h flexviews-mysql -u root -ppassword < install.sql"
	docker exec flexviews bash -c "mysql -h flexviews-mysql -u root -ppassword < ../docker/enable-mv.sql"
	docker exec flexviews bash -c "mysql -h flexviews-mysql -u root -ppassword < ../flexviews_demo/views.sql"
destroy:
	docker-compose down -v
	docker rmi flexviews:latest
tail-consumer-error:
	docker exec flexviews tail -f flexcdc.err
