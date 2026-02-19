# Git Hooks Path
git config core.hooksPath .githooks

REM Install npm dependencies
npm install

REM Minify new design system CSS
npm run minify:design

REM Clear Symfony cache
php bin/console cache:clear

REM Warm up cache
php bin/console cache:warmup

REM Run tests
composer test

REM Check code style
composer cs

REM Run static analysis
composer phpstan

echo Pre-commit hooks installed!
