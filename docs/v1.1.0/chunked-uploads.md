---
title: "Chunked Uploads"
description: "Configure large-file chunked uploads and custom chunk endpoints in Filament Sanchaya."
---

# Chunked Uploads

<p>Chunked uploads provide an alternative upload mode for the standalone file manager and <code>SanchayaPicker</code>. When enabled, the chunked uploader is shown instead of the standard Livewire uploader. The existing disk selector remains available when multiple disks are configured. The browser sends the file in smaller requests, which are assembled before the final file is published to the selected disk.</p>

<h3>Enable chunked uploads</h3>

<p>Chunked uploads are disabled by default. Enable them in <code>config/filament-sanchaya.php</code>:</p>

```php
'file' => [
    'chunked' => true,
    'max_chunked_file_size' => 102400, // KB per file
    'chunk_size' => 1024, // KB per request
],
```

<p>The default limit is 100 MB per file and the default chunk size is 1 MB. A multipart request is larger than the chunk itself, so both the PHP and web-server request-body limits must leave room above <code>chunk_size</code>. A 2 MB request-body limit can reject a 2 MB chunk because of this overhead.</p>

<h3>Enable it for a picker</h3>

<p>Use the field methods to enable or override the large-file mode for a particular picker:</p>

```php
SanchayaPicker::make('gallery')
    ->chunked()
    ->chunkedUploadMaxFileSize(204800); // 200 MB, in KB
```

<p>Use <code>->chunked(false)</code> to use the standard Livewire uploader for an individual picker when chunked uploads are enabled globally. The two upload controls are mutually exclusive. In a multiple-file picker, selected files upload sequentially: each file is assembled and saved before the next begins. The dialog stays open until the final file succeeds. If a file fails, processing stops, the error is shown, and files that have not yet uploaded remain selected so you can retry.</p>

<h3>Staging and deployment</h3>

<p>Chunks are staged in the application's local <code>storage/app/sanchaya-chunks</code> directory. Once all chunks arrive, Sanchaya assembles the file, streams it to the selected disk, and creates the file record. Incomplete uploads older than one day are cleaned up when a new upload starts.</p>

<p>Multi-server deployments need shared staging storage or request affinity for the duration of an upload. The staging directory must be writable by the application process.</p>

<h3>Custom chunk route</h3>

<p>Sanchaya registers its default endpoint using the fixed package route name <code>sanchaya.upload-chunk</code>. Its URI, middleware, signed URL requirement, and expiration are configurable:</p>

```php
'chunk_upload' => [
    'register_route' => true,
    'uri' => 'sanchaya/upload-chunk',
    'require_signed_url' => true,
    'signed_url_expiration' => 120, // minutes
    'middleware' => ['web', 'throttle:600,1'],
],
```

<p>To use an application-owned controller, disable the package route and define an application route with the configured name. The <code>route_name</code> setting is only used in this mode:</p>

```php
'chunk_upload' => [
    'register_route' => false,
    'route_name' => 'media.chunks',
    'require_signed_url' => true,
],
```

```php
Route::middleware(['web', 'signed', 'throttle:600,1'])
    ->post('media/chunks', [MediaChunkController::class, 'store'])
    ->name('media.chunks');
```

<p>The custom controller receives the Laravel <code>Request</code>. Each successful request must return JSON containing the new assembled byte offset:</p>

```json
{"offset": 2097152}
```

<p>Keep signed URL validation, authentication, authorization, CSRF protection, and throttling enabled unless the application intentionally provides equivalent protections.</p>

<h3>Customize finalization</h3>

<p>Chunk collection and final publishing are separate operations. Sanchaya collects and validates chunks first, then resolves the configured finalizer to publish the completed temporary file to the selected disk and create the database record.</p>

```php
'file' => [
    'finalizer' => \App\Actions\FinalizeMediaUploadAction::class,
],
```

<p>Custom finalizers should implement <code>DP0\Sanchaya\Contracts\ChunkUploadFinalizer</code>. The <code>execute()</code> method receives the completed upload metadata, disk, optional parent folder ID, optional picker directory, accepted MIME patterns, and maximum file size. It must return the created <code>SanchayaFile</code>.</p>

<p>The default implementation is <code>DP0\Sanchaya\Actions\FinalizeChunkedUploadAction</code>. It performs size, quota, MIME, destination-scope, storage, checksum, and database-record validation.</p>

<h3>Standard upload safeguard</h3>

<p>Chunked uploads provide their own Cancel action, which aborts the active chunk request and removes its staged data; previously completed files remain saved. When chunked uploads are disabled, standard Livewire uploads keep the Upload action disabled while the browser is still transferring files. The action becomes available after the transfer reaches 100%.</p>
