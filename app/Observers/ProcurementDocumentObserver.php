<?php

namespace App\Observers;

use App\Models\Procurement;
use App\Services\DocumentGenerator;

/**
 * Keeps the archived documents of a procurement in step with its data.
 *
 * Whatever screen changes a procurement (the edit form, a checklist input,
 * a PIC appointment, a status change), its generated documents pick the new
 * values up straight away, so nobody has to reload a document by hand.
 */
class ProcurementDocumentObserver
{
    /**
     * The placeholder values each procurement had before the pending save.
     *
     * Static because the container builds a fresh observer for every event,
     * and the value taken while updating is needed once updated fires.
     *
     * @var array<int, array<string, string>>
     */
    protected static array $before = [];

    public function __construct(protected DocumentGenerator $generator) {}

    /**
     * Remember what the documents were filled with before the change.
     */
    public function updating(Procurement $procurement): void
    {
        if (! $procurement->documents()->exists()) {
            return;
        }

        $original = (new Procurement)->newFromBuilder($procurement->getRawOriginal());

        self::$before[spl_object_id($procurement)] = $this->generator->placeholderValues($original);
    }

    /**
     * Push the new values into the documents.
     */
    public function updated(Procurement $procurement): void
    {
        $key = spl_object_id($procurement);

        if (! array_key_exists($key, self::$before)) {
            return;
        }

        $before = self::$before[$key];
        unset(self::$before[$key]);

        $this->generator->syncDocumentsOnProcurementUpdate($procurement, $before);
    }
}
