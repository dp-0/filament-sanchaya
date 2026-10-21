---
title: "Filament Sanchaya"
description: "A focused file manager and media picker for Filament v5."
layout: page
---

<div class="sanchaya-home">
  <section class="sanchaya-hero">
    <div>
      <div class="sanchaya-eyebrow">Filament v5 media plugin</div>
      <h1>Filament Sanchaya</h1>
      <p class="sanchaya-copy">A focused file manager and media picker for Filament v5, built for selecting, uploading, attaching, organizing, and recovering files inside your admin panel.</p>
      <div class="sanchaya-actions">
        <a class="sanchaya-button primary" href="/v1.1.0/">Open v1.1 docs</a>
        <a class="sanchaya-button" href="https://github.com/dp-0/filament-sanchaya">GitHub</a>
      </div>
      <div class="sanchaya-badges">
        <span class="badge">Media picker</span>
        <span class="badge">Upload modal</span>
        <span class="badge mint">Theme aware</span>
      </div>
    </div>
    <div class="sanchaya-video">
      <video controls preload="metadata" poster="/assets/filament-sanchaya-thumbnail.jpg">
        <source src="/assets/filament-sanchaya-video.mp4" type="video/mp4">
      </video>
    </div>
  </section>
</div>

<section class="sanchaya-section sanchaya-changelog">
  <h2>Changelog</h2>
  <div class="release">
    <div class="release-version">v1.1.2</div>
    <div>
      <ul>
        <li>Organized Trash into navigable deleted folders instead of repeating their contents at the root.</li>
        <li>Kept nested Trash search, breadcrumbs, and restore behavior clear and safe.</li>
        <li>Completed Filament icon-wrapper coverage for file-grid icons.</li>
      </ul>
    </div>
  </div>
  <div class="release">
    <div class="release-version">v1.1.1</div>
    <div>
      <ul>
        <li>Fixed Trash purging so a deleted folder cannot remove files from a replacement folder with the same path.</li>
        <li>Preserved file contents when another media record still references a reused path.</li>
        <li>Updated the picker selection when reopening the media library after removing images.</li>
        <li>Fixed chunked uploads into folders created at the manager root.</li>
      </ul>
    </div>
  </div>
  <div class="release">
    <div class="release-version">v1.1.0</div>
    <div>
      <ul>
        <li>Added configurable storage base directories and date-based path generation.</li>
        <li>Added overridable path generators through configuration.</li>
        <li>Added picker-scoped directories with <code>SanchayaPicker::directory()</code>.</li>
        <li>Added opt-in chunked uploads for large files in the manager and picker.</li>
        <li>Fixed Filament v5 icon rendering and disabled Upload during active transfers.</li>
      </ul>
    </div>
  </div>
  <div class="release">
    <div class="release-version">v1.0.2</div>
    <div>
      <ul>
        <li>Fixed Alpine keyboard shortcut handling in the file manager and media picker.</li>
        <li>Updated release documentation and public documentation URL.</li>
        <li>Added versioned documentation structure for clean release URLs.</li>
      </ul>
    </div>
  </div>
  <div class="release">
    <div class="release-version">v1.0.1</div>
    <div>
      <ul>
        <li>Updated package dependency support for Filament v5 and Livewire v4.</li>
      </ul>
    </div>
  </div>
  <div class="release">
    <div class="release-version">v1.0.0</div>
    <div>
      <ul>
        <li>Initial release of Filament Sanchaya.</li>
      </ul>
    </div>
  </div>
</section>
