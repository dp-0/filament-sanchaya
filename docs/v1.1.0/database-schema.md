---
title: "Database Schema"
description: "Database tables used by Filament Sanchaya for files and attachments."
---

# Database Schema

<h3>sanchaya_files</h3>
<div class="table-wrap">
    <table>
        <thead><tr><th>Column</th><th>Type</th><th>Notes</th></tr></thead>
        <tbody>
            <tr><td><code>id</code></td><td>bigint unsigned</td><td>Primary key</td></tr>
            <tr><td><code>parent_id</code></td><td>bigint unsigned (nullable)</td><td>Self-referencing parent folder foreign key</td></tr>
            <tr><td><code>type</code></td><td>string</td><td><code>file</code> or <code>folder</code></td></tr>
            <tr><td><code>disk</code></td><td>string</td><td>Filesystem disk name</td></tr>
            <tr><td><code>path</code></td><td>text</td><td>Full relative path on disk</td></tr>
            <tr><td><code>file_name</code></td><td>string</td><td>Physical file name on disk</td></tr>
            <tr><td><code>original_name</code></td><td>string</td><td>Original user-facing file name</td></tr>
            <tr><td><code>extension</code></td><td>string (nullable)</td><td>File extension (lowercase)</td></tr>
            <tr><td><code>mime_type</code></td><td>string (nullable)</td><td>Detected MIME type</td></tr>
            <tr><td><code>size</code></td><td>bigint unsigned</td><td>Size in bytes (0 for folders)</td></tr>
            <tr><td><code>metadata</code></td><td>json (nullable)</td><td>JSON object storing alt text, SEO, and custom attributes</td></tr>
            <tr><td><code>deleted_at</code></td><td>timestamp (nullable)</td><td>Soft delete timestamp</td></tr>
        </tbody>
    </table>
</div>

<h3>sanchaya_attachments</h3>
<div class="table-wrap">
    <table>
        <thead><tr><th>Column</th><th>Type</th><th>Notes</th></tr></thead>
        <tbody>
            <tr><td><code>id</code></td><td>bigint unsigned</td><td>Primary key</td></tr>
            <tr><td><code>sanchaya_file_id</code></td><td>bigint unsigned</td><td>Foreign key referencing <code>sanchaya_files.id</code></td></tr>
            <tr><td><code>attachable_type</code></td><td>string</td><td>Polymorphic model class name</td></tr>
            <tr><td><code>attachable_id</code></td><td>unsignedBigInteger</td><td>Polymorphic model ID created by <code>morphs()</code></td></tr>
            <tr><td><code>group</code></td><td>string (nullable)</td><td>Slot/category name (e.g., 'avatar', 'gallery')</td></tr>
            <tr><td><code>order</code></td><td>integer</td><td>Sort position for multiple attachments</td></tr>
        </tbody>
    </table>
</div>
