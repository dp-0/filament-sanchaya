---
title: "Troubleshooting"
description: "Fix common Filament Sanchaya installation, styling, upload, and disk issues."
---

# Troubleshooting

<div class="callout callout-note">
    <div class="callout-content">
        <strong>Large File Upload Stops</strong>
        <p>Check <code>file.max_chunked_file_size</code>, <code>file.chunk_size</code>, and the selected disk's storage quota. HTTP 413 means the web server rejected a request before Sanchaya could process it. The request includes multipart overhead, so keep <code>file.chunk_size</code> below the PHP and web-server request-body limits (for example, use a 1 MB chunk with a 2 MB limit). On multiple app servers, make sure each request can access the same <code>storage/app/sanchaya-chunks</code> directory. If you replaced the controller, verify that it returns JSON with an increasing <code>offset</code> value.</p>
    </div>
</div>
<div class="callout callout-note">
    <div class="callout-content">
        <strong>Destination folder is not available on this disk</strong>
        <p>Confirm that the upload disk matches the folder's disk. For a directory-scoped picker, the destination folder must also be inside that directory. Chunked uploads to folders created at the standalone manager root are supported from v1.1.1.</p>
    </div>
</div>
<div class="callout callout-note">
    <div class="callout-content">
        <strong>Picker Directory Does Not Show Files</strong>
        <p><code>SanchayaPicker::directory()</code> is relative to <code>storage.base_directory</code>. Confirm that the stored file paths are inside that namespace and that the picker is using the same disk as the files.</p>
    </div>
</div>

<div class="callout callout-note">
    <div class="callout-content">
        <strong>Upload says the file was not found</strong>
<p>Do not submit the standard Upload action while Livewire is still transferring the selected file. The action is disabled during the transfer and becomes available when progress reaches 100%. If the issue persists, check the browser console, temporary upload configuration, PHP upload limits, and whether the temporary file has expired.</p>
    </div>
</div>

<div class="callout callout-note">
    <div class="callout-content">
        <strong>Filament reports a missing icon component</strong>
<p>Filament v5 package views should render Heroicons through Filament's wrapper, for example <code>&lt;x-filament::icon icon="heroicon-m-chevron-up-down" /&gt;</code>. If a published or customized view still uses a direct <code>x-heroicon-*</code> component, update it to the wrapper syntax.</p>
    </div>
</div>

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
