---
title: "HasSanchayaFiles Trait"
description: "Attach and retrieve grouped media files from Eloquent models."
---

# HasSanchayaFiles Trait

<p>Add the <code>HasSanchayaFiles</code> trait to any Eloquent model to enable polymorphic, grouped media attachments without altering your table schemas.</p>

```php
use DP0\Sanchaya\Traits\HasSanchayaFiles;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasSanchayaFiles;
}
```


<h3>Reading Attachments</h3>

```php
// Get Eloquent Collection of SanchayaFile in 'gallery' group
$gallery = $post->sanchayaFiles('gallery');

// Get first file in 'hero' group
$heroFile = $post->sanchayaFile('hero');

// Get public or temporary URL directly
$heroUrl = $post->sanchayaUrl('hero');

// Get array of file IDs
$ids = $post->sanchayaFileIds('gallery');

// Check if any files are attached
$hasAvatar = $post->hasSanchayaFiles('avatar');
```


<h3>Writing Attachments</h3>

```php
// Attach single file
$post->attachSanchayaFile($fileId, 'avatar');

// Sync a group to an exact ordered list of IDs (removes missing, adds new)
$post->syncSanchayaFiles([12, 14, 19], 'gallery');

// Detach all files in a group
$post->detachSanchayaFiles('gallery');

// Detach a specific file
$post->detachSanchayaFile($fileId, 'gallery');
```

