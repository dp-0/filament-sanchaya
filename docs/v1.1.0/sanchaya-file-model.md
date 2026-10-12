---
title: "SanchayaFile & Metadata"
description: "Understand the SanchayaFile model, metadata fields, and SEO fields."
---

# SanchayaFile & Metadata

<p>The <code>SanchayaFile</code> Eloquent model represents every indexed file and directory in Sanchaya.</p>

<h3>Metadata &amp; SEO Properties</h3>
<div class="table-wrap">
    <table>
        <thead><tr><th>Property / Accessor</th><th>Type</th><th>Description</th></tr></thead>
        <tbody>
            <tr><td><code>$file->alt_text</code></td><td><code>string</code></td><td>Image alternative text for accessibility &amp; SEO.</td></tr>
            <tr><td><code>$file->title</code></td><td><code>string</code></td><td>Human-friendly title attribute.</td></tr>
            <tr><td><code>$file->caption</code></td><td><code>string</code></td><td>Media caption for galleries.</td></tr>
            <tr><td><code>$file->description</code></td><td><code>string</code></td><td>Long-form description / notes.</td></tr>
            <tr><td><code>$file->getMetadata($key, $default)</code></td><td><code>mixed</code></td><td>Access metadata using dot-notation (e.g. <code>$file->getMetadata('exif.camera')</code>).</td></tr>
            <tr><td><code>$file->setMetadata($key, $value)</code></td><td><code>SanchayaFile</code></td><td>Set custom metadata using dot-notation; call <code>save()</code> to persist it.</td></tr>
        </tbody>
    </table>
</div>

<h3>Core Accessors &amp; Scopes</h3>
<div class="table-wrap">
    <table>
        <thead><tr><th>Accessor / Scope</th><th>Type</th><th>Description</th></tr></thead>
        <tbody>
            <tr><td><code>$file->display_name</code></td><td><code>string</code></td><td>Original upload name or formatted folder name.</td></tr>
            <tr><td><code>$file->url</code></td><td><code>?string</code></td><td>Resolved storage URL.</td></tr>
            <tr><td><code>$file->preview_url</code></td><td><code>?string</code></td><td>Accessible public or temporary file URL when the disk supports one.</td></tr>
            <tr><td><code>$file->human_size</code></td><td><code>string</code></td><td>Formatted size string (e.g., "3.2 MB").</td></tr>
            <tr><td><code>$file->is_image</code> / <code>is_video</code></td><td><code>bool</code></td><td>MIME type helpers.</td></tr>
            <tr><td><code>SanchayaFile::query()->folders()</code></td><td>Query</td><td>Filter only folders.</td></tr>
            <tr><td><code>SanchayaFile::query()->files()</code></td><td>Query</td><td>Filter only files.</td></tr>
            <tr><td><code>SanchayaFile::query()->onDisk($disk)</code></td><td>Query</td><td>Filter by filesystem disk.</td></tr>
            <tr><td><code>SanchayaFile::query()->ofMimeGroup('image')</code></td><td>Query</td><td>Filter by MIME group.</td></tr>
        </tbody>
    </table>
</div>
