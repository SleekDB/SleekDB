FROM php:7.4-cli
COPY . /usr/src/sleekdb
WORKDIR /usr/src/sleekdb
CMD [ "php", "./sleekdb_test_scripts/db-test/tester.php" ]