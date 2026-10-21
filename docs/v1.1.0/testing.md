---
title: "Automated Testing"
description: "Run Filament Sanchaya automated tests."
---

# Automated Testing

<p>Sanchaya includes an automated test suite covering actions, models, traits, form/table/infolist components, and Livewire browser interactions. Run these commands from a Laravel application that includes the package source; the test bootstrap registers the package test namespace. These commands are for package development, not consumer installation.</p>

<p>GitHub Actions creates a fresh Laravel test application and runs this suite for pushes to <code>main</code> and pull requests.</p>

```php
# Run all tests via PHPUnit or Artisan
php artisan test packages/dp0/filament-sanchaya/tests

# Run specific suite
php artisan test --filter=CreateFolderActionTest
```
