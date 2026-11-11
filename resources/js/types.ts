// Shapes of the JSON API responses.

export interface User {
    id: number;
    username: string;
    email: string | null;
    role: 'admin' | 'learner';
    session_size: number;
    postpone_sessions: number;
}

export interface Language {
    id: number;
    code: string;
    name: string;
}

export interface LanguagePair {
    /** Lower language ID first, e.g. "3-7". */
    key: string;
    label: string;
}

export interface Card {
    id: number;
    complexity: number;
    lang1_id: number;
    word1: string;
    notes1: string | null;
    examples1: string | null;
    lang2_id: number;
    word2: string;
    notes2: string | null;
    examples2: string | null;
}

export interface CardSaved {
    card: Card;
    message: string;
    warning: string | null;
}

/** Messages shown above a screen after a redirect, passed in the navigation state. */
export interface Notices {
    notice?: string;
    warning?: string | null;
    error?: string;
}

export interface Message {
    message: string;
}

/** A word field of a card on the side of the given language. */
export function sideField(card: Card, field: 'word' | 'notes' | 'examples', languageId: number): string | null {
    const side = card.lang1_id === languageId ? 1 : 2;

    return card[`${field}${side}`];
}
