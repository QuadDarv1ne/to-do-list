#!/bin/bash
# Script to reset migrations for fresh installations
# WARNING: This will delete all migration history and recreate from scratch
# Only use this on development environments or fresh installations

echo "⚠️  WARNING: This will reset all migrations!"
echo "This should only be used for fresh installations."
read -p "Are you sure you want to continue? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo "Aborted."
    exit 1
fi

echo "📦 Backing up current database..."
cp var/data.db var/data.db.backup.$(date +%Y%m%d_%H%M%S)

echo "🗑️  Dropping all tables..."
php bin/console doctrine:schema:drop --force --full-database

echo "📝 Removing old migrations..."
rm -rf migrations/Version*.php

echo "🔨 Creating fresh migration..."
php bin/console doctrine:migrations:diff

echo "⬆️  Running migration..."
php bin/console doctrine:migrations:migrate --no-interaction

echo "✅ Migration reset complete!"
echo "📊 Creating test data..."
php bin/console app:create-test-users

echo "🎉 Done! Database has been reset with a single migration."
