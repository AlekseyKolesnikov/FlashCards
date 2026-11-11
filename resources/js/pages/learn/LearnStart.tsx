import { useState } from 'react';
import { type LoaderFunctionArgs, useLoaderData, useNavigate } from 'react-router';
import { ApiError, api } from '../../api';
import { requireUser } from '../../auth';
import type { Language } from '../../types';
import { useTitle } from '../../ui';

interface Direction {
    from: Language;
    to: Language;
    new: number;
    active: number;
    learned: number;
    /** Cards left in the open session, or null when there is none. */
    cards_left: number | null;
}

export async function loader({ request }: LoaderFunctionArgs) {
    requireUser(request);

    return (await api.get<{ directions: Direction[] }>('/learn')).directions;
}

/** Resumes the direction's open session, or builds a new one (SPECIFICATION.md §6.1). */
export function useStartSession() {
    const navigate = useNavigate();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function start(fromId: number, toId: number) {
        setBusy(true);
        setError(null);
        try {
            const { session_id } = await api.post<{ session_id: number }>('/learn', { from_lang_id: fromId, to_lang_id: toId });
            navigate(`/learn/sessions/${session_id}`);
        } catch (e) {
            if (!(e instanceof ApiError)) {
                throw e;
            }
            setError(Object.values(e.fieldErrors)[0] ?? e.message);
        } finally {
            setBusy(false);
        }
    }

    return { start, busy, error };
}

export default function LearnStart() {
    useTitle('Learn');
    const directions = useLoaderData<typeof loader>();
    const session = useStartSession();

    return (
        <>
            <h1>Learn</h1>
            {session.error && <p className="notice warning">{session.error}</p>}

            {directions.length === 0 ? (
                <p className="muted">There are no cards to learn yet.</p>
            ) : (
                <>
                    <p className="muted">Choose what to translate from and to.</p>

                    <div className="directions">
                        {directions.map((direction) => (
                            <div key={`${direction.from.id}-${direction.to.id}`} className="card direction">
                                <h2>
                                    {direction.from.name} → {direction.to.name}
                                </h2>
                                <p className="counts">
                                    <span>
                                        <strong>{direction.new}</strong> new
                                    </span>
                                    <span>
                                        <strong>{direction.active}</strong> learning
                                    </span>
                                    <span>
                                        <strong>{direction.learned}</strong> learned
                                    </span>
                                </p>

                                <button
                                    type="button"
                                    className={direction.cards_left !== null ? 'button' : 'button secondary'}
                                    disabled={session.busy}
                                    onClick={() => session.start(direction.from.id, direction.to.id)}
                                >
                                    {direction.cards_left !== null ? `Resume (${direction.cards_left} left)` : 'Start session'}
                                </button>
                            </div>
                        ))}
                    </div>
                </>
            )}
        </>
    );
}
