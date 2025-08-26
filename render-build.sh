#!/usr/bin/env bash
set -o errexit

# Install mysqli & pdo_mysql
apt-get update && apt-get install -y php-mysql
