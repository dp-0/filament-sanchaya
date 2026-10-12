---
title: "Built-in Actions"
description: "Built-in Filament Sanchaya actions for download, create folder, rename, move, copy, and delete."
---

# Built-in Actions

<p>Every file management operation in Sanchaya is isolated into a dedicated action class, making it completely unit-testable and extensible.</p>
<div class="table-wrap">
    <table>
        <thead><tr><th>Action</th><th>Class</th><th>Description</th></tr></thead>
        <tbody>
            <tr><td><code>CreateFolder</code></td><td><code>CreateFolderAction</code></td><td>Creates a folder record and builds hierarchical paths.</td></tr>
            <tr><td><code>Rename</code></td><td><code>RenameAction</code></td><td>Renames files on disk and cascades path updates to all child files/folders.</td></tr>
            <tr><td><code>Move</code></td><td><code>MoveAction</code></td><td>Moves files or folders to another folder or disk, transfers file contents, and updates descendant paths.</td></tr>
            <tr><td><code>Copy</code></td><td><code>CopyAction</code></td><td>Deep copies files or entire folder subtrees with conflict resolution naming.</td></tr>
            <tr><td><code>Delete</code></td><td><code>DeleteAction</code></td><td>Handles soft deletion, cascade restoration, and permanent file/storage purging.</td></tr>
            <tr><td><code>Download</code></td><td><code>DownloadAction</code></td><td>Streams single files inline/attachment or zips entire folders/bulk selections on the fly.</td></tr>
        </tbody>
    </table>
</div>
