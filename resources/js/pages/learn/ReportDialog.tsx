import { useEffect, useRef } from 'react';
import { api } from '../../api';
import type { Language } from '../../types';
import { FieldError, useApiForm } from '../../ui';

interface Props {
    sessionId: number;
    cardId: number;
    /** The language of the expected answer, which the correction is written in. */
    to: Language;
    onSent: (message: string) => void;
    onClose: () => void;
}

/**
 * Report a wrong card to the admin (SPECIFICATION.md §6.3). The learner gives the correct
 * translation, a comment, or both. Escape or Cancel closes it.
 */
export function ReportDialog({ sessionId, cardId, to, onSent, onClose }: Props) {
    const dialog = useRef<HTMLDialogElement>(null);
    const form = useApiForm(
        (data) => api.post<{ message: string }>(`/learn/sessions/${sessionId}/report`, { ...Object.fromEntries(data), card_id: cardId }),
        ({ message }) => {
            onSent(message);
            dialog.current?.close();
        },
    );

    useEffect(() => {
        dialog.current?.showModal();
    }, []);

    // Both fields get the same message when both are empty; show it once.
    const commentError = form.errors.comment !== form.errors.correction ? form.errors.comment : undefined;

    return (
        <dialog ref={dialog} className="dialog report-dialog" onClose={onClose}>
            <form method="post" onSubmit={form.onSubmit} className="card form">
                <h1>Report a mistake</h1>
                <p className="hint">The admin gets an email with a link to this card. Give the correct translation, a comment, or both.</p>
                {form.message && <p className="notice danger">{form.message}</p>}

                <label htmlFor="correction">Correct translation ({to.name})</label>
                <input id="correction" name="correction" maxLength={255} lang={to.code} autoComplete="off" autoFocus />
                <FieldError error={form.errors.correction} />

                <label htmlFor="comment">Comment</label>
                <textarea id="comment" name="comment" rows={4} maxLength={2000} />
                <FieldError error={commentError} />

                <div className="buttons">
                    <button type="submit" className="button" disabled={form.busy}>
                        Send
                    </button>
                    <button type="button" className="link-button" onClick={() => dialog.current?.close()}>
                        Cancel
                    </button>
                </div>
            </form>
        </dialog>
    );
}
