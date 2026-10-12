---
title: "Authorization"
description: "Control Filament Sanchaya actions with Laravel policies and gates."
---

# Authorization

<p>Sanchaya ships with <code>SanchayaFilePolicy</code>, which permits authenticated users by default. Register a restrictive policy for production access control.</p>

<div class="callout callout-tip">
    <div class="callout-content">
        <strong>Custom Policy</strong>
        <p>The service provider calls <code>Gate::policy()</code> during boot - but only if you haven't already registered one.</p>
    </div>
</div>

```php
// config/filament-sanchaya.php
'policy' => \App\Policies\MyFilePolicy::class,
```

