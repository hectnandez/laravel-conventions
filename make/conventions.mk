# Targets compartidos. Inclúyelo desde el Makefile del proyecto:
#   include .conventions/Makefile
#
# El proyecto puede definir CONVENTIONS_BIN antes del include.

CONVENTIONS_BIN ?= ./vendor/bin/conventions

.PHONY: conventions-check conventions-sync

conventions-check:
	$(CONVENTIONS_BIN) check

conventions-sync:
	$(CONVENTIONS_BIN) sync
