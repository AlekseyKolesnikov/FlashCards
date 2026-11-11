import { SubmitEvent, useState } from 'react';
import { Link, type LoaderFunctionArgs, useLoaderData, useRevalidator } from 'react-router';
import { ApiError, api } from '../../api';
import { requireUser, useUser } from '../../auth';
import { type CardSaved, type Language, sideField } from '../../types';
import { plural, useTitle } from '../../ui';
import { CardDialog } from '../admin/CardForm';
import { useStartSession } from './LearnStart';

interface SessionInfo {
    id: number;
    seq: number;
    open: boolean;
    from: Language;
    to: Language;
    cards_left: number;
}

/** Only the "from" side of the card (SPECIFICATION.md §6.2). */
interface Question {
    card_id: number;
    word: string;
    notes: string | null;
    examples: string | null;
}

interface Summary {
    total: number;
    first_try: number;
    mistakes: { card_id: number; word: string; answer: string; wrong_count: number }[];
}

interface SessionData {
    session: SessionInfo;
    question?: Question;
    summary?: Summary;
}

interface AnswerResult {
    card_id: number;
    /** Null for "I don't know". */
    typed: string | null;
    correct: boolean;
    cards_left: number;
    prompt: string;
    expected: string;
    notes: string | null;
    examples: string | null;
}

export async function loader({ request, params }: LoaderFunctionArgs) {
    requireUser(request);

    return api.get<SessionData>(`/learn/sessions/${params.id}`);
}

/**
 * Question, result and summary screens of a session (SPECIFICATION.md §6.2–6.4). The result is
 * kept here while the next question loads in the background.
 */
export default function LearnSession() {
    const data = useLoaderData<typeof loader>();
    const revalidator = useRevalidator();
    const user = useUser();
    const [result, setResult] = useState<AnswerResult | null>(null);
    const [notice, setNotice] = useState<string | null>(null);
    const [editing, setEditing] = useState(false);
    // Counts answers, so the answer field starts empty even when the same card comes again.
    const [round, setRound] = useState(0);
    const { session } = data;
    useTitle(data.summary ? 'Session complete' : 'Learn');

    function answered(answer: AnswerResult) {
        setResult(answer);
        setNotice(null);
        setRound(round + 1);
        revalidator.revalidate();
    }

    // A repeated answer was ignored (§6.6): show the current question.
    function conflict(message: string) {
        setNotice(message);
        setRound(round + 1);
        revalidator.revalidate();
    }

    // Shows the edited card on the result screen.
    function cardSaved({ card, warning }: CardSaved) {
        setResult((current) =>
            current && {
                ...current,
                prompt: sideField(card, 'word', session.from.id) ?? current.prompt,
                expected: sideField(card, 'word', session.to.id) ?? current.expected,
                notes: sideField(card, 'notes', session.to.id),
                examples: sideField(card, 'examples', session.to.id),
            },
        );
        setNotice(warning ?? 'Card saved.');
        revalidator.revalidate();
    }

    let content = null;
    if (result) {
        content = (
            <ResultView
                session={session}
                result={result}
                onNext={() => {
                    setResult(null);
                    setNotice(null);
                }}
                onEdit={user?.role === 'admin' ? () => setEditing(true) : null}
            />
        );
    } else if (revalidator.state !== 'idle') {
        content = <p className="muted study">Loading…</p>;
    } else if (data.summary) {
        content = <SummaryView session={session} summary={data.summary} />;
    } else if (data.question) {
        content = <QuestionView key={round} session={session} question={data.question} onAnswered={answered} onConflict={conflict} />;
    }

    const cardsLeft = result ? result.cards_left : session.open ? session.cards_left : null;

    return (
        <>
            <p className="learn-header muted">
                <Link to="/learn">Learn</Link> · {session.from.name} → {session.to.name} · session {session.seq}
                {cardsLeft !== null && (
                    <>
                        {' · '}
                        <span className="cards-left">
                            {cardsLeft} {plural(cardsLeft, 'card', 'cards')} left
                        </span>
                    </>
                )}
            </p>
            {notice && <p className="notice warning study">{notice}</p>}
            {content}
            {editing && result && <CardDialog cardId={result.card_id} onSaved={cardSaved} onClose={() => setEditing(false)} />}
        </>
    );
}

