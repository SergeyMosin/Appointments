# This file is licensed under the Affero General Public License version 3 or
# later. See the COPYING file.

app_name=$(notdir $(CURDIR))
project_directory=$(CURDIR)/../$(app_name)
build_tools_directory=$(CURDIR)/build/tools
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

# Get timezone data from comm-central calendar project https://hg.mozilla.org/comm-central/file/tip/calendar by Mozilla, licensed under the MPL 2.0 https://www.mozilla.org/en-US/MPL/2.0/
timezones:
	curl https://hg.mozilla.org/comm-central/raw-file/tip/calendar/timezones/zones.json --output ajax/zones.json


# Builds the source package for the app store, ignores php and js tests
appstore: build-prod
	rm -rf $(appstore_build_directory)
	mkdir -p $(appstore_build_directory)
	tar czf $(appstore_package_name).tar.gz \
	--exclude-vcs \
	$(project_directory)/ajax \
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

#	$(project_directory)/translationfiles \

sign-app:
	openssl dgst -sha512 -sign ~/certs/appointments.key \
	$(appstore_package_name).tar.gz | openssl base64