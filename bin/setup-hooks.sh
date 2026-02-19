# Git Hooks Path
git config core.hooksPath .githooks

# Install npm dependencies
npm install

# Minify new design system CSS
npm run minify:design

# Clear Symfony cache
php bin/console cache:clear

# Warm up cache
php bin/console cache:warmup

# Run tests
composer test

# Check code style
composer cs

# Run static analysis
composer phpstan

# Install pre-commit hook for auto-formatting
echo "Pre-commit hooks installed!"
