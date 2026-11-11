import { useEffect, useRef } from 'react';
import { Link } from 'react-router';
import { api } from '../../api';
import { config, range } from '../../config';
import type { Card, CardSaved, Language } from '../../types';
import { FieldError, useApiData, useApiForm } from '../../ui';

interface Props {
    /** The card to edit; a new card without it. */
    cardId?: number;
    /** Values for a new card, e.g. the previous card's languages and complexity. */
    defaults?: Partial<Card>;
    onSaved: (saved: CardSaved, another: boolean) => void;
    onCancel: () => void;
}

/**
 * Add or edit a card (SPECIFICATION.md §5.2). It loads its own data, so it also works in a dialog.
 */
export default function CardForm({ cardId, defaults, onSaved, onCancel }: Props) {
    const languages = useApiData<{ languages: Language[] }>('/admin/languages');
    const loaded = useApiData<{ card: Card }>(cardId ? `/admin/cards/${cardId}` : null);
    const form = useApiForm(
        (data) => {
            const body = Object.fromEntries(data);
            return cardId ? api.put<CardSaved>(`/admin/cards/${cardId}`, body) : api.post<CardSaved>('/admin/cards', body);
        },
        (saved, data) => onSaved(saved, data.has('another')),
    );

    const failed = languages.error ?? loaded.error;
    if (failed) {
        return <p className="notice danger">{failed.status === 404 ? 'This card no longer exists.' : failed.message}</p>;
    }
    if (!languages.data || (cardId && !loaded.data)) {
        return <p className="muted">Loading…</p>;
    }

    const card: Partial<Card> = loaded.data?.card ?? defaults ?? {};
    const complexity = card.complexity ?? config.complexity.default;

    return (
        <form method="post" onSubmit={form.onSubmit} className="card form">
            <h1>{cardId ? 'Edit card' : 'Add card'}</h1>
            {form.message && <p className="notice danger">{form.message}</p>}

            {languages.data.languages.length < 2 && (
                <p className="notice warning">
                    A card needs two languages. <Link to="/admin/languages">Add languages</Link> first.
                </p>
            )}

            <div className="sides">
                {([1, 2] as const).map((side) => (
                    <fieldset key={side} className="side">
                        <legend>Side {side}</legend>

                        <label htmlFor={`lang${side}_id`}>Language</label>
                        <select id={`lang${side}_id`} name={`lang${side}_id`} required defaultValue={card[`lang${side}_id`] ?? ''}>
                            <option value="">Choose…</option>
                            {languages.data!.languages.map((language) => (
                                <option key={language.id} value={language.id}>
                                    {language.name} ({language.code})
                                </option>
                            ))}
                        </select>
                        <FieldError error={form.errors[`lang${side}_id`]} />

                        <label htmlFor={`word${side}`}>Word</label>
                        <input
                            id={`word${side}`}
                            name={`word${side}`}
                            required
                            maxLength={255}
                            defaultValue={card[`word${side}`] ?? ''}
                            autoFocus={side === 1}
                        />
                        <p className="hint">Exactly what learners must type. Put remarks in Notes, not in brackets here.</p>
                        <FieldError error={form.errors[`word${side}`]} />

                        <label htmlFor={`notes${side}`}>Notes</label>
                        <input id={`notes${side}`} name={`notes${side}`} maxLength={255} defaultValue={card[`notes${side}`] ?? ''} />
                        <p className="hint">Short remarks about this word, e.g. “female, plural”.</p>
                        <FieldError error={form.errors[`notes${side}`]} />

                        <label htmlFor={`examples${side}`}>Examples</label>
                        <textarea
                            id={`examples${side}`}
                            name={`examples${side}`}
                            rows={4}
                            maxLength={2000}
                            defaultValue={card[`examples${side}`] ?? ''}
                        />
                        <FieldError error={form.errors[`examples${side}`]} />
                    </fieldset>
                ))}
            </div>

            <fieldset className="choices">
                <legend>Complexity</legend>
                <div className="choice-row">
                    {range(config.complexity).map((value) => (
                        <label key={value}>
                            <input type="radio" name="complexity" value={value} required defaultChecked={complexity === value} />
                            <span>{value}</span>
                        </label>
                    ))}
                </div>
            </fieldset>
            <p className="hint">Lower values are learned first.</p>
            <FieldError error={form.errors.complexity} />

            {cardId && <p className="hint">Changing the card's languages resets learners' progress for it.</p>}

            <div className="buttons">
                <button type="submit" className="button" disabled={form.busy}>
                    Save
                </button>
                {!cardId && (
                    <button type="submit" name="another" value="1" className="button secondary" disabled={form.busy}>
                        Save and add another
                    </button>
                )}
                <button type="button" className="link-button" onClick={onCancel}>
                    Cancel
                </button>
            </div>
        </form>
    );
}

/**
 * The card form over another screen, e.g. the learn result (SPECIFICATION.md §6.3).
 * Escape or Cancel closes it.
 */
export function CardDialog({ cardId, onSaved, onClose }: { cardId: number; onSaved: (saved: CardSaved) => void; onClose: () => void }) {
    const dialog = useRef<HTMLDialogElement>(null);

    useEffect(() => {
        dialog.current?.showModal();
    }, []);

    return (
        <dialog ref={dialog} className="dialog" onClose={onClose}>
            <CardForm
                cardId={cardId}
                onSaved={(saved) => {
                    onSaved(saved);
                    dialog.current?.close();
                }}
                onCancel={() => dialog.current?.close()}
            />
        </dialog>
    );
}
