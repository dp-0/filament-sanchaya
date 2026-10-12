# Filament Sanchaya — Implementation Plan

> Package: `dp0/filament-sanchaya`
> Generated: 2026-09-06
> Status: Preparing first release; chunked uploads and image manipulation are deferred to the next release.

---

## Table of Contents

- [Package Overview](#package-overview)
- [1. Bugs & Critical Issues](#1-bugs--critical-issues)
- [2. Unnecessary & AI-Generated Comments](#2-unnecessary--ai-generated-comments)
- [3. Code Quality Issues](#3-code-quality-issues)
- [4. Missing Features](#4-missing-features)
- [5. Implementation Roadmap](#5-implementation-roadmap)

---

## Package Overview

Filament Sanchaya is a file manager plugin for Filament PHP. It provides:

- Full-page file manager with grid/list views, folders, search, filters, and trash
- SanchayaPicker — custom form field for selecting files
- SanchayaColumn — table column for displaying attached media
- SanchayaEntry — infolist entry for displaying attached media
- HasSanchayaFiles trait — polymorphic attachment system for any Eloquent model
- Action classes — modular file operations (Copy, Move, Rename, Delete, Download, CreateFolder)
- Soft deletes with trash bin, multi-disk support, breadcrumbs, metadata

---

## 1. Bugs & Critical Issues

### BUG-1: SQL REPLACE can corrupt descendant paths [CRITICAL]

**Files:** `src/Actions/MoveAction.php` L107-117, `src/Actions/RenameAction.php` L89-101

`DB::raw("REPLACE(path, oldQ, newQ)")` replaces ALL occurrences of the substring, not just the prefix. If folder `docs` is renamed to `files`, path `docs/user_docs/info.txt` becomes `files/user_files/info.txt`.

**Fix:** Use `CONCAT(newPrefix, SUBSTRING(path, LENGTH(oldPrefix) + 1))`.

### BUG-2: forceDelete loads all descendants into memory [MEDIUM]

**File:** `src/Actions/DeleteAction.php` L72-101

`->get()` loads every descendant. Use `lazyById()` instead.

### BUG-3: cascadeMove loads all descendants and runs duplicate work [MEDIUM]

**File:** `src/Actions/MoveAction.php` L86-118

PHP loop + SQL REPLACE do duplicate work. Refactor to PHP loop only with `$child->update()`.

### BUG-4: Missing composer dependencies [LOW]

**File:** `composer.json` — missing `filament/filament`, `laravel/framework`, `livewire/livewire`.

### BUG-5: Policy defaults all checks to true [LOW]

**File:** `src/Policies/SanchayaFilePolicy.php` — needs documentation warning.

### BUG-6: Raw SQL bypasses Eloquent events [LOW]

**Files:** MoveAction, RenameAction — `DB::table()->update()` skips model events.

---

## 2. Unnecessary & AI-Generated Comments

### Section Headers to Remove (40+)

All `/* Section Name */` comments across Action classes, FileBrowser.php, and SanchayaServiceProvider.php that just label what the methods below already say. Examples: `/* File copy */`, `/* Helpers */`, `/* Navigation */`, `/* Upload */`, etc.

### AI-Style DocBlocks to Remove (30+)

All docblocks that simply restate the method name across SanchayaFile.php, SanchayaAttachment.php, HasSanchayaFiles.php, FileBrowser.php. Examples: `/** Human-friendly name for display in the UI. */` on `getDisplayNameAttribute()`.

### Obvious Comments to Remove

- `// Create the new folder record` before `::create()`
- `// Move on disk` before `Storage::move()`
- `// Uniqueness: same name in same parent + disk` before uniqueness query
- `// 1. Publish config`, `// 2. Publish & run migrations` numbered steps

### Comments to KEEP

- `// Cross-disk move: copy then delete`
- `// Cannot copy a folder into itself or its own descendants`
- `// Another request claimed the same original_name — retry once`
- `// deepest first for FK safety`
- `// Stable tie-breaker keeps cursor pagination deterministic.`
- All `@param`, `@return`, `@throws` type annotations
- All Blade structural comments

---

## 3. Code Quality Issues

1. **FileBrowser.php is 1514 lines** — extract into traits (HasFileSelection, HasFileUpload, HasFileActions, HasTrashOperations, HasDetailPanel)
2. **Missing return types on model scopes** — SanchayaFile and SanchayaAttachment scopes
3. **Overly generic exception catching** — `bulkMove()` / `bulkCopy()` catch `\Throwable`
4. **FilePicker hardcodes SanchayaFile** — `resolveSelectedPreviewItems` should use `$this->fileModel()`
5. **Inconsistent comment style** — SanchayaFile uses Laravel banner format, rest doesn't

---

## 4. Missing Features

| # | Feature | Priority | Est. Time |
|---|---------|----------|-----------|
| 1 | Image Manipulation (crop/resize/optimize) | Medium | 16 hours |
| 2 | Drag-and-Drop Reordering in Picker | Medium | 4 hours |
| 3 | File Usage Tracking ("Used In" panel) | Medium | 4 hours |
| 4 | Duplicate Detection (hash-based) | Low | 3 hours |
| 5 | Storage Quota / Usage Dashboard | Low | 4 hours |
| 6 | File Versioning | Low | 8 hours |
| 7 | Chunked Uploads for Large Files | Medium | 8 hours |
| 8 | Database Seeders | Low | 2 hours |
| 9 | Policy Authorization Tests | Medium | 2 hours |
| 10 | Eloquent Model Event Hooks | High | 3 hours |
| 11 | Keyboard Shortcuts | Low | 3 hours |
| 12 | Drag-and-Drop File Moving | Low | 6 hours |

---

## 5. Implementation Roadmap

### Phase 1: Critical Bug Fixes [COMPLETED]
- [x] Fix SQL REPLACE path corruption (MoveAction, RenameAction)
- [x] Add chunking to forceDelete (DeleteAction)
- [x] Fix cascadeMove memory + duplicate work (MoveAction)
- [x] Add missing composer dependencies
- [x] Fix resolveSelectedPreviewItems to use fileModel()

### Phase 2: Comment Cleanup [COMPLETED]
- [x] Remove all unnecessary section comments
- [x] Remove AI-style docblocks (keep @param, @return, @throws)

### Phase 3: Code Quality [COMPLETED]
- [x] Add return types to all model scopes
- [x] Improve bulk error notification display
- [x] Document default policy being permissive

### Phase 4: Features [CURRENT RELEASE]
- [x] Eloquent model event hooks (FEAT-10)
- [x] Policy authorization tests (FEAT-9)
- [x] Database Seeders (FEAT-8)
- [x] File usage tracking ("Used In" panel) (FEAT-3)
- [x] Drag-and-drop reordering in Picker (FEAT-2)
- [x] Duplicate detection (hash-based) (FEAT-4)
- [x] Storage quota & usage dashboard (FEAT-5)
- [x] Keyboard shortcuts (FEAT-11)

### Planned for the Next Release
- [ ] Chunked uploads (FEAT-7)
- [ ] Image manipulation (FEAT-1)
