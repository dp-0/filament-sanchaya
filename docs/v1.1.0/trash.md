---
title: "Trash Bin & Soft Deletes"
description: "Understand soft deletes, trash recovery, and permanent deletion behavior in Filament Sanchaya."
---

# Trash Bin & Soft Deletes

<p>When <code>soft_deletes</code> is enabled (the default), deleting files or folders marks their database records as deleted and keeps file contents on disk for restoration. The Trash sidebar entry is hidden when soft deletes are disabled.</p>

<h3>Key Capabilities</h3>
<ul>
    <li><strong>Dedicated Bin View:</strong> Access the Trash Bin directly from the sidebar. Shows all soft-deleted items on the current disk.</li>
    <li><strong>One-Click Restore:</strong> Restore individual items from the context dropdown or select multiple items and click <code>Restore Selected</code>.</li>
    <li><strong>Permanent Deletion:</strong> Delete individual or selected trashed items to remove stored file contents and database records. Deleting a folder also removes its nested files.</li>
    <li><strong>Empty Trash:</strong> Purge all trashed files and folders across the selected disk with a single click.</li>
</ul>

```php
// config/filament-sanchaya.php
'soft_deletes' => true, // Set to false to immediately force-delete files from disk
```

<p>Permanent deletion cannot be undone. With <code>soft_deletes</code> set to <code>false</code>, the ordinary Delete action is permanent.</p>
