SHELL := /bin/bash

-include .env
export

DEPLOY_USER ?=
DEPLOY_HOST_PROD ?=
DEPLOY_PATH_PROD ?=
DEPLOY_SRC ?=
CLEAN_PATHS ?= node_modules/.cache dist build

# Drupal write-protected settings directory. Kept read-only (`u-w`) at rest;
# unlock before a `git pull`/deploy that needs to touch it, then lock again.
SITES_DEFAULT ?= web/sites/default

.DEFAULT_GOAL := help
.PHONY: help audit audit-php audit-js check-updates check-updates-php check-updates-js deploy deploy-prod clean sites-default-unlock sites-default-lock

help:
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Check"
	@echo "  check-updates         Check outdated PHP and JS dependencies"
	@echo "  check-updates-php     composer outdated"
	@echo "  check-updates-js      pnpm outdated + npm-check-updates"
	@echo "  audit                 Audit PHP and JS dependencies"
	@echo "  audit-php             composer audit"
	@echo "  audit-js              pnpm audit"
	@echo ""
	@echo "Deploy"
	@echo "  deploy                Alias of deploy-prod"
	@echo "  deploy-prod           Deploy built assets via SCP"
	@echo ""
	@echo "Drupal"
	@echo "  sites-default-unlock  Allow writes to $(SITES_DEFAULT) (before git pull)"
	@echo "  sites-default-lock    Restore write protection on $(SITES_DEFAULT)"
	@echo ""
	@echo "Cleanup"
	@echo "  clean                 Remove generated build artifacts"
	@echo ""

audit:
	@rc=0; \
	$(MAKE) audit-php || rc=$$?; \
	$(MAKE) audit-js || rc=$$?; \
	exit $$rc

audit-php:
	composer audit

audit-js:
	pnpm audit

check-updates: check-updates-php check-updates-js

check-updates-php:
	-composer outdated

check-updates-js:
	-pnpm outdated
	-pnpm dlx npm-check-updates

deploy: deploy-prod

deploy-prod:
	@if [ -z "$(DEPLOY_USER)" ] || [ -z "$(DEPLOY_HOST_PROD)" ] || [ -z "$(DEPLOY_PATH_PROD)" ] || [ -z "$(DEPLOY_SRC)" ]; then \
		echo "Set DEPLOY_USER, DEPLOY_HOST_PROD, DEPLOY_PATH_PROD, and DEPLOY_SRC before deploying."; \
		exit 1; \
	fi
	scp -r $(DEPLOY_SRC) $(DEPLOY_USER)@$(DEPLOY_HOST_PROD):$(DEPLOY_PATH_PROD)

sites-default-unlock:
	chmod u+w $(SITES_DEFAULT) $(SITES_DEFAULT)/settings.php $(SITES_DEFAULT)/default.settings.php 2>/dev/null || true

sites-default-lock:
	chmod u-w $(SITES_DEFAULT)/settings.php $(SITES_DEFAULT)/default.settings.php $(SITES_DEFAULT) 2>/dev/null || true

clean:
	rm -rf $(CLEAN_PATHS)