function QuestionView({
    session,
    question,
    onAnswered,
    onConflict,
}: {
    session: SessionInfo;
    question: Question;
    onAnswered: (result: AnswerResult) => void;
    onConflict: (message: string) => void;
}) {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function submit(event: SubmitEvent<HTMLFormElement>) {
        event.preventDefault();
        // The button is disabled too; this also stops a second Enter (§6.6).
        if (busy) {
            return;
        }

        const dontKnow = event.nativeEvent.submitter?.getAttribute('name') === 'dont_know';
        const answer = String(new FormData(event.currentTarget).get('answer') ?? '');
        setBusy(true);

        try {
            const { result } = await api.post<{ result: AnswerResult }>(`/learn/sessions/${session.id}/answer`, {
                card_id: question.card_id,
                answer: dontKnow ? null : answer,
                dont_know: dontKnow,
            });
            onAnswered(result);
        } catch (e) {
            if (!(e instanceof ApiError)) {
                throw e;
            }
            if (e.status === 409) {
                onConflict(e.message);
                return;
            }
            setError(e.fieldErrors.answer ?? e.message);
            setBusy(false);
        }
    }

    return (
        <div className="card study">
            <p className="prompt" lang={session.from.code}>
                {question.word}
            </p>
            {question.notes && (
                <p className="prompt-notes" lang={session.from.code}>
                    {question.notes}
                </p>
            )}
            {question.examples && (
                <p className="examples" lang={session.from.code}>
                    {question.examples}
                </p>
            )}

            <form method="post" onSubmit={submit} className="form answer-form">
                <label htmlFor="answer">{session.to.name}</label>
                <input
                    id="answer"
                    name="answer"
                    required
                    autoFocus
                    maxLength={255}
                    lang={session.to.code}
                    autoComplete="off"
                    autoCapitalize="off"
                    autoCorrect="off"
                    spellCheck={false}
                />
                {error && <p className="error">{error}</p>}

                <div className="buttons">
                    <button type="submit" className="button" disabled={busy}>
                        Submit
                    </button>
                    <button type="submit" name="dont_know" value="1" formNoValidate className="button secondary" disabled={busy}>
                        I don’t know
                    </button>
                </div>
            </form>
        </div>
    );
}

function ResultView({
    session,
    result,
    onNext,
    onEdit,
}: {
    session: SessionInfo;
    result: AnswerResult;
    onNext: () => void;
    onEdit: (() => void) | null;
}) {
    return (
        <div className="card study">
            {result.correct ? (
                <p className="mark correct">✔ Correct</p>
            ) : (
                <p className="mark wrong">✘ {result.typed === null ? 'Not known' : 'Incorrect'}</p>
            )}

            <p className="muted" lang={session.from.code}>
                {result.prompt}
            </p>
            <p className="prompt" lang={session.to.code}>
                {result.expected}
            </p>

            {!result.correct && result.typed !== null && (
                <p className="your-answer">
                    Your answer: <span lang={session.to.code}>{result.typed}</span>
                </p>
            )}

            {result.notes && (
                <p className="prompt-notes" lang={session.to.code}>
                    {result.notes}
                </p>
            )}
            {result.examples && (
                <p className="examples" lang={session.to.code}>
                    {result.examples}
                </p>
            )}

            <div className="buttons">
                {/* Focused, so Enter moves on. A held Enter key from the answer must not skip this screen. */}
                <button
                    type="button"
                    className="button"
                    autoFocus
                    onClick={onNext}
                    onKeyDown={(event) => event.key === 'Enter' && event.repeat && event.preventDefault()}
                >
                    {result.cards_left === 0 ? 'See results' : 'Next'}
                </button>
                {onEdit && (
                    <button type="button" className="link-button" onClick={onEdit}>
                        Edit card
                    </button>
                )}
            </div>
        </div>
    );
}

function SummaryView({ session, summary }: { session: SessionInfo; summary: Summary }) {
    const next = useStartSession();

    return (
        <div className="card study">
            <h1>Session complete</h1>
            <p>
                <strong>{summary.first_try}</strong> of <strong>{summary.total}</strong> {plural(summary.total, 'card', 'cards')} answered
                correctly on the first try.
            </p>

            {summary.mistakes.length > 0 && (
                <>
                    <h2>Words to repeat</h2>
                    <p className="muted">These come back in your next sessions.</p>
                    <div className="table-wrap">
                        <table className="table">
                            <thead>
                                <tr>
                                    <th>{session.from.name}</th>
                                    <th>{session.to.name}</th>
                                    <th>Wrong answers</th>
                                </tr>
                            </thead>
                            <tbody>
                                {summary.mistakes.map((mistake) => (
                                    <tr key={mistake.card_id}>
                                        <td lang={session.from.code}>{mistake.word}</td>
                                        <td lang={session.to.code}>{mistake.answer}</td>
                                        <td>{mistake.wrong_count}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}

            {next.error && <p className="notice warning">{next.error}</p>}
            <div className="buttons">
                <button
                    type="button"
                    className="button"
                    autoFocus
                    disabled={next.busy}
                    onClick={() => next.start(session.from.id, session.to.id)}
                >
                    Start next session
                </button>
                <Link to="/learn">Back to start page</Link>
            </div>
        </div>
    );
}
