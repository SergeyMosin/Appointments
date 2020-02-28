# This file is licensed under the Affero General Public License version 3 or
# later. See the COPYING file.

app_name=$(notdir $(CURDIR))
project_directory=$(CURDIR)/../$(app_name)
build_tools_directory=$(CURDIR)/build/tools
source_build_directory=$(CURDIR)/build/artifacts/source
source_package_name=$(source_build_directory)/$(app_name)
appstore_build_directory=$(CURDIR)/build/artifacts/appstore
appstore_package_name=$(appstore_build_directory)/$(app_name)

all: dev-setup build-prod test-php

# Dev env management
dev-setup: clean clean-dev npm-init

npm-init:
	npm install

composer-init:
	composer install --prefer-dist
	composer update --prefer-dist

npm-update:
	npm update

# Cleaning
clean:
	rm -rf js

clean-dev:
	rm -rf node_modules

# Building
build-dev:
	npm run dev

build-prod: clean
	npm run build

test-php:
	phpunit -c phpunit.xml
	phpunit -c phpunit.integration.xml

test-php-coverage:
	phpunit -c phpunit.xml --coverage-clover=coverage-unit.xml
	phpunit -c phpunit.integration.xml --coverage-clover=coverage-integration.xml

# Builds the source package for the app store, ignores php and js tests
appstore: build-prod
	rm -rf $(appstore_build_directory)
	mkdir -p $(appstore_build_directory)
	tar czf $(appstore_package_name).tar.gz \
	--exclude-vcs \
	$(project_directory)/appinfo \
	$(project_directory)/css \
	$(project_directory)/img \
	$(project_directory)/js \
	$(project_directory)/l10n \
	$(project_directory)/lib \
	$(project_directory)/templates \
	$(project_directory)/translationfiles \
	$(project_directory)/COPYING \
	$(project_directory)/CHANGELOG.md
