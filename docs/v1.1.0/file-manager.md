---
title: "File Manager & Bulk Actions"
description: "Use the Filament Sanchaya file manager, folder tree, toolbar, filters, views, and bulk actions."
---

# File Manager & Bulk Actions

<p>Register the plugin in your Filament panel to add the file manager. Its URL uses your panel's path followed by <code>/sanchaya</code> by default.</p>

<div class="table-wrap">
    <table>
        <thead><tr><th>Area</th><th>Features &amp; Capabilities</th></tr></thead>
        <tbody>
            <tr><td>Toolbar</td><td>Disk switcher, live debounced search, MIME group filter (Images, Videos, Audio, Documents), Date range filter, and Grid / List view mode toggles.</td></tr>
            <tr><td>Sidebar</td><td>Full recursive folder tree navigation with instant subtree expanding and Trash status count badge.</td></tr>
            <tr><td>Detail Panel</td><td>Live preview, file dimensions, MIME type, size, disk path, copyable public URL, and editable <strong>Metadata &amp; SEO</strong> (Alt Text, Title, Caption, Description).</td></tr>
            <tr><td>Bulk Actions</td><td>Select multiple files/folders via checkboxes to perform <strong>Bulk Move</strong> (with folder tree picker), <strong>Bulk Copy</strong>, <strong>Bulk Download (ZIP)</strong>, or <strong>Bulk Delete</strong>. Moving a folder between disks updates nested file paths and transfers their contents.</td></tr>
        </tbody>
    </table>
</div>

<h3>Large File Uploads</h3>
<p>Enable the optional large-file mode through the chunked upload settings. It sends files in smaller requests before assembling them on the selected disk. See <a href="/v1.1.0/chunked-uploads">Chunked Uploads</a> for configuration, deployment requirements, and custom endpoint routing.</p>
<p>Standard uploads keep the Upload action disabled while Livewire is transferring the selected files. Wait until the progress reaches 100% before submitting.</p>

<h3>Generated storage paths</h3>
<p>The standalone manager is global: it can browse all Sanchaya-managed records across the configured disks. Newly generated upload, folder, copy, and move paths use the configured path generator. The default layout is:</p>

```text
sanchaya/{Y}/{m}/{uuid}.{extension}
```

<p>Use <code>storage.base_directory</code> and <code>storage.date_format</code> to change the default layout, or provide a custom <code>storage.path_generator</code>. These settings affect new and relocated paths only; existing records remain readable.</p>

<div class="callout callout-tip">
    <div class="callout-content">
        <strong>Bulk Move &amp; Copy</strong>
        <p>When selecting multiple items to move or copy, Sanchaya opens an interactive modal allowing you to choose any destination folder in the hierarchy or Root, preserving nested subfolder structures automatically.</p>
    </div>
</div>
