import { type ReactNode, useEffect, useState } from 'react';
import { Form, Link, type LoaderFunctionArgs, useLoaderData, useLocation, useSearchParams } from 'react-router';
import { ApiError, api } from '../../api';
import { requireUser } from '../../auth';
import { config, range } from '../../config';
import type { LanguagePair, Message } from '../../types';
import { useGo, useTitle } from '../../ui';

interface CardRow {
    id: number;
    lang1: { code: string; name: string };
    word1: string;
    lang2: { code: string; name: string };
    word2: string;
    complexity: number;
}

interface CardList {
    cards: CardRow[];
    total: number;
    page: number;
    last_page: number;
    pairs: LanguagePair[];
}

export async function loader({ request }: LoaderFunctionArgs) {
    requireUser(request, 'admin');

    return api.get<CardList>('/admin/cards', new URL(request.url).searchParams);
}

/**
 * Card list (SPECIFICATION.md §5.1). Filters, sorting and the page are in the URL,
 * so editing a card and coming back keeps them.
 */
export default function Cards() {
    useTitle('Cards');
    const list = useLoaderData<typeof loader>();
    const [params] = useSearchParams();
    const location = useLocation();
    const go = useGo();
    const [selected, setSelected] = useState<Set<number>>(new Set());
    const [bulkComplexity, setBulkComplexity] = useState('');
    const [error, setError] = useState<string | null>(null);

    // A new page or filter starts with nothing selected.
    useEffect(() => setSelected(new Set()), [list]);

    const here = location.pathname + location.search;
    const sort = params.get('sort') ?? 'id';
    const dir = params.get('dir') === 'asc' ? 'asc' : 'desc';
    const filtered = Boolean(params.get('pair') || params.get('complexity') || params.get('q'));

    /** The current URL with some parameters changed; null removes one. */
    function withParams(changes: Record<string, string | null>): string {
        const next = new URLSearchParams(params);
        for (const [key, value] of Object.entries(changes)) {
            if (value === null) {
                next.delete(key);
            } else {
                next.set(key, value);
            }
        }
        return `?${next}`;
    }

    // Header link that sorts by the column, toggling the direction when it is already sorted by it.
    function sortLink(column: string, label: string) {
        const nextDir = sort === column && dir === 'asc' ? 'desc' : 'asc';
        const arrow = sort === column ? (dir === 'asc' ? ' ↑' : ' ↓') : '';

        return (
            <Link to={withParams({ sort: column, dir: nextDir, page: null })}>
                {label}
                {arrow}
            </Link>
        );
    }

    async function run(action: () => Promise<Message>) {
        setError(null);
        try {
            const { message } = await action();
            go(null, { notice: message });
        } catch (e) {
            if (!(e instanceof ApiError)) {
                throw e;
            }
            setError(Object.values(e.fieldErrors)[0] ?? e.message);
        }
    }

    function deleteCard(card: CardRow) {
        if (confirm(`Delete the card “${card.word1}” – “${card.word2}”? This cannot be undone.`)) {
            run(() => api.delete<Message>(`/admin/cards/${card.id}`));
        }
    }

    function bulk(action: 'complexity' | 'delete') {
        const ids = [...selected];
        if (ids.length === 0) {
            alert('Select at least one card first.');
            return;
        }
        if (action === 'complexity') {
            if (!bulkComplexity) {
                alert('Choose a complexity first.');
                return;
            }
            run(() => api.patch<Message>('/admin/cards', { ids, complexity: Number(bulkComplexity) }));
        } else if (confirm(`Delete ${ids.length} selected card(s)? This cannot be undone.`)) {
            run(() => api.delete<Message>('/admin/cards', { ids }));
        }
    }

    function toggle(id: number, on: boolean) {
        const next = new Set(selected);
        if (on) {
            next.add(id);
        } else {
            next.delete(id);
        }
        setSelected(next);
    }

    const allSelected = list.cards.length > 0 && list.cards.every((card) => selected.has(card.id));

    return (
        <>
            <div className="page-head">
                <h1>Cards</h1>
                <Link to="/admin/cards/new" state={{ back: here }} className="button">
                    Add card
                </Link>
            </div>

            {/* A GET form: the filters become the URL's query, which loads the list. */}
            <Form method="get" className="filters" key={location.search}>
                <select name="pair" aria-label="Language pair" defaultValue={params.get('pair') ?? ''}>
                    <option value="">All language pairs</option>
                    {list.pairs.map((pair) => (
                        <option key={pair.key} value={pair.key}>
                            {pair.label}
                        </option>
                    ))}
                </select>
                <select name="complexity" aria-label="Complexity" defaultValue={params.get('complexity') ?? ''}>
                    <option value="">Any complexity</option>
                    {range(config.complexity).map((value) => (
                        <option key={value} value={value}>
                            Complexity {value}
                        </option>
                    ))}
                </select>
                <input type="search" name="q" defaultValue={params.get('q') ?? ''} placeholder="Search words" aria-label="Search words" />
                <label className="filter-check">
                    <input type="checkbox" name="notes" value="1" defaultChecked={params.has('notes')} /> Search notes
                </label>
                <input type="hidden" name="sort" value={sort} />
                <input type="hidden" name="dir" value={dir} />
                <button type="submit" className="button secondary">
                    Filter
                </button>
                {filtered && <Link to="/admin/cards">Clear</Link>}
            </Form>

            {error && <p className="notice danger">{error}</p>}

            {list.cards.length === 0 ? (
                <p className="muted">
                    {filtered ? (
                        'No cards match these filters.'
                    ) : (
                        <>
                            No cards yet. <Link to="/admin/cards/new">Add a card</Link> or <Link to="/admin/import">import a file</Link>.
                        </>
                    )}
                </p>
            ) : (
                <>
                    <p className="muted">{list.total} card(s)</p>

                    <div className="table-wrap">
                        <table className="table">
                            <thead>
                                <tr>
                                    <th className="check">
                                        <input
                                            type="checkbox"
                                            aria-label="Select all"
                                            checked={allSelected}
                                            onChange={(event) => setSelected(new Set(event.target.checked ? list.cards.map((card) => card.id) : []))}
                                        />
                                    </th>
                                    <th>
                                        {sortLink('lang1', 'Lang 1')}
                                    </th>
                                    <th>
                                        {sortLink('word1', 'Word 1')}
                                    </th>
                                    <th>
                                        {sortLink('lang2', 'Lang 2')}
                                    </th>
                                    <th>
                                        {sortLink('word2', 'Word 2')}
                                    </th>
                                    <th>
                                        {sortLink('complexity', 'Complexity')}
                                    </th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.cards.map((card) => (
                                    <tr key={card.id}>
                                        <td className="check">
                                            <input
                                                type="checkbox"
                                                aria-label={`Select ${card.word1}`}
                                                checked={selected.has(card.id)}
                                                onChange={(event) => toggle(card.id, event.target.checked)}
                                            />
                                        </td>
                                        <td title={card.lang1.name}>{card.lang1.code}</td>
                                        <td lang={card.lang1.code}>{card.word1}</td>
                                        <td title={card.lang2.name}>{card.lang2.code}</td>
                                        <td lang={card.lang2.code}>{card.word2}</td>
                                        <td>{card.complexity}</td>
                                        <td className="actions">
                                            <Link to={`/admin/cards/${card.id}`} state={{ back: here }}>
                                                Edit
                                            </Link>
                                            <button type="button" className="link-button danger" onClick={() => deleteCard(card)}>
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Both buttons act on the ticked rows. */}
                    <div className="filters">
                        <select aria-label="New complexity" value={bulkComplexity} onChange={(event) => setBulkComplexity(event.target.value)}>
                            <option value="">Complexity</option>
                            {range(config.complexity).map((value) => (
                                <option key={value} value={value}>
                                    {value}
                                </option>
                            ))}
                        </select>
                        <button type="button" className="button secondary" onClick={() => bulk('complexity')}>
                            Set complexity
                        </button>
                        <button type="button" className="button danger" onClick={() => bulk('delete')}>
                            Delete selected
                        </button>
                    </div>

                    <Pagination page={list.page} lastPage={list.last_page} link={(page) => withParams({ page: String(page) })} />
                </>
            )}
        </>
    );
}

function Pagination({ page, lastPage, link }: { page: number; lastPage: number; link: (page: number) => string }) {
    if (lastPage <= 1) {
        return null;
    }

    // First, last, and two pages around the current one.
    const pages = [...new Set([1, page - 2, page - 1, page, page + 1, page + 2, lastPage])]
        .filter((p) => p >= 1 && p <= lastPage)
        .sort((a, b) => a - b);

    return (
        <nav aria-label="Pages">
            <ul className="pagination">
                <li className={page === 1 ? 'disabled' : undefined}>
                    {page === 1 ? <span>‹</span> : <Link to={link(page - 1)} aria-label="Previous page">‹</Link>}
                </li>
                {pages.map((p, i) => (
                    <PageItem key={p} gap={i > 0 && p - pages[i - 1] > 1}>
                        <li className={p === page ? 'active' : undefined}>
                            {p === page ? <span aria-current="page">{p}</span> : <Link to={link(p)}>{p}</Link>}
                        </li>
                    </PageItem>
                ))}
                <li className={page === lastPage ? 'disabled' : undefined}>
                    {page === lastPage ? <span>›</span> : <Link to={link(page + 1)} aria-label="Next page">›</Link>}
                </li>
            </ul>
        </nav>
    );
}

function PageItem({ gap, children }: { gap: boolean; children: ReactNode }) {
    return (
        <>
            {gap && (
                <li className="disabled">
                    <span>…</span>
                </li>
            )}
            {children}
        </>
    );
}
