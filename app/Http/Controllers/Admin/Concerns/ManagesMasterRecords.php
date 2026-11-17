<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Shared upload / toggle / reorder / delete helpers for master-data controllers. */
trait ManagesMasterRecords
{
    /**
     * Store a new upload for $field, delete the old file, or clear it when
     * "remove_<field>" is sent. Returns the attribute value to save.
     */
    protected function handleUpload(Request $request, string $field, string $directory, ?string $current): ?string
    {
        if ($request->file($field) instanceof UploadedFile) {
            $path = $request->file($field)->store($directory, 'public');
            $this->deleteFile($current);

            return $path;
        }

        if ($request->boolean("remove_{$field}")) {
            $this->deleteFile($current);

            return null;
        }

        return $current;
    }

    protected function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    protected function toggleActive(Model $record, string $label): RedirectResponse
    {
        $record->update(['is_active' => ! $record->is_active]);

        return back()->with('success', $label.($record->is_active ? ' activated.' : ' deactivated.'));
    }

    /** Persist the order of ids sent by the UI (1-based). */
    protected function applyOrder(string $modelClass, array $ids): void
    {
        DB::transaction(function () use ($modelClass, $ids) {
            foreach (array_values($ids) as $index => $id) {
                $modelClass::whereKey($id)->update(['sort_order' => $index + 1]);
            }
        });
    }

    /** Delete unless the model reports a blocker; also removes its files. */
    protected function destroyRecord(Model $record, string $label, array $fileFields = []): RedirectResponse
    {
        if ($reason = $record->deleteBlocker()) {
            return back()->with('error', $reason);
        }

        $files = array_map(fn ($f) => $record->{$f}, $fileFields);
        $record->delete();
        array_walk($files, fn ($path) => $this->deleteFile($path));

        return back()->with('success', $label.' deleted.');
    }

    protected function validateOrder(Request $request, string $table): array
    {
        return $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', "exists:{$table},id"],
        ])['ids'];
    }
}
