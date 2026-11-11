import { useState } from 'react';
import { Form, Link, type LoaderFunctionArgs, Navigate, useLoaderData } from 'react-router';
import { ApiError, api } from '../../api';
import { requireUser } from '../../auth';
import { useConfirm } from '../../components/ConfirmDialog';
import type { Message } from '../../types';
import { limit, useGo, useTitle } from '../../ui';
import { cancelImport, loadImportStep } from './importState';

interface Preview {
    name: string;
    format: 'csv' | 'anki';
    valid_count: number;
    errors: { row: number; message: string }[];
    error_count: number;
    duplicates: { row: number; word1: string; word2: string }[];
    duplicate_count: number;
    /** Changes are field => [old, new], in the saved card's language order. */
    updates: { row: number; id: number; word1: string; word2: string; changes: Record<string, [unknown, unknown]> }[];
    update_count: number;
    /** Language pairs of the file, with the number of cards already saved for each. */
    pairs: { key: string; label: string; saved: number }[];
    /** Pairs whose saved cards are deleted before importing. */
    replaced: string[];
    delete_count: number;
}

export async function loader({ request }: LoaderFunctionArgs) {
    requireUser(request, 'admin');

    // "replace[]" from the "Check again" form goes to the API as it is.
    return loadImportStep<Preview>('/admin/import/preview', new URL(request.url).searchParams);
}

const FIELD_NAMES: Record<string, string> = {
    complexity: 'Complexity',
    notes1: 'Notes 1',
    examples1: 'Examples 1',
    notes2: 'Notes 2',
    examples2: 'Examples 2',
};

