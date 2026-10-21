---
title: "Trash Bin & Soft Deletes"
description: "Understand soft deletes, trash recovery, and permanent deletion behavior in Filament Sanchaya."
---

# Trash Bin & Soft Deletes

<p>When <code>soft_deletes</code> is enabled (the default), deleting files or folders marks their database records as deleted and keeps file contents on disk for restoration. The Trash sidebar entry is hidden when soft deletes are disabled.</p>

<h3>Key Capabilities</h3>
<ul>
    <li><strong>Dedicated Bin View:</strong> Access the Trash Bin directly from the sidebar. The root shows deleted folders and independently deleted files on the current disk; open a deleted folder to inspect its contents.</li>
    <li><strong>One-Click Restore:</strong> Restore individual items from the context dropdown or select multiple items and click <code>Restore Selected</code>.</li>
    <li><strong>Permanent Deletion:</strong> Delete individual or selected trashed items to remove stored file contents and database records. Deleting a folder also removes its nested files, following the original folder hierarchy rather than matching every record with the same storage path.</li>
    <li><strong>Empty Trash:</strong> Purge all trashed files and folders across the selected disk with a single click.</li>
</ul>

```php
// config/filament-sanchaya.php
'soft_deletes' => true, // Set to false to immediately force-delete files from disk
```

<p>Permanent deletion cannot be undone. With <code>soft_deletes</code> set to <code>false</code>, the ordinary Delete action is permanent.</p>

<p>Since v1.1.1, you can delete a folder, create another folder with the same name, and then permanently delete the old folder from Trash without deleting the new folder's files. If a stored file path has been reused, Sanchaya keeps the bytes while another file record still references that path.</p>

<p>From v1.1.2, deleted folder contents no longer appear as separate root-level items. Open a deleted folder to inspect its files and subfolders, and use the breadcrumbs to return to the Trash root. Search checks all trashed items, including files nested inside deleted folders. The Trash badge counts all deleted records, while the root list shows only top-level entries.</p>

<p>Restore a deleted folder to recover its contents. A child cannot be restored on its own while its parent folder remains deleted; Sanchaya asks you to restore the parent first. Permanently deleting a folder removes its descendants. Empty Trash still purges every deleted item on the selected disk.</p>
