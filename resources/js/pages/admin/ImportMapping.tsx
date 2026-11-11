import { type LoaderFunctionArgs, Navigate, useLoaderData, useNavigate } from 'react-router';
import { api } from '../../api';
import { requireUser } from '../../auth';
import { limit, useApiForm, useGo, useTitle } from '../../ui';
import { cancelImport, loadImportStep } from './importState';

interface Mapping {
    name: string;
    lang1: string;
    lang2: string;
    sample: string[][];
    column_names: string[];
    column_count: number;
    /** Card field per column, or "ignore". */
    mapping: string[];
    fields: string[];
}

export async function loader({ request }: LoaderFunctionArgs) {
    requireUser(request, 'admin');

    return loadImportStep<Mapping>('/admin/import/mapping');
}

/** Choose which Anki column holds which card field (SPECIFICATION.md §5.5). */
export default function ImportMapping() {
    useTitle('Choose columns');
    const data = useLoaderData<typeof loader>();
    const navigate = useNavigate();
    const go = useGo();
    const form = useApiForm(
        (formData) => api.put('/admin/import/mapping', { mapping: formData.getAll('mapping') }),
        () => navigate('/admin/import/preview'),
    );

    if ('failed' in data) {
        return <Navigate to="/admin/import" replace state={{ error: data.failed }} />;
    }

    const labels: Record<string, string> = {
        word1: `Word 1 (${data.lang1})`,
        notes1: `Notes 1 (${data.lang1})`,
        examples1: `Examples 1 (${data.lang1})`,
        word2: `Word 2 (${data.lang2})`,
        notes2: `Notes 2 (${data.lang2})`,
        examples2: `Examples 2 (${data.lang2})`,
    };
    const columns = Array.from({ length: data.column_count }, (_, i) => i);

    async function cancel() {
        go('/admin/import', { notice: (await cancelImport()).message });
    }

    return (
        <>
            <h1>Choose columns</h1>
            <p className="muted">
                {data.name} — the first {data.sample.length} notes are shown. Pick the card field for each column; word 1 and word 2 are
                required.
            </p>
            {form.errors.mapping && <p className="notice danger">{form.errors.mapping}</p>}
            {form.message && <p className="notice danger">{form.message}</p>}

            <form method="post" onSubmit={form.onSubmit}>
                <div className="table-wrap">
                    <table className="table">
                        <thead>
                            <tr>
                                {columns.map((column) => (
                                    <th key={column}>
                                        {data.column_names[column] && <div className="muted">{data.column_names[column]}</div>}
                                        <select name="mapping" aria-label={`Column ${column + 1}`} defaultValue={data.mapping[column] ?? 'ignore'}>
                                            <option value="ignore">Ignore</option>
                                            {data.fields.map((field) => (
                                                <option key={field} value={field}>
                                                    {labels[field]}
                                                </option>
                                            ))}
                                        </select>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {data.sample.map((fields, row) => (
                                <tr key={row}>
                                    {columns.map((column) => (
                                        <td key={column} className="sample">
                                            {limit(fields[column], 120)}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="buttons">
                    <button type="submit" className="button" disabled={form.busy}>
                        Next: check file
                    </button>
                    <button type="button" className="link-button" onClick={cancel}>
                        Cancel import
                    </button>
                </div>
            </form>
        </>
    );
}