/** What the import will do, before anything is saved (SPECIFICATION.md §5.4). */
export default function ImportPreview() {
    useTitle('Check import');
    const preview = useLoaderData<typeof loader>();
    const go = useGo();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [confirm, confirmDialog] = useConfirm();

    if ('failed' in preview) {
        return <Navigate to="/admin/import" replace state={{ error: preview.failed }} />;
    }

    const rowLabel = preview.format === 'anki' ? 'Note' : 'Row';
    const replaceable = preview.pairs.filter((pair) => pair.saved > 0);
    const replacedLabels = preview.pairs
        .filter((pair) => preview.replaced.includes(pair.key))
        .map((pair) => pair.label)
        .join(', ');

    const runImport = async (update: boolean) => {
        if (
            preview.delete_count > 0 &&
            !(await confirm({
                message: <>
                    Delete <b>{preview.delete_count}</b> existing card(s) of <b>{replacedLabels}</b> and learners' progress for them?<br/>
                    This cannot be undone.
                </>,
                confirmLabel: 'Delete and import',
                danger: true,
            }))
        ) {
            return;
        }
        setBusy(true);
        setError(null);
        try {
            const { message } = await api.post<Message>('/admin/import', { replace: preview.replaced, update });
            go('/admin/cards', { notice: message });
        } catch (e) {
            if (!(e instanceof ApiError)) {
                throw e;
            }
            setError(e.message);
            setBusy(false);
        }
    };

    async function cancel() {
        go('/admin/import', { notice: (await cancelImport()).message });
    }

    return (
        <>
            <h1>Check import</h1>
            <p className="muted">{preview.name}</p>
            {error && <p className="notice danger">{error}</p>}
            {confirmDialog}

            {preview.delete_count > 0 && (
                <p className="notice danger">
                    All <strong>{preview.delete_count}</strong> existing card(s) of {replacedLabels} will be deleted before importing,
                    together with learners' progress for them.
                </p>
            )}

            <ul className="summary">
                <li>
                    <strong>{preview.valid_count}</strong> card(s) will be imported.
                </li>
                {preview.update_count > 0 && (
                    <li>
                        <strong>{preview.update_count}</strong> existing card(s) differ from the file. They are skipped unless you choose to
                        update them.
                    </li>
                )}
                <li>
                    <strong>{preview.duplicate_count}</strong> duplicate(s) will be skipped.
                </li>
                <li>
                    <strong>{preview.error_count}</strong> row(s) with errors will be skipped.
                </li>
            </ul>

            <div className="buttons">
                <button type="button" className="button" disabled={busy || preview.valid_count === 0} onClick={() => runImport(false)}>
                    Import {preview.valid_count} card(s)
                </button>
                {preview.update_count > 0 && (
                    <button type="button" className="button secondary" disabled={busy} onClick={() => runImport(true)}>
                        {preview.valid_count ? `Import ${preview.valid_count} card(s) and update` : 'Update'} {preview.update_count} existing card(s)
                    </button>
                )}
                {preview.format === 'anki' && <Link to="/admin/import/mapping">Change columns</Link>}
                <button type="button" className="link-button" onClick={cancel}>
                    Cancel
                </button>
            </div>

            {replaceable.length > 0 && (
                // A GET form: the ticked pairs go into the URL, which checks the file again.
                <Form method="get" className="card form replace-pairs">
                    <h2>Replace existing cards</h2>
                    <p className="hint">
                        For a clean re-import, delete all saved cards of a language pair first, so the file's cards are imported as new.
                        Learners lose their progress for the deleted cards.
                    </p>
                    {replaceable.map((pair) => (
                        <label key={pair.key} className="check-option">
                            <input type="checkbox" name="replace[]" value={pair.key} defaultChecked={preview.replaced.includes(pair.key)} />
                            Delete all {pair.saved} saved card(s) of {pair.label}
                        </label>
                    ))}
                    <button type="submit" className="button secondary">
                        Check again
                    </button>
                </Form>
            )}

            {preview.errors.length > 0 && (
                <>
                    <h2>Errors</h2>
                    <div className="table-wrap">
                        <table className="table">
                            <thead>
                                <tr>
                                    <th>{rowLabel}</th>
                                    <th>Problem</th>
                                </tr>
                            </thead>
                            <tbody>
                                {preview.errors.map((row, i) => (
                                    <tr key={i}>
                                        <td>{row.row}</td>
                                        <td>{row.message}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <More shown={preview.errors.length} total={preview.error_count} />
                </>
            )}

            {preview.updates.length > 0 && (
                <>
                    <h2>Changes to existing cards</h2>
                    <p className="muted">Saved only if you choose to update existing cards. Words are in the existing card's language order.</p>
                    <div className="table-wrap">
                        <table className="table">
                            <thead>
                                <tr>
                                    <th>{rowLabel}</th>
                                    <th>Word 1</th>
                                    <th>Word 2</th>
                                    <th>Changes</th>
                                </tr>
                            </thead>
                            <tbody>
                                {preview.updates.map((update) => (
                                    <tr key={update.row}>
                                        <td>{update.row}</td>
                                        <td>{update.word1}</td>
                                        <td>{update.word2}</td>
                                        <td>
                                            {Object.entries(update.changes).map(([field, [oldValue, newValue]]) => (
                                                <div key={field}>
                                                    {FIELD_NAMES[field] ?? field}: {limit(String(oldValue ?? '—'), 60)} → {limit(String(newValue ?? '—'), 60)}
                                                </div>
                                            ))}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <More shown={preview.updates.length} total={preview.update_count} />
                </>
            )}

            {preview.duplicates.length > 0 && (
                <>
                    <h2>Duplicates</h2>
                    <p className="muted">These cards already exist with the same values, or appear earlier in the file.</p>
                    <div className="table-wrap">
                        <table className="table">
                            <thead>
                                <tr>
                                    <th>{rowLabel}</th>
                                    <th>Word 1</th>
                                    <th>Word 2</th>
                                </tr>
                            </thead>
                            <tbody>
                                {preview.duplicates.map((duplicate) => (
                                    <tr key={duplicate.row}>
                                        <td>{duplicate.row}</td>
                                        <td>{duplicate.word1}</td>
                                        <td>{duplicate.word2}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <More shown={preview.duplicates.length} total={preview.duplicate_count} />
                </>
            )}
        </>
    );
}

function More({ shown, total }: { shown: number; total: number }) {
    return total > shown ? <p className="muted">…and {total - shown} more.</p> : null;
}
