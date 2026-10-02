---
title: "Troubleshooting"
description: "Fix common Filament Sanchaya installation, styling, upload, and disk issues."
---

# Troubleshooting

<div class="callout callout-danger">
    <div class="callout-content">
        <strong>Broken Styles or Layout</strong>
        <p>If layouts appear unstyled or colors are missing, ensure you have a custom theme configured. Because Filament uses Tailwind CSS v4, you must add the package views directory to your custom theme's main CSS file using the <code>@source</code> directive: <code>@source "../../../../vendor/dp0/filament-sanchaya/resources/views";</code> and rebuild your assets (<code>npm run build</code>).</p>
    </div>
</div>
<div class="callout callout-warning">
    <div class="callout-content">
        <strong>403 Forbidden</strong>
        <p>The active Gate policy is returning false. Debug with <code>Gate::inspect()</code> or configure your policy in <code>config/filament-sanchaya.php</code>.</p>
    </div>
</div>
<div class="callout callout-note">
    <div class="callout-content">
        <strong>Missing Disks</strong>
        <p>Confirm your disk is defined in <code>config/filesystems.php</code> and listed in <code>allowed_disks</code> (or leave <code>allowed_disks => null</code> to allow all configured disks).</p>
    </div>
</div>
